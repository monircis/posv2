<template>
  <template v-if="vm.hr_isTips">
    <div style="display:grid;gap:14px">
      <!-- Filters -->
      <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
        <div style="display:flex;border:1px solid #dfe6ee;border-radius:9px;overflow:hidden;background:#ffffff">
          <div v-for="t in STATUS_TABS" :key="t.key" @click="setStatus(t.key)" :style="segStyle(status === t.key)">{{ t.name }}</div>
        </div>
        <label style="font-size:12px;color:#5d6b7d;display:flex;align-items:center;gap:6px">From <input type="date" v-model="from" @change="load" style="height:34px;border:1px solid #dfe6ee;border-radius:8px;padding:0 8px;font-family:inherit;font-size:12.5px" /></label>
        <label style="font-size:12px;color:#5d6b7d;display:flex;align-items:center;gap:6px">To <input type="date" v-model="to" @change="load" style="height:34px;border:1px solid #dfe6ee;border-radius:8px;padding:0 8px;font-family:inherit;font-size:12.5px" /></label>
        <div v-if="from || to" @click="clearDates" style="font-size:12px;color:var(--c-primary);cursor:pointer">Clear dates</div>
        <div style="margin-left:auto;font-size:12.5px;color:#5d6b7d">{{ rows.length }} tips · <b style="color:#0f1c2b">${{ money(total) }}</b></div>
      </div>

      <div v-if="error" style="background:#fdeaea;color:#b02020;border-radius:10px;padding:10px 14px;font-size:12.5px">{{ error }}</div>
      <div v-if="message" style="background:#e7f6ee;color:#0f6b43;border-radius:10px;padding:10px 14px;font-size:12.5px">{{ message }}</div>

      <div style="display:grid;grid-template-columns:minmax(0,1fr) 360px;gap:14px;align-items:start">
        <!-- Every tip -->
        <div style="background:#ffffff;border:1px solid #dfe6ee;border-radius:12px;overflow:hidden">
          <div style="display:grid;grid-template-columns:1.1fr 0.9fr 1.3fr 1fr 0.6fr 0.8fr 1.1fr;gap:10px;padding:10px 16px;border-bottom:1px solid #eaeff5;font-size:11px;font-weight:600;color:#7d8b9c;text-transform:uppercase;letter-spacing:.04em">
            <div>Date</div><div>Sale</div><div>Staff</div><div>Branch</div><div>Paid by</div><div style="text-align:right">Tip</div><div>Status</div>
          </div>
          <div v-if="loading" style="padding:30px;text-align:center;font-size:13px;color:#7d8b9c">Loading…</div>
          <div v-else-if="!rows.length" style="padding:30px;text-align:center;font-size:13px;color:#7d8b9c">No tips here.</div>
          <div v-for="r in rows" :key="r.id" style="display:grid;grid-template-columns:1.1fr 0.9fr 1.3fr 1fr 0.6fr 0.8fr 1.1fr;gap:10px;padding:10px 16px;border-bottom:1px solid #f0f4f8;align-items:center;font-size:12.5px">
            <div style="font-family:'IBM Plex Mono',monospace;font-size:12px;color:#4a5866">{{ dmy(r.date) }} {{ r.date.slice(11, 16) }}</div>
            <div style="font-family:'IBM Plex Mono',monospace;font-size:12px">{{ r.sale_ref }}</div>
            <div style="font-weight:600">{{ r.employee }}</div>
            <div style="color:#5d6b7d">{{ r.branch }}</div>
            <div style="color:#5d6b7d;text-transform:capitalize">{{ r.method }}</div>
            <div style="font-family:'IBM Plex Mono',monospace;font-weight:600;text-align:right">${{ money(r.amount) }}</div>
            <div>
              <span v-if="r.paid_at" style="background:#e7f6ee;color:#0f6b43;border-radius:5px;padding:2px 8px;font-size:11px;font-weight:600" :title="'Paid by ' + r.paid_by">Paid {{ dmy(r.paid_at) }}</span>
              <span v-else style="background:#fdf1dd;color:#8a5300;border-radius:5px;padding:2px 8px;font-size:11px;font-weight:600">To pay</span>
            </div>
          </div>
        </div>

        <!-- Per staff member, with the Pay button -->
        <div style="background:#ffffff;border:1px solid #dfe6ee;border-radius:12px;overflow:hidden">
          <div style="padding:12px 16px;border-bottom:1px solid #eaeff5">
            <div style="font-size:13.5px;font-weight:700">By staff</div>
            <div style="font-size:11.5px;color:#7d8b9c;margin-top:2px">For the tips shown on the left</div>
          </div>
          <div v-if="canPay && staff.some(x => x.unpaid > 0)" style="padding:10px 16px;border-bottom:1px solid #eaeff5;display:flex;align-items:center;gap:8px">
            <div style="font-size:12px;color:#5d6b7d;flex:none">Pay from</div>
            <select v-model="accountId" style="flex:1;min-width:0;height:32px;border:1px solid #dfe6ee;border-radius:8px;padding:0 6px;font-family:inherit;font-size:12.5px">
              <option v-for="a in accounts" :key="a.id" :value="a.id">{{ a.name }} ({{ a.kind }})</option>
            </select>
          </div>
          <div v-if="!staff.length" style="padding:20px 16px;font-size:12.5px;color:#7d8b9c">Nobody yet.</div>
          <div v-for="m in staff" :key="m.employee_id" style="display:flex;align-items:center;gap:10px;padding:11px 16px;border-bottom:1px solid #f0f4f8">
            <div style="flex:1;min-width:0">
              <div style="font-size:13px;font-weight:600">{{ m.employee }}</div>
              <div style="font-size:11.5px;color:#7d8b9c">{{ m.count }} tips · ${{ money(m.total) }}</div>
            </div>
            <div v-if="m.unpaid > 0 && canPay" @click="pay(m)" :style="payBtnStyle">Pay ${{ money(m.unpaid) }}</div>
            <div v-else-if="m.unpaid > 0" style="font-family:'IBM Plex Mono',monospace;font-size:12.5px;font-weight:600;color:#8a5300">${{ money(m.unpaid) }} to pay</div>
            <div v-else style="font-size:12px;color:#0f6b43;font-weight:600">All paid</div>
          </div>
        </div>
      </div>
    </div>
  </template>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { api } from '../../api/http.js';
