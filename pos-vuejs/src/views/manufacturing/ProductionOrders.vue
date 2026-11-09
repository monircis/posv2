<template>
  <div style="height:100%;display:flex">
    <div style="flex:1;min-width:0;overflow-y:auto">
      <ManufacturingHeader crumb="Production orders">
        <div v-if="can('mfg.create')" @click="openForm()" :style="primaryButton" v-pseudo:hover="'background:var(--c-primary-hover)'">+ New production order</div>
      </ManufacturingHeader>
      <div style="padding:16px 24px 32px;display:grid;gap:14px">
        <div style="display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px">
          <div v-for="k in kpis" :key="k.k" style="background:#ffffff;border:1px solid #dfe6ee;border-radius:12px;padding:13px 16px">
            <div style="font-size:11.5px;color:#7d8b9c">{{ k.k }}</div>
            <div style="font-family:'IBM Plex Mono',monospace;font-size:21px;font-weight:600;margin-top:2px">{{ k.v }}</div>
            <div style="font-size:11.5px;color:#8b98a8;margin-top:1px">{{ k.n }}</div>
          </div>
        </div>
        <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
          <div style="display:flex;gap:3px;background:#e4eaf1;padding:3px;border-radius:9px">
            <div v-for="t in TABS" :key="t.key" @click="setTab(t.key)" :style="segStyle(t.key)">{{ t.name }} {{ counts[t.key] || 0 }}</div>
          </div>
          <select v-if="!userBranchId" v-model.number="branchFilter" @change="reload" style="height:34px;border:1px solid #dfe6ee;border-radius:8px;background:#ffffff;padding:0 8px;font-family:'Public Sans',sans-serif;font-size:12.5px;color:#4a5866">
            <option :value="0">All locations</option>
            <option v-for="b in branches" :key="b.id" :value="b.id">{{ b.name }}</option>
          </select>
        </div>
        <div style="background:#ffffff;border:1px solid #dfe6ee;border-radius:12px;overflow:hidden">
          <div style="display:grid;grid-template-columns:0.8fr 1.7fr 0.9fr 0.9fr 0.9fr 0.8fr 1fr;gap:12px;padding:10px 16px;background:#f6f9fc;border-bottom:1px solid #dfe6ee;font-family:'IBM Plex Mono',monospace;font-size:9.5px;letter-spacing:0.07em;text-transform:uppercase;color:#5d6b7d">
            <div>Order</div>
            <div>Product</div>
            <div style="text-align:right">Planned</div>
            <div style="text-align:right">Output</div>
            <div style="text-align:right">Cost</div>
            <div style="text-align:right">Yield</div>
            <div>Status</div>
          </div>
          <div v-for="r in rows" :key="r.id" @click="openDetails(r.id)" :style="rowStyle(r.id)" v-pseudo:hover="'background:var(--c-primary-tint)'">
            <div>
              <div style="font-family:'IBM Plex Mono',monospace;font-size:12.5px;font-weight:600;color:var(--c-primary)">{{ r.ref }}</div>
              <div style="font-size:10.5px;color:#9aa7b6">{{ dmy(r.date) }}</div>
            </div>
            <div style="min-width:0">
              <div style="font-size:13px;font-weight:500">{{ r.recipe_name }}</div>
              <div style="font-size:11px;color:#8b98a8">{{ r.branch.name }} · {{ batchText(r.batches) }}</div>
            </div>
            <div style="font-family:'IBM Plex Mono',monospace;font-size:12.5px;text-align:right">{{ qty(r.expected_qty, r.unit) }}</div>
            <div style="font-family:'IBM Plex Mono',monospace;font-size:12.5px;text-align:right">{{ r.actual_qty ? qty(r.actual_qty, r.unit) : '—' }}</div>
            <div style="font-family:'IBM Plex Mono',monospace;font-size:12.5px;text-align:right">{{ money(r.material_cost) }}</div>
            <div :style="yieldStyle(r)">{{ yieldText(r) }}</div>
            <div><span :style="statusBadge(r.status)">{{ STATUS_LABELS[r.status] }}</span></div>
          </div>
          <div v-if="!rows.length" style="padding:40px 20px;text-align:center;font-size:13px;color:#7d8b9c">{{ loading ? 'Loading…' : 'No production orders here.' }}</div>
          <div style="display:flex;align-items:center;gap:4px;padding:10px 16px;background:#f6f9fc">
            <div style="flex:1;font-size:12px;color:#7d8b9c">Showing {{ rows.length }} of {{ total }}</div>
            <div @click="goPage(page - 1)" :style="pageStyle(page > 1)">Prev</div>
            <div @click="goPage(page + 1)" :style="pageStyle(page < lastPage)">Next</div>
          </div>
        </div>
      </div>
    </div>

    <!-- The selected order -->
    <div v-if="d" style="width:420px;flex:none;background:#ffffff;border-left:1px solid #dfe6ee;display:flex;flex-direction:column;min-height:0">
      <div style="padding:16px 20px;border-bottom:1px solid #eaeff5">
        <div style="display:flex;align-items:center;gap:9px"><div style="font-family:'IBM Plex Mono',monospace;font-size:18px;font-weight:700">{{ d.ref }}</div><span :style="statusBadge(d.status)">{{ STATUS_LABELS[d.status] }}</span> <div @click="d = null" style="margin-left:auto;width:30px;height:30px;border-radius:8px;border:1px solid #dfe6ee;display:grid;place-items:center;cursor:pointer;color:#5d6b7d" v-pseudo:hover="'background:#f2f6fa'">×</div></div>
        <div style="font-size:13.5px;font-weight:600;margin-top:7px">{{ d.recipe_name }}</div>
        <div style="font-size:12px;color:#7d8b9c">{{ batchText(d.batches) }} · {{ d.branch.name }} · {{ dmy(d.date) }}</div>
      </div>
      <div style="flex:1;overflow-y:auto;padding:16px 20px;display:grid;gap:16px;align-content:start">
        <div style="display:grid;grid-template-columns:1fr 34px 1fr;gap:6px;align-items:stretch">
          <div style="background:#fdf4f4;border-radius:10px;padding:10px 12px">
            <div style="font-family:'IBM Plex Mono',monospace;font-size:9.5px;letter-spacing:0.07em;text-transform:uppercase;color:#b02020;margin-bottom:6px">{{ d.status === 'planned' ? 'Will use' : 'Used' }}</div>
            <div v-for="i in d.items" :key="i.material_id" style="display:flex;gap:6px;font-size:12px;padding:2px 0">
              <div style="flex:1;min-width:0">{{ i.name }}</div>
              <div :style="'font-family:\'IBM Plex Mono\',monospace;font-weight:600;white-space:nowrap;color:' + (isShort(i) ? '#b02020' : '#4a5866')">−{{ qty(i.qty, i.unit) }}</div>
            </div>
            <div v-if="!d.items.length" style="font-size:12px;color:#8b98a8">Nothing was taken.</div>
          </div>
          <div style="display:grid;place-items:center;color:#9aa7b6;font-size:18px">→</div>
          <div style="background:#e7f6ee;border-radius:10px;padding:10px 12px">
            <div style="font-family:'IBM Plex Mono',monospace;font-size:9.5px;letter-spacing:0.07em;text-transform:uppercase;color:#0f6b43;margin-bottom:6px">{{ d.status === 'done' ? 'Made' : 'Expected' }}</div>
            <div style="font-size:13px;font-weight:600">{{ d.recipe_name }}</div>
            <div style="font-family:'IBM Plex Mono',monospace;font-size:18px;font-weight:600;color:#0f6b43;margin-top:3px">+{{ qty(d.actual_qty || d.expected_qty, d.unit) }}</div>
            <div style="font-size:11px;color:#4f9a74;margin-top:2px">{{ money(d.material_cost / (d.actual_qty || d.expected_qty)) }} per {{ d.unit }}</div>
          </div>
        </div>
        <div v-if="shortList.length" :style="errorBox">Short of {{ shortList.join(', ') }}</div>
        <div v-if="!d.has_product && (d.status === 'planned' || d.status === 'progress')" style="background:#fdf1dd;color:#8a5300;border-radius:9px;padding:9px 12px;font-size:12.5px">This recipe has no product linked, so it cannot finish yet. Link one on the Recipes tab.</div>
        <div v-if="d.status === 'progress' && can('mfg.edit')" style="background:#f6f9fc;border-radius:10px;padding:12px 14px;display:grid;gap:8px">
          <label style="font-size:12px;font-weight:600">Actual output ({{ d.unit }})</label>
          <input v-model="actual" @input="cleanActual" style="width:100%;height:36px;border:1px solid #dfe6ee;border-radius:8px;padding:0 11px;outline:none;background:#ffffff;font-family:'IBM Plex Mono',monospace;font-size:15px;font-weight:600;text-align:right" v-pseudo:focus="'border-color:var(--c-primary)'" />
          <div :style="'font-size:12px;' + (actualYield !== null && actualYield < 95 ? 'color:#b45309;font-weight:600;' : 'color:#5d6b7d;')">{{ actualText }}</div>
        </div>
        <div style="display:grid;gap:6px;font-size:12.5px">
          <div style="display:flex;color:#5d6b7d"><div style="flex:1">Materials</div><div style="font-family:'IBM Plex Mono',monospace">{{ money(d.material_cost) }}</div></div>
          <div style="display:flex;color:#5d6b7d"><div style="flex:1">Expected</div><div style="font-family:'IBM Plex Mono',monospace">{{ qty(d.expected_qty, d.unit) }}</div></div>
          <div style="display:flex;color:#5d6b7d"><div style="flex:1">Yield</div><div style="font-family:'IBM Plex Mono',monospace">{{ yieldText(d) }}</div></div>
          <div style="display:flex;font-weight:700;border-top:1px solid #eaeff5;padding-top:7px"><div style="flex:1">Sell value</div><div style="font-family:'IBM Plex Mono',monospace">{{ money((d.actual_qty || d.expected_qty) * d.sell_price) }}</div></div>
        </div>
        <div>
          <div style="font-family:'IBM Plex Mono',monospace;font-size:9.5px;letter-spacing:0.07em;text-transform:uppercase;color:#5d6b7d;margin-bottom:8px">Activity</div>
          <div v-for="a in d.logs" :key="a.id" style="display:flex;gap:10px;margin-bottom:8px">
            <div :style="'width:8px;height:8px;border-radius:50%;margin-top:5px;flex:none;background:' + (LOG_COLORS[a.kind] || LOG_COLORS.neutral)"></div>
            <div style="font-size:12.5px;flex:1">{{ a.what }}</div>
            <div style="font-family:'IBM Plex Mono',monospace;font-size:11px;color:#9aa7b6;white-space:nowrap">{{ when(a.created_at) }}</div>
          </div>
        </div>
        <div v-if="actionError" :style="errorBox">{{ actionError }}</div>
      </div>
      <div style="padding:12px 20px;border-top:1px solid #eaeff5;display:flex;gap:7px">
        <div v-if="canCancel" @click="cancelOrder" style="height:40px;padding:0 14px;border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:600;cursor:pointer;border:1px solid #f3cfcf;background:#fdf4f4;color:#b02020">Cancel</div>
        <div v-if="d.status === 'planned' && can('mfg.edit')" @click="startOrder" :style="bigButton(shortList.length ? '#9fbde0' : 'var(--c-primary)')">{{ busy ? 'Starting…' : 'Start production' }}</div>
        <div v-if="d.status === 'progress' && can('mfg.edit')" @click="finishOrder" :style="bigButton('#2f9e63')">{{ busy ? 'Saving…' : 'Finish & add to stock' }}</div>
        <div v-if="(d.status === 'done' || d.status === 'cancelled') && can('mfg.create')" @click="openForm(d)" style="height:40px;padding:0 14px;border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:600;cursor:pointer;border:1px solid #dfe6ee;color:#4a5866">Duplicate</div>
      </div>
    </div>

    <!-- New production order -->
    <template v-if="formOpen">
      <div @click="formOpen = false" style="position:fixed;inset:0;background:rgba(9,22,35,.42);z-index:30"></div>
      <div style="position:fixed;top:0;right:0;bottom:0;width:480px;background:#ffffff;z-index:31;display:flex;flex-direction:column;box-shadow:-20px 0 50px -24px rgba(9,22,35,.6)">
        <div style="padding:16px 22px;border-bottom:1px solid #eaeff5;display:flex;align-items:center">
          <div>
            <div style="font-size:16px;font-weight:700">New production order</div>
            <div style="font-size:12px;color:#5d6b7d;margin-top:2px">{{ nextRef }} · materials come out of stock when production starts</div>
          </div>
          <div @click="formOpen = false" style="margin-left:auto;width:32px;height:32px;border-radius:8px;border:1px solid #dfe6ee;display:grid;place-items:center;cursor:pointer;color:#5d6b7d">×</div>
        </div>
        <div style="flex:1;overflow-y:auto;padding:18px 22px;display:grid;gap:14px;align-content:start">
          <div>
            <label :style="labelStyle">Recipe</label>
            <select v-model.number="form.recipe_id" :style="inputStyle + ';cursor:pointer;padding:0 8px'">
              <option v-for="r in recipes" :key="r.id" :value="r.id">{{ r.name }} · {{ qty(r.output_qty, r.unit) }} per batch</option>
            </select>
          </div>
          <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px">
            <div>
              <label :style="labelStyle">Batches</label>
              <div style="display:flex;align-items:center;height:36px;border:1px solid #dfe6ee;border-radius:8px;overflow:hidden">
                <div @click="form.batches = Math.max(1, form.batches - 1)" style="width:34px;height:100%;display:grid;place-items:center;cursor:pointer;border-right:1px solid #dfe6ee">−</div>
                <div style="flex:1;text-align:center;font-family:'IBM Plex Mono',monospace;font-weight:600">{{ form.batches }}</div>
                <div @click="form.batches++" style="width:34px;height:100%;display:grid;place-items:center;cursor:pointer;border-left:1px solid #dfe6ee">+</div>
              </div>
            </div>
            <div>
              <label :style="labelStyle">Location</label>
              <select v-model.number="form.branch_id" @change="loadForm" :disabled="!!userBranchId" :style="inputStyle + ';cursor:pointer;padding:0 8px'">
                <option v-for="b in activeBranches" :key="b.id" :value="b.id">{{ b.name }}</option>
              </select>
            </div>
            <div>
              <label :style="labelStyle">Date</label>
              <input v-model="form.date" type="date" :style="inputStyle + ';padding:0 8px;font-size:12px'" />
            </div>
          </div>
          <div style="background:#ffffff;border:1px solid #dfe6ee;border-radius:12px;overflow:hidden">
            <div style="display:grid;grid-template-columns:minmax(0,1.8fr) 1fr 1fr 1fr;gap:10px;padding:8px 14px;background:#f6f9fc;border-bottom:1px solid #dfe6ee;font-family:'IBM Plex Mono',monospace;font-size:9.5px;letter-spacing:0.07em;text-transform:uppercase;color:#5d6b7d">
              <div>Material</div>
              <div style="text-align:right">Needed</div>
              <div style="text-align:right">In stock</div>
              <div style="text-align:right">After</div>
            </div>
            <div v-for="l in formLines" :key="l.id" style="display:grid;grid-template-columns:minmax(0,1.8fr) 1fr 1fr 1fr;gap:10px;padding:8px 14px;border-bottom:1px solid #f0f4f8;font-size:12.5px">
              <div style="font-weight:500">{{ l.name }}</div>
              <div style="font-family:'IBM Plex Mono',monospace;text-align:right">{{ qty(l.need, l.unit) }}</div>
              <div style="font-family:'IBM Plex Mono',monospace;text-align:right;color:#5d6b7d">{{ qty(l.have, l.unit) }}</div>
              <div :style="'font-family:\'IBM Plex Mono\',monospace;text-align:right;font-weight:600;color:' + (l.have - l.need < 0 ? '#b02020' : '#0f6b43')">{{ qty(l.have - l.need, l.unit) }}</div>
            </div>
          </div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
            <div style="background:#f6f9fc;border-radius:10px;padding:11px 13px">
              <div style="font-size:11px;color:#7d8b9c">Expected output</div>
              <div style="font-family:'IBM Plex Mono',monospace;font-size:18px;font-weight:600">{{ formRecipe ? qty(formRecipe.output_qty * form.batches, formRecipe.unit) : '—' }}</div>
            </div>
            <div style="background:#f6f9fc;border-radius:10px;padding:11px 13px">
              <div style="font-size:11px;color:#7d8b9c">Material cost</div>
              <div style="font-family:'IBM Plex Mono',monospace;font-size:18px;font-weight:600">{{ formRecipe ? money(formRecipe.batch_cost * form.batches) : '—' }}</div>
            </div>
          </div>
          <div v-if="formShort" :style="errorBox">Not enough stock to start now. You can still save it as planned.</div>
          <div v-if="formError" :style="errorBox">{{ formError }}</div>
        </div>
        <div style="padding:14px 22px;border-top:1px solid #eaeff5;display:flex;gap:8px;justify-content:flex-end">
          <div @click="formOpen = false" :style="ghostButton">Cancel</div>
          <div @click="saveOrder(false)" :style="ghostButton + ';font-weight:600;color:var(--c-primary-text)'">Save as planned</div>
          <div v-if="can('mfg.edit')" @click="saveOrder(true)" :style="primaryButton + ';background:' + (formShort ? '#9fbde0' : 'var(--c-primary)')">Start production</div>
        </div>
      </div>
    </template>
  </div>
