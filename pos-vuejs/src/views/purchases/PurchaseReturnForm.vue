<template>
  <template v-if="vm.puReturn">
    <div>
      <div style="padding:20px 24px 16px;background:#ffffff;border-bottom:1px solid #dfe6ee">
        <div style="display:flex;align-items:center;gap:7px;font-size:11.5px;color:#7d8b9c;margin-bottom:8px"><span @click="vm.puGoReturns" style="cursor:pointer;color:var(--c-primary)">Returns</span><span style="color:#c2cedb">/</span><span style="color:#0f1c2b;font-weight:500">Return items</span></div>
        <div style="display:flex;align-items:flex-start;gap:20px;flex-wrap:wrap">
          <div>
            <h2 style="margin:0 0 3px;font-size:22px;font-weight:700;letter-spacing:-0.018em">{{ p ? 'Return to ' + p.supplier.name : 'New return' }}</h2>
            <div style="font-size:12.5px;color:#5d6b7d">{{ p ? 'Against ' + p.ref + ' · received ' + dmy(p.received_at) + ' · lines and costs locked to the original purchase' : 'A return always starts from a received purchase' }}</div>
          </div>
          <div style="margin-left:auto;display:flex;gap:8px;align-items:center">
            <div @click="vm.puGoReturns" style="height:34px;padding:0 13px;border-radius:8px;border:1px solid #dfe6ee;display:flex;align-items:center;font-size:12.5px;font-weight:500;color:#4a5866;cursor:pointer" v-pseudo:hover="'background:#f2f6fa'">Cancel</div>
            <div v-if="p" @click="submit" :style="'height:34px;padding:0 15px;border-radius:8px;display:flex;align-items:center;font-size:12.5px;font-weight:600;color:#ffffff;' + (hasLines ? 'background:var(--c-primary);cursor:pointer' : 'background:#b7c6d6;cursor:not-allowed')">{{ saving ? 'Saving…' : hasLines ? 'Submit return · ' + money(totalCredit) : 'Submit return' }}</div>
          </div>
        </div>
      </div>

      <!-- No purchase picked yet -->
      <div v-if="!p" style="padding:20px 24px 32px">
        <div style="background:#ffffff;border:1px solid #dfe6ee;border-radius:12px;padding:17px 19px;max-width:520px">
          <label :style="labelStyle">Purchase to return against</label>
          <select @change="pickPurchase" style="width:100%;height:36px;border:1px solid #dfe6ee;border-radius:8px;padding:0 9px;font-family:'Public Sans',sans-serif;font-size:13px;background:#ffffff">
            <option value="">{{ loading ? 'Loading…' : 'Pick a received purchase' }}</option>
            <option v-for="r in purchases" :key="r.id" :value="r.id">{{ r.ref }} · {{ r.supplier ? r.supplier.name : '' }} · {{ dmy(r.date) }}</option>
          </select>
        </div>
      </div>

      <div v-else style="padding:20px 24px 32px;display:grid;grid-template-columns:1fr 330px;gap:16px;align-items:start">
        <div style="display:grid;gap:14px">
          <div v-if="error" style="background:#fdeaea;color:#b02020;border-radius:9px;padding:10px 13px;font-size:12.5px">{{ error }}</div>
          <div style="background:#ffffff;border:1px solid #dfe6ee;border-radius:12px;padding:17px 19px;display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px">
            <div><label :style="labelStyle">Return reference</label> <div style="height:36px;border:1px solid #dfe6ee;border-radius:8px;padding:0 11px;display:flex;align-items:center;font-family:'IBM Plex Mono',monospace;font-size:12.5px;color:#5d6b7d">{{ nextRef }}</div></div>
            <div><label :style="labelStyle">Date</label> <input v-model="date" type="date" style="width:100%;height:36px;border:1px solid #dfe6ee;border-radius:8px;padding:0 11px;font-family:'IBM Plex Mono',monospace;font-size:12.5px" /></div>
            <div><label :style="labelStyle">Take stock from</label> <div style="height:36px;border:1px solid #dfe6ee;border-radius:8px;padding:0 11px;display:flex;align-items:center;font-size:13px;color:#5d6b7d">{{ p.branch.name }}</div></div>
          </div>
          <div style="background:#ffffff;border:1px solid #dfe6ee;border-radius:12px;overflow:hidden">
            <div style="padding:14px 16px;border-bottom:1px solid #eaeff5;display:flex;align-items:center;gap:12px;flex-wrap:wrap">
              <div style="max-width:480px">
                <div style="font-size:13px;font-weight:700">What's going back</div>
                <div style="font-size:11.5px;color:#7d8b9c;margin-top:2px;line-height:1.5">Every line from the purchase is listed at zero. Raise the quantity on the lines you're returning. You can't return more than was received.</div>
              </div>
              <div @click="returnAll" style="margin-left:auto;height:30px;padding:0 11px;border-radius:7px;border:1px solid #dfe6ee;display:flex;align-items:center;font-size:12px;font-weight:500;color:#4a5866;cursor:pointer" v-pseudo:hover="'background:#f2f6fa'">Return everything</div>
              <div @click="clearAll" style="height:30px;padding:0 11px;border-radius:7px;border:1px solid #dfe6ee;display:flex;align-items:center;font-size:12px;font-weight:500;color:#4a5866;cursor:pointer" v-pseudo:hover="'background:#f2f6fa'">Clear</div>
            </div>
            <div style="display:grid;grid-template-columns:1.7fr 0.8fr 1.15fr 1.3fr 0.8fr 0.9fr;gap:10px;padding:9px 16px;background:#f6f9fc;border-bottom:1px solid #dfe6ee;font-family:'IBM Plex Mono',monospace;font-size:9.5px;letter-spacing:0.07em;text-transform:uppercase;color:#5d6b7d">
              <div>Product</div>
              <div style="text-align:right">Can return</div>
              <div style="text-align:center">Return qty</div>
              <div>Reason</div>
              <div style="text-align:right">Unit cost</div>
              <div style="text-align:right">Credit</div>
            </div>
            <div v-for="l in lines" :key="l.id" :style="'display:grid;grid-template-columns:1.7fr 0.8fr 1.15fr 1.3fr 0.8fr 0.9fr;gap:10px;padding:9px 16px;align-items:center;border-bottom:1px solid #f0f4f8;' + (l.qty > 0 ? 'background:#fff8f3;box-shadow:inset 3px 0 0 #d98c1a' : '')">
              <div style="min-width:0">
                <div style="font-size:13px;font-weight:500;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">{{ l.name }}</div>
                <div style="font-size:10.5px;color:#9aa7b6">on hand {{ qtyText(l.on_hand) }} {{ l.unit }}</div>
              </div>
              <div style="font-family:'IBM Plex Mono',monospace;font-size:12.5px;text-align:right;color:#5d6b7d">{{ qtyText(l.returnable) }} {{ l.unit }}</div>
              <div style="display:flex;align-items:center;border:1px solid #dfe6ee;border-radius:7px;overflow:hidden;height:32px;background:#ffffff">
                <div @click="setQty(l, l.qty - 1)" style="width:28px;height:100%;display:grid;place-items:center;font-size:14px;color:#5d6b7d;cursor:pointer;flex:none" v-pseudo:hover="'background:#f2f6fa;color:var(--c-primary)'">−</div>
                <input :value="l.qty" @change="setQty(l, Number(($event.target as HTMLInputElement).value))" type="number" min="0" step="any" style="flex:1;min-width:0;width:100%;text-align:center;font-family:'IBM Plex Mono',monospace;font-size:12.5px;font-weight:600;border:0;border-left:1px solid #eaeff5;border-right:1px solid #eaeff5;height:100%;outline:none" />
                <div @click="setQty(l, l.qty + 1)" style="width:28px;height:100%;display:grid;place-items:center;font-size:14px;color:#5d6b7d;cursor:pointer;flex:none" v-pseudo:hover="'background:#f2f6fa;color:var(--c-primary)'">+</div>
              </div>
              <select v-model="l.reason" :disabled="l.qty <= 0" style="height:32px;min-width:0;border:1px solid #dfe6ee;border-radius:7px;padding:0 6px;font-family:'Public Sans',sans-serif;font-size:12px;background:#ffffff">
                <option v-for="r in RETURN_REASONS" :key="r">{{ r }}</option>
              </select>
              <div style="font-family:'IBM Plex Mono',monospace;font-size:12.5px;text-align:right;color:#4a5866">{{ money(l.unit_cost) }}</div>
              <div :style="'font-family:\'IBM Plex Mono\',monospace;font-size:13px;text-align:right;font-weight:600;' + (l.qty > 0 ? '' : 'color:#c2cedb')">{{ l.qty > 0 ? money(lineCredit(l)) : '—' }}</div>
            </div>
            <div style="padding:10px 16px;font-size:11.5px;color:#7d8b9c;display:flex"><span style="margin-left:auto;font-family:'IBM Plex Mono',monospace">{{ returningLines.length }} of {{ lines.length }} lines · {{ qtyText(totalQty) }} returning</span></div>
          </div>
          <div style="background:#ffffff;border:1px solid #dfe6ee;border-radius:12px;padding:17px 19px">
            <label :style="labelStyle">Notes to supplier</label> <textarea v-model="notes" placeholder="Rump arrived above 5°C, logged at 11:02 by Omar" style="width:100%;height:72px;border:1px solid #dfe6ee;border-radius:8px;padding:9px 11px;font-family:'Public Sans',sans-serif;font-size:13px;outline:none;resize:none" v-pseudo:focus="'border-color:var(--c-primary)'"></textarea>
          </div>
        </div>
        <div style="display:grid;gap:12px">
          <div style="background:#ffffff;border:1px solid #dfe6ee;border-radius:12px;padding:16px 18px">
            <div style="font-size:12.5px;font-weight:700;margin-bottom:13px">Credit</div>
            <div style="display:grid;gap:9px">
              <div style="display:flex;align-items:baseline;gap:10px">
                <div style="font-size:12.5px;color:#5d6b7d;flex:1">Goods returned</div>
                <div style="font-family:'IBM Plex Mono',monospace;font-size:13px">{{ money(subtotal) }}</div>
              </div>
              <div style="display:flex;align-items:baseline;gap:10px">
                <div style="font-size:12.5px;color:#5d6b7d;flex:1">GST reversed</div>
                <div style="font-family:'IBM Plex Mono',monospace;font-size:13px">{{ money(tax) }}</div>
              </div>
            </div>
            <div style="display:flex;align-items:center;gap:12px;padding:12px 14px;background:#0f1c2b;border-radius:10px;margin-top:14px">
              <div style="font-size:12.5px;font-weight:600;color:#cfdbe6;flex:1">Total credit</div>
              <div style="font-family:'IBM Plex Mono',monospace;font-size:22px;font-weight:600;color:#ffffff;letter-spacing:-0.02em">{{ money(totalCredit) }}</div>
            </div>
          </div>
          <div style="background:#ffffff;border:1px solid #dfe6ee;border-radius:12px;padding:16px 18px">
            <div style="font-size:12.5px;font-weight:700;margin-bottom:11px">Settle as</div>
            <div style="display:grid;gap:7px;margin-bottom:12px">
              <div v-for="s in SETTLEMENTS" :key="s.name" @click="settlement = s.name" :style="'display:flex;align-items:flex-start;gap:11px;padding:10px 12px;border-radius:9px;cursor:pointer;' + (settlement === s.name ? 'border:1px solid var(--c-primary);background:var(--c-primary-tint)' : 'border:1px solid #dfe6ee')">
                <div :style="'width:17px;height:17px;border-radius:50%;display:grid;place-items:center;flex:none;margin-top:1px;border:1.5px solid ' + (settlement === s.name ? 'var(--c-primary)' : '#c2cedb')">
                  <div :style="'width:8px;height:8px;border-radius:50%;background:' + (settlement === s.name ? 'var(--c-primary)' : 'transparent')"></div>
                </div>
                <div style="flex:1;min-width:0">
                  <div style="font-size:12.5px;font-weight:600">{{ s.title }}</div>
                  <div style="font-size:11.5px;color:#7d8b9c;line-height:1.45;margin-top:1px">{{ s.desc }}</div>
                </div>
              </div>
            </div>
            <div v-if="settlement === 'Reduced owed'" style="display:flex;align-items:baseline;gap:10px;padding-top:10px;border-top:1px solid #f0f4f8">
              <div style="font-size:12.5px;font-weight:600;flex:1">Owed on {{ p.ref }} after</div>
              <div style="font-family:'IBM Plex Mono',monospace;font-size:16px;font-weight:700">{{ money(Math.max(0, p.due - totalCredit)) }}</div>
            </div>
            <div v-if="settlement === 'Reduced owed' && totalCredit > p.due" style="font-size:11.5px;color:#7d8b9c;margin-top:5px">{{ money(totalCredit - p.due) }} more than is owed. The rest is held as supplier credit.</div>
          </div>
          <div :style="'border-radius:12px;padding:14px 16px;' + (hasLines ? 'background:#fdf1dd;border:1px solid #f0d9ac;color:#8a5300' : 'background:#f6f9fc;border:1px solid #dfe6ee;color:#7d8b9c')">
            <div style="font-size:12px;font-weight:700;margin-bottom:7px">On submit</div>
            <div style="font-size:12px;line-height:1.6">{{ hasLines ? '−' + qtyText(totalQty) + ' out of ' + p.branch.name + ' across ' + returningLines.length + (returningLines.length === 1 ? ' product' : ' products') + '. ' + money(totalCredit) + ' comes off what you owe ' + p.supplier.name + '.' : 'Nothing selected yet. Raise a quantity on at least one line.' }}</div>
          </div>
        </div>
      </div>
    </div>
  </template>
