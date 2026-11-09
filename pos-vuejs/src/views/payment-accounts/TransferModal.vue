<template>
  <div @click="emit('close')" style="position:fixed;inset:0;background:rgba(9,22,35,.48);z-index:30"></div>
  <div style="position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);width:540px;background:#ffffff;border-radius:16px;z-index:31;box-shadow:0 30px 80px -24px rgba(9,22,35,.65);padding:22px">
    <div style="display:flex;align-items:center;gap:12px;margin-bottom:16px">
      <div>
        <div style="font-size:17px;font-weight:700">Transfer funds</div>
        <div style="font-size:12px;color:#5d6b7d;margin-top:2px">Moves money between your own accounts, e.g. banking a branch's cash. Not income or expense.</div>
      </div>
      <div @click="emit('close')" style="margin-left:auto;width:32px;height:32px;border-radius:8px;border:1px solid #dfe6ee;display:grid;place-items:center;cursor:pointer;color:#5d6b7d;font-size:15px;flex:none">×</div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 34px 1fr;gap:8px;align-items:end">
      <div><label :style="labelStyle">From</label>
        <select v-model="from" :style="selectStyle">
          <optgroup v-for="g in groups" :key="g.name" :label="g.name">
            <option v-for="o in g.accounts" :key="o.id" :value="o.id">{{ o.name }}</option>
          </optgroup>
        </select>
      </div>
      <div @click="swap" title="Swap" style="height:36px;border-radius:8px;border:1px solid #dfe6ee;display:grid;place-items:center;cursor:pointer;color:#5d6b7d;font-size:14px">⇆</div>
      <div><label :style="labelStyle">To</label>
        <select v-model="to" :style="selectStyle">
          <optgroup v-for="g in groups" :key="g.name" :label="g.name">
            <option v-for="o in g.accounts" :key="o.id" :value="o.id">{{ o.name }}</option>
          </optgroup>
        </select>
      </div>
    </div>

    <div v-if="fromAcc && toAcc" style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-top:10px">
      <div v-for="p in preview" :key="p.k" :style="p.box">
        <div style="font-size:11px;font-weight:600">{{ p.k }}</div>
        <div style="font-family:'IBM Plex Mono',monospace;font-size:12.5px;margin-top:3px">{{ p.now }} <span style="opacity:.6">→</span> <b>{{ p.after }}</b></div>
      </div>
    </div>

    <div style="margin-top:14px"><label :style="labelStyle">Amount</label>
      <input v-model="amount" placeholder="0.00" style="width:100%;height:42px;border:1.5px solid var(--c-primary);border-radius:9px;padding:0 12px;font-family:'IBM Plex Mono',monospace;font-size:17px;font-weight:600;outline:none;text-align:right;background:var(--c-primary-tint)" />
    </div>
    <div style="display:flex;gap:6px;margin-top:8px;flex-wrap:wrap">
      <div v-for="q in quick" :key="q[0]" @click="amount = String(q[1])" style="height:28px;padding:0 10px;border-radius:7px;border:1px solid #dfe6ee;display:flex;align-items:center;font-size:12px;color:#4a5866;cursor:pointer">{{ q[0] }}</div>
    </div>
    <div style="margin-top:12px"><label :style="labelStyle">Note</label>
      <input v-model="note" placeholder="e.g. Banking Friday takings" style="width:100%;height:36px;border:1px solid #dfe6ee;border-radius:8px;padding:0 11px;font-family:'Public Sans',sans-serif;font-size:13px;outline:none" />
    </div>

    <div v-if="error" style="margin-top:12px;background:#fdeaea;border:1px solid #f3cfcf;border-radius:10px;padding:10px 13px;font-size:12px;color:#b02020">{{ error }}</div>
    <div style="display:flex;gap:9px;justify-content:flex-end;margin-top:18px">
      <div @click="emit('close')" style="height:40px;padding:0 16px;border-radius:9px;border:1px solid #dfe6ee;display:flex;align-items:center;font-size:13px;font-weight:600;color:#4a5866;cursor:pointer">Cancel</div>
      <div @click="send" :style="'height:40px;padding:0 16px;border-radius:9px;color:#ffffff;display:flex;align-items:center;font-size:13px;font-weight:700;cursor:pointer;background:' + (problem ? '#9fbde0' : 'var(--c-primary)')">{{ saving ? 'Moving…' : amt > 0 ? 'Transfer ' + money(amt) : 'Transfer' }}</div>
    </div>
  </div>
</template>