</template>

<script setup lang="ts">
// Production orders (/manufacturing).
// API: GET /api/production-orders, GET form-data, POST, and POST /{id}/start, /finish, /cancel
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { api } from '../../api/http.js';
import { can } from '../../api/auth.js';
import { branches, loadBranches } from '../../api/branches.js';
import { money, qty, dmy, today, userBranchId, statusBadge, STATUS_LABELS, inputStyle, labelStyle, ghostButton, primaryButton, errorBox } from '../../api/manufacturing.js';
import ManufacturingHeader from './ManufacturingHeader.vue';

const route = useRoute();
const router = useRouter();

const TABS = [
  { key: 'all', name: 'All' }, { key: 'planned', name: 'Planned' }, { key: 'progress', name: 'In progress' },
  { key: 'done', name: 'Done' }, { key: 'cancelled', name: 'Cancelled' }
];
const LOG_COLORS: Record<string, string> = { neutral: '#8fa3b6', info: 'var(--c-primary)', success: '#2f9e63', error: '#d93a3a' };

const tab = ref('all');
const branchFilter = ref(0);
const page = ref(1);
const rows = ref<any[]>([]);
const total = ref(0);
const lastPage = ref(1);
const counts = ref<Record<string, number>>({});
const month = ref<any>({ finished: 0, output: {}, average_yield: null, material_cost: 0 });
const loading = ref(true);

