<template>
  <div style="display:grid;gap:14px">
    <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
      <input type="date" v-model="date" @change="load" :style="ui.input" />
      <div @click="date = today(); load()" :style="ui.btn">Today</div>
      <select v-model="dayStatus" @change="load" :style="ui.input">
        <option value="">Everyone</option>
        <option v-for="s in STATUSES" :key="s" :value="s">{{ label(s) }}</option>
      </select>
      <div :style="ui.muted + ';margin-left:auto'">{{ rows.length }} staff</div>
    </div>
    <div v-if="message" :style="ui.ok">{{ message }}</div>
    <div v-if="error" :style="ui.err">{{ error }}</div>

    <div :style="ui.card">
      <div :style="ui.head + ';display:grid;' + grid"><div>Employee</div><div>Status</div><div>In</div><div>Out</div><div>Worked</div><div>Break</div><div>Late</div><div>Extra</div><div></div></div>
      <template v-for="r in rows" :key="r.employee_id">
        <div :style="ui.row + ';display:grid;' + grid">
          <div><div style="font-weight:600">{{ r.name }}</div><div :style="ui.muted">{{ r.code }} · {{ r.department || 'No department' }}</div></div>
          <div><span :style="badge(r.status)">{{ label(r.status) }}</span><span v-if="r.attendance?.remote" :style="ui.muted"> · remote</span><span v-if="r.attendance?.corrected" :style="ui.muted"> · fixed</span></div>
          <div :style="ui.mono">{{ time(r.attendance?.first_in_at) }}</div>
          <div :style="ui.mono">{{ time(r.attendance?.last_out_at) }}</div>
          <div :style="ui.mono">{{ r.attendance ? hours(r.attendance.worked_minutes) : '—' }}</div>
          <div :style="ui.mono">{{ r.attendance?.break_minutes ? r.attendance.break_minutes + 'm' : '—' }}</div>
          <div :style="ui.mono + (r.attendance?.late_minutes ? ';color:#8a5300' : '')">{{ r.attendance?.late_minutes ? r.attendance.late_minutes + 'm' : '—' }}</div>
          <div :style="ui.mono">{{ r.attendance?.extra_minutes ? r.attendance.extra_minutes + 'm' : '—' }}</div>
          <div style="display:flex;gap:5px;justify-content:flex-end;flex-wrap:wrap">
            <template v-if="canEdit && date === today()">
              <div v-for="b in buttons(r)" :key="b.type" @click="punch(r, b.type)" :style="b.type === 'in' ? ui.btnGood : ui.btnSmall">{{ b.name }}</div>
            </template>
            <div v-if="r.attendance" @click="toggle(r)" :style="ui.btnSmall">{{ open[r.employee_id] ? 'Hide' : 'Punches' }}</div>
            <div v-if="canEdit" @click="startFix(r)" :style="ui.btnSmall">Fix</div>
          </div>
        </div>
        <div v-if="open[r.employee_id]" style="padding:8px 16px 12px 32px;background:#fafcfe;border-bottom:1px solid #f0f4f8;font-size:12px;display:flex;gap:14px;flex-wrap:wrap">
          <span v-for="p in open[r.employee_id].punches" :key="p.id"><b>{{ label(p.type) }}</b> <span :style="ui.mono">{{ time(p.punched_at) }}</span> <span :style="ui.muted">{{ p.source }}</span></span>
          <span v-if="open[r.employee_id].shift" :style="ui.muted">Shift {{ open[r.employee_id].shift.name }} {{ open[r.employee_id].shift.start_time }}–{{ open[r.employee_id].shift.end_time }}</span>
        </div>
      </template>
      <div v-if="!loading && !rows.length" style="padding:30px;text-align:center;color:#7d8b9c;font-size:13px">Nobody to show.</div>
    </div>

    <!-- Attendance fix requests -->
    <div :style="ui.card">
      <div :style="ui.pad + ';display:flex;align-items:center;gap:10px'"><div :style="ui.title">Attendance fix requests</div><div :style="ui.muted">Approve them in Approvals.</div></div>
      <div :style="ui.head + ';display:grid;grid-template-columns:90px 1.2fr 100px 120px 1.6fr 100px 80px;gap:12px'"><div>Ref</div><div>Employee</div><div>Day</div><div>Asked</div><div>Reason</div><div>Status</div><div></div></div>
      <div v-for="c in corrections" :key="c.id" :style="ui.row + ';display:grid;grid-template-columns:90px 1.2fr 100px 120px 1.6fr 100px 80px;gap:12px'">
        <div :style="ui.mono">{{ c.ref }}</div>
        <div>{{ c.employee?.first }} {{ c.employee?.last }}</div>
        <div>{{ dmy(c.date) }}</div>
        <div :style="ui.mono">{{ time(c.in_at) }}–{{ time(c.out_at) }}</div>
        <div>{{ c.reason }}</div>
        <div><span :style="badge(c.status)">{{ c.status }}</span></div>
        <div><div v-if="c.status === 'pending' && canEdit" @click="cancelFix(c)" :style="ui.btnSmall">Cancel</div></div>
      </div>
      <div v-if="!corrections.length" :style="ui.row + ';color:#8b98a8'">No requests.</div>
    </div>

    <!-- Fix a day by hand -->
    <div v-if="fix" :style="ui.overlay" @click.self="fix = null">
      <div :style="ui.dialog">
        <div :style="ui.title">Fix {{ fix.name }} · {{ dmy(date) }}</div>
        <div :style="ui.muted">The day's punches are replaced by these times.</div>
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px">
          <label :style="ui.label">Clock in<input type="datetime-local" v-model="fix.in_at" :style="ui.input" /></label>
          <label :style="ui.label">Clock out<input type="datetime-local" v-model="fix.out_at" :style="ui.input" /></label>
          <label :style="ui.label">Break (min)<input type="number" min="0" v-model="fix.break_minutes" placeholder="keep" :style="ui.input" /></label>
        </div>
        <label :style="ui.label">Note<input v-model="fix.note" :style="ui.input" /></label>
        <div style="display:flex;gap:8px;justify-content:flex-end">
          <div @click="fix = null" :style="ui.btn">Cancel</div>
          <div @click="saveFix" :style="ui.btnMain">Save</div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
