<template>
  <div>
    <!-- Header: today, the register, and who/where the numbers are for -->
    <div style="padding:20px 24px 16px;background:#ffffff;border-bottom:1px solid #dfe6ee">
      <div style="display:flex;align-items:flex-start;gap:20px;flex-wrap:wrap">
        <div>
          <h2 style="margin:0 0 3px;font-size:22px;font-weight:700;letter-spacing:-0.018em">{{ todayText }}</h2>
          <div style="font-size:12.5px;color:#5d6b7d">{{ registerLine }}</div>
        </div>
        <div style="margin-left:auto;display:flex;gap:8px;flex-wrap:wrap">
          <RouterLink v-if="can('expenses.create')" to="/expenses" :style="btnLight">Add expense</RouterLink>
          <RouterLink v-if="can('sell.create')" to="/pos" :style="btnLight">Open POS</RouterLink>
          <!-- One button per register this user has open today (usually one) -->
          <div v-for="r in openRegisters" :key="r.id" @click="closing = r" :title="'Drawer should have ' + money(r.expected_cash)" style="height:34px;padding:0 13px;border-radius:8px;border:1px solid #f3cfcf;background:#fff7f7;display:flex;align-items:center;gap:7px;font-size:12.5px;font-weight:600;color:#b02020;cursor:pointer">
            Close register<span v-if="openRegisters.length > 1" style="font-weight:500">· {{ r.branch }}</span>
          </div>
        </div>
      </div>

      <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-top:14px">
        <template v-if="d.scope === 'all'">
          <select v-model.number="branchId" @change="load" title="Branch" :style="selectStyle">
            <option :value="0">All branches</option>
            <option v-for="b in d.branches" :key="b.id" :value="b.id">{{ b.name }}</option>
          </select>
        </template>
        <div v-else-if="d.branch" :style="chip">{{ d.branch.name }}</div>
        <select v-if="d.scope !== 'own'" v-model="staffId" @change="load" title="Salesperson" :style="selectStyle">
          <option value="">All salespeople</option>
          <option v-for="s in d.staff" :key="s.id" :value="s.id">{{ s.name }}</option>
        </select>
        <div v-else :style="chip">Your own sales</div>
        <div v-if="loading" style="font-size:12px;color:#8b98a8">Updating…</div>
      </div>
    </div>
    <div v-if="closedMessage" style="margin:14px 24px 0;background:#e7f6ee;color:#0f6b43;border-radius:10px;padding:10px 14px;font-size:12.5px">{{ closedMessage }}</div>
    <div v-if="error" style="margin:14px 24px 0;background:#fdeaea;color:#b02020;border-radius:10px;padding:10px 14px;font-size:12.5px">{{ error }}</div>
    <CloseRegister v-if="closing" :register="closing" @cancel="closing = null" @done="onClosed" />

    <div style="padding:20px 24px 32px">
      <!-- Today's numbers -->
      <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:14px">
        <div v-for="(k, i) in d.kpis" :key="k.label" style="background:#ffffff;border:1px solid #dfe6ee;border-radius:12px;padding:15px 17px">
          <div style="font-size:11.5px;color:#7d8b9c;margin-bottom:8px">{{ k.label }}</div>
          <div style="font-family:'IBM Plex Mono',monospace;font-size:25px;font-weight:600;letter-spacing:-0.025em;line-height:1">{{ k.type === 'money' ? money(k.value) : k.value }}</div>
          <div :style="'font-size:11.5px;margin-top:6px;' + noteColor(i, k)">{{ noteText(i, k) }}</div>
        </div>
      </div>

      <div style="display:grid;grid-template-columns:1fr 320px;gap:14px;margin-bottom:14px;align-items:start">
        <!-- Sales chart -->
        <div style="background:#ffffff;border:1px solid #dfe6ee;border-radius:12px;padding:18px 20px">
          <div style="display:flex;align-items:center;gap:14px;margin-bottom:20px;flex-wrap:wrap">
            <div>
              <div style="font-size:14px;font-weight:700">Sales, {{ days === 365 ? 'last 12 months' : 'last ' + days + ' days' }}</div>
              <div style="font-size:11.5px;color:#7d8b9c;margin-top:2px">Total {{ money(d.chart.total) }} · average {{ money(d.chart.average) }} per day</div>
            </div>
            <div style="margin-left:auto;display:flex;gap:2px;background:#eef2f6;padding:3px;border-radius:8px">
              <div v-for="p in [[30, '30d'], [90, '90d'], [365, 'Year']]" :key="p[0]" @click="days = Number(p[0]); load()" :style="seg(days === p[0])">{{ p[1] }}</div>
            </div>
          </div>
          <div style="display:flex;align-items:flex-end;gap:3px;height:168px;border-bottom:1px solid #e4eaf1;padding:0 1px">
            <div v-for="(v, i) in d.chart.values" :key="i" :title="d.chart.labels[i] + ': ' + money(v)" :style="barStyle(v)"></div>
          </div>
          <div style="display:flex;justify-content:space-between;font-family:'IBM Plex Mono',monospace;font-size:10px;color:#9aa7b6;margin-top:8px">
            <span v-for="(l, i) in axisLabels" :key="i">{{ l }}</span>
          </div>
          <div v-if="!d.chart.total && !loading" style="font-size:12px;color:#8b98a8;margin-top:10px">No sales in this period yet.</div>
        </div>

        <div style="display:grid;gap:12px">
          <!-- Needs attention: only what this user may open -->
          <div v-if="d.alerts.length" style="background:#ffffff;border:1px solid #dfe6ee;border-radius:12px;padding:16px 18px">
            <div style="font-size:12.5px;font-weight:700;margin-bottom:12px">Needs attention</div>
            <div style="display:grid;gap:8px">
              <RouterLink v-for="a in d.alerts" :key="a.label" :to="a.link" :style="alertStyle(a.tone)">
                <div style="font-family:'IBM Plex Mono',monospace;font-size:14px;font-weight:600;flex:none;min-width:22px">{{ a.count }}</div>
                <div style="font-size:12.5px;line-height:1.4;flex:1">{{ a.label }}</div>
                <div style="font-size:13px;flex:none;opacity:.5">›</div>
              </RouterLink>
            </div>
          </div>
          <!-- Payment accounts (needs accounts.view) -->
          <div v-if="d.accounts" style="background:#ffffff;border:1px solid #dfe6ee;border-radius:12px;padding:16px 18px">
            <div style="display:flex;align-items:baseline;margin-bottom:12px">
              <div style="font-size:12.5px;font-weight:700;flex:1">Payment accounts</div>
              <RouterLink to="/payment-accounts" style="font-size:12px;font-weight:500;color:var(--c-primary);text-decoration:none">View all</RouterLink>
            </div>
            <div style="display:grid;gap:11px">
              <div v-if="!d.accounts.length" style="font-size:12px;color:#8b98a8">No accounts yet.</div>
              <div v-for="ac in d.accounts" :key="ac.name + ac.kind" style="display:flex;align-items:baseline;gap:10px">
                <div style="font-size:12.5px;flex:1;min-width:0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">{{ ac.name }}</div>
                <div :style="'font-family:\'IBM Plex Mono\',monospace;font-size:13px;font-weight:600;' + (ac.bal < 0 ? 'color:#b02020' : '')">{{ ac.kind === 'card' ? (ac.bal < 0 ? money(-ac.bal) + ' to pay' : 'Nothing to pay') : money(ac.bal) }}</div>
              </div>
              <div v-if="d.accounts.length" style="display:flex;align-items:baseline;gap:10px;padding-top:9px;border-top:1px solid #f0f4f8">
                <div style="font-size:12.5px;font-weight:600;flex:1">Cash and bank</div>
                <div style="font-family:'IBM Plex Mono',monospace;font-size:13px;font-weight:700">{{ money(cashAndBank) }}</div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div style="display:grid;grid-template-columns:1.35fr 1fr;gap:14px;align-items:start">
        <!-- Recent sales -->
        <div style="background:#ffffff;border:1px solid #dfe6ee;border-radius:12px;overflow:hidden">
          <div style="padding:14px 18px;display:flex;align-items:center;gap:12px;border-bottom:1px solid #eaeff5">
            <div style="font-size:13px;font-weight:700">{{ d.scope === 'own' ? 'My recent sales' : 'Recent sales' }}</div>
            <RouterLink v-if="can('sell.view')" to="/sell" style="margin-left:auto;font-size:12px;font-weight:500;color:var(--c-primary);text-decoration:none">View all</RouterLink>
          </div>
          <div :style="saleGrid + 'padding:9px 18px;background:#f6f9fc;border-bottom:1px solid #dfe6ee;font-family:\'IBM Plex Mono\',monospace;font-size:9.5px;letter-spacing:0.07em;text-transform:uppercase;color:#5d6b7d'">
            <div>Invoice</div><div>Customer</div><div>By</div><div style="text-align:right">Total</div><div style="text-align:right">Status</div>
          </div>
          <div v-if="!d.recent.length" style="padding:18px;font-size:12.5px;color:#8b98a8">No sales yet.</div>
          <div v-for="s in d.recent" :key="s.id" :style="saleGrid + 'padding:10px 18px;border-bottom:1px solid #f0f4f8'">
            <div>
              <div style="font-family:'IBM Plex Mono',monospace;font-size:12px;font-weight:500;color:var(--c-primary)">{{ s.ref }}</div>
              <div style="font-size:10.5px;color:#9aa7b6;margin-top:1px">{{ when(s.sold_at) }}{{ d.scope === 'all' && !branchId && s.branch ? ' · ' + s.branch.name : '' }}</div>
            </div>
            <div style="font-size:12.5px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">{{ s.customer_name }}</div>
            <div style="font-size:12px;color:#5d6b7d;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">{{ s.created_by }}</div>
            <div style="font-family:'IBM Plex Mono',monospace;font-size:12.5px;font-weight:500;text-align:right">{{ money(s.total) }}</div>
            <div style="text-align:right"><span :style="statusBadge(s.status)">{{ STATUS[s.status] || s.status }}</span></div>
          </div>
        </div>

        <div style="display:grid;gap:14px">
          <!-- Sales by salesperson (not for "own") -->
          <div v-if="d.scope !== 'own'" style="background:#ffffff;border:1px solid #dfe6ee;border-radius:12px;overflow:hidden">
            <div style="padding:14px 18px;border-bottom:1px solid #eaeff5;font-size:13px;font-weight:700">Sales by salesperson · {{ days === 365 ? '12 months' : days + ' days' }}</div>
            <div v-if="!d.staff_sales.length" style="padding:18px;font-size:12.5px;color:#8b98a8">No sales yet.</div>
            <div v-for="s in d.staff_sales" :key="s.user_id || s.name" @click="pickStaff(s.user_id)" style="display:flex;align-items:center;gap:12px;padding:10px 18px;border-bottom:1px solid #f0f4f8;cursor:pointer">
              <div style="flex:1;min-width:0">
                <div style="font-size:12.5px;font-weight:600">{{ s.name || 'Unknown' }}</div>
                <div style="height:6px;border-radius:99px;background:#eef2f6;margin-top:5px;overflow:hidden"><div :style="'height:100%;border-radius:99px;background:var(--c-primary);width:' + (s.total / staffMax * 100) + '%'"></div></div>
              </div>
              <div style="text-align:right">
                <div style="font-family:'IBM Plex Mono',monospace;font-size:12.5px;font-weight:600">{{ money(s.total) }}</div>
                <div style="font-size:10.5px;color:#8b98a8">{{ s.invoices }} {{ s.invoices === 1 ? 'sale' : 'sales' }}</div>
              </div>
            </div>
          </div>
          <!-- Low stock (needs products.view) -->
          <div v-if="d.low_stock" style="background:#ffffff;border:1px solid #dfe6ee;border-radius:12px;overflow:hidden">
            <div style="padding:14px 18px;display:flex;align-items:center;gap:12px;border-bottom:1px solid #eaeff5">
              <div style="font-size:13px;font-weight:700">Low stock</div>
              <span v-if="d.low_stock.length" style="font-family:'IBM Plex Mono',monospace;font-size:10.5px;background:#fdeaea;color:#b02020;border-radius:4px;padding:2px 6px;font-weight:600">{{ d.low_stock.length }}</span>
              <RouterLink v-if="can('purchases.create')" to="/purchases" style="margin-left:auto;font-size:12px;font-weight:500;color:var(--c-primary);text-decoration:none">Reorder</RouterLink>
            </div>
            <div v-if="!d.low_stock.length" style="padding:18px;font-size:12.5px;color:#0f6b43">All stock above the alert levels.</div>
            <div v-for="l in d.low_stock.slice(0, 8)" :key="l.name" style="display:flex;align-items:center;gap:12px;padding:10px 18px;border-bottom:1px solid #f0f4f8">
              <div style="flex:1;min-width:0">
                <div style="font-size:12.5px;font-weight:500">{{ l.name }}</div>
                <div style="font-size:10.5px;color:#9aa7b6">alert at {{ l.alert }} {{ l.unit }}</div>
              </div>
              <span :style="'font-family:\'IBM Plex Mono\',monospace;font-size:11.5px;font-weight:600;border-radius:5px;padding:2px 7px;' + (l.out ? 'background:#fdeaea;color:#b02020' : 'background:#fdf1dd;color:#8a5300')">{{ l.out ? 'Out' : l.qty + ' ' + l.unit }}</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
