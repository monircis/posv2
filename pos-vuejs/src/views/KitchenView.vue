<template>
  <div data-screen-label="Kitchen board" style="height:100vh;min-height:720px;position:relative">
    <div style="height:100%;overflow:auto">
      <div style="min-width:1280px;display:flex;flex-direction:column;background:#0f1c2b;height:100%;position:relative;overflow:hidden">
        <!-- Top bar -->
        <div style="height:62px;flex:none;background:#08131f;border-bottom:1px solid rgba(255,255,255,.08);display:flex;align-items:center;gap:12px;padding:0 20px">
          <RouterLink to="/" style="height:34px;padding:0 12px;border-radius:8px;border:1px solid rgba(255,255,255,.18);display:flex;align-items:center;font-size:12.5px;font-weight:600;color:#c4dced;text-decoration:none">‹ Back</RouterLink>
          <div style="font-size:17px;font-weight:700;color:#ffffff;letter-spacing:-0.015em">Kitchen</div>
          <select v-if="branches.length" v-model.number="branchId" @change="onBranch" title="Branch" style="height:32px;border:1px solid rgba(255,255,255,.18);border-radius:8px;background:#0f1c2b;color:#c4dced;padding:0 8px;font-family:inherit;font-size:12.5px;font-weight:600;cursor:pointer">
            <option v-for="b in branches" :key="b.id" :value="b.id">{{ b.name }}</option>
          </select>
          <div v-else-if="branchName" style="font-size:12.5px;font-weight:600;color:#c4dced">{{ branchName }}</div>
          <div style="display:flex;align-items:center;gap:7px;background:rgba(77,163,232,.16);border:1px solid rgba(77,163,232,.36);border-radius:99px;padding:5px 12px">
            <div style="width:7px;height:7px;border-radius:50%;background:var(--c-accent)"></div>
            <div style="font-size:12px;font-weight:600;color:#9cd0f5">{{ openTickets.length }} open · {{ countOf('ready') }} ready</div>
          </div>
          <div v-if="overdueCount" style="display:flex;align-items:center;gap:7px;background:rgba(217,58,58,.16);border:1px solid rgba(217,58,58,.36);border-radius:99px;padding:5px 12px">
            <div style="width:7px;height:7px;border-radius:50%;background:#ff6b6b"></div>
            <div style="font-size:12px;font-weight:600;color:#ffb3b3">{{ overdueCount }} over target</div>
          </div>
          <div style="font-size:12px;color:#8fa3b6">{{ avgLabel }}</div>
          <div v-if="offline" style="font-size:12px;font-weight:600;color:#ffb3b3">Not connected · retrying</div>

          <div style="margin-left:auto;display:flex;align-items:center;gap:8px">
            <div style="display:flex;gap:3px;background:rgba(255,255,255,.07);padding:4px;border-radius:9px">
              <div v-for="s in stationTabs" :key="s.key" @click="station = s.key" :style="stationStyle(station === s.key)">
                {{ s.name }}<span :style="'font-family:\'IBM Plex Mono\',monospace;font-size:10.5px;border-radius:4px;padding:1px 5px;background:rgba(255,255,255,' + (station === s.key ? '.2' : '.08') + ')'">{{ s.count }}</span>
              </div>
            </div>
            <div @click="allDay = !allDay" :style="pill(allDay)">All-day</div>
            <div @click="toggleSound" :style="pill(sound)">{{ sound ? 'Chime on' : 'Chime off' }}</div>
            <div style="font-family:'IBM Plex Mono',monospace;font-size:20px;font-weight:600;color:#ffffff;letter-spacing:-0.02em;padding-left:6px;min-width:62px;text-align:right">{{ clock }}</div>
          </div>
        </div>

        <div style="flex:1;display:flex;min-height:0">
          <!-- The four columns -->
          <div style="flex:1;display:flex;gap:1px;min-height:0;min-width:0;background:rgba(255,255,255,.07)">
            <div v-for="col in COLUMNS" :key="col.status" :style="'flex:' + (col.status === 'served' ? '0.75' : '1') + ';min-width:0;background:#0f1c2b;display:flex;flex-direction:column;min-height:0'">
              <div style="flex:none;padding:14px 16px 12px;display:flex;align-items:center;gap:10px;border-bottom:1px solid rgba(255,255,255,.08)">
                <div :style="'width:10px;height:10px;border-radius:50%;background:' + col.color"></div>
                <div style="font-size:14px;font-weight:700;color:#ffffff;flex:1">{{ col.name }}</div>
                <div style="font-family:'IBM Plex Mono',monospace;font-size:13px;font-weight:600;color:#8fa3b6;background:rgba(255,255,255,.08);border-radius:6px;padding:3px 9px">{{ ticketsIn(col.status).length }}</div>
              </div>
              <div style="flex:1;overflow-y:auto;overflow-x:hidden;padding:12px;display:grid;grid-template-columns:minmax(0,1fr);gap:11px;align-content:start;min-height:0">
                <div v-for="t in ticketsIn(col.status)" :key="t.id" :style="cardStyle(t)">
                  <div style="display:flex;align-items:flex-start;gap:10px;margin-bottom:10px">
                    <div style="flex:1;min-width:0">
                      <div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap">
                        <div style="font-family:'IBM Plex Mono',monospace;font-size:18px;font-weight:600;line-height:1.1">{{ t.ref }}</div>
                        <span :style="sourceStyle(t.source)">{{ SOURCES[t.source][0] }}</span>
                        <span v-if="t.rush && t.status !== 'served'" style="background:#d93a3a;color:#ffffff;border-radius:5px;padding:2px 7px;font-size:10.5px;font-weight:800;letter-spacing:.05em">RUSH</span>
                      </div>
                      <div style="font-size:12.5px;color:#4a5866;margin-top:4px;font-weight:500">{{ t.where_text }}</div>
                    </div>
                    <div style="text-align:right">
                      <div :style="'font-family:\'IBM Plex Mono\',monospace;font-size:20px;font-weight:700;letter-spacing:-0.02em;color:' + tone(t)">{{ mmss(elapsed(t)) }}</div>
                      <div style="font-size:10.5px;color:#8b98a8;margin-top:3px">{{ subTime(t) }}</div>
                    </div>
                  </div>
                  <div style="height:4px;border-radius:99px;background:#eef2f6;overflow:hidden;margin-bottom:10px">
                    <div :style="'height:100%;border-radius:99px;width:' + Math.min(100, elapsed(t) / (t.target_minutes * 60) * 100) + '%;background:' + tone(t)"></div>
                  </div>
                  <div style="display:grid;grid-template-columns:minmax(0,1fr);gap:4px;margin-bottom:12px">
                    <div v-for="it in t.items" :key="it.id" @click="tick(t, it)" :style="itemRow(t, it)">
                      <div :style="'width:22px;height:22px;border-radius:6px;flex:none;display:grid;place-items:center;font-size:13px;font-weight:800;margin-top:1px;' + (it.done ? 'background:#2f9e63;color:#ffffff;' : 'box-shadow:inset 0 0 0 2px #c2cedb;color:transparent;')">✓</div>
                      <div style="font-family:'IBM Plex Mono',monospace;font-size:15px;font-weight:700;color:var(--c-primary);flex:none;min-width:24px">{{ qtyText(it) }}</div>
                      <div style="flex:1;min-width:0">
                        <div :style="'font-size:15px;font-weight:600;line-height:1.3;overflow-wrap:anywhere;' + (it.done ? 'text-decoration:line-through;color:#8b98a8;' : '')">{{ it.name }}</div>
                        <div v-if="it.note" style="font-size:12.5px;font-weight:700;color:#b02020;margin-top:1px">{{ it.note }}</div>
                      </div>
                      <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#c2410c;margin-top:4px;flex:none">{{ it.station }}</div>
                    </div>
                  </div>
                  <div v-if="t.note && t.status !== 'served'" style="background:#fdf1dd;color:#8a5300;border-radius:8px;padding:7px 10px;font-size:12.5px;font-weight:600;margin-bottom:12px">{{ t.note }}</div>
                  <div v-if="canEdit" style="display:flex;gap:7px">
                    <div v-if="t.status === 'new' || t.status === 'prep'" @click="toggleRush(t)" :style="secondaryBtn">{{ t.rush ? 'Un-rush' : 'Rush' }}</div>
                    <div v-if="t.status === 'ready'" @click="move(t, 'prep', 'Recalled')" :style="secondaryBtn">Recall</div>
                    <div v-if="t.status === 'served'" @click="move(t, 'ready', 'Recalled')" :style="secondaryBtn">Recall</div>
                    <div v-if="t.status === 'new'" @click="move(t, 'prep', 'Preparing')" :style="mainBtn('var(--c-primary)')">Start</div>
                    <div v-if="t.status === 'prep'" @click="move(t, 'ready', 'Ready')" :style="mainBtn('#2f9e63')">Ready</div>
                    <div v-if="t.status === 'ready'" @click="move(t, 'served', t.source === 'dine' ? 'Served' : 'Picked up')" :style="mainBtn('#0f1c2b')">{{ t.source === 'dine' ? 'Served' : 'Picked up' }}</div>
                  </div>
                </div>
                <div v-if="!ticketsIn(col.status).length" style="border:1px dashed rgba(255,255,255,.14);border-radius:12px;padding:26px 16px;text-align:center">
                  <div style="font-size:13px;color:#5d7285">{{ loaded ? col.empty : 'Loading…' }}</div>
                </div>
              </div>
            </div>
          </div>

          <!-- All-day: what is still to cook, added up -->
          <div v-if="allDay" style="width:220px;flex:none;background:#08131f;border-left:1px solid rgba(255,255,255,.08);display:flex;flex-direction:column;min-height:0">
            <div style="padding:14px 16px 12px;border-bottom:1px solid rgba(255,255,255,.08)">
              <div style="font-size:14px;font-weight:700;color:#ffffff">All-day</div>
              <div style="font-size:11.5px;color:#8fa3b6;margin-top:2px">Still to cook across open tickets</div>
            </div>
            <div style="flex:1;overflow-y:auto;padding:8px 10px">
              <div v-for="r in allDayRows" :key="r.name" style="display:flex;align-items:center;gap:10px;padding:9px 8px;border-bottom:1px solid rgba(255,255,255,.06)">
                <div style="font-family:'IBM Plex Mono',monospace;font-size:20px;font-weight:600;color:#ffffff;min-width:34px;text-align:right">{{ r.qty }}</div>
                <div style="flex:1;min-width:0">
                  <div style="font-size:13px;font-weight:600;color:#e3edf5">{{ r.name }}</div>
                  <div style="font-size:11px;color:#7d93a8">{{ r.station }} · {{ r.tickets }} {{ r.tickets === 1 ? 'ticket' : 'tickets' }}</div>
                </div>
              </div>
              <div v-if="!allDayRows.length" style="padding:24px 8px;text-align:center;font-size:12.5px;color:#5d7285">All caught up</div>
            </div>
          </div>
        </div>

        <!-- Undo the last move -->
        <div v-if="undo" style="position:absolute;left:50%;bottom:22px;transform:translateX(-50%);background:#ffffff;color:#0f1c2b;border-radius:12px;padding:8px 8px 8px 16px;display:flex;align-items:center;gap:14px;box-shadow:0 18px 40px -14px rgba(0,0,0,.7);z-index:20">
          <div style="font-size:13.5px;font-weight:600">{{ undo.text }}</div>
          <div @click="doUndo" style="height:38px;padding:0 16px;border-radius:9px;background:#0f1c2b;color:#ffffff;display:flex;align-items:center;font-size:13px;font-weight:700;cursor:pointer">Undo</div>
        </div>
        <div v-if="flash" style="position:absolute;top:74px;left:50%;transform:translateX(-50%);background:var(--c-primary);color:#ffffff;border-radius:10px;padding:10px 16px;font-size:13.5px;font-weight:700;box-shadow:0 14px 30px -12px rgba(0,0,0,.7);z-index:21">{{ flash }}</div>
        <div v-if="error" style="position:absolute;top:74px;right:20px;background:#fdeaea;color:#b02020;border-radius:10px;padding:10px 16px;font-size:13px;font-weight:600;z-index:21">{{ error }}</div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
