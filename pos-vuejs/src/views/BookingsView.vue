<template>
  <div>
    <div style="padding:18px 24px 0;background:#ffffff;border-bottom:1px solid #dfe6ee">
      <div style="display:flex;align-items:center;gap:7px;font-size:11.5px;color:#7d8b9c;margin-bottom:8px"><span>Bookings</span><span style="color:#c2cedb">/</span><span style="color:#0f1c2b;font-weight:500">{{ view === 'timeline' ? 'Table plan' : 'List' }}</span></div>
      <div style="display:flex;align-items:center;gap:14px;flex-wrap:wrap">
        <h2 style="margin:0;font-size:22px;font-weight:700;letter-spacing:-0.018em">Bookings</h2>
        <!-- Branch: a user with a branch always sees their own -->
        <select v-if="branches.length" v-model.number="branchId" @change="onBranch" title="Branch" style="height:32px;border:1px solid #dfe6ee;border-radius:8px;background:#ffffff;padding:0 8px;font-family:inherit;font-size:12.5px;font-weight:600;cursor:pointer">
          <option v-for="b in branches" :key="b.id" :value="b.id">{{ b.name }}</option>
        </select>
        <div v-else-if="branchName" style="font-size:12.5px;font-weight:600;color:#4a5866;background:#eef2f6;border-radius:7px;padding:5px 10px">{{ branchName }}</div>

        <div style="display:flex;align-items:center;gap:6px;margin-left:8px">
          <div @click="goDay(addDays(date, -1))" :style="arrowBtn">‹</div>
          <div style="min-width:170px;text-align:center">
            <div style="font-size:14px;font-weight:700">{{ dayLabel(date) }}</div>
            <div style="font-size:11px;color:#7d8b9c">{{ date === today ? 'Today · ' : '' }}open {{ open }}–{{ close }}</div>
          </div>
          <div @click="goDay(addDays(date, 1))" :style="arrowBtn">›</div>
          <div @click="goDay(today)" :style="'height:32px;padding:0 12px;border-radius:8px;border:1px solid #dfe6ee;display:flex;align-items:center;font-size:12.5px;font-weight:600;cursor:pointer;' + (date === today ? 'color:#9aa7b6;' : 'color:var(--c-primary);')">Today</div>
          <input type="date" :value="date" @change="goDay(($event.target as HTMLInputElement).value)" title="Go to a date" style="height:32px;border:1px solid #dfe6ee;border-radius:8px;padding:0 6px;font-family:inherit;font-size:12px;color:#4a5866" />
        </div>
        <div style="margin-left:auto;display:flex;gap:8px;align-items:center">
          <div style="display:flex;gap:3px;background:#eef2f6;padding:3px;border-radius:9px">
            <div @click="view = 'timeline'" :style="seg(view === 'timeline')">Table plan</div>
            <div @click="view = 'list'" :style="seg(view === 'list')">List</div>
          </div>
          <div v-if="canEdit" @click="tablesOpen = true" style="height:34px;padding:0 13px;border-radius:8px;border:1px solid #dfe6ee;display:flex;align-items:center;font-size:12.5px;font-weight:600;color:#4a5866;cursor:pointer;background:#ffffff">Tables</div>
          <div v-if="canCreate" @click="newBooking()" style="height:34px;padding:0 15px;border-radius:8px;background:var(--c-primary);color:#ffffff;display:flex;align-items:center;font-size:12.5px;font-weight:600;cursor:pointer">+ New booking</div>
        </div>
      </div>
      <!-- The week: how many bookings each day -->
      <div style="display:flex;gap:6px;margin-top:14px;padding-bottom:12px;overflow-x:auto">
        <div v-for="d in week" :key="d.date" @click="goDay(d.date)" :style="dayStyle(d.date)">
          <div style="font-size:10.5px;text-transform:uppercase;letter-spacing:.05em">{{ dayLabel(d.date, true).split(' ')[0].replace(',', '') }}</div>
          <div style="font-size:16px;font-weight:700;font-family:'IBM Plex Mono',monospace">{{ Number(d.date.slice(8)) }}</div>
          <div :style="'font-size:10.5px;' + (d.date === date ? 'color:#b9c7d6;' : 'color:#8b98a8;')">{{ d.count ? d.count + ' booked' : 'none' }}</div>
        </div>
      </div>
    </div>

    <div style="padding:16px 24px 32px;display:grid;gap:14px">
      <div v-if="message" style="background:#e7f6ee;color:#0f6b43;border-radius:10px;padding:10px 14px;font-size:12.5px">{{ message }}</div>
      <div v-if="error" style="background:#fdeaea;color:#b02020;border-radius:10px;padding:10px 14px;font-size:12.5px">{{ error }}</div>

      <div style="display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px">
        <div v-for="k in kpis" :key="k.k" style="background:#ffffff;border:1px solid #dfe6ee;border-radius:12px;padding:13px 16px">
          <div style="font-size:11.5px;color:#7d8b9c">{{ k.k }}</div>
          <div style="font-family:'IBM Plex Mono',monospace;font-size:21px;font-weight:600;letter-spacing:-0.02em;margin-top:2px">{{ k.v }}</div>
          <div style="font-size:11.5px;color:#8b98a8;margin-top:1px">{{ k.n }}</div>
        </div>
      </div>

      <div v-if="!loading && !tables.length" style="background:#ffffff;border:1px solid #dfe6ee;border-radius:12px;padding:36px;text-align:center">
        <div style="font-size:14px;font-weight:700">{{ branchName }} has no tables to book yet</div>
        <div style="font-size:12.5px;color:#7d8b9c;margin-top:4px">Add the tables and rooms with the <b>Tables</b> button.</div>
      </div>

      <!-- Table plan: one row per table, the day from opening to closing -->
      <div v-if="view === 'timeline' && tables.length" style="background:#ffffff;border:1px solid #dfe6ee;border-radius:12px;overflow:hidden">
        <div style="display:flex;align-items:center;gap:14px;padding:10px 16px;border-bottom:1px solid #eaeff5;flex-wrap:wrap">
          <div style="font-size:12.5px;color:#5d6b7d;flex:1">{{ canCreate ? 'Click an empty slot to book it. ' : '' }}Click a booking to open it.</div>
          <div v-for="k in ['pending', 'confirmed', 'seated', 'completed']" :key="k" style="display:flex;align-items:center;gap:6px;font-size:11.5px;color:#5d6b7d">
            <div :style="'width:10px;height:10px;border-radius:3px;background:' + STATUS[k].c[2]"></div>{{ STATUS[k].name }}
          </div>
        </div>
        <div style="display:flex">
          <div style="width:150px;flex:none;border-right:1px solid #eaeff5">
            <div style="height:30px;border-bottom:1px solid #eaeff5;background:#f6f9fc"></div>
            <div v-for="t in tables" :key="t.id" style="height:54px;border-bottom:1px solid #f0f4f8;padding:0 14px;display:flex;flex-direction:column;justify-content:center">
              <div style="font-size:13px;font-weight:600">{{ t.name }}<span v-if="!t.active" style="font-size:10.5px;color:#b02020;font-weight:500"> · off</span></div>
              <div style="font-size:11px;color:#8b98a8">{{ t.seats }} seats{{ t.zone ? ' · ' + t.zone : '' }}</div>
            </div>
          </div>
          <div style="flex:1;min-width:0;position:relative">
            <div style="height:30px;border-bottom:1px solid #eaeff5;background:#f6f9fc;display:flex">
              <div v-for="h in hours" :key="h" style="flex:1;font-family:'IBM Plex Mono',monospace;font-size:10px;color:#7d8b9c;padding:9px 0 0 5px;border-left:1px solid #eaeff5">{{ h }}</div>
            </div>
            <div v-for="t in tables" :key="t.id" style="height:54px;border-bottom:1px solid #f0f4f8;position:relative;display:flex">
              <div v-for="s in slots" :key="s" @click="slotClick(t, s)" :title="slotPast(s) ? '' : 'Book ' + t.name + ' at ' + hm(s)" :style="slotStyle(s)" v-pseudo:hover="slotPast(s) || !canCreate ? '' : 'background:#eef5fd'"></div>
              <div v-for="b in bookingsOn(t)" :key="b.id" @click="openId = b.id" :style="blockStyle(b)">
                <div style="font-size:12px;font-weight:700;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">{{ b.customer_name }} · {{ b.size }}</div>
                <div style="font-size:10.5px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;opacity:.85">{{ b.start_time }}–{{ b.end_time }}{{ b.notes ? ' · ' + b.notes : '' }}</div>
              </div>
            </div>
            <div v-if="date === today && now > minutes(open) && now < minutes(close)" :style="'position:absolute;top:30px;bottom:0;width:2px;background:#d93a3a;z-index:3;pointer-events:none;left:' + pct(now) + '%'">
              <div style="position:absolute;top:-1px;left:-22px;background:#d93a3a;color:#ffffff;font-family:'IBM Plex Mono',monospace;font-size:9.5px;padding:2px 5px;border-radius:4px">{{ hm(now) }}</div>
            </div>
          </div>
        </div>
      </div>

      <!-- List with status tabs -->
      <div v-if="view === 'list' && tables.length" style="background:#ffffff;border:1px solid #dfe6ee;border-radius:12px;overflow:hidden">
        <div style="display:flex;gap:2px;padding:4px 12px 0;border-bottom:1px solid #eaeff5;overflow-x:auto">
          <div v-for="k in STATUS_TABS" :key="k" @click="statusTab = k" :style="tabStyle(statusTab === k)">
            {{ k === 'all' ? 'All' : STATUS[k].name }}<span :style="countStyle(statusTab === k)">{{ countOf(k) }}</span>
          </div>
        </div>
        <div :style="listGrid + 'padding:10px 16px;background:#f6f9fc;border-bottom:1px solid #dfe6ee;font-family:\'IBM Plex Mono\',monospace;font-size:9.5px;letter-spacing:0.07em;text-transform:uppercase;color:#5d6b7d'">
          <div>Time</div><div>Customer</div><div style="text-align:right">Party</div><div>Table</div><div>Status</div><div style="text-align:right">Deposit</div><div>Source</div>
        </div>
        <div v-for="b in listed" :key="b.id" @click="openId = b.id" :style="listGrid + 'padding:10px 16px;border-bottom:1px solid #f0f4f8;cursor:pointer'" v-pseudo:hover="'background:#f9fbfd'">
          <div style="font-family:'IBM Plex Mono',monospace;font-size:12.5px;font-weight:500">{{ b.start_time }}–{{ b.end_time }}</div>
          <div style="min-width:0">
            <div style="font-size:13px;font-weight:500;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">{{ b.customer_name }}</div>
            <div style="font-size:11px;color:#8b98a8;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">{{ [b.customer_phone, b.notes].filter(Boolean).join(' · ') || ' ' }}</div>
          </div>
          <div style="font-family:'IBM Plex Mono',monospace;font-size:12.5px;text-align:right">{{ b.size }}</div>
          <div style="font-size:12.5px;color:#4a5866">{{ tableName(b.booking_table_id) }}</div>
          <div><span :style="badge(b.status)">{{ STATUS[b.status].name }}</span></div>
          <div :style="'font-family:\'IBM Plex Mono\',monospace;font-size:12.5px;text-align:right;' + (b.deposit && !b.deposit_paid ? 'color:#8a5300;' : b.deposit ? 'color:#0f6b43;' : 'color:#9aa7b6;')">{{ b.deposit ? money(b.deposit) : '—' }}</div>
          <div style="font-size:12px;color:#7d8b9c">{{ b.source }}</div>
        </div>
        <div v-if="!listed.length" style="padding:36px 20px;text-align:center;font-size:12.5px;color:#7d8b9c">No bookings for this day and status.</div>
      </div>
    </div>

    <BookingDetail v-if="openId" :key="openId" :id="openId" :today="today" @close="openId = 0" @changed="onChanged" @edit="editBooking" @rebook="rebook" />
    <BookingForm v-if="formOpen" :booking="editing" :branch-id="branchId" :branch-name="branchName" :tables="tables" :bookings="bookings" :date="date"
      :today="today" :now="now" :open="open" :close="close" :start="formStart" :contacts="contacts" @close="formOpen = false" @saved="onSaved" />
    <TablesManager v-if="tablesOpen" :branch-id="branchId" :branch-name="branchName" @close="tablesOpen = false" @changed="load" />
  </div>