// Dashboard. GET /api/dashboard?branch_id=&user_id=&days= (refreshed every minute).
// The server decides what the user may see: all branches, their branch, or only their own sales.
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { useErpPage } from '../erp/useErpPage';
import { api } from '../api/http.js';
import { can } from '../api/auth.js';
import { money } from '../api/products.js';
import CloseRegister from './pos/CloseRegister.vue';

useErpPage('home', 'nav'); // the sidebar

const STATUS: Record<string, string> = { paid: 'Paid', partial: 'Part paid', due: 'Due' };
const btnLight = 'height:34px;padding:0 13px;border-radius:8px;border:1px solid #dfe6ee;display:flex;align-items:center;font-size:12.5px;font-weight:500;color:#4a5866;cursor:pointer;text-decoration:none';
const selectStyle = "height:34px;border:1px solid #dfe6ee;border-radius:8px;background:#ffffff;padding:0 10px;font-family:'Public Sans',sans-serif;font-size:12.5px;color:#4a5866;font-weight:600;cursor:pointer";
const chip = 'height:34px;padding:0 12px;border-radius:8px;background:#eef2f6;display:flex;align-items:center;font-size:12.5px;font-weight:600;color:#4a5866';
const saleGrid = 'display:grid;grid-template-columns:1fr 1.1fr 0.9fr 0.8fr 0.8fr;gap:12px;align-items:center;';