// Kitchen board for one branch. GET /api/kitchen?branch_id= every 5 seconds;
// POST /api/kitchen/{id}/status, /undo, /rush and /items/{item} for the buttons.
// Tickets are made by the server when a POS sale has items marked "Send to kitchen".
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { api } from '../api/http.js';
import { can } from '../api/auth.js';

const COLUMNS = [
  { status: 'new', name: 'New', color: 'var(--c-accent)', empty: 'No new tickets' },
  { status: 'prep', name: 'Preparing', color: '#d98c1a', empty: 'Nothing cooking' },
  { status: 'ready', name: 'Ready', color: '#2f9e63', empty: 'Nothing waiting' },
  { status: 'served', name: 'Served', color: '#8fa3b6', empty: 'Nothing served yet' },
];
// Order source: label, background, text colour.
const SOURCES: Record<string, string[]> = {
  dine: ['Dine-in', 'var(--c-primary-soft)', 'var(--c-primary-text)'], take: ['Takeaway', '#fdf1dd', '#8a5300'],
  deliv: ['Delivery', '#f3ecfd', '#5b34a8'], online: ['Online', '#e7f6ee', '#0f6b43'],
};
const canEdit = can('kitchen.edit');
const secondaryBtn = 'height:44px;padding:0 12px;border-radius:9px;border:1px solid #dfe6ee;display:grid;place-items:center;font-size:12.5px;font-weight:600;color:#4a5866;cursor:pointer';

