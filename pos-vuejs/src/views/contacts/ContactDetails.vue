<template>
  <template v-if="vm.cDetails">
    <div>
      <div v-if="!contact" style="padding:24px;font-size:13px;color:#5d6b7d">
        {{ loading ? 'Loading…' : 'Contact not found.' }}
        <span v-if="!loading" @click="goList" style="color:var(--c-primary);cursor:pointer;margin-left:6px">Back to contacts</span>
      </div>

      <template v-else>
        <div style="padding:20px 24px 0;background:#ffffff;border-bottom:1px solid #dfe6ee">
          <div style="display:flex;align-items:center;gap:7px;font-size:11.5px;color:#7d8b9c;margin-bottom:14px"><span @click="goList" style="cursor:pointer;color:var(--c-primary)">Contacts</span><span style="color:#c2cedb">/</span><span style="color:#0f1c2b;font-weight:500">{{ contact.name }}</span></div>
          <div style="display:flex;align-items:flex-start;gap:16px;flex-wrap:wrap">
            <div style="width:52px;height:52px;border-radius:12px;background:var(--c-sidebar);color:#ffffff;display:grid;place-items:center;font-size:17px;font-weight:600;flex:none">{{ initials(contact.name) }}</div>
            <div style="min-width:0">
              <div style="display:flex;align-items:center;gap:9px;flex-wrap:wrap;margin-bottom:4px">
                <h2 style="margin:0;font-size:22px;font-weight:700;letter-spacing:-0.018em">{{ contact.name }}</h2>
                <span style="background:var(--c-primary-soft);color:var(--c-primary-text);border-radius:5px;padding:3px 9px;font-size:11.5px;font-weight:600">{{ contact.type }}</span>
                <span v-if="contact.customer_group" style="background:#fdf1dd;color:#8a5300;border-radius:5px;padding:3px 9px;font-size:11.5px;font-weight:600">{{ contact.customer_group.name }}</span>
              </div>
              <div style="font-size:12.5px;color:#5d6b7d">{{ [contact.contact_name !== contact.name ? contact.contact_name : '', contact.phone, contact.email].filter(Boolean).join(' · ') }}</div>
            </div>
            <div style="margin-left:auto;display:flex;gap:8px;align-items:center">
              <div v-if="can('contacts.delete')" @click="remove" style="height:34px;padding:0 13px;border-radius:8px;border:1px solid #f3cfcf;background:#fdf4f4;display:flex;align-items:center;font-size:12.5px;font-weight:600;color:#b02020;cursor:pointer">Delete</div>
              <div v-if="can('contacts.edit')" @click="openContactForm(contact.id)" style="height:34px;padding:0 15px;border-radius:8px;background:var(--c-primary);color:#ffffff;display:flex;align-items:center;font-size:12.5px;font-weight:600;cursor:pointer">Edit</div>
            </div>
          </div>
          <div style="display:flex;gap:2px;margin-top:16px">
            <div v-for="t in tabs" :key="t[0]" @click="tab = t[0]" :style="'padding:9px 12px;font-size:13px;cursor:pointer;' + (tab === t[0] ? 'color:#0f1c2b;font-weight:600;box-shadow:inset 0 -2px 0 var(--c-primary);' : 'color:#5d6b7d;font-weight:500;')">{{ t[1] }}</div>
          </div>
        </div>

        <div style="padding:20px 24px 32px;display:grid;grid-template-columns:1fr 320px;gap:16px;align-items:start">
          <div>
            <!-- Transactions: sales and purchases with this contact's name -->
            <div v-if="tab === 'tx'" style="background:#ffffff;border:1px solid #dfe6ee;border-radius:12px;overflow:hidden">
              <div style="padding:14px 18px;border-bottom:1px solid #eaeff5;font-size:13px;font-weight:700">Transactions</div>
              <div style="display:grid;grid-template-columns:1.1fr 1fr 1fr 0.9fr 0.9fr;gap:12px;padding:9px 18px;background:#f6f9fc;border-bottom:1px solid #dfe6ee;font-family:'IBM Plex Mono',monospace;font-size:9.5px;letter-spacing:0.07em;text-transform:uppercase;color:#5d6b7d">
                <div>Reference</div>
                <div>Type</div>
                <div style="text-align:right">Total</div>
                <div style="text-align:right">Due</div>
                <div style="text-align:right">Status</div>
              </div>
              <div v-if="!transactions.length" style="padding:16px 18px;font-size:12.5px;color:#7d8b9c">No sales or purchases yet.</div>
              <div v-for="t in transactions" :key="t.kind + t.ref" style="display:grid;grid-template-columns:1.1fr 1fr 1fr 0.9fr 0.9fr;gap:12px;padding:11px 18px;border-bottom:1px solid #f0f4f8;align-items:center">
                <div><div style="font-family:'IBM Plex Mono',monospace;font-size:12px;font-weight:500">{{ t.ref }}</div> <div style="font-size:10.5px;color:#9aa7b6;margin-top:1px">{{ t.date }}</div></div>
                <div style="font-size:12.5px;color:#4a5866">{{ t.kind }}</div>
                <div style="font-family:'IBM Plex Mono',monospace;font-size:12.5px;text-align:right;font-weight:500">{{ money(t.total) }}</div>
                <div :style="'font-family:\'IBM Plex Mono\',monospace;font-size:12.5px;text-align:right;' + (t.due > 0 ? 'color:#b02020;font-weight:600;' : 'color:#9aa7b6;')">{{ money(t.due) }}</div>
                <div style="text-align:right"><span :style="statusBadge(t.status)">{{ t.status }}</span></div>
              </div>
            </div>

            <!-- Coins: earned and spent on sales, or changed by hand -->
            <div v-if="tab === 'coins'" style="display:grid;gap:14px">
              <div v-if="can('contacts.edit')" style="background:#ffffff;border:1px solid #dfe6ee;border-radius:12px;padding:16px 18px">
                <div style="font-size:13px;font-weight:700;margin-bottom:4px">Add or take off coins</div>
                <div style="font-size:12px;color:#5d6b7d;margin-bottom:12px">Use + to add (e.g. 50) and - to take off (e.g. -20). Sales add and spend coins by themselves.</div>
                <div style="display:grid;grid-template-columns:130px 1fr auto;gap:10px;align-items:start">
                  <input v-model="adjChange" placeholder="e.g. 50" style="height:36px;border:1px solid #dfe6ee;border-radius:8px;padding:0 11px;font-family:'IBM Plex Mono',monospace;font-size:13px;text-align:right;outline:none" v-pseudo:focus="'border-color:var(--c-primary)'" />
                  <input v-model="adjNote" maxlength="190" placeholder="Reason, e.g. Birthday gift" style="height:36px;border:1px solid #dfe6ee;border-radius:8px;padding:0 11px;font-family:'Public Sans',sans-serif;font-size:13px;outline:none" v-pseudo:focus="'border-color:var(--c-primary)'" />
                  <div @click="adjustCoins" style="height:36px;padding:0 15px;border-radius:8px;background:var(--c-primary);color:#ffffff;display:flex;align-items:center;font-size:12.5px;font-weight:600;cursor:pointer">{{ busy ? 'Saving…' : 'Save' }}</div>
                </div>
                <div v-if="adjError" style="background:#fdeaea;color:#b02020;border-radius:9px;padding:9px 12px;font-size:12.5px;margin-top:10px">{{ adjError }}</div>
              </div>
              <div style="background:#ffffff;border:1px solid #dfe6ee;border-radius:12px;overflow:hidden">
                <div style="padding:14px 18px;border-bottom:1px solid #eaeff5;font-size:13px;font-weight:700">Coin history</div>
                <div style="display:grid;grid-template-columns:1fr 1.2fr 0.7fr 0.7fr 1.4fr;gap:12px;padding:9px 18px;background:#f6f9fc;border-bottom:1px solid #dfe6ee;font-family:'IBM Plex Mono',monospace;font-size:9.5px;letter-spacing:0.07em;text-transform:uppercase;color:#5d6b7d">
                  <div>Date</div>
                  <div>What</div>
                  <div style="text-align:right">Coins</div>
                  <div style="text-align:right">Balance</div>
                  <div>Note</div>
                </div>
                <div v-if="!coinData.transactions.length" style="padding:16px 18px;font-size:12.5px;color:#7d8b9c">No coins yet.</div>
                <div v-for="t in coinData.transactions" :key="t.id" style="display:grid;grid-template-columns:1fr 1.2fr 0.7fr 0.7fr 1.4fr;gap:12px;padding:10px 18px;border-bottom:1px solid #f0f4f8;align-items:center;font-size:12.5px">
                  <div style="font-family:'IBM Plex Mono',monospace;font-size:11.5px;color:#5d6b7d">{{ dateTime(t.created_at) }}</div>
                  <div>
                    {{ t.kind }}
                    <RouterLink v-if="t.sale" :to="{ name: 'sell-details', query: { id: t.sale.id } }" style="font-family:'IBM Plex Mono',monospace;font-size:11.5px;color:var(--c-primary);text-decoration:none;margin-left:4px">{{ t.sale.ref }}</RouterLink>
                  </div>
                  <div :style="'font-family:\'IBM Plex Mono\',monospace;text-align:right;font-weight:600;color:' + (t.change > 0 ? '#0f6b43' : '#b02020')">{{ t.change > 0 ? '+' : '' }}{{ t.change }}</div>
                  <div style="font-family:'IBM Plex Mono',monospace;text-align:right">{{ t.balance }}</div>
                  <div style="color:#5d6b7d;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ [t.note, t.user_name].filter(Boolean).join(' · ') }}</div>
                </div>
              </div>
            </div>

            <!-- Address & notes -->
            <div v-if="tab === 'info'" style="background:#ffffff;border:1px solid #dfe6ee;border-radius:12px;padding:16px 18px;display:grid;gap:14px">
              <div>
                <div style="font-size:12px;font-weight:700;margin-bottom:6px">Address</div>
                <div style="font-size:12.5px;line-height:1.55;color:#4a5866">{{ address || 'No address yet.' }}</div>
              </div>
              <div>
                <div style="font-size:12px;font-weight:700;margin-bottom:6px">Note</div>
                <div style="font-size:12.5px;line-height:1.55;color:#4a5866">{{ contact.note || 'No note.' }}</div>
              </div>
            </div>
          </div>

          <div style="display:grid;gap:12px">
            <!-- Balance -->
            <div style="background:#ffffff;border:1px solid #dfe6ee;border-radius:12px;padding:16px 18px">
              <div style="font-size:11.5px;color:#7d8b9c;margin-bottom:7px">{{ contact.balance > 0 ? 'Balance due (they owe us)' : contact.balance < 0 ? 'We owe them' : 'Balance' }}</div>
              <div :style="'font-family:\'IBM Plex Mono\',monospace;font-size:27px;font-weight:600;letter-spacing:-0.025em;line-height:1;color:' + (contact.balance > 0 ? '#b02020' : contact.balance < 0 ? '#8a5300' : '#0f1c2b')">{{ money(Math.abs(contact.balance)) }}</div>
              <div v-if="contact.credit_limit > 0" style="font-size:11.5px;color:#5d6b7d;margin-top:6px">Credit limit {{ money(contact.credit_limit) }}</div>
            </div>

            <!-- Loyalty coins -->
            <div v-if="isCustomer" @click="tab = 'coins'" style="background:#ffffff;border:1px solid #dfe6ee;border-radius:12px;padding:16px 18px;cursor:pointer">
              <div style="font-size:11.5px;color:#7d8b9c;margin-bottom:7px">Loyalty coins</div>
              <div style="font-family:'IBM Plex Mono',monospace;font-size:27px;font-weight:600;letter-spacing:-0.025em;line-height:1;color:#8a5300">{{ coinData.coins }}</div>
            </div>

            <!-- Account -->
            <div style="background:#ffffff;border:1px solid #dfe6ee;border-radius:12px;padding:16px 18px">
              <div style="font-size:12.5px;font-weight:700;margin-bottom:13px">Account</div>
              <div style="display:grid;gap:11px">
                <div v-for="m in account" :key="m.k">
                  <div style="font-size:11px;color:#8b98a8;margin-bottom:2px">{{ m.k }}</div>
                  <div :style="'font-size:12.5px;font-weight:500;' + (m.mono ? 'font-family:\'IBM Plex Mono\',monospace;font-size:12px;' : '')">{{ m.v }}</div>
                </div>
              </div>
            </div>

            <!-- Lifetime -->
            <div style="background:#ffffff;border:1px solid #dfe6ee;border-radius:12px;padding:16px 18px">
              <div style="font-size:12.5px;font-weight:700;margin-bottom:12px">Lifetime</div>
              <div style="display:grid;gap:12px">
                <div v-for="s in lifetime" :key="s.k" style="display:flex;align-items:baseline;gap:10px">
                  <div style="font-size:12.5px;color:#5d6b7d;flex:1">{{ s.k }}</div>
                  <div style="font-family:'IBM Plex Mono',monospace;font-size:14px;font-weight:600">{{ s.v }}</div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </template>
    </div>
  </template>
