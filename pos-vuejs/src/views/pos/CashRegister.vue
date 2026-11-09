<template>
  <!-- Top bar chip: what should be in the drawer now -->
  <div v-if="ready && register" @click="openPanel" title="Cash register" :style="chipStyle" v-pseudo:hover="'background:rgba(255,255,255,.16)'">
    <span style="width:7px;height:7px;border-radius:50%;background:#2f9e63"></span>
    Drawer <span style="font-family:'IBM Plex Mono',monospace">{{ money(register.expected_cash) }}</span>
  </div>
  <div v-else-if="ready && loaded && !required" @click="showOpen = true" :style="chipStyle" v-pseudo:hover="'background:rgba(255,255,255,.16)'">Open register</div>

  <!-- Open register: count the cash in the drawer -->
  <div v-if="ready && loaded && !register && !closeOpen && (required || showOpen)" ref="root" @keydown.stop style="position:fixed;inset:0;z-index:50;display:grid;place-items:center;background:rgba(9,22,35,.62)">
    <div style="width:520px;max-height:92vh;overflow:auto;background:#ffffff;border-radius:18px;padding:22px;box-shadow:0 30px 80px -24px rgba(9,22,35,.65);color:#0f1c2b">
      <div style="display:flex;align-items:flex-start;gap:12px">
        <div>
          <div style="font-size:18px;font-weight:700">Open the cash register</div>
          <div style="font-size:12.5px;color:#5d6b7d;margin-top:3px">A new day: count the cash in the drawer before your first sale.</div>
        </div>
        <div v-if="!required" @click="showOpen = false" :style="closeBtn">×</div>
      </div>

      <div v-if="lastClose && lastClose.auto_closed" style="margin-top:14px;background:#fffaf0;border:1px solid #f1dcb0;border-radius:11px;padding:10px 14px;font-size:12.5px;color:#8a5300;line-height:1.55">
        {{ lastClose.user_name }}'s register for {{ dayText(lastClose.shop_day) }} was not closed, so it was closed when the new day started, without a count.
        It should have <b style="font-family:'IBM Plex Mono',monospace">{{ money(lastClose.kept_cash) }}</b> in the drawer. Count carefully.
      </div>
      <div v-else-if="lastClose" style="margin-top:14px;background:#f4f8fd;border-radius:11px;padding:10px 14px;font-size:12.5px;color:#34485c;line-height:1.55">
        The last close ({{ lastClose.user_name }}, {{ dateTime(lastClose.closed_at) }}) left
        <b style="font-family:'IBM Plex Mono',monospace">{{ money(lastClose.kept_cash) }}</b> in the drawer.
      </div>

      <div style="margin-top:14px;font-size:12px;font-weight:600;color:#5d6b7d">Cash in the drawer now</div>
      <div style="display:flex;gap:8px;margin-top:6px">
        <div style="position:relative;flex:1">
          <span style="position:absolute;left:14px;top:50%;transform:translateY(-50%);color:#5d6b7d;font-weight:700">$</span>
          <input v-model="openAmount" type="number" min="0" step="0.05" placeholder="0.00" autofocus @keydown.enter="openRegister" :style="bigInput" />
        </div>
        <div @click="countOpen = !countOpen" :style="secondaryBtn">{{ countOpen ? 'Hide count' : 'Count notes & coins' }}</div>
      </div>
      <div v-if="countOpen" style="margin-top:10px">
        <DenomRow v-for="d in DENOMS" :key="d" :d="d" :n="openCount[d]" @set="v => { openCount[d] = v; openAmount = String(countTotal(openCount)); }" />
        <div style="text-align:right;font-size:12.5px;color:#5d6b7d;margin-top:6px">Counted <b style="font-family:'IBM Plex Mono',monospace;color:#0f1c2b">{{ money(countTotal(openCount)) }}</b></div>
      </div>

      <div v-if="openDiffers" style="margin-top:12px">
        <div style="font-size:12.5px;color:#8a5300;background:#fffaf0;border:1px solid #f1dcb0;border-radius:10px;padding:9px 12px">
          That is {{ money(Math.abs(Number(openAmount) - lastClose.kept_cash)) }} {{ Number(openAmount) > lastClose.kept_cash ? 'more' : 'less' }} than the last close left. Say why:
        </div>
        <input v-model="openNote" placeholder="e.g. owner added change from the safe" :style="noteInput" />
      </div>

      <div v-if="error" :style="errorBox">{{ error }}</div>
      <div style="display:flex;gap:9px;margin-top:16px;align-items:center">
        <RouterLink v-if="required" to="/sell" style="font-size:12.5px;color:#5d6b7d;text-decoration:underline">Back to Sell</RouterLink>
        <div @click="openRegister" :style="primaryBtn + 'margin-left:auto'">{{ saving ? 'Opening…' : 'Start selling' }}</div>
      </div>
    </div>
  </div>

  <!-- The drawer: totals, cash in/out, close -->
  <div v-if="panel && register" ref="root" @keydown.stop style="position:fixed;inset:0;z-index:50;display:grid;place-items:center;background:rgba(9,22,35,.55)">
    <div style="width:760px;max-height:92vh;overflow:auto;background:#ffffff;border-radius:18px;padding:22px;box-shadow:0 30px 80px -24px rgba(9,22,35,.65);color:#0f1c2b">
      <div style="display:flex;align-items:flex-start;gap:12px;margin-bottom:14px">
        <div>
          <div style="font-size:18px;font-weight:700">Cash register · today</div>
          <div style="font-size:12.5px;color:#5d6b7d;margin-top:3px">{{ register.user_name }} · {{ register.branch }} · opened {{ dateTime(register.opened_at) }}</div>
        </div>
        <div @click="panel = false" :style="closeBtn">×</div>
      </div>

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;align-items:start">
        <!-- Totals -->
        <div style="border:1px solid #dfe6ee;border-radius:12px;overflow:hidden">
          <div v-for="r in rows" :key="r.k" style="display:flex;padding:8px 14px;border-bottom:1px solid #f0f4f8;font-size:12.5px">
            <div style="flex:1;color:#4a5866">{{ r.k }}</div>
            <div :style="'font-family:\'IBM Plex Mono\',monospace;' + (r.v < 0 ? 'color:#b02020' : '')">{{ r.v < 0 ? '−' : '' }}{{ money(Math.abs(r.v)) }}</div>
          </div>
          <div style="display:flex;padding:11px 14px;background:#0f1c2b;color:#ffffff;font-size:14px;font-weight:700">
            <div style="flex:1">Should be in the drawer</div>
            <div style="font-family:'IBM Plex Mono',monospace">{{ money(register.expected_cash) }}</div>
          </div>
        </div>

        <!-- Cash in / out -->
        <div>
          <div style="display:flex;gap:4px;background:#eef2f6;border-radius:10px;padding:4px">
            <div @click="moveType = 'cash_in'" :style="tabStyle(moveType === 'cash_in')">Cash in</div>
            <div @click="moveType = 'cash_out'" :style="tabStyle(moveType === 'cash_out')">Cash out</div>
          </div>
          <div style="position:relative;margin-top:10px">
            <span style="position:absolute;left:14px;top:50%;transform:translateY(-50%);color:#5d6b7d;font-weight:700">$</span>
            <input v-model="moveAmount" type="number" min="0" step="0.05" placeholder="0.00" :style="bigInput" />
          </div>
          <input v-model="moveNote" :placeholder="moveType === 'cash_in' ? 'Why? e.g. more change from the safe' : 'Why? e.g. paid the delivery driver'" @keydown.enter="saveMove" :style="noteInput" />
          <div @click="saveMove" :style="primaryBtn + 'margin-top:10px;width:100%'">{{ moveType === 'cash_in' ? 'Add cash to the drawer' : 'Take cash out' }}</div>

          <div style="font-size:12px;font-weight:600;color:#5d6b7d;margin:16px 0 6px">Latest cash moves</div>
          <div v-if="!moves.length" style="font-size:12.5px;color:#8b98a8">No cash moves yet.</div>
          <div style="max-height:190px;overflow:auto">
            <div v-for="m in moves" :key="m.id" style="display:flex;gap:8px;padding:6px 0;border-bottom:1px solid #f0f4f8;font-size:12px">
              <div style="width:42px;color:#8b98a8;font-family:'IBM Plex Mono',monospace">{{ timeOf(m.created_at) }}</div>
              <div style="flex:1;min-width:0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">{{ TYPE_NAMES[m.type] }}{{ m.ref ? ' · ' + m.ref : '' }}{{ m.note ? ' · ' + m.note : '' }}</div>
              <div :style="'font-family:\'IBM Plex Mono\',monospace;' + (m.amount < 0 ? 'color:#b02020' : 'color:#0f6b43')">{{ m.amount < 0 ? '−' : '+' }}{{ money(Math.abs(m.amount)) }}</div>
            </div>
          </div>
        </div>

      </div>

      <div v-if="error" :style="errorBox">{{ error }}</div>
      <div style="display:flex;gap:9px;margin-top:16px">
        <div @click="startClose" :style="dangerBtn + 'margin-left:auto'">Close register…</div>
      </div>
    </div>
  </div>

  <!-- Count the drawer and close (same window as on the dashboard) -->
  <CloseRegister v-if="closeOpen && register" :register="register" @cancel="closeOpen = false; panel = true" @done="onClosed" />