// Small settings remembered on this screen (tablet) only.
function saved(key: string, fallback: string) {
  try { return localStorage.getItem(key) || fallback; } catch { return fallback; }
}
function save(key: string, value: string) {
  try { localStorage.setItem(key, value); } catch { /* ignore */ }
}

const tickets = ref<any[]>([]);
const branchId = ref(Number(saved('kt_branch', '0')) || 0);
const branchName = ref('');
const branches = ref<any[]>([]);
const station = ref('all');
const allDay = ref(true);
const sound = ref(saved('kt_sound', '1') === '1');
const loaded = ref(false);
const offline = ref(false);
const error = ref('');
const flash = ref('');
const undo = ref<any>(null);
const nowMs = ref(Date.now());
let serverOffset = 0;          // server time minus this tablet's time
let known = new Set<number>(); // ticket ids already on the board, to chime for new ones

// --- loading (every 5 seconds) ---
async function load() {
  try {
    const res = await api.get('/kitchen' + (branchId.value ? '?branch_id=' + branchId.value : ''));
    serverOffset = new Date(res.now).getTime() - Date.now();
    branchId.value = res.branch.id;
    branchName.value = res.branch.name;
    branches.value = res.branches;
    const fresh = res.tickets.filter((t: any) => t.status === 'new' && !known.has(t.id));
    if (loaded.value && fresh.length) {
      showFlash('New ticket ' + fresh.map((t: any) => t.ref + ' · ' + t.where_text).join(', '));
      chime();
    }
    known = new Set(res.tickets.map((t: any) => t.id));
    tickets.value = res.tickets;
    loaded.value = true;
    offline.value = false;
  } catch (e) {
    offline.value = true;
  }
}

