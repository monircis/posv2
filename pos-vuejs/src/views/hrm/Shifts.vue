<template>
  <div style="display:grid;gap:14px">
    <div v-if="message" :style="ui.ok">{{ message }}</div>
    <div v-if="error" :style="ui.err">{{ error }}</div>

    <div :style="ui.card">
      <div :style="ui.pad + ';display:flex;align-items:center'">
        <div :style="ui.title">Shifts</div>
        <div v-if="canCreate" @click="edit(null)" :style="ui.btnMain + ';margin-left:auto'">+ New shift</div>
      </div>
      <div :style="ui.head + ';display:grid;' + grid"><div>Code</div><div>Name</div><div>Time</div><div>Break</div><div>Grace in / out</div><div>Half day under</div><div>Days off</div><div>Branch</div><div></div></div>
      <div v-for="s in shifts" :key="s.id" :style="ui.row + ';display:grid;' + grid + (s.active ? '' : ';color:#9aa7b6')">
        <div :style="ui.mono">{{ s.code }}</div>
        <div style="font-weight:600">{{ s.name }}<span v-if="!s.active" :style="ui.muted"> · off</span></div>
        <div :style="ui.mono">{{ s.flexible ? 'Flexible ' + hours(s.required_minutes) : s.start_time + '–' + s.end_time }}<span v-if="s.crosses_midnight" :style="ui.muted"> (next day)</span></div>
        <div>{{ s.break_minutes }}m</div>
        <div>{{ s.late_grace_minutes }}m / {{ s.early_grace_minutes }}m</div>
        <div>{{ s.half_day_minutes ? hours(s.half_day_minutes) : '—' }}</div>
        <div>{{ (s.weekly_off || []).map((d: number) => DAYS[d]).join(', ') || 'none' }}</div>
        <div>{{ branchName(s.branch_id) }}</div>
        <div style="display:flex;gap:5px;justify-content:flex-end">
          <div v-if="canEdit" @click="edit(s)" :style="ui.btnSmall">Edit</div>
          <div v-if="canDelete" @click="removeShift(s)" :style="ui.btnBad">Delete</div>
        </div>
      </div>
      <div v-if="!shifts.length" :style="ui.row + ';color:#8b98a8'">No shifts yet.</div>
    </div>

    <div :style="ui.card">
      <div :style="ui.pad + ';display:flex;align-items:center'">
        <div>
          <div :style="ui.title">Who works which shift</div>
          <div :style="ui.muted">An employee's own shift beats their department's, which beats their branch's.</div>
        </div>
        <div v-if="canEdit && shifts.length" @click="startAssign" :style="ui.btnMain + ';margin-left:auto'">+ Assign</div>
      </div>
      <div :style="ui.head + ';display:grid;grid-template-columns:1fr 1.4fr 1.2fr 1fr 1fr 1.2fr 0.6fr;gap:10px'"><div>For</div><div>Who</div><div>Shift</div><div>From</div><div>To</div><div>Note</div><div></div></div>
      <div v-for="a in assignments" :key="a.id" :style="ui.row + ';display:grid;grid-template-columns:1fr 1.4fr 1.2fr 1fr 1fr 1.2fr 0.6fr;gap:10px'">
        <div>{{ label(a.target_type) }}</div>
        <div style="font-weight:600">{{ a.target_name }}</div>
        <div>{{ a.rotation_shift_ids ? 'Rotation: ' + a.rotation_shift_ids.map(shiftCode).join(' → ') + ' every ' + a.rotation_days + ' days' : a.shift?.name + ' ' + a.shift?.start_time + '–' + a.shift?.end_time }}</div>
        <div>{{ dmy(a.date_from) }}</div>
        <div>{{ a.date_to ? dmy(a.date_to) : 'ongoing' }}</div>
        <div :style="ui.muted">{{ a.note }}</div>
        <div><div v-if="canEdit" @click="removeAssign(a)" :style="ui.btnSmall">Delete</div></div>
      </div>
      <div v-if="!assignments.length" :style="ui.row + ';color:#8b98a8'">No shifts assigned: staff use the default days off and have no late / early rules.</div>
    </div>

    <!-- Shift form -->
    <div v-if="form" :style="ui.overlay" @click.self="form = null">
      <div :style="ui.dialog">
        <div :style="ui.title">{{ form.id ? 'Edit shift' : 'New shift' }}</div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
          <label :style="ui.label">Name<input v-model="form.name" :style="ui.input" /></label>
          <label :style="ui.label">Code<input v-model="form.code" :style="ui.input" /></label>
          <label :style="ui.label">Start<input type="time" v-model="form.start_time" :style="ui.input" /></label>
          <label :style="ui.label">End (before start = next day)<input type="time" v-model="form.end_time" :style="ui.input" /></label>
          <label :style="ui.label">Break minutes<input type="number" min="0" v-model.number="form.break_minutes" :style="ui.input" /></label>
          <label :style="ui.label">Half day if worked under (min, 0 = off)<input type="number" min="0" v-model.number="form.half_day_minutes" :style="ui.input" /></label>
          <label :style="ui.label">Late after (grace min)<input type="number" min="0" v-model.number="form.late_grace_minutes" :style="ui.input" /></label>
          <label :style="ui.label">Early before (grace min)<input type="number" min="0" v-model.number="form.early_grace_minutes" :style="ui.input" /></label>
          <label :style="ui.label">Branch
            <select v-model="form.branch_id" :style="ui.input"><option v-if="!lockedBranch" :value="null">All branches</option><option v-for="b in branches" :key="b.id" :value="b.id">{{ b.name }}</option></select>
          </label>
          <label :style="ui.label + ';align-content:end'"><span><input type="checkbox" v-model="form.flexible" /> Flexible hours</span></label>
          <label v-if="form.flexible" :style="ui.label">Minutes to work a day<input type="number" min="0" v-model.number="form.required_minutes" :style="ui.input" /></label>
        </div>
        <div :style="ui.label">Days off
          <div style="display:flex;gap:6px;flex-wrap:wrap">
            <label v-for="(d, i) in DAYS" :key="d" style="font-weight:500"><input type="checkbox" :checked="form.weekly_off.includes(i)" @change="toggleDay(i)" /> {{ d }}</label>
          </div>
        </div>
        <label style="font-size:12.5px"><input type="checkbox" v-model="form.active" /> In use</label>
        <div style="display:flex;gap:8px;justify-content:flex-end">
          <div @click="form = null" :style="ui.btn">Cancel</div>
          <div @click="saveShift" :style="ui.btnMain">Save</div>
        </div>
      </div>
    </div>

    <!-- Assign form -->
    <div v-if="assign" :style="ui.overlay" @click.self="assign = null">
      <div :style="ui.dialog">
        <div :style="ui.title">Assign a shift</div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
          <label :style="ui.label">For
            <select v-model="assign.target_type" @change="assign.target_id = ''" :style="ui.input"><option value="employee">An employee</option><option value="department">A department</option><option value="branch">A branch</option></select>
          </label>
          <label :style="ui.label">Who
            <select v-model="assign.target_id" :style="ui.input">
              <option value="">Pick…</option>
              <option v-for="t in targets" :key="t.id" :value="t.id">{{ t.name }}</option>
            </select>
          </label>
          <label :style="ui.label">Shift
            <select v-model="assign.shift_id" :style="ui.input"><option v-for="s in shifts.filter(s => s.active)" :key="s.id" :value="s.id">{{ s.name }} {{ s.start_time }}–{{ s.end_time }}</option></select>
          </label>
          <label :style="ui.label + ';align-content:end'"><span><input type="checkbox" v-model="assign.rotate" /> Rotate between shifts</span></label>
          <label :style="ui.label">From<input type="date" v-model="assign.date_from" :style="ui.input" /></label>
          <label :style="ui.label">To (empty = ongoing)<input type="date" v-model="assign.date_to" :style="ui.input" /></label>
        </div>
        <template v-if="assign.rotate">
          <div :style="ui.label">Shifts in turn (in this order)
            <div style="display:flex;gap:8px;flex-wrap:wrap"><label v-for="s in shifts.filter(s => s.active)" :key="s.id" style="font-weight:500"><input type="checkbox" :value="s.id" v-model="assign.rotation_shift_ids" /> {{ s.name }}</label></div>
          </div>
          <label :style="ui.label">Change every (days)<input type="number" min="1" v-model.number="assign.rotation_days" :style="ui.input + ';width:120px'" /></label>
        </template>
        <label :style="ui.label">Note<input v-model="assign.note" :style="ui.input" /></label>
        <div style="display:flex;gap:8px;justify-content:flex-end">
          <div @click="assign = null" :style="ui.btn">Cancel</div>
          <div @click="saveAssign" :style="ui.btnMain">Assign</div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
