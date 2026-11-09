<template>
  <div>
    <div style="padding:20px 24px 16px;background:#ffffff;border-bottom:1px solid #dfe6ee">
      <div style="display:flex;align-items:center;gap:7px;font-size:11.5px;color:#7d8b9c;margin-bottom:8px"><RouterLink to="/sell" style="color:#7d8b9c;text-decoration:none">Sell</RouterLink><span style="color:#c2cedb">/</span><span style="color:#0f1c2b;font-weight:500">Registers</span></div>
      <h2 style="margin:0 0 3px;font-size:22px;font-weight:700;letter-spacing:-0.018em">Cash registers</h2>
      <div style="font-size:12.5px;color:#5d6b7d">Each cashier's shift: the cash counted to open, what should be in the drawer, and the count at close.</div>
    </div>
    <div style="padding:20px 24px 32px">
      <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:16px">
        <div v-for="k in kpis" :key="k.label" style="background:#ffffff;border:1px solid #dfe6ee;border-radius:12px;padding:14px 16px">
          <div style="font-size:11.5px;color:#7d8b9c;margin-bottom:7px">{{ k.label }}</div>
          <div :style="'font-family:\'IBM Plex Mono\',monospace;font-size:22px;font-weight:600;line-height:1;' + k.color">{{ k.value }}</div>
          <div style="font-size:11.5px;color:#7d8b9c;margin-top:5px">{{ k.sub }}</div>
        </div>
      </div>

      <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-bottom:14px">
        <div style="display:flex;border:1px solid #dfe6ee;border-radius:8px;overflow:hidden;background:#ffffff">
          <div v-for="t in STATUS_TABS" :key="t.key" @click="status = t.key; load()" :style="segStyle(status === t.key)">{{ t.name }}</div>
        </div>
        <select v-if="branchList.length" v-model.number="branchId" @change="load" :style="filterStyle">
          <option :value="0">All locations</option>
          <option v-for="b in branchList" :key="b.id" :value="b.id">{{ b.name }}</option>
        </select>
        <input v-model="from" @change="load" type="date" title="From date" :style="filterStyle" />
        <input v-model="to" @change="load" type="date" title="To date" :style="filterStyle" />
      </div>

      <div v-if="error" style="background:#fdeaea;color:#b02020;border-radius:10px;padding:10px 14px;font-size:12.5px;margin-bottom:12px">{{ error }}</div>

      <div style="background:#ffffff;border:1px solid #dfe6ee;border-radius:12px;overflow:hidden">
        <div :style="grid + 'padding:10px 16px;background:#f6f9fc;border-bottom:1px solid #dfe6ee;font-family:\'IBM Plex Mono\',monospace;font-size:9.5px;letter-spacing:0.07em;text-transform:uppercase;color:#5d6b7d'">
          <div>Opened</div><div>Cashier</div><div style="text-align:right">Opening</div><div style="text-align:right">Cash sales</div>
          <div style="text-align:right">Expected</div><div style="text-align:right">Counted</div><div style="text-align:right">Over / short</div><div>Status</div>
        </div>
        <div v-if="loading" style="padding:30px;text-align:center;font-size:13px;color:#7d8b9c">Loading…</div>
        <div v-else-if="!rows.length" style="padding:30px;text-align:center;font-size:13px;color:#7d8b9c">No registers here yet.</div>
        <template v-for="r in rows" :key="r.id">
          <div @click="toggle(r)" :style="grid + 'padding:10px 16px;border-bottom:1px solid #f0f4f8;cursor:pointer;font-size:12.5px'" v-pseudo:hover="'background:#f9fbfd'">
            <div style="font-family:'IBM Plex Mono',monospace;font-size:12px">{{ dateTime(r.opened_at) }}</div>
            <div style="min-width:0">
              <div style="font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">{{ r.user_name }}</div>
              <div style="font-size:11px;color:#8b98a8">{{ r.branch }}</div>
            </div>
            <div :style="mono">{{ money(r.opening_cash) }}</div>
            <div :style="mono">{{ money(r.cash_sales) }}</div>
            <div :style="mono">{{ money(r.expected_cash) }}</div>
            <div :style="mono">{{ r.closed_at && !r.auto_closed ? money(r.counted_cash) : '—' }}</div>
            <div :style="mono + diffColor(r)">{{ diffText(r) }}</div>
            <div>
              <span v-if="!r.closed_at" style="background:#e7f6ee;color:#0f6b43;border-radius:5px;padding:2px 8px;font-size:11px;font-weight:600">Open</span>
              <span v-else-if="r.auto_closed" style="background:#fdf1dd;color:#8a5300;border-radius:5px;padding:2px 8px;font-size:11px;font-weight:600" title="Not closed on its day: closed when the next day started, without a count">Not counted</span>
              <span v-else style="background:#eef2f6;color:#4a5866;border-radius:5px;padding:2px 8px;font-size:11px;font-weight:600" :title="'Closed by ' + r.closed_by">Closed {{ dateTime(r.closed_at).slice(-5) }}</span>
            </div>
          </div>
          <!-- The shift's cash moves -->
          <div v-if="openId === r.id" style="background:#f9fbfd;border-bottom:1px solid #dfe6ee;padding:12px 16px 14px 32px">
            <div v-if="r.opening_note || r.close_note" style="font-size:12px;color:#5d6b7d;margin-bottom:8px">
              <div v-if="r.opening_note">Opening note: {{ r.opening_note }}</div>
              <div v-if="r.close_note">Closing note: {{ r.close_note }}</div>
            </div>
            <div style="display:flex;gap:18px;font-size:12px;color:#4a5866;margin-bottom:8px;flex-wrap:wrap">
              <span>Received {{ money(r.received) }}</span><span>Tips {{ money(r.tips) }}</span><span>Refunds {{ money(r.refunds) }}</span>
              <span>Cash in {{ money(r.cash_in) }}</span><span>Cash out {{ money(r.cash_out) }}</span>
              <span v-if="r.closed_at">Left in drawer {{ money(r.kept_cash) }}</span>
            </div>
            <div v-if="!moves.length" style="font-size:12px;color:#8b98a8">No cash moves.</div>
            <div v-for="m in moves" :key="m.id" style="display:grid;grid-template-columns:70px 130px 1fr 100px;gap:10px;padding:4px 0;font-size:12px">
              <div style="font-family:'IBM Plex Mono',monospace;color:#8b98a8">{{ timeOf(m.created_at) }}</div>
              <div>{{ TYPE_NAMES[m.type] }}</div>
              <div style="color:#5d6b7d;min-width:0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">{{ [m.ref, m.note, m.user_name].filter(Boolean).join(' · ') }}</div>
              <div :style="mono + (m.amount < 0 ? 'color:#b02020;' : 'color:#0f6b43;')">{{ m.amount < 0 ? '−' : '+' }}{{ money(Math.abs(m.amount)) }}</div>
            </div>
          </div>
        </template>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
