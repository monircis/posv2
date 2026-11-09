<template>
  <div style="display:grid;gap:14px">
    <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
      <div :style="ui.segWrap">
        <div v-for="(k, key) in KINDS" :key="key" @click="kind = key; load()" :style="ui.seg(kind === key)">{{ k.name }}</div>
      </div>
      <div :style="ui.muted">{{ KINDS[kind].help }}</div>
      <div v-if="canCreate" @click="edit(null)" :style="ui.btnMain + ';margin-left:auto'">+ New {{ KINDS[kind].one }}</div>
    </div>
    <div v-if="message" :style="ui.ok">{{ message }}</div>
    <div v-if="error" :style="ui.err">{{ error }}</div>

    <div :style="ui.card">
      <div v-for="r in rows" :key="r.id" :style="ui.row + ';display:flex;gap:14px;align-items:center' + (r.active ? '' : ';color:#9aa7b6')">
        <div style="min-width:180px"><div style="font-weight:700">{{ r.name }} <span v-if="r.code" :style="ui.mono + ';font-size:11px;color:#8b98a8'">{{ r.code }}</span></div><div :style="ui.muted">{{ branchName(r.branch_id) }}{{ r.active ? '' : ' · switched off' }}</div></div>
        <div style="flex:1;font-size:12.5px;color:#4a5866">{{ summary(r) }}</div>
        <div style="display:flex;gap:5px">
          <div v-if="canEdit" @click="edit(r)" :style="ui.btnSmall">Edit</div>
          <div v-if="canDelete" @click="remove(r)" :style="ui.btnBad">Delete</div>
        </div>
      </div>
      <div v-if="!rows.length" style="padding:30px;text-align:center;color:#7d8b9c;font-size:13px">No {{ KINDS[kind].name.toLowerCase() }} policies yet.</div>
    </div>

    <div v-if="form" :style="ui.overlay" @click.self="form = null">
      <div :style="ui.dialog + ';width:640px'">
        <div :style="ui.title">{{ form.id ? 'Edit' : 'New' }} {{ KINDS[kind].one }}</div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
          <template v-for="fl in KINDS[kind].fields" :key="fl.key">
            <label v-if="fl.type === 'bool'" style="font-size:12.5px;display:flex;gap:6px;align-items:center"><input type="checkbox" v-model="form[fl.key]" /> {{ fl.label }}</label>
            <label v-else :style="ui.label">{{ fl.label }}
              <select v-if="fl.type === 'select'" v-model="form[fl.key]" :style="ui.input">
                <option v-for="o in options(fl)" :key="String(o.v)" :value="o.v">{{ o.t }}</option>
              </select>
              <input v-else :type="fl.type === 'number' ? 'number' : 'text'" step="any" v-model="form[fl.key]" :placeholder="fl.hint || ''" :style="ui.input" />
            </label>
          </template>
        </div>
        <div style="display:flex;gap:8px;justify-content:flex-end">
          <div @click="form = null" :style="ui.btn">Cancel</div>
          <div @click="save" :style="ui.btnMain">Save</div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
// HRM > Policies: leave, overtime and deduction rules.
// API: GET|POST /leave-policies | /overtime-policies | /deduction-policies, PUT|DELETE .../{id}
import { onMounted, ref } from 'vue';
import { api, ui, label, money } from '../../api/hrm.js';
import { branches, loadBranches } from '../../api/branches.js';
import { can, currentUser } from '../../api/auth.js';

type Field = { key: string, label: string, type: 'text' | 'number' | 'bool' | 'select', options?: string, hint?: string, empty?: any };