<script setup lang="ts">
// Transfer funds between two open accounts: POST /api/payment-accounts/transfer.
import { computed, ref } from 'vue';
import { api } from '../../api/http.js';
import { money, signed } from '../../api/paymentAccounts.js';

const props = defineProps<{ accounts: any[]; startFrom?: string }>();
const emit = defineEmits<{ (e: 'close'): void; (e: 'done', result: any): void }>();

const labelStyle = 'display:block;font-size:12px;font-weight:600;margin-bottom:5px';
const selectStyle = "width:100%;height:36px;border:1px solid #dfe6ee;border-radius:8px;font-family:'Public Sans',sans-serif;font-size:13px;outline:none;background:#ffffff;cursor:pointer;padding:0 8px";

const open = computed(() => props.accounts.filter(a => a.active));

// Grouped by branch in the pickers, so two "Cash drawer" accounts are easy to tell apart.
const groups = computed(() => {
  const list: { name: string; accounts: any[] }[] = [];
  open.value.forEach(a => {
    const name = a.branch_name || 'All branches';
    let g = list.find(x => x.name === name);
    if (!g) { g = { name, accounts: [] }; list.push(g); }
    g.accounts.push(a);
  });
  return list;
});

// Start from the account given (or a cash drawer), going to a bank account: the usual "bank the takings".
const first = open.value.find(a => a.id === props.startFrom) || open.value.find(a => a.kind === 'cash') || open.value[0];
const from = ref(first ? first.id : '');
const firstTo = open.value.find(a => a.id !== from.value && a.kind === 'bank') || open.value.find(a => a.id !== from.value);
const to = ref(firstTo ? firstTo.id : '');
const amount = ref('');
const note = ref('');
const saving = ref(false);
const error = ref('');

const fromAcc = computed(() => open.value.find(a => a.id === from.value));
const toAcc = computed(() => open.value.find(a => a.id === to.value));
const amt = computed(() => Math.round((parseFloat(amount.value) || 0) * 100) / 100);

function swap() {
  const f = from.value;
  from.value = to.value;
  to.value = f;
}

// The same checks the server makes.
const problem = computed(() => {
  const f = fromAcc.value, t = toAcc.value, x = amt.value;
  if (!f || !t) return 'Pick two accounts';
  if (f.id === t.id) return 'Pick two different accounts';
  if (!(x > 0)) return 'Enter an amount';
  if (f.kind !== 'card' && x > f.bal + 0.001) return f.name + ' only has ' + money(f.bal);
  if (f.kind === 'card' && f.limit && -f.bal + x > f.limit + 0.001) return 'That would go over the ' + money(f.limit) + ' card limit';
  if (t.kind === 'card' && x > -t.bal + 0.001) return 'That is more than the ' + money(-t.bal) + ' owing on ' + t.name;
  return '';
});

const preview = computed(() => [[fromAcc.value, -amt.value, 'From · '], [toAcc.value, amt.value, 'To · ']].map((p: any[]) => {
  const card = p[0].kind === 'card';
  const after = Math.round((p[0].bal + p[1]) * 100) / 100;
  const bad = !card && after < -0.001;
  return {
    k: p[2] + p[0].name + (p[0].branch_name ? ' · ' + p[0].branch_name : '') + (card ? ' (owing)' : ''),
    now: card ? money(-p[0].bal) : signed(p[0].bal),
    after: card ? money(-after) : signed(after),
    box: 'border-radius:10px;padding:9px 12px;' + (bad ? 'background:#fdeaea;color:#b02020;' : p[1] < 0 ? 'background:#f6f9fc;color:#4a5866;' : 'background:#e7f6ee;color:#0f6b43;'),
  };
}));

const quick = computed(() => {
  const f = fromAcc.value, t = toAcc.value, list: [string, number][] = [];
  if (f && f.kind === 'cash' && f.bal > 0) {
    list.push(['Bank it all · ' + money(f.bal), f.bal]);
    if (f.bal > 300) list.push(['Keep 300 float', Math.round((f.bal - 300) * 100) / 100]);
  }
  if (t && t.kind === 'card' && t.bal < 0) list.push(['Pay off card · ' + money(-t.bal), -t.bal]);
  return list;
});

async function send() {
  if (saving.value) return;
  if (problem.value) { error.value = problem.value; return; }
  saving.value = true;
  error.value = '';
  try {
    const res = await api.post('/payment-accounts/transfer', { from: from.value, to: to.value, amount: amt.value, note: note.value.trim() || null });
    emit('done', res);
  } catch (e: any) {
    error.value = e.message || 'Could not move the money.';
  }
  saving.value = false;
}
</script>
