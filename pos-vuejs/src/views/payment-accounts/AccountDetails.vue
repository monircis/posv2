<template>
  <div>
    <div v-if="loading && !a" style="padding:60px;text-align:center;font-size:13px;color:#7d8b9c">Loading…</div>
    <div v-else-if="error && !a" style="padding:24px"><div style="background:#fdeaea;color:#b02020;border-radius:10px;padding:10px 14px;font-size:12.5px">{{ error }}</div></div>
    <template v-if="a">
      <div style="padding:20px 24px 0;background:#ffffff;border-bottom:1px solid #dfe6ee">
        <div style="display:flex;align-items:center;gap:7px;font-size:11.5px;color:#7d8b9c;margin-bottom:8px">
          <span @click="back" style="cursor:pointer;color:var(--c-primary)">Payment accounts</span><span style="color:#c2cedb">/</span>
          <span>{{ branchText(a) }}</span><span style="color:#c2cedb">/</span>
          <span style="color:#0f1c2b;font-weight:500">{{ a.name }}</span>
        </div>
        <div style="display:flex;align-items:flex-start;gap:20px;flex-wrap:wrap">
          <div style="display:flex;gap:12px;align-items:center">
            <div :style="iconStyle(a.kind, true)">{{ KIND[a.kind].icon }}</div>
            <div>
              <div style="display:flex;align-items:center;gap:9px;margin-bottom:2px">
                <h2 style="margin:0;font-size:22px;font-weight:700;letter-spacing:-0.018em">{{ a.name }}</h2>
                <span :style="badge(a.active ? KIND[a.kind].c : TONE.neutral)">{{ a.active ? KIND[a.kind].name : 'Closed' }}</span>
                <span :style="badge(a.branch_id ? TONE.success : TONE.info)">{{ branchText(a) }}</span>
              </div>
              <div style="font-size:12.5px;color:#5d6b7d">{{ accountSub(a) }}{{ defsLine }}</div>
            </div>
          </div>
          <div style="margin-left:auto;display:flex;gap:8px;align-items:center">
            <div @click="exportCsv" :style="btn">Export</div>
            <div v-if="canEdit" @click="formOpen = true" :style="btn + 'font-weight:600;color:var(--c-primary-text)'">Edit</div>
            <div v-if="canEdit && a.active" @click="transferOpen = true" style="height:34px;padding:0 15px;border-radius:8px;background:var(--c-primary);color:#ffffff;display:flex;align-items:center;font-size:12.5px;font-weight:600;cursor:pointer">Transfer funds</div>
          </div>
        </div>
        <div style="display:grid;grid-template-columns:1.3fr repeat(3,minmax(0,1fr));margin-top:18px;border-top:1px solid #eef2f6">
          <div style="padding:14px 18px 14px 0">
            <div style="font-size:11.5px;color:#7d8b9c">{{ card ? 'Owing' : 'Current balance' }}</div>
            <div :style="'font-family:\'IBM Plex Mono\',monospace;font-size:28px;font-weight:600;letter-spacing:-0.03em;margin-top:2px;' + (a.bal < 0 ? 'color:#b02020;' : '')">{{ card ? money(-a.bal) : signed(a.bal) }}</div>
          </div>
          <div v-for="s in stats" :key="s.k" style="padding:14px 18px;border-left:1px solid #eef2f6">
            <div style="font-size:11.5px;color:#7d8b9c">{{ s.k }}</div>
            <div :style="'font-family:\'IBM Plex Mono\',monospace;font-size:17px;font-weight:600;margin-top:3px;color:' + s.c">{{ s.v }}</div>
            <div style="font-size:11px;color:#9aa7b6;margin-top:1px">{{ s.n }}</div>
          </div>
        </div>
        <div style="display:flex;gap:2px;margin-top:4px">
          <div v-for="t in TABS" :key="t" @click="tab = t" :style="tabStyle(tab === t)">{{ t }}<span :style="countStyle(tab === t)">{{ countOf(t) }}</span></div>
        </div>
      </div>

      <div style="padding:20px 24px 32px">
        <div v-if="message" style="background:#e7f6ee;color:#0f6b43;border-radius:10px;padding:10px 14px;font-size:12.5px;margin-bottom:12px">{{ message }}</div>
        <div style="display:flex;gap:8px;align-items:center;margin-bottom:12px">
          <div style="position:relative;width:260px"><div style="position:absolute;left:10px;top:50%;transform:translateY(-50%);color:#9aa7b6;font-size:12px">⚲</div>
            <input v-model="q" placeholder="Description or reference" style="width:100%;height:34px;border:1px solid #dfe6ee;border-radius:8px;background:#ffffff;padding:0 10px 0 29px;font-family:'Public Sans',sans-serif;font-size:12.5px;outline:none" />
          </div>
        </div>
        <div style="background:#ffffff;border:1px solid #dfe6ee;border-radius:12px;overflow:hidden">
          <div :style="grid + 'padding:10px 16px;background:#f6f9fc;border-bottom:1px solid #dfe6ee;font-family:\'IBM Plex Mono\',monospace;font-size:9.5px;letter-spacing:0.07em;text-transform:uppercase;color:#5d6b7d'">
            <div>Date</div><div>Description</div><div>Reference</div><div>Type</div>
            <div style="text-align:right">Money in</div><div style="text-align:right">Money out</div><div style="text-align:right">Balance</div>
          </div>
          <div v-for="(l, i) in ledger" :key="i" :style="grid + 'padding:10px 16px;border-bottom:1px solid #f0f4f8'">
            <div style="font-family:'IBM Plex Mono',monospace;font-size:12px;color:#4a5866">{{ l.date }}</div>
            <div style="min-width:0">
              <div style="font-size:13px;font-weight:500;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">{{ l.desc }}</div>
              <div style="font-size:11px;color:#8b98a8;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">{{ l.party || ' ' }}</div>
            </div>
            <div style="font-family:'IBM Plex Mono',monospace;font-size:12px;color:var(--c-primary);white-space:nowrap;overflow:hidden;text-overflow:ellipsis">{{ l.ref }}</div>
            <div><span :style="badge(TONE[typeOf(l)[1]])">{{ typeOf(l)[0] }}</span></div>
            <div style="font-family:'IBM Plex Mono',monospace;font-size:12.5px;text-align:right;color:#0f6b43;font-weight:500">{{ l.amt > 0 ? money(l.amt) : '' }}</div>
            <div style="font-family:'IBM Plex Mono',monospace;font-size:12.5px;text-align:right;color:#b02020;font-weight:500">{{ l.amt < 0 ? money(-l.amt) : '' }}</div>
            <div :style="'font-family:\'IBM Plex Mono\',monospace;font-size:12.5px;text-align:right;font-weight:600;' + (!card && l.runBal < 0 ? 'color:#b02020;' : '')">{{ card ? money(-l.runBal) : signed(l.runBal) }}</div>
          </div>
          <div v-if="!ledger.length" style="padding:40px 20px;text-align:center;font-size:12.5px;color:#7d8b9c">No transactions match.</div>
          <div :style="grid + 'padding:12px 16px;background:#f6f9fc'">
            <div style="font-size:12px;color:#7d8b9c;grid-column:span 4">Showing {{ ledger.length }} of {{ withBal.length }}{{ card ? ' · balance column shows amount owing' : ' · running balance after each line' }}</div>
            <div style="font-family:'IBM Plex Mono',monospace;font-size:13px;text-align:right;font-weight:700;color:#0f6b43">{{ money(sumIn) }}</div>
            <div style="font-family:'IBM Plex Mono',monospace;font-size:13px;text-align:right;font-weight:700;color:#b02020">{{ money(sumOut) }}</div>
            <div></div>
          </div>
        </div>
      </div>

      <AccountForm v-if="formOpen" :account="a" :accounts="accounts" :branches="branches" :user-branch-id="userBranchId" :start-branch-id="a.branch_id || 0" @close="formOpen = false" @saved="onSaved" />
      <TransferModal v-if="transferOpen" :accounts="accounts" :start-from="a.id" @close="transferOpen = false" @done="onTransfer" />
    </template>
  </div>
