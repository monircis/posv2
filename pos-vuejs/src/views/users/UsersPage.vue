<template>
  <div>
    <RouterView />

    <!-- overlays -->
    <template v-if="vm.userFormOpen">
      <div @click="closeForm" style="position:absolute;inset:0;background:rgba(9,22,35,.42);z-index:20"></div>
      <form @submit.prevent="saveUser" style="position:absolute;top:0;right:0;bottom:0;width:520px;background:#ffffff;z-index:21;box-shadow:-18px 0 40px -20px rgba(15,28,43,.4);display:flex;flex-direction:column">
        <div style="flex:none;padding:18px 22px;border-bottom:1px solid #dfe6ee;display:flex;align-items:center;gap:14px">
          <div>
            <div style="font-size:16px;font-weight:700;letter-spacing:-0.015em">{{ form.id ? 'Edit user' : 'Add user' }}</div>
            <div style="font-size:12px;color:#5d6b7d;margin-top:2px">{{ form.id ? form.email : 'Without a password the user is saved as invited' }}</div>
          </div>
          <div @click="closeForm" style="margin-left:auto;width:30px;height:30px;border-radius:7px;border:1px solid #dfe6ee;display:grid;place-items:center;cursor:pointer;color:#5d6b7d;font-size:14px" v-pseudo:hover="'background:#f2f6fa'">×</div>
        </div>
        <div style="flex:1;overflow-y:auto;padding:20px 22px">
          <div v-if="formError" style="background:#fdeaea;color:#b02020;border-radius:9px;padding:10px 13px;font-size:12.5px;margin-bottom:16px">{{ formError }}</div>
          <div style="font-family:'IBM Plex Mono',monospace;font-size:9.5px;letter-spacing:0.09em;text-transform:uppercase;color:#7d8b9c;margin-bottom:12px">Identity</div>
          <div style="display:grid;gap:14px;margin-bottom:26px">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
              <div><label style="display:block;font-size:12px;font-weight:600;margin-bottom:5px">First name <span style="color:#d93a3a">*</span></label> <input v-model="form.first" placeholder="Sara" style="width:100%;height:36px;border:1px solid #dfe6ee;border-radius:8px;padding:0 11px;font-family:'Public Sans',sans-serif;font-size:13px;outline:none" v-pseudo:focus="'border-color:var(--c-primary)'" /></div>
              <div><label style="display:block;font-size:12px;font-weight:600;margin-bottom:5px">Last name</label> <input v-model="form.last" placeholder="Rahman" style="width:100%;height:36px;border:1px solid #dfe6ee;border-radius:8px;padding:0 11px;font-family:'Public Sans',sans-serif;font-size:13px;outline:none" v-pseudo:focus="'border-color:var(--c-primary)'" /></div>
            </div>
            <div><label style="display:block;font-size:12px;font-weight:600;margin-bottom:5px">Email <span style="color:#d93a3a">*</span></label> <input v-model="form.email" type="email" placeholder="name@madinah.com.au" style="width:100%;height:36px;border:1px solid #dfe6ee;border-radius:8px;padding:0 11px;font-family:'Public Sans',sans-serif;font-size:13px;outline:none" v-pseudo:focus="'border-color:var(--c-primary)'" /> <div style="font-size:11.5px;color:#7d8b9c;margin-top:5px">Used to sign in.</div></div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
              <div><label style="display:block;font-size:12px;font-weight:600;margin-bottom:5px">Phone</label> <input v-model="form.phone" placeholder="+61" style="width:100%;height:36px;border:1px solid #dfe6ee;border-radius:8px;padding:0 11px;font-family:'IBM Plex Mono',monospace;font-size:12.5px;outline:none" v-pseudo:focus="'border-color:var(--c-primary)'" /></div>
              <div><label style="display:block;font-size:12px;font-weight:600;margin-bottom:5px">POS PIN</label> <input v-model="form.pin" placeholder="4 digits" maxlength="4" style="width:100%;height:36px;border:1px solid #dfe6ee;border-radius:8px;padding:0 11px;font-family:'IBM Plex Mono',monospace;font-size:12.5px;outline:none" v-pseudo:focus="'border-color:var(--c-primary)'" /></div>
            </div>
            <div><label style="display:block;font-size:12px;font-weight:600;margin-bottom:5px">{{ form.id ? 'New password' : 'Password' }}</label> <input v-model="form.password" type="password" autocomplete="new-password" :placeholder="form.id ? 'Leave blank to keep the current password' : 'Leave blank to invite'" style="width:100%;height:36px;border:1px solid #dfe6ee;border-radius:8px;padding:0 11px;font-family:'Public Sans',sans-serif;font-size:13px;outline:none" v-pseudo:focus="'border-color:var(--c-primary)'" /></div>
          </div>
          <div style="font-family:'IBM Plex Mono',monospace;font-size:9.5px;letter-spacing:0.09em;text-transform:uppercase;color:#7d8b9c;margin-bottom:12px">Access</div>
          <div style="display:grid;gap:12px;margin-bottom:14px">
            <div><label style="display:block;font-size:12px;font-weight:600;margin-bottom:6px">Role <span style="color:#d93a3a">*</span></label> <div style="display:grid;gap:7px">
              <template v-for="r in assignableRoles" :key="r.name">
                <div @click="form.role = r.name" :style="'display:flex;align-items:flex-start;gap:11px;padding:11px 13px;border-radius:9px;cursor:pointer;' + (form.role === r.name ? 'border:1px solid var(--c-primary);background:var(--c-primary-tint);' : 'border:1px solid #dfe6ee;')">
                  <div :style="'width:17px;height:17px;border-radius:50%;display:grid;place-items:center;flex:none;margin-top:1px;border:1.5px solid ' + (form.role === r.name ? 'var(--c-primary)' : '#c2cedb')">
                    <div :style="'width:8px;height:8px;border-radius:50%;background:' + (form.role === r.name ? 'var(--c-primary)' : 'transparent')"></div>
                  </div>
                  <div style="flex:1;min-width:0">
                    <div style="font-size:12.5px;font-weight:600">{{ r.name }}</div>
                    <div style="font-size:11.5px;color:#7d8b9c;line-height:1.45;margin-top:1px">{{ r.permissions.length }} permissions · {{ r.users }} {{ r.users === 1 ? 'user' : 'users' }}</div>
                  </div>
                </div>
              </template>
            </div></div>
            <div><label style="display:block;font-size:12px;font-weight:600;margin-bottom:5px">Branch <span style="color:#d93a3a">*</span></label>
              <select v-model="form.branchId" style="width:100%;height:36px;border:1px solid #dfe6ee;border-radius:8px;padding:0 9px;font-family:'Public Sans',sans-serif;font-size:13px;background:#ffffff">
                <option value="">All branches</option>
                <option v-for="b in branches.filter(b => b.active || b.id === form.branchId)" :key="b.id" :value="b.id">{{ b.name }}</option>
              </select>
            </div>
          </div>
          <div style="display:flex;gap:10px;align-items:flex-start;background:var(--c-primary-soft);border:1px solid #cfe0f5;border-radius:9px;padding:11px 13px;font-size:12.5px;color:var(--c-primary-text);line-height:1.55">
            <div style="font-weight:700;flex:none">i</div>
            <div>Permissions come from the role. Change what a role can do under <strong>Roles &amp; permissions</strong>.</div>
          </div>
        </div>
        <div style="flex:none;padding:14px 22px;border-top:1px solid #dfe6ee;display:flex;align-items:center;gap:10px">
          <div style="font-size:12px;color:#7d8b9c;flex:1">{{ form.id ? '' : form.password ? 'Can sign in straight away' : 'Saved as invited' }}</div>
          <div @click="closeForm" style="height:36px;padding:0 15px;border-radius:8px;border:1px solid #dfe6ee;display:flex;align-items:center;font-size:13px;font-weight:500;color:#4a5866;cursor:pointer" v-pseudo:hover="'background:#f2f6fa'">Cancel</div>
          <button type="submit" :disabled="saving" style="height:36px;padding:0 17px;border:0;border-radius:8px;background:var(--c-primary);color:#ffffff;display:flex;align-items:center;font-family:inherit;font-size:13px;font-weight:600;cursor:pointer" v-pseudo:hover="'background:var(--c-primary-hover)'">{{ saving ? 'Saving…' : form.id ? 'Save changes' : 'Add user' }}</button>
        </div>
      </form>
    </template>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, provide, reactive, ref } from 'vue';
