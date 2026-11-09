<template>
  <div>
    <div style="padding:20px 24px 0;background:#ffffff;border-bottom:1px solid #dfe6ee">
      <div style="display:flex;align-items:center;gap:7px;font-size:11.5px;color:#7d8b9c;margin-bottom:8px"><span>Sell</span><span style="color:#c2cedb">/</span><span style="color:#0f1c2b;font-weight:500">Sales</span></div>
      <div style="display:flex;align-items:flex-start;gap:20px;flex-wrap:wrap">
        <div>
          <h2 style="margin:0 0 3px;font-size:22px;font-weight:700;letter-spacing:-0.018em">Sales</h2>
          <div style="font-size:12.5px;color:#5d6b7d">POS, online and order sales in one list{{ userBranchId ? ' · your branch' : '' }}</div>
        </div>
        <div style="margin-left:auto;display:flex;gap:8px;align-items:center">
          <RouterLink to="/sell/registers" style="height:34px;padding:0 14px;border-radius:8px;border:1px solid #dfe6ee;background:#ffffff;color:#34485c;display:flex;align-items:center;font-size:12.5px;font-weight:600;text-decoration:none" v-pseudo:hover="'background:#f2f6fa'">Cash registers</RouterLink>
          <template v-if="can('sell.create')">
          <RouterLink to="/pos" style="height:34px;padding:0 15px;border-radius:8px;background:var(--c-primary);color:#ffffff;display:flex;align-items:center;font-size:12.5px;font-weight:600;cursor:pointer;text-decoration:none" v-pseudo:hover="'background:var(--c-primary-hover);color:#ffffff'">+ New sale</RouterLink>
          </template>
        </div>
      </div>
      <div style="display:flex;gap:2px;margin-top:16px">
        <div v-for="t in TABS" :key="t.key" @click="setTab(t.key)" :style="tabStyle(t.key)">{{ t.name }}<span :style="countStyle(t.key)">{{ counts[t.key] || 0 }}</span></div>
      </div>
    </div>
    <div style="padding:20px 24px 32px">
      <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:16px">
        <div v-for="k in kpis" :key="k.label" style="background:#ffffff;border:1px solid #dfe6ee;border-radius:12px;padding:14px 16px">
          <div style="font-size:11.5px;color:#7d8b9c;margin-bottom:7px">{{ k.label }}</div>
          <div :style="'font-family:\'IBM Plex Mono\',monospace;font-size:22px;font-weight:600;letter-spacing:-0.025em;line-height:1;' + k.color">{{ k.value }}</div>
          <div style="font-size:11.5px;color:#7d8b9c;margin-top:5px">{{ k.sub }}</div>
        </div>
      </div>
      <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-bottom:14px">
        <div style="position:relative;width:250px"><div style="position:absolute;left:10px;top:50%;transform:translateY(-50%);color:#9aa7b6;font-size:12px">⚲</div> <input :value="search" @input="onSearch" placeholder="Invoice, customer or phone" style="width:100%;height:34px;border:1px solid #dfe6ee;border-radius:8px;background:#ffffff;padding:0 10px 0 29px;font-family:'Public Sans',sans-serif;font-size:12.5px;outline:none" v-pseudo:focus="'border-color:var(--c-primary)'" /></div>
        <select v-if="!userBranchId" v-model.number="branchId" @change="reload" :style="filterStyle">
          <option :value="0">All locations</option>
          <option v-for="b in branches" :key="b.id" :value="b.id">{{ b.name }}</option>
        </select>
        <input v-model="dateFrom" @change="reload" type="date" title="From date" :style="filterStyle" />
        <input v-model="dateTo" @change="reload" type="date" title="To date" :style="filterStyle" />
        <div v-for="c in CHANNELS" :key="c.key" @click="setChannel(c.key)" :style="channelStyle(c.key)">{{ c.name }}</div>
        <div v-if="hasFilters" @click="clearFilters" style="font-size:12.5px;font-weight:600;color:var(--c-primary);cursor:pointer;padding:0 6px">Clear</div>
      </div>
      <div style="background:#ffffff;border:1px solid #dfe6ee;border-radius:12px;overflow:hidden">
        <div :style="rowGrid + 'padding:10px 16px;background:#f6f9fc;border-bottom:1px solid #dfe6ee;font-family:\'IBM Plex Mono\',monospace;font-size:9.5px;letter-spacing:0.07em;text-transform:uppercase;color:#5d6b7d'">
          <div>Invoice</div>
          <div>Customer</div>
          <div>Channel</div>
          <div>Method</div>
          <div style="text-align:right">Total</div>
          <div style="text-align:right">Due</div>
          <div>Status</div>
          <div>By</div>
        </div>
        <div v-for="r in rows" :key="r.id" @click="openDetails(r.id)" :style="rowGrid + 'padding:10px 16px;border-bottom:1px solid #f0f4f8;cursor:pointer'" v-pseudo:hover="'background:#f9fbfd'">
          <div>
            <div style="font-family:'IBM Plex Mono',monospace;font-size:12.5px;font-weight:500;color:var(--c-primary)">{{ r.ref }}</div>
            <div style="font-size:10.5px;color:#9aa7b6;margin-top:1px">{{ dateTime(r.sold_at) }}</div>
          </div>
          <div style="min-width:0">
            <div style="font-size:13px;font-weight:500;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">{{ r.customer_name }}</div>
            <div style="font-size:11px;color:#8b98a8">{{ r.item_count }} {{ r.item_count === 1 ? 'item' : 'items' }} · {{ r.branch.name }}</div>
          </div>
          <div style="font-size:12.5px;color:#4a5866">{{ r.channel }}</div>
          <div style="font-size:12.5px;color:#4a5866">{{ r.method || '—' }}</div>
          <div style="font-family:'IBM Plex Mono',monospace;font-size:12.5px;text-align:right;font-weight:500">{{ r.status === 'cancelled' ? '(' + money(r.total) + ')' : money(r.total) }}</div>
          <div :style="'font-family:\'IBM Plex Mono\',monospace;font-size:12.5px;text-align:right;' + (r.due ? 'color:#b02020;font-weight:600;' : 'color:#9aa7b6;')">{{ r.due ? money(r.due) : '—' }}</div>
          <div><span :style="statusBadge(r.status)">{{ STATUS_LABELS[r.status] || r.status }}</span></div>
          <div style="font-size:12px;color:#5d6b7d;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">{{ r.created_by }}</div>
        </div>
        <div v-if="!rows.length" style="padding:40px 20px;text-align:center">
          <div style="font-size:13.5px;font-weight:600;margin-bottom:4px">{{ loading ? 'Loading…' : 'No sales match' }}</div>
          <div v-if="!loading" style="font-size:12.5px;color:#7d8b9c">Try another status or clear the filters.</div>
        </div>
        <div :style="rowGrid + 'padding:12px 16px;background:#f6f9fc'">
          <div style="font-size:12px;color:#7d8b9c;grid-column:span 4">Showing {{ rows.length }} of {{ total }} · totals for current filter</div>
          <div style="font-family:'IBM Plex Mono',monospace;font-size:13px;text-align:right;font-weight:700">{{ money(totalAmount) }}</div>
          <div style="font-family:'IBM Plex Mono',monospace;font-size:13px;text-align:right;font-weight:700;color:#b02020">{{ money(totalDue) }}</div>
          <div style="display:flex;justify-content:flex-end;gap:4px;grid-column:span 2">
            <div @click="goPage(page - 1)" :style="pageStyle(page > 1)">Prev</div>
            <div @click="goPage(page + 1)" :style="pageStyle(page < lastPage)">Next</div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
