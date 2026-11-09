<template>
  <template v-if="vm.puDetails">
    <div>
      <div v-if="!p" style="padding:24px;font-size:13px;color:#5d6b7d">
        {{ loading ? 'Loading…' : 'Purchase not found.' }}
        <span v-if="!loading" @click="vm.puGoList" style="color:var(--c-primary);cursor:pointer;margin-left:6px">Back to purchases</span>
      </div>
      <template v-else>
        <div style="padding:20px 24px 18px;background:#ffffff;border-bottom:1px solid #dfe6ee">
          <div style="display:flex;align-items:center;gap:7px;font-size:11.5px;color:#7d8b9c;margin-bottom:14px"><span @click="vm.puGoList" style="cursor:pointer;color:var(--c-primary)">Purchases</span><span style="color:#c2cedb">/</span><span style="color:#0f1c2b;font-weight:500">{{ p.ref }}</span></div>
          <div style="display:flex;align-items:flex-start;gap:16px;flex-wrap:wrap">
            <div style="min-width:0">
              <div style="display:flex;align-items:center;gap:9px;flex-wrap:wrap;margin-bottom:4px"><h2 style="margin:0;font-family:'IBM Plex Mono',monospace;font-size:22px;font-weight:600;letter-spacing:-0.02em">{{ p.ref }}</h2> <span :style="badge(p.status)">{{ STATUS_LABELS[p.status] || p.status }}</span> <span :style="badge(p.payment_status)">{{ PAY_LABELS[p.payment_status] }}</span></div>
              <div style="font-size:12.5px;color:#5d6b7d">{{ p.supplier.name }} · {{ dmy(p.date) }} · {{ received ? 'received into' : 'for' }} {{ p.branch.name }}{{ p.received_by ? ' by ' + p.received_by : '' }}</div>
            </div>
            <div style="margin-left:auto;display:flex;gap:8px;align-items:center;flex-wrap:wrap;justify-content:flex-end">
              <div @click="print" :style="buttonStyle" v-pseudo:hover="'background:#f2f6fa'">Print</div>
              <div v-if="!received && canDelete" @click="remove" :style="buttonStyle + ';color:#b02020'" v-pseudo:hover="'background:#fdf4f4'">Delete</div>
              <div v-if="received && can('purchases.create')" @click="openReturn" :style="buttonStyle" v-pseudo:hover="'background:#f2f6fa'">Return items</div>
              <div v-if="!received && can('purchases.edit')" @click="openEdit" :style="buttonStyle" v-pseudo:hover="'background:#f2f6fa'">Edit</div>
              <div v-if="!received && can('purchases.edit')" @click="receive" :style="buttonStyle + ';font-weight:600;color:var(--c-primary-text)'" v-pseudo:hover="'background:#f2f6fa'">{{ busy ? 'Receiving…' : 'Receive stock' }}</div>
              <div v-if="p.due > 0 && can('purchases.edit')" @click="openPay" style="height:34px;padding:0 15px;border-radius:8px;background:var(--c-primary);color:#ffffff;display:flex;align-items:center;font-size:12.5px;font-weight:600;cursor:pointer" v-pseudo:hover="'background:var(--c-primary-hover)'">Pay {{ money(p.due) }}</div>
            </div>
          </div>
          <div style="display:flex;align-items:center;gap:0;margin-top:18px;max-width:760px">
            <div v-for="(st, i) in steps" :key="st.name" style="display:flex;align-items:center;flex:1;min-width:0">
              <div style="display:flex;align-items:center;gap:8px;flex:none">
                <div :style="stepDot(st.done)">{{ st.done ? '✓' : i + 1 }}</div>
                <div>
                  <div style="font-size:12px;font-weight:600">{{ st.name }}</div>
                  <div style="font-family:'IBM Plex Mono',monospace;font-size:10.5px;color:#8b98a8">{{ st.when }}</div>
                </div>
              </div>
              <div v-if="i < steps.length - 1" :style="'flex:1;height:2px;margin:0 12px;border-radius:2px;min-width:16px;background:' + (steps[i + 1].done ? '#2f9e63' : '#e4eaf1')"></div>
            </div>
          </div>
        </div>
        <div style="padding:20px 24px 32px;display:grid;grid-template-columns:1fr 320px;gap:16px;align-items:start">
          <div style="display:grid;gap:14px">
            <div style="background:#ffffff;border:1px solid #dfe6ee;border-radius:12px;overflow:hidden">
              <div style="padding:14px 18px;border-bottom:1px solid #eaeff5;font-size:13px;font-weight:700">Lines</div>
              <div style="display:grid;grid-template-columns:2fr 0.8fr 0.9fr 0.9fr 1fr;gap:12px;padding:9px 18px;background:#f6f9fc;border-bottom:1px solid #dfe6ee;font-family:'IBM Plex Mono',monospace;font-size:9.5px;letter-spacing:0.07em;text-transform:uppercase;color:#5d6b7d">
                <div>Product</div>
                <div style="text-align:right">Qty</div>
                <div style="text-align:right">Unit cost</div>
                <div style="text-align:right">Stock after</div>
                <div style="text-align:right">Line total</div>
              </div>
              <div v-for="l in p.items" :key="l.id" style="display:grid;grid-template-columns:2fr 0.8fr 0.9fr 0.9fr 1fr;gap:12px;padding:11px 18px;border-bottom:1px solid #f0f4f8;align-items:center">
                <div>
                  <div style="font-size:13px;font-weight:500">{{ l.name }}</div>
                  <div v-if="l.discount || l.returned" style="font-size:10.5px;color:#b45309">{{ l.discount ? qtyText(l.discount) + '% off' : '' }}{{ l.discount && l.returned ? ' · ' : '' }}{{ l.returned ? qtyText(l.returned) + ' returned' : '' }}</div>
                </div>
                <div style="font-family:'IBM Plex Mono',monospace;font-size:12.5px;text-align:right">{{ qtyText(l.qty) }} {{ l.unit }}</div>
                <div style="font-family:'IBM Plex Mono',monospace;font-size:12.5px;text-align:right;color:#4a5866">{{ money(l.cost) }}</div>
                <div style="text-align:right"><span v-if="l.stock_after !== null" style="font-family:'IBM Plex Mono',monospace;font-size:12px;font-weight:600;border-radius:5px;padding:3px 7px;background:#e7f6ee;color:#0f6b43">{{ qtyText(l.stock_after) }} {{ l.unit }}</span><span v-else style="color:#9aa7b6">—</span></div>
                <div style="font-family:'IBM Plex Mono',monospace;font-size:12.5px;text-align:right;font-weight:600">{{ money(l.line_total) }}</div>
              </div>
              <div style="padding:13px 18px;display:grid;gap:7px;background:#fbfcfe">
                <div style="display:flex;gap:10px;font-size:12.5px">
                  <div style="flex:1;color:#5d6b7d">Subtotal</div>
                  <div style="font-family:'IBM Plex Mono',monospace">{{ money(p.subtotal) }}</div>
                </div>
                <div v-if="p.order_discount" style="display:flex;gap:10px;font-size:12.5px">
                  <div style="flex:1;color:#5d6b7d">Order discount</div>
                  <div style="font-family:'IBM Plex Mono',monospace;color:#b02020">−{{ money(p.order_discount) }}</div>
                </div>
                <div v-if="p.freight" style="display:flex;gap:10px;font-size:12.5px">
                  <div style="flex:1;color:#5d6b7d">Freight</div>
                  <div style="font-family:'IBM Plex Mono',monospace">{{ money(p.freight) }}</div>
                </div>
                <div style="display:flex;gap:10px;font-size:12.5px">
                  <div style="flex:1;color:#5d6b7d">GST {{ qtyText(p.tax_rate) }}%</div>
                  <div style="font-family:'IBM Plex Mono',monospace">{{ money(p.tax) }}</div>
                </div>
                <div style="display:flex;gap:10px;font-size:14px;font-weight:700;padding-top:7px;border-top:1px solid #eaeff5">
                  <div style="flex:1">Total</div>
                  <div style="font-family:'IBM Plex Mono',monospace">{{ money(p.total) }}</div>
                </div>
              </div>
            </div>
            <div v-if="p.returns.length" style="background:#ffffff;border:1px solid #dfe6ee;border-radius:12px;overflow:hidden">
              <div style="padding:14px 18px;border-bottom:1px solid #eaeff5;font-size:13px;font-weight:700">Returns</div>
              <div v-for="r in p.returns" :key="r.id" style="display:flex;align-items:center;gap:12px;padding:11px 18px;border-bottom:1px solid #f0f4f8">
                <div style="font-family:'IBM Plex Mono',monospace;font-size:12.5px;font-weight:500">{{ r.ref }}</div>
                <div style="font-size:12px;color:#5d6b7d;flex:1">{{ dmy(r.date) }} · {{ r.settlement }}</div>
                <span :style="badge(r.status)">{{ r.status }}</span>
                <div style="font-family:'IBM Plex Mono',monospace;font-size:12.5px;font-weight:600">{{ money(r.credit) }}</div>
              </div>
            </div>
            <div style="background:#ffffff;border:1px solid #dfe6ee;border-radius:12px;overflow:hidden">
              <div style="padding:14px 18px;border-bottom:1px solid #eaeff5;font-size:13px;font-weight:700">Journal preview</div>
              <div style="display:grid;grid-template-columns:1.6fr 1fr 1fr;gap:12px;padding:9px 18px;background:#f6f9fc;border-bottom:1px solid #dfe6ee;font-family:'IBM Plex Mono',monospace;font-size:9.5px;letter-spacing:0.07em;text-transform:uppercase;color:#5d6b7d">
                <div>Account</div>
                <div style="text-align:right">Debit</div>
                <div style="text-align:right">Credit</div>
              </div>
              <div v-for="j in journal" :key="j.code" style="display:grid;grid-template-columns:1.6fr 1fr 1fr;gap:12px;padding:10px 18px;border-bottom:1px solid #f0f4f8;align-items:center">
                <div style="font-size:12.5px"><span style="font-family:'IBM Plex Mono',monospace;font-size:11px;color:#8b98a8;margin-right:8px">{{ j.code }}</span>{{ j.name }}</div>
                <div style="font-family:'IBM Plex Mono',monospace;font-size:12.5px;text-align:right">{{ j.dr ? money(j.dr) : '' }}</div>
                <div style="font-family:'IBM Plex Mono',monospace;font-size:12.5px;text-align:right">{{ j.cr ? money(j.cr) : '' }}</div>
              </div>
              <div style="padding:11px 18px;font-size:12px;color:#7d8b9c;background:#fbfcfe">Worked out from this purchase. It is not posted to Accounting yet.</div>
            </div>
          </div>
          <div style="display:grid;gap:12px">
            <div style="background:#ffffff;border:1px solid #dfe6ee;border-radius:12px;padding:16px 18px">
              <div style="font-size:11.5px;color:#7d8b9c;margin-bottom:7px">Due to supplier</div>
              <div :style="'font-family:\'IBM Plex Mono\',monospace;font-size:27px;font-weight:600;letter-spacing:-0.025em;line-height:1;' + (p.due > 0 ? 'color:#b02020' : 'color:#0f6b43')">{{ money(p.due) }}</div>
              <div v-if="p.due > 0 && p.due_date" style="font-size:11.5px;color:#5d6b7d;margin-top:6px">Due {{ dmy(p.due_date) }}{{ p.supplier.pay_term ? ' · ' + p.supplier.pay_term : '' }}</div>
              <div style="height:7px;border-radius:99px;background:#fdeaea;margin:14px 0 0;overflow:hidden">
                <div :style="'height:100%;background:#2f9e63;width:' + paidPercent + '%'"></div>
              </div>
              <div style="display:flex;justify-content:space-between;font-family:'IBM Plex Mono',monospace;font-size:10.5px;color:#7d8b9c;margin-top:6px"><span>{{ money(p.paid) }} paid</span><span>{{ money(p.total) }}</span></div>
              <div v-if="p.credit_applied" style="font-size:11.5px;color:#5d6b7d;margin-top:6px">{{ money(p.credit_applied) }} taken off by returns</div>
            </div>
            <div style="background:#ffffff;border:1px solid #dfe6ee;border-radius:12px;padding:16px 18px">
              <div style="font-size:12.5px;font-weight:700;margin-bottom:12px">Payments</div>
              <div v-if="!p.payments.length" style="font-size:12px;color:#7d8b9c">No payments yet.</div>
              <div style="display:grid;gap:11px">
                <div v-for="pp in p.payments" :key="pp.id" style="display:flex;align-items:baseline;gap:10px">
                  <div style="flex:1;min-width:0">
                    <div style="font-size:12.5px;font-weight:500">{{ pp.account_name }}</div>
                    <div style="font-family:'IBM Plex Mono',monospace;font-size:10.5px;color:#8b98a8">{{ dmy(pp.date) }}{{ pp.user_name ? ' · ' + pp.user_name : '' }}</div>
                  </div>
                  <div style="font-family:'IBM Plex Mono',monospace;font-size:13px;font-weight:600">{{ money(pp.amount) }}</div>
                </div>
              </div>
            </div>
            <div v-if="p.notes" style="background:#ffffff;border:1px solid #dfe6ee;border-radius:12px;padding:16px 18px">
              <div style="font-size:12.5px;font-weight:700;margin-bottom:8px">Notes</div>
              <div style="font-size:12.5px;color:#4a5866;line-height:1.5;white-space:pre-wrap">{{ p.notes }}</div>
            </div>
            <div style="background:#ffffff;border:1px solid #dfe6ee;border-radius:12px;padding:16px 18px">
              <div style="font-size:12.5px;font-weight:700;margin-bottom:10px">Supplier invoice</div>
              <a v-if="p.invoice_url" :href="p.invoice_url" target="_blank" style="display:flex;align-items:center;gap:10px;border:1px solid #e4eaf1;border-radius:9px;padding:9px 11px;margin-bottom:8px">
                <div style="width:30px;height:30px;border-radius:7px;background:#eef2f6;display:grid;place-items:center;font-family:'IBM Plex Mono',monospace;font-size:8px;color:#8b98a8;flex:none">FILE</div>
                <div style="font-size:12.5px;font-weight:500;flex:1;min-width:0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">{{ fileName }}</div>
              </a>
              <label v-if="can('purchases.edit')" style="font-size:12px;font-weight:600;color:var(--c-primary);cursor:pointer">{{ p.invoice_url ? 'Replace file' : 'Attach a file' }}<input type="file" accept=".pdf,.png,.jpg,.jpeg" @change="uploadFile" style="display:none" /></label>
            </div>
          </div>
        </div>

        <!-- Pay the supplier -->
        <template v-if="pay.open">
          <div @click="pay.open = false" style="position:absolute;inset:0;background:rgba(9,22,35,.42);z-index:20"></div>
          <form @submit.prevent="savePayment" style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);width:400px;background:#ffffff;border-radius:14px;z-index:21;box-shadow:0 24px 60px -20px rgba(15,28,43,.5);padding:20px 22px;display:grid;gap:13px">
            <div>
              <div style="font-size:16px;font-weight:700">Pay {{ p.supplier.name }}</div>
              <div style="font-size:12px;color:#5d6b7d;margin-top:2px">{{ p.ref }} · {{ money(p.due) }} due</div>
            </div>
            <div v-if="pay.error" style="background:#fdeaea;color:#b02020;border-radius:9px;padding:9px 12px;font-size:12.5px">{{ pay.error }}</div>
            <label style="display:grid;gap:5px;font-size:12px;font-weight:600">Amount <input v-model.number="pay.amount" type="number" min="0" step="0.01" :style="fieldStyle" /></label>
            <label style="display:grid;gap:5px;font-size:12px;font-weight:600">From account
              <select v-model="pay.account" :style="fieldStyle">
                <option v-for="a in accounts" :key="a.id" :value="a.id">{{ a.name }} ({{ money(a.bal) }})</option>
              </select>
            </label>
            <label style="display:grid;gap:5px;font-size:12px;font-weight:600">Date <input v-model="pay.date" type="date" :style="fieldStyle" /></label>
            <label style="display:grid;gap:5px;font-size:12px;font-weight:600">Note <input v-model="pay.note" placeholder="Optional" :style="fieldStyle" /></label>
            <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:4px">
              <div @click="pay.open = false" :style="buttonStyle" v-pseudo:hover="'background:#f2f6fa'">Cancel</div>
              <button type="submit" :disabled="busy" style="height:34px;padding:0 15px;border:0;border-radius:8px;background:var(--c-primary);color:#ffffff;font-family:inherit;font-size:12.5px;font-weight:600;cursor:pointer">{{ busy ? 'Saving…' : 'Save payment' }}</button>
            </div>
          </form>
        </template>
      </template>
    </div>
  </template>
