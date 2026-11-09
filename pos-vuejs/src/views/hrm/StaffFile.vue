<template>
  <div style="display:grid;gap:14px">
    <RouterLink :to="{ name: 'hrm-files' }" :style="ui.muted + ';text-decoration:none'">← All staff files</RouterLink>
    <div v-if="message" :style="ui.ok">{{ message }}</div>
    <div v-if="error" :style="ui.err">{{ error }}</div>

    <template v-if="p">
      <!-- Header -->
      <div :style="ui.card + ';' + ui.pad + ';display:flex;gap:16px;align-items:center;flex-wrap:wrap'">
        <img v-if="p.photo_url" :src="p.photo_url" style="width:64px;height:64px;border-radius:12px;object-fit:cover" />
        <div v-else style="width:64px;height:64px;border-radius:12px;background:var(--c-sidebar);color:#fff;display:grid;place-items:center;font-size:20px;font-weight:700">{{ (p.first[0] || '') + (p.last[0] || '') }}</div>
        <div>
          <div style="font-size:19px;font-weight:700">{{ p.name }} <span :style="badge(p.status)">{{ p.status }}</span></div>
          <div :style="ui.muted">{{ p.code }} · {{ p.designation?.name || p.role }} · {{ p.department?.name || 'No department' }} · {{ p.branch?.name }}</div>
          <div :style="ui.muted">Started {{ dmy(p.start) || '—' }}<template v-if="p.probation_end"> · probation until {{ dmy(p.probation_end) }}</template><template v-if="p.exit_date"> · last day {{ dmy(p.exit_date) }}</template></div>
        </div>
        <div v-if="canEdit" style="margin-left:auto;display:flex;gap:8px;flex-wrap:wrap">
          <label :style="ui.btn">Photo<input type="file" accept="image/*" @change="uploadPhoto" style="display:none" /></label>
          <div v-if="p.status === 'Probation'" @click="confirmEmp" :style="ui.btn">Confirm</div>
          <div v-if="!['Resigned', 'Terminated'].includes(p.status)" @click="exitForm = { type: 'Resigned', exit_date: '', reason: '' }" :style="ui.btn">Resign / terminate</div>
          <div @click="save" :style="ui.btnMain">Save changes</div>
        </div>
      </div>

      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:14px">
        <!-- Job -->
        <div :style="ui.card + ';' + ui.pad + ';display:grid;gap:10px'">
          <div :style="ui.title">Job</div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
            <label :style="ui.label">Department
              <select v-model="f.department_id" :style="ui.input"><option :value="null">—</option><option v-for="d in departments" :key="d.id" :value="d.id">{{ d.name }}</option></select>
            </label>
            <label :style="ui.label">Designation
              <select v-model="f.designation_id" :style="ui.input"><option :value="null">—</option><option v-for="d in designations" :key="d.id" :value="d.id">{{ d.name }}</option></select>
            </label>
            <label :style="ui.label">Reporting manager
              <select v-model="f.reporting_manager_id" :style="ui.input"><option :value="null">—</option><option v-for="s in staff.filter(s => s.id !== p.id)" :key="s.id" :value="s.id">{{ s.first }} {{ s.last }}</option></select>
            </label>
            <label :style="ui.label">Job title<input v-model="f.role" :style="ui.input" /></label>
            <label :style="ui.label">Employment type
              <select v-model="f.type" :style="ui.input"><option v-for="t in TYPES" :key="t">{{ t }}</option></select>
            </label>
            <label :style="ui.label">Start date<input type="date" v-model="f.start" :style="ui.input" /></label>
            <label :style="ui.label">Probation (months)<input type="number" min="0" max="24" v-model.number="f.probation_months" :style="ui.input" /></label>
            <label :style="ui.label">Sign-in user id (My HR)<input v-model="f.user_id" placeholder="not linked" :style="ui.input" /></label>
          </div>
          <label :style="ui.label">Job description<textarea v-model="f.job_description" rows="2" :style="ui.input + ';height:auto;padding:6px 9px'"></textarea></label>
        </div>

        <!-- Personal -->
        <div :style="ui.card + ';' + ui.pad + ';display:grid;gap:10px'">
          <div :style="ui.title">Personal and contact</div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
            <label :style="ui.label">First name<input v-model="f.first" :style="ui.input" /></label>
            <label :style="ui.label">Last name<input v-model="f.last" :style="ui.input" /></label>
            <label :style="ui.label">Gender<select v-model="f.gender" :style="ui.input"><option :value="null">—</option><option>Male</option><option>Female</option><option>Other</option></select></label>
            <label :style="ui.label">Date of birth<input v-model="f.dob" placeholder="1990-05-14" :style="ui.input" /></label>
            <label :style="ui.label">Marital status<input v-model="f.marital_status" :style="ui.input" /></label>
            <label :style="ui.label">Nationality<input v-model="f.nationality" :style="ui.input" /></label>
            <label :style="ui.label">Mobile<input v-model="f.phone" :style="ui.input" /></label>
            <label :style="ui.label">Email<input v-model="f.email" :style="ui.input" /></label>
            <label :style="ui.label">Address<input v-model="f.address" :style="ui.input" /></label>
            <label :style="ui.label">City<input v-model="f.city" :style="ui.input" /></label>
            <label :style="ui.label">Emergency contact<input v-model="f.emergency_name" :style="ui.input" /></label>
            <label :style="ui.label">Emergency phone<input v-model="f.emergency_phone" :style="ui.input" /></label>
            <label :style="ui.label">Relation<input v-model="f.emergency_relation" :style="ui.input" /></label>
          </div>
        </div>

        <!-- Bank -->
        <div v-if="canPay" :style="ui.card + ';' + ui.pad + ';display:grid;gap:10px'">
          <div :style="ui.title">Bank account</div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
            <label :style="ui.label">Bank<input v-model="f.bank_name" :style="ui.input" /></label>
            <label :style="ui.label">Account name<input v-model="f.bank_account_name" :style="ui.input" /></label>
            <label :style="ui.label">BSB<input v-model="f.bank_bsb" :style="ui.input" /></label>
            <label :style="ui.label">Account number<input v-model="f.bank_account_number" :style="ui.input" /></label>
          </div>
          <RouterLink :to="{ name: 'hrm-salary', query: { employee: p.id } }" :style="ui.muted">Salary structure and history →</RouterLink>
        </div>

        <!-- Leave and this month -->
        <div :style="ui.card + ';' + ui.pad + ';display:grid;gap:8px'">
          <div :style="ui.title">Leave and attendance</div>
          <div v-for="b in p.leave_balances" :key="b.policy_id" style="display:flex;justify-content:space-between;font-size:12.5px"><span>{{ b.name }} leave</span><span :style="ui.mono">{{ b.available }} days left<span v-if="b.pending"> · {{ b.pending }} waiting</span></span></div>
          <div v-if="month" style="border-top:1px solid #f0f4f8;padding-top:8px;font-size:12.5px;display:grid;gap:3px">
            <div>This month: <b>{{ month.summary.attended }}</b> days at work, {{ month.summary.late }} late, {{ month.summary.absent }} absent, {{ month.summary.leave }} leave</div>
            <div :style="ui.muted">Worked {{ hours(month.summary.worked_minutes) }} · extra {{ hours(month.summary.extra_minutes) }} · attendance {{ month.summary.attendance_percent ?? '—' }}%</div>
          </div>
          <div style="border-top:1px solid #f0f4f8;padding-top:8px;display:flex;gap:4px;flex-wrap:wrap">
            <div v-for="d in schedule" :key="d.date" :title="d.holiday || ''" :style="'padding:4px 6px;border-radius:6px;font-size:10.5px;text-align:center;min-width:44px;' + (d.holiday ? 'background:#f3ecfd;color:#5b34a8' : d.day_off ? 'background:#eef2f6;color:#8b98a8' : 'background:#e7f6ee;color:#0f6b43')">
              {{ d.day }} {{ d.date.slice(8) }}<br />{{ d.holiday ? 'Holiday' : d.day_off ? 'Off' : d.shift ? d.shift.code : '—' }}
            </div>
          </div>
        </div>
      </div>

      <!-- Documents -->
      <div :style="ui.card">
        <div :style="ui.pad + ';display:flex;gap:8px;align-items:center;flex-wrap:wrap'">
          <div :style="ui.title">Documents</div>
          <template v-if="canEdit">
            <input v-model="doc.title" placeholder="Title (e.g. Visa)" :style="ui.input + ';margin-left:auto'" />
            <input v-model="doc.type" placeholder="Type" :style="ui.input + ';width:110px'" />
            <input type="date" v-model="doc.expires_on" title="Expires on" :style="ui.input" />
            <input type="file" ref="docFile" style="font-size:12px;max-width:200px" />
            <div @click="uploadDoc" :style="ui.btnMain">Upload</div>
          </template>
        </div>
        <div v-for="d in p.documents" :key="d.id" :style="ui.row + ';display:flex;gap:12px'">
          <div style="font-weight:600">{{ d.title }}</div><div :style="ui.muted">{{ d.type }} · {{ d.file_name }}<template v-if="d.expires_on"> · expires {{ dmy(d.expires_on) }}</template></div>
          <div style="margin-left:auto;display:flex;gap:6px">
            <div @click="download('/employee-documents/' + d.id + '/download', d.file_name || 'document')" :style="ui.btnSmall">Download</div>
            <div v-if="canEdit" @click="removeDoc(d)" :style="ui.btnBad">Delete</div>
          </div>
        </div>
        <div v-if="!p.documents.length" :style="ui.row + ';color:#8b98a8'">No documents.</div>
      </div>

      <!-- Education and experience -->
      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:14px">
        <div :style="ui.card">
          <div :style="ui.pad"><div :style="ui.title">Education</div></div>
          <div v-for="e in p.educations" :key="e.id" :style="ui.row + ';display:flex;gap:10px'">
            <div><b>{{ e.degree }}</b> <span :style="ui.muted">{{ e.institution }} {{ e.year }} {{ e.result }}</span></div>
            <div v-if="canEdit" @click="removeLine('educations', e.id)" :style="ui.btnBad + ';margin-left:auto'">Delete</div>
          </div>
          <div v-if="canEdit" :style="ui.row + ';display:grid;grid-template-columns:1.4fr 1.2fr 0.6fr auto;gap:6px'">
            <input v-model="edu.degree" placeholder="Qualification" :style="ui.input" /><input v-model="edu.institution" placeholder="Where" :style="ui.input" />
            <input v-model="edu.year" placeholder="Year" :style="ui.input" /><div @click="addLine('educations', edu)" :style="ui.btn">Add</div>
          </div>
        </div>
        <div :style="ui.card">
          <div :style="ui.pad"><div :style="ui.title">Experience</div></div>
          <div v-for="e in p.experiences" :key="e.id" :style="ui.row + ';display:flex;gap:10px'">
            <div><b>{{ e.company }}</b> <span :style="ui.muted">{{ e.position }} {{ dmy(e.date_from) }}{{ e.date_to ? ' – ' + dmy(e.date_to) : '' }}</span></div>
            <div v-if="canEdit" @click="removeLine('experiences', e.id)" :style="ui.btnBad + ';margin-left:auto'">Delete</div>
          </div>
          <div v-if="canEdit" :style="ui.row + ';display:grid;grid-template-columns:1.2fr 1fr 1fr 1fr auto;gap:6px'">
            <input v-model="exp.company" placeholder="Company" :style="ui.input" /><input v-model="exp.position" placeholder="Position" :style="ui.input" />
            <input type="date" v-model="exp.date_from" :style="ui.input" /><input type="date" v-model="exp.date_to" :style="ui.input" />
            <div @click="addLine('experiences', exp)" :style="ui.btn">Add</div>
          </div>
        </div>
      </div>

      <!-- Status history -->
      <div :style="ui.card">
        <div :style="ui.pad"><div :style="ui.title">Status history</div></div>
        <div v-for="h in p.status_history" :key="h.id" :style="ui.row + ';display:flex;gap:12px'">
          <div :style="ui.mono">{{ dmy(h.date) }}</div>
          <div>{{ h.from_status || 'New' }} → <span :style="badge(h.to_status)">{{ h.to_status }}</span></div>
          <div :style="ui.muted">{{ h.note }} <template v-if="h.changed_by">· {{ h.changed_by }}</template></div>
        </div>
      </div>
    </template>

    <!-- Resign / terminate -->
    <div v-if="exitForm" :style="ui.overlay" @click.self="exitForm = null">
      <div :style="ui.dialog">
        <div :style="ui.title">Resignation or termination</div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
          <label :style="ui.label">Type<select v-model="exitForm.type" :style="ui.input"><option>Resigned</option><option>Terminated</option></select></label>
          <label :style="ui.label">Last day<input type="date" v-model="exitForm.exit_date" :style="ui.input" /></label>
        </div>
        <label :style="ui.label">Reason<input v-model="exitForm.reason" :style="ui.input" /></label>
        <div :style="ui.muted">They still count for attendance and pay up to the last day.</div>
        <div style="display:flex;gap:8px;justify-content:flex-end">
          <div @click="exitForm = null" :style="ui.btn">Cancel</div>
          <div @click="saveExit" :style="ui.btnMain">Save</div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
