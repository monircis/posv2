<template>
  <div style="height:100%;display:flex">
    <!-- The list of reports -->
    <div style="width:232px;flex:none;background:#ffffff;border-right:1px solid #dfe6ee;display:flex;flex-direction:column;min-height:0">
      <div style="padding:16px 14px 10px">
        <div style="font-size:15px;font-weight:700;margin-bottom:10px">Reports</div>
        <input v-model="findReport" placeholder="Find a report" style="width:100%;height:32px;border:1px solid #dfe6ee;border-radius:8px;background:#f7fafc;padding:0 10px;font-family:'Public Sans',sans-serif;font-size:12.5px;outline:none" />
      </div>
      <div style="flex:1;overflow-y:auto;padding:0 8px 16px">
        <div v-for="g in groups" :key="g.name" style="margin-top:10px">
          <div style="font-family:'IBM Plex Mono',monospace;font-size:9.5px;letter-spacing:0.07em;text-transform:uppercase;color:#5d6b7d;padding:4px 8px 6px">{{ g.name }}</div>
          <div v-for="r in g.items" :key="r.id" @click="pick(r)" :style="menuStyle(r.id === report.id)" v-pseudo:hover="'background:#f2f6fa'">
            <div style="flex:1;min-width:0">
              <div style="font-size:13px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">{{ r.name }}</div>
              <div style="font-size:11px;color:#8b98a8;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">{{ r.hint }}</div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div style="flex:1;min-width:0;overflow-y:auto">
      <!-- Title and filters -->
      <div style="padding:18px 24px 16px;background:#ffffff;border-bottom:1px solid #dfe6ee">
        <div style="display:flex;align-items:center;gap:7px;font-size:11.5px;color:#7d8b9c;margin-bottom:8px"><span>Reports</span><span style="color:#c2cedb">/</span><span>{{ report.group }}</span><span style="color:#c2cedb">/</span><span style="color:#0f1c2b;font-weight:500">{{ report.name }}</span></div>
        <div style="display:flex;align-items:flex-start;gap:20px;flex-wrap:wrap">
          <div style="min-width:0">
            <h2 style="margin:0 0 3px;font-size:22px;font-weight:700;letter-spacing:-0.018em">{{ report.name }}</h2>
            <div style="font-size:12.5px;color:#5d6b7d">{{ report.desc }}</div>
          </div>
          <div style="margin-left:auto;display:flex;gap:8px;align-items:center">
            <div @click="print" :style="btnLight">Print</div>
            <div @click="exportCsv" style="height:34px;padding:0 15px;border-radius:8px;background:var(--c-primary);color:#ffffff;display:flex;align-items:center;font-size:12.5px;font-weight:600;cursor:pointer">Export to Excel</div>
          </div>
        </div>

        <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-top:14px">
          <template v-if="!report.pit">
            <div style="display:flex;gap:3px;background:#eef2f6;padding:3px;border-radius:9px;flex-wrap:wrap">
              <div v-for="r in RANGES" :key="r[0]" @click="setRange(r[0])" :style="seg(range === r[0])">{{ r[1] }}</div>
            </div>
            <template v-if="range === 'custom'">
              <input v-model="from" @change="load" type="date" :style="selectStyle" />
              <input v-model="to" @change="load" type="date" :style="selectStyle" />
            </template>
          </template>
          <div v-else style="height:34px;padding:0 12px;border-radius:8px;background:#eef2f6;display:flex;align-items:center;font-size:12.5px;color:#4a5866">As at now</div>

          <!-- Branch: a user who can only see their own branch gets a label -->
          <select v-if="meta.branches.length" v-model.number="branchId" @change="load" :style="selectStyle">
            <option :value="0">All branches</option>
            <option v-for="b in meta.branches" :key="b.id" :value="b.id">{{ b.name }}</option>
          </select>
          <div v-else-if="meta.branch" style="height:34px;padding:0 12px;border-radius:8px;background:#eef2f6;display:flex;align-items:center;font-size:12.5px;font-weight:600;color:#4a5866">{{ meta.branch.name }}</div>

          <select v-if="report.contact" v-model.number="contactId" @change="load" :style="selectStyle">
            <option :value="0">{{ report.contact === 'supplier' ? 'All suppliers' : 'All customers' }}</option>
            <option v-for="c in (report.contact === 'supplier' ? meta.suppliers : meta.customers)" :key="c.id" :value="c.id">{{ c.name }}</option>
          </select>
          <select v-if="report.cat" v-model.number="categoryId" @change="load" :style="selectStyle">
            <option :value="0">All categories</option>
            <option v-for="c in meta.categories" :key="c.id" :value="c.id">{{ c.name }}</option>
          </select>

          <div v-if="canCompare" @click="compare = !compare; load()" style="display:flex;align-items:center;gap:8px;cursor:pointer;margin-left:4px">
            <div :style="'width:32px;height:19px;border-radius:99px;position:relative;flex:none;background:' + (compare ? 'var(--c-primary)' : '#c2cedb')">
              <div :style="'position:absolute;top:2.5px;width:14px;height:14px;border-radius:50%;background:#ffffff;left:' + (compare ? '15.5px' : '2.5px')"></div>
            </div>
            <div style="font-size:12.5px;color:#4a5866">Compare with previous period</div>
          </div>
          <div v-if="hasFilters" @click="reset" style="font-size:12.5px;font-weight:600;color:var(--c-primary);cursor:pointer;margin-left:auto">Reset filters</div>
        </div>
      </div>

      <div style="padding:18px 24px 32px;display:grid;gap:14px">
        <div v-if="error" style="background:#fdeaea;color:#b02020;border-radius:10px;padding:10px 14px;font-size:12.5px">{{ error }}</div>

        <!-- The four numbers -->
        <div style="display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px">
          <div v-for="(k, i) in data.kpis" :key="k.label" style="background:#ffffff;border:1px solid #dfe6ee;border-radius:12px;padding:14px 16px;min-width:0">
            <div style="font-size:11.5px;color:#7d8b9c">{{ k.label }}</div>
            <div :style="'font-family:\'IBM Plex Mono\',monospace;font-size:21px;font-weight:600;letter-spacing:-0.02em;margin-top:3px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;' + (loading ? 'opacity:.4' : '')">{{ fmt(k.value, k.type) }}</div>
            <div :style="'font-size:11.5px;margin-top:3px;min-height:15px;' + changeOf(k, i).style">{{ changeOf(k, i).text }}</div>
          </div>
        </div>

        <!-- Chart -->
        <div style="background:#ffffff;border:1px solid #dfe6ee;border-radius:12px;padding:16px 18px">
          <div style="display:flex;align-items:center;gap:14px;margin-bottom:14px">
            <div style="font-size:13px;font-weight:700;flex:1">{{ chartTitle }}</div>
            <div v-for="l in legend" :key="l[0]" style="display:flex;align-items:center;gap:6px;font-size:11.5px;color:#5d6b7d">
              <div :style="'width:10px;height:10px;border-radius:3px;background:' + l[1]"></div>{{ l[0] }}
            </div>
          </div>
          <div v-if="data.chart.type === 'time'" style="display:flex;gap:10px">
            <div style="width:62px;flex:none;height:190px;display:flex;flex-direction:column;justify-content:space-between;font-family:'IBM Plex Mono',monospace;font-size:10px;color:#9aa7b6;text-align:right">
              <div>{{ short(chartMax) }}</div><div>{{ short(chartMax / 2) }}</div><div>0</div>
            </div>
            <div style="flex:1;min-width:0">
              <div style="height:190px;display:flex;align-items:flex-end;gap:3px;border-bottom:1px solid #dfe6ee">
                <div v-for="(v, i) in seriesA" :key="i" :title="barTip(i)" style="flex:1;min-width:0;height:100%;display:flex;align-items:flex-end;justify-content:center;gap:2px">
                  <div v-if="seriesB" :style="bar(seriesB[i], secondColor)"></div>
                  <div :style="bar(v, v < 0 ? '#d93a3a' : 'var(--c-primary)')"></div>
                </div>
              </div>
              <div style="display:flex;gap:3px;margin-top:6px">
                <div v-for="(l, i) in data.chart.labels" :key="i" style="flex:1;min-width:0;text-align:center;font-family:'IBM Plex Mono',monospace;font-size:9.5px;color:#8b98a8;white-space:nowrap">{{ i % labelEvery === 0 ? l : '' }}</div>
              </div>
            </div>
          </div>
          <div v-else style="display:grid;gap:9px">
            <div v-if="!rankItems.length" style="font-size:12.5px;color:#8b98a8">Nothing in this period.</div>
            <div v-for="(r, i) in rankItems" :key="i" style="display:grid;grid-template-columns:190px minmax(0,1fr) 110px;gap:12px;align-items:center">
              <div style="font-size:12.5px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">{{ r[0] }}</div>
              <div style="height:14px;border-radius:4px;background:#f2f5f9;overflow:hidden">
                <div :style="'height:100%;border-radius:4px;width:' + Math.max(1, r[1] / rankMax * 100) + '%;background:' + (i === 0 ? 'var(--c-primary)' : '#7fb0e6')"></div>
              </div>
              <div style="font-family:'IBM Plex Mono',monospace;font-size:12px;text-align:right;color:#4a5866">{{ money(r[1]) }}</div>
            </div>
          </div>
        </div>

        <!-- Table -->
        <div style="background:#ffffff;border:1px solid #dfe6ee;border-radius:12px;overflow:hidden">
          <div style="display:flex;align-items:center;gap:10px;padding:11px 16px;border-bottom:1px solid #eaeff5">
            <div style="font-size:13px;font-weight:700;flex:1">{{ data.table.title }} · {{ rows.length }} {{ rows.length === 1 ? 'row' : 'rows' }}</div>
            <input v-model="findRow" @input="page = 0" placeholder="Search rows" style="width:220px;height:30px;border:1px solid #dfe6ee;border-radius:7px;padding:0 10px;font-family:'Public Sans',sans-serif;font-size:12.5px;outline:none" />
          </div>
          <div :style="grid + 'background:#f6f9fc;border-bottom:1px solid #dfe6ee;padding-top:10px;padding-bottom:10px'">
            <div v-for="(c, i) in data.table.cols" :key="i" @click="sortBy(i)" :style="headStyle(c)">{{ c.h }}<span style="color:var(--c-primary)">{{ sortCol === i ? (sortDir > 0 ? ' ↑' : ' ↓') : '' }}</span></div>
          </div>
          <div v-for="(r, ri) in shown" :key="ri" :style="grid + 'padding-top:9px;padding-bottom:9px;border-bottom:1px solid #f0f4f8;' + (r.bold === 2 ? 'background:#f6f9fc;border-top:1.5px solid #0f1c2b;' : '')">
            <div v-for="(v, i) in r.c" :key="i" :style="cellStyle(data.table.cols[i], i, r.bold, v)">
              <span v-if="data.table.cols[i].t === 'badge'" :style="badgeStyle(v)">{{ v[0] }}</span>
              <span v-else :style="typeof v === 'string' && v.startsWith('   ') ? 'padding-left:18px' : ''">{{ fmt(v, data.table.cols[i].t) }}</span>
            </div>
          </div>
          <div v-if="!rows.length" style="padding:36px 20px;text-align:center;font-size:12.5px;color:#7d8b9c">{{ loading ? 'Loading…' : 'Nothing in this period. Change the dates or filters.' }}</div>
          <div v-if="showTotals" :style="grid + 'padding-top:11px;padding-bottom:11px;background:#f6f9fc;border-top:1px solid #dfe6ee'">
            <div v-for="(c, i) in data.table.cols" :key="i" :style="cellStyle(c, i, 1, total(i))">{{ i === 0 ? 'Total' : c.sum ? fmt(total(i), c.t) : '' }}</div>
          </div>
          <div style="display:flex;align-items:center;gap:8px;padding:10px 16px;border-top:1px solid #eaeff5">
            <div style="font-size:12px;color:#7d8b9c;flex:1">{{ rows.length ? 'Showing ' + (page * PAGE + 1) + '–' + Math.min(rows.length, page * PAGE + PAGE) + ' of ' + rows.length + (data.table.no_sort ? '' : ' · click a column to sort') : '' }}</div>
            <div @click="page > 0 && page--" :style="pageBtn(page > 0)">‹ Prev</div>
            <div @click="page < pages - 1 && page++" :style="pageBtn(page < pages - 1)">Next ›</div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
