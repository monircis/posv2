<template>
  <div>
    <div style="padding:20px 24px 0;background:#ffffff;border-bottom:1px solid #dfe6ee">
      <div style="display:flex;align-items:center;gap:7px;font-size:11.5px;color:#7d8b9c;margin-bottom:8px"><span>Stock</span><span style="color:#c2cedb">/</span><span style="color:#0f1c2b;font-weight:500">Stock transfers</span></div>
      <div style="display:flex;align-items:flex-start;gap:20px;flex-wrap:wrap">
        <div>
          <h2 style="margin:0 0 3px;font-size:22px;font-weight:700;letter-spacing:-0.018em">Stock transfers</h2>
          <div style="font-size:12.5px;color:#5d6b7d">{{ counts.all || 0 }} transfers{{ userBranchId ? ' in or out of your branch' : ' between branches' }}</div>
        </div>
        <div style="margin-left:auto;display:flex;gap:8px;align-items:center">
          <RouterLink v-if="can('transfers.create')" :to="{ name: 'transfers-new' }" style="height:34px;padding:0 15px;border-radius:8px;background:var(--c-primary);color:#ffffff;display:flex;align-items:center;font-size:12.5px;font-weight:600;cursor:pointer;text-decoration:none" v-pseudo:hover="'background:var(--c-primary-hover);color:#ffffff'">+ New transfer</RouterLink>
        </div>
      </div>
      <div style="display:flex;gap:2px;margin-top:16px">
        <div v-for="t in TABS" :key="t.key" @click="setTab(t.key)" :style="tabStyle(t.key)">{{ t.name }}<span :style="countStyle(t.key)">{{ counts[t.key] || 0 }}</span></div>
      </div>
    </div>
    <div style="padding:20px 24px 32px">
      <div v-if="transit.count > 0 && tab !== 'transit'" style="display:flex;align-items:center;gap:12px;background:var(--c-primary-soft);border:1px solid #cfe0f5;border-radius:11px;padding:11px 15px;margin-bottom:14px">
        <div style="width:8px;height:8px;border-radius:50%;background:var(--c-primary);flex:none"></div>
        <div style="font-size:12.5px;color:var(--c-primary-text);flex:1">{{ transit.count }} {{ transit.count === 1 ? 'transfer is' : 'transfers are' }} on the road, worth {{ money(transit.value) }} at cost. The receiving branch confirms counts on arrival.</div>
        <div @click="setTab('transit')" style="font-size:12.5px;font-weight:600;color:var(--c-primary);cursor:pointer">Show in transit</div>
      </div>
      <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-bottom:14px">
        <div style="position:relative;width:240px"><div style="position:absolute;left:10px;top:50%;transform:translateY(-50%);color:#9aa7b6;font-size:12px">⚲</div> <input :value="search" @input="onSearch" placeholder="Reference or product" style="width:100%;height:34px;border:1px solid #dfe6ee;border-radius:8px;background:#ffffff;padding:0 10px 0 29px;font-family:'Public Sans',sans-serif;font-size:12.5px;outline:none" v-pseudo:focus="'border-color:var(--c-primary)'" /></div>
        <select v-model.number="fromId" @change="reload" :style="filterStyle">
          <option :value="0">From: any branch</option>
          <option v-for="b in branches" :key="b.id" :value="b.id">From: {{ b.name }}</option>
        </select>
        <select v-model.number="toId" @change="reload" :style="filterStyle">
          <option :value="0">To: any branch</option>
          <option v-for="b in branches" :key="b.id" :value="b.id">To: {{ b.name }}</option>
        </select>
        <input v-model="dateFrom" @change="reload" type="date" title="From date" :style="filterStyle" />
        <input v-model="dateTo" @change="reload" type="date" title="To date" :style="filterStyle" />
        <div v-if="hasFilters" @click="clearFilters" style="font-size:12.5px;font-weight:600;color:var(--c-primary);cursor:pointer;padding:0 6px">Clear</div>
      </div>
      <div style="background:#ffffff;border:1px solid #dfe6ee;border-radius:12px;overflow:hidden">
        <div style="display:grid;grid-template-columns:1fr 2.2fr 1.6fr 1fr 1.2fr 0.9fr;gap:12px;padding:10px 16px;background:#f6f9fc;border-bottom:1px solid #dfe6ee;font-family:'IBM Plex Mono',monospace;font-size:9.5px;letter-spacing:0.07em;text-transform:uppercase;color:#5d6b7d;align-items:center">
          <div>Reference</div>
          <div>Route</div>
          <div>Items</div>
          <div style="text-align:right">Value at cost</div>
          <div>Status</div>
          <div>By</div>
        </div>
        <div v-for="r in rows" :key="r.id" @click="openDetails(r.id)" style="display:grid;grid-template-columns:1fr 2.2fr 1.6fr 1fr 1.2fr 0.9fr;gap:12px;padding:11px 16px;border-bottom:1px solid #f0f4f8;align-items:center;cursor:pointer" v-pseudo:hover="'background:#f9fbfd'">
          <div>
            <div style="font-family:'IBM Plex Mono',monospace;font-size:12.5px;font-weight:500;color:var(--c-primary)">{{ r.ref }}</div>
            <div style="font-size:10.5px;color:#9aa7b6;margin-top:1px">{{ dmy(r.date) }}</div>
          </div>
          <div style="display:flex;align-items:center;gap:8px;min-width:0">
            <div style="font-size:13px;font-weight:500;white-space:nowrap">{{ r.from_branch.name }}</div>
            <div style="color:#9aa7b6;font-size:12px;flex:none">→</div>
            <div style="font-size:13px;font-weight:500;white-space:nowrap">{{ r.to_branch.name }}</div>
          </div>
          <div style="min-width:0">
            <div style="font-size:12.5px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">{{ itemNames(r) }}</div>
            <div style="font-size:11px;color:#8b98a8">{{ r.items.length }} {{ r.items.length === 1 ? 'line' : 'lines' }}</div>
          </div>
          <div style="font-family:'IBM Plex Mono',monospace;font-size:12.5px;text-align:right;font-weight:500">{{ money(r.stock_value + r.shipping_cost) }}</div>
          <div><span :style="badge(r.status)">{{ STATUS_LABELS[r.status] }}</span></div>
          <div style="font-size:12.5px;color:#4a5866">{{ r.created_by }}</div>
        </div>
        <div v-if="!rows.length" style="padding:40px 20px;text-align:center">
          <div style="font-size:13.5px;font-weight:600;margin-bottom:4px">{{ loading ? 'Loading…' : 'No transfers match' }}</div>
          <div v-if="!loading" style="font-size:12.5px;color:#7d8b9c">Try another status or clear the filters.</div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 2.2fr 1.6fr 1fr 1.2fr 0.9fr;gap:12px;padding:12px 16px;background:#f6f9fc;align-items:center">
          <div style="font-size:12px;color:#7d8b9c;grid-column:span 3">Showing {{ rows.length }} of {{ total }} · value for current filter</div>
          <div style="font-family:'IBM Plex Mono',monospace;font-size:13px;text-align:right;font-weight:700">{{ money(value) }}</div>
          <div style="grid-column:span 2;display:flex;justify-content:flex-end;gap:4px">
            <div @click="goPage(page - 1)" :style="pageStyle(page > 1)">Prev</div>
            <div @click="goPage(page + 1)" :style="pageStyle(page < lastPage)">Next</div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