</template>

<script setup lang="ts">
// Purchases > Return items (/purchases/returns/new?purchase=5).
// API: GET /api/purchases/{id}, GET /api/purchases?tab=received, GET /api/purchase-returns (next number), POST /api/purchase-returns
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { usePageVm } from '../../erp/useErpPage';
import { api } from '../../api/http.js';
import { money, dmy, qtyText, today, RETURN_REASONS, SETTLEMENTS } from '../../api/purchases.js';

const vm = usePageVm();
const route = useRoute();
const router = useRouter();

const labelStyle = 'display:block;font-size:12px;font-weight:600;margin-bottom:5px';

const p = ref<any>(null);
const purchases = ref<any[]>([]);
const lines = ref<any[]>([]);
const nextRef = ref('');
const date = ref(today());
const notes = ref('');
const settlement = ref('Reduced owed');
const error = ref('');
const saving = ref(false);
const loading = ref(true);

const purchaseId = computed(() => Number(route.query.purchase) || 0);

const returningLines = computed(() => lines.value.filter(l => l.qty > 0));
const hasLines = computed(() => returningLines.value.length > 0);
const totalQty = computed(() => returningLines.value.reduce((sum, l) => sum + l.qty, 0));
const subtotal = computed(() => returningLines.value.reduce((sum, l) => sum + lineCredit(l), 0));
const tax = computed(() => (p.value ? Math.round(subtotal.value * p.value.tax_rate) / 100 : 0));
const totalCredit = computed(() => subtotal.value + tax.value);

