<template>
  <div>
    <div v-if="!t" style="padding:24px;font-size:13px;color:#5d6b7d">
      {{ loading ? 'Loading…' : 'Transfer not found.' }}
      <RouterLink v-if="!loading" :to="{ name: 'transfers' }" style="margin-left:6px">Back to transfers</RouterLink>
    </div>
    <template v-else>
      <div style="padding:20px 24px 16px;background:#ffffff;border-bottom:1px solid #dfe6ee">
        <div style="display:flex;align-items:center;gap:7px;font-size:11.5px;color:#7d8b9c;margin-bottom:8px"><RouterLink :to="{ name: 'transfers' }" style="color:var(--c-primary)">Stock transfers</RouterLink><span style="color:#c2cedb">/</span><span style="color:#0f1c2b;font-weight:500;font-family:'IBM Plex Mono',monospace">{{ t.ref }}</span></div>
        <div style="display:flex;align-items:flex-start;gap:20px;flex-wrap:wrap">
          <div>
            <div style="display:flex;align-items:center;gap:10px;margin-bottom:3px"><h2 style="margin:0;font-size:22px;font-weight:700;letter-spacing:-0.018em">{{ t.from_branch.name }} → {{ t.to_branch.name }}</h2> <span :style="badge(t.status)">{{ STATUS_LABELS[t.status] }}</span></div>
            <div style="font-size:12.5px;color:#5d6b7d">{{ t.ref }} · {{ dmy(t.date) }} · {{ t.items.length }} {{ t.items.length === 1 ? 'line' : 'lines' }}{{ t.created_by ? ' · created by ' + t.created_by : '' }}</div>
          </div>
          <div style="margin-left:auto;display:flex;gap:8px;align-items:center">
            <div @click="print" :style="buttonStyle" v-pseudo:hover="'background:#f2f6fa'">Print delivery note</div>
            <template v-if="isDraft && atSource && can('transfers.edit')">
              <div @click="cancelTransfer" style="height:34px;padding:0 13px;border-radius:8px;border:1px solid #f3cfcf;background:#fdf4f4;display:flex;align-items:center;font-size:12.5px;font-weight:600;color:#b02020;cursor:pointer" v-pseudo:hover="'background:#fdeaea'">Cancel transfer</div>
              <RouterLink :to="{ name: 'transfers-new', query: { id: t.id } }" :style="buttonStyle + ';font-weight:600;color:var(--c-primary-text);text-decoration:none'" v-pseudo:hover="'background:#f2f6fa'">Edit</RouterLink>
              <div @click="send" style="height:34px;padding:0 15px;border-radius:8px;background:var(--c-primary);color:#ffffff;display:flex;align-items:center;font-size:12.5px;font-weight:600;cursor:pointer" v-pseudo:hover="'background:var(--c-primary-hover)'">{{ busy ? 'Sending…' : 'Send transfer' }}</div>
            </template>
            <div v-if="isTransit && atDestination && can('transfers.edit')" @click="receive" style="height:34px;padding:0 15px;border-radius:8px;background:#2f9e63;color:#ffffff;display:flex;align-items:center;font-size:12.5px;font-weight:600;cursor:pointer" v-pseudo:hover="'background:#278652'">{{ busy ? 'Receiving…' : loss > 0 ? 'Receive with ' + money(loss) + ' short' : 'Receive in full' }}</div>
          </div>
        </div>
      </div>
      <div style="padding:20px 24px 32px;display:grid;grid-template-columns:minmax(0,1fr) 330px;gap:16px;align-items:start">
        <div style="display:grid;gap:14px">
          <div style="background:#ffffff;border:1px solid #dfe6ee;border-radius:12px;padding:18px 22px">
            <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:0">
              <div v-for="(st, i) in steps" :key="st.label" style="display:flex;flex-direction:column;gap:8px">
                <div style="display:flex;align-items:center">
                  <div :style="stepDot(st)">{{ st.done ? '✓' : st.failed ? '×' : i + 1 }}</div>
                  <div :style="'flex:1;height:2px;margin:0 8px;' + (i === 2 ? 'visibility:hidden;' : '') + (steps[i + 1] && steps[i + 1].done ? 'background:#2f9e63' : 'background:#e4eaf1')"></div>
                </div>
                <div>
                  <div :style="'font-size:13px;font-weight:600;' + (st.done || st.next ? 'color:#0f1c2b' : 'color:#8b98a8')">{{ st.label }}</div>
                  <div style="font-size:11.5px;color:#7d8b9c;margin-top:1px">{{ st.meta }}</div>
                </div>
              </div>
            </div>
          </div>
          <div v-if="isTransit" style="display:flex;gap:10px;align-items:flex-start;background:var(--c-primary-soft);border:1px solid #cfe0f5;border-radius:11px;padding:11px 14px;font-size:12.5px;color:var(--c-primary-text);line-height:1.5">
            <div style="font-weight:700;flex:none">i</div>
            <div v-if="atDestination">Count what arrived at {{ t.to_branch.name }} and enter it under Received. Any shortfall is written off as transit loss.</div>
            <div v-else>Waiting for {{ t.to_branch.name }} to count what arrived and receive it.</div>
          </div>
          <div style="background:#ffffff;border:1px solid #dfe6ee;border-radius:12px;overflow:hidden">
            <div style="display:grid;grid-template-columns:28px minmax(0,2.2fr) 1fr 1.2fr 1fr 1fr;gap:10px;padding:10px 16px;background:#f6f9fc;border-bottom:1px solid #dfe6ee;font-family:'IBM Plex Mono',monospace;font-size:9.5px;letter-spacing:0.07em;text-transform:uppercase;color:#5d6b7d">
              <div>#</div>
              <div>Product</div>
              <div style="text-align:right">Sent</div>
              <div style="text-align:center">Received</div>
              <div style="text-align:right">Variance</div>
              <div style="text-align:right">Value</div>
            </div>
            <div v-for="(l, i) in t.items" :key="l.id" style="display:grid;grid-template-columns:28px minmax(0,2.2fr) 1fr 1.2fr 1fr 1fr;gap:10px;padding:10px 16px;border-bottom:1px solid #f0f4f8;align-items:center">
              <div style="font-family:'IBM Plex Mono',monospace;font-size:11px;color:#9aa7b6">{{ i + 1 }}</div>
              <div style="min-width:0">
                <div style="font-size:13px;font-weight:500">{{ l.name }}</div>
                <div style="font-family:'IBM Plex Mono',monospace;font-size:10.5px;color:#8b98a8">SKU {{ l.sku }} · {{ money(l.cost) }}{{ l.unit ? ' / ' + l.unit : '' }}</div>
              </div>
              <div style="font-family:'IBM Plex Mono',monospace;font-size:12.5px;text-align:right">{{ qtyUnit(l.qty, l.unit) }}</div>
              <div style="display:flex;justify-content:center">
                <div v-if="isTransit && atDestination" :style="'display:flex;align-items:center;height:32px;border-radius:8px;background:#ffffff;border:1px solid ' + (shortBy(l) > 0 ? '#d98c1a' : '#bfe5d1')"><input v-model.number="received[l.id]" type="number" min="0" :max="l.qty" step="any" style="width:64px;height:100%;border:none;background:transparent;text-align:right;font-family:'IBM Plex Mono',monospace;font-size:12.5px;font-weight:600;outline:none;padding:0 4px 0 0" /> <div style="font-size:11px;color:#8b98a8;padding-right:9px">{{ l.unit }}</div></div>
                <div v-else style="font-family:'IBM Plex Mono',monospace;font-size:12.5px;color:#4a5866">{{ l.received_qty === null ? '—' : qtyUnit(l.received_qty, l.unit) }}</div>
              </div>
              <div :style="'font-family:\'IBM Plex Mono\',monospace;font-size:12.5px;text-align:right;' + (shortBy(l) > 0 ? 'color:#b02020;font-weight:600' : 'color:#9aa7b6')">{{ varianceText(l) }}</div>
              <div style="font-family:'IBM Plex Mono',monospace;font-size:12.5px;text-align:right">{{ money(l.qty * l.cost) }}</div>
            </div>
            <div style="display:flex;align-items:center;gap:12px;padding:12px 16px;background:#f6f9fc">
              <div style="font-size:12px;color:#7d8b9c;flex:1">{{ linesFoot }}</div>
              <div v-if="loss > 0" style="font-family:'IBM Plex Mono',monospace;font-size:12.5px;font-weight:600;color:#b02020">Transit loss {{ money(loss) }}</div>
            </div>
          </div>
          <div v-if="t.notes" style="background:#ffffff;border:1px solid #dfe6ee;border-radius:12px;padding:15px 19px">
            <div style="font-size:12px;font-weight:600;margin-bottom:5px">Notes</div>
            <div style="font-size:13px;color:#4a5866;line-height:1.55;white-space:pre-wrap">{{ t.notes }}</div>
          </div>
        </div>
        <div style="display:grid;gap:14px">
          <div style="background:#ffffff;border:1px solid #dfe6ee;border-radius:12px;padding:17px 19px">
            <div style="display:grid;gap:12px">
              <div>
                <div :style="captionStyle">From</div>
                <div style="font-size:13.5px;font-weight:600">{{ t.from_branch.name }}</div>
                <div style="font-size:12px;color:#7d8b9c">{{ t.from_branch.address }}</div>
              </div>
              <div>
                <div :style="captionStyle">To</div>
                <div style="font-size:13.5px;font-weight:600">{{ t.to_branch.name }}</div>
                <div style="font-size:12px;color:#7d8b9c">{{ t.to_branch.address }}</div>
              </div>
            </div>
            <div style="border-top:1px solid #eaeff5;margin-top:14px;padding-top:12px;display:grid;gap:7px;font-size:12.5px">
              <div style="display:flex">
                <div style="flex:1;color:#5d6b7d">Stock value</div>
                <div style="font-family:'IBM Plex Mono',monospace">{{ money(t.stock_value) }}</div>
              </div>
              <div style="display:flex">
                <div style="flex:1;color:#5d6b7d">Shipping</div>
                <div style="font-family:'IBM Plex Mono',monospace">{{ money(t.shipping_cost) }}</div>
              </div>
              <div style="display:flex;font-weight:700;font-size:13.5px;padding-top:4px">
                <div style="flex:1">Total</div>
                <div style="font-family:'IBM Plex Mono',monospace">{{ money(t.stock_value + t.shipping_cost) }}</div>
              </div>
            </div>
          </div>
          <div style="background:#ffffff;border:1px solid #dfe6ee;border-radius:12px;padding:17px 19px">
            <div style="font-size:13px;font-weight:700;margin-bottom:12px">Activity</div>
            <div style="display:grid;gap:12px">
              <div v-for="a in t.logs" :key="a.id" style="display:flex;gap:10px">
                <div :style="logDot(a.tone)"></div>
                <div style="min-width:0">
                  <div style="font-size:12.5px;line-height:1.45">{{ a.message }}</div>
                  <div style="font-size:11px;color:#9aa7b6;margin-top:1px">{{ dayTime(a.logged_at) }}</div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>

