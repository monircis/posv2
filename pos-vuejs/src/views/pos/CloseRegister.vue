<template>
  <!-- Close a register: count the drawer, see over/short, leave a float, then the shift report. -->
  <div ref="root" @keydown.stop style="position:fixed;inset:0;z-index:52;display:grid;place-items:center;background:rgba(9,22,35,.6)">
    <div v-if="!report" style="width:760px;max-width:calc(100vw - 32px);max-height:92vh;overflow:auto;background:#ffffff;border-radius:18px;padding:22px;box-shadow:0 30px 80px -24px rgba(9,22,35,.65);color:#0f1c2b">
      <div style="display:flex;align-items:flex-start;gap:12px;margin-bottom:14px">
        <div>
          <div style="font-size:18px;font-weight:700">Close the register</div>
          <div style="font-size:12.5px;color:#5d6b7d;margin-top:3px">{{ register.user_name }} · {{ register.branch }} · opened {{ dateTime(register.opened_at) }}</div>
        </div>
        <div @click="emit('cancel')" :style="closeBtn">×</div>
      </div>

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;align-items:start">
        <!-- What should be in the drawer -->
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

        <!-- The count -->
        <div>
          <div style="font-size:12px;font-weight:600;color:#5d6b7d">Count the cash in the drawer</div>
          <div style="margin-top:6px">
            <DenomRow v-for="d in DENOMS" :key="d" :d="d" :n="count[d]" @set="v => setCount(d, v)" />
          </div>
          <div style="display:flex;align-items:center;gap:8px;margin-top:8px">
            <div style="font-size:12.5px;color:#5d6b7d;flex:1">Counted</div>
            <input v-model="counted" type="number" min="0" step="0.05" placeholder="0.00" :style="numInput + 'font-weight:700;'" />
          </div>
          <div v-if="counted !== ''" :style="diffBox">
            {{ Math.abs(difference) < 0.005 ? 'Balanced. The drawer matches.' : (difference > 0 ? 'Over by ' : 'Short by ') + money(Math.abs(difference)) }}
          </div>
          <div style="display:flex;align-items:center;gap:8px;margin-top:10px">
            <div style="font-size:12.5px;color:#5d6b7d;flex:1">Leave in the drawer for the next opening</div>
            <input v-model="kept" type="number" min="0" step="0.05" :style="numInput" />
          </div>
          <div v-if="counted !== ''" style="font-size:12px;color:#5d6b7d;margin-top:6px;text-align:right">To bank: <b style="font-family:'IBM Plex Mono',monospace;color:#0f1c2b">{{ money(Math.max(0, Number(counted) - Number(kept || 0))) }}</b></div>
          <input v-model="note" :placeholder="Math.abs(difference) >= 0.005 && counted !== '' ? 'Why is it over or short? (needed)' : 'Note (optional)'" :style="noteInput" />
        </div>
      </div>

      <div v-if="error" :style="errorBox">{{ error }}</div>
      <div style="display:flex;gap:9px;margin-top:16px">
        <div @click="emit('cancel')" :style="secondaryBtn + 'margin-left:auto'">Cancel</div>
        <div @click="close" :style="dangerBtn">{{ saving ? 'Closing…' : 'Close register' }}</div>
      </div>
    </div>

    <!-- After closing: the shift report -->
    <div v-else style="width:420px;max-width:calc(100vw - 32px);background:#ffffff;border-radius:18px;padding:22px;color:#0f1c2b">
      <div style="font-size:18px;font-weight:700">Register closed</div>
      <div style="font-size:12.5px;color:#5d6b7d;margin-top:3px">{{ report.user_name }} · {{ report.branch }} · {{ dateTime(report.opened_at) }} to {{ dateTime(report.closed_at) }}</div>
      <div style="border:1px solid #dfe6ee;border-radius:12px;margin-top:14px;overflow:hidden">
        <div v-for="r in reportRows" :key="r[0]" style="display:flex;padding:8px 14px;border-bottom:1px solid #f0f4f8;font-size:12.5px">
          <div style="flex:1;color:#4a5866">{{ r[0] }}</div>
          <div :style="'font-family:\'IBM Plex Mono\',monospace;font-weight:600;' + (r[2] || '')">{{ r[1] }}</div>
        </div>
      </div>
      <div style="font-size:12px;color:#5d6b7d;margin-top:10px">The next opening starts from {{ money(report.kept_cash) }}.</div>
      <div style="display:flex;margin-top:16px">
        <div @click="emit('done', report)" :style="primaryBtn + 'margin-left:auto'">Done</div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
// Closing a cash register, used on the POS (drawer panel) and on the dashboard.
// POST /api/cash-register/{id}/close. Emits `cancel`, or `done` with the closed register after the report.
import { computed, onMounted, onUnmounted, reactive, ref } from 'vue';
import { api } from '../../api/http.js';
import { money } from '../../api/products.js';
import DenomRow from './DenomRow.vue';

const props = defineProps<{ register: any }>();
const emit = defineEmits<{ (e: 'cancel'): void; (e: 'done', register: any): void }>();

