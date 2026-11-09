<template>
  <template v-if="vm.puForm">
    <div>
      <div style="padding:20px 24px 16px;background:#ffffff;border-bottom:1px solid #dfe6ee">
        <div style="display:flex;align-items:center;gap:7px;font-size:11.5px;color:#7d8b9c;margin-bottom:8px"><span @click="vm.puGoList" style="cursor:pointer;color:var(--c-primary)">Purchases</span><span style="color:#c2cedb">/</span><span style="color:#0f1c2b;font-weight:500">{{ editId ? 'Edit ' + form.ref : 'New purchase' }}</span></div>
        <div style="display:flex;align-items:flex-start;gap:20px;flex-wrap:wrap">
          <div>
            <h2 style="margin:0 0 3px;font-size:22px;font-weight:700;letter-spacing:-0.018em">{{ editId ? 'Edit purchase' : 'New purchase' }}</h2>
            <div style="font-size:12.5px;color:#5d6b7d">Stock goes into the branch you pick when the purchase is received</div>
          </div>
          <div style="margin-left:auto;display:flex;gap:8px;align-items:center">
            <div @click="vm.puGoList" style="height:34px;padding:0 13px;border-radius:8px;border:1px solid #dfe6ee;display:flex;align-items:center;font-size:12.5px;font-weight:500;color:#4a5866;cursor:pointer" v-pseudo:hover="'background:#f2f6fa'">Cancel</div>
            <div @click="save(false)" style="height:34px;padding:0 13px;border-radius:8px;border:1px solid #dfe6ee;display:flex;align-items:center;font-size:12.5px;font-weight:600;color:var(--c-primary-text);cursor:pointer" v-pseudo:hover="'background:#f2f6fa'">{{ saving ? 'Saving…' : 'Save as ordered' }}</div>
            <div @click="save(true)" style="height:34px;padding:0 15px;border-radius:8px;background:var(--c-primary);color:#ffffff;display:flex;align-items:center;font-size:12.5px;font-weight:600;cursor:pointer" v-pseudo:hover="'background:var(--c-primary-hover)'">{{ saving ? 'Saving…' : 'Save & receive stock' }}</div>
          </div>
        </div>
      </div>
      <div style="padding:20px 24px 32px;display:grid;grid-template-columns:1fr 330px;gap:16px;align-items:start">
        <div style="display:grid;gap:14px">
          <div v-if="error" style="background:#fdeaea;color:#b02020;border-radius:9px;padding:10px 13px;font-size:12.5px">{{ error }}</div>
          <div style="background:#ffffff;border:1px solid #dfe6ee;border-radius:12px;padding:17px 19px;display:grid;grid-template-columns:1.5fr 1fr 1fr 1fr;gap:14px">
            <div>
              <label :style="labelStyle">Supplier <span style="color:#d93a3a">*</span></label>
              <select v-model.number="form.supplier_id" :style="inputStyle">
                <option :value="0">Pick a supplier</option>
                <option v-for="c in suppliers" :key="c.id" :value="c.id">{{ c.name }}</option>
              </select>
              <div v-if="supplierHint" style="font-size:11.5px;color:#b45309;margin-top:5px">{{ supplierHint }}</div>
            </div>
            <div><label :style="labelStyle">Reference</label> <input v-model="form.ref" :placeholder="nextRef" :style="inputStyle + monoFont" v-pseudo:focus="'border-color:var(--c-primary)'" /></div>
            <div><label :style="labelStyle">Date</label> <input v-model="form.date" type="date" :style="inputStyle + monoFont" /></div>
            <div>
              <label :style="labelStyle">Receive into <span style="color:#d93a3a">*</span></label>
              <select v-model.number="form.branch_id" @change="onBranchChange" :disabled="!!userBranchId" :style="inputStyle">
                <option :value="0">Pick a branch</option>
                <option v-for="b in activeBranches" :key="b.id" :value="b.id">{{ b.name }}</option>
              </select>
            </div>
          </div>
          <div style="background:#ffffff;border:1px solid #dfe6ee;border-radius:12px;overflow:hidden">
            <div style="padding:13px 16px;border-bottom:1px solid #eaeff5;position:relative">
              <div style="position:relative"><div style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:#9aa7b6;font-size:13px">⚲</div> <input :value="search" @input="onSearch" @keydown.enter.prevent="addFirstResult" :disabled="!form.branch_id" :placeholder="form.branch_id ? 'Scan or search product to add a line' : 'Pick a branch first'" style="width:100%;height:40px;border:1px solid var(--c-primary);border-radius:9px;background:var(--c-primary-tint);padding:0 12px 0 34px;font-family:'Public Sans',sans-serif;font-size:13px;outline:none" /></div>
              <div v-if="results.length" style="position:absolute;left:16px;right:16px;top:54px;background:#ffffff;border:1px solid #dfe6ee;border-radius:9px;box-shadow:0 12px 28px -14px rgba(15,28,43,.4);z-index:5;overflow:hidden">
                <div v-for="(r, i) in results" :key="i" @click="addLine(r)" style="display:flex;align-items:center;gap:10px;padding:9px 13px;border-bottom:1px solid #f0f4f8;cursor:pointer" v-pseudo:hover="'background:#f0f6fd'">
                  <div style="font-size:12.5px;flex:1">{{ r.name }} <span style="color:#9aa7b6;font-family:'IBM Plex Mono',monospace;font-size:11px">{{ r.sku }}</span></div>
                  <div style="font-family:'IBM Plex Mono',monospace;font-size:11px;color:#5d6b7d">{{ qtyText(r.stock) }} {{ r.unit }} here</div>
                  <div style="font-family:'IBM Plex Mono',monospace;font-size:11.5px;color:#5d6b7d;min-width:60px;text-align:right">{{ money(r.cost) }}</div>
                </div>
              </div>
            </div>
            <div v-for="(sg, i) in suggestions" :key="i" @click="addLine(sg)" style="display:flex;align-items:center;gap:10px;padding:8px 16px;border-bottom:1px solid #f0f4f8;background:#fbfcfe;cursor:pointer" v-pseudo:hover="'background:#f0f6fd'">
              <div style="font-size:12px;font-weight:600;color:var(--c-primary);flex:none">+</div>
              <div style="font-size:12.5px;flex:1">{{ sg.name }}</div>
              <div style="font-family:'IBM Plex Mono',monospace;font-size:11px;color:#b45309">{{ qtyText(sg.stock) }} {{ sg.unit }} · alert {{ qtyText(sg.alert_qty) }}</div>
              <div style="font-family:'IBM Plex Mono',monospace;font-size:11.5px;color:#5d6b7d;min-width:60px;text-align:right">{{ money(sg.cost) }}</div>
            </div>
            <div style="display:grid;grid-template-columns:28px 2.2fr 1.1fr 1fr 0.8fr 1fr 30px;gap:10px;padding:9px 16px;background:#f6f9fc;border-bottom:1px solid #dfe6ee;font-family:'IBM Plex Mono',monospace;font-size:9.5px;letter-spacing:0.07em;text-transform:uppercase;color:#5d6b7d">
              <div>#</div>
              <div>Product</div>
              <div style="text-align:center">Qty</div>
              <div style="text-align:right">Unit cost</div>
              <div style="text-align:right">Disc %</div>
              <div style="text-align:right">Line total</div>
              <div></div>
            </div>
            <div v-if="!lines.length" style="padding:20px 16px;font-size:12.5px;color:#7d8b9c;text-align:center">No lines yet. Search above, or tap a low-stock suggestion.</div>
            <div v-for="(l, i) in lines" :key="i" style="display:grid;grid-template-columns:28px 2.2fr 1.1fr 1fr 0.8fr 1fr 30px;gap:10px;padding:9px 16px;border-bottom:1px solid #f0f4f8;align-items:center" v-pseudo:hover="'background:#f9fbfd'">
              <div style="font-family:'IBM Plex Mono',monospace;font-size:11px;color:#9aa7b6">{{ i + 1 }}</div>
              <div style="min-width:0">
                <div style="font-size:13px;font-weight:500;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">{{ l.name }}</div>
                <div :style="'font-size:10.5px;' + (l.cost !== l.last_cost ? 'color:#b45309' : 'color:#9aa7b6')">{{ l.cost !== l.last_cost ? 'last cost ' + money(l.last_cost) : 'same as last cost' }}</div>
              </div>
              <div style="display:flex;align-items:center;border:1px solid #dfe6ee;border-radius:7px;overflow:hidden;height:32px">
                <div @click="l.qty = Math.max(1, l.qty - 1)" style="width:28px;height:100%;display:grid;place-items:center;font-size:14px;color:#5d6b7d;cursor:pointer;flex:none" v-pseudo:hover="'background:#f2f6fa;color:var(--c-primary)'">−</div>
                <input v-model.number="l.qty" type="number" min="0" step="any" style="flex:1;min-width:0;width:100%;text-align:center;font-family:'IBM Plex Mono',monospace;font-size:12.5px;font-weight:600;border:0;border-left:1px solid #eaeff5;border-right:1px solid #eaeff5;height:100%;outline:none" />
                <div @click="l.qty = l.qty + 1" style="width:28px;height:100%;display:grid;place-items:center;font-size:14px;color:#5d6b7d;cursor:pointer;flex:none" v-pseudo:hover="'background:#f2f6fa;color:var(--c-primary)'">+</div>
              </div>
              <input v-model.number="l.cost" type="number" min="0" step="0.01" :style="numberStyle" />
              <input v-model.number="l.discount" type="number" min="0" max="100" step="any" :style="numberStyle" />
              <div style="font-family:'IBM Plex Mono',monospace;font-size:13px;text-align:right;font-weight:600">{{ money(lineTotal(l)) }}</div>
              <div @click="lines.splice(i, 1)" style="width:24px;height:24px;border-radius:6px;display:grid;place-items:center;color:#b3c0cf;cursor:pointer;font-size:13px" v-pseudo:hover="'background:#fdeaea;color:#b02020'">×</div>
            </div>
            <div style="padding:10px 16px;font-size:11.5px;color:#7d8b9c;display:flex;gap:16px;flex-wrap:wrap"><span>Enter adds the first result · −/+ adjust quantity</span> <span style="margin-left:auto;font-family:'IBM Plex Mono',monospace">{{ lines.length }} {{ lines.length === 1 ? 'line' : 'lines' }} · {{ qtyText(totalQty) }} units</span></div>
          </div>
          <div style="background:#ffffff;border:1px solid #dfe6ee;border-radius:12px;padding:17px 19px;display:grid;grid-template-columns:1fr 1fr;gap:14px">
            <div><label :style="labelStyle">Notes</label> <textarea v-model="form.notes" placeholder="Delivered chilled, checked by Omar" style="width:100%;height:72px;border:1px solid #dfe6ee;border-radius:8px;padding:9px 11px;font-family:'Public Sans',sans-serif;font-size:13px;outline:none;resize:none" v-pseudo:focus="'border-color:var(--c-primary)'"></textarea></div>
            <div>
              <label :style="labelStyle">Supplier invoice</label>
              <label style="border:1px dashed #cfdae6;border-radius:8px;height:72px;display:grid;place-items:center;text-align:center;cursor:pointer">
                <div>
                  <div style="font-size:12.5px;font-weight:600">{{ invoiceFile ? invoiceFile.name : 'Choose PDF or photo' }}</div>
                  <div style="font-size:11px;color:#7d8b9c">Attached to the purchase when you save</div>
                </div>
                <input type="file" accept=".pdf,.png,.jpg,.jpeg" @change="onFile" style="display:none" />
              </label>
            </div>
          </div>
        </div>
        <div style="display:grid;gap:12px;position:sticky;top:0">
          <div style="background:#ffffff;border:1px solid #dfe6ee;border-radius:12px;padding:16px 18px">
            <div style="font-size:12.5px;font-weight:700;margin-bottom:13px">Summary</div>
            <div style="display:grid;gap:9px">
              <div style="display:flex;align-items:baseline;gap:10px">
                <div style="font-size:12.5px;color:#5d6b7d;flex:1">Subtotal</div>
                <div style="font-family:'IBM Plex Mono',monospace;font-size:13px">{{ money(subtotal) }}</div>
              </div>
              <div style="display:flex;align-items:center;gap:10px">
                <div style="font-size:12.5px;color:#5d6b7d;flex:1">Order discount</div>
                <input v-model.number="form.order_discount" type="number" min="0" step="0.01" :style="numberStyle + ';width:92px;height:30px;color:#b02020'" />
              </div>
              <div style="display:flex;align-items:center;gap:10px">
                <div style="font-size:12.5px;color:#5d6b7d;flex:1">Freight &amp; charges</div>
                <input v-model.number="form.freight" type="number" min="0" step="0.01" :style="numberStyle + ';width:92px;height:30px'" />
              </div>
              <div style="display:flex;align-items:baseline;gap:10px">
                <div style="font-size:12.5px;color:#5d6b7d;flex:1">GST {{ qtyText(taxRate) }}% (claimable)</div>
                <div style="font-family:'IBM Plex Mono',monospace;font-size:13px">{{ money(tax) }}</div>
              </div>
            </div>
            <div style="display:flex;align-items:center;gap:12px;padding:12px 14px;background:#0f1c2b;border-radius:10px;margin-top:14px">
              <div style="font-size:12.5px;font-weight:600;color:#cfdbe6;flex:1">Total</div>
              <div style="font-family:'IBM Plex Mono',monospace;font-size:22px;font-weight:600;color:#ffffff;letter-spacing:-0.02em">{{ money(grandTotal) }}</div>
            </div>
          </div>
          <div v-if="!editId" style="background:#ffffff;border:1px solid #dfe6ee;border-radius:12px;padding:16px 18px">
            <div style="font-size:12.5px;font-weight:700;margin-bottom:11px">Payment</div>
            <div style="display:flex;gap:6px;margin-bottom:12px">
              <div v-for="p in PAY_CHOICES" :key="p" @click="payChoice = p" :style="'flex:1;height:32px;border-radius:8px;display:grid;place-items:center;font-size:12px;cursor:pointer;white-space:nowrap;' + (payChoice === p ? 'background:var(--c-primary);color:#ffffff;font-weight:600' : 'border:1px solid #dfe6ee;color:#4a5866;font-weight:500')">{{ p }}</div>
            </div>
            <div v-if="payChoice !== 'Unpaid'" style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:10px">
              <div>
                <label style="display:block;font-size:11.5px;font-weight:600;margin-bottom:4px">Amount</label>
                <input v-if="payChoice === 'Part paid'" v-model.number="partAmount" type="number" min="0" step="0.01" :style="numberStyle + ';width:100%;height:34px'" />
                <div v-else style="height:34px;border:1px solid #dfe6ee;border-radius:8px;padding:0 10px;display:flex;align-items:center;justify-content:flex-end;font-family:'IBM Plex Mono',monospace;font-size:12.5px">{{ money(grandTotal) }}</div>
              </div>
              <div>
                <label style="display:block;font-size:11.5px;font-weight:600;margin-bottom:4px">From account</label>
                <select v-model="form.payment_account_id" style="width:100%;height:34px;border:1px solid #dfe6ee;border-radius:8px;padding:0 6px;font-family:'Public Sans',sans-serif;font-size:12.5px;background:#ffffff">
                  <option v-for="a in accounts" :key="a.id" :value="a.id">{{ a.name }}</option>
                </select>
              </div>
            </div>
            <div style="display:flex;align-items:baseline;gap:10px;padding-top:10px;border-top:1px solid #f0f4f8">
              <div style="font-size:12.5px;font-weight:600;flex:1">Due to supplier</div>
              <div :style="'font-family:\'IBM Plex Mono\',monospace;font-size:16px;font-weight:700;' + (balance > 0 ? 'color:#b02020' : 'color:#0f6b43')">{{ money(balance) }}</div>
            </div>
          </div>
          <div style="background:#e7f6ee;border:1px solid #bfe5d1;border-radius:12px;padding:14px 16px">
            <div style="font-size:12px;font-weight:700;color:#0f6b43;margin-bottom:7px">On receive</div>
            <div style="font-size:12px;line-height:1.6;color:#0f6b43">+{{ qtyText(totalQty) }} units into {{ branchName }}. Product cost updates for {{ lines.length }} {{ lines.length === 1 ? 'line' : 'lines' }}, and the total is added to what you owe the supplier.</div>
          </div>
        </div>
      </div>
    </div>
  </template>
