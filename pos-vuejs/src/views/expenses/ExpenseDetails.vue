<template>
  <div>
    <div v-if="!e" style="padding:24px;font-size:13px;color:#5d6b7d">
      {{ loading ? 'Loading…' : 'Expense not found.' }}
      <RouterLink v-if="!loading" :to="{ name: 'expenses' }" style="margin-left:6px">Back to expenses</RouterLink>
    </div>
    <template v-else>
      <div style="padding:20px 24px 16px;background:#ffffff;border-bottom:1px solid #dfe6ee">
        <div style="display:flex;align-items:center;gap:7px;font-size:11.5px;color:#7d8b9c;margin-bottom:8px"><RouterLink :to="{ name: 'expenses' }" style="color:var(--c-primary)">Expenses</RouterLink><span style="color:#c2cedb">/</span><span style="color:#0f1c2b;font-weight:500;font-family:'IBM Plex Mono',monospace">{{ e.ref }}</span></div>
        <div style="display:flex;align-items:flex-start;gap:20px;flex-wrap:wrap">
          <div>
            <div style="display:flex;align-items:center;gap:10px;margin-bottom:3px"><h2 style="margin:0;font-size:22px;font-weight:700;letter-spacing:-0.018em">{{ e.payee }}</h2> <span :style="statusBadge(e)">{{ statusText(e) }}</span></div>
            <div style="font-size:12.5px;color:#5d6b7d">{{ e.ref }} · {{ e.category.name }} · {{ dmy(e.date) }} · {{ e.branch.name }}</div>
          </div>
          <div style="margin-left:auto;display:flex;gap:8px;align-items:center">
            <div v-if="can('expenses.delete')" @click="deleteOpen = true" style="height:34px;padding:0 13px;border-radius:8px;border:1px solid #f3cfcf;background:#fdf4f4;display:flex;align-items:center;font-size:12.5px;font-weight:600;color:#b02020;cursor:pointer" v-pseudo:hover="'background:#fdeaea'">Delete</div>
            <RouterLink v-if="can('expenses.edit')" :to="{ name: 'expenses-new', query: { id: e.id } }" style="height:34px;padding:0 13px;border-radius:8px;border:1px solid #dfe6ee;display:flex;align-items:center;font-size:12.5px;font-weight:600;color:var(--c-primary-text);cursor:pointer;text-decoration:none" v-pseudo:hover="'background:#f2f6fa'">Edit</RouterLink>
            <div v-if="!e.payment_account_id && can('expenses.edit')" @click="openPay" style="height:34px;padding:0 15px;border-radius:8px;background:var(--c-primary);color:#ffffff;display:flex;align-items:center;font-size:12.5px;font-weight:600;cursor:pointer" v-pseudo:hover="'background:var(--c-primary-hover)'">Mark as paid</div>
          </div>
        </div>
      </div>
      <div style="padding:20px 24px 32px;display:grid;grid-template-columns:minmax(0,1fr) 330px;gap:16px;align-items:start">
        <div style="display:grid;gap:14px">
          <div v-if="error" style="background:#fdeaea;color:#b02020;border-radius:9px;padding:10px 13px;font-size:12.5px">{{ error }}</div>
          <div style="background:#ffffff;border:1px solid #dfe6ee;border-radius:12px;padding:20px 22px;display:grid;grid-template-columns:auto 1fr;gap:26px;align-items:center">
            <div>
              <div style="font-size:11.5px;color:#7d8b9c;margin-bottom:2px">Amount</div>
              <div style="font-family:'IBM Plex Mono',monospace;font-size:32px;font-weight:600;letter-spacing:-0.03em">{{ money(e.amount) }}</div>
              <div style="font-size:11.5px;color:#7d8b9c;margin-top:2px">{{ e.has_gst ? 'includes GST ' + money(e.gst_amount) : 'no GST' }}</div>
            </div>
            <div style="display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px;border-left:1px solid #eaeff5;padding-left:26px">
              <div v-for="fact in facts" :key="fact.k" style="min-width:0">
                <div style="font-family:'IBM Plex Mono',monospace;font-size:9.5px;letter-spacing:.07em;text-transform:uppercase;color:#7d8b9c;margin-bottom:3px">{{ fact.k }}</div>
                <div style="font-size:13px;font-weight:500;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">{{ fact.v }}</div>
              </div>
            </div>
          </div>
          <div style="background:#ffffff;border:1px solid #dfe6ee;border-radius:12px;padding:17px 19px">
            <div style="font-size:13px;font-weight:700;margin-bottom:12px">Receipt</div>
            <div v-if="e.receipt_url" style="display:grid;grid-template-columns:180px 1fr;gap:16px;align-items:start">
              <img v-if="!isPdf" :src="e.receipt_url" alt="Receipt" style="width:180px;max-height:220px;object-fit:contain;border-radius:9px;border:1px solid #e4eaf1" />
              <div v-else style="height:220px;border-radius:9px;border:1px solid #e4eaf1;background:repeating-linear-gradient(135deg,#f6f9fc 0 8px,#eef2f6 8px 16px);display:grid;place-items:center;font-family:'IBM Plex Mono',monospace;font-size:10.5px;color:#7d8b9c;text-align:center;padding:10px">PDF</div>
              <div>
                <div style="font-size:13px;font-weight:500;overflow-wrap:anywhere">{{ e.receipt_name }}</div>
                <a :href="e.receipt_url" target="_blank" rel="noopener" style="margin-top:12px;display:inline-flex;height:32px;padding:0 12px;border-radius:8px;border:1px solid #dfe6ee;align-items:center;font-size:12.5px;font-weight:600;color:#4a5866;cursor:pointer;text-decoration:none" v-pseudo:hover="'background:#f2f6fa'">Download</a>
              </div>
            </div>
            <div v-else style="display:flex;align-items:center;gap:12px;background:#fdf1dd;border:1px solid #f0d9ac;border-radius:10px;padding:11px 14px">
              <div style="font-size:12.5px;color:#8a5300;flex:1">{{ e.has_gst && e.amount > 82.5 ? 'No receipt attached. You need the tax invoice to claim ' + money(e.gst_amount) + ' GST.' : 'No receipt attached.' }}</div>
              <label v-if="can('expenses.create') || can('expenses.edit')" style="height:30px;padding:0 12px;border-radius:7px;background:#ffffff;border:1px solid #f0d9ac;display:flex;align-items:center;font-size:12px;font-weight:600;color:#8a5300;cursor:pointer">
                {{ uploading ? 'Uploading…' : 'Attach receipt' }}
                <input type="file" accept=".pdf,.png,.jpg,.jpeg" @change="attachReceipt" style="display:none" />
              </label>
            </div>
          </div>
          <div v-if="e.notes" style="background:#ffffff;border:1px solid #dfe6ee;border-radius:12px;padding:15px 19px">
            <div style="font-size:12px;font-weight:600;margin-bottom:5px">Notes</div>
            <div style="font-size:13px;color:#4a5866;line-height:1.55;white-space:pre-wrap">{{ e.notes }}</div>
          </div>
        </div>
        <div style="background:#ffffff;border:1px solid #dfe6ee;border-radius:12px;padding:17px 19px">
          <div style="font-size:13px;font-weight:700;margin-bottom:12px">Activity</div>
          <div style="display:grid;gap:12px">
            <div v-for="log in e.logs" :key="log.id" style="display:flex;gap:10px">
              <div :style="'width:8px;height:8px;border-radius:50%;margin-top:5px;flex:none;background:' + (LOG_COLORS[log.kind] || LOG_COLORS.neutral)"></div>
              <div>
                <div style="font-size:12.5px;line-height:1.45">{{ log.what }}</div>
                <div style="font-size:11px;color:#9aa7b6;margin-top:1px">{{ when(log.created_at) }}</div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <template v-if="payOpen">
        <div @click="payOpen = false" style="position:fixed;inset:0;background:rgba(9,22,35,.48);z-index:30"></div>
        <div style="position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);width:440px;background:#ffffff;border-radius:16px;z-index:31;box-shadow:0 30px 80px -24px rgba(9,22,35,.65);padding:22px">
          <div style="font-size:17px;font-weight:700;margin-bottom:4px">Mark as paid</div>
          <div style="font-size:12.5px;color:#5d6b7d;margin-bottom:16px">{{ e.payee }} · {{ e.ref }}{{ e.due_date ? ' · due ' + dmy(e.due_date) : '' }}</div>
          <div style="display:grid;gap:7px;margin-bottom:18px">
            <div v-for="a in accounts" :key="a.id" @click="payAccount = a.id" :style="accountCardStyle(payAccount === a.id)">
              <div :style="accountRadioStyle(payAccount === a.id)"></div>
              <div style="flex:1;min-width:0">
                <div style="font-size:13px;font-weight:600">{{ a.name }}</div>
                <div style="font-size:11px;color:#7d8b9c">{{ accountType(a) }}</div>
              </div>
              <div style="text-align:right">
                <div style="font-family:'IBM Plex Mono',monospace;font-size:12.5px;font-weight:600">{{ balance(a.bal, money) }}</div>
                <div v-if="payAccount === a.id" :style="'font-family:\'IBM Plex Mono\',monospace;font-size:10.5px;' + (a.kind !== 'card' && a.bal - e.amount < 0 ? 'color:#b02020;font-weight:600;' : 'color:#7d8b9c;')">after {{ balance(a.bal - e.amount, money) }}</div>
              </div>
            </div>
          </div>
          <div v-if="modalError" style="background:#fdeaea;color:#b02020;border-radius:9px;padding:10px 13px;font-size:12.5px;margin-bottom:12px">{{ modalError }}</div>
          <div style="display:flex;gap:9px;justify-content:flex-end">
            <div @click="payOpen = false" style="height:40px;padding:0 16px;border-radius:9px;border:1px solid #dfe6ee;display:flex;align-items:center;font-size:13px;font-weight:600;color:#4a5866;cursor:pointer" v-pseudo:hover="'background:#f2f6fa'">Cancel</div>
            <div @click="confirmPay" style="height:40px;padding:0 16px;border-radius:9px;background:var(--c-primary);color:#ffffff;display:flex;align-items:center;font-size:13px;font-weight:700;cursor:pointer" v-pseudo:hover="'background:var(--c-primary-hover)'">{{ busy ? 'Paying…' : 'Pay ' + money(e.amount) }}</div>
          </div>
        </div>
      </template>

      <template v-if="deleteOpen">
        <div @click="deleteOpen = false" style="position:fixed;inset:0;background:rgba(9,22,35,.48);z-index:30"></div>
        <div style="position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);width:400px;background:#ffffff;border-radius:16px;z-index:31;box-shadow:0 30px 80px -24px rgba(9,22,35,.65);padding:22px">
          <div style="font-size:17px;font-weight:700;margin-bottom:8px">Delete this expense?</div>
          <div style="font-size:13px;line-height:1.6;color:#5d6b7d;margin-bottom:20px;text-wrap:pretty">{{ e.ref }} for {{ money(e.amount) }} will be removed from the list{{ e.payment_account ? ', and the amount goes back into ' + e.payment_account.name : '' }}.</div>
          <div v-if="modalError" style="background:#fdeaea;color:#b02020;border-radius:9px;padding:10px 13px;font-size:12.5px;margin-bottom:12px">{{ modalError }}</div>
          <div style="display:flex;gap:9px;justify-content:flex-end">
            <div @click="deleteOpen = false" style="height:40px;padding:0 16px;border-radius:9px;border:1px solid #dfe6ee;display:flex;align-items:center;font-size:13px;font-weight:600;color:#4a5866;cursor:pointer" v-pseudo:hover="'background:#f2f6fa'">Keep it</div>
            <div @click="confirmDelete" style="height:40px;padding:0 16px;border-radius:9px;background:#c62828;color:#ffffff;display:flex;align-items:center;font-size:13px;font-weight:700;cursor:pointer" v-pseudo:hover="'background:#a81f1f'">{{ busy ? 'Deleting…' : 'Delete' }}</div>
          </div>
        </div>
      </template>
    </template>
  </div>