</template>

<script setup lang="ts">
import { computed, onMounted, onUnmounted, reactive, ref, watch } from 'vue';
import { api } from '../../api/http.js';
import { money } from '../../api/products.js';
import DenomRow from './DenomRow.vue';
import CloseRegister from './CloseRegister.vue';

// A register is for one day. Opens it with the cash counted in the drawer, keeps what should be in the drawer
// (every cash sale, tip, refund, cash in and out is added by the server) and closes it with a count.
const props = defineProps<{ branchId: number | string; required: boolean; ready: boolean; saleRef?: string }>();

// Australian notes and coins, for counting the drawer.
const DENOMS = [100, 50, 20, 10, 5, 2, 1, 0.5, 0.2, 0.1, 0.05];
const TYPE_NAMES: Record<string, string> = { sale: 'Cash sale', received: 'Payment received', tip: 'Cash tip', refund: 'Refund', cash_in: 'Cash in', cash_out: 'Cash out' };

const loaded = ref(false);
const register = ref<any>(null);
const moves = ref<any[]>([]);
const lastClose = ref<any>(null);
const showOpen = ref(false);
const panel = ref(false);
const closeOpen = ref(false);
const saving = ref(false);
const error = ref('');
const root = ref<HTMLElement | null>(null);

const openAmount = ref('');
const openNote = ref('');
const countOpen = ref(false);
const openCount = reactive<Record<number, number>>({});