// Stock transfers list. API: GET /api/stock-transfers?tab=&search=&from_branch_id=&to_branch_id=&from=&to=&page=
import { computed, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import { api } from '../../api/http.js';
import { can } from '../../api/auth.js';
import { branches, loadBranches } from '../../api/branches.js';
import { money, dmy, badge, userBranchId, STATUS_LABELS } from '../../api/transfers.js';

const router = useRouter();

const TABS = [
  { key: 'all', name: 'All' }, { key: 'draft', name: 'Draft' }, { key: 'transit', name: 'In transit' },
  { key: 'received', name: 'Received' }, { key: 'cancelled', name: 'Cancelled' }
];
const filterStyle = "height:34px;border:1px solid #dfe6ee;border-radius:8px;background:#ffffff;padding:0 8px;font-family:'Public Sans',sans-serif;font-size:12.5px;color:#4a5866";

const tab = ref('all');
const search = ref('');
const fromId = ref(0);
const toId = ref(0);
const dateFrom = ref('');
const dateTo = ref('');
const page = ref(1);

const rows = ref<any[]>([]);
const total = ref(0);
const lastPage = ref(1);
const counts = ref<Record<string, number>>({});
const value = ref(0);
const transit = ref({ count: 0, value: 0 });
const loading = ref(true);

const hasFilters = computed(() => !!(search.value || fromId.value || toId.value || dateFrom.value || dateTo.value));

async function load() {
  loading.value = true;
  const params = new URLSearchParams({ page: String(page.value) });
  if (tab.value !== 'all') params.set('tab', tab.value);
  if (search.value.trim()) params.set('search', search.value.trim());
  if (fromId.value) params.set('from_branch_id', String(fromId.value));
  if (toId.value) params.set('to_branch_id', String(toId.value));
  if (dateFrom.value) params.set('from', dateFrom.value);
  if (dateTo.value) params.set('to', dateTo.value);
  try {
    const res = await api.get('/stock-transfers?' + params.toString());
    rows.value = res.data;
    total.value = res.total;
    lastPage.value = res.last_page;
    counts.value = res.counts;
    value.value = res.value;
    transit.value = res.transit;
  } catch (e) { /* the error bar shows why */ }
  loading.value = false;
}

onMounted(() => {
  load();
  loadBranches().catch(() => {});
});

function reload() { page.value = 1; load(); }
function setTab(key: string) { tab.value = key; reload(); }
function goPage(n: number) { if (n < 1 || n > lastPage.value) return; page.value = n; load(); }
function openDetails(id: number) { router.push({ name: 'transfers-details', query: { id } }); }

function clearFilters() {
  search.value = '';
  fromId.value = 0;
  toId.value = 0;
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

function itemNames(row: any) {
  return row.items.map((i: any) => i.name).join(', ');
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
