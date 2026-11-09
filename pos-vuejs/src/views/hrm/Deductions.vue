<template>
  <div style="display:grid;gap:14px">
    <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
      <input type="month" v-model="month" @change="load" :style="ui.input" />
      <div :style="ui.segWrap">
        <div v-for="s in ['', 'pending', 'approved', 'rejected']" :key="s" @click="status = s; load()" :style="ui.seg(status === s)">{{ s ? label(s) : 'All' }}</div>
      </div>
      <div style="margin-left:auto;display:flex;gap:8px">
        <div @click="calculate" :style="ui.btn">Calculate {{ monthName(month) }}</div>
        <div @click="form = { employee_id: '', month, amount: '', note: '' }" :style="ui.btnMain">+ Add by hand</div>
      </div>
    </div>
    <div v-if="message" :style="ui.ok">{{ message }}</div>
    <div v-if="error" :style="ui.err">{{ error }}</div>
    <div :style="ui.muted">Calculate goes through the finished days of the month (not today, not days with a fix waiting) using the deduction policies. Running it again never adds the same deduction twice. Only approved deductions go into payroll.</div>

    <div :style="ui.card">
      <div :style="ui.head + ';display:grid;' + grid"><div>Ref</div><div>Employee</div><div>Kind</div><div>Day</div><div>How much</div><div>Amount</div><div>Note</div><div>Status</div><div></div></div>
      <div v-for="d in rows" :key="d.id" :style="ui.row + ';display:grid;' + grid">
        <div :style="ui.mono">{{ d.ref }}</div>
        <div>{{ d.employee?.first }} {{ d.employee?.last }}</div>
        <div>{{ label(d.type) }}</div>
        <div>{{ dmy(d.date) || d.month }}</div>
        <div :style="ui.mono">{{ ['late', 'early_leave'].includes(d.type) ? d.quantity + ' min' : d.quantity ? d.quantity + ' day' : '—' }}</div>
        <div :style="ui.mono">{{ money(d.amount) }}</div>
        <div>{{ d.note }}</div>
        <div><span :style="badge(d.status)">{{ d.status }}</span><span v-if="d.pay_run_id" :style="ui.muted"> · paid</span></div>
        <div><div v-if="d.status === 'pending'" @click="remove(d)" :style="ui.btnSmall">Remove</div></div>
      </div>
      <div v-if="!rows.length" style="padding:30px;text-align:center;color:#7d8b9c;font-size:13px">No deductions for {{ monthName(month) }}.</div>
    </div>

    <!-- Exceptions -->
    <div :style="ui.card">
      <div :style="ui.pad + ';display:flex;gap:8px;align-items:center;flex-wrap:wrap'">
        <div :style="ui.title">Not deducted (exceptions)</div>
        <select v-model="ex.employee_id" :style="ui.input + ';margin-left:auto'"><option value="">Employee…</option><option v-for="s in staff" :key="s.id" :value="s.id">{{ s.first }} {{ s.last }}</option></select>
        <select v-model="ex.deduction_policy_id" :style="ui.input"><option value="">Every policy</option><option v-for="pl in policies" :key="pl.id" :value="pl.id">{{ pl.name }}</option></select>
        <input type="date" v-model="ex.date_from" :style="ui.input" /><input type="date" v-model="ex.date_to" :style="ui.input" />
        <input v-model="ex.reason" placeholder="Reason" :style="ui.input" />
        <div @click="addException" :style="ui.btn">Add</div>
      </div>
      <div v-for="x in exceptions" :key="x.id" :style="ui.row + ';display:flex;gap:12px'">
        <div style="font-weight:600">{{ staffName(x.employee_id) }}</div>
        <div>{{ policies.find(pl => pl.id === x.deduction_policy_id)?.name || 'Every policy' }}</div>
        <div :style="ui.muted">{{ dmy(x.date_from) }} – {{ x.date_to ? dmy(x.date_to) : 'no end' }} · {{ x.reason }}</div>
        <div @click="removeException(x)" :style="ui.btnBad + ';margin-left:auto'">Delete</div>
      </div>
      <div v-if="!exceptions.length" :style="ui.row + ';color:#8b98a8'">None.</div>
    </div>

    <div v-if="form" :style="ui.overlay" @click.self="form = null">
      <div :style="ui.dialog">
        <div :style="ui.title">Add a deduction by hand</div>
        <label :style="ui.label">Employee
          <select v-model="form.employee_id" :style="ui.input"><option value="">Pick…</option><option v-for="s in staff" :key="s.id" :value="s.id">{{ s.first }} {{ s.last }}</option></select>
        </label>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
          <label :style="ui.label">Month<input type="month" v-model="form.month" :style="ui.input" /></label>
          <label :style="ui.label">Amount<input type="number" min="0" step="0.01" v-model="form.amount" :style="ui.input" /></label>
        </div>
        <label :style="ui.label">Why<input v-model="form.note" placeholder="e.g. damaged knife" :style="ui.input" /></label>
        <div :style="ui.muted">It goes through approval before payroll uses it.</div>
        <div style="display:flex;gap:8px;justify-content:flex-end">
          <div @click="form = null" :style="ui.btn">Cancel</div>
          <div @click="save" :style="ui.btnMain">Send for approval</div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
