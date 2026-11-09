<template>
  <template v-if="vm.puList">
    <div>
      <div style="padding:20px 24px 0;background:#ffffff;border-bottom:1px solid #dfe6ee">
        <div style="display:flex;align-items:center;gap:7px;font-size:11.5px;color:#7d8b9c;margin-bottom:8px"><span>Purchases</span><span style="color:#c2cedb">/</span><span style="color:#0f1c2b;font-weight:500">All purchases</span></div>
        <div style="display:flex;align-items:flex-start;gap:20px;flex-wrap:wrap">
          <div>
            <h2 style="margin:0 0 3px;font-size:22px;font-weight:700;letter-spacing:-0.018em">Purchases</h2>
            <div style="font-size:12.5px;color:#5d6b7d">{{ counts.all || 0 }} purchases · {{ money(owed) }} still owed to suppliers</div>
          </div>
          <div style="margin-left:auto;display:flex;gap:8px;align-items:center">
            <div @click="vm.puGoReturns" style="height:34px;padding:0 13px;border-radius:8px;border:1px solid #dfe6ee;display:flex;align-items:center;font-size:12.5px;font-weight:500;color:#4a5866;cursor:pointer" v-pseudo:hover="'background:#f2f6fa'">Returns</div>
            <div v-if="can('purchases.create')" @click="vm.puGoForm" style="height:34px;padding:0 15px;border-radius:8px;background:var(--c-primary);color:#ffffff;display:flex;align-items:center;font-size:12.5px;font-weight:600;cursor:pointer" v-pseudo:hover="'background:var(--c-primary-hover)'">+ New purchase</div>
          </div>
        </div>
        <div style="display:flex;gap:2px;margin-top:16px">
          <div v-for="t in TABS" :key="t.key" @click="setTab(t.key)" :style="tabStyle(t.key)">{{ t.name }}<span :style="countStyle(t.key)">{{ counts[t.key] || 0 }}</span></div>
        </div>
      </div>
      <div style="padding:20px 24px 32px">
        <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-bottom:14px">
          <div style="position:relative;width:250px"><div style="position:absolute;left:10px;top:50%;transform:translateY(-50%);color:#9aa7b6;font-size:12px">⚲</div> <input :value="search" @input="onSearch" placeholder="Reference or supplier" style="width:100%;height:34px;border:1px solid #dfe6ee;border-radius:8px;background:#ffffff;padding:0 10px 0 29px;font-family:'Public Sans',sans-serif;font-size:12.5px;outline:none" v-pseudo:focus="'border-color:var(--c-primary)'" /></div>
          <select v-if="!userBranchId" v-model.number="branchId" @change="reload" title="Purchases of one branch" :style="filterStyle">
            <option :value="0">All branches</option>
            <option v-for="b in branches" :key="b.id" :value="b.id">{{ b.name }}</option>
          </select>
          <input v-model="from" @change="reload" type="date" title="From" :style="filterStyle" />
          <input v-model="to" @change="reload" type="date" title="To" :style="filterStyle" />
          <select v-model.number="supplierId" @change="reload" :style="filterStyle">
            <option :value="0">All suppliers</option>
            <option v-for="c in suppliers" :key="c.id" :value="c.id">{{ c.name }}</option>
          </select>
          <select v-model="payment" @change="reload" :style="filterStyle">
            <option value="">All payments</option>
            <option value="paid">Paid</option>
            <option value="partial">Partial</option>
            <option value="due">Due</option>
          </select>
        </div>
        <div style="background:#ffffff;border:1px solid #dfe6ee;border-radius:12px;overflow:hidden">
          <div style="display:grid;grid-template-columns:1.1fr 1.7fr 1fr 1fr 1fr 1fr 1fr;gap:12px;padding:10px 16px;background:#f6f9fc;border-bottom:1px solid #dfe6ee;font-family:'IBM Plex Mono',monospace;font-size:9.5px;letter-spacing:0.07em;text-transform:uppercase;color:#5d6b7d;align-items:center">
            <div>Reference</div>
            <div>Supplier</div>
            <div>Status</div>
            <div>Payment</div>
            <div style="text-align:right">Total</div>
            <div style="text-align:right">Due</div>
            <div>Branch</div>
          </div>
          <div v-if="!rows.length" style="padding:24px 16px;font-size:13px;color:#7d8b9c;text-align:center">{{ loading ? 'Loading…' : 'No purchases found.' }}</div>
          <div v-for="r in rows" :key="r.id" style="display:grid;grid-template-columns:1.1fr 1.7fr 1fr 1fr 1fr 1fr 1fr;gap:12px;padding:11px 16px;border-bottom:1px solid #f0f4f8;align-items:center" v-pseudo:hover="'background:#f9fbfd'">
            <div>
              <div @click="openDetails(r.id)" style="font-family:'IBM Plex Mono',monospace;font-size:12.5px;font-weight:500;color:var(--c-primary);cursor:pointer">{{ r.ref }}</div>
              <div style="font-size:10.5px;color:#9aa7b6;margin-top:1px">{{ dmy(r.date) }}</div>
            </div>
            <div style="min-width:0">
              <div style="font-size:13px;font-weight:500;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">{{ r.supplier ? r.supplier.name : '' }}</div>
              <div style="font-size:11px;color:#8b98a8">{{ r.items_count }} {{ r.items_count === 1 ? 'line' : 'lines' }}</div>
            </div>
            <div><span :style="badge(r.status)">{{ STATUS_LABELS[r.status] || r.status }}</span></div>
            <div><span :style="badge(r.payment_status)">{{ PAY_LABELS[r.payment_status] }}</span></div>
            <div style="font-family:'IBM Plex Mono',monospace;font-size:12.5px;text-align:right;font-weight:500">{{ money(r.total) }}</div>
            <div :style="'font-family:\'IBM Plex Mono\',monospace;font-size:12.5px;text-align:right;' + (r.due > 0 ? 'color:#b02020;font-weight:600' : 'color:#9aa7b6')">{{ r.due > 0 ? money(r.due) : '—' }}</div>
            <div style="font-size:12.5px;color:#4a5866">{{ r.branch ? r.branch.name : '' }}</div>
          </div>
          <div style="display:grid;grid-template-columns:1.1fr 1.7fr 1fr 1fr 1fr 1fr 1fr;gap:12px;padding:12px 16px;background:#f6f9fc;align-items:center">
            <div style="font-size:12px;color:#7d8b9c;grid-column:span 4">Showing {{ rows.length }} of {{ total }} · totals for current filter</div>
            <div style="font-family:'IBM Plex Mono',monospace;font-size:13px;text-align:right;font-weight:700">{{ money(totals.total) }}</div>
            <div style="font-family:'IBM Plex Mono',monospace;font-size:13px;text-align:right;font-weight:700;color:#b02020">{{ money(totals.due) }}</div>
            <div style="display:flex;justify-content:flex-end;gap:4px">
              <div @click="goPage(page - 1)" :style="pageStyle(page > 1)">Prev</div>
              <div @click="goPage(page + 1)" :style="pageStyle(page < lastPage)">Next</div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </template>
