<template>
  <div>
    <div style="padding:20px 24px 16px;background:#ffffff;border-bottom:1px solid #dfe6ee">
      <div style="display:flex;align-items:center;gap:7px;font-size:11.5px;color:#7d8b9c;margin-bottom:8px">
        <span @click="$router.push('/settings')" style="cursor:pointer">Settings</span><span style="color:#c2cedb">/</span><span style="color:#0f1c2b;font-weight:500">Price rules</span>
      </div>
      <div style="display:flex;align-items:flex-start;gap:20px;flex-wrap:wrap">
        <div>
          <h2 style="margin:0 0 3px;font-size:22px;font-weight:700;letter-spacing:-0.018em">Price rules</h2>
          <div style="font-size:12.5px;color:#5d6b7d">{{ rules.length }} rules · {{ running }} running today · prices on the POS change on the rule's dates</div>
        </div>
        <div v-if="can('settings.create')" @click="openForm(null)" style="margin-left:auto;height:34px;padding:0 15px;border-radius:8px;background:var(--c-primary);color:#ffffff;display:flex;align-items:center;font-size:12.5px;font-weight:600;cursor:pointer" v-pseudo:hover="'background:var(--c-primary-hover)'">+ New price rule</div>
      </div>
    </div>

    <div style="padding:20px 24px 32px">
      <div style="background:#ffffff;border:1px solid #dfe6ee;border-radius:12px;overflow:hidden">
        <div :style="grid + 'padding:10px 18px;background:#f6f9fc;border-bottom:1px solid #dfe6ee;font-family:\'IBM Plex Mono\',monospace;font-size:9.5px;letter-spacing:0.07em;text-transform:uppercase;color:#5d6b7d'">
          <div>Rule</div>
          <div>Dates</div>
          <div style="text-align:right">Change</div>
          <div>Products</div>
          <div>Branches</div>
          <div>Status</div>
          <div></div>
        </div>
        <div v-if="loading" style="padding:16px 18px;font-size:12.5px;color:#7d8b9c">Loading…</div>
        <div v-else-if="!rules.length" style="padding:16px 18px;font-size:12.5px;color:#7d8b9c">No price rules yet.</div>
        <div v-for="r in rules" :key="r.id" :style="grid + 'padding:12px 18px;border-bottom:1px solid #f0f4f8;align-items:center;'" v-pseudo:hover="'background:#f9fbfd'">
          <div style="font-size:13px;font-weight:600;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ r.name }}</div>
          <div style="font-family:'IBM Plex Mono',monospace;font-size:12px;color:#4a5866">{{ dmy(r.start_date) }}<span v-if="r.end_date !== r.start_date"> – {{ dmy(r.end_date) }}</span></div>
          <div :style="'font-family:\'IBM Plex Mono\',monospace;font-size:12.5px;font-weight:600;text-align:right;color:' + (r.change === 'increase' ? '#8a5300' : '#0f6b43')">{{ r.label }}</div>
          <div style="font-size:12.5px;color:#4a5866;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ r.all_products ? 'All products' : r.products.map((p: any) => p.name).join(', ') }}</div>
          <div style="font-size:12.5px;color:#4a5866;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ r.all_branches ? 'All branches' : r.branches.map((b: any) => b.name).join(', ') }}</div>
          <div><span :style="statusStyle(r.status)">{{ r.status }}</span></div>
          <div style="display:flex;justify-content:flex-end">
            <div v-if="can('settings.edit')" @click="openForm(r)" title="Edit" style="width:26px;height:26px;border-radius:6px;border:1px solid #e4eaf1;display:grid;place-items:center;font-size:11px;color:#5d6b7d;cursor:pointer" v-pseudo:hover="'background:#f2f6fa;color:var(--c-primary)'">✎</div>
          </div>
        </div>
      </div>
      <div style="display:flex;gap:10px;align-items:flex-start;background:var(--c-primary-soft);border:1px solid #cfe0f5;border-radius:10px;padding:13px 15px;font-size:12.5px;color:var(--c-primary-text);line-height:1.55;margin-top:14px;max-width:760px">
        <div style="font-weight:700;flex:none">i</div>
        <div>
          From the start date to the end date (both included), the POS sells at the changed price. The change is made on the price including tax,
          per unit (each, or per kg). Each branch's POS uses only the rules for that branch. If two rules are on for the same
          product and branch, a rule for chosen products wins over an all-products rule, then a rule for chosen branches wins
          over an all-branches rule, and then the newest rule wins. Only one rule is used, and sales already made keep their prices.
        </div>
      </div>
    </div>

    <!-- Add / edit rule -->
    <template v-if="form.open">
      <div @click="form.open = false" style="position:fixed;inset:0;background:rgba(9,22,35,.48);z-index:40"></div>
      <div style="position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);width:520px;max-width:calc(100vw - 32px);max-height:calc(100vh - 40px);overflow-y:auto;background:#ffffff;border-radius:16px;z-index:41;box-shadow:0 30px 80px -24px rgba(9,22,35,.65);padding:22px">
        <div style="font-size:17px;font-weight:700;margin-bottom:16px">{{ form.id ? 'Edit price rule' : 'New price rule' }}</div>
        <div style="display:grid;gap:14px">
          <label :style="labelStyle">Name <input v-model="form.name" :style="inputStyle" placeholder="Eid week" /></label>

          <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
            <label :style="labelStyle">From <input type="date" v-model="form.start_date" :style="inputStyle" /></label>
            <label :style="labelStyle">To <input type="date" v-model="form.end_date" :style="inputStyle" /></label>
          </div>

          <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
            <div :style="labelStyle">Price goes
              <div style="display:flex;gap:6px">
                <div v-for="c in CHANGES" :key="c.key" @click="form.change = c.key" :style="segStyle(form.change === c.key)">{{ c.name }}</div>
              </div>
            </div>
            <div :style="labelStyle">By
              <div style="display:flex;gap:6px">
                <div v-for="t in TYPES" :key="t.key" @click="form.amount_type = t.key" :style="segStyle(form.amount_type === t.key)">{{ t.name }}</div>
              </div>
            </div>
          </div>

          <label :style="labelStyle">{{ form.amount_type === 'percent' ? 'Percent %' : 'Amount $ per unit' }}
            <input v-model="form.amount" :style="inputStyle + 'font-family:\'IBM Plex Mono\',monospace;'" :placeholder="form.amount_type === 'percent' ? '10' : '2.00'" />
            <span style="font-size:11.5px;font-weight:400;color:#7d8b9c">{{ example }}</span>
          </label>

          <div :style="labelStyle">Products
            <div style="display:flex;gap:6px">
              <div @click="form.all_products = true" :style="segStyle(form.all_products)">All products</div>
              <div @click="form.all_products = false" :style="segStyle(!form.all_products)">Chosen products</div>
            </div>
          </div>
          <div v-if="!form.all_products" style="border:1px solid #dfe6ee;border-radius:10px;overflow:hidden">
            <div style="padding:8px;border-bottom:1px solid #eef2f6;display:flex;gap:8px;align-items:center">
              <input v-model="search" placeholder="Search products" :style="inputStyle + 'height:32px;'" />
              <div style="font-size:11.5px;color:#5d6b7d;white-space:nowrap">{{ form.product_ids.length }} chosen</div>
            </div>
            <div style="max-height:220px;overflow-y:auto">
              <div v-if="!products.length" style="padding:10px 12px;font-size:12px;color:#7d8b9c">Loading products…</div>
              <label v-for="p in shownProducts" :key="p.id" style="display:flex;align-items:center;gap:9px;padding:7px 12px;font-size:12.5px;cursor:pointer;border-bottom:1px solid #f4f7fa">
                <input type="checkbox" :value="p.id" v-model="form.product_ids" style="accent-color:var(--c-primary)" />
                <span style="flex:1">{{ p.name }}</span>
                <span style="font-family:'IBM Plex Mono',monospace;font-size:11px;color:#8b98a8">{{ p.sku }}</span>
              </label>
            </div>
          </div>

          <div :style="labelStyle">Branches
            <div style="display:flex;gap:6px">
              <div @click="form.all_branches = true" :style="segStyle(form.all_branches)">All branches</div>
              <div @click="form.all_branches = false" :style="segStyle(!form.all_branches)">Chosen branches</div>
            </div>
          </div>
          <div v-if="!form.all_branches" style="border:1px solid #dfe6ee;border-radius:10px;overflow:hidden">
            <div v-if="!branches.length" style="padding:10px 12px;font-size:12px;color:#7d8b9c">Loading branches…</div>
            <label v-for="b in branches" :key="b.id" style="display:flex;align-items:center;gap:9px;padding:7px 12px;font-size:12.5px;cursor:pointer;border-bottom:1px solid #f4f7fa">
              <input type="checkbox" :value="b.id" v-model="form.branch_ids" style="accent-color:var(--c-primary)" />
              <span style="flex:1">{{ b.name }}</span>
              <span v-if="!b.active" style="font-size:11px;color:#8b98a8">closed</span>
            </label>
          </div>

          <label style="display:flex;align-items:center;gap:9px;font-size:12.5px;font-weight:600;cursor:pointer">
            <input type="checkbox" v-model="form.active" style="accent-color:var(--c-primary)" /> Rule is on
            <span style="font-weight:400;color:#7d8b9c">(off keeps it here but does not use it)</span>
          </label>
        </div>

        <div v-if="form.error" style="margin-top:12px;font-size:12px;color:#b02020;font-weight:600">{{ form.error }}</div>
        <div style="display:flex;gap:9px;margin-top:20px">
          <div v-if="form.id && can('settings.delete')" @click="remove" style="height:38px;padding:0 14px;border-radius:9px;border:1px solid #f3cfcf;background:#fdf4f4;color:#b02020;display:flex;align-items:center;font-size:13px;font-weight:600;cursor:pointer">Delete</div>
          <div style="flex:1"></div>
          <div @click="form.open = false" style="height:38px;padding:0 16px;border-radius:9px;border:1px solid #dfe6ee;display:flex;align-items:center;font-size:13px;font-weight:600;color:#4a5866;cursor:pointer">Cancel</div>
          <div @click="save" :style="'height:38px;padding:0 16px;border-radius:9px;background:var(--c-primary);color:#ffffff;display:flex;align-items:center;font-size:13px;font-weight:700;cursor:pointer;' + (form.saving ? 'opacity:.6;pointer-events:none;' : '')">{{ form.saving ? 'Saving…' : 'Save' }}</div>
        </div>
      </div>
    </template>
  </div>
