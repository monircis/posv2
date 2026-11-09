<template>
  <div>
    <RouterView />

    <!-- Add / edit contact (opened from the list and from the details page) -->
    <template v-if="contactForm.open">
      <div @click="close" style="position:absolute;inset:0;background:rgba(9,22,35,.42);z-index:20"></div>
      <div style="position:absolute;top:0;right:0;bottom:0;width:540px;max-width:100%;background:#ffffff;z-index:21;box-shadow:-18px 0 40px -20px rgba(15,28,43,.4);display:flex;flex-direction:column">
        <div style="flex:none;padding:18px 22px;border-bottom:1px solid #dfe6ee;display:flex;align-items:center;gap:14px">
          <div>
            <div style="font-size:16px;font-weight:700;letter-spacing:-0.015em">{{ contactForm.id ? 'Edit contact' : 'Add contact' }}</div>
            <div style="font-size:12px;color:#5d6b7d;margin-top:2px">One form for customers, suppliers and both</div>
          </div>
          <div @click="close" style="margin-left:auto;width:30px;height:30px;border-radius:7px;border:1px solid #dfe6ee;display:grid;place-items:center;cursor:pointer;color:#5d6b7d;font-size:14px" v-pseudo:hover="'background:#f2f6fa'">×</div>
        </div>

        <div style="flex:1;overflow-y:auto;padding:20px 22px">
          <div :style="sectionTitle">Contact type</div>
          <div style="display:flex;gap:7px;margin-bottom:9px">
            <div v-for="t in TYPES" :key="t.name" @click="f.type = t.name" :style="'flex:1;height:38px;border-radius:9px;display:grid;place-items:center;font-size:13px;cursor:pointer;' + (f.type === t.name ? 'background:var(--c-primary);color:#ffffff;font-weight:700;' : 'border:1px solid #dfe6ee;color:#4a5866;font-weight:500;')">{{ t.name }}</div>
          </div>
          <div style="font-size:11.5px;color:#7d8b9c;line-height:1.5;margin-bottom:24px">{{ typeInfo.hint }}</div>

          <div :style="sectionTitle">Branch</div>
          <div style="margin-bottom:24px">
            <select v-model="f.branch_id" :disabled="contactsOwnBranch" :style="inputStyle">
              <option v-if="!contactsOwnBranch" :value="null">All branches</option>
              <option v-for="b in branchOptions" :key="b.id" :value="b.id">{{ b.name }}</option>
            </select>
            <div style="font-size:11.5px;color:#7d8b9c;margin-top:5px">{{ f.branch_id ? 'Only this branch can use this contact.' : 'Every branch can use this contact.' }}</div>
          </div>

          <div :style="sectionTitle">Identity</div>
          <div style="display:grid;gap:14px;margin-bottom:24px">
            <div><label :style="labelStyle">Business name</label> <input v-model="f.name" placeholder="Al Amin Catering" :style="inputStyle" /> <div style="font-size:11.5px;color:#7d8b9c;margin-top:5px">Leave blank for an individual. One of business or contact name is required.</div></div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
              <div><label :style="labelStyle">Contact name</label> <input v-model="f.contact_name" placeholder="Ibrahim Khalil" :style="inputStyle" /></div>
              <div><label :style="labelStyle">Mobile <span style="color:#d93a3a">*</span></label> <input v-model="f.phone" placeholder="+61" :style="inputStyle + monoStyle" /></div>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
              <div><label :style="labelStyle">Email</label> <input v-model="f.email" placeholder="name@business.com.au" :style="inputStyle" /></div>
              <div><label :style="labelStyle">ABN</label> <input v-model="f.abn" placeholder="11 digits" :style="inputStyle + monoStyle" /></div>
            </div>
          </div>

          <template v-if="f.type === 'Customer' || f.type === 'Both'">
            <div :style="sectionTitle">Customer terms</div>
            <div style="display:grid;gap:14px;margin-bottom:24px">
              <div>
                <label :style="labelStyle">Customer group</label>
                <select v-model="f.customer_group_id" :style="inputStyle">
                  <option :value="null">— No group —</option>
                  <option v-for="g in groupOptions" :key="g.id" :value="g.id">{{ g.name }}{{ g.branch ? '' : ' (all branches)' }}</option>
                </select>
                <div style="font-size:11.5px;color:#7d8b9c;margin-top:5px">Sets price group, default discount and payment terms.</div>
              </div>
              <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
                <div><label :style="labelStyle">Credit limit</label> <input v-model="f.credit_limit" :style="inputStyle + monoStyle + 'text-align:right;'" /></div>
                <div v-if="!contactForm.id"><label :style="labelStyle">Opening balance</label> <input v-model="f.opening_balance" :style="inputStyle + monoStyle + 'text-align:right;'" /></div>
              </div>
            </div>
          </template>

          <template v-if="f.type === 'Supplier' || f.type === 'Both'">
            <div :style="sectionTitle">Supplier terms</div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:24px">
              <div>
                <label :style="labelStyle">Pay term</label>
                <select v-model="f.pay_term" :style="inputStyle">
                  <option :value="null">—</option>
                  <option v-for="t in PAY_TERMS" :key="t" :value="t">{{ t }}</option>
                </select>
              </div>
              <div v-if="!contactForm.id"><label :style="labelStyle">Advance balance</label> <input v-model="f.advance_balance" :style="inputStyle + monoStyle + 'text-align:right;'" /></div>
            </div>
          </template>

          <div :style="sectionTitle">Address</div>
          <div style="display:grid;gap:14px">
            <div><label :style="labelStyle">Street</label> <input v-model="f.street" placeholder="14 Haldon Street" :style="inputStyle" /></div>
            <div style="display:grid;grid-template-columns:1.4fr 1fr 0.8fr;gap:14px">
              <div><label :style="labelStyle">Suburb</label> <input v-model="f.suburb" placeholder="Lakemba" :style="inputStyle" /></div>
              <div>
                <label :style="labelStyle">State</label>
                <select v-model="f.state" :style="inputStyle">
                  <option :value="null">—</option>
                  <option v-for="s in STATES" :key="s" :value="s">{{ s }}</option>
                </select>
              </div>
              <div><label :style="labelStyle">Postcode</label> <input v-model="f.postcode" placeholder="2195" :style="inputStyle + monoStyle" /></div>
            </div>
            <div><label :style="labelStyle">Note</label> <input v-model="f.note" placeholder="Optional" :style="inputStyle" /></div>
          </div>

          <div v-if="error" style="margin-top:16px;font-size:12.5px;color:#b02020;font-weight:600">{{ error }}</div>
        </div>

        <div style="flex:none;padding:14px 22px;border-top:1px solid #dfe6ee;display:flex;align-items:center;gap:10px">
          <div style="font-size:12px;color:#7d8b9c;flex:1">{{ typeInfo.footer }}</div>
          <div @click="close" style="height:36px;padding:0 15px;border-radius:8px;border:1px solid #dfe6ee;display:flex;align-items:center;font-size:13px;font-weight:500;color:#4a5866;cursor:pointer" v-pseudo:hover="'background:#f2f6fa'">Cancel</div>
          <div v-if="!contactForm.id" @click="save(true)" style="height:36px;padding:0 15px;border-radius:8px;border:1px solid #dfe6ee;display:flex;align-items:center;font-size:13px;font-weight:600;color:var(--c-primary-text);cursor:pointer" v-pseudo:hover="'background:#f2f6fa'">Save &amp; add another</div>
          <div @click="save(false)" :style="'height:36px;padding:0 17px;border-radius:8px;background:var(--c-primary);color:#ffffff;display:flex;align-items:center;font-size:13px;font-weight:600;cursor:pointer;' + (saving ? 'opacity:.6;pointer-events:none;' : '')">{{ saving ? 'Saving…' : 'Save contact' }}</div>
        </div>
      </div>
    </template>
  </div>
