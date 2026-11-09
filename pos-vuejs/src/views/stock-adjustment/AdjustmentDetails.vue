<template>
  <div>
    <div v-if="!a" style="padding:24px;font-size:13px;color:#5d6b7d">
      {{ loading ? 'Loading…' : 'Adjustment not found.' }}
      <RouterLink v-if="!loading" :to="{ name: 'adjust' }" style="margin-left:6px">Back to adjustments</RouterLink>
    </div>
    <template v-else>
      <div style="padding:20px 24px 16px;background:#ffffff;border-bottom:1px solid #dfe6ee">
        <div style="display:flex;align-items:center;gap:7px;font-size:11.5px;color:#7d8b9c;margin-bottom:8px"><RouterLink :to="{ name: 'adjust' }" style="color:var(--c-primary)">Stock adjustments</RouterLink><span style="color:#c2cedb">/</span><span style="color:#0f1c2b;font-weight:500;font-family:'IBM Plex Mono',monospace">{{ a.ref }}</span></div>
        <div style="display:flex;align-items:flex-start;gap:20px;flex-wrap:wrap">
          <div>
            <div style="display:flex;align-items:center;gap:10px;margin-bottom:3px;flex-wrap:wrap">
              <h2 style="margin:0;font-size:22px;font-weight:700;letter-spacing:-0.018em;font-family:'IBM Plex Mono',monospace">{{ a.ref }}</h2>
              <span :style="badge(a.type)">{{ TYPE_LABELS[a.type] }}</span>
              <RouterLink v-if="a.reversed_by" :to="{ name: 'adjust-details', query: { id: a.reversed_by.id } }" style="background:#eef2f6;color:#5d6b7d;border-radius:5px;padding:3px 8px;font-size:11.5px;font-weight:600;text-decoration:none">Reversed by {{ a.reversed_by.ref }}</RouterLink>
              <RouterLink v-if="a.reverses" :to="{ name: 'adjust-details', query: { id: a.reverses.id } }" style="background:#eef2f6;color:#5d6b7d;border-radius:5px;padding:3px 8px;font-size:11.5px;font-weight:600;text-decoration:none">Reverses {{ a.reverses.ref }}</RouterLink>
            </div>
            <div style="font-size:12.5px;color:#5d6b7d">{{ a.branch.name }} · {{ dmy(a.date) }} · {{ a.items.length }} {{ a.items.length === 1 ? 'line' : 'lines' }}{{ a.created_by ? ' · posted by ' + a.created_by : '' }}</div>
          </div>
          <div style="margin-left:auto;display:flex;gap:8px;align-items:center">
            <div @click="print" style="height:34px;padding:0 13px;border-radius:8px;border:1px solid #dfe6ee;display:flex;align-items:center;font-size:12.5px;font-weight:500;color:#4a5866;cursor:pointer" v-pseudo:hover="'background:#f2f6fa'">Print</div>
            <div v-if="canReverse" @click="reverse" style="height:34px;padding:0 13px;border-radius:8px;border:1px solid #f3cfcf;background:#fdf4f4;display:flex;align-items:center;font-size:12.5px;font-weight:600;color:#b02020;cursor:pointer" v-pseudo:hover="'background:#fdeaea'">{{ busy ? 'Reversing…' : 'Reverse adjustment' }}</div>
          </div>
        </div>
      </div>
      <div style="padding:20px 24px 32px;display:grid;grid-template-columns:minmax(0,1fr) 330px;gap:16px;align-items:start">
        <div style="background:#ffffff;border:1px solid #dfe6ee;border-radius:12px;overflow:hidden">
          <div style="display:grid;grid-template-columns:24px minmax(0,2fr) 1.2fr 1fr 1.3fr 1fr;gap:10px;padding:10px 16px;background:#f6f9fc;border-bottom:1px solid #dfe6ee;font-family:'IBM Plex Mono',monospace;font-size:9.5px;letter-spacing:0.07em;text-transform:uppercase;color:#5d6b7d">
            <div>#</div>
            <div>Product</div>
            <div style="text-align:right">Before → after</div>
            <div style="text-align:right">Change</div>
            <div>Reason</div>
            <div style="text-align:right">Value</div>
          </div>
          <div v-for="(l, i) in a.items" :key="l.id" style="display:grid;grid-template-columns:24px minmax(0,2fr) 1.2fr 1fr 1.3fr 1fr;gap:10px;padding:11px 16px;border-bottom:1px solid #f0f4f8;align-items:center">
            <div style="font-family:'IBM Plex Mono',monospace;font-size:11px;color:#9aa7b6">{{ i + 1 }}</div>
            <div style="min-width:0">
              <div style="font-size:13px;font-weight:500">{{ l.name }}</div>
              <div style="font-family:'IBM Plex Mono',monospace;font-size:10.5px;color:#8b98a8">SKU {{ l.sku }} · {{ money(l.cost) }}{{ l.unit ? ' / ' + l.unit : '' }}</div>
            </div>
            <div style="font-family:'IBM Plex Mono',monospace;font-size:12px;text-align:right;color:#4a5866">{{ qtyUnit(l.before_qty, l.unit) }} <span style="color:#c2cedb">→</span> {{ qtyUnit(l.before_qty + l.change_qty, l.unit) }}</div>
            <div style="text-align:right"><span :style="changeStyle(l.change_qty)">{{ l.change_qty > 0 ? '+' : '−' }}{{ qtyUnit(Math.abs(l.change_qty), l.unit) }}</span></div>
            <div style="font-size:12.5px;color:#4a5866">{{ l.reason }}</div>
            <div :style="'font-family:\'IBM Plex Mono\',monospace;font-size:12.5px;text-align:right;' + valueColor(l.change_qty)">{{ signed(l.change_qty * l.cost) }}</div>
          </div>
          <div style="display:flex;align-items:center;gap:12px;padding:12px 16px;background:#f6f9fc">
            <div style="font-size:12px;color:#7d8b9c;flex:1">Stock updated at {{ a.branch.name }} when posted</div>
            <div :style="'font-family:\'IBM Plex Mono\',monospace;font-size:13px;font-weight:700;' + valueColor(net)">Net {{ signed(net) }}</div>
          </div>
        </div>
        <div style="display:grid;gap:14px">
          <div style="background:#ffffff;border:1px solid #dfe6ee;border-radius:12px;padding:17px 19px;display:grid;gap:9px;font-size:12.5px">
            <div style="display:flex">
              <div style="flex:1;color:#5d6b7d">Location</div>
              <div style="font-weight:600">{{ a.branch.name }}</div>
            </div>
            <div style="display:flex">
              <div style="flex:1;color:#5d6b7d">Date</div>
              <div style="font-family:'IBM Plex Mono',monospace">{{ dmy(a.date) }}</div>
            </div>
            <div style="display:flex">
              <div style="flex:1;color:#5d6b7d">Posted by</div>
              <div>{{ a.created_by || '—' }}</div>
            </div>
            <div style="display:flex">
              <div style="flex:1;color:#5d6b7d">Approved by</div>
              <div>{{ a.approved_by || '—' }}</div>
            </div>
            <div style="border-top:1px solid #eaeff5;padding-top:10px;display:grid;gap:7px">
              <div style="display:flex">
                <div style="flex:1;color:#0f6b43">Increase</div>
                <div style="font-family:'IBM Plex Mono',monospace;color:#0f6b43">+{{ money(a.increase_value) }}</div>
              </div>
              <div style="display:flex">
                <div style="flex:1;color:#b02020">Decrease</div>
                <div style="font-family:'IBM Plex Mono',monospace;color:#b02020">−{{ money(a.decrease_value) }}</div>
              </div>
            </div>
          </div>
          <div v-if="a.notes" style="background:#ffffff;border:1px solid #dfe6ee;border-radius:12px;padding:15px 19px">
            <div style="font-size:12px;font-weight:600;margin-bottom:5px">Notes</div>
            <div style="font-size:13px;color:#4a5866;line-height:1.55;white-space:pre-wrap">{{ a.notes }}</div>
          </div>
          <div v-if="error" style="background:#fdeaea;color:#b02020;border-radius:9px;padding:10px 13px;font-size:12.5px">{{ error }}</div>
        </div>
      </div>
    </template>
  </div>
