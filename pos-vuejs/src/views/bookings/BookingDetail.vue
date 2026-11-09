<template>
  <div @click="emit('close')" style="position:fixed;inset:0;background:rgba(9,22,35,.42);z-index:30"></div>
  <div style="position:fixed;top:0;right:0;bottom:0;width:440px;max-width:100vw;background:#ffffff;z-index:31;display:flex;flex-direction:column;box-shadow:-20px 0 50px -24px rgba(9,22,35,.6)">
    <div v-if="!b" style="padding:40px;text-align:center;font-size:13px;color:#7d8b9c">{{ error || 'Loading…' }}</div>
    <template v-else>
      <div style="padding:18px 22px;border-bottom:1px solid #eaeff5;display:flex;align-items:flex-start;gap:12px">
        <div style="min-width:0">
          <div style="font-family:'IBM Plex Mono',monospace;font-size:11.5px;color:#7d8b9c">{{ b.ref }} · {{ b.branch }}</div>
          <div style="display:flex;align-items:center;gap:9px;margin-top:2px">
            <div style="font-size:17px;font-weight:700">{{ b.customer_name }}</div>
            <span :style="badge(b.status)">{{ STATUS[b.status].name }}</span>
          </div>
          <div style="font-size:12.5px;color:#5d6b7d;margin-top:3px">{{ dayLabel(b.date) }} · {{ b.start_time }}–{{ b.end_time }}</div>
        </div>
        <div @click="emit('close')" style="margin-left:auto;width:32px;height:32px;border-radius:8px;border:1px solid #dfe6ee;display:grid;place-items:center;cursor:pointer;color:#5d6b7d;font-size:15px;flex:none">×</div>
      </div>

      <div style="flex:1;overflow-y:auto;padding:18px 22px;display:grid;gap:16px;align-content:start">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px 16px">
          <div v-for="f in facts" :key="f[0]">
            <div :style="caps">{{ f[0] }}</div>
            <div style="font-size:13px;font-weight:500">{{ f[1] }}</div>
          </div>
        </div>

        <!-- Deposit -->
        <div :style="depositBox">
          <div style="flex:1">
            <div style="font-size:12.5px;font-weight:600">{{ depositTitle }}</div>
            <div style="font-size:11.5px;opacity:.85;margin-top:1px">{{ depositSub }}</div>
          </div>
          <template v-if="canTakeDeposit">
            <div @click="takeDeposit('cash')" :style="smallBtn">Cash</div>
            <div @click="takeDeposit('card')" :style="smallBtn">Card</div>
          </template>
        </div>

        <div v-if="b.notes">
          <div :style="caps">Notes</div>
          <div style="font-size:13px;color:#4a5866;line-height:1.55;background:#f6f9fc;border-radius:9px;padding:10px 12px">{{ b.notes }}</div>
        </div>

        <div>
          <div :style="caps + 'margin-bottom:8px'">Activity</div>
          <div style="display:grid;gap:10px">
            <div v-for="(l, i) in b.logs" :key="i" style="display:flex;gap:10px">
              <div :style="'width:8px;height:8px;border-radius:50%;margin-top:5px;flex:none;background:' + (TONE_DOT[l.tone] || TONE_DOT.neutral)"></div>
              <div>
                <div style="font-size:12.5px">{{ l.text }}</div>
                <div style="font-size:11px;color:#9aa7b6">{{ when(l.at) }}</div>
              </div>
            </div>
          </div>
        </div>

        <div v-if="error" style="background:#fdeaea;color:#b02020;border-radius:10px;padding:9px 12px;font-size:12.5px">{{ error }}</div>
      </div>

      <!-- What can happen next -->
      <div v-if="canEdit" style="padding:14px 22px;border-top:1px solid #eaeff5;display:flex;gap:8px;flex-wrap:wrap;justify-content:flex-end">
        <div v-if="b.next.includes('cancelled')" @click="setStatus('cancelled')" :style="btn('d')">Cancel booking</div>
        <div v-if="b.next.includes('noshow')" @click="setStatus('noshow')" :style="btn('d')">No-show</div>
        <div v-if="b.next.length && b.status !== 'seated'" @click="emit('edit', b)" :style="btn('')">Edit</div>
        <div v-if="b.next.includes('confirmed')" @click="setStatus('confirmed')" :style="btn('p')">Confirm</div>
        <div v-if="b.next.includes('seated') && b.date === today" @click="setStatus('seated')" :style="btn('p')">Seat guests</div>
        <div v-if="b.next.includes('completed')" @click="setStatus('completed')" :style="btn('g')">Complete</div>
        <div v-if="!b.next.length && (b.status === 'noshow' || b.status === 'cancelled')" @click="emit('rebook', b)" :style="btn('')">Rebook</div>
      </div>
    </template>
  </div>