// The fields of each kind of policy. "options" names a list in options() below.
const KINDS: Record<string, { name: string, one: string, url: string, help: string, fields: Field[] }> = {
  leave: {
    name: 'Leave', one: 'leave type', url: '/leave-policies', help: 'Each leave type and its rules.',
    fields: [
      { key: 'name', label: 'Name', type: 'text', hint: 'e.g. Sick' }, { key: 'code', label: 'Code', type: 'text', hint: 'e.g. SL' },
      { key: 'days_per_year', label: 'Days a year', type: 'number', empty: 0 }, { key: 'accrual', label: 'How days are given', type: 'select', options: 'accrual' },
      { key: 'max_carry_days', label: 'Most days carried to next year', type: 'number', empty: 0 }, { key: 'max_balance', label: 'Most days held (empty = no limit)', type: 'number', empty: null },
      { key: 'attachment_after_days', label: 'File needed for more than (days)', type: 'number', empty: null }, { key: 'gender', label: 'Only for', type: 'select', options: 'gender' },
      { key: 'branch_id', label: 'Branch', type: 'select', options: 'branch' }, { key: 'department_id', label: 'Department', type: 'select', options: 'department' },
      { key: 'paid', label: 'Paid leave', type: 'bool' }, { key: 'track_balance', label: 'Keeps a balance', type: 'bool' },
      { key: 'carry_forward', label: 'Carry forward', type: 'bool' }, { key: 'encashable', label: 'Can be paid out (encashed)', type: 'bool' },
      { key: 'allow_half_day', label: 'Half days allowed', type: 'bool' }, { key: 'allow_hourly', label: 'Hourly leave allowed', type: 'bool' },
      { key: 'exclude_weekends', label: 'Days off are not counted', type: 'bool' }, { key: 'exclude_holidays', label: 'Holidays are not counted', type: 'bool' },
      { key: 'probation_allowed', label: 'Can be taken on probation', type: 'bool' }, { key: 'active', label: 'In use', type: 'bool' },
    ],
  },
  overtime: {
    name: 'Overtime', one: 'overtime policy', url: '/overtime-policies', help: 'Rates multiply the hourly rate (1.5 = time and a half).',
    fields: [
      { key: 'name', label: 'Name', type: 'text' }, { key: 'branch_id', label: 'Branch', type: 'select', options: 'branch' },
      { key: 'regular_rate', label: 'Rate on a work day', type: 'number' }, { key: 'weekend_rate', label: 'Rate on a day off', type: 'number' },
      { key: 'holiday_rate', label: 'Rate on a holiday', type: 'number' }, { key: 'min_minutes', label: 'Counts from (minutes)', type: 'number', empty: 0 },
      { key: 'max_daily_minutes', label: 'Most a day (minutes, 0 = no limit)', type: 'number', empty: 0 }, { key: 'max_monthly_minutes', label: 'Most a month (minutes, 0 = no limit)', type: 'number', empty: 0 },
      { key: 'auto_calculate', label: 'Make overtime from attendance', type: 'bool' }, { key: 'active', label: 'In use', type: 'bool' },
    ],
  },
  deduction: {
    name: 'Deductions', one: 'deduction policy', url: '/deduction-policies', help: 'What is taken off pay for late, absent and so on. Nothing is taken without these rules.',
    fields: [
      { key: 'name', label: 'Name', type: 'text' }, { key: 'branch_id', label: 'Branch', type: 'select', options: 'branch' },
      { key: 'type', label: 'For', type: 'select', options: 'dtype' }, { key: 'method', label: 'How much', type: 'select', options: 'method' },
      { key: 'value', label: 'Value ($, %, $ per minute/hour, or days of pay)', type: 'number' }, { key: 'grace_minutes', label: 'Free minutes (late / early)', type: 'number', empty: 0 },
      { key: 'free_times', label: 'Free times a month', type: 'number', empty: 0 }, { key: 'monthly_limit', label: 'Most a month $ (empty = no limit)', type: 'number', empty: null },
      { key: 'needs_approval', label: 'Needs approval', type: 'bool' }, { key: 'active', label: 'In use', type: 'bool' },
    ],
  },
};
const NEW: Record<string, any> = {
  leave: { name: '', code: '', days_per_year: 0, accrual: 'yearly', max_carry_days: 0, max_balance: null, attachment_after_days: null, gender: null, branch_id: null, department_id: null,
    paid: true, track_balance: true, carry_forward: false, encashable: false, allow_half_day: true, allow_hourly: false, exclude_weekends: true, exclude_holidays: true, probation_allowed: true, active: true },
  overtime: { name: '', branch_id: null, regular_rate: 1.5, weekend_rate: 2, holiday_rate: 2.5, min_minutes: 30, max_daily_minutes: 0, max_monthly_minutes: 0, auto_calculate: true, active: true },
  deduction: { name: '', branch_id: null, type: 'late', method: 'per_minute', value: 0, grace_minutes: 0, free_times: 0, monthly_limit: null, needs_approval: true, active: true },
};

const canCreate = can('hrm.create');
const canEdit = can('hrm.edit');
const canDelete = can('hrm.delete');
const lockedBranch = currentUser.value?.branch_id || 0;
const kind = ref('leave');
const rows = ref<any[]>([]);
const departments = ref<any[]>([]);
const form = ref<any>(null);
const error = ref('');
const message = ref('');

const branchName = (id: number | null) => (id ? branches.value.find((b: any) => b.id === id)?.name || '#' + id : 'All branches');