</template>

<script setup lang="ts">
// One stock adjustment (/stock-adjustment/details?id=5).
// API: GET /api/stock-adjustments/{id}, POST /api/stock-adjustments/{id}/reverse
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { api } from '../../api/http.js';
import { can } from '../../api/auth.js';
import { money, dmy, badge, qtyUnit, signed, valueColor, userBranchId, TYPE_LABELS } from '../../api/adjustments.js';

const route = useRoute();
const router = useRouter();

const a = ref<any>(null);
const loading = ref(true);
const busy = ref(false);
const error = ref('');

const id = computed(() => Number(route.query.id));
const net = computed(() => a.value.increase_value - a.value.decrease_value);

// A reversal, or one already reversed, cannot be reversed. A user with a branch only reverses at it.
const canReverse = computed(() => can('adjust.edit')
  && !a.value.reversed_by && !a.value.reverses_id
  && (!userBranchId.value || userBranchId.value === a.value.branch_id));

function changeStyle(change: number) {
  return "font-family:'IBM Plex Mono',monospace;font-size:12px;font-weight:600;border-radius:5px;padding:2px 7px;white-space:nowrap;"
    + (change > 0 ? 'background:#e7f6ee;color:#0f6b43' : 'background:#fdeaea;color:#b02020');
}

async function load() {
  error.value = '';
  if (!id.value) { a.value = null; loading.value = false; return; }
  loading.value = true;
  try {
    a.value = await api.get('/stock-adjustments/' + id.value);
  } catch (e) {
    a.value = null;
  }
  loading.value = false;
}

onMounted(load);
watch(id, load);

function print() { window.print(); }

async function reverse() {
  if (busy.value) return;
  if (!confirm('Reverse ' + a.value.ref + '? This posts a new adjustment with every line flipped. ' + a.value.ref + ' stays in the history, marked as reversed.')) return;
  error.value = '';
  busy.value = true;
  try {
    const saved = await api.post('/stock-adjustments/' + id.value + '/reverse');
    router.push({ name: 'adjust-details', query: { id: saved.id } });
  } catch (e: any) {
    error.value = e.message;
  }
  busy.value = false;
}
</script>