function onBranch() {
  save('kt_branch', String(branchId.value));
  loaded.value = false;
  load();
}

// --- buttons ---
// Puts the changed ticket in the list straight away (the next refresh confirms it).
function replace(t: any) {
  tickets.value = tickets.value.map(x => (x.id === t.id ? t : x));
}

async function move(t: any, status: string, word: string) {
  error.value = '';
  try {
    replace(await api.post('/kitchen/' + t.id + '/status', { status }));
    showUndo(t, t.ref + ' moved to ' + word);
  } catch (e: any) {
    error.value = e.message;
  }
}

async function tick(t: any, it: any) {
  if (!canEdit || !(t.status === 'new' || t.status === 'prep') || !inStation(it)) return;
  try {
    const updated = await api.post('/kitchen/' + t.id + '/items/' + it.id, {});
    replace(updated);
    if (updated.items.every((i: any) => i.done)) showFlash(t.ref + ': all items done · tap Ready to call it');
  } catch (e: any) {
    error.value = e.message;
  }
}

async function toggleRush(t: any) {
  try {
    replace(await api.post('/kitchen/' + t.id + '/rush', {}));
  } catch (e: any) {
    error.value = e.message;
  }
}

let undoTimer: ReturnType<typeof setTimeout> | undefined;
function showUndo(t: any, text: string) {
  clearTimeout(undoTimer);
  undo.value = { id: t.id, text };
  undoTimer = setTimeout(() => { undo.value = null; }, 8000);
}