function options(fl: Field) {
  switch (fl.options) {
    case 'accrual': return [{ v: 'yearly', t: 'All on 1 January' }, { v: 'monthly', t: '1/12 each month' }, { v: 'none', t: 'Not given (added by hand)' }];
    case 'gender': return [{ v: null, t: 'Everyone' }, { v: 'Female', t: 'Women (e.g. maternity)' }, { v: 'Male', t: 'Men (e.g. paternity)' }];
    case 'branch': return [...(lockedBranch ? [] : [{ v: null, t: 'All branches' }]), ...branches.value.filter((b: any) => !lockedBranch || b.id === lockedBranch).map((b: any) => ({ v: b.id, t: b.name }))];
    case 'department': return [{ v: null, t: 'All departments' }, ...departments.value.map(d => ({ v: d.id, t: d.name }))];
    case 'dtype': return ['late', 'early_leave', 'absence', 'half_day', 'missing_punch', 'unpaid_leave'].map(v => ({ v, t: label(v) }));
    case 'method': return [{ v: 'fixed', t: 'A fixed amount each time' }, { v: 'percent', t: '% of the monthly basic' }, { v: 'per_minute', t: '$ per minute' }, { v: 'per_hour', t: '$ per hour' }, { v: 'day_salary', t: 'Days of pay' }];
    default: return [];
  }
}

function summary(r: any) {
  if (kind.value === 'leave') {
    return (r.paid ? 'Paid' : 'Unpaid') + (r.track_balance ? ' · ' + r.days_per_year + ' days a year, ' + { yearly: 'given 1 Jan', monthly: 'given monthly', none: 'added by hand' }[r.accrual as string] : ' · no balance')
      + (r.carry_forward ? ' · carry up to ' + r.max_carry_days : '') + (r.encashable ? ' · can be encashed' : '') + (r.allow_half_day ? ' · half days' : '') + (r.allow_hourly ? ' · hourly' : '')
      + (r.probation_allowed ? '' : ' · not on probation') + (r.gender ? ' · ' + r.gender + ' only' : '');
  }
  if (kind.value === 'overtime') {
    return '×' + r.regular_rate + ' work day · ×' + r.weekend_rate + ' day off · ×' + r.holiday_rate + ' holiday · from ' + r.min_minutes + ' min'
      + (r.max_daily_minutes ? ' · most ' + r.max_daily_minutes / 60 + ' h a day' : '') + (r.max_monthly_minutes ? ' · ' + r.max_monthly_minutes / 60 + ' h a month' : '') + (r.auto_calculate ? ' · from attendance' : '');
  }
  const how: Record<string, string> = { fixed: money(r.value) + ' each time', percent: r.value + '% of basic', per_minute: money(r.value) + ' a minute', per_hour: money(r.value) + ' an hour', day_salary: r.value + ' day(s) of pay' };
  return label(r.type) + ': ' + how[r.method] + (r.grace_minutes ? ' · ' + r.grace_minutes + ' min free' : '') + (r.free_times ? ' · first ' + r.free_times + ' free' : '')
    + (r.monthly_limit !== null ? ' · most ' + money(r.monthly_limit) + ' a month' : '') + (r.needs_approval ? ' · needs approval' : '');
}

async function load() {
  error.value = '';
  try {
    rows.value = await api.get(KINDS[kind.value].url);
  } catch (e: any) {
    error.value = e.message;
  }
}

function edit(r: any) {
  form.value = r ? { ...r } : { ...NEW[kind.value], branch_id: lockedBranch || null };
}

async function save() {
  error.value = '';
  message.value = '';
  const k = KINDS[kind.value];
  const body: Record<string, any> = {};
  for (const fl of k.fields) {
    let v = form.value[fl.key];
    if (fl.type === 'number') v = v === '' || v === null || v === undefined ? (fl.empty !== undefined ? fl.empty : 0) : Number(v);
    if (fl.type === 'bool') v = !!v;
    body[fl.key] = v;
  }
  try {
    if (form.value.id) await api.put(k.url + '/' + form.value.id, body); else await api.post(k.url, body);
    message.value = body.name + ' saved.';
    form.value = null;
    await load();
  } catch (e: any) {
    error.value = e.message;
  }
}

async function remove(r: any) {
  if (!confirm('Delete ' + r.name + '?')) return;
  try {
    await api.del(KINDS[kind.value].url + '/' + r.id);
    await load();
  } catch (e: any) {
    error.value = e.message;
  }
}

onMounted(async () => {
  loadBranches().catch(() => {});
  departments.value = await api.get('/departments').catch(() => []);
  load();
});
</script>
