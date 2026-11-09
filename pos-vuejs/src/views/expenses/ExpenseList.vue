<template>
  <div>
    <div style="padding:20px 24px 0;background:#ffffff;border-bottom:1px solid #dfe6ee">
      <div style="display:flex;align-items:center;gap:7px;font-size:11.5px;color:#7d8b9c;margin-bottom:8px"><span>Expenses</span><span style="color:#c2cedb">/</span><span style="color:#0f1c2b;font-weight:500">All expenses</span></div>
      <div style="display:flex;align-items:flex-start;gap:20px;flex-wrap:wrap">
        <div>
          <h2 style="margin:0 0 3px;font-size:22px;font-weight:700;letter-spacing:-0.018em">Expenses</h2>
          <div style="font-size:12.5px;color:#5d6b7d">{{ allCount }} expenses{{ userBranchId ? ' at your branch' : '' }} · {{ money(unpaidAmount) }} unpaid</div>
        </div>
        <div v-if="can('expenses.create')" style="margin-left:auto;display:flex;gap:8px;align-items:center">
          <RouterLink :to="{ name: 'expenses-new' }" style="height:34px;padding:0 15px;border-radius:8px;background:var(--c-primary);color:#ffffff;display:flex;align-items:center;font-size:12.5px;font-weight:600;cursor:pointer;text-decoration:none" v-pseudo:hover="'background:var(--c-primary-hover);color:#ffffff'">+ Add expense</RouterLink>
        </div>
      </div>
      <div style="display:flex;gap:2px;margin-top:16px">
        <div v-for="t in TABS" :key="t.key" @click="setTab(t.key)" :style="tabStyle(t.key)">{{ t.name }}<span :style="countStyle(t.key)">{{ counts[t.key] || 0 }}</span></div>
      </div>
    </div>
    <div style="padding:20px 24px 32px;display:grid;grid-template-columns:minmax(0,1fr) 300px;gap:16px;align-items:start">
      <div>
        <div v-if="overdue.length && tab !== 'unpaid'" style="display:flex;align-items:center;gap:12px;background:#fdf1dd;border:1px solid #f0d9ac;border-radius:11px;padding:11px 15px;margin-bottom:14px">
          <div style="width:8px;height:8px;border-radius:50%;background:#d98c1a;flex:none"></div>
          <div style="font-size:12.5px;color:#8a5300;flex:1">{{ overdueText }}</div>
          <div @click="setTab('unpaid')" style="font-size:12.5px;font-weight:600;color:#8a5300;cursor:pointer;text-decoration:underline">Show unpaid</div>
        </div>
        <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-bottom:14px">
          <div style="position:relative;width:230px"><div style="position:absolute;left:10px;top:50%;transform:translateY(-50%);color:#9aa7b6;font-size:12px">⚲</div> <input :value="search" @input="onSearch" placeholder="Payee, reference or note" style="width:100%;height:34px;border:1px solid #dfe6ee;border-radius:8px;background:#ffffff;padding:0 10px 0 29px;font-family:'Public Sans',sans-serif;font-size:12.5px;outline:none" v-pseudo:focus="'border-color:var(--c-primary)'" /></div>
          <select v-if="!userBranchId" v-model.number="branchId" @change="reload" :style="filterStyle">
            <option :value="0">All locations</option>
            <option v-for="b in branches" :key="b.id" :value="b.id">{{ b.name }}</option>
          </select>
          <select v-model.number="categoryId" @change="reload" :style="filterStyle">
            <option :value="0">All categories</option>
            <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
          </select>
          <select v-model="account" @change="reload" :style="filterStyle">
            <option value="">All accounts</option>
            <option v-for="a in accounts" :key="a.id" :value="a.id">{{ accountLabel(a) }}</option>
          </select>
          <div v-if="hasFilters" @click="clearFilters" style="font-size:12.5px;font-weight:600;color:var(--c-primary);cursor:pointer;padding:0 6px">Clear</div>
        </div>
        <div style="background:#ffffff;border:1px solid #dfe6ee;border-radius:12px;overflow:hidden">
          <div style="display:grid;grid-template-columns:0.9fr 1.1fr 1.9fr 1.1fr 0.9fr 0.9fr 34px;gap:12px;padding:10px 16px;background:#f6f9fc;border-bottom:1px solid #dfe6ee;font-family:'IBM Plex Mono',monospace;font-size:9.5px;letter-spacing:0.07em;text-transform:uppercase;color:#5d6b7d;align-items:center">
            <div>Date</div>
            <div>Category</div>
            <div>Payee</div>
            <div>Account</div>
            <div style="text-align:right">Amount</div>
            <div>Status</div>
            <div></div>
          </div>
          <div v-for="r in rows" :key="r.id" @click="openDetails(r.id)" style="display:grid;grid-template-columns:0.9fr 1.1fr 1.9fr 1.1fr 0.9fr 0.9fr 34px;gap:12px;padding:11px 16px;border-bottom:1px solid #f0f4f8;align-items:center;cursor:pointer" v-pseudo:hover="'background:#f9fbfd'">
            <div>
              <div style="font-family:'IBM Plex Mono',monospace;font-size:12px">{{ dmy(r.date) }}</div>
              <div style="font-family:'IBM Plex Mono',monospace;font-size:10.5px;color:var(--c-primary);margin-top:1px">{{ r.ref }}</div>
            </div>
            <div style="display:flex;align-items:center;gap:7px;min-width:0">
              <div :style="categoryDot(r.category.color)"></div>
              <div style="font-size:12.5px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">{{ r.category.name }}</div>
            </div>
            <div style="min-width:0">
              <div style="font-size:13px;font-weight:500;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">{{ r.payee }}</div>
              <div style="font-size:11px;color:#8b98a8;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">{{ r.notes || r.branch.name }}</div>
            </div>
            <div style="font-size:12.5px;color:#4a5866;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">{{ r.payment_account ? r.payment_account.name : '—' }}</div>
            <div style="font-family:'IBM Plex Mono',monospace;font-size:12.5px;text-align:right;font-weight:600">{{ money(r.amount) }}</div>
            <div><span :style="statusBadge(r)">{{ statusText(r) }}</span></div>
            <div v-if="r.receipt_url" title="Receipt attached" style="font-family:'IBM Plex Mono',monospace;font-size:9.5px;font-weight:600;text-align:center;background:#eef2f6;color:#5d6b7d;border-radius:4px;padding:2px 0">{{ fileType(r) }}</div>
            <div v-else></div>
          </div>
          <div v-if="!rows.length" style="padding:40px 20px;text-align:center">
            <div style="font-size:13.5px;font-weight:600;margin-bottom:4px">{{ loading ? 'Loading…' : 'No expenses match' }}</div>
            <div v-if="!loading" style="font-size:12.5px;color:#7d8b9c">Try another status or clear the filters.</div>
          </div>
          <div style="display:grid;grid-template-columns:0.9fr 1.1fr 1.9fr 1.1fr 0.9fr 0.9fr 34px;gap:12px;padding:12px 16px;background:#f6f9fc;align-items:center">
            <div style="font-size:12px;color:#7d8b9c;grid-column:span 4">Showing {{ rows.length }} of {{ total }} · GST included {{ money(totalGst) }}</div>
            <div style="font-family:'IBM Plex Mono',monospace;font-size:13px;text-align:right;font-weight:700">{{ money(totalAmount) }}</div>
            <div style="display:flex;justify-content:flex-end;gap:4px;grid-column:span 2">
              <div @click="goPage(page - 1)" :style="pageStyle(page > 1)">Prev</div>
              <div @click="goPage(page + 1)" :style="pageStyle(page < lastPage)">Next</div>
            </div>
          </div>
        </div>
      </div>
      <div style="background:#ffffff;border:1px solid #dfe6ee;border-radius:12px;padding:17px 19px">
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:3px">
          <div style="font-size:13px;font-weight:700;flex:1">By category</div>
          <input v-model="month" @change="load" type="month" title="Month" style="height:28px;border:1px solid #dfe6ee;border-radius:7px;background:#ffffff;padding:0 6px;font-family:'Public Sans',sans-serif;font-size:11.5px;color:#4a5866" />
        </div>
        <div style="font-family:'IBM Plex Mono',monospace;font-size:22px;font-weight:600;letter-spacing:-0.02em;margin-bottom:14px">{{ money(byCategory.total) }}</div>
        <div style="display:grid;gap:10px">
          <div v-for="b in byCategory.rows" :key="b.id" @click="toggleCategory(b.id)" style="cursor:pointer">
            <div style="display:flex;align-items:baseline;gap:8px;margin-bottom:4px">
              <div :style="'flex:1;font-size:12.5px;' + (categoryId === b.id ? 'font-weight:700;color:var(--c-primary);' : 'color:#0f1c2b;')">{{ b.name }}</div>
              <div style="font-family:'IBM Plex Mono',monospace;font-size:12px;color:#4a5866">{{ money(b.value) }}</div>
            </div>
            <div style="height:6px;border-radius:99px;background:#eef2f6;overflow:hidden">
              <div :style="barStyle(b)"></div>
            </div>
          </div>
          <div v-if="!byCategory.rows.length" style="font-size:12.5px;color:#9aa7b6">Nothing spent in this month.</div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
