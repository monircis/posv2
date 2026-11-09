<template>
  <div @click="emit('close')" style="position:fixed;inset:0;background:rgba(9,22,35,.48);z-index:30"></div>
  <div style="position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);width:640px;max-width:calc(100vw - 32px);max-height:90vh;overflow:auto;background:#ffffff;border-radius:16px;z-index:31;box-shadow:0 30px 80px -24px rgba(9,22,35,.65);padding:22px">
    <div style="display:flex;align-items:center;gap:12px;margin-bottom:14px">
      <div>
        <div style="font-size:17px;font-weight:700">Tables · {{ branchName }}</div>
        <div style="font-size:12px;color:#5d6b7d;margin-top:2px">The tables and rooms this branch can book. A table with bookings can be switched off but not deleted.</div>
      </div>
      <div @click="emit('close')" style="margin-left:auto;width:32px;height:32px;border-radius:8px;border:1px solid #dfe6ee;display:grid;place-items:center;cursor:pointer;color:#5d6b7d;font-size:15px;flex:none">×</div>
    </div>

    <div style="display:grid;grid-template-columns:1.3fr 0.6fr 1.1fr 0.8fr 0.7fr 70px;gap:8px;font-size:11px;font-weight:600;color:#7d8b9c;text-transform:uppercase;letter-spacing:.04em;padding:0 2px 6px">
      <div>Name</div><div>Seats</div><div>Zone</div><div>Deposit / guest</div><div>On</div><div></div>
    </div>
    <div v-for="t in rows" :key="t.id" style="display:grid;grid-template-columns:1.3fr 0.6fr 1.1fr 0.8fr 0.7fr 70px;gap:8px;align-items:center;margin-bottom:6px">
      <input v-model="t.name" :style="input" />
      <input v-model.number="t.seats" type="number" min="1" :style="input" />
      <input v-model="t.zone" placeholder="e.g. Window" :style="input" />
      <input v-model.number="t.deposit_per_head" type="number" min="0" step="1" :style="input" />
      <label style="display:flex;align-items:center;gap:6px;font-size:12px;color:#4a5866"><input v-model="t.active" type="checkbox" /> {{ t.active ? 'On' : 'Off' }}</label>
      <div style="display:flex;gap:4px">
        <div @click="saveRow(t)" title="Save" :style="iconBtn + 'color:var(--c-primary)'">✓</div>
        <div v-if="canDelete" @click="removeRow(t)" title="Delete" :style="iconBtn + 'color:#b02020'">×</div>
      </div>
    </div>
    <div v-if="!rows.length" style="font-size:12.5px;color:#7d8b9c;padding:8px 2px">No tables yet.</div>

    <!-- Add a table -->
    <div style="display:grid;grid-template-columns:1.3fr 0.6fr 1.1fr 0.8fr 0.7fr 70px;gap:8px;align-items:center;margin-top:12px;padding-top:12px;border-top:1px solid #eef2f6">
      <input v-model="add.name" placeholder="e.g. Table 9" :style="input" />
      <input v-model.number="add.seats" type="number" min="1" :style="input" />
      <input v-model="add.zone" placeholder="Zone" :style="input" />
      <input v-model.number="add.deposit_per_head" type="number" min="0" :style="input" />
      <div></div>
      <div @click="addRow" style="height:32px;border-radius:8px;background:var(--c-primary);color:#ffffff;display:grid;place-items:center;font-size:12.5px;font-weight:600;cursor:pointer">Add</div>
    </div>

    <div v-if="message" style="margin-top:12px;background:#e7f6ee;color:#0f6b43;border-radius:10px;padding:9px 12px;font-size:12.5px">{{ message }}</div>
    <div v-if="error" style="margin-top:12px;background:#fdeaea;color:#b02020;border-radius:10px;padding:9px 12px;font-size:12.5px">{{ error }}</div>
  </div>
</template>

<script setup lang="ts">
// Bookings > Tables: GET/POST /api/booking-tables, PUT/DELETE /api/booking-tables/{id}.
import { onMounted, reactive, ref } from 'vue';
import { api } from '../../api/http.js';
import { can } from '../../api/auth.js';

const props = defineProps<{ branchId: number; branchName: string }>();
const emit = defineEmits<{ (e: 'close'): void; (e: 'changed'): void }>();

const input = "width:100%;height:32px;border:1px solid #dfe6ee;border-radius:7px;padding:0 8px;font-family:'Public Sans',sans-serif;font-size:12.5px;outline:none;";
const iconBtn = 'width:32px;height:32px;border-radius:7px;border:1px solid #dfe6ee;display:grid;place-items:center;cursor:pointer;font-size:14px;font-weight:700;';
const canDelete = can('bookings.delete');

const rows = ref<any[]>([]);
const add = reactive({ name: '', seats: 4, zone: '', deposit_per_head: 0 });
const message = ref('');
const error = ref('');

async function load() {
  rows.value = await api.get('/booking-tables?branch_id=' + props.branchId);
}

async function saveRow(t: any) {
  error.value = '';
  message.value = '';
  try {
    await api.put('/booking-tables/' + t.id, { name: t.name, seats: t.seats, zone: t.zone || null, deposit_per_head: t.deposit_per_head || 0, active: t.active });
    message.value = t.name + ' saved.';
    emit('changed');
  } catch (e: any) {
    error.value = e.message;
  }
}

async function removeRow(t: any) {
  if (!window.confirm('Delete ' + t.name + '?')) return;
  error.value = '';
  message.value = '';
  try {
    await api.del('/booking-tables/' + t.id);
    message.value = t.name + ' deleted.';
    await load();
    emit('changed');
  } catch (e: any) {
    error.value = e.message;
  }
}

async function addRow() {
  error.value = '';
  message.value = '';
  if (!add.name.trim()) { error.value = 'Give the table a name.'; return; }
  try {
    await api.post('/booking-tables', { branch_id: props.branchId, name: add.name.trim(), seats: add.seats, zone: add.zone || null, deposit_per_head: add.deposit_per_head || 0 });
    message.value = add.name + ' added.';
    add.name = '';
    add.zone = '';
    add.deposit_per_head = 0;
    await load();
    emit('changed');
  } catch (e: any) {
    error.value = e.message;
  }
}

onMounted(() => load().catch((e: any) => { error.value = e.message; }));
</script>
