<template>
  <template v-if="vm.cGroups">
    <div>
      <div style="padding:20px 24px 16px;background:#ffffff;border-bottom:1px solid #dfe6ee">
        <div style="display:flex;align-items:center;gap:7px;font-size:11.5px;color:#7d8b9c;margin-bottom:8px"><span>Contacts</span><span style="color:#c2cedb">/</span><span style="color:#0f1c2b;font-weight:500">Customer groups</span></div>
        <div style="display:flex;align-items:flex-start;gap:20px;flex-wrap:wrap">
          <div>
            <h2 style="margin:0 0 3px;font-size:22px;font-weight:700;letter-spacing:-0.018em">Customer groups</h2>
            <div style="font-size:12.5px;color:#5d6b7d">{{ groups.length }} groups · drive pricing, discount and payment terms</div>
          </div>
          <div v-if="can('contacts.create')" @click="openForm(0)" style="margin-left:auto;height:34px;padding:0 15px;border-radius:8px;background:var(--c-primary);color:#ffffff;display:flex;align-items:center;font-size:12.5px;font-weight:600;cursor:pointer" v-pseudo:hover="'background:var(--c-primary-hover)'">+ New group</div>
        </div>
      </div>

      <div style="padding:20px 24px 32px">
        <div style="background:#ffffff;border:1px solid #dfe6ee;border-radius:12px;overflow:hidden">
          <div style="display:grid;grid-template-columns:1.5fr 1fr 1.1fr 0.8fr 0.8fr 0.8fr 1fr 50px;gap:12px;padding:10px 18px;background:#f6f9fc;border-bottom:1px solid #dfe6ee;font-family:'IBM Plex Mono',monospace;font-size:9.5px;letter-spacing:0.07em;text-transform:uppercase;color:#5d6b7d">
            <div>Group</div>
            <div>Branch</div>
            <div>Price group</div>
            <div style="text-align:right">Discount</div>
            <div style="text-align:right">Terms</div>
            <div style="text-align:right">Contacts</div>
            <div style="text-align:right">Balance due</div>
            <div></div>
          </div>
          <div v-if="loading" style="padding:16px 18px;font-size:12.5px;color:#7d8b9c">Loading…</div>
          <div v-else-if="!groups.length" style="padding:16px 18px;font-size:12.5px;color:#7d8b9c">No groups yet.</div>
          <div v-for="g in groups" :key="g.id" style="display:grid;grid-template-columns:1.5fr 1fr 1.1fr 0.8fr 0.8fr 0.8fr 1fr 50px;gap:12px;padding:12px 18px;border-bottom:1px solid #f0f4f8;align-items:center" v-pseudo:hover="'background:#f9fbfd'">
            <div style="min-width:0">
              <div style="font-size:13px;font-weight:600">{{ g.name }}</div>
              <div style="font-size:11px;color:#8b98a8;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">{{ g.description }}</div>
            </div>
            <div style="font-size:12.5px;color:#4a5866">{{ branchText(g) }}</div>
            <div style="font-size:12.5px;color:#4a5866">{{ g.price_group || '—' }}</div>
            <div style="font-family:'IBM Plex Mono',monospace;font-size:12.5px;text-align:right">{{ g.discount }}%</div>
            <div style="font-family:'IBM Plex Mono',monospace;font-size:12.5px;text-align:right;color:#4a5866">{{ g.terms || '—' }}</div>
            <div style="font-family:'IBM Plex Mono',monospace;font-size:12.5px;text-align:right">{{ g.contacts_count }}</div>
            <div style="text-align:right"><span :style="'font-family:\'IBM Plex Mono\',monospace;font-size:12.5px;font-weight:600;border-radius:5px;padding:3px 7px;' + (g.balance_due > 0 ? 'background:#fdeaea;color:#b02020;' : 'color:#9aa7b6;')">{{ g.balance_due > 0 ? money(g.balance_due) : '—' }}</span></div>
            <div style="display:flex;justify-content:flex-end">
              <div v-if="can('contacts.edit')" @click="openForm(g.id)" title="Edit" style="width:26px;height:26px;border-radius:6px;border:1px solid #e4eaf1;display:grid;place-items:center;font-size:11px;color:#5d6b7d;cursor:pointer" v-pseudo:hover="'background:#f2f6fa;color:var(--c-primary)'">✎</div>
            </div>
          </div>
        </div>
        <div style="display:flex;gap:10px;align-items:flex-start;background:var(--c-primary-soft);border:1px solid #cfe0f5;border-radius:10px;padding:13px 15px;font-size:12.5px;color:var(--c-primary-text);line-height:1.55;margin-top:14px;max-width:760px">
          <div style="font-weight:700;flex:none">i</div>
          <div>A group sets the selling price group, the default discount and the payment terms in one place. Put a customer in a group in the contact form.</div>
        </div>
      </div>

      <!-- Add / edit group -->
      <template v-if="form.open">
        <div @click="form.open = false" style="position:fixed;inset:0;background:rgba(9,22,35,.48);z-index:40"></div>
        <div style="position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);width:440px;max-width:calc(100vw - 32px);background:#ffffff;border-radius:16px;z-index:41;box-shadow:0 30px 80px -24px rgba(9,22,35,.65);padding:22px">
          <div style="font-size:17px;font-weight:700;margin-bottom:16px">{{ form.id ? 'Edit group' : 'New group' }}</div>
          <div style="display:grid;gap:12px">
            <label :style="labelStyle">Name <input v-model="form.name" :style="inputStyle" placeholder="Wholesale" /></label>
            <label :style="labelStyle">Branch
              <select v-model="form.branch_id" :disabled="contactsOwnBranch" :style="inputStyle">
                <option v-if="!contactsOwnBranch" :value="null">All branches</option>
                <option v-for="b in branchOptions" :key="b.id" :value="b.id">{{ b.name }}</option>
              </select>
            </label>
            <label :style="labelStyle">Description <input v-model="form.description" :style="inputStyle" placeholder="Optional" /></label>
            <label :style="labelStyle">Price group
              <select v-model="form.price_group" :style="inputStyle">
                <option v-for="p in PRICE_GROUPS" :key="p" :value="p">{{ p }}</option>
              </select>
            </label>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
              <label :style="labelStyle">Discount % <input v-model="form.discount" :style="inputStyle" /></label>
              <label :style="labelStyle">Terms
                <select v-model="form.terms" :style="inputStyle">
                  <option v-for="t in TERMS" :key="t" :value="t">{{ t }}</option>
                </select>
              </label>
            </div>
          </div>
          <div v-if="form.error" style="margin-top:12px;font-size:12px;color:#b02020;font-weight:600">{{ form.error }}</div>
          <div style="display:flex;gap:9px;margin-top:20px">
            <div v-if="form.id && can('contacts.delete')" @click="remove" style="height:38px;padding:0 14px;border-radius:9px;border:1px solid #f3cfcf;background:#fdf4f4;color:#b02020;display:flex;align-items:center;font-size:13px;font-weight:600;cursor:pointer">Delete</div>
            <div style="flex:1"></div>
            <div @click="form.open = false" style="height:38px;padding:0 16px;border-radius:9px;border:1px solid #dfe6ee;display:flex;align-items:center;font-size:13px;font-weight:600;color:#4a5866;cursor:pointer">Cancel</div>
            <div @click="save" :style="'height:38px;padding:0 16px;border-radius:9px;background:var(--c-primary);color:#ffffff;display:flex;align-items:center;font-size:13px;font-weight:700;cursor:pointer;' + (form.saving ? 'opacity:.6;pointer-events:none;' : '')">{{ form.saving ? 'Saving…' : 'Save' }}</div>
          </div>
        </div>
      </template>
    </div>
  </template>
