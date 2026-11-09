<template>
  <div>
    <div v-if="loading" style="padding:24px;font-size:13px;color:#5d6b7d">Loading…</div>
    <template v-else>
      <div style="padding:20px 24px 16px;background:#ffffff;border-bottom:1px solid #dfe6ee">
        <div style="display:flex;align-items:center;gap:7px;font-size:11.5px;color:#7d8b9c;margin-bottom:8px"><RouterLink :to="{ name: 'expenses' }" style="color:var(--c-primary)">Expenses</RouterLink><span style="color:#c2cedb">/</span><span style="color:#0f1c2b;font-weight:500">{{ title }}</span></div>
        <div style="display:flex;align-items:flex-start;gap:20px;flex-wrap:wrap">
          <div>
            <h2 style="margin:0 0 3px;font-size:22px;font-weight:700;letter-spacing:-0.018em">{{ title }}</h2>
            <div style="font-size:12.5px;color:#5d6b7d">{{ editing ? 'Paid expenses come out of the chosen account straight away.' : 'Will be saved as ' + nextRef + '. Paid expenses come out of the chosen account straight away.' }}</div>
          </div>
          <div style="margin-left:auto;display:flex;gap:8px;align-items:center">
            <div @click="cancel" style="height:34px;padding:0 13px;border-radius:8px;border:1px solid #dfe6ee;display:flex;align-items:center;font-size:12.5px;font-weight:500;color:#4a5866;cursor:pointer" v-pseudo:hover="'background:#f2f6fa'">Cancel</div>
            <div v-if="!editing" @click="save(true)" style="height:34px;padding:0 13px;border-radius:8px;border:1px solid #dfe6ee;display:flex;align-items:center;font-size:12.5px;font-weight:600;color:var(--c-primary-text);cursor:pointer" v-pseudo:hover="'background:#f2f6fa'">Save &amp; add another</div>
            <div @click="save(false)" :style="'height:34px;padding:0 15px;border-radius:8px;display:flex;align-items:center;font-size:12.5px;font-weight:600;color:#ffffff;cursor:pointer;background:' + (errors.length || saving ? '#9fbde0' : 'var(--c-primary)')">{{ saving ? 'Saving…' : editing ? 'Save changes' : form.paid ? 'Save & pay' : 'Save bill' }}</div>
          </div>
        </div>
      </div>
      <div style="padding:20px 24px 32px;display:grid;grid-template-columns:minmax(0,1fr) 330px;gap:16px;align-items:start">
        <div style="display:grid;gap:14px">
          <div v-if="savedMessage" style="background:#e7f6ee;color:#0f6b43;border-radius:9px;padding:10px 13px;font-size:12.5px;font-weight:600">{{ savedMessage }}</div>
          <div style="background:#ffffff;border:1px solid #dfe6ee;border-radius:12px;padding:17px 19px">
            <div style="font-size:13px;font-weight:700;margin-bottom:12px">Category <span style="color:#d93a3a">*</span></div>
            <div style="display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:7px">
              <div v-for="c in categories" :key="c.id" @click="form.expense_category_id = c.id" :title="c.name" :style="chipStyle(c.id)">
                <div :style="categoryDot(c.color)"></div>
                <div style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis">{{ c.name }}</div>
              </div>
            </div>
          </div>
          <div style="background:#ffffff;border:1px solid #dfe6ee;border-radius:12px;padding:17px 19px">
            <div style="font-size:13px;font-weight:700;margin-bottom:12px">Details</div>
            <div style="display:grid;grid-template-columns:1.2fr 1fr 1fr;gap:14px">
              <div>
                <label :style="labelStyle">Amount <span style="color:#d93a3a">*</span></label>
                <div :style="'display:flex;align-items:center;height:40px;border:1px solid ' + (form.amount && !(amount > 0) ? '#d93a3a' : 'var(--c-primary)') + ';border-radius:8px;background:var(--c-primary-tint)'"><div style="font-size:12px;color:#8b98a8;padding-left:11px">AUD</div> <input :value="form.amount" @input="onAmount" placeholder="0.00" style="flex:1;min-width:0;height:100%;border:none;background:transparent;text-align:right;font-family:'IBM Plex Mono',monospace;font-size:15px;font-weight:600;outline:none;padding:0 11px" /></div>
                <div style="display:flex;align-items:center;gap:8px;margin-top:7px">
                  <div @click="form.has_gst = !form.has_gst" :style="'width:30px;height:18px;border-radius:99px;position:relative;cursor:pointer;flex:none;background:' + (form.has_gst ? 'var(--c-primary)' : '#c2cedb')">
                    <div :style="'position:absolute;top:2px;width:14px;height:14px;border-radius:50%;background:#ffffff;left:' + (form.has_gst ? '14px' : '2px')"></div>
                  </div>
                  <div style="font-size:11.5px;color:#4a5866">{{ form.has_gst ? 'Includes GST' + (amount ? ' of ' + money(gst) : '') : 'No GST (GST-free or not registered)' }}</div>
                </div>
              </div>
              <div><label :style="labelStyle">Date <span style="color:#d93a3a">*</span></label> <input v-model="form.date" type="date" style="width:100%;height:40px;border:1px solid #dfe6ee;border-radius:8px;padding:0 11px;font-family:'IBM Plex Mono',monospace;font-size:12.5px;outline:none" v-pseudo:focus="'border-color:var(--c-primary)'" /></div>
              <div>
                <label :style="labelStyle">Location <span style="color:#d93a3a">*</span></label>
                <select v-model.number="form.branch_id" :disabled="!!userBranchId" style="width:100%;height:40px;border:1px solid #dfe6ee;border-radius:8px;padding:0 10px;font-family:'Public Sans',sans-serif;font-size:13px;background:#ffffff;outline:none;cursor:pointer">
                  <option v-for="b in activeBranches" :key="b.id" :value="b.id">{{ b.name }}</option>
                </select>
              </div>
            </div>
            <div style="display:grid;grid-template-columns:1.2fr 1fr;gap:14px;margin-top:14px">
              <div><label :style="labelStyle">Payee</label> <input v-model="form.payee" placeholder="Who you paid, e.g. Ausgrid" style="width:100%;height:36px;border:1px solid #dfe6ee;border-radius:8px;padding:0 11px;font-family:'Public Sans',sans-serif;font-size:13px;outline:none" v-pseudo:focus="'border-color:var(--c-primary)'" /></div>
              <div><label :style="labelStyle">Reference / invoice no.</label> <input v-model="form.invoice_no" placeholder="Optional" style="width:100%;height:36px;border:1px solid #dfe6ee;border-radius:8px;padding:0 11px;font-family:'IBM Plex Mono',monospace;font-size:12.5px;outline:none" v-pseudo:focus="'border-color:var(--c-primary)'" /></div>
            </div>
            <div style="margin-top:14px"><label :style="labelStyle">Notes</label> <textarea v-model="form.notes" placeholder="What this was for" style="width:100%;min-height:58px;border:1px solid #dfe6ee;border-radius:8px;padding:9px 11px;font-family:'Public Sans',sans-serif;font-size:13px;outline:none;resize:vertical" v-pseudo:focus="'border-color:var(--c-primary)'"></textarea></div>
          </div>
          <div style="background:#ffffff;border:1px solid #dfe6ee;border-radius:12px;padding:17px 19px">
            <div style="font-size:13px;font-weight:700;margin-bottom:12px">Receipt</div>
            <div v-if="receiptName" style="display:flex;align-items:center;gap:12px;border:1px solid #dfe6ee;border-radius:11px;padding:10px 12px">
              <div style="width:40px;height:48px;border-radius:6px;background:repeating-linear-gradient(135deg,#eef2f6 0 6px,#e4eaf1 6px 12px);display:grid;place-items:center;font-family:'IBM Plex Mono',monospace;font-size:9px;color:#5d6b7d;flex:none">{{ /\.pdf$/i.test(receiptName) ? 'PDF' : 'IMG' }}</div>
              <div style="flex:1;min-width:0">
                <div style="font-size:13px;font-weight:500;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">{{ receiptName }}</div>
                <div style="font-size:11px;color:#8b98a8">{{ file ? 'Uploaded when you save' : 'Already attached' }}</div>
              </div>
              <div @click="removeFile" style="height:30px;padding:0 11px;border-radius:7px;border:1px solid #dfe6ee;display:flex;align-items:center;font-size:12px;font-weight:600;color:#b02020;cursor:pointer" v-pseudo:hover="'background:#fdf4f4'">Remove</div>
            </div>
            <label v-else @dragover.prevent @drop.prevent="onDrop" style="display:block;border:1.5px dashed #c2cedb;border-radius:11px;padding:22px;text-align:center;cursor:pointer;background:#fbfcfe" v-pseudo:hover="'border-color:var(--c-primary);background:var(--c-primary-tint)'">
              <div style="font-size:13px;font-weight:600;color:var(--c-primary)">Drop a photo or PDF, or click to browse</div>
              <div style="font-size:11.5px;color:#8b98a8;margin-top:3px">JPG, PNG or PDF up to 10 MB · needed for GST claims over 82.50</div>
              <input type="file" accept=".pdf,.png,.jpg,.jpeg" @change="onFile" style="display:none" />
            </label>
            <div v-if="fileError" style="font-size:12px;color:#b02020;margin-top:7px">{{ fileError }}</div>
          </div>
        </div>
        <div style="display:grid;gap:14px;position:sticky;top:20px">
          <div style="background:#ffffff;border:1px solid #dfe6ee;border-radius:12px;padding:17px 19px">
            <div style="font-size:13px;font-weight:700;margin-bottom:12px">Payment</div>
            <div style="display:flex;gap:3px;background:#eef2f6;padding:3px;border-radius:9px;margin-bottom:14px">
              <div @click="form.paid = true" :style="modeStyle(form.paid)">Paid</div>
              <div @click="form.paid = false" :style="modeStyle(!form.paid)">Unpaid bill</div>
            </div>
            <div v-if="form.paid" style="display:grid;gap:7px">
              <div v-for="a in accounts" :key="a.id" @click="form.payment_account_id = a.id" :style="accountCardStyle(form.payment_account_id === a.id)">
                <div :style="accountRadioStyle(form.payment_account_id === a.id)"></div>
                <div style="flex:1;min-width:0">
                  <div style="font-size:13px;font-weight:600">{{ a.name }}</div>
                  <div style="font-size:11px;color:#7d8b9c">{{ accountType(a) }}</div>
                </div>
                <div style="text-align:right">
                  <div style="font-family:'IBM Plex Mono',monospace;font-size:12.5px;font-weight:600">{{ balance(startBalance(a), money) }}</div>
                  <div v-if="form.payment_account_id === a.id && amount" :style="'font-family:\'IBM Plex Mono\',monospace;font-size:10.5px;' + (isOverdrawn(a) ? 'color:#b02020;font-weight:600;' : 'color:#7d8b9c;')">after {{ balance(startBalance(a) - amount, money) }}</div>
                </div>
              </div>
            </div>
            <template v-else>
              <label :style="labelStyle">Due date <span style="color:#d93a3a">*</span></label>
              <input v-model="form.due_date" type="date" style="width:100%;height:36px;border:1px solid #dfe6ee;border-radius:8px;padding:0 11px;font-family:'IBM Plex Mono',monospace;font-size:12.5px;outline:none" v-pseudo:focus="'border-color:var(--c-primary)'" />
              <div style="font-size:11.5px;color:#7d8b9c;margin-top:7px;line-height:1.5">Shows on the Unpaid tab until you mark it paid.</div>
            </template>
          </div>
          <div style="background:#ffffff;border:1px solid #dfe6ee;border-radius:12px;padding:17px 19px;display:grid;gap:8px;font-size:12.5px">
            <div style="display:flex">
              <div style="flex:1;color:#5d6b7d">Amount ex GST</div>
              <div style="font-family:'IBM Plex Mono',monospace">{{ money(amount - gst) }}</div>
            </div>
            <div style="display:flex">
              <div style="flex:1;color:#5d6b7d">GST</div>
              <div style="font-family:'IBM Plex Mono',monospace">{{ money(gst) }}</div>
            </div>
            <div style="display:flex;align-items:baseline;border-top:1px solid #eaeff5;padding-top:10px;margin-top:2px">
              <div style="flex:1;font-size:13px;font-weight:600">Total</div>
              <div style="font-family:'IBM Plex Mono',monospace;font-size:20px;font-weight:600;letter-spacing:-0.02em">{{ money(amount) }}</div>
            </div>
          </div>
          <div v-if="warnGst" style="display:flex;gap:8px;font-size:12px;line-height:1.5;border-radius:10px;padding:10px 12px;background:#fdf1dd;color:#8a5300"><span style="font-weight:700">!</span>Tax invoice needed to claim GST over 82.50. Attach the receipt.</div>
          <div v-if="overdrawnText" style="display:flex;gap:8px;font-size:12px;line-height:1.5;border-radius:10px;padding:10px 12px;background:#fdeaea;color:#b02020"><span style="font-weight:700">×</span>{{ overdrawnText }}</div>
          <div v-if="saveError || (tried && errors.length)" style="background:#fdeaea;color:#b02020;border-radius:9px;padding:10px 13px;font-size:12.5px">{{ saveError || errors[0] }}</div>
        </div>
      </div>
    </template>
  </div>