</template>

<script setup lang="ts">
// One expense (/expenses/details?id=5).
// API: GET and DELETE /api/expenses/{id}, POST /api/expenses/{id}/pay, POST /api/expenses/{id}/receipt
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { api } from '../../api/http.js';
import { can } from '../../api/auth.js';
import { money, dmy, statusText, statusBadge, balance, accountType, defaultAccountId, accountCardStyle, accountRadioStyle } from '../../api/expenses.js';

const route = useRoute();
const router = useRouter();

const LOG_COLORS: Record<string, string> = { neutral: '#8fa3b6', info: 'var(--c-primary)', success: '#2f9e63' };

const e = ref<any>(null);
const accounts = ref<any[]>([]);
const payAccount = ref('');
const payOpen = ref(false);
const deleteOpen = ref(false);
const loading = ref(true);
const busy = ref(false);
const uploading = ref(false);
const error = ref('');
const modalError = ref('');

const id = computed(() => Number(route.query.id));
const isPdf = computed(() => /\.pdf$/i.test(e.value.receipt_name || ''));

const facts = computed(() => [
  { k: 'Category', v: e.value.category.name },
  { k: 'Date', v: dmy(e.value.date) },
  { k: 'Location', v: e.value.branch.name },
  e.value.payment_account
    ? { k: 'Paid from', v: e.value.payment_account.name }
    : { k: 'Due', v: e.value.due_date ? dmy(e.value.due_date) : '—' },
  { k: 'Reference', v: e.value.invoice_no || '—' },
  { k: 'Added by', v: e.value.created_by || '—' }
]);