import { can } from '../../api/auth.js';
import { dmy } from '../../api/purchases.js';
import { money } from '../../api/products.js';
import { usePageVm } from '../../erp/useErpPage';

const vm = usePageVm();

const STATUS_TABS = [
  { key: 'unpaid', name: 'To pay' },
  { key: 'paid', name: 'Paid' },
  { key: 'all', name: 'All' },
];

const status = ref('unpaid');
const from = ref('');
const to = ref('');
const rows = ref<any[]>([]);
const staff = ref<any[]>([]);
const accounts = ref<any[]>([]);
const accountId = ref('');
const total = ref(0);
const loading = ref(false);
const saving = ref(false);
const error = ref('');
const message = ref('');
const canPay = can('hrm.edit');

const payBtnStyle = 'height:32px;padding:0 12px;border-radius:8px;background:#2f9e63;color:#ffffff;display:flex;align-items:center;font-size:12.5px;font-weight:600;cursor:pointer;flex:none;white-space:nowrap';

function segStyle(on: boolean) {
  return 'height:34px;padding:0 14px;display:flex;align-items:center;font-size:12.5px;font-weight:600;cursor:pointer;user-select:none;'
    + (on ? 'background:var(--c-primary);color:#ffffff;' : 'color:#5d6b7d;');
}

async function load() {
  loading.value = true;
  error.value = '';
  try {
    let url = '/tips?status=' + status.value;
    if (from.value) url += '&from=' + from.value;
    if (to.value) url += '&to=' + to.value;
    const res = await api.get(url);
    rows.value = res.data;
    staff.value = res.staff;
    total.value = res.total;
    accounts.value = res.accounts;
    // The cash drawer is the usual place tips are paid from.
    if (!accounts.value.some(a => a.id === accountId.value)) {
      const cash = accounts.value.find(a => a.kind === 'cash');
      accountId.value = cash ? cash.id : (accounts.value[0] ? accounts.value[0].id : '');
    }
  } catch (e: any) {
    error.value = e.message || 'Could not load the tips.';
  }
  loading.value = false;
}

function setStatus(key: string) {
  status.value = key;
  load();
}

function clearDates() {
  from.value = '';
  to.value = '';
  load();
}

async function pay(m: any) {
  if (saving.value) return;
  const account = accounts.value.find(a => a.id === accountId.value);
  if (!account) {
    error.value = 'Choose the account to pay from.';
    return;
  }
  if (!window.confirm('Pay ' + m.employee + ' $' + money(m.unpaid) + ' in tips from ' + account.name + '?')) return;

  saving.value = true;
  error.value = '';
  message.value = '';
  try {
    const res = await api.post('/tips/pay', { employee_id: m.employee_id, tip_ids: m.unpaid_ids, payment_account_id: accountId.value });
    message.value = 'Paid ' + res.employee + ' $' + money(res.total) + ' (' + res.count + ' tips) from ' + res.account + '.';
    await load();
  } catch (e: any) {
    error.value = e.message || 'Could not pay the tips.';
  }
  saving.value = false;
}

onMounted(load);
</script>