</template>

<script setup lang="ts">
// Contacts > one contact (/contacts/details?id=5).
// API: GET /api/contacts, GET /api/contacts/{id}/transactions, DELETE /api/contacts/{id},
//      GET /api/contacts/{id}/coins, POST /api/contacts/{id}/coins (add or take off coins)
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { usePageVm } from '../../erp/useErpPage';
import { api } from '../../api/http.js';
import { can } from '../../api/auth.js';
import { contacts, loadContacts, openContactForm, money, initials } from '../../api/contacts.js';
import { dateTime } from '../../api/sales.js';

const vm = usePageVm();
const route = useRoute();
const router = useRouter();

const tab = ref('tx');
const loading = ref(true);
const transactions = ref<any[]>([]);

const coinData = ref<{ coins: number; transactions: any[] }>({ coins: 0, transactions: [] });
const adjChange = ref('');
const adjNote = ref('');
const adjError = ref('');
const busy = ref(false);

const id = computed(() => Number(route.query.id));
const contact = computed(() => contacts.value.find(c => c.id === id.value));
const isCustomer = computed(() => !!contact.value && contact.value.type !== 'Supplier');
const tabs = computed(() => isCustomer.value
  ? [['tx', 'Transactions'], ['coins', 'Coins'], ['info', 'Address & notes']]
  : [['tx', 'Transactions'], ['info', 'Address & notes']]);