// HRM > Staff file: one employee's full record.
// API: GET/PUT /employees/{id}, POST /employees/{id}/confirm | exit | photo | documents | educations | experiences,
//      DELETE /employee-documents/{id}, /employee-educations/{id}, /employee-experiences/{id},
//      GET /employees/{id}/schedule, /employees/{id}/attendance
import { onMounted, reactive, ref } from 'vue';
import { useRoute } from 'vue-router';
import { api, ui, badge, dmy, hours, download, today } from '../../api/hrm.js';
import { can } from '../../api/auth.js';

const TYPES = ['Full-time', 'Part-time', 'Casual', 'Permanent', 'Contract', 'Temporary'];
// The fields PUT /employees/{id} takes (the API needs all the required ones every time).
const FIELDS = ['first', 'last', 'gender', 'marital_status', 'nationality', 'phone', 'email', 'address', 'city', 'dob', 'emerg', 'emergency_name',
  'emergency_phone', 'emergency_relation', 'role', 'job_description', 'dept', 'department_id', 'designation_id', 'reporting_manager_id',
  'branch_id', 'overtime_policy_id', 'start', 'probation_months', 'type', 'basis', 'rate', 'hours', 'super_fund', 'user_id',
  'bank_name', 'bank_account_name', 'bank_bsb', 'bank_account_number', 'code'];