</template>

<script setup lang="ts">
// Contacts. The child routes are the list, details and customer groups.
// The add / edit contact form lives here. API: POST /api/contacts, PUT /api/contacts/{id}
import { computed, reactive, ref, watch } from 'vue';
import { useErpPage } from '../../erp/useErpPage';
import { api } from '../../api/http.js';
import { contacts, groups, loadContacts, loadGroups, contactForm, contactsOwnBranch } from '../../api/contacts.js';
import { branches, loadBranches } from '../../api/branches.js';
import { currentUser } from '../../api/auth.js';

useErpPage('contacts', 'contactNav');

const TYPES = [
  { name: 'Customer', hint: 'Sells to. Shows customer group, credit limit and opening balance.', footer: 'Will appear in POS customer search' },
  { name: 'Supplier', hint: 'Buys from. Shows pay term and advance balance.', footer: 'Will appear in purchase supplier search' },
  { name: 'Both', hint: 'Trades both ways — a restaurant that also supplies you. Shows both sets of terms and keeps one balance.', footer: 'One balance, sales and purchases' }
];
const PAY_TERMS = ['On delivery', '7 days', '14 days', '30 days', '60 days'];
const STATES = ['NSW', 'VIC', 'QLD', 'WA', 'SA', 'TAS', 'ACT', 'NT'];