</template>

<script setup lang="ts">
// Bookings for one branch and one day. API: GET /api/bookings?branch_id=&date=
// The new/edit form, one booking and the tables are in views/bookings.
import { computed, onMounted, ref } from 'vue';
import { useErpPage } from '../erp/useErpPage';
import { api } from '../api/http.js';
import { can } from '../api/auth.js';
import { STATUS, HOLDS_TABLE, badge, minutes, hm, dayLabel, addDays, money } from '../api/bookings.js';
import BookingForm from './bookings/BookingForm.vue';
import BookingDetail from './bookings/BookingDetail.vue';
import TablesManager from './bookings/TablesManager.vue';

useErpPage('bookings', 'bk_nav'); // the sidebar

const STATUS_TABS = ['all', 'pending', 'confirmed', 'seated', 'completed', 'noshow', 'cancelled'];
const listGrid = 'display:grid;grid-template-columns:0.9fr 1.8fr 0.6fr 1fr 1fr 0.9fr 0.8fr;gap:12px;align-items:center;';
const arrowBtn = 'width:32px;height:32px;border-radius:8px;border:1px solid #dfe6ee;display:grid;place-items:center;cursor:pointer;color:#4a5866';
const canCreate = can('bookings.create');
const canEdit = can('bookings.edit');

