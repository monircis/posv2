<template>
  <div style="display:grid;gap:14px">
    <div v-if="message" :style="ui.ok">{{ message }}</div>
    <div v-if="error" :style="ui.err">{{ error }}</div>

    <!-- Salary structure -->
    <div :style="ui.card">
      <div :style="ui.pad + ';display:flex;gap:8px;align-items:center;flex-wrap:wrap'">
        <div :style="ui.title">Salary structure</div>
        <select v-model="employeeId" @change="loadStructures" :style="ui.input + ';margin-left:auto'">
          <option :value="0">Pick an employee…</option>
          <option v-for="s in staff" :key="s.id" :value="s.id">{{ s.first }} {{ s.last }} · {{ s.code }}</option>
        </select>
      </div>
      <template v-if="employeeId">
        <div :style="ui.head + ';display:grid;' + sGrid"><div>From</div><div>Basic</div><div>House rent</div><div>Medical</div><div>Transport</div><div>Other</div><div>Fixed deductions</div><div>Monthly gross</div></div>
        <div v-for="(s, i) in structures" :key="s.id" :style="ui.row + ';display:grid;' + sGrid + (i === 0 ? '' : ';color:#8b98a8')">
          <div>{{ dmy(s.effective_from) }}<div v-if="s.note" :style="ui.muted">{{ s.note }}</div></div>
          <div :style="ui.mono">{{ money(s.basic) }}</div><div :style="ui.mono">{{ money(s.house_rent) }}</div>
          <div :style="ui.mono">{{ money(s.medical) }}</div><div :style="ui.mono">{{ money(s.transport) }}</div>
          <div>{{ (s.other_allowances || []).map((a: any) => a.name + ' ' + money(a.amount)).join(', ') || '—' }}</div>
          <div>{{ (s.fixed_deductions || []).map((a: any) => a.name + ' ' + money(a.amount)).join(', ') || '—' }}</div>
          <div :style="ui.mono + ';font-weight:600'">{{ money(s.gross) }}</div>
        </div>
        <div v-if="!structures.length" :style="ui.row + ';color:#8b98a8'">No salary structure yet: payroll uses the employee's rate.</div>

        <div :style="ui.pad + ';display:grid;gap:10px;border-top:1px solid #f0f4f8'">
          <div style="font-weight:700;font-size:13px">New salary (a pay change keeps the old one as history)</div>
          <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(130px,1fr));gap:10px">
            <label :style="ui.label">From<input type="date" v-model="ns.effective_from" :style="ui.input" /></label>
            <label :style="ui.label">Basic / month<input type="number" min="0" v-model="ns.basic" :style="ui.input" /></label>
            <label :style="ui.label">House rent<input type="number" min="0" v-model="ns.house_rent" :style="ui.input" /></label>
            <label :style="ui.label">Medical<input type="number" min="0" v-model="ns.medical" :style="ui.input" /></label>
            <label :style="ui.label">Transport<input type="number" min="0" v-model="ns.transport" :style="ui.input" /></label>
          </div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
            <div v-for="list in LISTS" :key="list" style="display:grid;gap:6px">
              <div :style="ui.label">{{ list === 'other_allowances' ? 'Other allowances' : 'Fixed deductions' }}</div>
              <div v-for="(a, i) in lines(list)" :key="i" style="display:flex;gap:6px">
                <input v-model="a.name" placeholder="Name" :style="ui.input + ';flex:1'" /><input type="number" min="0" v-model="a.amount" placeholder="Amount" :style="ui.input + ';width:100px'" />
                <div @click="lines(list).splice(i, 1)" :style="ui.btnSmall">×</div>
              </div>
              <div @click="lines(list).push({ name: '', amount: '' })" :style="ui.btnSmall + ';justify-self:start'">+ Add line</div>
            </div>
          </div>
          <label :style="ui.label">Note<input v-model="ns.note" :style="ui.input" /></label>
          <div><div @click="saveStructure" :style="ui.btnMain">Save salary</div></div>
        </div>
      </template>
    </div>

    <!-- Bonuses and advances -->
    <div :style="ui.card">
      <div :style="ui.pad + ';display:flex;gap:8px;align-items:center;flex-wrap:wrap'">
        <div :style="ui.title">Bonuses, incentives and advances</div>
        <input type="month" v-model="month" @change="loadAdjustments" :style="ui.input + ';margin-left:auto'" />
      </div>
      <div :style="ui.row + ';display:grid;grid-template-columns:1.4fr 1fr 0.8fr 1.4fr auto;gap:6px'">
        <select v-model="adj.employee_id" :style="ui.input"><option value="">Employee…</option><option v-for="s in staff" :key="s.id" :value="s.id">{{ s.first }} {{ s.last }}</option></select>
        <select v-model="adj.type" :style="ui.input"><option value="bonus">Bonus (added)</option><option value="incentive">Incentive (added)</option><option value="advance">Advance (taken off)</option></select>
        <input type="number" min="0" v-model="adj.amount" placeholder="Amount" :style="ui.input" />
        <input v-model="adj.note" placeholder="Note" :style="ui.input" />
        <div @click="saveAdjustment" :style="ui.btn">Add to {{ monthName(month) }}</div>
      </div>
      <div v-for="a in adjustments" :key="a.id" :style="ui.row + ';display:flex;gap:12px'">
        <div style="font-weight:600;min-width:160px">{{ staffName(a.employee_id) }}</div>
        <div>{{ label(a.type) }}</div>
        <div :style="ui.mono + (a.type === 'advance' ? ';color:#b02020' : '')">{{ a.type === 'advance' ? '−' : '+' }}{{ money(a.amount) }}</div>
        <div :style="ui.muted">{{ a.note }}</div>
        <div style="margin-left:auto"><span v-if="a.pay_run_id" :style="badge('paid')">paid</span><div v-else @click="removeAdjustment(a)" :style="ui.btnSmall">Remove</div></div>
      </div>
      <div v-if="!adjustments.length" :style="ui.row + ';color:#8b98a8'">Nothing for {{ monthName(month) }}.</div>
    </div>

    <!-- Loans -->
    <div :style="ui.card">
      <div :style="ui.pad"><div :style="ui.title">Loans</div></div>
      <div :style="ui.row + ';display:grid;grid-template-columns:1.4fr 0.8fr 0.8fr 0.9fr 1.2fr auto;gap:6px'">
        <select v-model="loan.employee_id" :style="ui.input"><option value="">Employee…</option><option v-for="s in staff" :key="s.id" :value="s.id">{{ s.first }} {{ s.last }}</option></select>
        <input type="number" min="0" v-model="loan.amount" placeholder="Loan" :style="ui.input" />
        <input type="number" min="0" v-model="loan.installment" placeholder="Per month" :style="ui.input" />
        <input type="month" v-model="loan.start_month" title="First month" :style="ui.input" />
        <input v-model="loan.note" placeholder="Note" :style="ui.input" />
        <div @click="saveLoan" :style="ui.btn">Give loan</div>
      </div>
      <div v-for="l in loans" :key="l.id" :style="ui.row + ';display:flex;gap:12px;align-items:center'">
        <div style="font-weight:600;min-width:160px">{{ staffName(l.employee_id) }}</div>
        <div :style="ui.mono">{{ money(l.amount) }}</div>
        <div :style="ui.muted">{{ money(l.installment) }}/month from {{ l.start_month }} · paid {{ money(l.paid) }} · left {{ money(l.left) }}</div>
        <div style="margin-left:auto;display:flex;gap:6px;align-items:center"><span :style="badge(l.status)">{{ l.status }}</span><div v-if="l.status === 'active'" @click="closeLoan(l)" :style="ui.btnSmall">Close</div></div>
      </div>
      <div v-if="!loans.length" :style="ui.row + ';color:#8b98a8'">No loans.</div>
    </div>
  </div>