// Reports. Filters: GET /api/reports. Each report: GET /api/reports/{id}?from=&to=&branch_id=&contact_id=&category_id=
// Every report answers with kpis, chart and table (see pos/app/Http/Controllers/Api/Reports/ReportController.php).
import { computed, onMounted, ref } from 'vue';
import { useErpPage } from '../erp/useErpPage';
import { api } from '../api/http.js';

useErpPage('reports', 'rp_nav'); // the sidebar

// The reports: id is the API path. contact = which list to filter by, cat = category filter, pit = "as at now" (no dates).
const REPORTS = [
  { id: 'sales', group: 'Sales', name: 'Sales', hint: 'Net sales by period', desc: 'Invoices, discounts, GST, paid and due for every sale (drafts, quotes and cancelled sales are left out)', contact: 'customer' },
  { id: 'customers', group: 'Sales', name: 'Customers', hint: 'Sales and balances per customer', desc: 'Who buys, how much, and what they owe', contact: 'customer' },
  { id: 'products', group: 'Sales', name: 'Product sales', hint: 'Best sellers and margin', desc: 'Quantity, revenue and margin per product (cost is today’s cost price)', cat: true },
  { id: 'payments', group: 'Sales', name: 'Payments', hint: 'Money in and out by account', desc: 'Sale payments received, and supplier payments and expenses paid, per payment account' },
  { id: 'purchases', group: 'Purchasing', name: 'Purchases', hint: 'Purchase orders by period', desc: 'What was ordered, received and returned to suppliers', contact: 'supplier' },
  { id: 'suppliers', group: 'Purchasing', name: 'Suppliers', hint: 'Spend and balances per supplier', desc: 'Spend, paid, owed and returns for each supplier', contact: 'supplier' },
  { id: 'stock', group: 'Stock', name: 'Stock on hand', hint: 'Quantity and value now', desc: 'Quantity now, value at cost and at selling price, and Low / Out of stock', cat: true, pit: true },
  { id: 'manufacturing', group: 'Stock', name: 'Manufacturing', hint: 'Output, cost and yield', desc: 'Production orders with material cost and yield' },
  { id: 'profit-loss', group: 'Finance', name: 'Profit & loss', hint: 'Income less costs', desc: 'Net sales (without GST) less cost of goods and expenses' },
  { id: 'expenses', group: 'Finance', name: 'Expenses', hint: 'Spend by category', desc: 'Expenses by category with the GST you can claim' },
  { id: 'gst', group: 'Finance', name: 'GST / BAS', hint: 'GST collected and paid', desc: 'Figures for your Business Activity Statement' },
];
const RANGES = [['today', 'Today'], ['week', 'This week'], ['month', 'This month'], ['last', 'Last month'], ['quarter', 'This quarter'], ['custom', 'Dates…']];
const PAGE = 12;
const EMPTY = { kpis: [], chart: { type: 'rank', title: '', items: [] }, table: { title: '', cols: [], rows: [] } };