// The same sums the server does when it saves.
function lineCredit(l: any) {
  return Math.round(l.qty * l.unit_cost * 100) / 100;
}

async function load() {
  loading.value = true;
  error.value = '';
  p.value = null;
  try {
    if (purchaseId.value) {
      const res = await api.get('/purchase-returns');
      nextRef.value = res.next_ref;
      const purchase = await api.get('/purchases/' + purchaseId.value);
      if (purchase.status === 'received') {
        lines.value = purchase.items.map((i: any) => ({
          id: i.id, name: i.name, unit: i.unit, on_hand: i.on_hand, returnable: i.returnable,
          unit_cost: Math.round(i.cost * (1 - i.discount / 100) * 100) / 100, qty: 0, reason: 'Damaged'
        }));
        p.value = purchase;
      }
    } else {
      const res = await api.get('/purchases?tab=received');
      purchases.value = res.data;
    }
  } catch (e) { /* the error bar shows why */ }
  loading.value = false;
}

onMounted(load);
watch(purchaseId, load);

function pickPurchase(e: Event) {
  const id = (e.target as HTMLSelectElement).value;
  if (id) router.push({ name: 'purchases-return-new', query: { purchase: id } });
}

// Never below 0 or above what can still be returned.
function setQty(line: any, value: number) {
  line.qty = Math.max(0, Math.min(line.returnable, Number(value) || 0));
}
function returnAll() { lines.value.forEach(l => { l.qty = l.returnable; }); }
function clearAll() { lines.value.forEach(l => { l.qty = 0; }); }

async function submit() {
  if (!hasLines.value || saving.value) return;
  error.value = '';
  saving.value = true;
  try {
    await api.post('/purchase-returns', {
      purchase_id: p.value.id,
      date: date.value,
      settlement: settlement.value,
      notes: notes.value || null,
      lines: returningLines.value.map(l => ({ purchase_item_id: l.id, qty: l.qty, reason: l.reason }))
    });
    vm.value.puGoReturns();
  } catch (e: any) {
    error.value = e.message;
  }
  saving.value = false;
}
</script>