</template>

<script setup lang="ts">
// Add or edit an expense (/expenses/new, /expenses/new?id=5).
// API: GET /api/expenses/form-data, POST /api/expenses, PUT /api/expenses/{id}, POST and DELETE /api/expenses/{id}/receipt
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { api } from '../../api/http.js';
import { branches, loadBranches } from '../../api/branches.js';
import { money, today, addDays, userBranchId, categoryDot, balance, accountType, defaultAccountId, accountCardStyle, accountRadioStyle } from '../../api/expenses.js';

const route = useRoute();
const router = useRouter();

const labelStyle = 'display:block;font-size:12px;font-weight:600;margin-bottom:5px';

const form = reactive<any>({
  expense_category_id: 0, amount: '', has_gst: true, date: today(), branch_id: 0, payee: '', invoice_no: '', notes: '',
  paid: true, payment_account_id: '', due_date: addDays(today(), 30)
});
const categories = ref<any[]>([]);
const accounts = ref<any[]>([]);
const nextRef = ref('');
const startBranch = ref(0);
const editing = ref<any>(null); // the expense as it was saved, when editing

const file = ref<File | null>(null);
const oldReceiptName = ref('');
const fileError = ref('');

const loading = ref(true);
const saving = ref(false);
const tried = ref(false);
const saveError = ref('');
const savedMessage = ref('');