// Expenses list. API: GET /api/expenses?tab=&search=&branch_id=&category_id=&account=&month=&page=
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { api } from '../../api/http.js';
import { can } from '../../api/auth.js';
import { branches, loadBranches } from '../../api/branches.js';
import { money, dmy, userBranchId, categoryDot, statusText, statusBadge, accountLabel } from '../../api/expenses.js';

const route = useRoute();
const router = useRouter();

const TABS = [
  { key: 'all', name: 'All' }, { key: 'paid', name: 'Paid' }, { key: 'unpaid', name: 'Unpaid' }, { key: 'no_receipt', name: 'No receipt' }
];
const filterStyle = "height:34px;border:1px solid #dfe6ee;border-radius:8px;background:#ffffff;padding:0 8px;font-family:'Public Sans',sans-serif;font-size:12.5px;color:#4a5866";

// The tab is in the URL, so the sidebar's Unpaid bills link can open it.
const tab = computed(() => String(route.query.tab || 'all'));
const search = ref('');
const branchId = ref(0);
const categoryId = ref(0);
const account = ref('');
const month = ref(new Date().getFullYear() + '-' + String(new Date().getMonth() + 1).padStart(2, '0'));
const page = ref(1);

const rows = ref<any[]>([]);
const total = ref(0);
const lastPage = ref(1);
const counts = ref<Record<string, number>>({});
const totalAmount = ref(0);
const totalGst = ref(0);
const allCount = ref(0);
const unpaidAmount = ref(0);
const overdue = ref<any[]>([]);
const byCategory = ref<any>({ total: 0, rows: [] });
const categories = ref<any[]>([]);
const accounts = ref<any[]>([]);
const loading = ref(true);