const moveType = ref('cash_in');
const moveAmount = ref('');
const moveNote = ref('');


// The time of a cash move, in the shop's own time.
function timeOf(iso: string) {
  const d = new Date(iso);
  return String(d.getHours()).padStart(2, '0') + ':' + String(d.getMinutes()).padStart(2, '0');
}

function countTotal(c: Record<number, number>) {
  return Math.round(DENOMS.reduce((a, d) => a + (c[d] || 0) * d, 0) * 100) / 100;
}

function clearCount(c: Record<number, number>) {
  DENOMS.forEach(d => { c[d] = 0; });
}

// "Fri 7/11" for a shop day (YYYY-MM-DD).
function dayText(day: string) {
  if (!day) return 'an earlier day';
  const d = new Date(day + 'T12:00:00');
  return d.toLocaleDateString('en-AU', { weekday: 'short', day: 'numeric', month: 'numeric' });
}

function dateTime(iso: string) {
  if (!iso) return '';
  const d = new Date(iso);
  const p = (n: number) => String(n).padStart(2, '0');
  return p(d.getDate()) + '/' + p(d.getMonth() + 1) + ' ' + p(d.getHours()) + ':' + p(d.getMinutes());
}

async function load() {
  if (!props.ready || !props.branchId) return;
  try {
    const res = await api.get('/cash-register?branch_id=' + props.branchId);
    register.value = res.register;
    moves.value = res.moves;
    lastClose.value = res.last_close;
    if (!res.register) {
      panel.value = false;
      // Start the count from what the last close left in the drawer.
      openAmount.value = res.last_close ? String(res.suggested_opening) : '';
      openNote.value = '';
      clearCount(openCount);
    }
    loaded.value = true;
  } catch (e: any) {
    error.value = e.message || 'Could not load the cash register.';
    loaded.value = true;
  }
}

const openDiffers = computed(() => !!lastClose.value && lastClose.value.kept_cash !== null && openAmount.value !== ''
  && Math.abs(Number(openAmount.value) - lastClose.value.kept_cash) > 0.005);

async function openRegister() {
  if (saving.value) return;
  if (openAmount.value === '' || Number(openAmount.value) < 0) { error.value = 'Type the cash in the drawer (0 if it is empty).'; return; }
  if (openDiffers.value && !openNote.value.trim()) { error.value = 'Say why the count is different from what the last shift left.'; return; }
  saving.value = true;
  error.value = '';
  try {
    const res = await api.post('/cash-register/open', { branch_id: props.branchId, opening_cash: Number(openAmount.value), note: openNote.value.trim() || null });
    register.value = res.register;
    moves.value = res.moves;
    showOpen.value = false;
  } catch (e: any) {
    error.value = e.message || 'Could not open the register.';
  }
  saving.value = false;
}

function openPanel() {
  error.value = '';
  panel.value = true;
  load();
}