</template>

<script setup lang="ts">
// HRM > Salary (needs hrm.payroll).
// API: GET|POST /employees/{id}/salary, GET|POST /salary-adjustments, DELETE /salary-adjustments/{id},
//      GET|POST /employee-loans, POST /employee-loans/{id}/close
import { onMounted, reactive, ref } from 'vue';
import { useRoute } from 'vue-router';
import { api, ui, badge, label, dmy, money, monthName, thisMonth, today, loadStaff } from '../../api/hrm.js';

const sGrid = 'grid-template-columns:0.8fr 0.8fr 0.8fr 0.7fr 0.7fr 1.2fr 1.2fr 0.9fr;gap:10px';
const route = useRoute();
const staff = ref<any[]>([]);
const employeeId = ref(Number(route.query.employee) || 0);
const structures = ref<any[]>([]);
const blank = () => ({ effective_from: today(), basic: '', house_rent: '', medical: '', transport: '', other_allowances: [] as any[], fixed_deductions: [] as any[], note: '' });
const ns = reactive(blank());
const month = ref(thisMonth());
const adjustments = ref<any[]>([]);
const adj = reactive({ employee_id: '', type: 'bonus', amount: '', note: '' });
const loans = ref<any[]>([]);
const loan = reactive({ employee_id: '', amount: '', installment: '', start_month: thisMonth(), note: '' });
const error = ref('');
const message = ref('');