// A remembered choice on this computer only.
function saved(key: string, fallback: string) {
  try { return localStorage.getItem(key) || fallback; } catch { return fallback; }
}

const EMPTY = { scope: 'own', branch: null, branches: [], staff: [], kpis: [], chart: { labels: [], values: [], total: 0, average: 0, by: 'day' }, recent: [], staff_sales: [], alerts: [], accounts: null, low_stock: null };
const d = ref<any>(EMPTY);
const branchId = ref(Number(saved('home_branch', '0')) || 0);
const staffId = ref('');
const days = ref(30);
const loading = ref(false);
const error = ref('');

async function load() {
  loading.value = true;
  error.value = '';
  try {
    const p = new URLSearchParams({ days: String(days.value) });
    if (branchId.value) p.set('branch_id', String(branchId.value));
    if (staffId.value) p.set('user_id', staffId.value);
    d.value = await api.get('/dashboard?' + p.toString());
    try { localStorage.setItem('home_branch', String(branchId.value)); } catch { /* ignore */ }
  } catch (e: any) {
    error.value = e.message || 'Could not load the dashboard.';
  }
  loading.value = false;
}

function pickStaff(id: string) {
  staffId.value = staffId.value === id ? '' : id;
  load();
}

// --- small helpers for the template ---
const cashAndBank = computed(() => (d.value.accounts || []).filter((a: any) => a.kind !== 'card').reduce((t: number, a: any) => t + a.bal, 0));
const chartMax = computed(() => Math.max(1, ...d.value.chart.values));
const staffMax = computed(() => Math.max(1, ...d.value.staff_sales.map((s: any) => s.total)));
// Five dates under the chart.
const axisLabels = computed(() => {
  const l = d.value.chart.labels;
  if (!l.length) return [];
  return [0, 0.25, 0.5, 0.75, 1].map(f => l[Math.round(f * (l.length - 1))]);
});