</template>

<script setup lang="ts">
// Purchases > All purchases. API: GET /api/purchases?tab=&search=&branch_id=&supplier_id=&payment_status=&from=&to=&page=
import { computed, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import { usePageVm } from '../../erp/useErpPage';
import { api } from '../../api/http.js';
import { can } from '../../api/auth.js';
import { branches, loadBranches } from '../../api/branches.js';
import { contacts, loadContacts } from '../../api/contacts.js';
import { money, badge, dmy, userBranchId, STATUS_LABELS, PAY_LABELS } from '../../api/purchases.js';

const vm = usePageVm();
const router = useRouter();

const TABS = [
  { key: 'all', name: 'All' }, { key: 'ordered', name: 'Ordered' }, { key: 'received', name: 'Received' }, { key: 'unpaid', name: 'Unpaid' }
];
const filterStyle = "height:34px;border:1px solid #dfe6ee;border-radius:8px;background:#ffffff;padding:0 8px;font-family:'Public Sans',sans-serif;font-size:12.5px;color:#4a5866";

const tab = ref('all');
const search = ref('');
const branchId = ref(0);
const supplierId = ref(0);
const payment = ref('');
const from = ref('');
const to = ref('');
const page = ref(1);

const rows = ref<any[]>([]);
const total = ref(0);
const lastPage = ref(1);
const counts = ref<Record<string, number>>({});
const totals = ref<any>({ total: 0, due: 0 });
const owed = ref(0);
const loading = ref(true);

const suppliers = computed(() => contacts.value.filter((c: any) => c.type !== 'Customer'));

async function load() {
  loading.value = true;
  const params = new URLSearchParams({ page: String(page.value) });
  if (tab.value !== 'all') params.set('tab', tab.value);
  if (search.value.trim()) params.set('search', search.value.trim());
  if (branchId.value) params.set('branch_id', String(branchId.value));
  if (supplierId.value) params.set('supplier_id', String(supplierId.value));
  if (payment.value) params.set('payment_status', payment.value);
  if (from.value) params.set('from', from.value);
  if (to.value) params.set('to', to.value);
  try {
    const res = await api.get('/purchases?' + params.toString());
    rows.value = res.data;
    total.value = res.total;
    lastPage.value = res.last_page;
    counts.value = res.counts;
    totals.value = res.totals;
    owed.value = res.owed;
  } catch (e) { /* the error bar shows why */ }
  loading.value = false;
}

onMounted(() => {
  load();
  loadBranches().catch(() => {});
  loadContacts().catch(() => {});
});

function reload() { page.value = 1; load(); }
function setTab(key: string) { tab.value = key; reload(); }
function goPage(n: number) { if (n < 1 || n > lastPage.value) return; page.value = n; load(); }
function openDetails(id: number) { router.push({ name: 'purchases-details', query: { id } }); }

// Wait until typing stops for a moment before searching.
let timer: any = null;
function onSearch(e: Event) {
  search.value = (e.target as HTMLInputElement).value;
  clearTimeout(timer);
  timer = setTimeout(reload, 300);
}

function tabStyle(key: string) {
  return 'padding:9px 14px;font-size:13px;cursor:pointer;border-bottom:2px solid '
    + (tab.value === key ? 'var(--c-primary);color:var(--c-primary);font-weight:600;' : 'transparent;color:#5d6b7d;font-weight:500;');
}
function countStyle(key: string) {
  return "font-family:'IBM Plex Mono',monospace;font-size:10.5px;border-radius:4px;padding:1px 5px;margin-left:6px;"
    + (tab.value === key ? 'background:var(--c-primary-soft);color:var(--c-primary-text);' : 'background:#eef2f6;color:#7d8b9c;');
}
function pageStyle(on: boolean) {
  return 'height:26px;padding:0 8px;border-radius:6px;border:1px solid #dfe6ee;display:flex;align-items:center;font-size:11.5px;' + (on ? 'color:#4a5866;cursor:pointer' : 'color:#c2cedb');
}
</script>
