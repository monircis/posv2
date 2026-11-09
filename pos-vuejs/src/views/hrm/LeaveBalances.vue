<template>
  <div style="display:grid;gap:14px">
    <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
      <select v-model.number="year" @change="load" :style="ui.input">
        <option v-for="y in [year0 - 1, year0, year0 + 1]" :key="y" :value="y">{{ y }}</option>
      </select>
      <div v-if="canEdit" @click="allocate" :style="ui.btn">Allocate {{ year }} for everyone</div>
      <div :style="ui.muted + ';margin-left:auto'">Leave requests are on the Leave tab; approving is in Approvals.</div>
    </div>
    <div v-if="message" :style="ui.ok">{{ message }}</div>
    <div v-if="error" :style="ui.err">{{ error }}</div>

    <div :style="ui.card">
      <div :style="ui.head + ';display:grid;grid-template-columns:1.4fr repeat(' + policies.length + ',1fr);gap:10px'">
        <div>Employee</div><div v-for="pl in policies" :key="pl">{{ pl }}</div>
      </div>
      <div v-for="r in rows" :key="r.employee_id" :style="ui.row + ';display:grid;grid-template-columns:1.4fr repeat(' + policies.length + ',1fr);gap:10px'">
        <div><div style="font-weight:600">{{ r.name }}</div><div :style="ui.muted">{{ r.code }}</div></div>
        <div v-for="pl in policies" :key="pl">
          <template v-if="bal(r, pl)">
            <div><b :style="ui.mono">{{ bal(r, pl).available }}</b> <span :style="ui.muted">left</span></div>
            <div :style="ui.muted">used {{ bal(r, pl).used }}<template v-if="bal(r, pl).pending"> · {{ bal(r, pl).pending }} waiting</template><template v-if="bal(r, pl).carried"> · {{ bal(r, pl).carried }} carried</template></div>
            <div style="display:flex;gap:4px;margin-top:3px">
              <div v-if="canEdit" @click="adjust(r, bal(r, pl))" :style="ui.btnSmall">± Adjust</div>
              <div v-if="canPay && bal(r, pl).encashable" @click="encash(r, bal(r, pl))" :style="ui.btnSmall">Encash</div>
            </div>
          </template>
          <span v-else :style="ui.muted">—</span>
        </div>
      </div>
      <div v-if="!rows.length" style="padding:30px;text-align:center;color:#7d8b9c;font-size:13px">No staff.</div>
    </div>

    <!-- Calendar -->
    <div :style="ui.card">
      <div :style="ui.pad + ';display:flex;gap:8px;align-items:center'">
        <div :style="ui.title">Leave calendar</div>
        <input type="month" v-model="month" @change="loadCalendar" :style="ui.input + ';margin-left:auto'" />
      </div>
      <div style="display:grid;grid-template-columns:repeat(7,1fr);gap:4px;padding:0 16px 16px">
        <div v-for="d in ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun']" :key="d" :style="ui.muted + ';text-align:center'">{{ d }}</div>
        <div v-for="c in cells" :key="c.key" :style="'min-height:64px;border-radius:8px;padding:4px 6px;font-size:11px;' + (c.date ? 'background:#f7fafc' : '')">
          <template v-if="c.date">
            <div style="font-weight:700;color:#5d6b7d">{{ Number(c.date.slice(8)) }}</div>
            <div v-for="l in calendar.days?.[c.date] || []" :key="l.employee_id" :style="'margin-top:2px;border-radius:4px;padding:1px 4px;' + (l.status === 'Approved' ? 'background:var(--c-primary-soft);color:var(--c-primary-text)' : 'background:#fdf1dd;color:#8a5300')">{{ l.name }} · {{ l.type }}</div>
          </template>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
// HRM > Leave balances and calendar.
// API: GET /leave-balances?year=, POST /leave-balances/allocate, POST /leave-balances/{employee}/{policy}/adjust | encash,
//      GET /leave-calendar?month=
import { computed, onMounted, ref } from 'vue';
import { api, ui, thisMonth } from '../../api/hrm.js';
import { can } from '../../api/auth.js';

const canEdit = can('hrm.edit');
const canPay = can('hrm.payroll');
const year0 = new Date().getFullYear();
const year = ref(year0);
const rows = ref<any[]>([]);
const month = ref(thisMonth());
const calendar = ref<any>({});
const error = ref('');
const message = ref('');

const policies = computed(() => [...new Set(rows.value.flatMap(r => r.balances.map((b: any) => b.policy)))]);
const bal = (r: any, policy: string) => r.balances.find((b: any) => b.policy === policy);

// The month as weeks starting on Monday.
const cells = computed(() => {
  const first = new Date(month.value + '-01T12:00:00');
  const blanks = (first.getDay() + 6) % 7;
  const days = new Date(first.getFullYear(), first.getMonth() + 1, 0).getDate();
  const out = [];
  for (let i = 0; i < blanks; i++) out.push({ key: 'b' + i, date: '' });
  for (let d = 1; d <= days; d++) out.push({ key: 'd' + d, date: month.value + '-' + String(d).padStart(2, '0') });
  return out;
});

async function load() {
  error.value = '';
  try {
    rows.value = (await api.get('/leave-balances?year=' + year.value)).rows;
  } catch (e: any) {
    error.value = e.message;
  }
}
async function loadCalendar() {
  calendar.value = await api.get('/leave-calendar?month=' + month.value).catch(() => ({}));
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

function adjust(r: any, b: any) {
  const days = Number(prompt('Days to add to ' + r.name + "'s " + b.policy + ' leave (negative to take off):', '1'));
  if (!days) return;
  act(() => api.post('/leave-balances/' + r.employee_id + '/' + b.leave_policy_id + '/adjust', { days, year: year.value }), r.name + ': ' + b.policy + ' leave changed by ' + days + ' days.');
}
function encash(r: any, b: any) {
  const days = Number(prompt('Days of ' + b.policy + ' leave to pay out to ' + r.name + ' (added to this month’s payroll):', '1'));
  if (!days) return;
  act(() => api.post('/leave-balances/' + r.employee_id + '/' + b.leave_policy_id + '/encash', { days, year: year.value }), r.name + ': ' + days + ' days encashed.');
}
const allocate = () => act(async () => { const r = await api.post('/leave-balances/allocate', { year: year.value }); message.value = r.balances + ' balances ready.'; }, 'Balances for ' + year.value + ' are made.');

onMounted(() => { load(); loadCalendar(); });
</script>