import { useErpPage, useScreen } from '../../erp/useErpPage';
import { DATA, replaceRows } from '../../erp/logic/app.js';
import { authApi } from '../../api/http.js';
import { can, currentUser, refreshUser } from '../../api/auth.js';
import { branches, loadBranches } from '../../api/branches.js';

const vm = useErpPage('users', 'userNav');
const screen = useScreen();

// ---------------------------------------------------------------------------
// Auth service API — users: GET/POST/PUT/DELETE /api/users, roles: GET/PUT /api/roles
// ---------------------------------------------------------------------------

type User = { id: number; name: string; email: string; phone: string | null; branch_id: number | null; status: string; role: string; permissions: string[]; last_login_at: string | null };
type Role = { name: string; built_in: boolean; users: number; permissions: string[] };

const users = ref<User[]>([]);
const roles = ref<Role[]>([]);
const selected = ref<User | null>(null); // user shown on the details screen

// The permission grid: one row per screen (code on the grid), five actions per row.
const SCREENS: Record<string, string> = {
  Hm: 'home', Us: 'users', Ct: 'contacts', Pr: 'products', Mf: 'mfg', Pu: 'purchases', Sl: 'sell', St: 'transfers', Sa: 'adjust',
  Ex: 'expenses', Pa: 'accounts', Ac: 'accounting', Rp: 'reports', Bk: 'bookings', Kt: 'kitchen', Or: 'orders', Se: 'settings', Hr: 'hrm'
};
const ACTIONS = ['view', 'create', 'edit', 'delete', 'all-locations'];

