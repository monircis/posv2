<template>
  <div style="display:grid;gap:14px">
    <div :style="ui.card + ';' + ui.pad + ';display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap'">
      <label :style="ui.label">Report
        <select v-model="report" :style="ui.input"><option v-for="r in reports" :key="r.key" :value="r.key">{{ r.title }}</option></select>
      </label>
      <label :style="ui.label">From<input type="date" v-model="from" :style="ui.input" /></label>
      <label :style="ui.label">To<input type="date" v-model="to" :style="ui.input" /></label>
      <label :style="ui.label">Employee
        <select v-model="employeeId" :style="ui.input"><option value="">Everyone</option><option v-for="s in staff" :key="s.id" :value="s.id">{{ s.first }} {{ s.last }}</option></select>
      </label>
      <label :style="ui.label">Department
        <select v-model="departmentId" :style="ui.input"><option value="">All</option><option v-for="d in departments" :key="d.id" :value="d.id">{{ d.name }}</option></select>
      </label>
      <label :style="ui.label">Shift
        <select v-model="shiftId" :style="ui.input"><option value="">All</option><option v-for="s in shifts" :key="s.id" :value="s.id">{{ s.name }}</option></select>
      </label>
      <label v-if="!lockedBranch" :style="ui.label">Branch
        <select v-model="branchId" :style="ui.input"><option value="">All</option><option v-for="b in branches" :key="b.id" :value="b.id">{{ b.name }}</option></select>
      </label>
      <div @click="run" :style="ui.btnMain">Show</div>
      <div style="display:flex;gap:6px;margin-left:auto">
        <div @click="exportAs('csv')" :style="ui.btn">CSV</div>
        <div @click="exportAs('excel')" :style="ui.btn">Excel</div>
        <div @click="exportAs('pdf')" :style="ui.btn">PDF</div>
      </div>
    </div>
    <div v-if="error" :style="ui.err">{{ error }}</div>

    <div v-if="result" :style="ui.card + ';overflow-x:auto'">
      <div :style="ui.pad + ';display:flex;gap:10px'"><div :style="ui.title">{{ result.title }}</div><div :style="ui.muted">{{ dmy(result.from) }} – {{ dmy(result.to) }} · {{ result.rows.length }} rows</div></div>
      <table style="width:100%;border-collapse:collapse;font-size:12.5px">
        <thead><tr><th v-for="c in result.columns" :key="c.key" style="text-align:left;padding:8px 12px;background:#f6f9fc;border-bottom:1px solid #dfe6ee;font-size:10.5px;text-transform:uppercase;letter-spacing:.05em;color:#5d6b7d;white-space:nowrap">{{ c.label }}</th></tr></thead>
        <tbody>
          <tr v-for="(r, i) in result.rows" :key="i">
            <td v-for="c in result.columns" :key="c.key" style="padding:7px 12px;border-bottom:1px solid #f0f4f8;white-space:nowrap">
              <span v-if="c.key === 'status'" :style="badge(r.status)">{{ label(r.status) }}</span>
              <template v-else>{{ r[c.key] ?? '' }}</template>
            </td>
          </tr>
        </tbody>
      </table>
      <div v-if="!result.rows.length" style="padding:30px;text-align:center;color:#7d8b9c;font-size:13px">Nothing for these filters.</div>
    </div>
  </div>
</template>

<script setup lang="ts">
// HRM > Attendance reports. API: GET /attendance-reports, GET /attendance-reports/{report}?from=&to=&...&format=csv|excel|pdf
import { onMounted, ref } from 'vue';
import { api, ui, badge, label, dmy, today, thisMonth, loadStaff, download, openPrintable } from '../../api/hrm.js';
import { branches, loadBranches } from '../../api/branches.js';
import { currentUser } from '../../api/auth.js';

const lockedBranch = currentUser.value?.branch_id || 0;
const reports = ref<any[]>([]);
const report = ref('daily');
const from = ref(thisMonth() + '-01');
const to = ref(today());
const employeeId = ref('');
const departmentId = ref('');
const shiftId = ref('');
const branchId = ref('');
const staff = ref<any[]>([]);
const departments = ref<any[]>([]);
const shifts = ref<any[]>([]);
const result = ref<any>(null);
const error = ref('');

function query(format?: string) {
  const p = new URLSearchParams({ from: from.value, to: to.value });
  if (employeeId.value) p.set('employee_id', employeeId.value);
  if (departmentId.value) p.set('department_id', departmentId.value);
  if (shiftId.value) p.set('shift_id', shiftId.value);
  if (branchId.value) p.set('branch_id', branchId.value);
  if (format) p.set('format', format);
  return '/attendance-reports/' + report.value + '?' + p.toString();
}

async function run() {
  error.value = '';
  try {
    result.value = await api.get(query());
  } catch (e: any) {
    error.value = e.message;
  }
}

async function exportAs(format: string) {
  error.value = '';
  try {
    if (format === 'pdf') await openPrintable(query('pdf'));
    else await download(query(format), report.value + '-' + from.value + '-to-' + to.value + (format === 'csv' ? '.csv' : '.xls'));
  } catch (e: any) {
    error.value = e.message;
  }
}

onMounted(async () => {
  loadBranches().catch(() => {});
  [reports.value, staff.value, departments.value, shifts.value] = await Promise.all([
    api.get('/attendance-reports').catch(() => []), loadStaff().catch(() => []),
    api.get('/departments').catch(() => []), api.get('/shifts').catch(() => []),
  ]);
  run();
});
</script>