const d = ref<any>(null);
const actual = ref('');
const busy = ref(false);
const actionError = ref('');

const formOpen = ref(false);
const form = reactive({ recipe_id: 0, branch_id: 0, batches: 1, date: today() });
const recipes = ref<any[]>([]);
const nextRef = ref('');
const formError = ref('');

const kpis = computed(() => {
  const output = Object.entries(month.value.output).map(([unit, n]) => qty(n as number, unit));
  return [
    { k: 'In progress', v: String(counts.value.progress || 0), n: (counts.value.planned || 0) + ' planned' },
    { k: 'Output this month', v: output[0] || '0', n: output.slice(1).join(' · ') || month.value.finished + ' finished orders' },
    { k: 'Average yield', v: month.value.average_yield === null ? '—' : month.value.average_yield + '%', n: 'actual vs expected, this month' },
    { k: 'Material cost', v: money(month.value.material_cost), n: month.value.finished + ' finished this month' }
  ];
});

const activeBranches = computed(() => branches.value.filter(b => b.active || b.id === form.branch_id));
const formRecipe = computed(() => recipes.value.find(r => r.id === form.recipe_id));
const formLines = computed(() => (formRecipe.value ? formRecipe.value.items.map((i: any) => ({
  id: i.id, name: i.material.name, unit: i.material.unit, need: i.qty * form.batches, have: i.on_hand
})) : []));
const formShort = computed(() => formLines.value.some((l: any) => l.have < l.need - 1e-9));

