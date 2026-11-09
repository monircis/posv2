<template>
  <div @click="emit('close')" style="position:fixed;inset:0;background:rgba(9,22,35,.42);z-index:30"></div>
  <div style="position:fixed;top:0;right:0;bottom:0;width:480px;max-width:100vw;background:#ffffff;z-index:31;display:flex;flex-direction:column;box-shadow:-20px 0 50px -24px rgba(9,22,35,.6)">
    <div style="padding:18px 22px;border-bottom:1px solid #eaeff5;display:flex;align-items:center;gap:12px">
      <div>
        <div style="font-size:16px;font-weight:700">{{ booking ? 'Edit ' + booking.ref : 'New booking' }}</div>
        <div style="font-size:12px;color:#5d6b7d;margin-top:2px">{{ branchName }}</div>
      </div>
      <div @click="emit('close')" :style="closeBtn">×</div>
    </div>

    <div style="flex:1;overflow-y:auto;padding:18px 22px;display:grid;gap:15px;align-content:start">
      <!-- Customer: pick a saved customer, or just type a name and mobile -->
      <div>
        <label :style="label">Customer <span style="color:#d93a3a">*</span></label>
        <div style="position:relative">
          <input v-model="form.customer_name" @input="form.contact_id = null; showMatches = true" @blur="hideMatches" placeholder="Name, or search saved customers" :style="input" />
          <div v-if="showMatches && matches.length" style="position:absolute;left:0;right:0;top:40px;background:#ffffff;border:1px solid #dfe6ee;border-radius:9px;box-shadow:0 12px 30px -12px rgba(9,22,35,.35);z-index:5;max-height:220px;overflow:auto">
            <div v-for="c in matches" :key="c.id" @mousedown.prevent="pickContact(c)" style="padding:8px 12px;font-size:12.5px;cursor:pointer;border-bottom:1px solid #f0f4f8" v-pseudo:hover="'background:#f4f9ff'">
              <b>{{ c.name }}</b> <span style="color:#7d8b9c">{{ c.phone }}</span>
            </div>
          </div>
        </div>
        <div v-if="form.contact_id" style="font-size:11px;color:#0f6b43;margin-top:4px">Saved customer</div>
      </div>
      <div>
        <label :style="label">Mobile</label>
        <input v-model="form.customer_phone" placeholder="04xx xxx xxx" :style="input + mono" />
      </div>

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
        <div>
          <label :style="label">Party size</label>
          <div style="display:flex;align-items:center;height:36px;border:1px solid #dfe6ee;border-radius:8px;overflow:hidden">
            <div @click="setSize(form.size - 1)" :style="stepBtn + 'border-right:1px solid #dfe6ee'">−</div>
            <input v-model.number="form.size" @change="setSize(form.size)" style="flex:1;width:40px;text-align:center;border:0;outline:none;font-family:'IBM Plex Mono',monospace;font-size:14px;font-weight:600" />
            <div @click="setSize(form.size + 1)" :style="stepBtn + 'border-left:1px solid #dfe6ee'">+</div>
          </div>
        </div>
        <div>
          <label :style="label">Date</label>
          <input v-model="form.date" type="date" :min="today" :style="input" />
        </div>
      </div>

      <div style="display:grid;grid-template-columns:1fr 1.4fr;gap:12px">
        <div>
          <label :style="label">Time</label>
          <select v-model="form.start_time" :style="input + mono + 'cursor:pointer'">
            <option v-for="t in times" :key="t" :value="t">{{ t }}</option>
          </select>
        </div>
        <div>
          <label :style="label">Duration</label>
          <div style="display:flex;gap:3px;background:#eef2f6;padding:3px;border-radius:9px">
            <div v-for="d in [60, 90, 120, 180]" :key="d" @click="form.duration = d" :style="seg(form.duration === d)">{{ d < 120 ? d + 'm' : d / 60 + 'h' }}</div>
          </div>
        </div>
      </div>

      <div>
        <div style="display:flex;align-items:baseline;margin-bottom:6px">
          <label :style="label + 'margin:0;flex:1'">Table or room <span style="color:#d93a3a">*</span></label>
          <div @click="bestFit" style="font-size:12px;font-weight:600;color:var(--c-primary);cursor:pointer">Pick best fit</div>
        </div>
        <div v-if="!activeTables.length" style="font-size:12.5px;color:#8a5300;background:#fdf1dd;border-radius:9px;padding:9px 12px">This branch has no tables yet. Add them with the Tables button.</div>
        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:6px">
          <div v-for="t in activeTables" :key="t.id" @click="pickTable(t)" :style="tableStyle(t)">
            <div style="font-size:12.5px;font-weight:600">{{ t.name }}</div>
            <div :style="'font-size:10.5px;margin-top:1px;color:' + tableNote(t).color">{{ tableNote(t).text }}</div>
          </div>
        </div>
      </div>

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
        <div>
          <label :style="label">Deposit</label>
          <input v-model="form.deposit" :disabled="!!(booking && booking.deposit_paid)" placeholder="0.00" :style="input + mono + 'text-align:right'" />
          <div style="font-size:11px;color:#8b98a8;margin-top:4px">{{ depositHint }}</div>
        </div>
        <div>
          <label :style="label">Source</label>
          <select v-model="form.source" :style="input + 'cursor:pointer'">
            <option>Phone</option><option>Online</option><option>Walk-in</option>
          </select>
        </div>
      </div>

      <div>
        <label :style="label">Notes</label>
        <textarea v-model="form.notes" placeholder="Occasion, allergies, high chair, set menu" style="width:100%;min-height:60px;border:1px solid #dfe6ee;border-radius:8px;padding:9px 11px;font-family:'Public Sans',sans-serif;font-size:13px;outline:none;resize:vertical"></textarea>
      </div>

      <div v-if="problems.length || error" style="background:#fdeaea;border:1px solid #f3cfcf;border-radius:10px;padding:10px 13px;display:grid;gap:4px">
        <div v-for="p in problems" :key="p" style="font-size:12px;color:#b02020">× {{ p }}</div>
        <div v-if="error" style="font-size:12px;color:#b02020">× {{ error }}</div>
      </div>
    </div>

    <div style="padding:14px 22px;border-top:1px solid #eaeff5;display:flex;gap:8px;align-items:center">
      <div style="font-size:12px;color:#5d6b7d;flex:1">{{ summary }}</div>
      <div @click="emit('close')" style="height:34px;padding:0 13px;border-radius:8px;border:1px solid #dfe6ee;display:flex;align-items:center;font-size:12.5px;color:#4a5866;cursor:pointer">Cancel</div>
      <div @click="save" :style="'height:34px;padding:0 15px;border-radius:8px;display:flex;align-items:center;font-size:12.5px;font-weight:600;color:#ffffff;cursor:pointer;background:' + (problems.length ? '#9fbde0' : 'var(--c-primary)')">{{ saving ? 'Saving…' : booking ? 'Save changes' : 'Create booking' }}</div>
    </div>
  </div>