</template>

<script setup lang="ts">
// Contacts > Customer groups. API: GET/POST/PUT/DELETE /api/customer-groups
import { computed, onMounted, reactive, ref } from 'vue';
import { usePageVm } from '../../erp/useErpPage';
import { api } from '../../api/http.js';
import { can, currentUser } from '../../api/auth.js';
import { groups, loadGroups, money, contactsOwnBranch, branchText } from '../../api/contacts.js';
import { branches, loadBranches } from '../../api/branches.js';

const vm = usePageVm();

const PRICE_GROUPS = ['Default selling price', 'Wholesale ex-GST', 'Cost price', 'Cost + 10%'];
const TERMS = ['On sale', 'Prepaid', '7 days', '14 days', '30 days', 'Payroll deduct'];
const labelStyle = 'display:grid;gap:5px;font-size:12px;font-weight:600';
const inputStyle = "width:100%;height:36px;border:1px solid #dfe6ee;border-radius:8px;padding:0 11px;font-family:'Public Sans',sans-serif;font-size:13px;font-weight:400;outline:none;background:#ffffff";

const loading = ref(true);
onMounted(async () => {
  try { await loadGroups(); await loadBranches(); } catch (e) { /* the error bar shows why */ }
  loading.value = false;
});

// A user limited to their branch only sees their own branch in the form.
const branchOptions = computed(() => branches.value.filter((b: any) => b.active && (!contactsOwnBranch.value || b.id === (currentUser.value && currentUser.value.branch_id))));

const form = reactive({ open: false, id: 0, saving: false, error: '', name: '', branch_id: null as number | null, description: '', price_group: PRICE_GROUPS[0], discount: '0', terms: TERMS[0] });

function openForm(id: number) {
  const g = groups.value.find(x => x.id === id);
  Object.assign(form, {
    open: true, id, error: '',
    name: g ? g.name : '',
    branch_id: g ? g.branch_id : (currentUser.value && currentUser.value.branch_id ? currentUser.value.branch_id : null),
    description: g ? g.description || '' : '',
    price_group: g ? g.price_group || PRICE_GROUPS[0] : PRICE_GROUPS[0],
    discount: g ? String(g.discount) : '0', terms: g ? g.terms || TERMS[0] : TERMS[0]
  });
}

async function save() {
  if (!form.name.trim()) { form.error = 'Enter a name.'; return; }
  form.saving = true;
  form.error = '';
  const body = { name: form.name.trim(), branch_id: form.branch_id, description: form.description || null, price_group: form.price_group, discount: Number(form.discount) || 0, terms: form.terms };
  try {
    if (form.id) await api.put('/customer-groups/' + form.id, body);
    else await api.post('/customer-groups', body);
    await loadGroups();
    form.open = false;
  } catch (e: any) {
    form.error = e.message;
  }
  form.saving = false;
}

async function remove() {
  if (!confirm('Delete the group ' + form.name + '?')) return;
  form.error = '';
  try {
    await api.del('/customer-groups/' + form.id);
    await loadGroups();
    form.open = false;
  } catch (e: any) {
    form.error = e.message; // e.g. "Wholesale still has 4 contacts."
  }
}
</script>