// Australian notes and coins, for counting the drawer.
const DENOMS = [100, 50, 20, 10, 5, 2, 1, 0.5, 0.2, 0.1, 0.05];

const count = reactive<Record<number, number>>({});
const counted = ref('');
// Leave the same float the day started with, so the next opening starts the same.
const kept = ref(String(props.register.opening_cash));
const note = ref('');
const saving = ref(false);
const error = ref('');
const report = ref<any>(null);
const root = ref<HTMLElement | null>(null);

function setCount(d: number, n: number) {
  count[d] = n;
  counted.value = String(Math.round(DENOMS.reduce((a, x) => a + (count[x] || 0) * x, 0) * 100) / 100);
}

function dateTime(iso: string) {
  if (!iso) return '';
  const d = new Date(iso);
  const p = (n: number) => String(n).padStart(2, '0');
  return p(d.getDate()) + '/' + p(d.getMonth() + 1) + ' ' + p(d.getHours()) + ':' + p(d.getMinutes());
}

const difference = computed(() => Math.round((Number(counted.value || 0) - props.register.expected_cash) * 100) / 100);

const rows = computed(() => {
  const r = props.register;
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

const reportRows = computed(() => {
  const r = report.value;
  if (!r) return [];
  const d = r.difference || 0;
  return [
    ['Opening cash', money(r.opening_cash)],
    ['Cash sales (' + r.sales_count + ')', money(r.cash_sales)],
    ['Expected in drawer', money(r.expected_cash)],
    ['Counted', money(r.counted_cash)],
    [Math.abs(d) < 0.005 ? 'Balanced' : d > 0 ? 'Over' : 'Short', money(Math.abs(d)), Math.abs(d) < 0.005 ? 'color:#0f6b43' : 'color:#b02020'],
    ['Left in drawer', money(r.kept_cash)],
    ['To bank', money(Math.max(0, r.counted_cash - r.kept_cash))],
  ];
});

async function close() {
  if (saving.value) return;
  if (counted.value === '') { error.value = 'Count the cash in the drawer.'; return; }
  if (Math.abs(difference.value) >= 0.005 && !note.value.trim()) { error.value = 'The drawer is ' + (difference.value > 0 ? 'over' : 'short') + '. Add a note to close.'; return; }
  if (Number(kept.value || 0) > Number(counted.value)) { error.value = 'You cannot leave more in the drawer than you counted.'; return; }
  saving.value = true;
  error.value = '';
  try {
    const res = await api.post('/cash-register/' + props.register.id + '/close', { counted_cash: Number(counted.value), kept_cash: Number(kept.value || 0), note: note.value.trim() || null });
    report.value = res.register;
  } catch (e: any) {
    error.value = e.message || 'Could not close the register.';
  }
  saving.value = false;
}

// On the POS, keys typed here must not reach its shortcuts (F9, barcode typing); Esc cancels.
function onKeyFirst(e: KeyboardEvent) {
  if (e.key === 'Escape' && !report.value) { e.stopImmediatePropagation(); emit('cancel'); return; }
  if (root.value && e.target instanceof Node && root.value.contains(e.target)) return;
  e.stopImmediatePropagation();
}
onMounted(() => window.addEventListener('keydown', onKeyFirst, true));
onUnmounted(() => window.removeEventListener('keydown', onKeyFirst, true));

const diffBox = computed(() => 'margin-top:8px;border-radius:10px;padding:9px 12px;font-size:13px;font-weight:700;'
  + (Math.abs(difference.value) < 0.005 ? 'background:#e7f6ee;color:#0f6b43;' : 'background:#fdeaea;color:#b02020;'));
const closeBtn = 'margin-left:auto;width:32px;height:32px;border-radius:8px;border:1px solid #dfe6ee;display:grid;place-items:center;cursor:pointer;color:#5d6b7d;font-size:15px;flex:none';
const numInput = "width:130px;height:38px;border:1px solid #dfe6ee;border-radius:9px;padding:0 10px;font-family:'IBM Plex Mono',monospace;font-size:15px;text-align:right;outline:none;";
const noteInput = 'width:100%;height:38px;border:1px solid #dfe6ee;border-radius:9px;padding:0 12px;font-family:inherit;font-size:12.5px;margin-top:8px;outline:none';
const primaryBtn = 'height:44px;padding:0 20px;border-radius:11px;background:var(--c-primary);color:#ffffff;display:grid;place-items:center;font-size:14px;font-weight:700;cursor:pointer;';
const secondaryBtn = 'height:44px;padding:0 14px;border-radius:11px;border:1px solid #dfe6ee;color:#34485c;display:grid;place-items:center;font-size:12.5px;font-weight:600;cursor:pointer;white-space:nowrap;';
const dangerBtn = 'height:44px;padding:0 20px;border-radius:11px;background:#b02020;color:#ffffff;display:grid;place-items:center;font-size:14px;font-weight:700;cursor:pointer;';
const errorBox = 'margin-top:12px;background:#fdeaea;color:#b02020;border-radius:10px;padding:9px 12px;font-size:12.5px';
</script>
