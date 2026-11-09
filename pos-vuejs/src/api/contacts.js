// Contacts and customer groups from the POS API (GET /api/contacts, /api/customer-groups),
// shared by the contact list, contact details, the add/edit form and Customer groups.
import { computed, reactive, ref } from 'vue';
import { api } from './http.js';
import { can, currentUser } from './auth.js';

// A user with a branch only works with their branch's contacts and the all-branch ones,
// unless they have contacts.all-locations. Then the branch cannot be changed in the forms.
export const contactsOwnBranch = computed(() => !!(currentUser.value && currentUser.value.branch_id && !can('contacts.all-locations')));

// "Main branch", or "All branches" for a contact or group with no branch.
export function branchText(x) {
  return x && x.branch ? x.branch.name : 'All branches';
}

/** @type {import('vue').Ref<any[]>} */
export const contacts = ref([]);
/** @type {import('vue').Ref<any[]>} */
export const groups = ref([]);

export async function loadContacts() {
  contacts.value = await api.get('/contacts');
  return contacts.value;
}

export async function loadGroups() {
  groups.value = await api.get('/customer-groups');
  return groups.value;
}

// The add / edit contact form. It is in ContactsPage.vue, so the list and the details page can both open it.
// id = 0 adds a new contact.
export const contactForm = reactive({ open: false, id: 0 });

export function openContactForm(id = 0) {
  contactForm.id = id;
  contactForm.open = true;
}

// 1842.45 -> "1,842.45"
export function money(n) {
  return (n || 0).toLocaleString('en-AU', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

// "Al Amin Catering" -> "AC"
export function initials(name) {
  return name.split(' ').map(x => x[0]).join('').slice(0, 2).toUpperCase();
}