</template>

<script setup lang="ts">
// New or edit booking. POST /api/bookings or PUT /api/bookings/{id}.
// The same checks as the server are shown while typing; the server checks them again when saving.
import { computed, reactive, ref } from 'vue';
import { api } from '../../api/http.js';
import { HOLDS_TABLE, minutes, hm, dayLabel } from '../../api/bookings.js';

const props = defineProps<{
  booking: any | null;     // null = new
  branchId: number;
  branchName: string;
  tables: any[];           // the branch's tables
  bookings: any[];         // bookings of the day shown (to show which tables are free)
  date: string;            // the day shown
  today: string;
  now: number;             // minutes since midnight, shop time
  open: string;
  close: string;
  start?: { tableId?: number; time?: string; customer?: any };   // a slot clicked on the table plan, or a booking to rebook
  contacts: any[];         // saved customers to search
}>();
const emit = defineEmits<{ (e: 'close'): void; (e: 'saved', booking: any): void }>();

const label = 'display:block;font-size:12px;font-weight:600;margin-bottom:5px;';
const input = "width:100%;height:36px;border:1px solid #dfe6ee;border-radius:8px;padding:0 11px;font-family:'Public Sans',sans-serif;font-size:13px;outline:none;background:#ffffff;";
const mono = "font-family:'IBM Plex Mono',monospace;font-size:12.5px;";
const stepBtn = 'width:36px;height:100%;display:grid;place-items:center;cursor:pointer;font-size:16px;color:#4a5866;flex:none;';
const closeBtn = 'margin-left:auto;width:32px;height:32px;border-radius:8px;border:1px solid #dfe6ee;display:grid;place-items:center;cursor:pointer;color:#5d6b7d;font-size:15px';