// The branch a user with no branch picked last time (only a convenience on this computer).
function savedBranch() {
  try { return Number(localStorage.getItem('bk_branch')) || 0; } catch { return 0; }
}

const branchId = ref(savedBranch());
const branchName = ref('');
const branches = ref<any[]>([]);
const date = ref('');
const today = ref('');
const now = ref(0);
const open = ref('11:00');
const close = ref('22:00');
const tables = ref<any[]>([]);
const bookings = ref<any[]>([]);
const week = ref<any[]>([]);
const depositsHeld = ref(0);
const contacts = ref<any[]>([]);
const loading = ref(true);
const error = ref('');
const message = ref('');

const view = ref('timeline');
const statusTab = ref('all');
const openId = ref(0);
const formOpen = ref(false);
const editing = ref<any>(null);
const formStart = ref<any>(null);
const tablesOpen = ref(false);

async function load() {
  loading.value = true;
  error.value = '';
  const params = new URLSearchParams();
  if (branchId.value) params.set('branch_id', String(branchId.value));
  if (date.value) params.set('date', date.value);
  try {
    const res = await api.get('/bookings?' + params.toString());
    branchId.value = res.branch.id;
    branchName.value = res.branch.name;
    branches.value = res.branches;
    date.value = res.date;
    today.value = res.today;
    now.value = res.now;
    open.value = res.open;
    close.value = res.close;
    tables.value = res.tables;
    bookings.value = res.bookings;
    week.value = res.week;
    depositsHeld.value = res.deposits_held;
  } catch (e: any) {
    error.value = e.message || 'Could not load the bookings.';
  }
  loading.value = false;
}