// HRM > Shifts. API: GET|POST /shifts, PUT|DELETE /shifts/{id}, GET|POST /shift-assignments, DELETE /shift-assignments/{id}
import { computed, onMounted, ref } from 'vue';
import { api, ui, label, dmy, hours, today, DAYS, loadStaff } from '../../api/hrm.js';
import { branches as allBranches, loadBranches } from '../../api/branches.js';
import { can, currentUser } from '../../api/auth.js';

const grid = 'grid-template-columns:0.6fr 1.1fr 1.3fr 0.6fr 0.9fr 0.8fr 1fr 1fr 1fr;gap:10px';
const canCreate = can('hrm.create');
const canEdit = can('hrm.edit');
const canDelete = can('hrm.delete');
const lockedBranch = currentUser.value?.branch_id || 0;
const branches = computed(() => allBranches.value.filter((b: any) => !lockedBranch || b.id === lockedBranch));
const shifts = ref<any[]>([]);
const assignments = ref<any[]>([]);
const departments = ref<any[]>([]);
const staff = ref<any[]>([]);
const form = ref<any>(null);
const assign = ref<any>(null);
const error = ref('');
const message = ref('');

const branchName = (id: number | null) => (id ? allBranches.value.find((b: any) => b.id === id)?.name || '#' + id : 'All branches');
const shiftCode = (id: number) => shifts.value.find(s => s.id === id)?.code || '#' + id;
const targets = computed(() => {
  const t = assign.value?.target_type;
  if (t === 'branch') return branches.value.map((b: any) => ({ id: b.id, name: b.name }));
  if (t === 'department') return departments.value.map(d => ({ id: d.id, name: d.name }));
  return staff.value.map(s => ({ id: s.id, name: s.first + ' ' + s.last }));
});