</template>

<script setup lang="ts">
// Settings > Price rules: sell dearer or cheaper on some dates.
// API: GET/POST/PUT/DELETE /api/settings/price-rules. The POS reads the price with today's rule from /api/pos/catalog.
import { computed, onMounted, reactive, ref } from 'vue';
import { api } from '../api/http.js';
import { can } from '../api/auth.js';
import { dmy, today } from '../api/purchases.js';
import { branches, loadBranches } from '../api/branches.js';
import { useErpPage } from '../erp/useErpPage';

// Fills the left menu (with Settings highlighted), like the other pages.
useErpPage('settings', 'se_nav');

const CHANGES = [{ key: 'increase', name: 'Up' }, { key: 'decrease', name: 'Down' }];
const TYPES = [{ key: 'percent', name: 'Percent %' }, { key: 'fixed', name: 'Fixed $' }];

const grid = 'display:grid;grid-template-columns:1.3fr 1.3fr 0.7fr 1.6fr 1.2fr 0.8fr 40px;gap:12px;';
const labelStyle = 'display:grid;gap:5px;font-size:12px;font-weight:600';
const inputStyle = "width:100%;height:36px;border:1px solid #dfe6ee;border-radius:8px;padding:0 11px;font-family:'Public Sans',sans-serif;font-size:13px;font-weight:400;outline:none;background:#ffffff;";
const segStyle = (on: boolean) => 'flex:1;height:36px;border-radius:8px;display:grid;place-items:center;font-size:12.5px;cursor:pointer;'
  + (on ? 'background:var(--c-primary);color:#ffffff;font-weight:700;' : 'border:1px solid #dfe6ee;color:#4a5866;font-weight:500;');