const route = useRoute();
const id = Number(route.params.id);
const canEdit = can('hrm.edit');
const canPay = can('hrm.payroll');

const p = ref<any>(null);
const f = reactive<Record<string, any>>({});
const departments = ref<any[]>([]);
const designations = ref<any[]>([]);
const staff = ref<any[]>([]);
const schedule = ref<any[]>([]);
const month = ref<any>(null);
const doc = reactive({ title: '', type: '', expires_on: '' });
const docFile = ref<HTMLInputElement | null>(null);
const edu = reactive({ degree: '', institution: '', year: '' });
const exp = reactive({ company: '', position: '', date_from: '', date_to: '' });
const exitForm = ref<any>(null);
const error = ref('');
const message = ref('');

async function load() {
  error.value = '';
  try {
    p.value = await api.get('/employees/' + id);
    // The list row has the pay fields (rate, basis) that the API needs back on save.
    const row = (await api.get('/employees?employee_id=' + id))[0] || {};
    for (const k of FIELDS) f[k] = row[k] ?? p.value[k] ?? null;
    schedule.value = await api.get('/employees/' + id + '/schedule');
    month.value = await api.get('/employees/' + id + '/attendance?from=' + today().slice(0, 8) + '01');
  } catch (e: any) {
    error.value = e.message;
  }
}

