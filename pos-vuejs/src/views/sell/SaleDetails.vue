<template>
  <div>
    <div v-if="!s" style="padding:24px;font-size:13px;color:#5d6b7d">
      {{ loading ? 'Loading…' : 'Sale not found.' }}
      <RouterLink v-if="!loading" :to="{ name: 'sell' }" style="margin-left:6px">Back to sales</RouterLink>
    </div>
    <template v-else>
      <div style="padding:20px 24px 18px;background:#ffffff;border-bottom:1px solid #dfe6ee">
        <div style="display:flex;align-items:center;gap:7px;font-size:11.5px;color:#7d8b9c;margin-bottom:14px"><RouterLink :to="{ name: 'sell' }" style="color:var(--c-primary)">Sales</RouterLink><span style="color:#c2cedb">/</span><span style="color:#0f1c2b;font-weight:500">{{ s.ref }}</span></div>
        <div style="display:flex;align-items:flex-start;gap:16px;flex-wrap:wrap">
          <div style="min-width:0">
            <div style="display:flex;align-items:center;gap:9px;flex-wrap:wrap;margin-bottom:4px">
              <h2 style="margin:0;font-family:'IBM Plex Mono',monospace;font-size:22px;font-weight:600;letter-spacing:-0.02em">{{ s.ref }}</h2>
              <span :style="statusBadge(s.status)">{{ STATUS_LABELS[s.status] || s.status }}</span>
              <span v-if="s.order_type" style="background:var(--c-primary-soft);color:var(--c-primary-text);border-radius:5px;padding:3px 9px;font-size:11.5px;font-weight:600">{{ s.order_type }}{{ s.table_no ? ' · Table ' + s.table_no : '' }}</span>
            </div>
            <div style="font-size:12.5px;color:#5d6b7d">{{ s.customer_name }} · {{ dateTime(s.sold_at) }} · {{ s.branch.name }}{{ s.created_by ? ' · rung up by ' + s.created_by : '' }}</div>
          </div>
          <div style="margin-left:auto;display:flex;gap:8px;align-items:center">
            <div @click="print" style="height:34px;padding:0 13px;border-radius:8px;border:1px solid #dfe6ee;display:flex;align-items:center;font-size:12.5px;font-weight:500;color:#4a5866;cursor:pointer" v-pseudo:hover="'background:#f2f6fa'">Print invoice</div>
            <div v-if="canCancel" @click="openCancel" style="height:34px;padding:0 13px;border-radius:8px;border:1px solid #f3cfcf;background:#fdf4f4;display:flex;align-items:center;font-size:12.5px;font-weight:600;color:#b02020;cursor:pointer" v-pseudo:hover="'background:#fdeaea'">Cancel sale</div>
            <div v-if="canReceive" @click="openReceive" style="height:34px;padding:0 15px;border-radius:8px;background:var(--c-primary);color:#ffffff;display:flex;align-items:center;font-size:12.5px;font-weight:600;cursor:pointer" v-pseudo:hover="'background:var(--c-primary-hover)'">Receive {{ money(s.due) }}</div>
          </div>
        </div>
      </div>
      <div style="padding:20px 24px 32px;display:grid;grid-template-columns:1fr 320px;gap:16px;align-items:start">
        <div style="display:grid;gap:14px">
          <div v-if="s.status === 'cancelled'" style="background:#eef2f6;color:#4a5866;border-radius:10px;padding:11px 14px;font-size:12.5px">
            Cancelled{{ s.cancelled_by ? ' by ' + s.cancelled_by : '' }}{{ s.cancelled_at ? ' on ' + dateTime(s.cancelled_at) : '' }}{{ s.cancel_reason ? ': ' + s.cancel_reason : '' }}
          </div>
          <div v-if="!s.items.length" style="background:#fdf1dd;color:#8a5300;border-radius:10px;padding:11px 14px;font-size:12.5px">This older sale was saved without its item lines.</div>
          <div style="background:#ffffff;border:1px solid #dfe6ee;border-radius:12px;padding:26px 28px">
            <div style="display:flex;align-items:flex-start;gap:20px;margin-bottom:22px">
              <div>
                <div style="font-size:15px;font-weight:700">{{ s.business.business_name }}</div>
                <div style="font-size:12px;color:#5d6b7d;line-height:1.55;margin-top:3px">{{ s.branch.address || s.business.address }}<br v-if="s.business.abn" /><template v-if="s.business.abn">ABN {{ s.business.abn }}</template></div>
              </div>
              <div style="margin-left:auto;text-align:right">
                <div style="font-family:'IBM Plex Mono',monospace;font-size:10px;letter-spacing:0.1em;text-transform:uppercase;color:#7d8b9c">Tax invoice</div>
                <div style="font-family:'IBM Plex Mono',monospace;font-size:15px;font-weight:600;margin-top:3px">{{ s.ref }}</div>
                <div style="font-family:'IBM Plex Mono',monospace;font-size:11.5px;color:#5d6b7d;margin-top:2px">{{ dateTime(s.sold_at) }}{{ s.due_date ? ' · due ' + dmy(s.due_date) : '' }}</div>
              </div>
            </div>
            <div style="font-size:11px;color:#7d8b9c;margin-bottom:3px">Bill to</div>
            <div style="font-size:13px;font-weight:600;margin-bottom:20px">{{ s.customer_name }}</div>
            <div style="display:grid;grid-template-columns:2fr 0.8fr 0.9fr 0.9fr;gap:12px;padding:8px 0;border-bottom:1.5px solid #0f1c2b;font-family:'IBM Plex Mono',monospace;font-size:9.5px;letter-spacing:0.07em;text-transform:uppercase;color:#5d6b7d">
              <div>Item</div>
              <div style="text-align:right">Qty</div>
              <div style="text-align:right">Price</div>
              <div style="text-align:right">Amount</div>
            </div>
            <div v-for="l in s.items" :key="l.id" style="display:grid;grid-template-columns:2fr 0.8fr 0.9fr 0.9fr;gap:12px;padding:10px 0;border-bottom:1px solid #eef2f6;align-items:center">
              <div style="font-size:13px;min-width:0">
                {{ l.name }}<span v-if="l.discount_percent" style="font-family:'IBM Plex Mono',monospace;font-size:10.5px;font-weight:600;background:#fdeaea;color:#b02020;border-radius:4px;padding:1px 5px;margin-left:6px">−{{ l.discount_percent }}%</span>
                <div v-if="l.note" style="font-size:11.5px;color:#b45309;font-style:italic;margin-top:2px">{{ l.note }}</div>
              </div>
              <div style="font-family:'IBM Plex Mono',monospace;font-size:12.5px;text-align:right">{{ qtyText(l.qty, l.unit) }}</div>
              <div style="font-family:'IBM Plex Mono',monospace;font-size:12.5px;text-align:right;color:#4a5866">{{ money(l.price) }}</div>
              <div style="font-family:'IBM Plex Mono',monospace;font-size:12.5px;text-align:right;font-weight:500">{{ money(l.total) }}</div>
            </div>
            <div style="margin-left:auto;width:280px;display:grid;gap:7px;margin-top:14px">
              <div v-for="r in totalRows" :key="r.k" style="display:flex;font-size:12.5px">
                <div style="flex:1;color:#5d6b7d">{{ r.k }}</div>
                <div :style="'font-family:\'IBM Plex Mono\',monospace;' + (r.red ? 'color:#b02020' : '')">{{ r.v }}</div>
              </div>
              <div style="display:flex;font-size:15px;font-weight:700;padding-top:8px;border-top:1.5px solid #0f1c2b">
                <div style="flex:1">Total</div>
                <div style="font-family:'IBM Plex Mono',monospace">{{ money(s.total) }}</div>
              </div>
              <div style="display:flex;font-size:12.5px">
                <div style="flex:1;color:#5d6b7d">GST included</div>
                <div style="font-family:'IBM Plex Mono',monospace">{{ money(s.gst) }}</div>
              </div>
              <div style="display:flex;font-size:12.5px">
                <div style="flex:1;color:#5d6b7d">Paid</div>
                <div style="font-family:'IBM Plex Mono',monospace;color:#0f6b43">{{ money(s.paid) }}</div>
              </div>
              <div v-if="s.due" style="display:flex;font-size:13px;font-weight:700;color:#b02020">
                <div style="flex:1">Balance due</div>
                <div style="font-family:'IBM Plex Mono',monospace">{{ money(s.due) }}</div>
              </div>
            </div>
          </div>
        </div>
        <div style="display:grid;gap:12px">
          <div style="background:#ffffff;border:1px solid #dfe6ee;border-radius:12px;padding:16px 18px">
            <div style="font-size:11.5px;color:#7d8b9c;margin-bottom:7px">Balance due</div>
            <div :style="'font-family:\'IBM Plex Mono\',monospace;font-size:27px;font-weight:600;letter-spacing:-0.025em;line-height:1;' + (s.due ? 'color:#b02020' : 'color:#0f6b43')">{{ money(s.due) }}</div>
            <div v-if="s.due_date && s.due" style="font-size:11.5px;color:#5d6b7d;margin-top:6px">Due {{ dmy(s.due_date) }}</div>
            <div style="height:7px;border-radius:99px;background:#fdeaea;margin-top:14px;overflow:hidden">
              <div :style="'height:100%;background:#2f9e63;width:' + paidPercent + '%'"></div>
            </div>
            <div style="display:grid;gap:9px;margin-top:14px">
              <div v-for="p in s.payments" :key="p.id" style="display:flex;align-items:baseline;gap:10px">
                <div style="flex:1;min-width:0">
                  <div style="font-size:12.5px;font-weight:500;text-transform:capitalize">{{ p.method }}</div>
                  <div style="font-family:'IBM Plex Mono',monospace;font-size:10.5px;color:#8b98a8">{{ dateTime(p.created_at) }}{{ p.account_name ? ' · ' + p.account_name : '' }}</div>
                </div>
                <div style="font-family:'IBM Plex Mono',monospace;font-size:13px;font-weight:600">{{ money(p.amount) }}</div>
              </div>
              <div v-if="!s.payments.length" style="font-size:12px;color:#9aa7b6">No payments yet.</div>
              <div v-if="s.status === 'cancelled' && s.payments.length" style="font-size:11.5px;color:#5d6b7d">These payments were taken back out of their accounts when the sale was cancelled.</div>
            </div>
          </div>
          <div v-if="s.tendered" style="background:#ffffff;border:1px solid #dfe6ee;border-radius:12px;padding:14px 18px;display:grid;gap:7px;font-size:12.5px">
            <div style="display:flex"><div style="flex:1;color:#5d6b7d">Cash handed over</div><div style="font-family:'IBM Plex Mono',monospace">{{ money(s.tendered) }}</div></div>
            <div style="display:flex"><div style="flex:1;color:#5d6b7d">Change given</div><div style="font-family:'IBM Plex Mono',monospace">{{ money(s.change_given) }}</div></div>
          </div>
          <div v-if="s.contact" style="background:#ffffff;border:1px solid #dfe6ee;border-radius:12px;padding:16px 18px">
            <div style="font-size:12.5px;font-weight:700;margin-bottom:12px">Customer</div>
            <div style="margin-bottom:12px;min-width:0">
              <RouterLink :to="{ name: 'contacts-details', query: { id: s.contact.id } }" style="font-size:13px;font-weight:600;color:var(--c-primary);text-decoration:none">{{ s.contact.name }}</RouterLink>
              <div style="font-size:11.5px;color:#7d8b9c">{{ s.contact.customer_group ? s.contact.customer_group.name : '' }}{{ s.contact.phone ? ' · ' + s.contact.phone : '' }}</div>
            </div>
            <div style="display:flex;align-items:baseline;gap:10px;padding-top:10px;border-top:1px solid #f0f4f8">
              <div style="font-size:12.5px;color:#5d6b7d;flex:1">Total owing, all invoices</div>
              <div :style="'font-family:\'IBM Plex Mono\',monospace;font-size:13px;font-weight:700;' + (s.contact.balance > 0 ? 'color:#b02020' : '')">{{ money(s.contact.balance) }}</div>
            </div>
            <div v-if="s.coins_used || s.coins_earned || s.contact.coins" style="display:grid;gap:6px;padding-top:10px;margin-top:10px;border-top:1px solid #f0f4f8;font-size:12.5px">
              <div v-if="s.coins_used" style="display:flex"><div style="flex:1;color:#5d6b7d">Coins spent on this sale</div><div style="font-family:'IBM Plex Mono',monospace">−{{ s.coins_used }}</div></div>
              <div v-if="s.coins_earned" style="display:flex"><div style="flex:1;color:#5d6b7d">Coins earned on this sale</div><div style="font-family:'IBM Plex Mono',monospace;color:#0f6b43">+{{ s.coins_earned }}</div></div>
              <div style="display:flex"><div style="flex:1;color:#5d6b7d">Coin balance now</div><div style="font-family:'IBM Plex Mono',monospace;font-weight:700">{{ s.contact.coins }}</div></div>
            </div>
          </div>
          <div v-if="error" style="background:#fdeaea;color:#b02020;border-radius:9px;padding:10px 13px;font-size:12.5px">{{ error }}</div>
        </div>
      </div>

      <template v-if="receiveOpen">
        <div @click="receiveOpen = false" style="position:fixed;inset:0;background:rgba(9,22,35,.48);z-index:30"></div>
        <div style="position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);width:400px;background:#ffffff;border-radius:16px;z-index:31;box-shadow:0 30px 80px -24px rgba(9,22,35,.65);padding:22px">
          <div style="font-size:17px;font-weight:700;margin-bottom:4px">Receive payment</div>
          <div style="font-size:12.5px;color:#5d6b7d;margin-bottom:16px">{{ s.customer_name }} · {{ s.ref }} · {{ money(s.due) }} due</div>
          <div style="display:flex;gap:6px;margin-bottom:12px">
            <div @click="receiveMethod = 'cash'" :style="methodStyle('cash')">Cash</div>
            <div @click="receiveMethod = 'card'" :style="methodStyle('card')">Card</div>
          </div>
          <label style="display:block;font-size:12px;font-weight:600;margin-bottom:5px">Amount</label>
          <input v-model="receiveAmount" style="width:100%;height:40px;border:1px solid #dfe6ee;border-radius:8px;padding:0 11px;font-family:'IBM Plex Mono',monospace;font-size:14px;text-align:right;outline:none" v-pseudo:focus="'border-color:var(--c-primary)'" />
          <div v-if="modalError" style="background:#fdeaea;color:#b02020;border-radius:9px;padding:10px 13px;font-size:12.5px;margin-top:12px">{{ modalError }}</div>
          <div style="display:flex;gap:9px;justify-content:flex-end;margin-top:18px">
            <div @click="receiveOpen = false" style="height:40px;padding:0 16px;border-radius:9px;border:1px solid #dfe6ee;display:flex;align-items:center;font-size:13px;font-weight:600;color:#4a5866;cursor:pointer" v-pseudo:hover="'background:#f2f6fa'">Close</div>
            <div @click="confirmReceive" style="height:40px;padding:0 16px;border-radius:9px;background:var(--c-primary);color:#ffffff;display:flex;align-items:center;font-size:13px;font-weight:700;cursor:pointer" v-pseudo:hover="'background:var(--c-primary-hover)'">{{ busy ? 'Saving…' : 'Receive payment' }}</div>
          </div>
        </div>
      </template>

      <template v-if="cancelOpen">
        <div @click="cancelOpen = false" style="position:fixed;inset:0;background:rgba(9,22,35,.48);z-index:30"></div>
        <div style="position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);width:420px;background:#ffffff;border-radius:16px;z-index:31;box-shadow:0 30px 80px -24px rgba(9,22,35,.65);padding:22px">
          <div style="font-size:17px;font-weight:700;margin-bottom:8px">Cancel {{ s.ref }}?</div>
          <div style="font-size:13px;line-height:1.6;color:#5d6b7d;margin-bottom:14px;text-wrap:pretty">{{ cancelText }}</div>
          <label style="display:block;font-size:12px;font-weight:600;margin-bottom:5px">Reason <span style="color:#d93a3a">*</span></label>
          <input v-model="cancelReason" maxlength="190" placeholder="e.g. Customer changed their mind" style="width:100%;height:38px;border:1px solid #dfe6ee;border-radius:8px;padding:0 11px;font-family:'Public Sans',sans-serif;font-size:13px;outline:none" v-pseudo:focus="'border-color:var(--c-primary)'" />
          <div v-if="modalError" style="background:#fdeaea;color:#b02020;border-radius:9px;padding:10px 13px;font-size:12.5px;margin-top:12px">{{ modalError }}</div>
          <div style="display:flex;gap:9px;justify-content:flex-end;margin-top:18px">
            <div @click="cancelOpen = false" style="height:40px;padding:0 16px;border-radius:9px;border:1px solid #dfe6ee;display:flex;align-items:center;font-size:13px;font-weight:600;color:#4a5866;cursor:pointer" v-pseudo:hover="'background:#f2f6fa'">Keep sale</div>
            <div @click="confirmCancel" style="height:40px;padding:0 16px;border-radius:9px;background:#c62828;color:#ffffff;display:flex;align-items:center;font-size:13px;font-weight:700;cursor:pointer" v-pseudo:hover="'background:#a81f1f'">{{ busy ? 'Cancelling…' : 'Cancel sale' }}</div>
          </div>
        </div>
      </template>
    </template>
  </div>