</template>

<script setup lang="ts">
// Purchases > one purchase (/purchases/details?id=5).
// API: GET /api/purchases/{id}, POST /api/purchases/{id}/receive, POST /api/purchases/{id}/payments,
// POST /api/purchases/{id}/invoice, DELETE /api/purchases/{id}
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { usePageVm } from '../../erp/useErpPage';
import { api } from '../../api/http.js';
import { can } from '../../api/auth.js';
import { money, badge, dmy, qtyText, today, defaultAccountId, STATUS_LABELS, PAY_LABELS } from '../../api/purchases.js';

const vm = usePageVm();
const route = useRoute();
const router = useRouter();

const buttonStyle = 'height:34px;padding:0 13px;border-radius:8px;border:1px solid #dfe6ee;display:flex;align-items:center;font-size:12.5px;font-weight:500;color:#4a5866;cursor:pointer';
const fieldStyle = "height:36px;border:1px solid #dfe6ee;border-radius:8px;padding:0 11px;font-family:'Public Sans',sans-serif;font-size:13px;font-weight:400;outline:none;background:#ffffff";

const p = ref<any>(null);
const loading = ref(true);
const busy = ref(false);
const accounts = ref<any[]>([]);
const pay = reactive({ open: false, amount: 0, account: '', date: today(), note: '', error: '' });