const shortList = computed(() => (d.value && d.value.status === 'planned' ? d.value.items.filter(isShort).map((i: any) => i.name + ' (' + qty(i.qty - i.on_hand, i.unit) + ')') : []));
const canCancel = computed(() => can('mfg.edit') && (d.value.status === 'planned' || d.value.status === 'progress'));
const actualNumber = computed(() => parseFloat(actual.value));
const actualYield = computed(() => (actualNumber.value > 0 ? actualNumber.value / d.value.expected_qty * 100 : null));
const actualText = computed(() => {
  const x = d.value;
  if (actualYield.value === null) return 'Weigh the finished product. Expected ' + qty(x.expected_qty, x.unit);
  return 'Yield ' + actualYield.value.toFixed(1) + '% · ' + (actualNumber.value < x.expected_qty ? qty(x.expected_qty - actualNumber.value, x.unit) + ' trim and loss' : 'on or over target');
});

function isShort(item: any) {
  return item.on_hand !== undefined && item.on_hand < item.qty - 1e-9;
}
function batchText(n: number) {
  return n + (n === 1 ? ' batch' : ' batches');
}
function yieldText(r: any) {
  const value = r.actual_qty ? r.actual_qty : r === d.value && actualYield.value !== null ? actualNumber.value : null;
  return value ? (value / r.expected_qty * 100).toFixed(1) + '%' : '—';
}
function when(iso: string) {
  const x = new Date(iso);
  return String(x.getDate()).padStart(2, '0') + '/' + String(x.getMonth() + 1).padStart(2, '0') + ' ' + String(x.getHours()).padStart(2, '0') + ':' + String(x.getMinutes()).padStart(2, '0');
}
function cleanActual() {
  actual.value = actual.value.replace(/[^0-9.]/g, '');
}