</template>

<script setup lang="ts">
// One booking: details, deposit, history and the status buttons.
// GET /api/bookings/{id}, POST /api/bookings/{id}/status, POST /api/bookings/{id}/deposit.
import { computed, onMounted, ref } from 'vue';
import { api } from '../../api/http.js';
import { can } from '../../api/auth.js';
import { STATUS, TONE_DOT, badge, dayLabel, money } from '../../api/bookings.js';

const props = defineProps<{ id: number; today: string }>();
const emit = defineEmits<{ (e: 'close'): void; (e: 'changed', booking: any): void; (e: 'edit', booking: any): void; (e: 'rebook', booking: any): void }>();

const b = ref<any>(null);
const error = ref('');
const busy = ref(false);
const canEdit = can('bookings.edit');

const caps = "font-family:'IBM Plex Mono',monospace;font-size:9.5px;letter-spacing:0.07em;text-transform:uppercase;color:#5d6b7d;margin-bottom:3px;";
const smallBtn = 'height:30px;padding:0 12px;border-radius:7px;background:#ffffff;border:1px solid currentColor;display:flex;align-items:center;font-size:12px;font-weight:600;cursor:pointer';

function btn(kind: string) {
  return 'height:36px;padding:0 14px;border-radius:8px;display:flex;align-items:center;font-size:12.5px;font-weight:600;cursor:pointer;'
    + (kind === 'p' ? 'background:var(--c-primary);color:#ffffff;' : kind === 'g' ? 'background:#2f9e63;color:#ffffff;'
      : kind === 'd' ? 'border:1px solid #f3cfcf;background:#fdf4f4;color:#b02020;' : 'border:1px solid #dfe6ee;background:#ffffff;color:#4a5866;');
}

function when(iso: string) {
  const d = new Date(iso);
  return d.toLocaleDateString('en-AU', { day: 'numeric', month: 'short' }) + ' · ' + String(d.getHours()).padStart(2, '0') + ':' + String(d.getMinutes()).padStart(2, '0');
}

const facts = computed(() => {
  const x = b.value;
  return [
    ['Table', x.table.name + ' · ' + x.table.seats + ' seats'],
    ['Party', x.size + (x.size === 1 ? ' guest' : ' guests')],
    ['Mobile', x.customer_phone || '—'],
    ['Source', x.source],
    ['Duration', x.duration + ' min'],
    ['Zone', x.table.zone || '—'],
  ];
});

const canTakeDeposit = computed(() => canEdit && b.value.deposit > 0 && !b.value.deposit_paid && ['pending', 'confirmed', 'seated'].includes(b.value.status));
const depositTitle = computed(() => {
  const x = b.value;
  if (!x.deposit) return 'No deposit';
  return x.deposit_paid ? 'Deposit paid · ' + money(x.deposit) + ' by ' + x.deposit_method : 'Deposit due · ' + money(x.deposit);
});
const depositSub = computed(() => {
  const x = b.value;
  if (!x.deposit) return 'Add one with Edit if needed';
  return x.deposit_paid ? 'In the branch’s ' + (x.deposit_method === 'cash' ? 'cash drawer' : 'card account') : 'The booking stays pending until it is paid. Take it by:';
});
const depositBox = computed(() => {
  const x = b.value;
  return 'display:flex;align-items:center;gap:8px;border-radius:10px;padding:11px 13px;'
    + (x.deposit ? (x.deposit_paid ? 'background:#e7f6ee;color:#0f6b43;' : 'background:#fdf1dd;color:#8a5300;') : 'background:#f6f9fc;color:#5d6b7d;');
});

async function load() {
  try {
    b.value = await api.get('/bookings/' + props.id);
  } catch (e: any) {
    error.value = e.message || 'Could not load the booking.';
  }
}

async function setStatus(status: string) {
  if (busy.value) return;
  busy.value = true;
  error.value = '';
  try {
    b.value = await api.post('/bookings/' + props.id + '/status', { status });
    emit('changed', b.value);
  } catch (e: any) {
    error.value = e.message || 'Could not change the booking.';
  }
  busy.value = false;
}

async function takeDeposit(method: string) {
  if (busy.value) return;
  if (!window.confirm('Take the ' + money(b.value.deposit) + ' deposit by ' + method + '?')) return;
  busy.value = true;
  error.value = '';
  try {
    b.value = await api.post('/bookings/' + props.id + '/deposit', { method });
    emit('changed', b.value);
  } catch (e: any) {
    error.value = e.message || 'Could not take the deposit.';
  }
  busy.value = false;
}

onMounted(load);
</script>