const title = computed(() => (editing.value ? 'Edit ' + editing.value.ref : 'Add expense'));
const activeBranches = computed(() => branches.value.filter(b => b.active || b.id === form.branch_id));
const receiptName = computed(() => (file.value ? file.value.name : oldReceiptName.value));
const amount = computed(() => Math.round((parseFloat(form.amount) || 0) * 100) / 100);
const gst = computed(() => (form.has_gst ? Math.round(amount.value / 11 * 100) / 100 : 0));
const warnGst = computed(() => form.has_gst && amount.value > 82.5 && !receiptName.value);

const errors = computed(() => {
  const list: string[] = [];
  if (!form.expense_category_id) list.push('Pick a category');
  if (!(amount.value > 0)) list.push('Enter an amount');
  if (!form.date) list.push('Enter a date');
  if (!form.branch_id) list.push('Pick a location');
  if (form.paid && !form.payment_account_id) list.push('Pick a payment account');
  if (!form.paid && !form.due_date) list.push('Enter a due date');
  return list;
});

// When editing, the old amount goes back into its account first.
function startBalance(a: any) {
  const old = editing.value;
  return a.bal + (old && old.payment_account_id === a.id ? old.amount : 0);
}
function isOverdrawn(a: any) {
  return a.kind !== 'card' && startBalance(a) - amount.value < 0;
}
const overdrawnText = computed(() => {
  if (!form.paid) return '';
  const a = accounts.value.find(x => x.id === form.payment_account_id);
  if (!a || !isOverdrawn(a)) return '';
  return a.name + ' would go overdrawn by ' + money(amount.value - startBalance(a)) + '.';
});

