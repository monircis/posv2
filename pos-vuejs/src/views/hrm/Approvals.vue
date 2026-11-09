<template>
  <div style="display:grid;gap:14px">
    <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
      <div :style="ui.segWrap">
        <div v-for="m in MODULES" :key="m.key" @click="module = m.key" :style="ui.seg(module === m.key)">{{ m.name }} <span v-if="count(m.key)" :style="ui.mono">{{ count(m.key) }}</span></div>
      </div>
      <div @click="load" :style="ui.btn + ';margin-left:auto'">Refresh</div>
    </div>
    <div v-if="message" :style="ui.ok">{{ message }}</div>
    <div v-if="error" :style="ui.err">{{ error }}</div>

    <div :style="ui.card">
      <div :style="ui.head + ';display:grid;grid-template-columns:110px 1fr 1.2fr 280px;gap:12px'"><div>Ref</div><div>Who</div><div>What</div><div></div></div>
      <div v-for="p in shown" :key="p.module + p.record_id" :style="ui.row + ';display:grid;grid-template-columns:110px 1fr 1.2fr 280px;gap:12px'">
        <div>
          <div :style="ui.mono + ';font-weight:600'">{{ p.ref }}</div>
          <div :style="ui.muted">{{ label(p.module) }}</div>
        </div>
        <div>
          <div style="font-weight:600">{{ p.employee || '—' }}</div>
          <div :style="ui.muted">Step {{ p.step }} · {{ label(p.level) }}</div>
        </div>
        <div>
          <div>{{ p.title }}</div>
          <div v-if="p.note" :style="ui.muted">“{{ p.note }}”</div>
          <div v-if="history[key(p)]" style="margin-top:6px;display:grid;gap:3px">
            <div v-for="h in history[key(p)]" :key="h.id" style="font-size:11.5px;color:#5d6b7d">
              {{ h.step }}. {{ label(h.level) }}: <span :style="badge(h.status)">{{ h.status }}</span>
              <template v-if="h.approver_name"> {{ h.approver_name }} {{ dmy(h.acted_at) }}</template>
              <template v-if="h.remarks"> · “{{ h.remarks }}”</template>
            </div>
          </div>
        </div>
        <div style="display:grid;gap:6px">
          <input v-model="remarks[key(p)]" placeholder="Remarks (needed to reject)" :style="ui.input" />
          <div style="display:flex;gap:6px;justify-content:flex-end">
            <div v-if="canHistory" @click="toggleHistory(p)" :style="ui.btnSmall">History</div>
            <div @click="decide(p, false)" :style="ui.btnBad">Reject</div>
            <div @click="decide(p, true)" :style="ui.btnGood">Approve</div>
          </div>
        </div>
      </div>
      <div v-if="!loading && !shown.length" style="padding:30px;text-align:center;color:#7d8b9c;font-size:13px">Nothing is waiting for you.</div>
      <div v-if="loading" style="padding:30px;text-align:center;color:#7d8b9c;font-size:13px">Loading…</div>
    </div>
  </div>
</template>

<script setup lang="ts">
// Approvals waiting for the signed-in user. Used at HRM > Approvals and My HR > To approve.
// API: GET /api/approvals/pending, POST /api/approvals/{module}/{id} {approve, remarks},
//      GET /api/approvals/{module}/{id}/history (needs hrm.view)
import { computed, onMounted, reactive, ref } from 'vue';
import { api, ui, badge, label, dmy } from '../../api/hrm.js';
import { can } from '../../api/auth.js';

const MODULES = [
  { key: 'all', name: 'All' }, { key: 'leave', name: 'Leave' }, { key: 'overtime', name: 'Overtime' },
  { key: 'correction', name: 'Attendance fixes' }, { key: 'deduction', name: 'Deductions' }, { key: 'payroll', name: 'Payroll' },
];

const items = ref<any[]>([]);
const module = ref('all');
const remarks = reactive<Record<string, string>>({});
const history = reactive<Record<string, any[]>>({});
const loading = ref(true);
const error = ref('');
const message = ref('');
const canHistory = can('hrm.view');

const key = (p: any) => p.module + '-' + p.record_id;
const shown = computed(() => items.value.filter(p => module.value === 'all' || p.module === module.value));
const count = (m: string) => (m === 'all' ? items.value.length : items.value.filter(p => p.module === m).length);

async function load() {
  loading.value = true;
  error.value = '';
  try {
    items.value = await api.get('/approvals/pending');
  } catch (e: any) {
    error.value = e.message;
  } finally {
    loading.value = false;
  }
}

async function decide(p: any, approve: boolean) {
  message.value = '';
  error.value = '';
  const text = (remarks[key(p)] || '').trim();
  if (!approve && !text) {
    error.value = 'Write why in the remarks to reject ' + p.ref + '.';
    return;
  }
  try {
    const r = await api.post('/approvals/' + p.module + '/' + p.record_id, { approve, remarks: text || null });
    message.value = p.ref + ': ' + (r.result === 'next' ? 'approved, now waiting for the next level.' : r.result === 'approved' ? 'approved.' : 'rejected.');
    await load();
  } catch (e: any) {
    error.value = e.message;
  }
}

async function toggleHistory(p: any) {
  if (history[key(p)]) {
    delete history[key(p)];
    return;
  }
  try {
    history[key(p)] = await api.get('/approvals/' + p.module + '/' + p.record_id + '/history');
  } catch (e: any) {
    error.value = e.message;
  }
}

onMounted(load);
</script>