</template>

<script setup lang="ts">
// One sale (/sell/details?id=5).
// API: GET /api/sales/{id}, POST /api/sales/{id}/receive, POST /api/sales/{id}/cancel
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import { api } from '../../api/http.js';
import { can } from '../../api/auth.js';
import { qtyText } from '../../api/products.js';
import { dmy } from '../../api/purchases.js';
import { money, dateTime, statusBadge, STATUS_LABELS } from '../../api/sales.js';

const route = useRoute();

const s = ref<any>(null);
const loading = ref(true);
const busy = ref(false);
const error = ref('');
const modalError = ref('');

const receiveOpen = ref(false);
const receiveMethod = ref('cash');
const receiveAmount = ref('');
const cancelOpen = ref(false);
const cancelReason = ref('');

const id = computed(() => Number(route.query.id));
const canReceive = computed(() => (s.value.status === 'due' || s.value.status === 'partial') && (can('sell.create') || can('sell.edit')));
const canCancel = computed(() => ['paid', 'partial', 'due'].includes(s.value.status) && can('sell.edit'));
const paidPercent = computed(() => (s.value.total > 0 ? Math.min(100, s.value.paid / s.value.total * 100) : 0));

const totalRows = computed(() => {
  const x = s.value;
  const list = [{ k: 'Subtotal', v: money(x.subtotal), red: false }];
  if (x.group_discount) list.push({ k: 'Group discount ' + x.group_percent + '%', v: '−' + money(x.group_discount), red: true });
  if (x.discount) list.push({ k: (x.discount_type === 'amount' ? 'Discount' : 'Discount ' + x.discount_percent + '%') + (x.discount_approved_by ? ' · approved by ' + x.discount_approved_by : ''), v: '−' + money(x.discount), red: true });
  if (x.coins_used) list.push({ k: 'Coins used · ' + x.coins_used, v: '−' + money(x.coin_discount), red: true });
  if (x.delivery_fee) list.push({ k: 'Delivery', v: money(x.delivery_fee), red: false });
  if (x.card_surcharge) list.push({ k: 'Card surcharge', v: money(x.card_surcharge), red: false });
  if (x.rounding) list.push({ k: 'Cash rounding', v: (x.rounding > 0 ? '+' : '−') + money(Math.abs(x.rounding)), red: false });
  return list;
});