function goDay(d: string) {
  if (!d) return;
  date.value = d;
  load();
}

function onBranch() {
  try { localStorage.setItem('bk_branch', String(branchId.value)); } catch { /* ignore */ }
  load();
}

// --- table plan ---
const hours = computed(() => {
  const list: string[] = [];
  for (let m = minutes(open.value); m < minutes(close.value); m += 60) list.push(hm(m));
  return list;
});
const slots = computed(() => {
  const list: number[] = [];
  for (let m = minutes(open.value); m < minutes(close.value); m += 30) list.push(m);
  return list;
});
const span = computed(() => minutes(close.value) - minutes(open.value));

function pct(m: number) {
  return (m - minutes(open.value)) / span.value * 100;
}

function slotPast(s: number) {
  return date.value < today.value || (date.value === today.value && s + 30 <= now.value);
}

function slotStyle(s: number) {
  const i = (s - minutes(open.value)) / 30;
  return 'flex:1;height:100%;' + (i % 2 === 0 ? 'border-left:1px solid #eaeff5;' : 'border-left:1px dashed #f2f5f8;')
    + (slotPast(s) ? 'background:#fafbfc;' : canCreate ? 'cursor:pointer;' : '');
}

function slotClick(t: any, s: number) {
  if (slotPast(s) || !canCreate || !t.active) return;
  newBooking({ tableId: t.id, time: hm(Math.max(s, date.value === today.value ? Math.ceil(now.value / 15) * 15 : s)) });
}

function bookingsOn(t: any) {
  return bookings.value.filter(b => b.booking_table_id === t.id && b.status !== 'cancelled');
}

function blockStyle(b: any) {
  const c = STATUS[b.status].c;
  const start = minutes(b.start_time);
  const width = Math.min(b.duration, minutes(close.value) - start) / span.value * 100;
  return 'position:absolute;top:5px;bottom:5px;left:calc(' + pct(start) + '% + 2px);width:calc(' + width + '% - 4px);border-radius:7px;padding:5px 8px;cursor:pointer;overflow:hidden;z-index:2;'
    + 'background:' + c[0] + ';color:' + c[1] + ';box-shadow:inset 3px 0 0 ' + c[2] + ';' + (b.status === 'noshow' ? 'opacity:.55;text-decoration:line-through;' : '')
    + (openId.value === b.id ? 'outline:2px solid ' + c[2] + ';' : '');
}