</template>

<script setup lang="ts">
// Purchases > New or edit purchase (/purchases/new, /purchases/new?id=5).
// API: GET /api/purchases/form-data, GET /api/purchases/products?branch_id=&search=&low=1, POST /api/purchases, PUT /api/purchases/{id}
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { usePageVm } from '../../erp/useErpPage';
import { api } from '../../api/http.js';
import { branches, loadBranches } from '../../api/branches.js';
import { contacts, loadContacts } from '../../api/contacts.js';
import { money, qtyText, today, userBranchId, defaultAccountId } from '../../api/purchases.js';

const vm = usePageVm();
const route = useRoute();
const router = useRouter();

const labelStyle = 'display:block;font-size:12px;font-weight:600;margin-bottom:5px';
const inputStyle = "width:100%;height:36px;border:1px solid #dfe6ee;border-radius:8px;padding:0 11px;font-family:'Public Sans',sans-serif;font-size:13px;outline:none;background:#ffffff;";
const monoFont = "font-family:'IBM Plex Mono',monospace;font-size:12.5px;";
const numberStyle = "height:32px;min-width:0;border:1px solid #dfe6ee;border-radius:7px;padding:0 9px;text-align:right;font-family:'IBM Plex Mono',monospace;font-size:12.5px;outline:none";
const PAY_CHOICES = ['Unpaid', 'Part paid', 'Paid in full'];