// Sales list. API: GET /api/sales?tab=&search=&branch_id=&channel=&from=&to=&page=
import { computed, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import { api } from '../../api/http.js';
import { can } from '../../api/auth.js';
import { branches, loadBranches } from '../../api/branches.js';
import { userBranchId } from '../../api/purchases.js';
import { money, dateTime, statusBadge, STATUS_LABELS } from '../../api/sales.js';

const router = useRouter();

const TABS = [
  { key: 'all', name: 'All' }, { key: 'paid', name: 'Paid' }, { key: 'due', name: 'Due' },
  { key: 'draft', name: 'Drafts' }, { key: 'quote', name: 'Quotations' }, { key: 'cancelled', name: 'Cancelled' }
];
const CHANNELS = [{ key: '', name: 'All channels' }, { key: 'POS', name: 'POS' }, { key: 'Online', name: 'Online' }, { key: 'Order', name: 'Order' }];
const rowGrid = 'display:grid;grid-template-columns:1.1fr 1.6fr 0.7fr 1fr 0.95fr 0.95fr 0.9fr 0.8fr;gap:12px;align-items:center;';
const filterStyle = "height:34px;border:1px solid #dfe6ee;border-radius:8px;background:#ffffff;padding:0 8px;font-family:'Public Sans',sans-serif;font-size:12.5px;color:#4a5866";

const tab = ref('all');
const search = ref('');
const branchId = ref(0);
const channel = ref('');
const dateFrom = ref('');
const dateTo = ref('');
const page = ref(1);

const rows = ref<any[]>([]);
const total = ref(0);
const lastPage = ref(1);
const counts = ref<Record<string, number>>({});
const salesCount = ref(0);
const totalAmount = ref(0);
const totalPaid = ref(0);
const totalDue = ref(0);
const loading = ref(true);

const hasFilters = computed(() => !!(search.value || branchId.value || channel.value || dateFrom.value || dateTo.value));
const kpis = computed(() => [
  { label: 'Sales', value: money(totalAmount.value), sub: salesCount.value + ' invoices · excludes cancelled', color: '' },
  { label: 'Collected', value: money(totalPaid.value), sub: 'cash and card', color: 'color:#0f6b43;' },
  { label: 'Still due', value: money(totalDue.value), sub: 'owing on account', color: 'color:#b02020;' },
  { label: 'Cancelled', value: String(counts.value.cancelled || 0), sub: 'for this filter', color: '' }
]);

async function load() {
  loading.value = true;
  const params = new URLSearchParams({ page: String(page.value) });
  if (tab.value !== 'all') params.set('tab', tab.value);
  if (search.value.trim()) params.set('search', search.value.trim());
  if (branchId.value) params.set('branch_id', String(branchId.value));
  if (channel.value) params.set('channel', channel.value);
  // The dates are the shop's own days, so send them as exact moments.
  if (dateFrom.value) params.set('from', new Date(dateFrom.value + 'T00:00:00').toISOString());
  if (dateTo.value) params.set('to', new Date(dateTo.value + 'T23:59:59').toISOString());
  try {
    const res = await api.get('/sales?' + params.toString());
    rows.value = res.data;
    total.value = res.total;
    lastPage.value = res.last_page;
    counts.value = res.counts;
    salesCount.value = res.sales_count;
    totalAmount.value = res.total_amount;
    totalPaid.value = res.total_paid;
    totalDue.value = res.total_due;
  } catch (e) { /* the error bar shows why */ }
  loading.value = false;
}

onMounted(() => {
  load();
  loadBranches().catch(() => {});
});

function reload() { page.value = 1; load(); }
function setTab(key: string) { tab.value = key; reload(); }
function setChannel(key: string) { channel.value = key; reload(); }
function goPage(n: number) { if (n < 1 || n > lastPage.value) return; page.value = n; load(); }
function openDetails(id: number) { router.push({ name: 'sell-details', query: { id } }); }

function clearFilters() {
  search.value = '';
  branchId.value = 0;
  channel.value = '';
  dateFrom.value = '';
  dateTo.value = '';
  reload();
}

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
function channelStyle(key: string) {
  return 'height:34px;padding:0 12px;border-radius:8px;display:flex;align-items:center;font-size:12.5px;cursor:pointer;white-space:nowrap;'
    + (channel.value === key ? 'background:var(--c-primary);color:#ffffff;font-weight:600;' : 'border:1px solid #dfe6ee;background:#ffffff;color:#4a5866;font-weight:500;');
}
function pageStyle(on: boolean) {
  return 'height:26px;padding:0 8px;border-radius:6px;border:1px solid #dfe6ee;display:flex;align-items:center;font-size:11.5px;' + (on ? 'color:#4a5866;cursor:pointer' : 'color:#c2cedb');
}
</script>
