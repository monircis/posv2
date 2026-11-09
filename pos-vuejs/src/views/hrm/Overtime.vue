<template>
  <div style="display:grid;gap:14px">
    <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
      <input type="month" v-model="month" @change="load" :style="ui.input" />
      <div :style="ui.segWrap">
        <div v-for="s in ['', 'pending', 'approved', 'rejected', 'cancelled']" :key="s" @click="status = s; load()" :style="ui.seg(status === s)">{{ s ? label(s) : 'All' }}</div>
      </div>
      <div v-if="canCreate" @click="form = { employee_id: '', date: today(), minutes: 60, reason: '' }" :style="ui.btnMain + ';margin-left:auto'">+ Ask for overtime</div>
    </div>
    <div v-if="message" :style="ui.ok">{{ message }}</div>
    <div v-if="error" :style="ui.err">{{ error }}</div>

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px">
      <div v-for="s in summary" :key="s.employee_id" :style="ui.kpi">
        <div :style="ui.muted">{{ s.name }}</div>
        <div :style="ui.kpiValue">{{ money(s.approved_amount) }}</div>
        <div style="font-size:11.5px;color:#8b98a8">{{ hours(s.approved_minutes) }} approved<template v-if="s.pending_minutes"> · {{ hours(s.pending_minutes) }} waiting</template></div>
      </div>
    </div>

    <div :style="ui.card">
      <div :style="ui.head + ';display:grid;' + grid"><div>Ref</div><div>Employee</div><div>Day</div><div>Kind</div><div>Time</div><div>Rate</div><div>Amount</div><div>From</div><div>Status</div><div></div></div>
      <div v-for="o in rows" :key="o.id" :style="ui.row + ';display:grid;' + grid">
        <div :style="ui.mono">{{ o.ref }}</div>
        <div>{{ o.employee?.first }} {{ o.employee?.last }}</div>
        <div>{{ dmy(o.date) }}</div>
        <div><span :style="badge(o.day_type === 'workday' ? 'none' : o.day_type)">{{ label(o.day_type) }}</span></div>
        <div :style="ui.mono">{{ hours(o.minutes) }}</div>
        <div :style="ui.mono">×{{ o.rate }}</div>
        <div :style="ui.mono">{{ money(o.amount) }}</div>
        <div :title="o.reason || ''">{{ o.source === 'auto' ? 'Attendance' : 'Request' }}</div>
        <div><span :style="badge(o.status)">{{ o.status }}</span><span v-if="o.pay_run_id" :style="ui.muted"> · paid</span></div>
        <div><div v-if="o.status === 'pending' && canEdit" @click="cancel(o)" :style="ui.btnSmall">Cancel</div></div>
      </div>
      <div v-if="!rows.length" style="padding:30px;text-align:center;color:#7d8b9c;font-size:13px">No overtime this month.</div>
    </div>
    <div :style="ui.muted">Overtime from attendance is the time worked past the shift; on a day off or holiday all worked time counts. Rates come from the overtime policy.</div>

    <div v-if="form" :style="ui.overlay" @click.self="form = null">
      <div :style="ui.dialog">
        <div :style="ui.title">Ask for overtime</div>
        <label :style="ui.label">Employee
          <select v-model="form.employee_id" :style="ui.input"><option value="">Pick…</option><option v-for="s in staff" :key="s.id" :value="s.id">{{ s.first }} {{ s.last }}</option></select>
        </label>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
          <label :style="ui.label">Day<input type="date" v-model="form.date" :style="ui.input" /></label>
          <label :style="ui.label">Minutes<input type="number" min="1" v-model.number="form.minutes" :style="ui.input" /></label>
        </div>
        <label :style="ui.label">Reason<input v-model="form.reason" :style="ui.input" /></label>
        <div style="display:flex;gap:8px;justify-content:flex-end">
          <div @click="form = null" :style="ui.btn">Cancel</div>
          <div @click="save" :style="ui.btnMain">Send for approval</div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
// HRM > Overtime. API: GET /overtime-requests?month=&overtime_status=, GET /overtime-requests/summary?month=,
//                      POST /overtime-requests, POST /overtime-requests/{id}/cancel
import { onMounted, ref } from 'vue';
import { api, ui, badge, label, dmy, hours, money, thisMonth, today, loadStaff } from '../../api/hrm.js';
import { can } from '../../api/auth.js';

const grid = 'grid-template-columns:0.7fr 1.3fr 0.8fr 0.8fr 0.7fr 0.5fr 0.8fr 0.8fr 0.9fr 0.6fr;gap:10px';
const canCreate = can('hrm.create');
const canEdit = can('hrm.edit');
const month = ref(thisMonth());
const status = ref('');
const rows = ref<any[]>([]);
const summary = ref<any[]>([]);
const staff = ref<any[]>([]);
const form = ref<any>(null);
const error = ref('');
const message = ref('');

async function load() {
  error.value = '';
  try {
    rows.value = await api.get('/overtime-requests?month=' + month.value + (status.value ? '&overtime_status=' + status.value : ''));
    summary.value = (await api.get('/overtime-requests/summary?month=' + month.value)).rows;
  } catch (e: any) {
    error.value = e.message;
  }
}

async function save() {
  error.value = '';
  try {
    const r = await api.post('/overtime-requests', form.value);
    message.value = r.ref + ' sent for approval: ' + money(r.amount) + '.';
    form.value = null;
    await load();
  } catch (e: any) {
    error.value = e.message;
  }
}

async function cancel(o: any) {
  try {
    await api.post('/overtime-requests/' + o.id + '/cancel');
    await load();
  } catch (e: any) {
    error.value = e.message;
  }
}

onMounted(async () => {
  staff.value = await loadStaff().catch(() => []);
  load();
});
</script>
