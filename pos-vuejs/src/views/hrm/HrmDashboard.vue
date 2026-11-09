<template>
  <div style="display:grid;gap:14px">
    <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
      <select v-if="branches.length > 1" v-model.number="branchId" @change="load" :style="ui.input">
        <option :value="0">All branches</option>
        <option v-for="b in branches" :key="b.id" :value="b.id">{{ b.name }}</option>
      </select>
      <div :style="ui.muted">{{ d ? 'Today ' + dmy(d.date) : 'Loading…' }}</div>
    </div>
    <div v-if="error" :style="ui.err">{{ error }}</div>

    <template v-if="d">
      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px">
        <div v-for="k in kpis" :key="k.k" :style="ui.kpi">
          <div :style="ui.muted">{{ k.k }}</div>
          <div :style="ui.kpiValue">{{ k.v }}</div>
          <div style="font-size:11.5px;color:#8b98a8;margin-top:1px">{{ k.n }}</div>
        </div>
      </div>

      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:14px">
        <div :style="ui.card">
          <div :style="ui.head">Waiting for approval</div>
          <RouterLink v-for="p in pending" :key="p.k" :to="{ name: 'hrm-approvals' }" :style="'display:flex;justify-content:space-between;text-decoration:none;color:inherit;' + ui.row">
            <span>{{ p.k }}</span><b :style="ui.mono">{{ p.v }}</b>
          </RouterLink>
        </div>
        <div :style="ui.card">
          <div :style="ui.head">Staff by department</div>
          <div v-for="(n, name) in d.by_department" :key="name" :style="'display:flex;justify-content:space-between;' + ui.row"><span>{{ name }}</span><b :style="ui.mono">{{ n }}</b></div>
          <div v-if="!Object.keys(d.by_department).length" :style="ui.row + ';color:#8b98a8'">No staff yet</div>
        </div>
        <div :style="ui.card">
          <div :style="ui.head">Staff by branch</div>
          <div v-for="(n, name) in d.by_branch" :key="name" :style="'display:flex;justify-content:space-between;' + ui.row"><span>{{ name }}</span><b :style="ui.mono">{{ n }}</b></div>
          <div v-if="!Object.keys(d.by_branch).length" :style="ui.row + ';color:#8b98a8'">No staff yet</div>
        </div>
        <div :style="ui.card">
          <div :style="ui.head">Birthdays · next 30 days</div>
          <div v-for="b in d.birthdays" :key="b.employee_id" :style="'display:flex;justify-content:space-between;' + ui.row"><span>{{ b.name }}</span><span :style="ui.muted">{{ dmy(b.date) }} · {{ b.in_days ? 'in ' + b.in_days + ' days' : 'today' }}</span></div>
          <div v-if="!d.birthdays.length" :style="ui.row + ';color:#8b98a8'">None</div>
        </div>
        <div :style="ui.card">
          <div :style="ui.head">Work anniversaries · next 30 days</div>
          <div v-for="b in d.anniversaries" :key="b.employee_id" :style="'display:flex;justify-content:space-between;' + ui.row"><span>{{ b.name }} · {{ b.years }} yr</span><span :style="ui.muted">{{ dmy(b.date) }}</span></div>
          <div v-if="!d.anniversaries.length" :style="ui.row + ';color:#8b98a8'">None</div>
        </div>
      </div>
    </template>
  </div>
</template>

<script setup lang="ts">
// HRM > Dashboard. API: GET /api/hrm-dashboard?branch_id=
import { computed, onMounted, ref } from 'vue';
import { api, ui, dmy } from '../../api/hrm.js';
import { branches as allBranches, loadBranches } from '../../api/branches.js';
import { currentUser } from '../../api/auth.js';

const d = ref<any>(null);
const error = ref('');
const branchId = ref(0);
// A user kept to one branch sees only that one; the API decides it anyway.
const branches = computed(() => (currentUser.value?.branch_id ? [] : allBranches.value));

const kpis = computed(() => {
  const x = d.value;
  return [
    { k: 'Staff', v: x.employees.active, n: x.employees.inactive + ' not working · ' + x.employees.on_probation + ' on probation' },
    { k: 'Present today', v: x.today.present, n: x.today.late + ' late · ' + x.today.remote + ' remote' },
    { k: 'Absent today', v: x.today.absent, n: x.today.not_in_yet + ' not in yet' },
    { k: 'On leave today', v: x.today.on_leave, n: x.today.day_off + ' day off' },
    { k: 'Overtime this month', v: x.month.overtime_hours + ' h', n: 'approved' },
    { k: 'Attendance this month', v: x.month.attendance_percent === null ? '—' : x.month.attendance_percent + '%', n: 'days at work / work days' },
  ];
});
const pending = computed(() => [
  { k: 'Leave requests', v: d.value.pending.leave },
  { k: 'Attendance fixes', v: d.value.pending.corrections },
  { k: 'Overtime', v: d.value.pending.overtime },
]);

async function load() {
  error.value = '';
  try {
    d.value = await api.get('/hrm-dashboard' + (branchId.value ? '?branch_id=' + branchId.value : ''));
  } catch (e: any) {
    error.value = e.message;
  }
}

onMounted(() => {
  loadBranches().catch(() => {});
  load();
});
</script>
