<template>
  <div @click="emit('close')" style="position:fixed;inset:0;background:rgba(9,22,35,.42);z-index:30"></div>
  <div style="position:fixed;top:0;right:0;bottom:0;width:460px;background:#ffffff;z-index:31;display:flex;flex-direction:column;box-shadow:-20px 0 50px -24px rgba(9,22,35,.6)">
    <div style="padding:18px 22px;border-bottom:1px solid #eaeff5;display:flex;align-items:center;gap:12px">
      <div>
        <div style="font-size:16px;font-weight:700">{{ account ? 'Edit ' + account.name : 'Add payment account' }}</div>
        <div style="font-size:12px;color:#5d6b7d;margin-top:2px">{{ account ? 'The account type cannot change' : 'Cash drawer, bank account, card or wallet' }}</div>
      </div>
      <div @click="emit('close')" :style="closeBtn">×</div>
    </div>

    <div style="flex:1;overflow-y:auto;padding:18px 22px;display:grid;gap:16px;align-content:start">
      <div>
        <label :style="labelStyle">Account type</label>
        <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:6px">
          <div v-for="(k, key) in KIND" :key="key" @click="!account && (form.kind = key)" :style="typeStyle(key)">
            <div :style="'width:28px;height:28px;border-radius:8px;display:grid;place-items:center;font-family:\'IBM Plex Mono\',monospace;font-size:12.5px;font-weight:700;background:' + k.c[0] + ';color:' + k.c[1]">{{ k.icon }}</div>
            <div style="font-size:12px;font-weight:600">{{ k.name }}</div>
          </div>
        </div>
      </div>

      <div>
        <label :style="labelStyle">Branch <span v-if="form.kind === 'cash'" style="color:#d93a3a">*</span></label>
        <select v-model.number="form.branch_id" :disabled="!!userBranchId" :style="inputStyle + 'cursor:pointer;padding:0 8px'">
          <option v-if="form.kind !== 'cash' && !userBranchId" :value="0">All branches (whole business)</option>
          <option v-for="b in branchOptions" :key="b.id" :value="b.id">{{ b.name }}</option>
        </select>
        <div style="font-size:11.5px;color:#7d8b9c;margin-top:5px">{{ form.branch_id ? 'Only ' + branchName + ' uses this account.' : 'Every branch can use this account.' }}</div>
      </div>

      <div>
        <label :style="labelStyle">Account name <span style="color:#d93a3a">*</span></label>
        <input v-model="form.name" :placeholder="NAME_PH[form.kind]" :style="inputStyle" />
      </div>

      <div v-if="form.kind === 'bank'" style="display:grid;grid-template-columns:1fr 1.4fr;gap:12px">
        <div><label :style="labelStyle">BSB</label><input v-model="form.bsb" placeholder="062-000" :style="inputStyle + mono" /></div>
        <div><label :style="labelStyle">Account number</label><input v-model="form.num" placeholder="1234 5678" :style="inputStyle + mono" /></div>
      </div>
      <div v-if="form.kind === 'card'" style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
        <div><label :style="labelStyle">Last 4 digits</label><input v-model="form.num" placeholder="1004" maxlength="4" :style="inputStyle + mono" /></div>
        <div><label :style="labelStyle">Credit limit</label><input v-model="form.limit" placeholder="0.00" :style="inputStyle + mono + 'text-align:right'" /></div>
      </div>

      <div v-if="!account">
        <label :style="labelStyle">Opening balance</label>
        <input v-model="form.opening" placeholder="0.00" :style="inputStyle + mono + 'text-align:right'" />
        <div style="font-size:11.5px;color:#7d8b9c;margin-top:5px">{{ form.kind === 'card' ? 'What is owing on the card now' : 'Recorded as the first transaction' }}</div>
      </div>

      <div>
        <label :style="labelStyle">Use this account by default for {{ form.branch_id ? branchName : 'all branches' }}</label>
        <div style="display:grid;gap:7px">
          <div v-for="d in DEFAULTS" :key="d[0]" @click="toggleDef(d[0])" style="display:flex;align-items:center;gap:10px;cursor:pointer">
            <div :style="checkStyle(form.defs.includes(d[0]))">{{ form.defs.includes(d[0]) ? '✓' : '' }}</div>
            <div style="flex:1">
              <div style="font-size:12.5px">{{ d[1] }}</div>
              <div :style="'font-size:11px;' + (holder(d[0]) && form.defs.includes(d[0]) ? 'color:#8a5300' : 'color:#8b98a8')">{{ defNote(d) }}</div>
            </div>
          </div>
        </div>
      </div>

      <div v-if="account" @click="form.active = !form.active" style="display:flex;align-items:center;gap:10px;cursor:pointer;border-top:1px solid #eef2f6;padding-top:14px">
        <div :style="'width:32px;height:19px;border-radius:99px;position:relative;flex:none;background:' + (form.active ? 'var(--c-primary)' : '#c2cedb')">
          <div :style="'position:absolute;top:2.5px;width:14px;height:14px;border-radius:50%;background:#ffffff;left:' + (form.active ? '15.5px' : '2.5px')"></div>
        </div>
        <div style="flex:1">
          <div style="font-size:12.5px;font-weight:600">{{ form.active ? 'Active' : 'Closed' }}</div>
          <div style="font-size:11.5px;color:#7d8b9c">Closed accounts keep their history but can't take new payments</div>
        </div>
      </div>

      <div v-if="error" style="background:#fdeaea;border:1px solid #f3cfcf;border-radius:10px;padding:10px 13px;font-size:12px;color:#b02020">{{ error }}</div>
    </div>

    <div style="padding:14px 22px;border-top:1px solid #eaeff5;display:flex;gap:8px;justify-content:flex-end">
      <div @click="emit('close')" style="height:34px;padding:0 13px;border-radius:8px;border:1px solid #dfe6ee;display:flex;align-items:center;font-size:12.5px;font-weight:500;color:#4a5866;cursor:pointer;background:#ffffff">Cancel</div>
      <div @click="save" style="height:34px;padding:0 15px;border-radius:8px;background:var(--c-primary);color:#ffffff;display:flex;align-items:center;font-size:12.5px;font-weight:600;cursor:pointer">{{ saving ? 'Saving…' : account ? 'Save changes' : 'Add account' }}</div>
    </div>
  </div>
