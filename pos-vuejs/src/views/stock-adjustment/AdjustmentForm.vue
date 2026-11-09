<template>
  <div>
    <div style="padding:20px 24px 16px;background:#ffffff;border-bottom:1px solid #dfe6ee">
      <div style="display:flex;align-items:center;gap:7px;font-size:11.5px;color:#7d8b9c;margin-bottom:8px"><RouterLink :to="{ name: 'adjust' }" style="color:var(--c-primary)">Stock adjustments</RouterLink><span style="color:#c2cedb">/</span><span style="color:#0f1c2b;font-weight:500">New adjustment</span></div>
      <div style="display:flex;align-items:flex-start;gap:20px;flex-wrap:wrap">
        <div>
          <h2 style="margin:0 0 3px;font-size:22px;font-weight:700;letter-spacing:-0.018em">New stock adjustment</h2>
          <div style="font-size:12.5px;color:#5d6b7d">Stock changes the moment you post. A mistake is undone with a reversal.</div>
        </div>
        <div style="margin-left:auto;display:flex;gap:8px;align-items:center">
          <RouterLink :to="{ name: 'adjust' }" style="height:34px;padding:0 13px;border-radius:8px;border:1px solid #dfe6ee;display:flex;align-items:center;font-size:12.5px;font-weight:500;color:#4a5866;cursor:pointer;text-decoration:none" v-pseudo:hover="'background:#f2f6fa'">Cancel</RouterLink>
          <div @click="post" :style="'height:34px;padding:0 15px;border-radius:8px;display:flex;align-items:center;font-size:12.5px;font-weight:600;color:#ffffff;cursor:pointer;background:' + (errors.length ? '#9fbde0' : 'var(--c-primary)')">{{ saving ? 'Posting…' : needsApproval ? 'Approve & post' : 'Post adjustment' }}</div>
        </div>
      </div>
    </div>
    <div style="padding:20px 24px 32px;display:grid;grid-template-columns:minmax(0,1fr) 330px;gap:16px;align-items:start">
      <div style="display:grid;gap:14px">
        <div v-if="saveError" style="background:#fdeaea;color:#b02020;border-radius:9px;padding:10px 13px;font-size:12.5px">{{ saveError }}</div>
        <div style="background:#ffffff;border:1px solid #dfe6ee;border-radius:12px;padding:17px 19px">
          <label :style="labelStyle">Adjustment type</label>
          <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-top:7px">
            <div v-for="m in MODES" :key="m.key" @click="setMode(m.key)" :style="modeStyle(m)">
              <div :style="modeIconStyle(m)">{{ m.icon }}</div>
              <div style="min-width:0">
                <div style="font-size:13.5px;font-weight:700">{{ m.name }}</div>
                <div style="font-size:11.5px;opacity:.8;margin-top:1px;line-height:1.35">{{ m.sub }}</div>
              </div>
            </div>
          </div>
          <div style="display:grid;grid-template-columns:1.2fr 1fr 1fr;gap:14px;margin-top:16px">
            <div>
              <label :style="labelStyle">Location <span style="color:#d93a3a">*</span></label>
              <select v-model.number="form.branch" @change="loadCatalog" :disabled="!!userBranchId" style="width:100%;height:36px;border:1px solid #dfe6ee;border-radius:8px;padding:0 10px;font-family:'Public Sans',sans-serif;font-size:13px;background:#ffffff;outline:none;cursor:pointer">
                <option v-for="b in activeBranches" :key="b.id" :value="b.id">{{ b.name }}</option>
              </select>
            </div>
            <div><label :style="labelStyle">Reference</label> <div style="height:36px;border:1px solid #e4eaf1;border-radius:8px;padding:0 11px;display:flex;align-items:center;font-family:'IBM Plex Mono',monospace;font-size:12.5px;background:#f7fafc;color:#4a5866">{{ reference }}</div></div>
            <div><label :style="labelStyle">Date</label> <input v-model="form.date" type="date" style="width:100%;height:36px;border:1px solid #dfe6ee;border-radius:8px;padding:0 11px;font-family:'IBM Plex Mono',monospace;font-size:12.5px" /></div>
          </div>
        </div>
        <div style="background:#ffffff;border:1px solid #dfe6ee;border-radius:12px;overflow:hidden">
          <div style="padding:13px 16px;border-bottom:1px solid #eaeff5;position:relative;display:flex;gap:10px;align-items:center">
            <div style="position:relative;flex:1"><div style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:#9aa7b6;font-size:13px">⚲</div> <input v-model="search" @keydown.enter.prevent="addFromSearch" @keydown.esc="search = ''" placeholder="Scan or search a product to add a line" style="width:100%;height:40px;border:1px solid var(--c-primary);border-radius:9px;background:var(--c-primary-tint);padding:0 12px 0 34px;font-family:'Public Sans',sans-serif;font-size:13px;outline:none" /></div>
            <div v-if="form.mode === 'count'" @click="addAll" style="height:40px;padding:0 13px;border-radius:9px;border:1px solid #dfe6ee;display:flex;align-items:center;font-size:12.5px;font-weight:500;color:#4a5866;cursor:pointer;flex:none" v-pseudo:hover="'background:#f2f6fa'">Add all products</div>
            <div v-if="search.trim()" style="position:absolute;left:16px;right:16px;top:56px;background:#ffffff;border:1px solid #dfe6ee;border-radius:10px;box-shadow:0 16px 32px -16px rgba(15,28,43,.35);z-index:5;overflow:hidden">
              <div v-for="p in results" :key="keyOf(p)" @click="addLine(p)" style="display:flex;align-items:center;gap:10px;padding:9px 14px;border-bottom:1px solid #f0f4f8;cursor:pointer" v-pseudo:hover="'background:#f0f6fd'">
                <div style="flex:1;min-width:0">
                  <div style="font-size:13px;font-weight:500">{{ p.name }}</div>
                  <div style="font-family:'IBM Plex Mono',monospace;font-size:10.5px;color:#8b98a8">SKU {{ p.sku }}</div>
                </div>
                <div style="font-family:'IBM Plex Mono',monospace;font-size:11.5px;color:#5d6b7d">{{ qtyUnit(p.on_hand, p.unit) }} on hand</div>
              </div>
              <div v-if="!results.length" style="padding:14px 16px;font-size:12.5px;color:#7d8b9c">No product at this branch matches.</div>
            </div>
          </div>
          <div style="display:grid;grid-template-columns:24px minmax(0,1.9fr) 0.8fr 1.3fr 0.9fr 1.3fr 0.9fr 28px;gap:10px;padding:9px 16px;background:#f6f9fc;border-bottom:1px solid #dfe6ee;font-family:'IBM Plex Mono',monospace;font-size:9.5px;letter-spacing:0.07em;text-transform:uppercase;color:#5d6b7d">
            <div>#</div>
            <div>Product</div>
            <div style="text-align:right">On hand</div>
            <div style="text-align:center">{{ form.mode === 'count' ? 'Counted' : form.mode === 'inc' ? 'Add' : 'Remove' }}</div>
            <div style="text-align:right">New stock</div>
            <div>Reason</div>
            <div style="text-align:right">Value</div>
            <div></div>
          </div>
          <div v-for="(l, i) in lines" :key="keyOf(l)" :style="'display:grid;grid-template-columns:24px minmax(0,1.9fr) 0.8fr 1.3fr 0.9fr 1.3fr 0.9fr 28px;gap:10px;padding:9px 16px;border-bottom:1px solid #f0f4f8;align-items:center;' + (lineChange(l) > 0 ? 'box-shadow:inset 3px 0 0 #2f9e63' : lineChange(l) < 0 ? 'box-shadow:inset 3px 0 0 #d93a3a' : '')">
            <div style="font-family:'IBM Plex Mono',monospace;font-size:11px;color:#9aa7b6">{{ i + 1 }}</div>
            <div style="min-width:0">
              <div style="font-size:13px;font-weight:500;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">{{ l.name }}</div>
              <div v-if="lineProblem(l)" style="font-size:11px;color:#b02020;font-weight:600">{{ lineProblem(l) }}</div>
              <div v-else style="font-family:'IBM Plex Mono',monospace;font-size:10.5px;color:#8b98a8">SKU {{ l.sku }} · {{ money(l.cost) }}{{ l.unit ? ' / ' + l.unit : '' }}</div>
            </div>
            <div style="font-family:'IBM Plex Mono',monospace;font-size:12px;text-align:right;color:#4a5866">{{ qtyUnit(onHand(l), l.unit) }}</div>
            <div style="display:flex;align-items:center;justify-content:center">
              <div :style="'display:flex;align-items:center;height:32px;border-radius:8px;background:#ffffff;border:1px solid ' + (lineProblem(l) ? '#d93a3a' : '#dfe6ee')"><input v-model.number="l.qty" type="number" min="0" step="any" :placeholder="form.mode === 'count' ? String(onHand(l)) : '0'" style="width:62px;height:100%;border:none;background:transparent;text-align:right;font-family:'IBM Plex Mono',monospace;font-size:12.5px;font-weight:600;outline:none;padding:0 4px 0 0" /> <div style="font-size:11px;color:#8b98a8;padding-right:8px">{{ l.unit }}</div></div>
            </div>
            <div :style="'font-family:\'IBM Plex Mono\',monospace;font-size:12px;text-align:right;font-weight:600;' + valueColor(lineChange(l))">{{ l.qty === '' ? '—' : qtyUnit(onHand(l) + lineChange(l), l.unit) }}</div>
            <select v-model="l.reason" style="width:100%;height:32px;border:1px solid #dfe6ee;border-radius:8px;padding:0 6px;font-family:'Public Sans',sans-serif;font-size:12px;background:#ffffff;outline:none;cursor:pointer;min-width:0">
              <option value="">Pick reason</option>
              <option v-for="r in reasonsFor(l)" :key="r" :value="r">{{ r }}</option>
            </select>
            <div :style="'font-family:\'IBM Plex Mono\',monospace;font-size:12.5px;text-align:right;' + valueColor(lineChange(l))">{{ l.qty === '' ? '—' : signed(lineChange(l) * l.cost) }}</div>
            <div @click="lines.splice(i, 1)" style="width:26px;height:26px;border-radius:6px;display:grid;place-items:center;color:#b3c0cf;cursor:pointer;font-size:14px" v-pseudo:hover="'background:#fdeaea;color:#b02020'">×</div>
          </div>
          <div v-if="!lines.length" style="padding:30px 20px;text-align:center;font-size:12.5px;color:#7d8b9c">{{ form.mode === 'count' ? 'Add the products you counted, or add all products for a full count.' : 'Search above to add the products you are adjusting.' }}</div>
        </div>
        <div style="background:#ffffff;border:1px solid #dfe6ee;border-radius:12px;padding:15px 19px"><label :style="labelStyle">Notes</label> <textarea v-model="form.notes" placeholder="e.g. Cool room 2 failed overnight, temperature log attached" style="width:100%;min-height:64px;border:1px solid #dfe6ee;border-radius:8px;padding:9px 11px;font-family:'Public Sans',sans-serif;font-size:13px;outline:none;resize:vertical" v-pseudo:focus="'border-color:var(--c-primary)'"></textarea></div>
      </div>
      <div style="display:grid;gap:14px;position:sticky;top:20px">
        <div style="background:#ffffff;border:1px solid #dfe6ee;border-radius:12px;padding:17px 19px">
          <div style="font-size:13px;font-weight:700;margin-bottom:12px">Value impact</div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">
            <div style="background:#e7f6ee;border-radius:10px;padding:10px 12px">
              <div style="font-size:11px;color:#0f6b43;font-weight:600">Increase</div>
              <div style="font-family:'IBM Plex Mono',monospace;font-size:15px;font-weight:600;color:#0f6b43;margin-top:3px">+{{ money(totals.increase) }}</div>
              <div style="font-size:10.5px;color:#4f9a74;margin-top:2px">{{ totals.incLines }} {{ totals.incLines === 1 ? 'line' : 'lines' }}</div>
            </div>
            <div style="background:#fdf4f4;border-radius:10px;padding:10px 12px">
              <div style="font-size:11px;color:#b02020;font-weight:600">Decrease</div>
              <div style="font-family:'IBM Plex Mono',monospace;font-size:15px;font-weight:600;color:#b02020;margin-top:3px">−{{ money(totals.decrease) }}</div>
              <div style="font-size:10.5px;color:#c77a7a;margin-top:2px">{{ totals.decLines }} {{ totals.decLines === 1 ? 'line' : 'lines' }}</div>
            </div>
          </div>
          <div style="display:flex;align-items:baseline;border-top:1px solid #eaeff5;margin-top:12px;padding-top:12px">
            <div style="flex:1;font-size:13px;font-weight:600">Net change</div>
            <div :style="'font-family:\'IBM Plex Mono\',monospace;font-size:20px;font-weight:600;letter-spacing:-0.02em;' + valueColor(totals.increase - totals.decrease)">{{ signed(totals.increase - totals.decrease) }}</div>
          </div>
          <div style="font-size:11.5px;color:#7d8b9c;margin-top:6px;line-height:1.5">Valued at the product's current cost.</div>
        </div>
        <div v-if="needsApproval" style="display:flex;gap:10px;align-items:flex-start;background:#fdf1dd;border:1px solid #f0d9ac;border-radius:12px;padding:12px 14px;font-size:12px;color:#8a5300;line-height:1.5">
          <div style="font-weight:700;flex:none">!</div>
          <div v-if="can('adjust.edit')">Write-offs over {{ money(approvalLimit) }} need a manager. Posting records you as the approver.</div>
          <div v-else>Write-offs over {{ money(approvalLimit) }} need a manager. Ask someone who can edit adjustments to post this.</div>
        </div>
        <div v-if="lines.length && errors.length" style="background:#fdeaea;border:1px solid #f3cfcf;border-radius:12px;padding:12px 15px;display:grid;gap:5px">
          <div v-for="e in errors.slice(0, 5)" :key="e" style="font-size:12px;color:#b02020;display:flex;gap:7px"><span style="font-weight:700">×</span>{{ e }}</div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