const id = computed(() => Number(route.query.id));
const received = computed(() => p.value.status === 'received');
const canDelete = computed(() => can('purchases.delete') && !p.value.payments.length);
const paidPercent = computed(() => (p.value.total > 0 ? Math.min(100, ((p.value.paid + p.value.credit_applied) / p.value.total) * 100) : 0));
const fileName = computed(() => (p.value.invoice_file ? p.value.invoice_file.split('/').pop() : ''));

const steps = computed(() => [
  { name: 'Ordered', when: dmy(p.value.date), done: true },
  { name: 'Received', when: p.value.received_at ? dmy(p.value.received_at) : 'waiting', done: received.value },
  { name: 'Paid', when: p.value.due > 0 ? money(p.value.due) + ' due' : 'in full', done: p.value.due <= 0 }
]);

// The entry this purchase makes in the books. Freight is part of the inventory cost.
const journal = computed(() => [
  { code: '1-1400', name: 'Inventory', dr: p.value.subtotal - p.value.order_discount + p.value.freight, cr: 0 },
  { code: '2-1310', name: 'GST paid', dr: p.value.tax, cr: 0 },
  { code: '2-1100', name: 'Accounts payable · ' + p.value.supplier.name, dr: 0, cr: p.value.total }
]);

function stepDot(done: boolean) {
  return 'width:24px;height:24px;border-radius:50%;display:grid;place-items:center;font-size:11px;font-weight:700;flex:none;'
    + (done ? 'background:#2f9e63;color:#ffffff' : 'background:#ffffff;color:#b45309;box-shadow:inset 0 0 0 2px #d98c1a');
}