const grantsOf = (permissions: string[]) =>
  Object.fromEntries(Object.entries(SCREENS).map(([code, s]) => [code, ACTIONS.map(a => (permissions.includes(s + '.' + a) ? 1 : 0))]));

const permissionsOf = (grants: Record<string, number[]>) =>
  Object.entries(grants).flatMap(([code, row]) => ACTIONS.filter((_, i) => row[i]).map(a => SCREENS[code] + '.' + a));

// Permissions outside the grid, shown as ticks under it ("Special permissions").
// "See everyone's ..." per screen. Without it, a user only sees (and opens) the records they made.
const EXTRA_PERMISSIONS = [
  { name: 'sell.view-all', label: 'Sell: see everyone\u2019s sales and cash registers', hint: 'Without it, the user only sees the sales and registers they made.' },
  { name: 'purchases.view-all', label: 'Purchases: see everyone\u2019s purchases and returns', hint: 'Without it, only the purchases they made.' },
  { name: 'expenses.view-all', label: 'Expenses: see everyone\u2019s expenses', hint: 'Without it, only the expenses they added.' },
  { name: 'transfers.view-all', label: 'Stock transfers: see everyone\u2019s transfers', hint: 'Without it, only the transfers they made.' },
  { name: 'adjust.view-all', label: 'Stock adjustment: see everyone\u2019s adjustments', hint: 'Without it, only the adjustments they made.' },
  { name: 'mfg.view-all', label: 'Manufacturing: see everyone\u2019s production orders', hint: 'Without it, only the orders they planned.' },
  { name: 'bookings.view-all', label: 'Bookings: see everyone\u2019s bookings', hint: 'Without it, only the bookings they took (front desk staff usually need all).' },
  // HRM extras
  { name: 'hrm.approve', label: 'HRM: approve as HR manager', hint: 'Decides the HR manager step of leave, overtime, attendance fixes, deductions and payroll.' },
  { name: 'hrm.payroll', label: 'HRM: salary and payroll', hint: 'Salary structures, deductions, bonuses, loans, monthly payroll and payslips.' },
  { name: 'hrm.admin', label: 'HRM: administrator', hint: 'Decides any approval step, changes approval workflows and HRM settings.' },
];
// role name => the extra permissions it has (kept apart from the grid, added back when saving)
const extras = reactive<Record<string, string[]>>({});

function toggleExtra(role: string, permission: string) {
  const list = extras[role] || [];
  extras[role] = list.includes(permission) ? list.filter(p => p !== permission) : list.concat([permission]);
}

function lastActive(iso: string | null) {
  if (!iso) return 'never';
  const min = Math.round((Date.now() - new Date(iso).getTime()) / 60000);
  if (min < 2) return 'now';
  if (min < 60) return min + ' min ago';
  if (min < 60 * 24) return Math.round(min / 60) + ' hr ago';
  return Math.round(min / 60 / 24) + ' days ago';
}

async function loadUsers() {
  users.value = await authApi.get('/users');
  // The list screen reads DATA.users (see erp/logic/app.js).
  replaceRows(DATA.users, users.value.map(u => ({
    name: u.name, email: u.email, role: u.role || '—', locations: branches.value.find(b => b.id === u.branch_id)?.name || 'All',
    lastActive: lastActive(u.last_login_at), status: u.status
  })));
  if (selected.value) selected.value = users.value.find(u => u.id === selected.value!.id) || null;
  screen.refresh();
}

async function loadRoles() {
  roles.value = await authApi.get('/roles');
  roles.value.forEach(r => { extras[r.name] = r.permissions.filter(p => EXTRA_PERMISSIONS.some(x => x.name === p)); });
  // The roles and permission screens read DATA.roles; "grants" fills the grid.
  replaceRows(DATA.roles, roles.value.map(r => {
    const old = DATA.roles.find((x: any) => x.name === r.name);
    const screens = new Set(r.permissions.map(p => p.split('.')[0])).size;
    return {
      name: r.name, kind: r.built_in ? 'Built in' : 'Custom', users: r.users,
      perms: r.name === 'Owner' ? 'All 18 modules' : screens + (screens === 1 ? ' module' : ' modules'),
      desc: old ? old.desc : r.permissions.length + ' permissions',
      grants: grantsOf(r.permissions)
    };
  }));
  screen.setState({ grants: null });
}