// HRM > Deductions (needs hrm.payroll).
// API: GET /salary-deductions?month=&deduction_status=, POST /salary-deductions/calculate, POST /salary-deductions,
//      DELETE /salary-deductions/{id}, GET|POST /deduction-exceptions, DELETE /deduction-exceptions/{id}
import { onMounted, reactive, ref } from 'vue';
import { api, ui, badge, label, dmy, money, monthName, thisMonth, loadStaff } from '../../api/hrm.js';

const grid = 'grid-template-columns:0.7fr 1.3fr 0.9fr 0.8fr 0.7fr 0.7fr 1.3fr 0.9fr 0.6fr;gap:10px';
const month = ref(thisMonth());
const status = ref('');
const rows = ref<any[]>([]);
const staff = ref<any[]>([]);
const policies = ref<any[]>([]);
const exceptions = ref<any[]>([]);
const form = ref<any>(null);
const ex = reactive({ employee_id: '', deduction_policy_id: '', date_from: '', date_to: '', reason: '' });
const error = ref('');
const message = ref('');

const staffName = (id: number) => { const s = staff.value.find(s => s.id === id); return s ? s.first + ' ' + s.last : '#' + id; };

async function load() {
  error.value = '';
  try {
    rows.value = await api.get('/salary-deductions?month=' + month.value + (status.value ? '&deduction_status=' + status.value : ''));
    exceptions.value = await api.get('/deduction-exceptions');
  } catch (e: any) {
    error.value = e.message;
  }
}

async function act(fn: () => Promise<any>) {
  error.value = '';
  message.value = '';
  try {
    await fn();
    await load();
  } catch (e: any) {
    error.value = e.message;
  }
}

const calculate = () => act(async () => {
  const r = await api.post('/salary-deductions/calculate', { month: month.value });
  message.value = r.added + ' new deduction' + (r.added === 1 ? '' : 's') + ' for ' + r.employees + ' staff.' + (policies.value.length ? '' : ' (There are no deduction policies yet: add them on Policies.)');
});
const save = () => act(async () => {
  const r = await api.post('/salary-deductions', form.value);
  message.value = r.ref + ' sent for approval.';
  form.value = null;
});
const remove = (d: any) => act(() => api.del('/salary-deductions/' + d.id));
const addException = () => act(async () => {
  await api.post('/deduction-exceptions', { ...ex, deduction_policy_id: ex.deduction_policy_id || null, date_to: ex.date_to || null });
  Object.assign(ex, { employee_id: '', deduction_policy_id: '', date_from: '', date_to: '', reason: '' });
});
const removeException = (x: any) => act(() => api.del('/deduction-exceptions/' + x.id));

onMounted(async () => {
  [staff.value, policies.value] = await Promise.all([loadStaff().catch(() => []), api.get('/deduction-policies').catch(() => [])]);
  load();
});
</script>