const STATUS: Record<string, string> = { Running: 'background:#e7f6ee;color:#0f6b43;', Upcoming: 'background:var(--c-primary-soft);color:var(--c-primary-text);', Ended: 'background:#eef2f6;color:#5d6b7d;', Off: 'background:#fdf1dd;color:#8a5300;' };
const statusStyle = (status: string) => 'font-size:11px;font-weight:700;border-radius:99px;padding:3px 9px;' + STATUS[status];

const rules = ref<any[]>([]);
const loading = ref(true);
const running = computed(() => rules.value.filter(r => r.status === 'Running').length);

async function loadRules() {
  rules.value = await api.get('/settings/price-rules');
}

onMounted(async () => {
  try { await loadRules(); } catch (e) { /* the error bar shows why */ }
  loading.value = false;
});

// ---- All products, for "Chosen products" (loaded the first time the form opens) ----

const products = ref<any[]>([]);
const search = ref('');
const shownProducts = computed(() => {
  const q = search.value.trim().toLowerCase();
  return products.value.filter(p => !q || p.name.toLowerCase().includes(q) || String(p.sku).toLowerCase().includes(q));
});

async function loadProducts() {
  if (products.value.length) return;
  const all: any[] = [];
  for (let page = 1; ; page++) {
    const res = await api.get('/products?page=' + page);
    all.push(...res.data);
    if (page >= res.last_page) break;
  }
  products.value = all;
}