// '2026-09-27T09:12:00.000000Z' -> '27/09/2026 · 09:12' in the browser's time.
function when(iso: string) {
  const d = new Date(iso);
  return String(d.getDate()).padStart(2, '0') + '/' + String(d.getMonth() + 1).padStart(2, '0') + '/' + d.getFullYear()
    + ' · ' + String(d.getHours()).padStart(2, '0') + ':' + String(d.getMinutes()).padStart(2, '0');
}

async function load() {
  error.value = route.query.receipt === 'failed' ? 'The expense was saved, but the receipt did not upload. Attach it again below.' : '';
  if (!id.value) { e.value = null; loading.value = false; return; }
  loading.value = true;
  try {
    e.value = await api.get('/expenses/' + id.value);
  } catch (err) {
    e.value = null;
  }
  loading.value = false;
}

onMounted(load);
watch(id, load);

async function openPay() {
  modalError.value = '';
  payOpen.value = true;
  try {
    const data = await api.get('/expenses/form-data');
    accounts.value = data.accounts;
    payAccount.value = defaultAccountId(data.accounts, e.value ? e.value.branch_id : null);
  } catch (err: any) {
    modalError.value = err.message;
  }
}

async function confirmPay() {
  if (busy.value || !payAccount.value) return;
  busy.value = true;
  modalError.value = '';
  try {
    e.value = await api.post('/expenses/' + id.value + '/pay', { payment_account_id: payAccount.value });
    payOpen.value = false;
  } catch (err: any) {
    modalError.value = err.message;
  }
  busy.value = false;
}

async function confirmDelete() {
  if (busy.value) return;
  busy.value = true;
  modalError.value = '';
  try {
    await api.del('/expenses/' + id.value);
    router.push({ name: 'expenses' });
  } catch (err: any) {
    modalError.value = err.message;
  }
  busy.value = false;
}

async function attachReceipt(event: Event) {
  const files = (event.target as HTMLInputElement).files;
  if (!files || !files.length || uploading.value) return;
  error.value = '';
  uploading.value = true;
  try {
    const data = new FormData();
    data.append('file', files[0]);
    e.value = await api.post('/expenses/' + id.value + '/receipt', data);
  } catch (err: any) {
    error.value = err.message;
  }
  uploading.value = false;
}
</script>