function barStyle(v: number) {
  return 'flex:1;min-width:0;border-radius:3px 3px 0 0;background:var(--c-primary);height:' + Math.max(v > 0 ? 2 : 0, v / chartMax.value * 100) + '%';
}

function noteText(i: number, k: any) {
  if (i === 0) return k.note === null ? 'nothing on this day last week' : (k.note >= 0 ? '▲ ' : '▼ ') + Math.abs(k.note) + '% vs this day last week';
  if (i === 3) return k.note + (k.note === 1 ? ' sale' : ' sales') + ' with money owing';
  return d.value.scope === 'own' ? 'your sales' : '';
}

function noteColor(i: number, k: any) {
  if (i === 0 && k.note !== null) return k.note >= 0 ? 'color:#0f6b43' : 'color:#b02020';
  return 'color:#8b98a8';
}

function alertStyle(tone: string) {
  const c: Record<string, string[]> = { error: ['#fdeaea', '#b02020'], warn: ['#fdf1dd', '#8a5300'], info: ['var(--c-primary-soft)', 'var(--c-primary-text)'], ok: ['#f6f9fc', '#5d6b7d'] };
  const t = c[tone] || c.ok;
  return 'display:flex;align-items:center;gap:10px;border-radius:9px;padding:9px 11px;text-decoration:none;background:' + t[0] + ';color:' + t[1];
}

