<template>
  <div style="display:grid;gap:14px">
    <div v-if="message" :style="ui.ok">{{ message }}</div>
    <div v-if="error" :style="ui.err">{{ error }}</div>

    <!-- One run -->
    <template v-if="run">
      <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
        <div @click="run = null; loadRuns()" :style="ui.btn">← All payrolls</div>
        <div style="font-size:16px;font-weight:700">PR-{{ run.id }} · {{ monthName(run.month) }} · {{ run.branch?.name || 'All branches' }}</div>
        <span :style="badge(run.status)">{{ run.status === 'pending' ? 'waiting for approval' : run.status }}</span>
        <div style="margin-left:auto;display:flex;gap:8px">
          <template v-if="run.status === 'draft'">
            <div @click="act('recalculate')" :style="ui.btn">Recalculate</div>
            <div @click="remove" :style="ui.btn">Delete</div>
            <div @click="act('submit')" :style="ui.btnMain">Send for approval</div>
          </template>
          <RouterLink v-if="run.status === 'pending'" :to="{ name: 'hrm-approvals' }" :style="ui.btnMain + ';text-decoration:none'">Open approvals</RouterLink>
          <div v-if="run.status === 'approved'" @click="act('pay')" :style="ui.btnMain">Mark as paid</div>
        </div>
      </div>
      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px">
        <div :style="ui.kpi"><div :style="ui.muted">Staff</div><div :style="ui.kpiValue">{{ run.staff }}</div></div>
        <div :style="ui.kpi"><div :style="ui.muted">Gross</div><div :style="ui.kpiValue">{{ money(run.gross) }}</div></div>
        <div :style="ui.kpi"><div :style="ui.muted">Taken off</div><div :style="ui.kpiValue">{{ money(run.deductions) }}</div><div :style="ui.muted">deductions, advances, loans</div></div>
        <div :style="ui.kpi"><div :style="ui.muted">Net to pay</div><div :style="ui.kpiValue">{{ money(run.net) }}</div><div :style="ui.muted">{{ run.paid_at ? 'paid ' + dmy(run.paid_at) + ' by ' + run.paid_by : 'pay date ' + dmy(run.pay_date) }}</div></div>
      </div>
      <div :style="ui.card">
        <div :style="ui.head + ';display:grid;' + grid"><div>Employee</div><div>Basic</div><div>Allowances</div><div>Overtime</div><div>Bonus</div><div>Deductions</div><div>Advance</div><div>Loan</div><div>Net</div><div></div></div>
        <template v-for="i in run.items" :key="i.id">
          <div :style="ui.row + ';display:grid;' + grid">
            <div style="font-weight:600">{{ i.employee?.first }} {{ i.employee?.last }}<div :style="ui.muted">{{ i.employee?.code }}</div></div>
            <div :style="ui.mono">{{ money(i.basic) }}</div><div :style="ui.mono">{{ money(i.allowances) }}</div>
            <div :style="ui.mono">{{ money(i.overtime) }}</div><div :style="ui.mono">{{ money(i.bonus) }}</div>
            <div :style="ui.mono">{{ money(i.deductions) }}</div><div :style="ui.mono">{{ money(i.advance) }}</div>
            <div :style="ui.mono">{{ money(i.loan) }}</div><div :style="ui.mono + ';font-weight:700'">{{ money(i.net) }}</div>
            <div style="display:flex;gap:5px;justify-content:flex-end">
              <div @click="openLines[i.id] = !openLines[i.id]" :style="ui.btnSmall">Lines</div>
              <div @click="openPrintable('/payslips/' + i.id + '/print')" :style="ui.btnSmall">Payslip</div>
            </div>
          </div>
          <div v-if="openLines[i.id]" style="padding:6px 16px 10px 32px;background:#fafcfe;border-bottom:1px solid #f0f4f8;display:grid;gap:2px;font-size:12px;max-width:520px">
            <div v-for="(l, n) in i.lines" :key="n" style="display:flex;justify-content:space-between"><span>{{ l.name }}</span><span :style="ui.mono + (l.amount < 0 ? ';color:#b02020' : '')">{{ money(l.amount) }}</span></div>
          </div>
        </template>
        <div v-if="!run.items.length" :style="ui.row + ';color:#8b98a8'">Nobody with a salary at this branch.</div>
      </div>
      <div :style="ui.muted">Net = basic + allowances + overtime + bonus − deductions − advance − loan. Only approved overtime and deductions are counted.</div>
      <div v-if="run.approvals.length" :style="ui.card">
        <div :style="ui.head">Approval steps</div>
        <div v-for="a in run.approvals" :key="a.id" :style="ui.row + ';display:flex;gap:12px'">
          <div>{{ a.step }}. {{ label(a.level) }}</div><span :style="badge(a.status)">{{ a.status }}</span>
          <div :style="ui.muted">{{ a.approver_name }} {{ a.acted_at ? dmy(a.acted_at) : '' }} {{ a.remarks ? '· “' + a.remarks + '”' : '' }}</div>
        </div>
      </div>
    </template>

    <!-- All runs -->
    <template v-else>
      <div :style="ui.card + ';' + ui.pad + ';display:flex;gap:8px;align-items:center;flex-wrap:wrap'">
        <div :style="ui.title">Make a month's payroll</div>
        <input type="month" v-model="newMonth" :style="ui.input + ';margin-left:auto'" />
        <select v-if="!lockedBranch" v-model.number="newBranch" :style="ui.input">
          <option :value="0">All branches</option>
          <option v-for="b in branches" :key="b.id" :value="b.id">{{ b.name }}</option>
        </select>
        <div @click="make" :style="ui.btnMain">Make payroll</div>
      </div>
      <div :style="ui.card">
        <div :style="ui.head + ';display:grid;grid-template-columns:0.6fr 1fr 1fr 0.6fr 1fr 1fr 1fr;gap:10px'"><div>Ref</div><div>Month</div><div>Branch</div><div>Staff</div><div>Gross</div><div>Net</div><div>Status</div></div>
        <div v-for="r in runs" :key="r.id" @click="open(r.id)" :style="ui.row + ';display:grid;grid-template-columns:0.6fr 1fr 1fr 0.6fr 1fr 1fr 1fr;gap:10px;cursor:pointer'">
          <div :style="ui.mono">PR-{{ r.id }}</div><div>{{ monthName(r.month) }}</div><div>{{ r.branch?.name || 'All branches' }}</div>
          <div :style="ui.mono">{{ r.staff }}</div><div :style="ui.mono">{{ money(r.gross) }}</div><div :style="ui.mono">{{ money(r.net) }}</div>
          <div><span :style="badge(r.status)">{{ r.status === 'pending' ? 'waiting for approval' : r.status }}</span></div>
        </div>
        <div v-if="!runs.length" style="padding:30px;text-align:center;color:#7d8b9c;font-size:13px">No monthly payroll yet.</div>
      </div>
      <div v-if="report.months?.length" :style="ui.card">
        <div :style="ui.head">{{ report.year }}: approved and paid payroll</div>
        <div v-for="m in report.months" :key="m.month" :style="ui.row + ';display:flex;gap:16px'">
          <div style="min-width:140px">{{ monthName(m.month) }}</div><div :style="ui.muted">{{ m.staff }} staff</div>
          <div :style="ui.mono">gross {{ money(m.gross) }}</div><div :style="ui.mono">taken off {{ money(m.deductions) }}</div><div :style="ui.mono + ';font-weight:700'">net {{ money(m.net) }}</div>
        </div>
      </div>
    </template>
  </div>
