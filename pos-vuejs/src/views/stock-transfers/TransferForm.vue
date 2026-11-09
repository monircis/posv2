<template>
  <div>
    <div style="padding:20px 24px 16px;background:#ffffff;border-bottom:1px solid #dfe6ee">
      <div style="display:flex;align-items:center;gap:7px;font-size:11.5px;color:#7d8b9c;margin-bottom:8px"><RouterLink :to="{ name: 'transfers' }" style="color:var(--c-primary)">Stock transfers</RouterLink><span style="color:#c2cedb">/</span><span style="color:#0f1c2b;font-weight:500">{{ editId ? 'Edit ' + reference : 'New transfer' }}</span></div>
      <div style="display:flex;align-items:flex-start;gap:20px;flex-wrap:wrap">
        <div>
          <h2 style="margin:0 0 3px;font-size:22px;font-weight:700;letter-spacing:-0.018em">{{ editId ? 'Edit ' + reference : 'New stock transfer' }}</h2>
          <div style="font-size:12.5px;color:#5d6b7d">Stock leaves the source when you send, and lands when the destination receives.</div>
        </div>
        <div style="margin-left:auto;display:flex;gap:8px;align-items:center">
          <div @click="cancelForm" :style="buttonStyle" v-pseudo:hover="'background:#f2f6fa'">Cancel</div>
          <div @click="save(false)" :style="buttonStyle + ';font-weight:600;color:var(--c-primary-text)'" v-pseudo:hover="'background:#f2f6fa'">{{ saving ? 'Saving…' : 'Save draft' }}</div>
          <div @click="save(true)" :style="'height:34px;padding:0 15px;border-radius:8px;display:flex;align-items:center;font-size:12.5px;font-weight:600;color:#ffffff;cursor:pointer;background:' + (errors.length ? '#9fbde0' : 'var(--c-primary)')">{{ saving ? 'Saving…' : 'Send transfer' }}</div>
        </div>
      </div>
    </div>
    <div style="padding:20px 24px 32px;display:grid;grid-template-columns:minmax(0,1fr) 330px;gap:16px;align-items:start">
      <div style="display:grid;gap:14px">
        <div v-if="saveError" style="background:#fdeaea;color:#b02020;border-radius:9px;padding:10px 13px;font-size:12.5px">{{ saveError }}</div>
        <div style="background:#ffffff;border:1px solid #dfe6ee;border-radius:12px;padding:17px 19px">
          <div style="display:grid;grid-template-columns:1fr 36px 1fr;gap:10px;align-items:end">
            <div>
              <label :style="labelStyle">From <span style="color:#d93a3a">*</span></label>
              <select v-model.number="form.from" @change="loadCatalog" :disabled="!!userBranchId" :style="selectStyle">
                <option v-for="b in activeBranches" :key="b.id" :value="b.id">{{ b.name }}{{ b.address ? ' · ' + b.address : '' }}</option>
              </select>
            </div>
            <div v-if="!userBranchId" @click="swap" title="Swap" style="height:38px;border-radius:8px;border:1px solid #dfe6ee;display:grid;place-items:center;cursor:pointer;color:#5d6b7d;font-size:14px" v-pseudo:hover="'background:#f2f6fa;color:var(--c-primary)'">⇆</div>
            <div v-else style="height:38px;display:grid;place-items:center;color:#9aa7b6">→</div>
            <div>
              <label :style="labelStyle">To <span style="color:#d93a3a">*</span></label>
              <select v-model.number="form.to" @change="loadCatalog" :style="selectStyle + (sameBranch ? ';border-color:#d93a3a' : '')">
                <option v-for="b in activeBranches" :key="b.id" :value="b.id">{{ b.name }}{{ b.address ? ' · ' + b.address : '' }}</option>
              </select>
            </div>
          </div>
          <div v-if="sameBranch" style="font-size:11.5px;color:#b02020;margin-top:7px">Source and destination must be different branches.</div>
          <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px;margin-top:14px">
            <div><label :style="labelStyle">Reference</label> <div style="height:36px;border:1px solid #e4eaf1;border-radius:8px;padding:0 11px;display:flex;align-items:center;font-family:'IBM Plex Mono',monospace;font-size:12.5px;background:#f7fafc;color:#4a5866">{{ reference }}</div></div>
            <div><label :style="labelStyle">Date</label> <input v-model="form.date" type="date" style="width:100%;height:36px;border:1px solid #dfe6ee;border-radius:8px;padding:0 11px;font-family:'IBM Plex Mono',monospace;font-size:12.5px" /></div>
            <div><label :style="labelStyle">Shipping cost</label> <input v-model.number="form.shipping" type="number" min="0" step="0.01" placeholder="0.00" style="width:100%;height:36px;border:1px solid #dfe6ee;border-radius:8px;padding:0 11px;font-family:'IBM Plex Mono',monospace;font-size:12.5px;outline:none;text-align:right" v-pseudo:focus="'border-color:var(--c-primary)'" /></div>
          </div>
        </div>
        <div style="background:#ffffff;border:1px solid #dfe6ee;border-radius:12px;overflow:hidden">
          <div style="padding:13px 16px;border-bottom:1px solid #eaeff5;position:relative">
            <div style="position:relative"><div style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:#9aa7b6;font-size:13px">⚲</div> <input v-model="search" @keydown.enter.prevent="addFromSearch" @keydown.esc="search = ''" placeholder="Scan or search a product to add a line" style="width:100%;height:40px;border:1px solid var(--c-primary);border-radius:9px;background:var(--c-primary-tint);padding:0 12px 0 34px;font-family:'Public Sans',sans-serif;font-size:13px;outline:none" /></div>
            <div v-if="search.trim()" style="position:absolute;left:16px;right:16px;top:56px;background:#ffffff;border:1px solid #dfe6ee;border-radius:10px;box-shadow:0 16px 32px -16px rgba(15,28,43,.35);z-index:5;overflow:hidden">
              <div v-for="p in results" :key="keyOf(p)" @click="addLine(p)" :style="'display:flex;align-items:center;gap:10px;padding:9px 14px;border-bottom:1px solid #f0f4f8;cursor:pointer;' + (p.from_stock > 0 ? '' : 'opacity:.55')" v-pseudo:hover="'background:#f0f6fd'">
                <div style="flex:1;min-width:0">
                  <div style="font-size:13px;font-weight:500">{{ p.name }}</div>
                  <div style="font-family:'IBM Plex Mono',monospace;font-size:10.5px;color:#8b98a8">SKU {{ p.sku }}</div>
                </div>
                <div :style="'font-family:\'IBM Plex Mono\',monospace;font-size:11.5px;' + (p.from_stock > 0 ? 'color:#5d6b7d' : 'color:#b02020')">{{ p.from_stock > 0 ? qtyUnit(p.from_stock, p.unit) + ' at ' + fromName : 'None at ' + fromName }}</div>
              </div>
              <div v-if="!results.length" style="padding:14px 16px;font-size:12.5px;color:#7d8b9c">No product sold at both branches matches.</div>
            </div>
          </div>
          <div v-if="suggestions.length" style="padding:10px 16px;border-bottom:1px solid #f0f4f8;background:#fbfcfe;display:flex;align-items:center;gap:8px;flex-wrap:wrap">
            <div style="font-size:11.5px;color:#8a5300;font-weight:600;margin-right:4px">Low at {{ toName }}:</div>
            <div v-for="p in suggestions" :key="keyOf(p)" @click="addLine(p)" style="height:28px;padding:0 10px;border-radius:7px;border:1px solid #f0d9ac;background:#fffaf0;display:flex;align-items:center;gap:6px;font-size:12px;color:#8a5300;cursor:pointer" v-pseudo:hover="'background:#fdf1dd'"><span style="font-weight:700">+</span>{{ p.name }}<span style="font-family:'IBM Plex Mono',monospace;font-size:10.5px;opacity:.8">{{ qtyUnit(p.to_stock, p.unit) }} left</span></div>
          </div>
          <div style="display:grid;grid-template-columns:28px minmax(0,2fr) 1fr 1.1fr 1fr 0.9fr 30px;gap:10px;padding:9px 16px;background:#f6f9fc;border-bottom:1px solid #dfe6ee;font-family:'IBM Plex Mono',monospace;font-size:9.5px;letter-spacing:0.07em;text-transform:uppercase;color:#5d6b7d">
            <div>#</div>
            <div>Product</div>
            <div style="text-align:right">{{ fromName }} has</div>
            <div style="text-align:center">Send</div>
            <div style="text-align:right">{{ toName }} after</div>
            <div style="text-align:right">Value</div>
            <div></div>
          </div>
          <div v-for="(l, i) in lines" :key="keyOf(l)" style="display:grid;grid-template-columns:28px minmax(0,2fr) 1fr 1.1fr 1fr 0.9fr 30px;gap:10px;padding:9px 16px;border-bottom:1px solid #f0f4f8;align-items:center">
            <div style="font-family:'IBM Plex Mono',monospace;font-size:11px;color:#9aa7b6">{{ i + 1 }}</div>
            <div style="min-width:0">
              <div style="font-size:13px;font-weight:500;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">{{ l.name }}</div>
              <div v-if="lineProblem(l)" style="font-size:11px;color:#b02020;font-weight:600">{{ lineProblem(l) }}</div>
              <div v-else style="font-family:'IBM Plex Mono',monospace;font-size:10.5px;color:#8b98a8">SKU {{ l.sku }} · {{ money(l.cost) }}{{ l.unit ? ' / ' + l.unit : '' }}</div>
            </div>
            <div style="font-family:'IBM Plex Mono',monospace;font-size:12px;text-align:right;color:#4a5866">{{ qtyUnit(fromStock(l), l.unit) }}</div>
            <div style="display:flex;align-items:center;justify-content:center">
              <div :style="'display:flex;align-items:center;height:32px;border-radius:8px;background:#ffffff;border:1px solid ' + (lineProblem(l) ? '#d93a3a' : '#dfe6ee')"><input v-model.number="l.qty" type="number" min="0" step="any" style="width:64px;height:100%;border:none;background:transparent;text-align:right;font-family:'IBM Plex Mono',monospace;font-size:12.5px;font-weight:600;outline:none;padding:0 4px 0 0" /> <div style="font-size:11px;color:#8b98a8;padding-right:9px">{{ l.unit }}</div></div>
            </div>
            <div :style="'font-family:\'IBM Plex Mono\',monospace;font-size:12px;text-align:right;' + (toStock(l) + (Number(l.qty) || 0) < l.alert_qty ? 'color:#8a5300' : 'color:#0f6b43;font-weight:600')">{{ qtyUnit(toStock(l) + (Number(l.qty) || 0), l.unit) }}</div>
            <div style="font-family:'IBM Plex Mono',monospace;font-size:12.5px;text-align:right">{{ money(lineValue(l)) }}</div>
            <div @click="lines.splice(i, 1)" style="width:26px;height:26px;border-radius:6px;display:grid;place-items:center;color:#b3c0cf;cursor:pointer;font-size:14px" v-pseudo:hover="'background:#fdeaea;color:#b02020'">×</div>
          </div>
          <div v-if="!lines.length" style="padding:30px 20px;text-align:center;font-size:12.5px;color:#7d8b9c">No lines yet. Search above, or add a product that is running low at the destination.</div>
        </div>
        <div style="background:#ffffff;border:1px solid #dfe6ee;border-radius:12px;padding:15px 19px"><label :style="labelStyle">Notes for the receiving branch</label> <textarea v-model="form.notes" placeholder="e.g. Two crates, van 2, driver Rafi" style="width:100%;min-height:64px;border:1px solid #dfe6ee;border-radius:8px;padding:9px 11px;font-family:'Public Sans',sans-serif;font-size:13px;outline:none;resize:vertical" v-pseudo:focus="'border-color:var(--c-primary)'"></textarea></div>
      </div>
      <div style="display:grid;gap:14px;position:sticky;top:20px">
        <div style="background:#ffffff;border:1px solid #dfe6ee;border-radius:12px;padding:17px 19px">
          <div style="font-size:13px;font-weight:700;margin-bottom:12px">Summary</div>
          <div style="display:grid;gap:8px;font-size:12.5px">
            <div style="display:flex">
              <div style="flex:1;color:#5d6b7d">Lines</div>
              <div style="font-family:'IBM Plex Mono',monospace">{{ lines.length }}</div>
            </div>
            <div style="display:flex">
              <div style="flex:1;color:#5d6b7d">Stock value at cost</div>
              <div style="font-family:'IBM Plex Mono',monospace">{{ money(stockValue) }}</div>
            </div>
            <div style="display:flex">
              <div style="flex:1;color:#5d6b7d">Shipping</div>
              <div style="font-family:'IBM Plex Mono',monospace">{{ money(form.shipping) }}</div>
            </div>
          </div>
          <div style="display:flex;align-items:baseline;border-top:1px solid #eaeff5;margin-top:12px;padding-top:12px">
            <div style="flex:1;font-size:13px;font-weight:600">Transfer total</div>
            <div style="font-family:'IBM Plex Mono',monospace;font-size:20px;font-weight:600;letter-spacing:-0.02em">{{ money(stockValue + (Number(form.shipping) || 0)) }}</div>
          </div>
        </div>
        <div style="background:#ffffff;border:1px solid #dfe6ee;border-radius:12px;padding:17px 19px">
          <div style="font-size:13px;font-weight:700;margin-bottom:4px">Stock impact</div>
          <div style="font-size:11.5px;color:#7d8b9c;margin-bottom:12px">What changes when you send, then receive</div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">
            <div style="background:#fdf4f4;border-radius:10px;padding:10px 12px">
              <div style="font-size:11px;color:#b02020;font-weight:600">{{ fromName }}</div>
              <div style="font-family:'IBM Plex Mono',monospace;font-size:15px;font-weight:600;color:#b02020;margin-top:3px">−{{ money(stockValue) }}</div>
              <div style="font-size:10.5px;color:#c77a7a;margin-top:2px">on send</div>
            </div>
            <div style="background:#e7f6ee;border-radius:10px;padding:10px 12px">
              <div style="font-size:11px;color:#0f6b43;font-weight:600">{{ toName }}</div>
              <div style="font-family:'IBM Plex Mono',monospace;font-size:15px;font-weight:600;color:#0f6b43;margin-top:3px">+{{ money(stockValue) }}</div>
              <div style="font-size:10.5px;color:#4f9a74;margin-top:2px">on receive</div>
            </div>
          </div>
        </div>
        <div v-if="lines.length && errors.length" style="background:#fdeaea;border:1px solid #f3cfcf;border-radius:12px;padding:12px 15px;display:grid;gap:5px">
          <div v-for="e in errors" :key="e" style="font-size:12px;color:#b02020;display:flex;gap:7px"><span style="font-weight:700">×</span>{{ e }}</div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