async function load() {
  loading.value = true;
  try {
    if (!contacts.value.length) await loadContacts();
    transactions.value = id.value ? await api.get('/contacts/' + id.value + '/transactions') : [];
  } catch (e) {
    transactions.value = [];
  }
  await loadCoins();
  loading.value = false;
}

async function loadCoins() {
  try {
    coinData.value = id.value ? await api.get('/contacts/' + id.value + '/coins') : { coins: 0, transactions: [] };
  } catch (e) {
    coinData.value = { coins: 0, transactions: [] };
  }
}

async function adjustCoins() {
  if (busy.value) return;
  adjError.value = '';
  const change = Number(adjChange.value.trim());
  if (!Number.isInteger(change) || change === 0) { adjError.value = 'Enter a whole number of coins, e.g. 50 or -20.'; return; }
  if (!adjNote.value.trim()) { adjError.value = 'Enter a reason.'; return; }
  busy.value = true;
  try {
    coinData.value = await api.post('/contacts/' + id.value + '/coins', { change, note: adjNote.value.trim() });
    adjChange.value = '';
    adjNote.value = '';
  } catch (e: any) {
    adjError.value = e.message;
  }
  busy.value = false;
}

onMounted(load);
watch(id, load);

const address = computed(() => {
  const c = contact.value;
  if (!c) return '';
  return [c.street, [c.suburb, c.state, c.postcode].filter(Boolean).join(' ')].filter(Boolean).join(', ');
});

