<template>
  <div>
    <div style="padding:18px 24px 0;background:#ffffff;border-bottom:1px solid #dfe6ee">
      <div style="display:flex;align-items:center;gap:7px;font-size:11.5px;color:#7d8b9c;margin-bottom:8px"><span>My HR</span><span style="color:#c2cedb">/</span><span style="color:#0f1c2b;font-weight:500">{{ TABS.find(t => t.key === tab)?.name }}</span></div>
      <h2 style="margin:0 0 3px;font-size:22px;font-weight:700;letter-spacing:-0.018em">{{ me ? 'Hi ' + me.first : 'My HR' }}</h2>
      <div style="font-size:12.5px;color:#5d6b7d">{{ me ? me.code + ' · ' + (me.designation?.name || me.role) + ' · ' + (me.branch?.name || '') : 'Your attendance, leave, overtime and payslips.' }}</div>
      <div style="display:flex;gap:2px;margin-top:14px;flex-wrap:wrap">
        <div v-for="t in TABS" :key="t.key" @click="openTab(t.key)" :style="tabStyle(tab === t.key)">{{ t.name }}<span v-if="t.key === 'approve' && approveCount" :style="ui.mono + ';font-size:10.5px;background:#fdf1dd;color:#8a5300;border-radius:4px;padding:1px 5px;margin-left:6px'">{{ approveCount }}</span></div>
      </div>
    </div>

    <div style="padding:18px 24px 32px;display:grid;gap:14px">
      <div v-if="message" :style="ui.ok">{{ message }}</div>
      <div v-if="error" :style="ui.err">{{ error }}</div>
      <div v-if="notLinked && tab !== 'approve'" :style="ui.card + ';' + ui.pad">
        <div :style="ui.title">Your sign-in is not linked to an employee record</div>
        <div :style="ui.muted">Ask HR to put your sign-in user id ({{ currentUser?.id }}) on your staff file. Approvals for your team still work: see “To approve”.</div>
      </div>

      <template v-if="me">
        <!-- Overview -->
        <template v-if="tab === 'home'">
          <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:12px">
            <div v-for="b in balances" :key="b.leave_policy_id" :style="ui.kpi"><div :style="ui.muted">{{ b.policy }} leave</div><div :style="ui.kpiValue">{{ b.available }} d</div><div :style="ui.muted">used {{ b.used }}<template v-if="b.pending"> · {{ b.pending }} waiting</template></div></div>
            <div v-if="summary" :style="ui.kpi"><div :style="ui.muted">This month</div><div :style="ui.kpiValue">{{ summary.attended }} days</div><div :style="ui.muted">{{ summary.late }} late · {{ hours(summary.worked_minutes) }} worked</div></div>
          </div>
          <div :style="ui.card">
            <div :style="ui.head">My shifts · next 2 weeks</div>
            <div style="display:flex;gap:6px;flex-wrap:wrap;padding:12px 16px">
              <div v-for="d in shifts" :key="d.date" :style="'padding:6px 8px;border-radius:8px;font-size:11.5px;text-align:center;min-width:62px;' + (d.holiday ? 'background:#f3ecfd;color:#5b34a8' : d.day_off ? 'background:#eef2f6;color:#8b98a8' : 'background:#e7f6ee;color:#0f6b43')">
                <b>{{ d.day }} {{ Number(d.date.slice(8)) }}</b><br />{{ d.holiday || (d.day_off ? 'Off' : d.shift ? d.shift.start_time + '–' + d.shift.end_time : '—') }}
              </div>
            </div>
          </div>
          <div :style="ui.card + ';' + ui.pad + ';display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:6px 16px;font-size:12.5px'">
            <div><span :style="ui.muted">Department</span><br />{{ me.department?.name || '—' }}</div>
            <div><span :style="ui.muted">Manager</span><br />{{ me.manager ? me.manager.first + ' ' + me.manager.last : '—' }}</div>
            <div><span :style="ui.muted">Started</span><br />{{ dmy(me.start) || '—' }}</div>
            <div><span :style="ui.muted">Type</span><br />{{ me.type }}</div>
            <div><span :style="ui.muted">Mobile</span><br />{{ me.phone }}</div>
            <div><span :style="ui.muted">Email</span><br />{{ me.email || '—' }}</div>
            <div><span :style="ui.muted">Emergency contact</span><br />{{ me.emergency_name || me.emerg || '—' }} {{ me.emergency_phone || '' }}</div>
            <div><span :style="ui.muted">Status</span><br /><span :style="badge(me.status)">{{ me.status }}</span></div>
          </div>
        </template>

        <!-- Attendance -->
        <template v-if="tab === 'attendance'">
          <div style="display:flex;gap:8px;align-items:center">
            <input type="month" v-model="month" @change="loadAttendance" :style="ui.input" />
            <div v-if="summary" :style="ui.muted">{{ summary.attended }} days at work · {{ summary.late }} late · {{ summary.absent }} absent · {{ hours(summary.worked_minutes) }} worked</div>
          </div>
          <div :style="ui.card">
            <div :style="ui.head + ';display:grid;' + aGrid"><div>Day</div><div>Status</div><div>In</div><div>Out</div><div>Worked</div><div>Late</div><div></div></div>
            <div v-for="d in days" :key="d.date" :style="ui.row + ';display:grid;' + aGrid">
              <div>{{ DAYS[new Date(d.date + 'T12:00:00').getDay()] }} {{ dmy(d.date) }}</div>
              <div><span :style="badge(d.status)">{{ label(d.status) }}</span></div>
              <div :style="ui.mono">{{ time(d.row?.first_in_at) }}</div>
              <div :style="ui.mono">{{ time(d.row?.last_out_at) }}</div>
              <div :style="ui.mono">{{ d.row ? hours(d.row.worked_minutes) : '—' }}</div>
              <div :style="ui.mono">{{ d.row?.late_minutes ? d.row.late_minutes + 'm' : '—' }}</div>
              <div><div v-if="d.status !== 'none' && d.date <= today()" @click="fix = { date: d.date, in_at: (d.row?.first_in_at || d.date + ' 09:00').replace(' ', 'T'), out_at: (d.row?.last_out_at || d.date + ' 17:00').replace(' ', 'T'), break_minutes: d.row?.break_minutes ?? '', reason: '' }" :style="ui.btnSmall">Ask fix</div></div>
            </div>
          </div>
          <div v-if="corrections.length" :style="ui.card">
            <div :style="ui.head">My fix requests</div>
            <div v-for="c in corrections" :key="c.id" :style="ui.row + ';display:flex;gap:12px;align-items:center'">
              <div :style="ui.mono">{{ c.ref }}</div><div>{{ dmy(c.date) }} {{ time(c.in_at) }}–{{ time(c.out_at) }}</div><div :style="ui.muted">{{ c.reason }}</div>
              <span :style="badge(c.status) + ';margin-left:auto'">{{ c.status }}</span>
              <div v-if="c.status === 'pending'" @click="cancelFix(c)" :style="ui.btnSmall">Withdraw</div>
            </div>
          </div>
        </template>

        <!-- Leave -->
        <template v-if="tab === 'leave'">
          <div :style="ui.card + ';' + ui.pad + ';display:grid;gap:10px'">
            <div :style="ui.title">Apply for leave</div>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:10px">
              <label :style="ui.label">Leave<select v-model="lv.leave_policy_id" :style="ui.input"><option v-for="b in policies" :key="b.id" :value="b.id">{{ b.name }}</option></select></label>
              <label :style="ui.label">From<input type="date" v-model="lv.date_from" :style="ui.input" /></label>
              <label :style="ui.label">To<input type="date" v-model="lv.date_to" :style="ui.input" /></label>
              <label :style="ui.label">How long<select v-model="lv.duration" :style="ui.input"><option value="full">Full days</option><option value="half">Half day</option><option value="hours">Some hours</option></select></label>
              <label v-if="lv.duration === 'hours'" :style="ui.label">Hours<input type="number" min="0.5" step="0.5" v-model="lv.hours" :style="ui.input" /></label>
            </div>
            <label :style="ui.label">Note<input v-model="lv.note" :style="ui.input" /></label>
            <div style="display:flex;gap:10px;align-items:center"><input type="file" ref="lvFile" style="font-size:12px" /><span :style="ui.muted">A file (e.g. a doctor's note) if needed.</span><div @click="applyLeave" :style="ui.btnMain + ';margin-left:auto'">Send request</div></div>
          </div>
          <div :style="ui.card">
            <div :style="ui.head">My leave</div>
            <div v-for="l in leave" :key="l.id" :style="ui.row + ';display:flex;gap:12px;align-items:center;flex-wrap:wrap'">
              <div :style="ui.mono">{{ l.ref }}</div><div style="font-weight:600">{{ l.type }}</div>
              <div>{{ dmy(l.date_from) }}{{ l.date_to !== l.date_from ? ' – ' + dmy(l.date_to) : '' }} · {{ l.days }} day{{ l.days == 1 ? '' : 's' }}</div>
              <div :style="ui.muted">{{ l.approvals.map((a: any) => label(a.level) + ' ' + a.status).join(' → ') }}</div>
              <span :style="badge(l.status) + ';margin-left:auto'">{{ l.status }}</span>
              <div v-if="l.status === 'Pending'" @click="cancelLeave(l)" :style="ui.btnSmall">Withdraw</div>
            </div>
            <div v-if="!leave.length" :style="ui.row + ';color:#8b98a8'">No leave yet.</div>
          </div>
          <div v-if="team.leave?.length" :style="ui.card">
            <div :style="ui.head">My team's leave · {{ monthName(month) }}</div>
            <div v-for="l in team.leave" :key="l.id" :style="ui.row + ';display:flex;gap:12px'"><b>{{ l.name }}</b><span>{{ l.type }}</span><span :style="ui.muted">{{ dmy(l.date_from) }} – {{ dmy(l.date_to) }}</span><span :style="badge(l.status) + ';margin-left:auto'">{{ l.status }}</span></div>
          </div>
        </template>

        <!-- Overtime -->
        <template v-if="tab === 'overtime'">
          <div :style="ui.card + ';' + ui.pad + ';display:grid;grid-template-columns:1fr 1fr 2fr auto;gap:10px;align-items:end'">
            <label :style="ui.label">Day<input type="date" v-model="ot.date" :style="ui.input" /></label>
            <label :style="ui.label">Minutes<input type="number" min="1" v-model.number="ot.minutes" :style="ui.input" /></label>
            <label :style="ui.label">Why<input v-model="ot.reason" :style="ui.input" /></label>
            <div @click="askOvertime" :style="ui.btnMain">Ask for overtime</div>
          </div>
          <div :style="ui.card">
            <div v-for="o in overtime" :key="o.id" :style="ui.row + ';display:flex;gap:12px;align-items:center'">
              <div :style="ui.mono">{{ o.ref }}</div><div>{{ dmy(o.date) }}</div><div>{{ hours(o.minutes) }} · {{ label(o.day_type) }} ×{{ o.rate }}</div>
              <div :style="ui.mono">{{ money(o.amount) }}</div><div :style="ui.muted">{{ o.source === 'auto' ? 'from attendance' : o.reason }}</div>
              <span :style="badge(o.status) + ';margin-left:auto'">{{ o.status }}</span>
            </div>
            <div v-if="!overtime.length" :style="ui.row + ';color:#8b98a8'">No overtime.</div>
          </div>
        </template>

        <!-- Pay -->
        <template v-if="tab === 'pay'">
          <div v-if="salary?.current" :style="ui.card + ';' + ui.pad + ';display:flex;gap:20px;flex-wrap:wrap;font-size:12.5px'">
            <div><span :style="ui.muted">Basic</span><br /><b :style="ui.mono">{{ money(salary.current.basic) }}</b></div>
            <div><span :style="ui.muted">Allowances</span><br /><b :style="ui.mono">{{ money(salary.current.allowances_total) }}</b></div>
            <div><span :style="ui.muted">Monthly gross</span><br /><b :style="ui.mono">{{ money(salary.current.gross) }}</b></div>
            <div><span :style="ui.muted">Since</span><br />{{ dmy(salary.current.effective_from) }}</div>
            <div v-if="salary.bank"><span :style="ui.muted">Paid to</span><br />{{ salary.bank }}</div>
          </div>
          <div :style="ui.card">
            <div :style="ui.head">My payslips</div>
            <div v-for="s in payslips" :key="s.id" :style="ui.row + ';display:flex;gap:14px;align-items:center'">
              <div style="font-weight:600;min-width:140px">{{ monthName(s.pay_run.month) }}</div>
              <div :style="ui.mono">gross {{ money(s.gross) }}</div><div :style="ui.mono">net {{ money(s.net) }}</div>
              <span :style="badge(s.pay_run.status)">{{ s.pay_run.status }}</span>
              <div @click="openPrintable('/me/hrm/payslips/' + s.id)" :style="ui.btnSmall + ';margin-left:auto'">Open / print</div>
            </div>
            <div v-if="!payslips.length" :style="ui.row + ';color:#8b98a8'">No payslips yet.</div>
          </div>
        </template>

        <!-- Holidays -->
        <template v-if="tab === 'holidays'">
          <div :style="ui.card">
            <div v-for="h in holidays" :key="h.date" :style="ui.row + ';display:flex;gap:14px' + (h.date < today() ? ';color:#9aa7b6' : '')">
              <div :style="ui.mono">{{ dmy(h.date) }}</div><div>{{ DAYS[new Date(h.date + 'T12:00:00').getDay()] }}</div><b>{{ h.name }}</b><span :style="ui.muted">{{ h.type }}</span>
            </div>
            <div v-if="!holidays.length" :style="ui.row + ';color:#8b98a8'">No holidays this year.</div>
          </div>
        </template>
      </template>

      <Approvals v-if="tab === 'approve'" />
    </div>

    <!-- Ask for an attendance fix -->
    <div v-if="fix" :style="ui.overlay" @click.self="fix = null">
      <div :style="ui.dialog">
        <div :style="ui.title">Ask to fix {{ dmy(fix.date) }}</div>
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px">
          <label :style="ui.label">In<input type="datetime-local" v-model="fix.in_at" :style="ui.input" /></label>
          <label :style="ui.label">Out<input type="datetime-local" v-model="fix.out_at" :style="ui.input" /></label>
          <label :style="ui.label">Break (min)<input type="number" min="0" v-model="fix.break_minutes" :style="ui.input" /></label>
        </div>
        <label :style="ui.label">Why<input v-model="fix.reason" placeholder="e.g. forgot to clock out" :style="ui.input" /></label>
        <div style="display:flex;gap:8px;justify-content:flex-end">
          <div @click="fix = null" :style="ui.btn">Cancel</div>
          <div @click="askFix" :style="ui.btnMain">Send for approval</div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
// My HR: the signed-in employee's own records (any signed-in user). API: /me/hrm/* and /approvals/pending.
import { onMounted, reactive, ref } from 'vue';
import { useErpPage } from '../erp/useErpPage';
import { api, ui, badge, label, dmy, time, hours, money, monthName, today, thisMonth, DAYS, openPrintable } from '../api/hrm.js';
import { currentUser } from '../api/auth.js';
import Approvals from './hrm/Approvals.vue';

useErpPage('home', 'nav'); // the sidebar

const TABS = [
  { key: 'home', name: 'Overview' }, { key: 'attendance', name: 'Attendance' }, { key: 'leave', name: 'Leave' },
  { key: 'overtime', name: 'Overtime' }, { key: 'pay', name: 'Pay' }, { key: 'holidays', name: 'Holidays' }, { key: 'approve', name: 'To approve' },
];
const aGrid = 'grid-template-columns:1.1fr 1fr 0.6fr 0.6fr 0.7fr 0.6fr 0.7fr;gap:10px';
const tab = ref('home');
const me = ref<any>(null);
const notLinked = ref(false);
const balances = ref<any[]>([]);
const policies = ref<any[]>([]);
const shifts = ref<any[]>([]);
const month = ref(thisMonth());
const summary = ref<any>(null);
const days = ref<any[]>([]);
const corrections = ref<any[]>([]);
const leave = ref<any[]>([]);
const team = ref<any>({});
const overtime = ref<any[]>([]);
const salary = ref<any>(null);
const payslips = ref<any[]>([]);
const holidays = ref<any[]>([]);
const approveCount = ref(0);
const fix = ref<any>(null);
const lv = reactive<any>({ leave_policy_id: '', date_from: today(), date_to: today(), duration: 'full', hours: '', note: '' });
const lvFile = ref<HTMLInputElement | null>(null);
const ot = reactive({ date: today(), minutes: 60, reason: '' });
const error = ref('');
const message = ref('');

const tabStyle = (on: boolean) => 'padding:9px 14px;font-size:13px;cursor:pointer;border-bottom:2px solid ' + (on ? 'var(--c-primary);color:var(--c-primary);font-weight:600;' : 'transparent;color:#5d6b7d;font-weight:500;');

async function loadAttendance() {
  const last = new Date(Number(month.value.slice(0, 4)), Number(month.value.slice(5)), 0).getDate();
  const r = await api.get('/me/hrm/attendance?from=' + month.value + '-01&to=' + month.value + '-' + last);
  days.value = r.days;
  summary.value = r.summary;
  corrections.value = await api.get('/me/hrm/corrections');
}
async function loadLeave() {
  leave.value = await api.get('/me/hrm/leave');
  balances.value = await api.get('/me/hrm/leave-balances');
  team.value = await api.get('/me/hrm/team-leave?month=' + month.value).catch(() => ({}));
}

const LOADERS: Record<string, () => Promise<any>> = {
  home: async () => { balances.value = await api.get('/me/hrm/leave-balances'); shifts.value = await api.get('/me/hrm/shifts'); summary.value = await api.get('/me/hrm/attendance/summary'); },
  attendance: loadAttendance,
  leave: loadLeave,
  overtime: async () => { overtime.value = await api.get('/me/hrm/overtime'); },
  pay: async () => { salary.value = await api.get('/me/hrm/salary'); payslips.value = await api.get('/me/hrm/payslips'); },
  holidays: async () => { holidays.value = await api.get('/me/hrm/holidays?year=' + new Date().getFullYear()); },
  approve: async () => {},
};

async function openTab(key: string) {
  tab.value = key;
  error.value = '';
  message.value = '';
  if (!me.value) return;
  try {
    await LOADERS[key]();
  } catch (e: any) {
    error.value = e.message;
  }
}

async function send(fn: () => Promise<any>, done: string) {
  error.value = '';
  message.value = '';
  try {
    await fn();
    if (!message.value) message.value = done; // fn may have set a more exact message
    await LOADERS[tab.value]();
  } catch (e: any) {
    error.value = e.message;
  }
}

const askFix = () => send(async () => {
  const f = fix.value;
  await api.post('/me/hrm/corrections', { date: f.date, in_at: f.in_at.replace('T', ' '), out_at: f.out_at ? f.out_at.replace('T', ' ') : null, break_minutes: f.break_minutes === '' ? null : Number(f.break_minutes), reason: f.reason });
  fix.value = null;
}, 'Sent for approval.');
const cancelFix = (c: any) => send(() => api.post('/me/hrm/corrections/' + c.id + '/cancel'), 'Withdrawn.');

const applyLeave = () => send(async () => {
  const body = new FormData();
  for (const k of ['leave_policy_id', 'date_from', 'date_to', 'duration', 'note']) if (lv[k]) body.append(k, lv[k]);
  if (lv.duration === 'half' || lv.duration === 'hours') body.set('date_to', lv.date_from);
  if (lv.duration === 'hours') body.append('hours', lv.hours);
  const file = lvFile.value?.files?.[0];
  if (file) body.append('attachment', file);
  const r = await api.post('/me/hrm/leave', body);
  message.value = r.ref + ': ' + r.days + ' day(s) sent for approval.';
  lv.note = '';
  if (lvFile.value) lvFile.value.value = '';
}, 'Sent for approval.');
const cancelLeave = (l: any) => send(() => api.post('/me/hrm/leave/' + l.id + '/cancel'), 'Withdrawn.');
const askOvertime = () => send(async () => { await api.post('/me/hrm/overtime', { ...ot }); ot.reason = ''; }, 'Sent for approval.');

onMounted(async () => {
  approveCount.value = (await api.get('/approvals/pending').catch(() => [])).length;
  try {
    me.value = await api.get('/me/hrm/profile');
    policies.value = await api.get('/me/hrm/leave-types');
    lv.leave_policy_id = policies.value[0]?.id || '';
    await LOADERS.home();
  } catch (e: any) {
    if (e.status === 404) {
      notLinked.value = true;
      tab.value = 'approve';
    } else error.value = e.message;
  }
});
</script>