async function load() {
  if (!id.value) { p.value = null; loading.value = false; return; }
  loading.value = true;
  try {
    p.value = await api.get('/purchases/' + id.value);
  } catch (e) {
    p.value = null;
  }
  loading.value = false;
}

onMounted(load);
watch(id, load);

function print() { window.print(); }
function openEdit() { router.push({ name: 'purchases-new', query: { id: id.value } }); }
function openReturn() { router.push({ name: 'purchases-return-new', query: { purchase: id.value } }); }

async function receive() {
  if (busy.value) return;
  if (!confirm('Receive ' + p.value.ref + ' into ' + p.value.branch.name + '? The stock is added now.')) return;
  busy.value = true;
  try {
    p.value = await api.post('/purchases/' + id.value + '/receive');
  } catch (e) { /* the error bar shows why */ }
  busy.value = false;
}

async function remove() {
  if (!confirm('Delete ' + p.value.ref + '? This cannot be undone.')) return;
  try {
    await api.del('/purchases/' + id.value);
    vm.value.puGoList();
  } catch (e) { /* the error bar shows why */ }
}

async function openPay() {
  pay.amount = p.value.due;
  pay.date = today();
  pay.note = '';
  pay.error = '';
  pay.open = true;
  if (!accounts.value.length) {
    const data = await api.get('/purchases/form-data').catch(() => null);
    if (data) {
      accounts.value = data.payment_accounts;
      pay.account = defaultAccountId(accounts.value, p.value.branch_id);
    }
  }
}

async function savePayment() {
  pay.error = '';
  if (!pay.account) { pay.error = 'Pick the account the money comes from.'; return; }
  busy.value = true;
  try {
    await api.post('/purchases/' + id.value + '/payments', { amount: pay.amount, payment_account_id: pay.account, date: pay.date, note: pay.note || null });
    pay.open = false;
    await load();
  } catch (e: any) {
    pay.error = e.message;
  }
  busy.value = false;
}

async function uploadFile(e: Event) {
  const files = (e.target as HTMLInputElement).files;
  if (!files || !files.length) return;
  const data = new FormData();
  data.append('file', files[0]);
  try {
    p.value = await api.post('/purchases/' + id.value + '/invoice', data);
  } catch (err) { /* the error bar shows why */ }
}
</script>
