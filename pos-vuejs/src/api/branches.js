// Branches from the API (GET /api/branches), shared by the sign-in page,
// the user form and Settings > Branches & locations.
import { ref } from 'vue';
import { api } from './http.js';

/** @type {import('vue').Ref<{ id: number, name: string, code: string, address: string | null, invoice_prefix: string | null, active: boolean, users_count: number }[]>} */
export const branches = ref([]);

export async function loadBranches() {
  branches.value = await api.get('/branches');
  return branches.value;
}
