<template>
  <div style="display:grid;gap:14px">
    <div v-if="message" :style="ui.ok">{{ message }}</div>
    <div v-if="error" :style="ui.err">{{ error }}</div>

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(340px,1fr));gap:14px">
      <!-- Departments -->
      <div :style="ui.card">
        <div :style="ui.pad"><div :style="ui.title">Departments</div></div>
        <div v-for="d in departments" :key="d.id" :style="ui.row + ';display:flex;gap:10px;align-items:center'">
          <div style="font-weight:600">{{ d.name }}</div>
          <div :style="ui.muted">{{ branchName(d.branch_id) }} · manager: {{ staffName(d.manager_id) }}</div>
          <div style="margin-left:auto;display:flex;gap:5px">
            <div v-if="canEdit" @click="dep = { ...d }" :style="ui.btnSmall">Edit</div>
            <div v-if="canDelete" @click="removeDep(d)" :style="ui.btnBad">Delete</div>
          </div>
        </div>
        <div v-if="canCreate || dep.id" :style="ui.row + ';display:grid;grid-template-columns:1.2fr 1fr 1fr auto;gap:6px'">
          <input v-model="dep.name" placeholder="Department" :style="ui.input" />
          <select v-model="dep.branch_id" :style="ui.input"><option v-if="!lockedBranch" :value="null">All branches</option><option v-for="b in branches" :key="b.id" :value="b.id">{{ b.name }}</option></select>
          <select v-model="dep.manager_id" :style="ui.input"><option :value="null">No manager</option><option v-for="s in staff" :key="s.id" :value="s.id">{{ s.first }} {{ s.last }}</option></select>
          <div @click="saveDep" :style="ui.btn">{{ dep.id ? 'Save' : 'Add' }}</div>
        </div>
      </div>

      <!-- Designations -->
      <div :style="ui.card">
        <div :style="ui.pad"><div :style="ui.title">Designations (job titles)</div></div>
        <div v-for="d in designations" :key="d.id" :style="ui.row + ';display:flex;gap:10px;align-items:center'">
          <div style="font-weight:600">{{ d.name }}</div><div :style="ui.muted">{{ d.department?.name || 'Any department' }}</div>
          <div v-if="canDelete" @click="removeDes(d)" :style="ui.btnBad + ';margin-left:auto'">Delete</div>
        </div>
        <div v-if="canCreate" :style="ui.row + ';display:grid;grid-template-columns:1.2fr 1fr auto;gap:6px'">
          <input v-model="des.name" placeholder="Designation" :style="ui.input" />
          <select v-model="des.department_id" :style="ui.input"><option :value="null">Any department</option><option v-for="d in departments" :key="d.id" :value="d.id">{{ d.name }}</option></select>
          <div @click="saveDes" :style="ui.btn">Add</div>
        </div>
      </div>
    </div>

    <!-- Approval workflows -->
    <div :style="ui.card">
      <div :style="ui.pad"><div :style="ui.title">Approval workflows</div><div :style="ui.muted">Who approves, in order. A level with nobody (e.g. no reporting manager) is skipped. {{ isAdmin ? '' : 'Changing them needs the HRM administrator permission.' }}</div></div>
      <div v-for="w in workflows" :key="w.module" :style="ui.row + ';display:flex;gap:12px;align-items:center;flex-wrap:wrap'">
        <div style="font-weight:700;min-width:150px">{{ MODULE_NAMES[w.module] || w.module }}</div>
        <div style="display:flex;gap:6px;flex-wrap:wrap;align-items:center">
          <template v-for="(lv, i) in w.levels" :key="lv">
            <span :style="badge('leave')">{{ i + 1 }}. {{ label(lv) }}</span>
            <span v-if="isAdmin" @click="moveLevel(w, i)" title="Move up" style="cursor:pointer;color:#8b98a8">↑</span>
            <span v-if="isAdmin && w.levels.length > 1" @click="w.levels.splice(i, 1)" title="Remove" style="cursor:pointer;color:#b02020">×</span>
          </template>
          <select v-if="isAdmin" @change="addLevel(w, $event)" :style="ui.input + ';height:26px'">
            <option value="">+ level</option>
            <option v-for="lv in LEVELS.filter(l => !w.levels.includes(l))" :key="lv" :value="lv">{{ label(lv) }}</option>
          </select>
        </div>
        <label v-if="isAdmin" style="font-size:12.5px"><input type="checkbox" v-model="w.active" /> In use</label>
        <div v-if="isAdmin" @click="saveWorkflow(w)" :style="ui.btnSmall + ';margin-left:auto'">Save</div>
      </div>
    </div>

    <!-- Settings -->
    <div :style="ui.card + ';' + ui.pad + ';display:grid;gap:10px'">
      <div :style="ui.title">HRM settings</div>
      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:10px">
        <label :style="ui.label">Refuse a second punch within (minutes)<input type="number" min="0" v-model.number="settings.duplicate_punch_minutes" :disabled="!isAdmin" :style="ui.input" /></label>
        <label :style="ui.label">A clock-in stays open for (hours)<input type="number" min="4" v-model.number="settings.open_punch_hours" :disabled="!isAdmin" :style="ui.input" /></label>
        <label :style="ui.label">Working hours in a month<input type="number" step="0.01" v-model.number="settings.hours_per_month" :disabled="!isAdmin" :style="ui.input" /></label>
        <label :style="ui.label">Hours in a working day<input type="number" step="0.1" v-model.number="settings.hours_per_day" :disabled="!isAdmin" :style="ui.input" /></label>
        <label :style="ui.label">Days for a day's pay<input type="number" v-model.number="settings.salary_days_per_month" :disabled="!isAdmin" :style="ui.input" /></label>
      </div>
      <div :style="ui.label">Days off for staff with no shift
        <div style="display:flex;gap:8px;flex-wrap:wrap">
          <label v-for="(d, i) in DAYS" :key="d" style="font-weight:500"><input type="checkbox" :disabled="!isAdmin" :checked="(settings.weekly_off || []).includes(i)" @change="toggleOff(i)" /> {{ d }}</label>
        </div>
      </div>
      <div v-if="isAdmin"><div @click="saveSettings" :style="ui.btnMain">Save settings</div></div>
    </div>
  </div>