// New or edit stock transfer (/stock-transfers/new, /stock-transfers/new?id=5).
// API: GET /api/stock-transfers/form-data, GET /api/stock-transfers/products?from_branch_id=&to_branch_id=,
// POST /api/stock-transfers, PUT /api/stock-transfers/{id}
import { computed, onMounted, reactive, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { api } from '../../api/http.js';
import { branches, loadBranches } from '../../api/branches.js';
import { money, today, qtyUnit, userBranchId } from '../../api/transfers.js';

const route = useRoute();
const router = useRouter();

const labelStyle = 'display:block;font-size:12px;font-weight:600;margin-bottom:5px';
const selectStyle = "width:100%;height:38px;border:1px solid #dfe6ee;border-radius:8px;padding:0 10px;font-family:'Public Sans',sans-serif;font-size:13px;background:#ffffff;outline:none;cursor:pointer";
const buttonStyle = 'height:34px;padding:0 13px;border-radius:8px;border:1px solid #dfe6ee;display:flex;align-items:center;font-size:12.5px;font-weight:500;color:#4a5866;cursor:pointer';

const editId = Number(route.query.id) || 0;

const form = reactive({ from: 0, to: 0, date: today(), shipping: 0, notes: '' });
const reference = ref('');
const lines = ref<any[]>([]);
const catalog = ref<any[]>([]); // products sold at both branches, with the stock at each
const search = ref('');
const saving = ref(false);
const saveError = ref('');

const activeBranches = computed(() => branches.value.filter(b => b.active || b.id === form.from || b.id === form.to));
const sameBranch = computed(() => !!form.from && form.from === form.to);
const fromName = computed(() => branchName(form.from));
const toName = computed(() => branchName(form.to));
const stockValue = computed(() => lines.value.reduce((sum, l) => sum + lineValue(l), 0));

function branchName(id: number) {
  const b = branches.value.find(x => x.id === id);
  return b ? b.name : '';
}

// The same line can be one product, or one variant of a product.
function keyOf(row: any) {
  return row.product_id + '-' + (row.variant_id || 0);
}
function catalogRow(line: any) {
  return catalog.value.find(p => keyOf(p) === keyOf(line));
}
function fromStock(line: any) {
  const row = catalogRow(line);
  return row ? row.from_stock : 0;
}
function toStock(line: any) {
  const row = catalogRow(line);
  return row ? row.to_stock : 0;
}
function lineValue(line: any) {
  return (Number(line.qty) || 0) * line.cost;
}

// What is wrong with a line, for sending. Empty when it is fine.
function lineProblem(line: any) {
  const qty = Number(line.qty) || 0;
  if (!catalogRow(line)) return 'Not sold at both branches';
  if (qty <= 0) return 'Enter a quantity';
  if (qty > fromStock(line)) return 'Only ' + qtyUnit(fromStock(line), line.unit) + ' available';
  if (!catalogRow(line).allow_decimals && !Number.isInteger(qty)) return 'Whole units only';
  return '';
}

const errors = computed(() => {
  const list: string[] = [];
  if (sameBranch.value) list.push('Pick two different branches');
  if (!lines.value.length) list.push('Add at least one product');
  for (const l of lines.value) {
    if (lineProblem(l)) list.push(l.name + ': ' + lineProblem(l));
  }
  return list;
});

const results = computed(() => {
  const text = search.value.trim().toLowerCase();
  if (!text) return [];
  return catalog.value.filter(p => p.name.toLowerCase().includes(text) || p.sku.toLowerCase().startsWith(text) || p.barcode === search.value.trim()).slice(0, 6);
});

// Low at the destination, in stock at the source, not on the transfer yet.
const suggestions = computed(() => catalog.value.filter(p =>
  p.to_stock < p.alert_qty && p.from_stock > 0 && !lines.value.some(l => keyOf(l) === keyOf(p))
).slice(0, 4));

async function loadCatalog() {
  const from = form.from;
  const to = form.to;
  catalog.value = [];
  if (!from || !to || from === to) return;
  const rows = await api.get('/stock-transfers/products?from_branch_id=' + from + '&to_branch_id=' + to).catch(() => []);
  if (from === form.from && to === form.to) catalog.value = rows; // skip a late answer for branches no longer picked
}

function swap() {
  const from = form.from;
  form.from = form.to;
  form.to = from;
  loadCatalog();
}

function addLine(row: any) {
  if (lines.value.some(l => keyOf(l) === keyOf(row))) {
    saveError.value = row.name + ' is already on this transfer.';
    search.value = '';
    return;
  }
  if (row.from_stock <= 0) {
    saveError.value = row.name + ' is out of stock at ' + fromName.value + '.';
    return;
  }
  saveError.value = '';

  // Enough to bring the destination up to twice its alert quantity, if the source has it.
  const wanted = Math.max(1, row.alert_qty * 2 - row.to_stock);
  const qty = Math.min(row.from_stock, wanted);

  lines.value.push({
    product_id: row.product_id, variant_id: row.variant_id, name: row.name, sku: row.sku, unit: row.unit,
    cost: row.cost, alert_qty: row.alert_qty, qty: row.allow_decimals ? qty : Math.floor(qty)
  });
  search.value = '';
}

// Enter: an exact SKU or barcode (a scan), or the only result.
function addFromSearch() {
  const text = search.value.trim();
  const exact = catalog.value.find(p => p.sku === text || p.barcode === text);
  if (exact) addLine(exact);
  else if (results.value.length === 1) addLine(results.value[0]);
}

function cancelForm() {
  if (editId) router.push({ name: 'transfers-details', query: { id: editId } });
  else router.push({ name: 'transfers' });
}

async function save(send: boolean) {
  if (saving.value) return;
  saveError.value = '';
  if (sameBranch.value) return; // the warning under the branches says why
  if (!lines.value.length) { saveError.value = 'Add at least one product.'; return; }
  if (send && errors.value.length) return; // the list beside the form says why

  const body = {
    date: form.date, from_branch_id: form.from, to_branch_id: form.to,
    shipping_cost: Number(form.shipping) || 0, notes: form.notes || null, send,
    lines: lines.value.map(l => ({ product_id: l.product_id, variant_id: l.variant_id, qty: Number(l.qty) || 0 }))
  };

  saving.value = true;
  try {
    const saved = editId ? await api.put('/stock-transfers/' + editId, body) : await api.post('/stock-transfers', body);
    router.push({ name: 'transfers-details', query: { id: saved.id } });
  } catch (e: any) {
    saveError.value = e.message;
  }
  saving.value = false;
}

onMounted(async () => {
  try {
    await loadBranches();
    const data = await api.get('/stock-transfers/form-data');

    if (editId) {
      const t = await api.get('/stock-transfers/' + editId);
      reference.value = t.ref;
      Object.assign(form, { from: t.from_branch_id, to: t.to_branch_id, date: t.date, shipping: t.shipping_cost, notes: t.notes || '' });
      lines.value = t.items.map((i: any) => ({
        product_id: i.product_id, variant_id: i.variant_id, name: i.name, sku: i.sku, unit: i.unit, cost: i.cost, alert_qty: 0, qty: i.qty
      }));
    } else {
      reference.value = data.next_ref;
      const firstActive = branches.value.find(b => b.active);
      form.from = data.from_branch_id || (firstActive ? firstActive.id : 0);
      const other = branches.value.find(b => b.active && b.id !== form.from);
      form.to = other ? other.id : form.from;
    }

    await loadCatalog();

    // The alert quantity for lines loaded from a saved draft.
    for (const l of lines.value) {
      const row = catalogRow(l);
      if (row) l.alert_qty = row.alert_qty;
    }
  } catch (e) { /* the error bar shows why */ }
});
</script>