const hasFilters = computed(() => !!(search.value || branchId.value || categoryId.value || account.value));
const overdueText = computed(() => overdue.value.length + (overdue.value.length === 1 ? ' bill is' : ' bills are') + ' overdue: '
  + overdue.value.map(x => x.payee + ' ' + money(x.amount)).join(', '));

async function load() {
  loading.value = true;
  const params = new URLSearchParams({ page: String(page.value) });
  if (tab.value !== 'all') params.set('tab', tab.value);
  if (search.value.trim()) params.set('search', search.value.trim());
  if (branchId.value) params.set('branch_id', String(branchId.value));
  if (categoryId.value) params.set('category_id', String(categoryId.value));
  if (account.value) params.set('account', account.value);
  if (month.value) params.set('month', month.value);
  try {
    const res = await api.get('/expenses?' + params.toString());
    rows.value = res.data;
    total.value = res.total;
    lastPage.value = res.last_page;
    counts.value = res.counts;
    totalAmount.value = res.total_amount;
    totalGst.value = res.total_gst;
    allCount.value = res.all_count;
    unpaidAmount.value = res.unpaid_amount;
    overdue.value = res.overdue;
    byCategory.value = res.by_category;
    categories.value = res.categories;
    accounts.value = res.accounts;
  } catch (e) { /* the error bar shows why */ }
  loading.value = false;
}

onMounted(() => {
  load();
  loadBranches().catch(() => {});
});

watch(tab, reload);

function reload() { page.value = 1; load(); }
function setTab(key: string) { router.replace({ name: 'expenses', query: key === 'all' ? {} : { tab: key } }); }
function goPage(n: number) { if (n < 1 || n > lastPage.value) return; page.value = n; load(); }
function openDetails(id: number) { router.push({ name: 'expenses-details', query: { id } }); }
function toggleCategory(id: number) { categoryId.value = categoryId.value === id ? 0 : id; reload(); }

function clearFilters() {
  search.value = '';
  branchId.value = 0;
  categoryId.value = 0;
  account.value = '';
  reload();
}

// Wait until typing stops for a moment before searching.
let timer: any = null;
function onSearch(e: Event) {
  search.value = (e.target as HTMLInputElement).value;
  clearTimeout(timer);
  timer = setTimeout(reload, 300);
}

function fileType(row: any) {
  return /\.pdf$/i.test(row.receipt_name || '') ? 'PDF' : 'IMG';
}
function barStyle(b: any) {
  const biggest = byCategory.value.rows[0].value || 1;
  return 'height:100%;border-radius:99px;width:' + Math.max(3, b.value / biggest * 100) + '%;background:' + b.color;
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