const LISTS = ['other_allowances', 'fixed_deductions'];
const lines = (list: string): any[] => (ns as any)[list];
const staffName = (id: number) => { const s = staff.value.find(s => s.id === id); return s ? s.first + ' ' + s.last : '#' + id; };

async function act(fn: () => Promise<any>, done: string) {
  error.value = '';
  message.value = '';
  try {
    await fn();
    message.value = done;
  } catch (e: any) {
    error.value = e.message;
  }
}

async function loadStructures() {
  structures.value = employeeId.value ? await api.get('/employees/' + employeeId.value + '/salary').catch(() => []) : [];
}
async function loadAdjustments() {
  adjustments.value = await api.get('/salary-adjustments?month=' + month.value).catch(() => []);
}
async function loadLoans() {
  loans.value = await api.get('/employee-loans').catch(() => []);
}

const saveStructure = () => act(async () => {
  const lines = (list: any[]) => list.filter(a => a.name && a.amount !== '').map(a => ({ name: a.name, amount: Number(a.amount) }));
  await api.post('/employees/' + employeeId.value + '/salary', {
    effective_from: ns.effective_from, basic: Number(ns.basic), house_rent: Number(ns.house_rent) || 0, medical: Number(ns.medical) || 0,
    transport: Number(ns.transport) || 0, other_allowances: lines(ns.other_allowances), fixed_deductions: lines(ns.fixed_deductions), note: ns.note || null,
  });
  Object.assign(ns, blank());
  await loadStructures();
}, 'Salary saved.');
const saveAdjustment = () => act(async () => {
  await api.post('/salary-adjustments', { ...adj, month: month.value, amount: Number(adj.amount) });
  Object.assign(adj, { employee_id: '', amount: '', note: '' });
  await loadAdjustments();
}, 'Added.');
const removeAdjustment = (a: any) => act(async () => { await api.del('/salary-adjustments/' + a.id); await loadAdjustments(); }, 'Removed.');
const saveLoan = () => act(async () => {
  await api.post('/employee-loans', { ...loan, amount: Number(loan.amount), installment: Number(loan.installment) });
  Object.assign(loan, { employee_id: '', amount: '', installment: '', note: '' });
  await loadLoans();
}, 'Loan saved. Payroll takes the instalment each month.');
const closeLoan = (l: any) => act(async () => { await api.post('/employee-loans/' + l.id + '/close'); await loadLoans(); }, 'Loan closed.');

onMounted(async () => {
  staff.value = await loadStaff().catch(() => []);
  loadStructures();
  loadAdjustments();
  loadLoans();
});
</script>