const cancelText = computed(() => {
  const x = s.value;
  const parts = [];
  if (x.items.some((l: any) => l.stock_taken)) parts.push('the stock goes back');
  if (x.paid) parts.push(money(x.paid) + ' comes back out of the payment accounts');
  if (x.due) parts.push(x.customer_name + ' no longer owes ' + money(x.due));
  if (x.coins_used) parts.push('the ' + x.coins_used + ' coins spent go back to ' + x.customer_name);
  if (x.coins_earned) parts.push('the ' + x.coins_earned + ' coins earned are taken off');
  return (parts.length ? 'If you cancel, ' + parts.join(', ') + '. ' : '') + 'The sale stays in the list, marked as cancelled.';
});

async function load() {
  error.value = '';
  if (!id.value) { s.value = null; loading.value = false; return; }
  loading.value = true;
  try {
    s.value = await api.get('/sales/' + id.value);
  } catch (e) {
    s.value = null;
  }
  loading.value = false;
}

onMounted(load);
watch(id, load);

function print() { window.print(); }

function openReceive() {
  modalError.value = '';
  receiveMethod.value = 'cash';
  receiveAmount.value = String(s.value.due);
  receiveOpen.value = true;
}

async function confirmReceive() {
  if (busy.value) return;
  modalError.value = '';
  const amount = parseFloat(receiveAmount.value);
  if (!(amount > 0)) { modalError.value = 'Enter an amount.'; return; }
  busy.value = true;
  try {
    s.value = await api.post('/sales/' + id.value + '/receive', { method: receiveMethod.value, amount });
    receiveOpen.value = false;
  } catch (e: any) {
    modalError.value = e.message;
  }
  busy.value = false;
}

function openCancel() {
  modalError.value = '';
  cancelReason.value = '';
  cancelOpen.value = true;
}

async function confirmCancel() {
  if (busy.value) return;
  modalError.value = '';
  if (!cancelReason.value.trim()) { modalError.value = 'Enter a reason.'; return; }
  busy.value = true;
  try {
    s.value = await api.post('/sales/' + id.value + '/cancel', { reason: cancelReason.value.trim() });
    cancelOpen.value = false;
  } catch (e: any) {
    modalError.value = e.message;
  }
  busy.value = false;
}

function methodStyle(method: string) {
  return 'flex:1;height:36px;border-radius:9px;display:grid;place-items:center;font-size:12.5px;cursor:pointer;'
    + (receiveMethod.value === method ? 'background:#0f1c2b;color:#ffffff;font-weight:700;' : 'border:1px solid #dfe6ee;color:#4a5866;font-weight:500;');
}
</script>
