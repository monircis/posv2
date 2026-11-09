<template>
  <template v-if="vm.cList">
    <div>
      <div style="padding:20px 24px 0;background:#ffffff;border-bottom:1px solid #dfe6ee">
        <div style="display:flex;align-items:center;gap:7px;font-size:11.5px;color:#7d8b9c;margin-bottom:8px"><span>Contacts</span><span style="color:#c2cedb">/</span><span style="color:#0f1c2b;font-weight:500">All contacts</span></div>
        <div style="display:flex;align-items:flex-start;gap:20px;flex-wrap:wrap">
          <div>
            <h2 style="margin:0 0 3px;font-size:22px;font-weight:700;letter-spacing:-0.018em">Contacts</h2>
            <div style="font-size:12.5px;color:#5d6b7d">{{ subtitle }}</div>
          </div>
          <div style="margin-left:auto;display:flex;gap:8px;align-items:center">
            <div v-if="can('contacts.create')" @click="openContactForm()" style="height:34px;padding:0 15px;border-radius:8px;background:var(--c-primary);color:#ffffff;display:flex;align-items:center;font-size:12.5px;font-weight:600;cursor:pointer" v-pseudo:hover="'background:var(--c-primary-hover)'">+ Add contact</div>
          </div>
        </div>
        <!-- Type tabs -->
        <div style="display:flex;gap:2px;margin-top:16px">
          <div v-for="t in TABS" :key="t" @click="tab = t" :style="tabStyle(t === tab)">
            {{ t }}<span :style="'font-family:\'IBM Plex Mono\',monospace;font-size:10.5px;border-radius:4px;padding:1px 5px;margin-left:6px;' + (t === tab ? 'background:var(--c-primary-soft);color:var(--c-primary-text);' : 'background:#eef2f6;color:#7d8b9c;')">{{ ofType(t).length }}</span>
          </div>
        </div>
      </div>

      <div style="padding:20px 24px 32px">
        <!-- Totals -->
        <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:16px">
          <div v-for="k in kpis" :key="k.label" style="background:#ffffff;border:1px solid #dfe6ee;border-radius:12px;padding:14px 16px">
            <div style="font-size:11.5px;color:#7d8b9c;margin-bottom:7px">{{ k.label }}</div>
            <div :style="'font-family:\'IBM Plex Mono\',monospace;font-size:22px;font-weight:600;letter-spacing:-0.025em;line-height:1;color:' + k.color">{{ k.value }}</div>
            <div style="font-size:11.5px;color:#7d8b9c;margin-top:5px">{{ k.sub }}</div>
          </div>
        </div>

        <!-- Filters -->
        <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-bottom:14px">
          <div style="position:relative;width:250px"><div style="position:absolute;left:10px;top:50%;transform:translateY(-50%);color:#9aa7b6;font-size:12px">⚲</div> <input v-model="search" placeholder="Name, business, phone or email" style="width:100%;height:34px;border:1px solid #dfe6ee;border-radius:8px;background:#ffffff;padding:0 10px 0 29px;font-family:'Public Sans',sans-serif;font-size:12.5px;outline:none" v-pseudo:focus="'border-color:var(--c-primary)'" /></div>
          <select v-if="!contactsOwnBranch" v-model="branchFilter" title="Branch" style="height:34px;border:1px solid #dfe6ee;border-radius:8px;background:#ffffff;padding:0 8px;font-family:'Public Sans',sans-serif;font-size:12.5px;color:#4a5866;outline:none;cursor:pointer">
            <option :value="0">All branches</option>
            <option v-for="b in branches" :key="b.id" :value="b.id">{{ b.name }} (+ shared)</option>
            <option :value="-1">Shared by all branches only</option>
          </select>
          <select v-model="groupId" style="height:34px;border:1px solid #dfe6ee;border-radius:8px;background:#ffffff;padding:0 8px;font-family:'Public Sans',sans-serif;font-size:12.5px;color:#4a5866;outline:none;cursor:pointer">
            <option :value="0">All groups</option>
            <option v-for="g in groups" :key="g.id" :value="g.id">{{ g.name }}</option>
          </select>
          <div @click="dueOnly = !dueOnly" :style="'height:34px;padding:0 12px;border-radius:8px;display:flex;align-items:center;font-size:12.5px;cursor:pointer;' + (dueOnly ? 'border:1px solid var(--c-primary);background:var(--c-primary-soft);color:var(--c-primary-text);font-weight:600;' : 'border:1px solid #dfe6ee;background:#ffffff;color:#4a5866;font-weight:500;')">Has balance due</div>
        </div>

        <!-- Table -->
        <div style="background:#ffffff;border:1px solid #dfe6ee;border-radius:12px;overflow:hidden">
          <div style="display:grid;grid-template-columns:2.2fr 1fr 1.3fr 1fr 1fr 1fr 50px;gap:12px;padding:10px 16px;background:#f6f9fc;border-bottom:1px solid #dfe6ee;font-family:'IBM Plex Mono',monospace;font-size:9.5px;letter-spacing:0.07em;text-transform:uppercase;color:#5d6b7d;align-items:center">
            <div>Contact</div>
            <div>Type</div>
            <div>Group / terms</div>
            <div style="text-align:right">Total business</div>
            <div style="text-align:right">Balance</div>
            <div>Last activity</div>
            <div></div>
          </div>

          <div v-if="loading" style="padding:18px 16px;font-size:12.5px;color:#7d8b9c">Loading…</div>
          <div v-else-if="!shown.length" style="padding:18px 16px;font-size:12.5px;color:#7d8b9c">No contacts match.</div>

          <div v-for="c in shown" :key="c.id" style="display:grid;grid-template-columns:2.2fr 1fr 1.3fr 1fr 1fr 1fr 50px;gap:12px;padding:11px 16px;border-bottom:1px solid #f0f4f8;align-items:center" v-pseudo:hover="'background:#f9fbfd'">
            <div style="display:flex;align-items:center;gap:11px;min-width:0">
              <div style="width:32px;height:32px;border-radius:8px;display:grid;place-items:center;font-size:11.5px;font-weight:600;flex:none;background:var(--c-sidebar);color:#ffffff">{{ initials(c.name) }}</div>
              <div style="min-width:0">
                <div @click="goDetails(c.id)" style="font-size:13px;font-weight:500;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;cursor:pointer;color:var(--c-primary)">{{ c.name }}</div>
                <div style="font-size:11px;color:#8b98a8;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">{{ [c.contact_name !== c.name ? c.contact_name : '', c.phone].filter(Boolean).join(' · ') }}</div>
              </div>
            </div>
            <div><span :style="typeBadge(c.type)">{{ c.type }}</span><div style="font-size:10.5px;color:#8b98a8;margin-top:3px">{{ branchText(c) }}</div></div>
            <div style="font-size:12.5px;color:#4a5866;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">{{ groupText(c) }}</div>
            <div style="font-family:'IBM Plex Mono',monospace;font-size:12.5px;text-align:right;color:#4a5866">{{ money(c.business) }}</div>
            <div style="text-align:right"><span :style="balanceStyle(c.balance)">{{ c.balance === 0 ? '—' : money(Math.abs(c.balance)) }}</span></div>
            <div style="font-family:'IBM Plex Mono',monospace;font-size:11.5px;color:#5d6b7d">{{ c.last_activity || '—' }}</div>
            <div style="display:flex;justify-content:flex-end">
              <div v-if="can('contacts.edit')" @click="openContactForm(c.id)" title="Edit" style="width:26px;height:26px;border-radius:6px;border:1px solid #e4eaf1;display:grid;place-items:center;font-size:11px;color:#5d6b7d;cursor:pointer" v-pseudo:hover="'background:#f2f6fa;color:var(--c-primary)'">✎</div>
            </div>
          </div>

          <div style="padding:12px 16px;font-size:12px;color:#7d8b9c">Showing {{ shown.length }} of {{ contacts.length }}</div>
        </div>
      </div>
    </div>
  </template>