// --- list ---
const listed = computed(() => bookings.value.filter(b => statusTab.value === 'all' || b.status === statusTab.value));

function countOf(k: string) {
  return k === 'all' ? bookings.value.length : bookings.value.filter(b => b.status === k).length;
}

function tableName(id: number) {
  const t = tables.value.find(x => x.id === id);
  return t ? t.name : '';
}

// --- numbers at the top ---
const kpis = computed(() => {
  const live = bookings.value.filter(b => b.status !== 'cancelled' && b.status !== 'noshow');
  const pending = bookings.value.filter(b => b.status === 'pending').length;
  const inUse = date.value === today.value
    ? tables.value.filter(t => live.some(b => b.booking_table_id === t.id && HOLDS_TABLE.includes(b.status) && minutes(b.start_time) <= now.value && now.value < minutes(b.start_time) + b.duration)).length
    : 0;
  return [
    { k: 'Bookings', v: String(live.length), n: pending ? pending + ' waiting to confirm' : 'none waiting' },
    { k: 'Covers', v: String(live.reduce((t, b) => t + b.size, 0)), n: 'guests expected' },
    { k: 'Tables in use now', v: date.value === today.value ? inUse + ' / ' + tables.value.length : '—', n: date.value === today.value ? 'at ' + hm(now.value) : 'only shown for today' },
    { k: 'Deposits held', v: money(depositsHeld.value), n: 'paid, for upcoming bookings' },
  ];
});

// --- form and detail ---
async function loadContacts() {
  if (contacts.value.length) return;
  try {
    const list = await api.get('/contacts');
    contacts.value = list.filter((c: any) => c.type !== 'Supplier').map((c: any) => ({ id: c.id, name: c.name, phone: c.phone || c.mobile || '' }));
  } catch { /* the form still works by typing the name */ }
}

function newBooking(start?: any) {
  editing.value = null;
  formStart.value = start || null;
  formOpen.value = true;
  loadContacts();
}

function editBooking(b: any) {
  openId.value = 0;
  if (b.date !== date.value) goDay(b.date);
  editing.value = b;
  formStart.value = null;
  formOpen.value = true;
  loadContacts();
}

// A no-show or cancelled booking booked again: a new booking with the same customer and party.
function rebook(b: any) {
  openId.value = 0;
  editing.value = null;
  formStart.value = { tableId: b.booking_table_id, customer: b };
  formOpen.value = true;
  loadContacts();
}

function onSaved(b: any) {
  formOpen.value = false;
  message.value = b.ref + ' saved · ' + b.table.name + ' at ' + b.start_time + (b.status === 'pending' ? ' · waiting on the deposit' : '');
  date.value = b.date;
  load();
  openId.value = b.id;
}

function onChanged(b: any) {
  message.value = b.ref + ' is now ' + STATUS[b.status].name.toLowerCase() + '.';
  load();
}

function seg(on: boolean) {
  return 'height:30px;padding:0 12px;border-radius:7px;display:grid;place-items:center;font-size:12.5px;cursor:pointer;white-space:nowrap;'
    + (on ? 'background:#ffffff;color:#0f1c2b;font-weight:700;box-shadow:0 1px 3px rgba(15,28,43,.14);' : 'color:#5d6b7d;font-weight:500;');
}

function dayStyle(d: string) {
  const on = d === date.value;
  return 'flex:1;min-width:86px;border-radius:10px;padding:7px 10px;cursor:pointer;display:grid;gap:1px;text-align:center;'
    + (on ? 'background:#0f1c2b;color:#ffffff;' : d === today.value ? 'background:var(--c-primary-soft);color:var(--c-primary-text);' : 'background:#f6f9fc;color:#0f1c2b;');
}

function tabStyle(on: boolean) {
  return 'padding:9px 14px;font-size:13px;cursor:pointer;white-space:nowrap;border-bottom:2px solid ' + (on ? 'var(--c-primary);color:var(--c-primary);font-weight:600;' : 'transparent;color:#5d6b7d;font-weight:500;');
}

function countStyle(on: boolean) {
  return "font-family:'IBM Plex Mono',monospace;font-size:10.5px;border-radius:4px;padding:1px 5px;margin-left:6px;"
    + (on ? 'background:var(--c-primary-soft);color:var(--c-primary-text);' : 'background:#eef2f6;color:#7d8b9c;');
}

onMounted(load);
</script>