async function load() {
  loading.value = true;
  const params = new URLSearchParams({ page: String(page.value) });
  if (tab.value !== 'all') params.set('status', tab.value);
  if (branchFilter.value) params.set('branch_id', String(branchFilter.value));
  try {
    const res = await api.get('/production-orders?' + params.toString());
    rows.value = res.data;
    total.value = res.total;
    lastPage.value = res.last_page;
    counts.value = res.counts;
    month.value = res.month;
  } catch (e) { /* the error bar shows why */ }
  loading.value = false;
}

onMounted(() => {
  load();
  loadBranches().catch(() => {});
  if (route.query.new) openForm();
});
watch(() => route.query.new, value => { if (value) openForm(); });

function reload() { page.value = 1; load(); }
function setTab(key: string) { tab.value = key; reload(); }
function goPage(n: number) { if (n < 1 || n > lastPage.value) return; page.value = n; load(); }

async function openDetails(id: number) {
  actual.value = '';
  actionError.value = '';
  try {
    d.value = await api.get('/production-orders/' + id);
  } catch (e: any) {
    actionError.value = e.message;
  }
}

// Stock is shown for the chosen branch, so skip a late answer for a branch no longer picked.
async function loadForm() {
  const branch = form.branch_id;
  const data = await api.get('/production-orders/form-data' + (branch ? '?branch_id=' + branch : '')).catch(() => null);
  if (!data || branch !== form.branch_id) return;
  recipes.value = data.recipes;
  nextRef.value = data.next_ref;
  if (!form.branch_id) form.branch_id = data.branch_id;
  if (!data.recipes.some((r: any) => r.id === form.recipe_id)) form.recipe_id = data.recipes.length ? data.recipes[0].id : 0;
}