// New stock adjustment (/stock-adjustment/new, /stock-adjustment/new?mode=count).
// API: GET /api/stock-adjustments/form-data, GET /api/stock-adjustments/products?branch_id=, POST /api/stock-adjustments
import { computed, onMounted, reactive, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { api } from '../../api/http.js';
import { can } from '../../api/auth.js';
import { branches, loadBranches } from '../../api/branches.js';
import { money, today, qtyUnit, signed, valueColor, userBranchId } from '../../api/adjustments.js';

const route = useRoute();
const router = useRouter();

const labelStyle = 'display:block;font-size:12px;font-weight:600;margin-bottom:5px';
const MODES = [
  { key: 'dec', name: 'Decrease', sub: 'Spoilage, damage, waste, missing', icon: '−', bg: '#fdeaea', text: '#b02020', mark: '#d93a3a' },
  { key: 'inc', name: 'Increase', sub: 'Found stock, returns, bonus', icon: '+', bg: '#e7f6ee', text: '#0f6b43', mark: '#2f9e63' },
  { key: 'count', name: 'Stock count', sub: 'Enter counted qty, we work out the difference', icon: '=', bg: 'var(--c-primary-soft)', text: 'var(--c-primary-text)', mark: 'var(--c-primary)' }
];

const form = reactive({ mode: route.query.mode === 'count' ? 'count' : 'dec', branch: 0, date: today(), notes: '' });
const reference = ref('');
const reasons = ref<any>({ dec: [], inc: [] });
const approvalLimit = ref(0);
const lines = ref<any[]>([]);
const catalog = ref<any[]>([]); // products at this branch, with the stock on hand
const search = ref('');
const saving = ref(false);
const saveError = ref('');

const activeBranches = computed(() => branches.value.filter(b => b.active || b.id === form.branch));

// The same line can be one product, or one variant of a product.
function keyOf(row: any) {
  return row.product_id + '-' + (row.variant_id || 0);
}
function catalogRow(line: any) {
  return catalog.value.find(p => keyOf(p) === keyOf(line));
}
function onHand(line: any) {
  const row = catalogRow(line);
  return row ? row.on_hand : 0;
}

// How much stock this line changes: + added, - removed.
function lineChange(line: any) {
  if (line.qty === '') return 0;
  const qty = Number(line.qty) || 0;
  if (form.mode === 'count') return Math.round((qty - onHand(line)) * 1000) / 1000;
  return form.mode === 'inc' ? qty : -qty;
}

function reasonsFor(line: any) {
  const change = lineChange(line);
  if (change > 0) return reasons.value.inc;
  if (change < 0) return reasons.value.dec;
  return form.mode === 'inc' ? reasons.value.inc : reasons.value.dec;
}

// What is wrong with a line, for posting. Empty when it is fine.
function lineProblem(line: any) {
  const row = catalogRow(line);
  if (!row) return 'Not sold at this branch';

  // A count line left blank was not counted, so it is simply not posted.
  if (line.qty === '') return form.mode === 'count' ? '' : 'Enter a quantity';

  const qty = Number(line.qty) || 0;
  const change = lineChange(line);
  if (form.mode !== 'count' && qty <= 0) return 'Enter a quantity';
  if (!row.allow_decimals && !Number.isInteger(qty)) return 'Whole units only';
  if (row.on_hand + change < 0 && !row.allow_negative_stock) return 'Only ' + qtyUnit(row.on_hand, line.unit) + ' on hand';
  if (change !== 0 && !reasonsFor(line).includes(line.reason)) return 'Pick a reason';
  return '';
}

const errors = computed(() => {
  const list: string[] = [];
  if (!lines.value.length) list.push('Add at least one product');
  for (const l of lines.value) {
    if (lineProblem(l)) list.push(l.name + ': ' + lineProblem(l));
  }
  return list;
});

const totals = computed(() => {
  const sum = { increase: 0, decrease: 0, incLines: 0, decLines: 0 };
  for (const l of lines.value) {
    const value = lineChange(l) * l.cost;
    if (value > 0) { sum.increase += value; sum.incLines++; }
    if (value < 0) { sum.decrease -= value; sum.decLines++; }
  }
  return sum;
});

const needsApproval = computed(() => totals.value.decrease > approvalLimit.value && approvalLimit.value > 0);

const results = computed(() => {
  const text = search.value.trim().toLowerCase();
  if (!text) return [];
  return catalog.value.filter(p => p.name.toLowerCase().includes(text) || p.sku.toLowerCase().startsWith(text) || p.barcode === search.value.trim()).slice(0, 6);
});

async function loadCatalog() {
  const branch = form.branch;
  catalog.value = [];
  if (!branch) return;
  const rows = await api.get('/stock-adjustments/products?branch_id=' + branch).catch(() => []);
  if (branch === form.branch) catalog.value = rows; // skip a late answer for a branch no longer picked
}

function setMode(mode: string) {
  form.mode = mode;
  for (const l of lines.value) {
    l.qty = '';
    l.reason = '';
  }
}

function newLine(row: any) {
  return { product_id: row.product_id, variant_id: row.variant_id, name: row.name, sku: row.sku, unit: row.unit, cost: row.cost, qty: '', reason: '' };
}

function addLine(row: any) {
  saveError.value = '';
  if (lines.value.some(l => keyOf(l) === keyOf(row))) {
    saveError.value = row.name + ' is already listed.';
  } else {
    lines.value.push(newLine(row));
  }
  search.value = '';
}

// Enter: an exact SKU or barcode (a scan), or the only result.
function addFromSearch() {
  const text = search.value.trim();
  const exact = catalog.value.find(p => p.sku === text || p.barcode === text);
  if (exact) addLine(exact);
  else if (results.value.length === 1) addLine(results.value[0]);
}

function addAll() {
  for (const row of catalog.value) {
    if (!lines.value.some(l => keyOf(l) === keyOf(row))) lines.value.push(newLine(row));
  }
}

async function post() {
  if (saving.value) return;
  saveError.value = '';
  if (errors.value.length) return; // the list beside the form says why

  // Blank count lines were not counted.
  const filled = lines.value.filter(l => l.qty !== '');
  if (!filled.length) { saveError.value = 'Enter at least one counted quantity.'; return; }

  if (needsApproval.value) {
    if (!can('adjust.edit')) return; // the note beside the form says why
    if (!confirm('This removes ' + money(totals.value.decrease) + ' of stock. Approve and post it?')) return;
  }

  saving.value = true;
  try {
    const saved = await api.post('/stock-adjustments', {
      date: form.date, branch_id: form.branch, type: form.mode, notes: form.notes || null,
      lines: filled.map(l => ({ product_id: l.product_id, variant_id: l.variant_id, qty: Number(l.qty) || 0, reason: l.reason || null }))
    });
    router.push({ name: 'adjust-details', query: { id: saved.id } });
  } catch (e: any) {
    saveError.value = e.message;
  }
  saving.value = false;
}

function modeStyle(m: any) {
  return 'display:flex;gap:11px;align-items:flex-start;border-radius:11px;padding:12px 13px;cursor:pointer;'
    + (form.mode === m.key ? 'background:' + m.bg + ';color:' + m.text + ';box-shadow:inset 0 0 0 1.5px ' + m.mark + ';' : 'border:1px solid #dfe6ee;color:#0f1c2b;');
}
function modeIconStyle(m: any) {
  return 'width:28px;height:28px;border-radius:8px;display:grid;place-items:center;font-size:16px;font-weight:700;flex:none;'
    + (form.mode === m.key ? 'background:' + m.mark + ';color:#ffffff;' : 'background:#eef2f6;color:#5d6b7d;');
}

onMounted(async () => {
  try {
    await loadBranches();
    const data = await api.get('/stock-adjustments/form-data');
    reference.value = data.next_ref;
    reasons.value = data.reasons;
    approvalLimit.value = data.approval_limit;
    const firstActive = branches.value.find(b => b.active);
    form.branch = data.branch_id || (firstActive ? firstActive.id : 0);
    await loadCatalog();
  } catch (e) { /* the error bar shows why */ }
});
</script>