// ---- Form ----

const form = reactive({
  open: false, id: 0, saving: false, error: '',
  name: '', start_date: '', end_date: '', change: 'increase', amount_type: 'percent', amount: '',
  all_products: true, product_ids: [] as number[],
  all_branches: true, branch_ids: [] as number[], active: true
});

function openForm(r: any) {
  Object.assign(form, {
    open: true, id: r ? r.id : 0, error: '',
    name: r ? r.name : '', start_date: r ? r.start_date : today(), end_date: r ? r.end_date : today(),
    change: r ? r.change : 'increase', amount_type: r ? r.amount_type : 'percent', amount: r ? String(r.amount) : '',
    all_products: r ? r.all_products : true, product_ids: r ? r.products.map((p: any) => p.id) : [],
    all_branches: r ? r.all_branches : true, branch_ids: r ? r.branches.map((b: any) => b.id) : [], active: r ? r.active : true
  });
  search.value = '';
  loadProducts().catch(() => {});
  if (!branches.value.length) loadBranches().catch(() => {});
}

// "A $20.00 product sells at $22.00", the same sum as PriceRule::apply()
const example = computed(() => {
  const amount = Number(form.amount);
  if (!amount) return '';
  const base = 20;
  const change = form.amount_type === 'percent' ? base * amount / 100 : amount;
  const price = Math.max(0, form.change === 'increase' ? base + change : base - change);
  return 'A $20.00 product sells at $' + price.toFixed(2) + (form.amount_type === 'fixed' ? ' (per kg for kg products)' : '');
});

async function save() {
  if (!form.name.trim()) { form.error = 'Enter a name.'; return; }
  if (!form.start_date || !form.end_date) { form.error = 'Pick the dates.'; return; }
  if (form.end_date < form.start_date) { form.error = 'The end date cannot be before the start date.'; return; }
  if (!(Number(form.amount) > 0)) { form.error = 'Enter an amount more than 0.'; return; }
  if (!form.all_products && !form.product_ids.length) { form.error = 'Pick at least one product, or choose all products.'; return; }
  if (!form.all_branches && !form.branch_ids.length) { form.error = 'Pick at least one branch, or choose all branches.'; return; }
  form.saving = true;
  form.error = '';
  const body = {
    name: form.name.trim(), start_date: form.start_date, end_date: form.end_date,
    change: form.change, amount_type: form.amount_type, amount: Number(form.amount),
    all_products: form.all_products, product_ids: form.all_products ? [] : form.product_ids,
    all_branches: form.all_branches, branch_ids: form.all_branches ? [] : form.branch_ids, active: form.active
  };
  try {
    if (form.id) await api.put('/settings/price-rules/' + form.id, body);
    else await api.post('/settings/price-rules', body);
    await loadRules();
    form.open = false;
  } catch (e: any) {
    form.error = e.message; // e.g. "A decrease cannot be more than 100%."
  }
  form.saving = false;
}

async function remove() {
  if (!confirm('Delete the price rule ' + form.name + '?')) return;
  form.error = '';
  try {
    await api.del('/settings/price-rules/' + form.id);
    await loadRules();
    form.open = false;
  } catch (e: any) {
    form.error = e.message;
  }
}
</script>
