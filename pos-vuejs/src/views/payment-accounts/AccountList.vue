<template>
  <div>
    <div style="padding:20px 24px 18px;background:#ffffff;border-bottom:1px solid #dfe6ee">
      <div style="display:flex;align-items:center;gap:7px;font-size:11.5px;color:#7d8b9c;margin-bottom:8px"><span>Payment accounts</span><span style="color:#c2cedb">/</span><span style="color:#0f1c2b;font-weight:500">{{ scopeName }}</span></div>
      <div style="display:flex;align-items:flex-start;gap:20px;flex-wrap:wrap">
        <div>
          <h2 style="margin:0 0 3px;font-size:22px;font-weight:700;letter-spacing:-0.018em">Payment accounts</h2>
          <div style="font-size:12.5px;color:#5d6b7d">{{ subLine }}</div>
        </div>
        <div style="margin-left:auto;display:flex;gap:8px;align-items:center">
          <select v-if="!userBranchId" v-model="scope" @change="onScope" title="Branch" style="height:34px;border:1px solid #dfe6ee;border-radius:8px;background:#ffffff;padding:0 8px;font-family:'Public Sans',sans-serif;font-size:12.5px;color:#0f1c2b;font-weight:600;cursor:pointer">
            <option value="">All branches</option>
            <option v-for="b in branches" :key="b.id" :value="String(b.id)">{{ b.name }}</option>
            <option value="0">Whole business only</option>
          </select>
          <div v-if="canEdit" @click="transferOpen = true" style="height:34px;padding:0 13px;border-radius:8px;border:1px solid #dfe6ee;display:flex;align-items:center;font-size:12.5px;cursor:pointer;background:#ffffff;font-weight:600;color:var(--c-primary-text)" v-pseudo:hover="'background:#f2f6fa'">Transfer funds</div>
          <div v-if="canCreate" @click="formOpen = true" style="height:34px;padding:0 15px;border-radius:8px;background:var(--c-primary);color:#ffffff;display:flex;align-items:center;font-size:12.5px;font-weight:600;cursor:pointer" v-pseudo:hover="'background:var(--c-primary-hover)'">+ Add account</div>
        </div>
      </div>
    </div>

    <div style="padding:20px 24px 32px">
      <div v-if="message" style="background:#e7f6ee;color:#0f6b43;border-radius:10px;padding:10px 14px;font-size:12.5px;margin-bottom:12px">{{ message }}</div>
      <div v-if="error" style="background:#fdeaea;color:#b02020;border-radius:10px;padding:10px 14px;font-size:12.5px;margin-bottom:12px">{{ error }}</div>

      <div style="display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;margin-bottom:16px">
        <div v-for="t in totals" :key="t.k" style="background:#ffffff;border:1px solid #dfe6ee;border-radius:12px;padding:15px 17px">
          <div style="font-size:11.5px;color:#7d8b9c">{{ t.k }}</div>
          <div :style="'font-family:\'IBM Plex Mono\',monospace;font-size:22px;font-weight:600;letter-spacing:-0.02em;margin-top:3px;' + t.c">{{ t.v }}</div>
          <div style="font-size:11.5px;color:#8b98a8;margin-top:2px">{{ t.sub }}</div>
        </div>
      </div>

      <div style="display:flex;align-items:center;gap:10px;margin-bottom:12px">
        <div style="display:flex;gap:3px;background:#e4eaf1;padding:3px;border-radius:9px">
          <div v-for="k in KIND_TABS" :key="k" @click="kind = k" :style="kindTab(kind === k)">{{ k }}</div>
        </div>
        <div @click="showClosed = !showClosed" style="margin-left:auto;display:flex;align-items:center;gap:8px;cursor:pointer">
          <div :style="'width:32px;height:19px;border-radius:99px;position:relative;flex:none;background:' + (showClosed ? 'var(--c-primary)' : '#c2cedb')">
            <div :style="'position:absolute;top:2.5px;width:14px;height:14px;border-radius:50%;background:#ffffff;left:' + (showClosed ? '15.5px' : '2.5px')"></div>
          </div>
          <div style="font-size:12.5px;color:#4a5866">Show closed accounts</div>
        </div>
      </div>

      <div v-if="loading" style="padding:40px;text-align:center;font-size:13px;color:#7d8b9c">Loading…</div>
      <div v-else-if="!groups.length" style="padding:40px;text-align:center;font-size:13px;color:#7d8b9c">No accounts here yet.</div>

      <!-- One section per branch, then the whole-business accounts -->
      <div v-for="g in groups" :key="g.key" style="margin-bottom:20px">
        <div style="display:flex;align-items:baseline;gap:10px;margin-bottom:8px">
          <div style="font-size:14px;font-weight:700">{{ g.name }}</div>
          <div style="font-size:12px;color:#7d8b9c">{{ g.accounts.length }} {{ g.accounts.length === 1 ? 'account' : 'accounts' }} · cash &amp; bank <b style="font-family:'IBM Plex Mono',monospace;color:#0f1c2b">{{ money(g.cash) }}</b></div>
        </div>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:12px">
          <div v-for="c in g.accounts" :key="c.id" @click="open(c)" :style="cardStyle(c)" v-pseudo:hover="'border-color:var(--c-primary);box-shadow:0 8px 20px -14px rgba(22,104,201,.5)'">
            <div style="display:flex;align-items:flex-start;gap:11px">
              <div :style="iconStyle(c.kind)">{{ KIND[c.kind].icon }}</div>
              <div style="flex:1;min-width:0">
                <div style="display:flex;align-items:center;gap:7px">
                  <div style="font-size:14px;font-weight:700;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">{{ c.name }}</div>
                  <span v-if="!c.active" :style="badge(TONE.neutral)">Closed</span>
                  <span v-else-if="c.defs.length" :style="badge(TONE.info)" :title="defsText(c)">Default</span>
                </div>
                <div style="font-size:11.5px;color:#7d8b9c;margin-top:1px">{{ accountSub(c) }}</div>
              </div>
            </div>
            <div style="margin-top:16px">
              <div style="font-size:11px;color:#7d8b9c">{{ c.kind === 'card' ? 'Owing' : 'Balance' }}</div>
              <div :style="'font-family:\'IBM Plex Mono\',monospace;font-size:24px;font-weight:600;letter-spacing:-0.025em;margin-top:1px;' + (c.kind === 'card' && c.bal < 0 ? 'color:#b02020;' : c.bal < 0 ? 'color:#b02020;' : '')">{{ c.kind === 'card' ? money(-c.bal) : signed(c.bal) }}</div>
            </div>
            <div v-if="c.kind === 'card' && c.limit > 0" style="margin-top:8px">
              <div style="height:5px;border-radius:99px;background:#eef2f6;overflow:hidden">
                <div :style="'height:100%;border-radius:99px;width:' + used(c) + '%;background:' + (used(c) > 80 ? '#d93a3a' : '#7a4fc9')"></div>
              </div>
              <div style="font-size:11px;color:#7d8b9c;margin-top:4px">{{ money(c.limit + c.bal) }} available of {{ money(c.limit) }}</div>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:14px;padding-top:12px;border-top:1px solid #eef2f6">
              <div>
                <div style="font-size:10.5px;color:#7d8b9c">Money in</div>
                <div style="font-family:'IBM Plex Mono',monospace;font-size:12.5px;font-weight:600;color:#0f6b43">{{ c.money_in ? "+" : "" }}{{ money(c.money_in) }}</div>
              </div>
              <div>
                <div style="font-size:10.5px;color:#7d8b9c">Money out</div>
                <div style="font-family:'IBM Plex Mono',monospace;font-size:12.5px;font-weight:600;color:#b02020">{{ c.money_out ? "−" : "" }}{{ money(c.money_out) }}</div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <AccountForm v-if="formOpen" :account="null" :accounts="accounts" :branches="branches" :user-branch-id="userBranchId" :start-branch-id="Number(scope) || 0" @close="formOpen = false" @saved="onSaved" />
    <TransferModal v-if="transferOpen" :accounts="accounts" @close="transferOpen = false" @done="onTransfer" />
  </div>