const meta = ref<any>({ today: '', branch: null, branches: [], customers: [], suppliers: [], categories: [] });
const report = ref<any>(REPORTS[0]);
const range = ref('month');
const from = ref('');
const to = ref('');
const branchId = ref(0);
const contactId = ref(0);
const categoryId = ref(0);
const compare = ref(true);
const data = ref<any>(EMPTY);
const prev = ref<any>(null);
const loading = ref(false);
const error = ref('');
const findReport = ref('');
const findRow = ref('');
const sortCol = ref<number | null>(null);
const sortDir = ref(-1);
const page = ref(0);

const btnLight = 'height:34px;padding:0 13px;border-radius:8px;border:1px solid #dfe6ee;display:flex;align-items:center;font-size:12.5px;color:#4a5866;cursor:pointer;background:#ffffff';
const selectStyle = "height:34px;border:1px solid #dfe6ee;border-radius:8px;background:#ffffff;padding:0 10px;font-family:'Public Sans',sans-serif;font-size:12.5px;color:#4a5866;outline:none;cursor:pointer";
const secondColor = computed(() => (data.value.chart.series && data.value.chart.series.length > 1 ? '#d98c1a' : '#c9d6e4'));

const groups = computed(() => ['Sales', 'Purchasing', 'Stock', 'Finance'].map(g => ({
  name: g,
  items: REPORTS.filter(r => r.group === g && (!findReport.value || (r.name + ' ' + r.hint).toLowerCase().includes(findReport.value.toLowerCase()))),
})).filter(g => g.items.length));