const editId = computed(() => Number(route.query.id) || 0);

const form = reactive({
  ref: '', date: today(), supplier_id: 0, branch_id: 0, order_discount: 0, freight: 0, notes: '', payment_account_id: ''
});
const lines = ref<any[]>([]);
const nextRef = ref('');
const taxRate = ref(0);
const accounts = ref<any[]>([]);
const payChoice = ref('Unpaid');
const partAmount = ref(0);
const invoiceFile = ref<File | null>(null);
const error = ref('');
const saving = ref(false);

const search = ref('');
const results = ref<any[]>([]);
const suggestions = ref<any[]>([]);

const suppliers = computed(() => contacts.value.filter((c: any) => c.type !== 'Customer'));
const activeBranches = computed(() => branches.value.filter(b => b.active || b.id === form.branch_id));
const branchName = computed(() => {
  const b = branches.value.find(x => x.id === form.branch_id);
  return b ? b.name : 'the branch';
});
const supplierHint = computed(() => {
  const s: any = contacts.value.find((c: any) => c.id === form.supplier_id);
  if (!s) return '';
  const owed = s.balance < 0 ? 'Owed ' + money(-s.balance) : 'Nothing owed';
  return owed + (s.pay_term ? ' · ' + s.pay_term : '');
});

// The same sums the server does when it saves.
function lineTotal(l: any) {
  return Math.round((Number(l.qty) || 0) * (Number(l.cost) || 0) * (1 - (Number(l.discount) || 0) / 100) * 100) / 100;
}
const subtotal = computed(() => lines.value.reduce((sum, l) => sum + lineTotal(l), 0));
const totalQty = computed(() => lines.value.reduce((sum, l) => sum + (Number(l.qty) || 0), 0));
const tax = computed(() => Math.round((subtotal.value - (Number(form.order_discount) || 0) + (Number(form.freight) || 0)) * taxRate.value) / 100);
const grandTotal = computed(() => subtotal.value - (Number(form.order_discount) || 0) + (Number(form.freight) || 0) + tax.value);
const paidAmount = computed(() => (payChoice.value === 'Paid in full' ? grandTotal.value : payChoice.value === 'Part paid' ? Number(partAmount.value) || 0 : 0));
const balance = computed(() => Math.max(0, grandTotal.value - paidAmount.value));