async function load() {
  loading.value = true;
  saveError.value = '';
  savedMessage.value = '';
  tried.value = false;
  file.value = null;
  fileError.value = '';
  try {
    const data = await api.get('/expenses/form-data');
    categories.value = data.categories;
    accounts.value = data.accounts;
    nextRef.value = data.next_ref;
    startBranch.value = data.branch_id;

    const id = Number(route.query.id);
    if (id) {
      const e = await api.get('/expenses/' + id);
      editing.value = e;
      oldReceiptName.value = e.receipt_name || '';
      // An account that was switched off stays selectable on the expense that uses it.
      if (e.payment_account && !accounts.value.some(a => a.id === e.payment_account_id)) {
        accounts.value.push(e.payment_account);
      }
      // So does a category.
      if (!categories.value.some(c => c.id === e.expense_category_id)) {
        categories.value.push(e.category);
      }
      Object.assign(form, {
        expense_category_id: e.expense_category_id, amount: String(e.amount), has_gst: e.has_gst, date: e.date, branch_id: e.branch_id,
        payee: e.payee, invoice_no: e.invoice_no || '', notes: e.notes || '', paid: !!e.payment_account_id,
        payment_account_id: e.payment_account_id || defaultAccountId(data.accounts, e.branch_id),
        due_date: e.due_date || addDays(today(), 30)
      });
    } else {
      editing.value = null;
      oldReceiptName.value = '';
      resetForm(defaultAccountId(data.accounts, data.branch_id), data.branch_id);
    }
  } catch (e: any) {
    saveError.value = e.message;
  }
  loading.value = false;
}