function openForm(from?: any) {
  formError.value = '';
  Object.assign(form, {
    recipe_id: from ? from.recipe_id : 0,
    branch_id: from ? from.branch_id : 0,
    batches: from ? from.batches : 1,
    date: today()
  });
  formOpen.value = true;
  loadForm();
  if (route.query.new) router.replace({ name: 'mfg' });
}

async function saveOrder(start: boolean) {
  if (busy.value) return;
  formError.value = '';
  if (!form.recipe_id) { formError.value = 'Add a recipe first.'; return; }
  if (start && formShort.value) { formError.value = 'Not enough materials to start. Save it as planned instead.'; return; }
  busy.value = true;
  try {
    d.value = await api.post('/production-orders', { ...form, start });
    actual.value = '';
    actionError.value = '';
    formOpen.value = false;
    load();
  } catch (e: any) {
    formError.value = e.message;
  }
  busy.value = false;
}

async function act(path: string, body?: any) {
  if (busy.value) return;
  actionError.value = '';
  busy.value = true;
  try {
    d.value = await api.post('/production-orders/' + d.value.id + '/' + path, body);
    actual.value = '';
    load();
  } catch (e: any) {
    actionError.value = e.message;
  }
  busy.value = false;
}

function startOrder() {
  if (shortList.value.length) { actionError.value = 'Not enough materials to start.'; return; }
  act('start');
}
function finishOrder() {
  if (!(actualNumber.value > 0)) { actionError.value = 'Enter the actual output.'; return; }
  act('finish', { actual_qty: actualNumber.value });
}
function cancelOrder() {
  const started = d.value.status === 'progress' && d.value.materials_taken;
  if (!confirm('Cancel ' + d.value.ref + '?' + (started ? ' The materials go back into stock.' : ''))) return;
  act('cancel');
}