async function load() {
  error.value = '';
  try {
    await loadBranches();
    await loadContacts();
    const data = await api.get('/purchases/form-data');
    nextRef.value = data.next_ref;
    taxRate.value = data.tax_rate;
    accounts.value = data.payment_accounts;
    form.payment_account_id = defaultAccountId(data.payment_accounts);

    if (editId.value) {
      const p = await api.get('/purchases/' + editId.value);
      Object.assign(form, {
        ref: p.ref, date: p.date, supplier_id: p.supplier_id, branch_id: p.branch_id,
        order_discount: p.order_discount, freight: p.freight, notes: p.notes || ''
      });
      taxRate.value = p.tax_rate;
      lines.value = p.items.map((i: any) => ({
        product_id: i.product_id, variant_id: i.variant_id, name: i.name, qty: i.qty, cost: i.cost, discount: i.discount, last_cost: i.cost
      }));
    } else {
      form.branch_id = userBranchId.value || data.branch_id || 0;
    }
    loadSuggestions();
  } catch (e) { /* the error bar shows why */ }
}

onMounted(load);
watch(editId, load);

// Low stock at this branch, not already on the purchase.
async function loadSuggestions() {
  suggestions.value = [];
  if (!form.branch_id) return;
  const rows = await api.get('/purchases/products?low=1&branch_id=' + form.branch_id).catch(() => []);
  suggestions.value = rows.filter((r: any) => !lines.value.some(l => l.product_id === r.product_id && l.variant_id === r.variant_id)).slice(0, 4);
}