onMounted(() => {
  // Branch names come from the POS backend, so load them before the user list.
  loadBranches().catch(() => {}).finally(() => loadUsers().catch(() => {}));
  loadRoles().catch(() => {});
});

// ---- Add / edit user (the drawer above) ----
const form = reactive({ id: 0, first: '', last: '', email: '', phone: '', pin: '', password: '', role: 'Cashier', branchId: '' as number | '' });
const formError = ref('');
const saving = ref(false);
const assignableRoles = computed(() => roles.value.filter(r => r.name !== 'Owner' || form.role === 'Owner'));

function openForm(user?: User) {
  const [first, ...rest] = (user?.name || '').split(' ');
  Object.assign(form, {
    id: user?.id || 0, first, last: rest.join(' '), email: user?.email || '', phone: user?.phone || '',
    pin: '', password: '', role: user?.role || 'Cashier', branchId: user?.branch_id ?? ''
  });
  formError.value = '';
  screen.setState({ formOpen: true });
}

function closeForm() {
  screen.setState({ formOpen: false });
}

async function saveUser() {
  formError.value = '';
  if (!form.first.trim() || !form.email.trim()) { formError.value = 'First name and email are required.'; return; }
  const body: Record<string, any> = {
    name: (form.first.trim() + ' ' + form.last.trim()).trim(), email: form.email.trim(),
    phone: form.phone || null, role: form.role, branch_id: form.branchId || null
  };
  if (form.pin) body.pin = form.pin;
  if (form.password) body.password = form.password;

  saving.value = true;
  try {
    if (form.id) await authApi.put('/users/' + form.id, body);
    else await authApi.post('/users', body);
    closeForm();
    await loadUsers();
    await loadRoles(); // user counts per role
    if (form.id === currentUser.value?.id) await refreshUser();
  } catch (e: any) {
    formError.value = e.message;
  } finally {
    saving.value = false;
  }
}

// ---- User details screen ----
function openDetails(email: string) {
  selected.value = users.value.find(u => u.email === email) || null;
  screen.setState({ sub: 'details' });
}

async function setStatus(user: User, status: string) {
  await authApi.put('/users/' + user.id, { status }).catch(() => {});
  await loadUsers();
}

async function deleteUser(user: User) {
  if (!confirm('Delete ' + user.name + '? This cannot be undone.')) return;
  try {
    await authApi.del('/users/' + user.id);
    selected.value = null;
    screen.setState({ sub: 'list' });
    await loadUsers();
    await loadRoles();
  } catch (e) {}
}

// ---- Roles & permissions screen ----
function openRole(name: string) {
  screen.setState({ preset: name, grants: null, sub: 'matrix' });
}

async function newRole() {
  const name = (prompt('Name of the new role') || '').trim();
  if (!name) return;
  try {
    await authApi.post('/roles', { name, permissions: [] });
    await loadRoles();
    openRole(name);
  } catch (e) {}
}

async function saveRole() {
  const name = screen.state.preset;
  const role: any = DATA.roles.find((r: any) => r.name === name);
  const grants = screen.state.grants || role?.grants;
  if (!grants) return;
  try {
    await authApi.put('/roles/' + encodeURIComponent(name), { permissions: permissionsOf(grants).concat(extras[name] || []) });
    await loadRoles();
    await refreshUser(); // the signed-in user's permissions may have changed
  } catch (e) {}
}

// The screens under this page (UserList, UserDetails, UserRoles, UserPermissions) use these.
provide('users', reactive({
  users, roles, selected, can,
  summary: computed(() => {
    if (!users.value.length) return 'Loading users…';
    const n = (s: string) => users.value.filter(u => u.status === s).length;
    return users.value.length + ' users · ' + n('active') + ' active, ' + n('invited') + ' invited, ' + n('off') + ' deactivated';
  }),
  rolesSummary: computed(() => {
    const builtIn = roles.value.filter(r => r.built_in).length;
    return roles.value.length + ' roles · ' + builtIn + ' built in, ' + (roles.value.length - builtIn) + ' custom';
  }),
  openForm, openDetails, setStatus, deleteUser, openRole, newRole, saveRole,
  extras, extraPermissions: EXTRA_PERMISSIONS, toggleExtra,
  cancelRole: () => screen.setState({ grants: null, sub: 'roles' }),
  editByEmail: (email: string) => openForm(users.value.find(u => u.email === email))
}));
</script>