function resetForm(accountId: string, branchId: number) {
  Object.assign(form, {
    expense_category_id: 0, amount: '', has_gst: true, date: today(), branch_id: branchId, payee: '', invoice_no: '', notes: '',
    paid: true, payment_account_id: accountId, due_date: addDays(today(), 30)
  });
}

onMounted(() => {
  load();
  loadBranches().catch(() => {});
});
watch(() => route.query.id, load);

function onAmount(e: Event) {
  const input = e.target as HTMLInputElement;
  input.value = input.value.replace(/[^0-9.]/g, '');
  form.amount = input.value;
}

function pickFile(picked?: File | null) {
  fileError.value = '';
  if (!picked) return;
  if (!/\.(pdf|png|jpe?g)$/i.test(picked.name)) { fileError.value = 'Only JPG, PNG or PDF files.'; return; }
  if (picked.size > 10 * 1024 * 1024) { fileError.value = 'That file is over 10 MB.'; return; }
  file.value = picked;
}
function onFile(e: Event) {
  const files = (e.target as HTMLInputElement).files;
  pickFile(files && files[0]);
}
function onDrop(e: DragEvent) {
  const files = e.dataTransfer && e.dataTransfer.files;
  pickFile(files && files[0]);
}
function removeFile() {
  file.value = null;
  oldReceiptName.value = '';
}

function cancel() {
  if (editing.value) router.push({ name: 'expenses-details', query: { id: editing.value.id } });
  else router.push({ name: 'expenses' });
}

async function save(another: boolean) {
  if (saving.value) return;
  tried.value = true;
  saveError.value = '';
  savedMessage.value = '';
  if (errors.value.length) return;

  saving.value = true;
  const body = {
    date: form.date, branch_id: form.branch_id, expense_category_id: form.expense_category_id, payee: form.payee,
    amount: amount.value, has_gst: form.has_gst, paid: form.paid,
    payment_account_id: form.paid ? form.payment_account_id : null,
    due_date: form.paid ? null : form.due_date,
    invoice_no: form.invoice_no, notes: form.notes
  };

  let saved: any;
  try {
    saved = editing.value
      ? await api.put('/expenses/' + editing.value.id, body)
      : await api.post('/expenses', body);
  } catch (e: any) {
    saveError.value = e.message;
    saving.value = false;
    return;
  }

  // The expense is saved by now, so a failed upload must not look like a failed save.
  let receiptFailed = false;
  try {
    if (file.value) {
      const data = new FormData();
      data.append('file', file.value);
      await api.post('/expenses/' + saved.id + '/receipt', data);
    } else if (editing.value && editing.value.receipt_name && !oldReceiptName.value) {
      await api.del('/expenses/' + saved.id + '/receipt');
    }
  } catch (e) {
    receiptFailed = true;
  }
  saving.value = false;

  if (another && !receiptFailed) {
    const data = await api.get('/expenses/form-data').catch(() => null);
    if (data) {
      accounts.value = data.accounts;
      nextRef.value = data.next_ref;
    }
    file.value = null;
    tried.value = false;
    resetForm(form.payment_account_id, form.branch_id);
    savedMessage.value = saved.ref + ' added · ' + money(saved.amount) + (saved.payment_account_id ? ' paid' : ' as unpaid');
    return;
  }

  router.push({ name: 'expenses-details', query: receiptFailed ? { id: saved.id, receipt: 'failed' } : { id: saved.id } });
}

function chipStyle(id: number) {
  return 'height:38px;border-radius:9px;display:flex;align-items:center;gap:8px;padding:0 11px;font-size:12.5px;cursor:pointer;min-width:0;'
    + (form.expense_category_id === id ? 'background:#0f1c2b;color:#ffffff;font-weight:600;' : 'border:1px solid #dfe6ee;color:#0f1c2b;font-weight:500;');
}
function modeStyle(on: boolean) {
  return 'flex:1;height:32px;border-radius:7px;display:grid;place-items:center;font-size:12.5px;cursor:pointer;'
    + (on ? 'background:#ffffff;color:#0f1c2b;font-weight:700;box-shadow:0 1px 3px rgba(15,28,43,.14);' : 'color:#5d6b7d;font-weight:500;');
}
</script>