const account = computed(() => {
  const c = contact.value;
  const g = c.customer_group;
  const rows = [{ k: 'Contact ID', v: 'CT-' + String(c.id).padStart(4, '0'), mono: true }];
  if (g) {
    rows.push({ k: 'Customer group', v: g.name, mono: false });
    rows.push({ k: 'Price group', v: g.price_group || '—', mono: false });
    rows.push({ k: 'Payment terms', v: g.terms || '—', mono: false });
    rows.push({ k: 'Group discount', v: g.discount + '%', mono: true });
  }
  if (c.pay_term) rows.push({ k: 'Pay term (supplier)', v: c.pay_term, mono: false });
  if (c.abn) rows.push({ k: 'ABN', v: c.abn, mono: true });
  if (c.created_at) rows.push({ k: 'Contact since', v: c.created_at.split('-').reverse().join('/'), mono: true });
  return rows;
});

const lifetime = computed(() => {
  const n = transactions.value.length;
  const total = transactions.value.reduce((a, t) => a + t.total, 0);
  return [
    { k: 'Total business', v: money(contact.value.business) },
    { k: 'Transactions', v: String(n) },
    { k: 'Average transaction', v: n ? money(total / n) : '—' }
  ];
});

function statusBadge(status: string) {
  const colors = status === 'paid' ? ['#e7f6ee', '#0f6b43'] : status === 'due' ? ['#fdeaea', '#b02020'] : status === 'cancelled' ? ['#eef2f6', '#5d6b7d'] : ['#fdf1dd', '#8a5300'];
  return 'background:' + colors[0] + ';color:' + colors[1] + ';border-radius:5px;padding:2px 8px;font-size:11px;font-weight:600;text-transform:capitalize';
}

function goList() {
  router.push({ name: 'contacts' });
}

async function remove() {
  if (!confirm('Delete ' + contact.value.name + '?')) return;
  try {
    await api.del('/contacts/' + contact.value.id);
    await loadContacts();
    goList();
  } catch (e) { /* the error bar shows why, e.g. "still has a balance" */ }
}
</script>