async function load() {
  [shifts.value, assignments.value] = await Promise.all([api.get('/shifts'), api.get('/shift-assignments')]);
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

function edit(s: any) {
  form.value = s ? { ...s, weekly_off: [...(s.weekly_off || [])] } : {
    name: '', code: '', start_time: '09:00', end_time: '17:00', break_minutes: 30, late_grace_minutes: 10, early_grace_minutes: 10,
    half_day_minutes: 0, flexible: false, required_minutes: 0, weekly_off: [0], active: true, branch_id: lockedBranch || null,
  };
}
function toggleDay(i: number) {
  const list = form.value.weekly_off;
  const at = list.indexOf(i);
  if (at >= 0) list.splice(at, 1); else list.push(i);
}
const saveShift = () => act(async () => {
  const f = form.value;
  const body = { branch_id: f.branch_id, name: f.name, code: f.code, start_time: f.start_time, end_time: f.end_time, break_minutes: f.break_minutes,
    late_grace_minutes: f.late_grace_minutes, early_grace_minutes: f.early_grace_minutes, half_day_minutes: f.half_day_minutes,
    flexible: f.flexible, required_minutes: f.required_minutes || 0, weekly_off: f.weekly_off, active: f.active };
  if (f.id) await api.put('/shifts/' + f.id, body); else await api.post('/shifts', body);
  form.value = null;
}, 'Shift saved.');
const removeShift = (s: any) => confirm('Delete shift ' + s.name + '?') && act(() => api.del('/shifts/' + s.id), 'Shift deleted.');

function startAssign() {
  assign.value = { target_type: 'employee', target_id: '', shift_id: shifts.value.find(s => s.active)?.id, date_from: today(), date_to: '', rotate: false, rotation_shift_ids: [], rotation_days: 7, note: '' };
}
const saveAssign = () => act(async () => {
  const a = assign.value;
  await api.post('/shift-assignments', {
    shift_id: a.rotate && a.rotation_shift_ids.length ? a.rotation_shift_ids[0] : a.shift_id, target_type: a.target_type, target_id: a.target_id,
    date_from: a.date_from, date_to: a.date_to || null, rotation_shift_ids: a.rotate ? a.rotation_shift_ids : null,
    rotation_days: a.rotate ? a.rotation_days : null, note: a.note || null,
  });
  assign.value = null;
}, 'Shift assigned.');
const removeAssign = (a: any) => act(() => api.del('/shift-assignments/' + a.id), 'Removed.');

onMounted(async () => {
  loadBranches().catch(() => {});
  [departments.value, staff.value] = await Promise.all([api.get('/departments').catch(() => []), loadStaff().catch(() => [])]);
  load().catch((e: any) => { error.value = e.message; });
});
</script>