<script setup lang="ts">
// One stock transfer (/stock-transfers/details?id=5).
// API: GET /api/stock-transfers/{id}, POST /api/stock-transfers/{id}/send, /receive, /cancel
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import { api } from '../../api/http.js';
import { can } from '../../api/auth.js';
import { money, dmy, badge, logDot, dayTime, qtyUnit, userBranchId, STATUS_LABELS } from '../../api/transfers.js';

const route = useRoute();

const buttonStyle = 'height:34px;padding:0 13px;border-radius:8px;border:1px solid #dfe6ee;display:flex;align-items:center;font-size:12.5px;font-weight:500;color:#4a5866;cursor:pointer';
const captionStyle = "font-family:'IBM Plex Mono',monospace;font-size:9.5px;letter-spacing:.07em;text-transform:uppercase;color:#7d8b9c;margin-bottom:3px";

const t = ref<any>(null);
const received = reactive<Record<number, number>>({}); // what arrived, by line id
const loading = ref(true);
const busy = ref(false);

const id = computed(() => Number(route.query.id));
const isDraft = computed(() => t.value.status === 'draft');
const isTransit = computed(() => t.value.status === 'transit');
const isDone = computed(() => t.value.status === 'received' || t.value.status === 'variance');
const cancelled = computed(() => t.value.status === 'cancelled');