// Sell > Registers. API: GET /api/cash-registers?status=&branch_id=&from=&to=, GET /api/cash-registers/{id}
import { computed, onMounted, ref } from 'vue';
import { api } from '../../api/http.js';
import { money, dateTime } from '../../api/sales.js';

const STATUS_TABS = [{ key: '', name: 'All' }, { key: 'open', name: 'Open' }, { key: 'closed', name: 'Closed' }];
const TYPE_NAMES: Record<string, string> = { sale: 'Cash sale', received: 'Payment received', tip: 'Cash tip', refund: 'Refund', cash_in: 'Cash in', cash_out: 'Cash out' };
const grid = 'display:grid;grid-template-columns:1fr 1.4fr 0.8fr 0.8fr 0.8fr 0.8fr 0.9fr 0.9fr;gap:12px;align-items:center;';
const mono = "font-family:'IBM Plex Mono',monospace;text-align:right;";
const filterStyle = "height:34px;border:1px solid #dfe6ee;border-radius:8px;background:#ffffff;padding:0 8px;font-family:'Public Sans',sans-serif;font-size:12.5px;color:#4a5866";

const status = ref('');
const branchId = ref(0);
const from = ref('');
const to = ref('');
const rows = ref<any[]>([]);
const branchList = ref<any[]>([]);
const moves = ref<any[]>([]);
const openId = ref<number | null>(null);
const loading = ref(true);
const error = ref('');

const kpis = computed(() => {
  const closed = rows.value.filter(r => r.closed_at && !r.auto_closed);
  const short = closed.filter(r => r.difference < -0.005);
  const net = closed.reduce((a, r) => a + (r.difference || 0), 0);
  return [
    { label: 'Open now', value: String(rows.value.filter(r => !r.closed_at).length), sub: 'registers in use', color: '' },
    { label: 'Cash sales', value: money(rows.value.reduce((a, r) => a + r.cash_sales, 0)), sub: rows.value.length + ' shifts shown', color: '' },
    { label: 'Short shifts', value: String(short.length), sub: 'of ' + closed.length + ' closed', color: short.length ? 'color:#b02020;' : 'color:#0f6b43;' },
    { label: 'Over / short', value: (net < -0.005 ? '−' : '') + money(Math.abs(net)), sub: 'all closed shifts shown', color: Math.abs(net) < 0.005 ? 'color:#0f6b43;' : 'color:#b02020;' },
  ];
});

// The time of a cash move, in the shop's own time.
function timeOf(iso: string) {
  const d = new Date(iso);
  return String(d.getHours()).padStart(2, '0') + ':' + String(d.getMinutes()).padStart(2, '0');
}

function segStyle(on: boolean) {
  return 'height:34px;padding:0 14px;display:flex;align-items:center;font-size:12.5px;font-weight:600;cursor:pointer;user-select:none;'
    + (on ? 'background:var(--c-primary);color:#ffffff;' : 'color:#5d6b7d;');
}

function diffText(r: any) {
  if (!r.closed_at) return '—';
  if (r.auto_closed) return 'Not counted';
  if (Math.abs(r.difference) < 0.005) return 'Balanced';
  return (r.difference > 0 ? '+' : '−') + money(Math.abs(r.difference));
}

function diffColor(r: any) {
  if (!r.closed_at) return 'color:#9aa7b6;';
  if (r.auto_closed) return 'color:#8a5300;';
  return Math.abs(r.difference) < 0.005 ? 'color:#0f6b43;' : 'color:#b02020;font-weight:700;';
}

async function load() {
  loading.value = true;
  error.value = '';
  const params = new URLSearchParams();
  if (status.value) params.set('status', status.value);
  if (branchId.value) params.set('branch_id', String(branchId.value));
  if (from.value) params.set('from', from.value);
  if (to.value) params.set('to', to.value);
  try {
    const res = await api.get('/cash-registers?' + params.toString());
    rows.value = res.data;
    branchList.value = res.branches;
  } catch (e: any) {
    error.value = e.message || 'Could not load the registers.';
  }
  loading.value = false;
}

async function toggle(r: any) {
  if (openId.value === r.id) { openId.value = null; return; }
  openId.value = r.id;
  moves.value = [];
  try {
    const res = await api.get('/cash-registers/' + r.id);
    moves.value = res.moves;
  } catch (e: any) {
    error.value = e.message || 'Could not load the cash moves.';
  }
}

onMounted(load);
</script>