// --- dates ---
function iso(d: Date) {
  return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
}

// The from/to of a range button, counted from the shop's today.
function rangeDates(key: string): [string, string] {
  const t = new Date((meta.value.today || iso(new Date())) + 'T12:00:00');
  const y = t.getFullYear(), m = t.getMonth();
  if (key === 'today') return [iso(t), iso(t)];
  if (key === 'week') { const mon = new Date(t); mon.setDate(t.getDate() - ((t.getDay() + 6) % 7)); return [iso(mon), iso(t)]; }
  if (key === 'last') return [iso(new Date(y, m - 1, 1)), iso(new Date(y, m, 0))];
  if (key === 'quarter') return [iso(new Date(y, Math.floor(m / 3) * 3, 1)), iso(t)];
  return [iso(new Date(y, m, 1)), iso(t)];
}

// The same number of days just before from..to, for "Compare with previous period".
function previousDates(): [string, string] {
  const f = new Date(from.value + 'T12:00:00'), t = new Date(to.value + 'T12:00:00');
  const days = Math.round((t.getTime() - f.getTime()) / 86400000) + 1;
  const pt = new Date(f); pt.setDate(f.getDate() - 1);
  const pf = new Date(pt); pf.setDate(pt.getDate() - days + 1);
  return [iso(pf), iso(pt)];
}