// Every 15 minutes from opening to an hour before closing.
const times: string[] = [];
for (let m = minutes(props.open); m <= minutes(props.close) - 60; m += 15) times.push(hm(m));

// A new booking starts at the slot clicked, or the next quarter hour today, or 19:00.
function firstTime() {
  if (props.start && props.start.time) return props.start.time;
  if (props.date === props.today) {
    const next = Math.ceil((props.now + 20) / 15) * 15;
    return hm(Math.min(Math.max(next, minutes(props.open)), minutes(props.close) - 60));
  }
  return '19:00';
}

const b = props.booking;
// Rebook: start from the old booking's customer, party and notes.
const copy = props.start && props.start.customer ? props.start.customer : null;
const from = b || copy;
const form = reactive({
  contact_id: from ? from.contact_id : null,
  customer_name: from ? from.customer_name : '',
  customer_phone: from ? from.customer_phone || '' : '',
  size: from ? from.size : 2,
  date: b ? b.date : (props.date < props.today ? props.today : props.date),
  start_time: b ? b.start_time : firstTime(),
  duration: b ? b.duration : 90,
  booking_table_id: b ? b.booking_table_id : (props.start && props.start.tableId ? props.start.tableId : 0),
  deposit: b && b.deposit ? String(b.deposit) : '',
  source: from ? from.source : 'Phone',
  notes: from ? from.notes || '' : '',
});
const saving = ref(false);
const error = ref('');
const showMatches = ref(false);

const activeTables = computed(() => props.tables.filter(t => t.active || (b && t.id === b.booking_table_id)));
const chosen = computed(() => props.tables.find(t => t.id === form.booking_table_id));

// Saved customers whose name or phone matches what is typed.
const matches = computed(() => {
  const q = form.customer_name.trim().toLowerCase();
  if (q.length < 2 || form.contact_id) return [];
  return props.contacts.filter(c => c.name.toLowerCase().includes(q) || String(c.phone || '').replace(/\s/g, '').includes(q.replace(/\s/g, ''))).slice(0, 8);
});

function pickContact(c: any) {
  form.contact_id = c.id;
  form.customer_name = c.name;
  form.customer_phone = c.phone || '';
  showMatches.value = false;
}

function hideMatches() {
  setTimeout(() => { showMatches.value = false; }, 150);
}

// The booking on this table that overlaps the chosen time (only bookings of the day shown are known here).
function clash(t: any) {
  if (form.date !== props.date) return null;
  const start = minutes(form.start_time), end = start + form.duration;
  return props.bookings.find(x => x.booking_table_id === t.id && HOLDS_TABLE.includes(x.status) && (!b || x.id !== b.id)
    && start < minutes(x.start_time) + x.duration && minutes(x.start_time) < end);
}

function tableNote(t: any) {
  const c = clash(t);
  if (c) return { text: 'Booked ' + c.start_time, color: '#b02020' };
  if (t.seats < form.size) return { text: t.seats + ' seats · too small', color: '#9aa7b6' };
  return { text: t.seats + ' seats · free', color: '#0f6b43' };
}