async function doUndo() {
  const u = undo.value;
  if (!u) return;
  undo.value = null;
  try {
    replace(await api.post('/kitchen/' + u.id + '/undo', {}));
  } catch (e: any) {
    error.value = e.message;
  }
}

let flashTimer: ReturnType<typeof setTimeout> | undefined;
function showFlash(text: string) {
  clearTimeout(flashTimer);
  flash.value = text;
  flashTimer = setTimeout(() => { flash.value = ''; }, 3000);
}

// A short two-note chime for a new ticket.
function chime() {
  if (!sound.value) return;
  try {
    const ctx = new AudioContext();
    [880, 1320].forEach((f, i) => {
      const o = ctx.createOscillator(), g = ctx.createGain();
      o.frequency.value = f;
      g.gain.setValueAtTime(0.25, ctx.currentTime + i * 0.18);
      g.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + i * 0.18 + 0.3);
      o.connect(g); g.connect(ctx.destination);
      o.start(ctx.currentTime + i * 0.18); o.stop(ctx.currentTime + i * 0.18 + 0.3);
    });
  } catch { /* no sound on this device */ }
}

function toggleSound() {
  sound.value = !sound.value;
  save('kt_sound', sound.value ? '1' : '0');
  if (sound.value) chime();
}

// --- times ---
const now = computed(() => nowMs.value + serverOffset);
function secondsSince(iso: string) {
  return Math.max(0, Math.floor((now.value - new Date(iso).getTime()) / 1000));
}

// Time on the ticket: from placed until ready (or until now while cooking).
function elapsed(t: any) {
  const end = t.ready_at && (t.status === 'ready' || t.status === 'served') ? new Date(t.ready_at).getTime() : now.value;
  return Math.max(0, Math.floor((end - new Date(t.placed_at).getTime()) / 1000));
}

function mmss(s: number) {
  return String(Math.floor(s / 60)).padStart(2, '0') + ':' + String(s % 60).padStart(2, '0');
}

function subTime(t: any) {
  if (t.status === 'served') return 'served ' + mmss(secondsSince(t.served_at)) + ' ago';
  if (t.status === 'ready') return 'waiting ' + mmss(secondsSince(t.ready_at));
  return 'target ' + t.target_minutes + ' min';
}

function isOver(t: any) {
  return elapsed(t) > t.target_minutes * 60;
}

function tone(t: any) {
  if (t.status === 'served') return '#8fa3b6';
  if (t.status === 'ready') return '#2f9e63';
  const e = elapsed(t), target = t.target_minutes * 60;
  return e > target ? '#d93a3a' : e > target * 0.7 ? '#d98c1a' : '#2f9e63';
}

const clock = computed(() => {
  const d = new Date(now.value);
  return String(d.getHours()).padStart(2, '0') + ':' + String(d.getMinutes()).padStart(2, '0');
});

// --- what is shown ---
const openTickets = computed(() => tickets.value.filter(t => t.status === 'new' || t.status === 'prep'));
const overdueCount = computed(() => openTickets.value.filter(isOver).length);
const avgLabel = computed(() => {
  const done = tickets.value.filter(t => t.ready_at);
  if (!done.length) return '';
  const avg = done.reduce((s, t) => s + (new Date(t.ready_at).getTime() - new Date(t.placed_at).getTime()) / 1000, 0) / done.length;
  return 'Avg ticket ' + mmss(Math.round(avg));
});

function inStation(it: any) {
  return station.value === 'all' || it.station === station.value;
}