function setRange(key: string) {
  range.value = key;
  if (key !== 'custom') [from.value, to.value] = rangeDates(key);
  load();
}

// --- loading ---
function url(f: string, t: string) {
  const p = new URLSearchParams();
  if (!report.value.pit) { p.set('from', f); p.set('to', t); }
  if (branchId.value) p.set('branch_id', String(branchId.value));
  if (report.value.contact && contactId.value) p.set('contact_id', String(contactId.value));
  if (report.value.cat && categoryId.value) p.set('category_id', String(categoryId.value));
  return '/reports/' + report.value.id + '?' + p.toString();
}

const canCompare = computed(() => !report.value.pit && report.value.id !== 'gst');

// Each load gets a number. When filters change quickly, an older answer that arrives late is ignored.
let lastLoad = 0;
const loadedDays = ref(0);

async function load() {
  if (from.value > to.value) { error.value = 'The "from" date is after the "to" date.'; return; }
  const mine = ++lastLoad;
  const days = daysShown();
  loading.value = true;
  error.value = '';
  page.value = 0;
  try {
    const [cur, before] = await Promise.all([
      api.get(url(from.value, to.value)),
      canCompare.value && compare.value ? api.get(url(...previousDates())) : Promise.resolve(null),
    ]);
    if (mine !== lastLoad) return;
    data.value = cur;
    prev.value = before;
    loadedDays.value = days;
  } catch (e: any) {
    if (mine !== lastLoad) return;
    error.value = e.message || 'Could not load the report.';
  }
  loading.value = false;
}

function pick(r: any) {
  report.value = r;
  contactId.value = 0;
  categoryId.value = 0;
  sortCol.value = null;
  findRow.value = '';
  data.value = EMPTY;
  load();
}

const hasFilters = computed(() => range.value !== 'month' || branchId.value || contactId.value || categoryId.value);
function reset() {
  branchId.value = 0;
  contactId.value = 0;
  categoryId.value = 0;
  setRange('month');
}