function statusBadge(status: string) {
  const c = status === 'paid' ? ['#e7f6ee', '#0f6b43'] : status === 'partial' ? ['#fdf1dd', '#8a5300'] : ['#fdeaea', '#b02020'];
  return 'background:' + c[0] + ';color:' + c[1] + ';border-radius:5px;padding:2px 7px;font-size:11px;font-weight:600';
}

function seg(on: boolean) {
  return 'padding:5px 11px;border-radius:6px;font-size:11.5px;cursor:pointer;' + (on ? 'background:#ffffff;font-weight:600;box-shadow:0 1px 2px rgba(15,28,43,.09);' : 'color:#5d6b7d;');
}

function when(iso: string) {
  const t = new Date(iso), now = new Date();
  const hm = String(t.getHours()).padStart(2, '0') + ':' + String(t.getMinutes()).padStart(2, '0');
  return t.toDateString() === now.toDateString() ? 'Today ' + hm : t.toLocaleDateString('en-AU', { day: 'numeric', month: 'short' }) + ' ' + hm;
}

// --- the cash register line and Close register (GET /api/cash-register/mine) ---
const openRegisters = ref<any[]>([]);
const closing = ref<any>(null);
const closedMessage = ref('');
const todayText = new Date().toLocaleDateString('en-AU', { weekday: 'long', day: 'numeric', month: 'long' });

function timeOf(iso: string) {
  const t = new Date(iso);
  return String(t.getHours()).padStart(2, '0') + ':' + String(t.getMinutes()).padStart(2, '0');
}

const registerLine = computed(() => {
  const list = openRegisters.value;
  if (!list.length) return can('sell.create') ? 'Your register is not open today. Open it on the POS with the opening cash count.' : '';
  return list.map(r => 'Register open at ' + r.branch + ' since ' + timeOf(r.opened_at) + ' · drawer should have ' + money(r.expected_cash)).join(' · ');
});

async function loadRegisters() {
  if (!can('sell.view')) return;
  try {
    openRegisters.value = (await api.get('/cash-register/mine')).data;
  } catch (e) { /* the header just says nothing about the register */ }
}

function onClosed(r: any) {
  closing.value = null;
  const diff = r.difference || 0;
  closedMessage.value = 'Register at ' + r.branch + ' closed: counted ' + money(r.counted_cash)
    + (Math.abs(diff) < 0.005 ? ', balanced.' : (diff > 0 ? ', over by ' : ', short by ') + money(Math.abs(diff)) + '.')
    + ' The next opening count starts from ' + money(r.kept_cash) + '.';
  loadRegisters();
}

let timer: ReturnType<typeof setInterval> | undefined;
onMounted(() => {
  load();
  loadRegisters();
  timer = setInterval(load, 60000);
});
onUnmounted(() => clearInterval(timer));
</script>