// Station tabs come from the items on the board (each product's top category).
const stationTabs = computed(() => {
  const names = Array.from(new Set(tickets.value.flatMap(t => t.items.map((i: any) => i.station)))).filter(Boolean).sort();
  const count = (s: string) => openTickets.value.filter(t => t.items.some((i: any) => (s === 'all' || i.station === s) && !i.done)).length;
  return [{ key: 'all', name: 'All stations', count: count('all') }].concat(names.map((n: any) => ({ key: n, name: n, count: count(n) })));
});

function countOf(status: string) {
  return tickets.value.filter(t => t.status === status).length;
}

function ticketsIn(status: string) {
  let list = tickets.value.filter(t => t.status === status && (station.value === 'all' || t.items.some(inStation)));
  if (status === 'served') return list.slice().sort((a, b) => b.served_at.localeCompare(a.served_at)).slice(0, 5);
  return list.slice().sort((a, b) => (Number(b.rush) - Number(a.rush)) || a.placed_at.localeCompare(b.placed_at));
}

const allDayRows = computed(() => {
  const rows: Record<string, any> = {};
  openTickets.value.forEach(t => t.items.filter((i: any) => !i.done && inStation(i)).forEach((i: any) => {
    if (!rows[i.name]) rows[i.name] = { name: i.name, qty: 0, station: i.station, tickets: 0 };
    rows[i.name].qty += Number(i.qty);
    rows[i.name].tickets += 1;
  }));
  return Object.values(rows).sort((a: any, b: any) => b.qty - a.qty).map((r: any) => ({ ...r, qty: Math.round(r.qty * 1000) / 1000 }));
});

function qtyText(it: any) {
  const q = Math.round(Number(it.qty) * 1000) / 1000;
  return it.unit === 'kg' ? q + 'kg' : q + '×';
}

// --- styles ---
function cardStyle(t: any) {
  const served = t.status === 'served';
  return 'background:#ffffff;border-radius:13px;padding:13px 14px;color:#0f1c2b;' + (served ? 'opacity:.6;' : '')
    + (isOver(t) && !served && t.status !== 'ready' ? 'box-shadow:0 0 0 3px #d93a3a;' : t.rush && !served ? 'box-shadow:0 0 0 3px #f59e0b;' : '');
}

function itemRow(t: any, it: any) {
  const mine = inStation(it);
  const can2 = canEdit && (t.status === 'new' || t.status === 'prep') && mine;
  return 'display:flex;align-items:flex-start;gap:9px;padding:6px 6px;border-radius:8px;' + (can2 ? 'cursor:pointer;' : '') + (mine ? '' : 'opacity:.35;');
}

function sourceStyle(src: string) {
  const s = SOURCES[src] || SOURCES.take;
  return 'background:' + s[1] + ';color:' + s[2] + ';border-radius:5px;padding:2px 7px;font-size:10.5px;font-weight:700';
}

function mainBtn(color: string) {
  return 'flex:1;height:44px;border-radius:9px;display:grid;place-items:center;font-size:15px;font-weight:700;color:#ffffff;cursor:pointer;background:' + color;
}

function stationStyle(on: boolean) {
  return 'padding:7px 12px;border-radius:6px;font-size:12.5px;cursor:pointer;display:flex;align-items:center;gap:6px;'
    + (on ? 'background:var(--c-primary);color:#ffffff;font-weight:600;' : 'color:#8fa3b6;font-weight:500;');
}

function pill(on: boolean) {
  return 'height:34px;padding:0 12px;border-radius:8px;display:flex;align-items:center;font-size:12.5px;font-weight:600;cursor:pointer;'
    + (on ? 'background:rgba(77,163,232,.2);color:#9cd0f5;border:1px solid rgba(77,163,232,.45);' : 'border:1px solid rgba(255,255,255,.14);color:#8fa3b6;');
}

let pollTimer: ReturnType<typeof setInterval> | undefined;
let clockTimer: ReturnType<typeof setInterval> | undefined;
onMounted(() => {
  load();
  pollTimer = setInterval(load, 5000);
  clockTimer = setInterval(() => { nowMs.value = Date.now(); }, 1000);
});
onUnmounted(() => {
  clearInterval(pollTimer);
  clearInterval(clockTimer);
  clearTimeout(undoTimer);
  clearTimeout(flashTimer);
});
</script>