// --- formatting ---
function money(n: number) {
  const v = Math.abs(n) < 0.005 ? 0 : n;
  return (v < 0 ? '−' : '') + Math.abs(v).toLocaleString('en-AU', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function fmt(v: any, t: string) {
  if (v === null || v === undefined || v === '') return t === 'text' ? '' : '—';
  if (t === 'money') return money(v);
  if (t === 'int') return Math.round(v).toLocaleString('en-AU');
  if (t === 'pct') return (v < 0 ? '−' : '') + Math.abs(v).toFixed(1) + '%';
  if (t === 'qty') return (Math.round(v * 1000) / 1000).toLocaleString('en-AU');
  if (t === 'badge') return v[0];
  return String(v);
}

function short(n: number) {
  return n >= 10000 ? (n / 1000).toFixed(0) + 'k' : n >= 1000 ? (n / 1000).toFixed(1) + 'k' : String(Math.round(n));
}

// "▲ 12.4% vs previous" under a number; green when it moved the good way.
function changeOf(k: any, i: number) {
  const grey = 'color:#8b98a8;';
  if (!prev.value || k.type === 'text' || !prev.value.kpis[i]) {
    return { text: i === 0 ? (report.value.pit ? 'As at now' : fmtDate(from.value) + ' – ' + fmtDate(to.value)) : '', style: grey };
  }
  const p = prev.value.kpis[i].value;
  if (k.type === 'pct') {
    const diff = k.value - p;
    return { text: (diff >= 0 ? '+' : '−') + Math.abs(diff).toFixed(1) + ' pts vs previous', style: (diff >= 0) === k.good ? 'color:#0f6b43;' : 'color:#b02020;' };
  }
  if (Math.abs(p) < 0.001) return { text: 'nothing in the previous period', style: grey };
  const pc = (k.value - p) / Math.abs(p) * 100;
  return { text: (pc >= 0 ? '▲ ' : '▼ ') + Math.abs(pc).toFixed(1) + '% vs previous', style: Math.abs(pc) < 0.05 ? grey : (pc >= 0) === k.good ? 'color:#0f6b43;' : 'color:#b02020;' };
}

function fmtDate(d: string) {
  return d ? d.split('-').reverse().join('/') : '';
}

// --- chart ---
const seriesA = computed<number[]>(() => (data.value.chart.series ? data.value.chart.series[0].values : []));
// Second bars: the report's second series (GST paid), or the previous period.
const seriesB = computed<number[] | null>(() => {
  const s = data.value.chart.series;
  if (s && s.length > 1) return s[1].values;
  if (prev.value && prev.value.chart.series && prev.value.chart.series[0].values.length === seriesA.value.length) return prev.value.chart.series[0].values;
  return null;
});
const chartMax = computed(() => Math.max(1, ...seriesA.value.map(v => Math.max(0, v)), ...(seriesB.value || []).map(v => Math.max(0, v))));
const labelEvery = computed(() => Math.max(1, Math.ceil(seriesA.value.length / 10)));
const rankItems = computed(() => (data.value.chart.items || []).slice().sort((a: any, b: any) => b[1] - a[1]).slice(0, 8));
const rankMax = computed(() => Math.max(1, ...rankItems.value.map((r: any) => r[1])));
const chartTitle = computed(() => {
  const c = data.value.chart;
  if (c.type === 'time') return (c.series ? c.series[0].name : '') + (loadedDays.value > 31 ? ' by week' : ' by day');
  return c.title + ((c.items || []).length > 8 ? ' · top 8' : '');
});
const legend = computed(() => {
  const c = data.value.chart;
  if (c.type !== 'time' || !c.series) return [];
  const list = [[c.series[0].name, 'var(--c-primary)']];
  if (c.series.length > 1) list.push([c.series[1].name, '#d98c1a']);
  else if (seriesB.value) list.push(['Previous period', '#c9d6e4']);
  return list;
});

function daysShown() {
  return Math.round((new Date(to.value).getTime() - new Date(from.value).getTime()) / 86400000) + 1;
}

function bar(v: number, color: string) {
  const w = seriesA.value.length > 20 ? 'max(3px,38%)' : '34%';
  return 'width:' + w + ';border-radius:3px 3px 0 0;height:' + Math.max(0, (v || 0) / chartMax.value * 100) + '%;background:' + color;
}

function barTip(i: number) {
  const c = data.value.chart;
  let tip = c.labels[i] + ': ' + money(seriesA.value[i]);
  if (seriesB.value) tip += ' · ' + (c.series.length > 1 ? c.series[1].name : 'previous') + ' ' + money(seriesB.value[i] || 0);
  return tip;
}

// --- table ---
const isNum = (t: string) => t === 'money' || t === 'int' || t === 'pct' || t === 'qty';
const grid = computed(() => 'display:grid;grid-template-columns:' + data.value.table.cols.map((c: any) => c.w).join(' ') + ';gap:12px;align-items:center;padding:0 16px;');

const rows = computed(() => {
  const cols = data.value.table.cols;
  const q = findRow.value.trim().toLowerCase();
  let list = data.value.table.rows.filter((r: any) => !q || r.c.some((v: any, i: number) => cols[i].t === 'text' && String(v).toLowerCase().includes(q)));
  if (sortCol.value !== null && !data.value.table.no_sort) {
    const k = sortCol.value, t = cols[k].t;
    list = list.slice().sort((a: any, b: any) => {
      const x = t === 'badge' ? a.c[k][0] : a.c[k], y = t === 'badge' ? b.c[k][0] : b.c[k];
      return (typeof x === 'string' ? x.localeCompare(y) : (x || 0) - (y || 0)) * sortDir.value;
    });
  }
  return list;
});
const pages = computed(() => Math.max(1, Math.ceil(rows.value.length / PAGE)));
const shown = computed(() => rows.value.slice(page.value * PAGE, page.value * PAGE + PAGE));
const showTotals = computed(() => !data.value.table.no_totals && data.value.table.cols.some((c: any) => c.sum) && rows.value.length > 0);

function total(i: number) {
  return rows.value.reduce((t: number, r: any) => t + (Number(r.c[i]) || 0), 0);
}

function sortBy(i: number) {
  if (data.value.table.no_sort) return;
  sortDir.value = sortCol.value === i ? -sortDir.value : (data.value.table.cols[i].t === 'text' ? 1 : -1);
  sortCol.value = i;
  page.value = 0;
}

function headStyle(c: any) {
  return "font-family:'IBM Plex Mono',monospace;font-size:9.5px;letter-spacing:0.07em;text-transform:uppercase;color:#5d6b7d;white-space:nowrap;user-select:none;"
    + (isNum(c.t) ? 'text-align:right;' : '') + (data.value.table.no_sort ? '' : 'cursor:pointer;');
}

function cellStyle(c: any, i: number, bold: number | undefined, v: any) {
  return 'min-width:0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;'
    + (isNum(c.t) ? "font-family:'IBM Plex Mono',monospace;font-size:12.5px;text-align:right;" : 'font-size:13px;')
    + (i === 0 || bold ? 'font-weight:' + (bold ? 700 : 500) + ';' : 'color:#4a5866;')
    + (isNum(c.t) && typeof v === 'number' && v < -0.004 ? 'color:#b02020;' : '')
    + (bold === 2 ? 'font-size:14px;' : '');
}

function badgeStyle(v: any) {
  const tones: Record<string, string[]> = { success: ['#e7f6ee', '#0f6b43'], warn: ['#fdf1dd', '#8a5300'], error: ['#fdeaea', '#b02020'], info: ['var(--c-primary-soft)', 'var(--c-primary-text)'], neutral: ['#eef2f6', '#5d6b7d'] };
  const t = tones[v[1]] || tones.neutral;
  return 'background:' + t[0] + ';color:' + t[1] + ';border-radius:5px;padding:3px 8px;font-size:11.5px;font-weight:600;white-space:nowrap';
}

function pageBtn(on: boolean) {
  return 'height:28px;padding:0 10px;border-radius:7px;border:1px solid #dfe6ee;display:flex;align-items:center;font-size:12px;font-weight:600;' + (on ? 'color:#4a5866;cursor:pointer;' : 'color:#c2cedb;');
}

function seg(on: boolean) {
  return 'height:30px;padding:0 12px;border-radius:7px;display:grid;place-items:center;font-size:12.5px;cursor:pointer;white-space:nowrap;'
    + (on ? 'background:#ffffff;color:#0f1c2b;font-weight:700;box-shadow:0 1px 3px rgba(15,28,43,.14);' : 'color:#5d6b7d;font-weight:500;');
}

function menuStyle(on: boolean) {
  return 'display:flex;align-items:center;padding:7px 9px;border-radius:8px;cursor:pointer;margin-bottom:1px;'
    + (on ? 'background:var(--c-primary-soft);box-shadow:inset 2px 0 0 var(--c-primary);color:var(--c-primary-text);font-weight:600;' : 'color:#0f1c2b;');
}

// --- print and export ---
function branchLabel() {
  if (meta.value.branch) return meta.value.branch.name;
  const b = meta.value.branches.find((x: any) => x.id === branchId.value);
  return b ? b.name : 'All branches';
}

// The table as a CSV file (opens in Excel), with the filters on the first lines.
function exportCsv() {
  const cols = data.value.table.cols;
  const cell = (v: any) => '"' + String(v === null || v === undefined ? '' : v).replace(/"/g, '""') + '"';
  const raw = (v: any, t: string) => (t === 'badge' ? v[0] : typeof v === 'number' ? Math.round(v * 100) / 100 : String(v || '').trim());
  const lines = [
    [report.value.name], [report.value.pit ? 'As at ' + fmtDate(meta.value.today) : fmtDate(from.value) + ' to ' + fmtDate(to.value)], [branchLabel()], [],
    cols.map((c: any) => c.h),
  ].concat(rows.value.map((r: any) => r.c.map((v: any, i: number) => raw(v, cols[i].t))));
  if (showTotals.value) lines.push(cols.map((c: any, i: number) => (i === 0 ? 'Total' : c.sum ? Math.round(total(i) * 100) / 100 : '')));
  const blob = new Blob(['﻿' + lines.map(l => l.map(cell).join(',')).join('\r\n')], { type: 'text/csv' });
  const a = document.createElement('a');
  a.href = URL.createObjectURL(blob);
  a.download = report.value.name.replace(/[^\w]+/g, '-') + '-' + (report.value.pit ? meta.value.today : from.value + '-to-' + to.value) + '.csv';
  a.click();
  URL.revokeObjectURL(a.href);
}

// Prints the report: title, filters, numbers and the whole table (all pages).
function print() {
  const cols = data.value.table.cols;
  const esc = (s: any) => String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;');
  const head = cols.map((c: any) => '<th style="text-align:' + (isNum(c.t) ? 'right' : 'left') + '">' + esc(c.h) + '</th>').join('');
  const body = rows.value.map((r: any) => '<tr' + (r.bold ? ' style="font-weight:700"' : '') + '>' + r.c.map((v: any, i: number) => '<td style="text-align:' + (isNum(cols[i].t) ? 'right' : 'left') + '">' + esc(fmt(v, cols[i].t)) + '</td>').join('') + '</tr>').join('');
  const foot = showTotals.value ? '<tr style="font-weight:700">' + cols.map((c: any, i: number) => '<td style="text-align:' + (isNum(c.t) ? 'right' : 'left') + '">' + (i === 0 ? 'Total' : c.sum ? esc(fmt(total(i), c.t)) : '') + '</td>').join('') + '</tr>' : '';
  const kpis = data.value.kpis.map((k: any) => '<div><div style="color:#666;font-size:11px">' + esc(k.label) + '</div><b>' + esc(fmt(k.value, k.type)) + '</b></div>').join('');
  const html = '<!doctype html><html><head><meta charset="utf-8"><title>' + esc(report.value.name) + '</title><style>body{font-family:Arial,sans-serif;font-size:12px;margin:20px}table{width:100%;border-collapse:collapse}th,td{padding:5px 6px;border-bottom:1px solid #ddd}th{font-size:10px;text-transform:uppercase;color:#555}.k{display:flex;gap:30px;margin:12px 0 16px}</style></head><body>'
    + '<h2 style="margin:0">' + esc(report.value.name) + '</h2><div style="color:#555">' + esc(report.value.pit ? 'As at ' + fmtDate(meta.value.today) : fmtDate(from.value) + ' to ' + fmtDate(to.value)) + ' · ' + esc(branchLabel()) + '</div>'
    + '<div class="k">' + kpis + '</div><table><thead><tr>' + head + '</tr></thead><tbody>' + body + foot + '</tbody></table></body></html>';
  const frame = document.createElement('iframe');
  frame.setAttribute('style', 'position:fixed;left:-10000px;top:0;width:210mm;height:297mm;border:0');
  document.body.appendChild(frame);
  const doc = frame.contentWindow!.document;
  doc.open(); doc.write(html); doc.close();
  setTimeout(() => { frame.contentWindow!.focus(); frame.contentWindow!.print(); setTimeout(() => frame.remove(), 60000); }, 300);
}

onMounted(async () => {
  try {
    meta.value = await api.get('/reports');
  } catch (e: any) {
    error.value = e.message || 'Could not load the report filters.';
  }
  [from.value, to.value] = rangeDates('month');
  load();
});
</script>