// A user with a branch only sends from it and receives into it.
const atSource = computed(() => !userBranchId.value || userBranchId.value === t.value.from_branch_id);
const atDestination = computed(() => !userBranchId.value || userBranchId.value === t.value.to_branch_id);

// How much of a line is missing. While in transit: from the numbers typed in.
function shortBy(line: any) {
  if (isTransit.value && atDestination.value) return Math.max(0, line.qty - (Number(received[line.id]) || 0));
  if (line.received_qty === null) return 0;
  return Math.max(0, line.qty - line.received_qty);
}

function varianceText(line: any) {
  if (!isDone.value && !(isTransit.value && atDestination.value)) return '—';
  const short = shortBy(line);
  return short > 0 ? '−' + qtyUnit(short, line.unit) : '0';
}

const loss = computed(() => t.value.items.reduce((sum: number, l: any) => sum + shortBy(l) * l.cost, 0));

const linesFoot = computed(() => {
  if (isTransit.value) return atDestination.value ? 'Received quantities start at what was sent' : 'On the road';
  if (isDone.value) return 'Counts confirmed at ' + t.value.to_branch.name;
  return 'Stock has not moved yet';
});

const steps = computed(() => [
  { label: 'Created', meta: t.value.created_by || '', done: true, next: false, failed: false },
  {
    label: cancelled.value ? 'Cancelled' : 'Sent',
    meta: cancelled.value ? '' : t.value.sent_at ? dayTime(t.value.sent_at) : 'Not sent yet',
    done: !!t.value.sent_at, next: isDraft.value, failed: cancelled.value
  },
  {
    label: 'Received',
    meta: t.value.received_at ? dayTime(t.value.received_at) : isTransit.value ? 'Waiting at ' + t.value.to_branch.name : '—',
    done: !!t.value.received_at, next: isTransit.value, failed: false
  }
]);