function onBranchChange() {
  results.value = [];
  search.value = '';
  loadSuggestions();
}

// Wait until typing stops for a moment before searching.
let timer: any = null;
function onSearch(e: Event) {
  search.value = (e.target as HTMLInputElement).value;
  clearTimeout(timer);
  timer = setTimeout(findProducts, 300);
}

async function findProducts() {
  const text = search.value.trim();
  if (!text) { results.value = []; return; }
  results.value = await api.get('/purchases/products?branch_id=' + form.branch_id + '&search=' + encodeURIComponent(text)).catch(() => []);
}

async function addFirstResult() {
  await findProducts();
  if (results.value.length) addLine(results.value[0]);
}

function addLine(row: any) {
  const same = lines.value.find(l => l.product_id === row.product_id && l.variant_id === row.variant_id);
  if (same) {
    same.qty = same.qty + 1;
  } else {
    lines.value.push({ product_id: row.product_id, variant_id: row.variant_id, name: row.name, qty: 1, cost: row.cost, discount: 0, last_cost: row.cost });
  }
  suggestions.value = suggestions.value.filter(s => !(s.product_id === row.product_id && s.variant_id === row.variant_id));
  search.value = '';
  results.value = [];
}

function onFile(e: Event) {
  const files = (e.target as HTMLInputElement).files;
  invoiceFile.value = files && files.length ? files[0] : null;
}