</template>

<script setup lang="ts">
// HRM > Setup. API: /departments, /designations, GET /approval-workflows, PUT /approval-workflows/{module},
//                   GET|PUT /hrm/settings (changes need hrm.admin)
import { computed, onMounted, reactive, ref } from 'vue';
import { api, ui, badge, label, DAYS, loadStaff } from '../../api/hrm.js';
import { branches as allBranches, loadBranches } from '../../api/branches.js';
import { can, currentUser } from '../../api/auth.js';

const LEVELS = ['reporting_manager', 'department_manager', 'hr_manager', 'administrator'];
const MODULE_NAMES: Record<string, string> = { leave: 'Leave', overtime: 'Overtime', correction: 'Attendance fixes', deduction: 'Deductions', payroll: 'Payroll' };
const canCreate = can('hrm.create');
const canEdit = can('hrm.edit');
const canDelete = can('hrm.delete');
const isAdmin = can('hrm.admin');
const lockedBranch = currentUser.value?.branch_id || 0;
const branches = computed(() => allBranches.value.filter((b: any) => !lockedBranch || b.id === lockedBranch));

const departments = ref<any[]>([]);
const designations = ref<any[]>([]);
const staff = ref<any[]>([]);
const workflows = ref<any[]>([]);
const settings = ref<any>({});
const dep = ref<any>({ name: '', branch_id: lockedBranch || null, manager_id: null });
const des = reactive<any>({ name: '', department_id: null });
const error = ref('');
const message = ref('');

const branchName = (id: number | null) => (id ? allBranches.value.find((b: any) => b.id === id)?.name || '#' + id : 'All branches');
const staffName = (id: number | null) => { const s = staff.value.find(s => s.id === id); return s ? s.first + ' ' + s.last : '—'; };

async function load() {
  [departments.value, designations.value, workflows.value, settings.value] = await Promise.all([
    api.get('/departments'), api.get('/designations'), api.get('/approval-workflows'), api.get('/hrm/settings'),
  ]);
}

async function act(fn: () => Promise<any>, done: string) {
  error.value = '';
  message.value = '';
  try {
    await fn();
    message.value = done;
    await load();
  } catch (e: any) {
    error.value = e.message;
  }
}

const saveDep = () => act(async () => {
  const d = dep.value;
  const body = { name: d.name, branch_id: d.branch_id, manager_id: d.manager_id, active: true };
  if (d.id) await api.put('/departments/' + d.id, body); else await api.post('/departments', body);
  dep.value = { name: '', branch_id: lockedBranch || null, manager_id: null };
}, 'Department saved.');
const removeDep = (d: any) => act(() => api.del('/departments/' + d.id), 'Deleted.');
const saveDes = () => act(async () => { await api.post('/designations', { ...des, active: true }); des.name = ''; }, 'Designation added.');
const removeDes = (d: any) => act(() => api.del('/designations/' + d.id), 'Deleted.');

function moveLevel(w: any, i: number) {
  if (i === 0) return;
  const [lv] = w.levels.splice(i, 1);
  w.levels.splice(i - 1, 0, lv);
}
function addLevel(w: any, ev: Event) {
  const el = ev.target as HTMLSelectElement;
  if (el.value) w.levels.push(el.value);
  el.value = '';
}
const saveWorkflow = (w: any) => act(() => api.put('/approval-workflows/' + w.module, { levels: w.levels, active: w.active }), (MODULE_NAMES[w.module] || w.module) + ' workflow saved. New requests use it.');

function toggleOff(i: number) {
  const list = [...(settings.value.weekly_off || [])];
  const at = list.indexOf(i);
  if (at >= 0) list.splice(at, 1); else list.push(i);
  settings.value.weekly_off = list;
}
const saveSettings = () => act(() => api.put('/hrm/settings', settings.value), 'Settings saved.');

onMounted(async () => {
  loadBranches().catch(() => {});
  staff.value = await loadStaff().catch(() => []);
  load().catch((e: any) => { error.value = e.message; });
});
</script>