function tableStyle(t: any) {
  const on = form.booking_table_id === t.id;
  const bad = !!clash(t) || t.seats < form.size;
  return 'border-radius:9px;padding:8px 10px;cursor:pointer;' + (on ? 'background:#f4f9ff;box-shadow:inset 0 0 0 1.5px var(--c-primary);' : 'border:1px solid #dfe6ee;') + (bad && !on ? 'opacity:.6;' : '');
}

// A table with a deposit per guest fills in the deposit.
function pickTable(t: any) {
  form.booking_table_id = t.id;
  if (!(b && b.deposit_paid)) form.deposit = t.deposit_per_head > 0 ? String(t.deposit_per_head * form.size) : '';
}

function setSize(n: number) {
  form.size = Math.max(1, Math.min(200, Number(n) || 1));
  const t = chosen.value;
  if (t && t.deposit_per_head > 0 && !(b && b.deposit_paid)) form.deposit = String(t.deposit_per_head * form.size);
}

// The smallest free table that seats the party.
function bestFit() {
  const free = activeTables.value.filter(t => t.seats >= form.size && !clash(t)).sort((x, y) => x.seats - y.seats);
  if (free.length) { pickTable(free[0]); error.value = ''; } else error.value = 'Nothing free for ' + form.size + ' at ' + form.start_time + '. Try another time.';
}

const depositHint = computed(() => {
  if (b && b.deposit_paid) return 'Paid. It cannot be changed.';
  const t = chosen.value;
  return t && t.deposit_per_head > 0 ? t.deposit_per_head + ' per guest for ' + t.name : 'Optional. A booking with a deposit stays pending until it is paid.';
});

const problems = computed(() => {
  const p: string[] = [];
  if (!form.customer_name.trim()) p.push('Enter the customer’s name');
  const t = chosen.value;
  if (!t) p.push('Pick a table or room');
  else {
    if (t.seats < form.size) p.push(t.name + ' seats ' + t.seats + ', the party is ' + form.size);
    const c = clash(t);
    if (c) p.push(t.name + ' is booked ' + c.start_time + '–' + c.end_time + ' by ' + c.customer_name);
  }
  if (minutes(form.start_time) + form.duration > minutes(props.close)) p.push('Ends after closing (' + props.close + ')');
  return p;
});

const summary = computed(() => chosen.value ? chosen.value.name + ' · ' + dayLabel(form.date, true) + ' · ' + form.start_time + '–' + hm(minutes(form.start_time) + form.duration) : '');

async function save() {
  if (saving.value) return;
  if (problems.value.length) { error.value = ''; return; }
  saving.value = true;
  error.value = '';
  const body = {
    branch_id: props.branchId,
    booking_table_id: form.booking_table_id,
    contact_id: form.contact_id,
    customer_name: form.customer_name.trim(),
    customer_phone: form.customer_phone.trim() || null,
    date: form.date,
    start_time: form.start_time,
    duration: form.duration,
    size: form.size,
    deposit: parseFloat(form.deposit) || 0,
    source: form.source,
    notes: form.notes.trim() || null,
  };
  try {
    const saved = b ? await api.put('/bookings/' + b.id, body) : await api.post('/bookings', body);
    emit('saved', saved);
  } catch (e: any) {
    error.value = e.message || 'Could not save the booking.';
  }
  saving.value = false;
}

function seg(on: boolean) {
  return 'flex:1;height:30px;padding:0 10px;border-radius:7px;display:grid;place-items:center;font-size:12.5px;cursor:pointer;white-space:nowrap;'
    + (on ? 'background:#ffffff;color:#0f1c2b;font-weight:700;box-shadow:0 1px 3px rgba(15,28,43,.14);' : 'color:#5d6b7d;font-weight:500;');
}
</script>