</template>

<script setup lang="ts">
// Add or edit a payment account. Saves with POST/PUT /api/payment-accounts.
import { computed, reactive, ref, watch } from 'vue';
import { api } from '../../api/http.js';
import { KIND, DEFAULTS } from '../../api/paymentAccounts.js';

const props = defineProps<{
  account: any | null;      // null = a new account
  accounts: any[];          // all accounts, to say which one a default moves from
  branches: any[];
  userBranchId: number | null;
  startBranchId: number;    // the branch picked in the list, for a new account (0 = all)
}>();
const emit = defineEmits<{ (e: 'close'): void; (e: 'saved', account: any): void }>();

const NAME_PH: Record<string, string> = { cash: 'e.g. Cash drawer', bank: 'e.g. CBA Business', card: 'e.g. Amex Business', wallet: 'e.g. PayPal' };
const labelStyle = 'display:block;font-size:12px;font-weight:600;margin-bottom:5px';
const inputStyle = "width:100%;height:36px;border:1px solid #dfe6ee;border-radius:8px;padding:0 11px;font-family:'Public Sans',sans-serif;font-size:13px;outline:none;background:#ffffff;";
const mono = "font-family:'IBM Plex Mono',monospace;";
const closeBtn = 'margin-left:auto;width:32px;height:32px;border-radius:8px;border:1px solid #dfe6ee;display:grid;place-items:center;cursor:pointer;color:#5d6b7d;font-size:15px';

const a = props.account;
const form = reactive({
  kind: a ? a.kind : 'bank',
  branch_id: a ? (a.branch_id || 0) : (props.userBranchId || props.startBranchId || 0),
  name: a ? a.name : '',
  bsb: a ? a.bsb || '' : '',
  num: a ? a.num || '' : '',
  limit: a && a.limit ? String(a.limit) : '',
  opening: '',
  defs: (a ? (a.defs || []).slice() : []) as string[],
  active: a ? a.active : true,
});
const saving = ref(false);
const error = ref('');

const branchOptions = computed(() => props.userBranchId ? props.branches.filter(b => b.id === props.userBranchId) : props.branches);
const branchName = computed(() => (props.branches.find(b => b.id === form.branch_id) || { name: '' }).name);

// A cash drawer always belongs to a branch.
watch(() => form.kind, (k) => {
  if (k === 'cash' && !form.branch_id && props.branches.length) form.branch_id = props.branches[0].id;
});

function typeStyle(key: string) {
  const on = form.kind === key;
  return 'border-radius:10px;padding:10px 6px;display:flex;flex-direction:column;align-items:center;gap:6px;cursor:' + (props.account ? 'default' : 'pointer') + ';'
    + (on ? 'background:#f4f9ff;box-shadow:inset 0 0 0 1.5px var(--c-primary);' : 'border:1px solid #dfe6ee;') + (props.account && !on ? 'opacity:.45;' : '');
}

function checkStyle(on: boolean) {
  return 'width:18px;height:18px;border-radius:5px;display:grid;place-items:center;font-size:11px;font-weight:700;flex:none;'
    + (on ? 'background:var(--c-primary);color:#ffffff;' : 'box-shadow:inset 0 0 0 1.5px #c2cedb;color:transparent;');
}

function toggleDef(d: string) {
  form.defs = form.defs.includes(d) ? form.defs.filter(x => x !== d) : form.defs.concat([d]);
}

// The other account at the same branch that has this default now.
function holder(d: string) {
  const branch = form.branch_id || null;
  return props.accounts.find(x => (x.defs || []).includes(d) && (x.branch_id || null) === branch && (!props.account || x.id !== props.account.id));
}

function defNote(d: string[]) {
  const h = holder(d[0]);
  if (h && form.defs.includes(d[0])) return 'Replaces ' + h.name + ' as the default';
  return d[2];
}

async function save() {
  if (saving.value) return;
  if (!form.name.trim()) { error.value = 'Give the account a name.'; return; }
  if (form.kind === 'bank' && form.bsb && !/^\d{3}-?\d{3}$/.test(form.bsb.trim())) { error.value = 'BSB should be six digits, e.g. 062-118.'; return; }
  if (form.kind === 'cash' && !form.branch_id) { error.value = 'A cash drawer belongs to a branch. Choose the branch.'; return; }
  saving.value = true;
  error.value = '';
  const body: any = {
    name: form.name.trim(), branch_id: form.branch_id || null, num: form.num.trim(), bsb: form.bsb.trim(),
    limit: parseFloat(form.limit) || 0, defs: form.defs,
  };
  try {
    let saved;
    if (props.account) {
      saved = await api.put('/payment-accounts/' + props.account.id, { ...body, active: form.active });
    } else {
      saved = await api.post('/payment-accounts', { ...body, kind: form.kind, opening: parseFloat(form.opening) || 0 });
    }
    emit('saved', saved);
  } catch (e: any) {
    error.value = e.message || 'Could not save the account.';
  }
  saving.value = false;
}
</script>