function stepDot(step: any) {
  const look = step.done ? 'background:#2f9e63;color:#ffffff'
    : step.failed ? 'background:#d93a3a;color:#ffffff'
    : step.next ? 'background:#ffffff;color:var(--c-primary);box-shadow:inset 0 0 0 2px var(--c-primary)'
    : 'background:#eef2f6;color:#8b98a8';
  return 'width:26px;height:26px;border-radius:50%;display:grid;place-items:center;font-size:11.5px;font-weight:700;flex:none;' + look;
}

function show(transfer: any) {
  t.value = transfer;
  for (const item of transfer.items) {
    received[item.id] = item.qty; // starts at what was sent
  }
}

async function load() {
  if (!id.value) { t.value = null; loading.value = false; return; }
  loading.value = true;
  try {
    show(await api.get('/stock-transfers/' + id.value));
  } catch (e) {
    t.value = null;
  }
  loading.value = false;
}

onMounted(load);
watch(id, load);

function print() { window.print(); }

async function send() {
  if (busy.value) return;
  busy.value = true;
  try {
    show(await api.post('/stock-transfers/' + id.value + '/send'));
  } catch (e) { /* the error bar shows why */ }
  busy.value = false;
}

async function receive() {
  if (busy.value) return;
  const lines = t.value.items.map((l: any) => ({ id: l.id, received_qty: Number(received[l.id]) || 0 }));
  busy.value = true;
  try {
    show(await api.post('/stock-transfers/' + id.value + '/receive', { lines }));
  } catch (e) { /* the error bar shows why */ }
  busy.value = false;
}

async function cancelTransfer() {
  if (!confirm('Cancel ' + t.value.ref + '? No stock has moved yet.')) return;
  try {
    show(await api.post('/stock-transfers/' + id.value + '/cancel'));
  } catch (e) { /* the error bar shows why */ }
}
</script>