// HRM > Daily attendance.
// API: GET /attendances/daily?date=&day_status=, POST /attendances/punch, GET /attendances/{id},
//      POST /attendances/manual, GET /attendance-corrections, POST /attendance-corrections/{id}/cancel
import { onMounted, reactive, ref } from 'vue';
import { api, ui, badge, label, dmy, time, hours, today } from '../../api/hrm.js';
import { can } from '../../api/auth.js';

const STATUSES = ['present', 'late', 'half_day', 'missing_punch', 'absent', 'not_in_yet', 'leave', 'weekend', 'holiday'];
const grid = 'grid-template-columns:1.6fr 1.1fr 0.6fr 0.6fr 0.7fr 0.6fr 0.6fr 0.6fr 1.9fr;gap:10px';
const canEdit = can('hrm.edit');

const date = ref(today());
const dayStatus = ref('');
const rows = ref<any[]>([]);
const corrections = ref<any[]>([]);
const open = reactive<Record<number, any>>({});
const fix = ref<any>(null);
const loading = ref(true);
const error = ref('');
const message = ref('');

// The buttons for the next punch: not in yet -> In; in -> Break / Out; clocked out -> In again.
function buttons(r: any) {
  const a = r.attendance;
  if (!a || !a.first_in_at || a.last_out_at) return [{ type: 'in', name: 'In' }];
  return [{ type: 'break_start', name: 'Break' }, { type: 'break_end', name: 'Back' }, { type: 'out', name: 'Out' }];
}

async function load() {
  loading.value = true;
  error.value = '';
  try {
    rows.value = await api.get('/attendances/daily?date=' + date.value + (dayStatus.value ? '&day_status=' + dayStatus.value : ''));
    corrections.value = await api.get('/attendance-corrections?from=' + date.value.slice(0, 8) + '01');
  } catch (e: any) {
    error.value = e.message;
  } finally {
    loading.value = false;
  }
}

async function punch(r: any, type: string) {
  message.value = '';
  error.value = '';
  try {
    await api.post('/attendances/punch', { employee_id: r.employee_id, type });
    message.value = r.name + ': ' + label(type) + ' at ' + new Date().toTimeString().slice(0, 5);
    delete open[r.employee_id];
    await load();
  } catch (e: any) {
    error.value = e.message;
  }
}

async function toggle(r: any) {
  if (open[r.employee_id]) {
    delete open[r.employee_id];
    return;
  }
  open[r.employee_id] = await api.get('/attendances/' + r.attendance.id);
}

function startFix(r: any) {
  const a = r.attendance;
  fix.value = {
    employee_id: r.employee_id, name: r.name,
    in_at: (a?.first_in_at || date.value + ' 09:00').replace(' ', 'T'),
    out_at: (a?.last_out_at || date.value + ' 17:00').replace(' ', 'T'),
    break_minutes: a?.break_minutes ?? '', note: '',
  };
}

async function saveFix() {
  error.value = '';
  const f = fix.value;
  try {
    await api.post('/attendances/manual', {
      employee_id: f.employee_id, date: date.value, in_at: f.in_at.replace('T', ' '), out_at: f.out_at ? f.out_at.replace('T', ' ') : null,
      break_minutes: f.break_minutes === '' ? null : Number(f.break_minutes), note: f.note || null,
    });
    message.value = f.name + "'s day is fixed.";
    fix.value = null;
    await load();
  } catch (e: any) {
    error.value = e.message;
  }
}

async function cancelFix(c: any) {
  try {
    await api.post('/attendance-corrections/' + c.id + '/cancel');
    await load();
  } catch (e: any) {
    error.value = e.message;
  }
}

onMounted(load);
</script>