function rowStyle(id: number) {
  return 'display:grid;grid-template-columns:0.8fr 1.7fr 0.9fr 0.9fr 0.9fr 0.8fr 1fr;gap:12px;padding:10px 16px;border-bottom:1px solid #f0f4f8;align-items:center;cursor:pointer;'
    + (d.value && d.value.id === id ? 'background:#f0f6fd;box-shadow:inset 3px 0 0 var(--c-primary);' : '');
}
function yieldStyle(r: any) {
  const low = r.actual_qty && r.actual_qty / r.expected_qty < 0.95;
  return "font-family:'IBM Plex Mono',monospace;font-size:12.5px;text-align:right;" + (low ? 'color:#b45309;font-weight:600;' : '');
}
function segStyle(key: string) {
  return 'height:30px;padding:0 12px;border-radius:7px;display:grid;place-items:center;font-size:12.5px;cursor:pointer;'
    + (tab.value === key ? 'background:#ffffff;color:#0f1c2b;font-weight:700;box-shadow:0 1px 3px rgba(15,28,43,.14);' : 'color:#5d6b7d;font-weight:500;');
}
function pageStyle(on: boolean) {
  return 'height:26px;padding:0 8px;border-radius:6px;border:1px solid #dfe6ee;display:flex;align-items:center;font-size:11.5px;' + (on ? 'color:#4a5866;cursor:pointer' : 'color:#c2cedb');
}
function bigButton(background: string) {
  return 'flex:1;height:40px;padding:0 14px;border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:600;cursor:pointer;color:#ffffff;background:' + background;
}
</script>
