<template>
  <div style="display:grid;gap:14px">
    <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
      <select v-model.number="year" @change="load" :style="ui.input">
        <option v-for="y in [year0 - 1, year0, year0 + 1, year0 + 2]" :key="y" :value="y">{{ y }}</option>
      </select>
      <div :style="ui.muted">Work on a holiday counts as overtime at the holiday rate (see Policies → Overtime).</div>
      <div v-if="canCreate" @click="edit(null)" :style="ui.btnMain + ';margin-left:auto'">+ Add holiday</div>
    </div>
    <div v-if="message" :style="ui.ok">{{ message }}</div>
    <div v-if="error" :style="ui.err">{{ error }}</div>

    <div :style="ui.card">
      <div :style="ui.head + ';display:grid;grid-template-columns:1fr 0.6fr 1.6fr 0.8fr 0.9fr 1fr 0.9fr;gap:10px'"><div>Date</div><div>Day</div><div>Holiday</div><div>Type</div><div>Repeats</div><div>Branch</div><div></div></div>
      <div v-for="h in calendar" :key="h.date + h.id" :style="ui.row + ';display:grid;grid-template-columns:1fr 0.6fr 1.6fr 0.8fr 0.9fr 1fr 0.9fr;gap:10px' + (h.date < today() ? ';color:#9aa7b6' : '')">
        <div :style="ui.mono">{{ dmy(h.date) }}</div>
        <div>{{ DAYS[new Date(h.date + 'T12:00:00').getDay()] }}</div>
        <div style="font-weight:600">{{ h.name }}</div>
        <div><span :style="badge('holiday')">{{ h.type }}</span></div>
        <div>{{ h.recurring ? 'Every year' : 'Once' }}</div>
        <div>{{ branchName(h.branch_id) }}</div>
        <div style="display:flex;gap:5px;justify-content:flex-end">
          <div v-if="canEdit" @click="edit(rows.find(r => r.id === h.id))" :style="ui.btnSmall">Edit</div>
          <div v-if="canDelete" @click="remove(h)" :style="ui.btnBad">Delete</div>
        </div>
      </div>
      <div v-if="!calendar.length" style="padding:30px;text-align:center;color:#7d8b9c;font-size:13px">No holidays in {{ year }}.</div>
    </div>

    <div v-if="form" :style="ui.overlay" @click.self="form = null">
      <div :style="ui.dialog">
        <div :style="ui.title">{{ form.id ? 'Edit holiday' : 'Add holiday' }}</div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
          <label :style="ui.label">Name<input v-model="form.name" placeholder="e.g. Good Friday" :style="ui.input" /></label>
          <label :style="ui.label">Date<input type="date" v-model="form.date" :style="ui.input" /></label>
          <label :style="ui.label">Type<select v-model="form.type" :style="ui.input"><option>Public</option><option>Company</option></select></label>
          <label :style="ui.label">Branch
            <select v-model="form.branch_id" :style="ui.input"><option v-if="!lockedBranch" :value="null">All branches</option><option v-for="b in branches" :key="b.id" :value="b.id">{{ b.name }}</option></select>
          </label>
        </div>
        <label style="font-size:12.5px"><input type="checkbox" v-model="form.recurring" /> The same day every year (e.g. Christmas). Leave off for moving days like Easter.</label>
        <div style="display:flex;gap:8px;justify-content:flex-end">
          <div @click="form = null" :style="ui.btn">Cancel</div>
          <div @click="save" :style="ui.btnMain">Save</div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
// HRM > Holidays. API: GET /holidays, GET /holidays/calendar?year=, POST /holidays, PUT|DELETE /holidays/{id}
import { computed, onMounted, ref } from 'vue';
import { api, ui, badge, dmy, today, DAYS } from '../../api/hrm.js';
import { branches as allBranches, loadBranches } from '../../api/branches.js';
import { can, currentUser } from '../../api/auth.js';

const canCreate = can('hrm.create');
const canEdit = can('hrm.edit');
const canDelete = can('hrm.delete');
const lockedBranch = currentUser.value?.branch_id || 0;
const branches = computed(() => allBranches.value.filter((b: any) => !lockedBranch || b.id === lockedBranch));
const year0 = new Date().getFullYear();
const year = ref(year0);
const rows = ref<any[]>([]);
const calendar = ref<any[]>([]);
const form = ref<any>(null);
const error = ref('');
const message = ref('');

const branchName = (id: number | null) => (id ? allBranches.value.find((b: any) => b.id === id)?.name || '#' + id : 'All branches');

async function load() {
  error.value = '';
  try {
    [rows.value, calendar.value] = await Promise.all([api.get('/holidays'), api.get('/holidays/calendar?year=' + year.value)]);
  } catch (e: any) {
    error.value = e.message;
  }
}

function edit(h: any) {
  form.value = h ? { ...h } : { name: '', date: today(), type: 'Public', recurring: false, branch_id: lockedBranch || null };
}

async function save() {
  error.value = '';
  message.value = '';
  const f = form.value;
  const body = { name: f.name, date: f.date, type: f.type, recurring: f.recurring, branch_id: f.branch_id };
  try {
    if (f.id) await api.put('/holidays/' + f.id, body); else await api.post('/holidays', body);
    message.value = f.name + ' saved.';
    form.value = null;
    await load();
  } catch (e: any) {
    error.value = e.message;
  }
}

async function remove(h: any) {
  if (!confirm('Delete ' + h.name + '?')) return;
  try {
    await api.del('/holidays/' + h.id);
    await load();
  } catch (e: any) {
    error.value = e.message;
  }
}

onMounted(() => {
  loadBranches().catch(() => {});
  load();
});
</script>