</template>

<script setup lang="ts">
// One payment account and its transactions. API: GET /api/payment-accounts/{id}
import { computed, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { api } from '../../api/http.js';
import { can } from '../../api/auth.js';
import { KIND, DEFAULTS, TX_TYPES, TONE, money, signed, isTransfer, branchText, accountSub, iconStyle, badge } from '../../api/paymentAccounts.js';
import AccountForm from './AccountForm.vue';
import TransferModal from './TransferModal.vue';

const route = useRoute();
const router = useRouter();
const TABS = ['All', 'Money in', 'Money out', 'Transfers'];
const grid = 'display:grid;grid-template-columns:0.8fr 2.2fr 1fr 1fr 1fr 1fr 1.1fr;gap:12px;align-items:center;';
const btn = 'height:34px;padding:0 13px;border-radius:8px;border:1px solid #dfe6ee;display:flex;align-items:center;font-size:12.5px;font-weight:500;color:#4a5866;cursor:pointer;background:#ffffff;';
const canEdit = can('accounts.edit');

const a = ref<any>(null);
const accounts = ref<any[]>([]);
const branches = ref<any[]>([]);
const userBranchId = ref<number | null>(null);
const loading = ref(true);
const error = ref('');
const message = ref('');
const tab = ref('All');
const q = ref('');
const formOpen = ref(false);
const transferOpen = ref(false);

const card = computed(() => !!a.value && a.value.kind === 'card');
const tx = computed<any[]>(() => (a.value ? a.value.tx : []));

// Running balance after each line (the list is newest first).
const withBal = computed(() => {
  let run = a.value ? a.value.bal : 0;
  return tx.value.map(t => {
    const row = { ...t, runBal: run };
    run = Math.round((run - t.amt) * 100) / 100;
    return row;
  });
});

function inTab(t: any, name: string) {
  if (name === 'Money in') return t.amt > 0 && !isTransfer(t);
  if (name === 'Money out') return t.amt < 0 && !isTransfer(t);
  if (name === 'Transfers') return isTransfer(t);
  return true;
}

const ledger = computed(() => {
  const s = q.value.trim().toLowerCase();
  return withBal.value.filter(t => inTab(t, tab.value) && (!s || (t.desc + ' ' + (t.party || '') + ' ' + t.ref).toLowerCase().includes(s)));
});
const sumIn = computed(() => ledger.value.filter(t => t.amt > 0).reduce((x, t) => x + t.amt, 0));
const sumOut = computed(() => ledger.value.filter(t => t.amt < 0).reduce((x, t) => x - t.amt, 0));

const stats = computed(() => {
  const list = tx.value;
  const inc = list.filter(t => t.amt > 0 && !isTransfer(t));
  const out = list.filter(t => t.amt < 0 && !isTransfer(t));
  const xf = list.filter(isTransfer);
  return [
    { k: 'Income', v: (inc.length ? '+' : '') + money(inc.reduce((x, t) => x + t.amt, 0)), n: inc.length + ' transactions', c: '#0f6b43' },
    { k: 'Expenses & payments', v: (out.length ? '−' : '') + money(out.reduce((x, t) => x - t.amt, 0)), n: out.length + ' transactions', c: '#b02020' },
    { k: 'Transfers', v: money(xf.reduce((x, t) => x + Math.abs(t.amt), 0)), n: xf.length + ' in or out', c: 'var(--c-primary-text)' },
  ];
});

const defsLine = computed(() => {
  if (!a.value || !a.value.defs.length) return '';
  return ' · default for ' + a.value.defs.map((d: string) => (DEFAULTS.find(x => x[0] === d) || ['', d])[1]).join(', ')
    + (a.value.branch_id ? ' at ' + a.value.branch_name : '');
});

function typeOf(t: any) {
  return (TX_TYPES as any)[t.type] || [String(t.type || '').replace(/^\w/, (c: string) => c.toUpperCase()), 'neutral'];
}

function countOf(name: string) {
  return tx.value.filter(t => inTab(t, name)).length;
}

function tabStyle(on: boolean) {
  return 'padding:9px 14px;font-size:13px;cursor:pointer;border-bottom:2px solid ' + (on ? 'var(--c-primary);color:var(--c-primary);font-weight:600;' : 'transparent;color:#5d6b7d;font-weight:500;');
}

function countStyle(on: boolean) {
  return "font-family:'IBM Plex Mono',monospace;font-size:10.5px;border-radius:4px;padding:1px 5px;margin-left:6px;"
    + (on ? 'background:var(--c-primary-soft);color:var(--c-primary-text);' : 'background:#eef2f6;color:#7d8b9c;');
}

async function load() {
  const id = route.query.id;
  if (typeof id !== 'string') { error.value = 'No account chosen.'; loading.value = false; return; }
  loading.value = true;
  error.value = '';
  try {
    const [acc, list] = await Promise.all([api.get('/payment-accounts/' + encodeURIComponent(id)), api.get('/payment-accounts')]);
    a.value = acc;
    accounts.value = list.data;
    branches.value = list.branches;
    userBranchId.value = list.user_branch_id;
  } catch (e: any) {
    error.value = e.message || 'Could not load the account.';
  }
  loading.value = false;
}

function back() {
  router.push({ name: 'accounts', query: typeof route.query.branch === 'string' ? { branch: route.query.branch } : {} });
}

function onSaved(saved: any) {
  formOpen.value = false;
  message.value = saved.name + ' saved.';
  load();
}

function onTransfer(res: any) {
  transferOpen.value = false;
  message.value = res.ref + ' · ' + money(res.amount) + ' moved from ' + res.from.name + ' to ' + res.to.name + '.';
  load();
}

// The transactions shown, as a CSV file.
function exportCsv() {
  const cell = (v: any) => '"' + String(v == null ? '' : v).replace(/"/g, '""') + '"';
  const rows = [['Date', 'Description', 'Party', 'Reference', 'Type', 'Money in', 'Money out', 'Balance']]
    .concat(ledger.value.map(t => [t.date, t.desc, t.party, t.ref, typeOf(t)[0], t.amt > 0 ? t.amt.toFixed(2) : '', t.amt < 0 ? (-t.amt).toFixed(2) : '', t.runBal.toFixed(2)]));
  const blob = new Blob([rows.map(r => r.map(cell).join(',')).join('\n')], { type: 'text/csv' });
  const link = document.createElement('a');
  link.href = URL.createObjectURL(blob);
  link.download = a.value.name.replace(/[^\w-]+/g, '-') + (a.value.branch_name ? '-' + a.value.branch_name.replace(/[^\w-]+/g, '-') : '') + '.csv';
  link.click();
  URL.revokeObjectURL(link.href);
}

watch(() => route.query.id, () => { if (route.name === 'accounts-details') load(); }, { immediate: true });
</script>