async function save(receive: boolean) {
  if (saving.value) return;
  error.value = '';
  if (!form.supplier_id) { error.value = 'Pick a supplier.'; return; }
  if (!form.branch_id) { error.value = 'Pick the branch that receives the stock.'; return; }
  if (!lines.value.length) { error.value = 'Add at least one line.'; return; }

  const body: Record<string, any> = {
    ref: form.ref.trim() || null, date: form.date, supplier_id: form.supplier_id, branch_id: form.branch_id,
    order_discount: Number(form.order_discount) || 0, freight: Number(form.freight) || 0, notes: form.notes || null, receive,
    lines: lines.value.map(l => ({ product_id: l.product_id, variant_id: l.variant_id, qty: l.qty, cost: l.cost, discount: Number(l.discount) || 0 }))
  };
  if (!editId.value && paidAmount.value > 0) {
    body.paid_amount = Math.round(paidAmount.value * 100) / 100;
    body.payment_account_id = form.payment_account_id;
  }

  saving.value = true;
  try {
    const saved = editId.value ? await api.put('/purchases/' + editId.value, body) : await api.post('/purchases', body);
    if (invoiceFile.value) {
      const data = new FormData();
      data.append('file', invoiceFile.value);
      await api.post('/purchases/' + saved.id + '/invoice', data);
    }
    router.push({ name: 'purchases-details', query: { id: saved.id } });
  } catch (e: any) {
    error.value = e.message;
  }
  saving.value = false;
}
</script>