async function run(fn: () => Promise<any>, done: string) {
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

const save = () => run(() => api.put('/employees/' + id, { ...f, user_id: f.user_id ? String(f.user_id) : null }), 'Saved.');
const confirmEmp = () => run(() => api.post('/employees/' + id + '/confirm', { date: today() }), p.value.first + ' is confirmed.');
const saveExit = () => run(async () => { await api.post('/employees/' + id + '/exit', exitForm.value); exitForm.value = null; }, 'Saved.');

function uploadPhoto(ev: Event) {
  const file = (ev.target as HTMLInputElement).files?.[0];
  if (!file) return;
  const body = new FormData();
  body.append('photo', file);
  run(() => api.post('/employees/' + id + '/photo', body), 'Photo saved.');
}

function uploadDoc() {
  const file = docFile.value?.files?.[0];
  if (!file || !doc.title) {
    error.value = 'Pick a file and give it a title.';
    return;
  }
  const body = new FormData();
  body.append('file', file);
  body.append('title', doc.title);
  if (doc.type) body.append('type', doc.type);
  if (doc.expires_on) body.append('expires_on', doc.expires_on);
  run(async () => {
    await api.post('/employees/' + id + '/documents', body);
    doc.title = ''; doc.type = ''; doc.expires_on = '';
    if (docFile.value) docFile.value.value = '';
  }, 'Document uploaded.');
}

const removeDoc = (d: any) => confirm('Delete ' + d.title + '?') && run(() => api.del('/employee-documents/' + d.id), 'Deleted.');

function addLine(kind: string, line: Record<string, any>) {
  const body: Record<string, any> = {};
  for (const k of Object.keys(line)) body[k] = line[k] || null;
  run(async () => {
    await api.post('/employees/' + id + '/' + kind, body);
    for (const k of Object.keys(line)) line[k] = '';
  }, 'Added.');
}
const removeLine = (kind: string, lineId: number) => run(() => api.del('/employee-' + kind + '/' + lineId), 'Deleted.');

onMounted(async () => {
  [departments.value, designations.value, staff.value] = await Promise.all([
    api.get('/departments').catch(() => []), api.get('/designations').catch(() => []), api.get('/employees?status=working').catch(() => []),
  ]);
  load();
});
</script>