</template>

<script setup lang="ts">
// Contacts > All contacts. API: GET /api/contacts (the form is in ContactsPage.vue).
import { computed, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import { usePageVm } from '../../erp/useErpPage';
import { can } from '../../api/auth.js';
import { contacts, groups, loadContacts, loadGroups, openContactForm, money, initials, contactsOwnBranch, branchText } from '../../api/contacts.js';
import { branches, loadBranches } from '../../api/branches.js';

const vm = usePageVm();
const router = useRouter();

const TABS = ['All', 'Customers', 'Suppliers', 'Both'];
const tab = ref('All');
const search = ref('');
const groupId = ref(0);
const dueOnly = ref(false);
// 0 = every branch, -1 = only the contacts shared by all branches, else one branch (with the shared ones).
const branchFilter = ref(0);
const loading = ref(true);

onMounted(async () => {
  try {
    await loadContacts();
    await loadGroups();
    if (!contactsOwnBranch.value) await loadBranches();
  } catch (e) { /* the error bar shows why */ }
  loading.value = false;
});

// "Customers" tab = type Customer, and so on.
function ofType(t: string) {
  if (t === 'All') return contacts.value;
  if (t === 'Both') return contacts.value.filter(c => c.type === 'Both');
  return contacts.value.filter(c => c.type === t.slice(0, -1));
}

const shown = computed(() => {
  const q = search.value.trim().toLowerCase();
  return ofType(tab.value).filter(c => {
    if (groupId.value && c.customer_group_id !== groupId.value) return false;
    if (branchFilter.value === -1 && c.branch_id) return false;
    if (branchFilter.value > 0 && c.branch_id && c.branch_id !== branchFilter.value) return false;
    if (dueOnly.value && c.balance === 0) return false;
    if (!q) return true;
    return [c.name, c.contact_name, c.phone, c.email].some(v => (v || '').toLowerCase().includes(q));
  });
});

const subtitle = computed(() => {
  const n = contacts.value.length;
  return n + ' contacts · ' + ofType('Customers').length + ' customers, ' + ofType('Suppliers').length + ' suppliers, ' + ofType('Both').length + ' both';
});

// Receivable = what customers owe us (+ balances), payable = what we owe suppliers (- balances).
const kpis = computed(() => {
  const owe = contacts.value.filter(c => c.balance > 0);
  const owed = contacts.value.filter(c => c.balance < 0);
  return [
    { label: 'Receivable', value: money(owe.reduce((a, c) => a + c.balance, 0)), sub: 'from ' + owe.length + ' contacts', color: '#b02020' },
    { label: 'Payable', value: money(owed.reduce((a, c) => a - c.balance, 0)), sub: 'to ' + owed.length + ' contacts', color: '#8a5300' },
    { label: 'Customers', value: String(ofType('Customers').length + ofType('Both').length), sub: 'including "both"', color: '#0f1c2b' },
    { label: 'Suppliers', value: String(ofType('Suppliers').length + ofType('Both').length), sub: 'including "both"', color: '#0f1c2b' }
  ];
});

function groupText(c: any) {
  if (c.customer_group) return c.customer_group.name;
  if (c.pay_term) return c.pay_term + ' terms';
  return '—';
}

function goDetails(id: number) {
  router.push({ name: 'contacts-details', query: { id } });
}

function tabStyle(on: boolean) {
  return 'padding:9px 12px;font-size:13px;cursor:pointer;display:flex;align-items:center;'
    + (on ? 'color:#0f1c2b;font-weight:600;box-shadow:inset 0 -2px 0 var(--c-primary);' : 'color:#5d6b7d;font-weight:500;');
}

function typeBadge(type: string) {
  const colors = type === 'Supplier' ? ['#fdf1dd', '#8a5300'] : type === 'Both' ? ['var(--c-primary-soft)', 'var(--c-primary-text)'] : ['#e7f6ee', '#0f6b43'];
  return 'background:' + colors[0] + ';color:' + colors[1] + ';border-radius:5px;padding:3px 8px;font-size:11.5px;font-weight:600';
}

// Red = they owe us, amber = we owe them.
function balanceStyle(balance: number) {
  return "font-family:'IBM Plex Mono',monospace;font-size:12.5px;font-weight:600;border-radius:5px;padding:3px 7px;"
    + (balance > 0 ? 'background:#fdeaea;color:#b02020;' : balance < 0 ? 'background:#fdf1dd;color:#8a5300;' : 'color:#9aa7b6;');
}
</script>