</template>

<script setup lang="ts">
// Payment accounts, grouped by branch. API: GET /api/payment-accounts?branch_id=
import { computed, onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { api } from '../../api/http.js';
import { can } from '../../api/auth.js';
import { KIND, DEFAULTS, TONE, money, signed, accountSub, iconStyle, badge } from '../../api/paymentAccounts.js';
import AccountForm from './AccountForm.vue';
import TransferModal from './TransferModal.vue';

const route = useRoute();
const router = useRouter();

const KIND_TABS = ['All', 'Cash', 'Bank', 'Card', 'Wallet'];
const canCreate = can('accounts.create');
const canEdit = can('accounts.edit');

// '' = all branches, '0' = whole business only, else a branch id. Kept in the URL (?branch=).
const scope = ref(typeof route.query.branch === 'string' ? route.query.branch : '');
const kind = ref('All');
const showClosed = ref(false);
const accounts = ref<any[]>([]);
const branches = ref<any[]>([]);
const userBranchId = ref<number | null>(null);
const loading = ref(true);
const error = ref('');
const message = ref('');
const formOpen = ref(false);
const transferOpen = ref(false);

const scopeName = computed(() => {
  if (userBranchId.value) return (branches.value.find(b => b.id === userBranchId.value) || { name: 'Your branch' }).name;
  if (scope.value === '') return 'All branches';
  if (scope.value === '0') return 'Whole business';
  return (branches.value.find(b => String(b.id) === scope.value) || { name: '' }).name;
});

const shown = computed(() => accounts.value.filter(a => (showClosed.value || a.active) && (kind.value === 'All' || KIND[a.kind].name === kind.value)));

// Sections: each branch in name order, then "All branches" for the whole-business accounts.
const groups = computed(() => {
  const list: any[] = [];
  const add = (key: string, name: string, accts: any[]) => {
    if (!accts.length) return;
    const cash = accts.filter(a => a.active && a.kind !== 'card').reduce((t, a) => t + a.bal, 0);
    list.push({ key, name, accounts: accts, cash: Math.round(cash * 100) / 100 });
  };
  branches.value.forEach(b => add('b' + b.id, b.name, shown.value.filter(a => a.branch_id === b.id)));
  // A branch that was switched off still shows its accounts.
  shown.value.filter(a => a.branch_id && !branches.value.some(b => b.id === a.branch_id)).forEach(a => {
    if (!list.some(g => g.key === 'b' + a.branch_id)) add('b' + a.branch_id, a.branch_name || 'Other branch', shown.value.filter(x => x.branch_id === a.branch_id));
  });
  add('all', 'All branches · whole business', shown.value.filter(a => !a.branch_id));
  return list;
});

const totals = computed(() => {
  const act = accounts.value.filter(a => a.active);
  const cashBank = act.filter(a => a.kind !== 'card').reduce((t, a) => t + a.bal, 0);
  const owed = act.filter(a => a.kind === 'card').reduce((t, a) => t - a.bal, 0);
  const net = cashBank - owed;
  const drawers = act.filter(a => a.kind === 'cash');
  return [
    { k: 'Cash & bank', v: money(cashBank), sub: act.filter(a => a.kind !== 'card').length + ' accounts · ' + money(drawers.reduce((t, a) => t + a.bal, 0)) + ' in cash drawers', c: '' },
    { k: 'Owed on cards', v: money(owed), sub: act.filter(a => a.kind === 'card').length + ' cards', c: owed > 0 ? 'color:#b02020;' : '' },
    { k: 'Net position', v: signed(net), sub: 'Cash and bank less card balances', c: net < 0 ? 'color:#b02020;' : 'color:#0f6b43;' },
  ];
});

const subLine = computed(() => {
  const n = accounts.value.filter(a => a.active).length;
  const base = n + ' active ' + (n === 1 ? 'account' : 'accounts');
  if (userBranchId.value || (scope.value && scope.value !== '0')) return base + ' · this branch\'s own accounts and the whole-business ones';
  return base;
});

function kindTab(on: boolean) {
  return 'height:30px;padding:0 13px;border-radius:7px;display:grid;place-items:center;font-size:12.5px;cursor:pointer;'
    + (on ? 'background:#ffffff;color:#0f1c2b;font-weight:700;box-shadow:0 1px 3px rgba(15,28,43,.14);' : 'color:#5d6b7d;font-weight:500;');
}

function cardStyle(c: any) {
  return 'background:#ffffff;border:1px solid #dfe6ee;border-radius:13px;padding:16px 17px;cursor:pointer;transition:border-color .12s,box-shadow .12s;' + (c.active ? '' : 'opacity:.6;');
}

function used(c: any) {
  return Math.max(0, Math.min(100, -c.bal / c.limit * 100));
}

function defsText(c: any) {
  return 'Default for ' + c.defs.map((d: string) => (DEFAULTS.find(x => x[0] === d) || ['', d])[1]).join(', ');
}

async function load() {
  loading.value = true;
  error.value = '';
  try {
    const res = await api.get('/payment-accounts' + (scope.value !== '' ? '?branch_id=' + scope.value : ''));
    accounts.value = res.data;
    branches.value = res.branches;
    userBranchId.value = res.user_branch_id;
  } catch (e: any) {
    error.value = e.message || 'Could not load the accounts.';
  }
  loading.value = false;
}

function onScope() {
  router.replace({ query: scope.value ? { branch: scope.value } : {} });
  load();
}

function open(c: any) {
  router.push({ name: 'accounts-details', query: { id: c.id, ...(scope.value ? { branch: scope.value } : {}) } });
}

function onSaved(a: any) {
  formOpen.value = false;
  message.value = a.name + ' added' + (a.branch_name ? ' to ' + a.branch_name : ' for all branches') + '.';
  load();
}

function onTransfer(res: any) {
  transferOpen.value = false;
  message.value = res.ref + ' · ' + money(res.amount) + ' moved from ' + res.from.name + ' to ' + res.to.name + '.';
  load();
}

onMounted(load);
</script>