async function saveMove() {
  if (saving.value) return;
  const amount = Number(moveAmount.value);
  if (!(amount > 0)) { error.value = 'Type the amount.'; return; }
  if (!moveNote.value.trim()) { error.value = 'Say what the cash is for.'; return; }
  saving.value = true;
  error.value = '';
  try {
    const res = await api.post('/cash-register/' + register.value.id + '/move', { type: moveType.value, amount, note: moveNote.value.trim() });
    register.value = res.register;
    moves.value = res.moves;
    moveAmount.value = '';
    moveNote.value = '';
  } catch (e: any) {
    error.value = e.message || 'Could not save.';
  }
  saving.value = false;
}

function startClose() {
  error.value = '';
  panel.value = false;
  closeOpen.value = true;
}

// Closed: the next opening (for the next day, or the next cashier) starts from what was left.
function onClosed() {
  closeOpen.value = false;
  register.value = null;
  load();
}

const rows = computed(() => {
  const r = register.value;
  if (!r) return [];
  return [
    { k: 'Opening cash', v: r.opening_cash },
    { k: 'Cash sales (' + r.sales_count + ')', v: r.cash_sales },
    { k: 'Payments received', v: r.received },
    { k: 'Cash tips', v: r.tips },
    { k: 'Refunds', v: r.refunds },
    { k: 'Cash in', v: r.cash_in },
    { k: 'Cash out', v: r.cash_out },
  ].filter(x => x.k === 'Opening cash' || x.k.startsWith('Cash sales') || Math.abs(x.v) >= 0.005);
});

const chipStyle = 'height:34px;padding:0 12px;border-radius:9px;background:rgba(255,255,255,.08);color:#dbe5ee;display:flex;align-items:center;gap:8px;font-size:12.5px;font-weight:600;cursor:pointer';
const closeBtn = 'margin-left:auto;width:32px;height:32px;border-radius:8px;border:1px solid #dfe6ee;display:grid;place-items:center;cursor:pointer;color:#5d6b7d;font-size:15px;flex:none';
const bigInput = "width:100%;height:52px;border:1px solid #dfe6ee;border-radius:11px;padding:0 14px 0 30px;font-size:20px;font-weight:700;font-family:'IBM Plex Mono',monospace;outline:none";
const noteInput = 'width:100%;height:38px;border:1px solid #dfe6ee;border-radius:9px;padding:0 12px;font-family:inherit;font-size:12.5px;margin-top:8px;outline:none';
const primaryBtn = 'height:44px;padding:0 20px;border-radius:11px;background:var(--c-primary);color:#ffffff;display:grid;place-items:center;font-size:14px;font-weight:700;cursor:pointer;';
const secondaryBtn = 'height:44px;padding:0 14px;border-radius:11px;border:1px solid #dfe6ee;color:#34485c;display:grid;place-items:center;font-size:12.5px;font-weight:600;cursor:pointer;white-space:nowrap;';
const dangerBtn = 'height:44px;padding:0 20px;border-radius:11px;background:#b02020;color:#ffffff;display:grid;place-items:center;font-size:14px;font-weight:700;cursor:pointer;';
const errorBox = 'margin-top:12px;background:#fdeaea;color:#b02020;border-radius:10px;padding:9px 12px;font-size:12.5px';

function tabStyle(on: boolean) {
  return 'flex:1;height:34px;border-radius:8px;display:grid;place-items:center;font-size:13px;font-weight:700;cursor:pointer;'
    + (on ? 'background:var(--c-primary);color:#ffffff;' : 'color:#4a5866;');
}

// While a register window is open, the POS keyboard shortcuts (F9, Esc, barcode typing) must not fire.
const blocking = computed(() => (props.ready && loaded.value && !register.value && !closeOpen.value && (props.required || showOpen.value)) || panel.value);
function onKeyFirst(e: KeyboardEvent) {
  if (!blocking.value) return;
  if (e.key === 'Escape' && panel.value) { panel.value = false; e.stopImmediatePropagation(); return; }
  // Keys typed inside the window reach its inputs; @keydown.stop keeps them from the POS.
  if (root.value && e.target instanceof Node && root.value.contains(e.target)) return;
  e.stopImmediatePropagation();
}
// When a new day starts while the POS is open, the server closes the old register: load again to ask for the opening count.
let day = new Date().toDateString();
let dayTimer: ReturnType<typeof setInterval> | undefined;
function checkDay() {
  const now = new Date().toDateString();
  if (now !== day) { day = now; load(); }
}

onMounted(() => { window.addEventListener('keydown', onKeyFirst, true); load(); dayTimer = setInterval(checkDay, 60000); });
onUnmounted(() => { window.removeEventListener('keydown', onKeyFirst, true); clearInterval(dayTimer); });

watch(() => [props.branchId, props.ready], () => { loaded.value = false; register.value = null; load(); });
// After each sale the drawer total changes.
watch(() => props.saleRef, () => { if (props.saleRef) load(); });
</script>