const sectionTitle = "font-family:'IBM Plex Mono',monospace;font-size:9.5px;letter-spacing:0.09em;text-transform:uppercase;color:#7d8b9c;margin-bottom:12px";
const labelStyle = 'display:block;font-size:12px;font-weight:600;margin-bottom:5px';
const inputStyle = "width:100%;height:36px;border:1px solid #dfe6ee;border-radius:8px;padding:0 11px;font-family:'Public Sans',sans-serif;font-size:13px;outline:none;background:#ffffff;";
const monoStyle = "font-family:'IBM Plex Mono',monospace;font-size:12.5px;";

// The form fields (the same names as the API).
const empty = () => ({
  // A new contact is for the user's own branch; a user with no branch starts with "All branches".
  branch_id: (currentUser.value && currentUser.value.branch_id ? currentUser.value.branch_id : null) as number | null,
  type: 'Customer', name: '', contact_name: '', phone: '', email: '', abn: '',
  customer_group_id: null as number | null, credit_limit: '0', opening_balance: '0',
  pay_term: null as string | null, advance_balance: '0',
  street: '', suburb: '', state: null as string | null, postcode: '', note: ''
});
const f = reactive(empty());
const error = ref('');
const saving = ref(false);

const typeInfo = computed(() => TYPES.find(t => t.name === f.type) || TYPES[0]);

// Branches to pick: a user limited to their branch only sees their own.
const branchOptions = computed(() => branches.value.filter((b: any) => b.active && (!contactsOwnBranch.value || b.id === f.branch_id)));
// Groups for the chosen branch, plus the all-branch groups.
const groupOptions = computed(() => groups.value.filter((g: any) => !g.branch_id || g.branch_id === f.branch_id));
// A group of another branch cannot stay chosen when the branch changes.
watch(() => f.branch_id, () => {
  if (f.customer_group_id && !groupOptions.value.some((g: any) => g.id === f.customer_group_id)) f.customer_group_id = null;
});

// When the form opens: empty for a new contact, or filled from the contact being edited.
watch(() => contactForm.open, async open => {
  if (!open) return;
  error.value = '';
  if (!groups.value.length) await loadGroups().catch(() => {});
  if (!branches.value.length) await loadBranches().catch(() => {});
  Object.assign(f, empty());
  const c = contacts.value.find(x => x.id === contactForm.id);
  if (c) {
    Object.assign(f, {
      branch_id: c.branch_id, type: c.type, name: c.name === c.contact_name ? '' : c.name, contact_name: c.contact_name || '',
      phone: c.phone || '', email: c.email || '', abn: c.abn || '',
      customer_group_id: c.customer_group_id, credit_limit: String(c.credit_limit),
      pay_term: c.pay_term, street: c.street || '', suburb: c.suburb || '', state: c.state,
      postcode: c.postcode || '', note: c.note || ''
    });
  }
});

function close() {
  contactForm.open = false;
}

// What the API expects. Customer-only and supplier-only fields are cleared for the other type.
function body() {
  const customer = f.type === 'Customer' || f.type === 'Both';
  const supplier = f.type === 'Supplier' || f.type === 'Both';
  return {
    branch_id: f.branch_id,
    type: f.type,
    name: f.name.trim() || null,
    contact_name: f.contact_name.trim() || null,
    phone: f.phone.trim(),
    email: f.email.trim() || null,
    abn: f.abn.trim() || null,
    customer_group_id: customer ? f.customer_group_id : null,
    credit_limit: customer ? Number(String(f.credit_limit).replace(/,/g, '')) || 0 : 0,
    opening_balance: customer ? Number(String(f.opening_balance).replace(/,/g, '')) || 0 : 0,
    pay_term: supplier ? f.pay_term : null,
    advance_balance: supplier ? Number(String(f.advance_balance).replace(/,/g, '')) || 0 : 0,
    street: f.street.trim() || null,
    suburb: f.suburb.trim() || null,
    state: f.state,
    postcode: f.postcode.trim() || null,
    note: f.note.trim() || null
  };
}

async function save(addAnother: boolean) {
  if (!f.name.trim() && !f.contact_name.trim()) { error.value = 'Enter a business name or a contact name.'; return; }
  if (!f.phone.trim()) { error.value = 'Enter a mobile number.'; return; }
  saving.value = true;
  error.value = '';
  try {
    if (contactForm.id) await api.put('/contacts/' + contactForm.id, body());
    else await api.post('/contacts', body());
    await loadContacts();
    await loadGroups(); // the group counts changed
    if (addAnother) Object.assign(f, empty(), { type: f.type, branch_id: f.branch_id });
    else contactForm.open = false;
  } catch (e: any) {
    error.value = e.message; // e.g. "The phone field is required."
  }
  saving.value = false;
}
</script>
