<template>
  <div style="display:grid;gap:14px">
    <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
      <input v-model="q" @keyup.enter="load" placeholder="Search name, code, phone, email" :style="ui.input + ';width:260px'" />
      <select v-model="departmentId" @change="load" :style="ui.input">
        <option value="">All departments</option>
        <option v-for="d in departments" :key="d.id" :value="d.id">{{ d.name }}</option>
      </select>
      <select v-model="status" @change="load" :style="ui.input">
        <option value="working">Working (active + probation)</option>
        <option value="">Everyone</option>
        <option v-for="s in ['Active', 'Probation', 'Inactive', 'Resigned', 'Terminated']" :key="s" :value="s">{{ s }}</option>
      </select>
      <div @click="load" :style="ui.btn">Search</div>
      <div :style="ui.muted + ';margin-left:auto'">New staff are added on the Employees tab.</div>
    </div>
    <div v-if="error" :style="ui.err">{{ error }}</div>
    <div :style="ui.card">
      <div :style="ui.head + ';display:grid;' + grid"><div>Code</div><div>Name</div><div>Department</div><div>Designation</div><div>Branch</div><div>Type</div><div>Started</div><div>Status</div></div>
      <RouterLink v-for="e in rows" :key="e.id" :to="{ name: 'hrm-file', params: { id: e.id } }" :style="ui.row + ';display:grid;' + grid + ';text-decoration:none;color:inherit'">
        <div :style="ui.mono">{{ e.code }}</div>
        <div style="font-weight:600">{{ e.first }} {{ e.last }}</div>
        <div>{{ e.department?.name || '—' }}</div>
        <div>{{ e.designation?.name || e.role }}</div>
        <div>{{ e.branch?.name }}</div>
        <div>{{ e.type }}</div>
        <div>{{ dmy(e.start) || '—' }}</div>
        <div><span :style="badge(e.status)">{{ e.status }}</span></div>
      </RouterLink>
      <div v-if="!rows.length" style="padding:30px;text-align:center;color:#7d8b9c;font-size:13px">No staff found.</div>
    </div>
  </div>
</template>

<script setup lang="ts">
// HRM > Staff files: the employee list with the new filters. API: GET /employees?q=&department_id=&status=
import { onMounted, ref } from 'vue';
import { api, ui, badge, dmy } from '../../api/hrm.js';

const grid = 'grid-template-columns:0.8fr 1.4fr 1fr 1fr 1fr 0.8fr 0.8fr 0.8fr;gap:10px';
const rows = ref<any[]>([]);
const departments = ref<any[]>([]);
const q = ref('');
const departmentId = ref('');
const status = ref('working');
const error = ref('');

async function load() {
  error.value = '';
  const params = new URLSearchParams();
  if (q.value) params.set('q', q.value);
  if (departmentId.value) params.set('department_id', departmentId.value);
  if (status.value) params.set('status', status.value);
  try {
    rows.value = await api.get('/employees?' + params.toString());
  } catch (e: any) {
    error.value = e.message;
  }
}

onMounted(async () => {
  departments.value = await api.get('/departments').catch(() => []);
  load();
});
</script>