</template>

<script setup lang="ts">
// HRM > Monthly payroll (needs hrm.payroll).
// API: GET|POST /payroll, GET /payroll/{id}, POST /payroll/{id}/recalculate | submit | pay, DELETE /payroll/{id},
//      GET /payroll/report?year=, GET /payslips/{item}/print
import { onMounted, reactive, ref } from 'vue';
import { api, ui, badge, label, dmy, money, monthName, openPrintable } from '../../api/hrm.js';
import { branches, loadBranches } from '../../api/branches.js';
import { currentUser } from '../../api/auth.js';

const grid = 'grid-template-columns:1.4fr repeat(8,0.8fr) 1.1fr;gap:8px';
const lockedBranch = currentUser.value?.branch_id || 0;
const runs = ref<any[]>([]);
const run = ref<any>(null);
const report = ref<any>({});
const openLines = reactive<Record<number, boolean>>({});
// Last month by default: a month is paid after it ends.
const d = new Date();
d.setDate(1);
d.setMonth(d.getMonth() - 1);
const newMonth = ref(d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0'));
const newBranch = ref(0);
const error = ref('');
const message = ref('');

async function loadRuns() {
  runs.value = await api.get('/payroll').catch(() => []);
  report.value = await api.get('/payroll/report?year=' + new Date().getFullYear()).catch(() => ({}));
}
async function open(id: number) {
  error.value = '';
  try {
    run.value = await api.get('/payroll/' + id);
  } catch (e: any) {
    error.value = e.message;
  }
}

async function make() {
  error.value = '';
  message.value = '';
  try {
    run.value = await api.post('/payroll', { month: newMonth.value, branch_id: lockedBranch || newBranch.value || null });
    message.value = 'Payroll made as a draft. Check it, then send it for approval.';
  } catch (e: any) {
    error.value = e.message;
  }
}

const DONE: Record<string, string> = { recalculate: 'Worked out again.', submit: 'Sent for approval.', pay: 'Marked as paid. Overtime, deductions and loan instalments are settled.' };
async function act(what: string) {
  error.value = '';
  message.value = '';
  try {
    run.value = await api.post('/payroll/' + run.value.id + '/' + what);
    message.value = DONE[what];
  } catch (e: any) {
    error.value = e.message;
  }
}

async function remove() {
  if (!confirm('Delete this draft payroll?')) return;
  try {
    await api.del('/payroll/' + run.value.id);
    run.value = null;
    await loadRuns();
  } catch (e: any) {
    error.value = e.message;
  }
}

onMounted(() => {
  loadBranches().catch(() => {});
  loadRuns();
});
</script>
