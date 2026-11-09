// The signed-in user and their permissions (Spatie, e.g. "products.view").
// Sign-in is done by the auth service; its token also works on the POS backend.
import { ref } from 'vue';
import { authApi, getRefreshToken, getToken, setToken, whenRenewed, whenUnauthorized } from './http.js';

/** @type {import('vue').Ref<null | { id: number, name: string, email: string, phone: string | null, branch_id: number | null, status: string, role: string, permissions: string[] }>} */
export const currentUser = ref(null);

export const isSignedIn = () => !!getToken();

export function can(permission) {
  return !!currentUser.value && currentUser.value.permissions.includes(permission);
}

// Where each role lands after signing in (and when a signed-in user opens the sign-in page).
const HOME = { Owner: '/', Manager: '/', Accountant: '/accounting', 'Stock keeper': '/products', Cashier: '/pos', Kitchen: '/kitchen' };

// The page that asked for sign-in (?next=), else the role's home page.
export function homeFor(role, next) {
  if (typeof next === 'string' && /^\/[a-z0-9\-/]*$/.test(next) && !next.startsWith('/login') && !next.startsWith('/register')) return next;
  return HOME[role] || '/';
}

export async function login(email, password, remember = true) {
  const res = await authApi.post('/auth/login', { email, password });
  setToken(res.token, remember, res.refresh_token);
  currentUser.value = res.user;
  return res.user;
}

export async function register(form) {
  const res = await authApi.post('/auth/register', form);
  setToken(res.token, undefined, res.refresh_token);
  currentUser.value = res.user;
  return res.user;
}

// Loads the user for a token saved in an earlier visit.
export async function loadUser() {
  if (currentUser.value || !getToken()) return currentUser.value;
  const res = await authApi.get('/auth/me');
  currentUser.value = res.user;
  return res.user;
}

// Reloads the signed-in user, e.g. after their role's permissions changed.
// The POS backend reads permissions from the token, so this gets a new token too.
export async function refreshUser() {
  const res = await authApi.post('/auth/refresh');
  setToken(res.token);
  currentUser.value = res.user;
  return res.user;
}

export async function logout() {
  // The refresh token stops working too, even if the short-lived token has already run out.
  const refreshToken = getRefreshToken();
  if (refreshToken) { try { await authApi.post('/auth/token/revoke', { refresh_token: refreshToken }); } catch (e) {} }
  try { await authApi.post('/auth/logout'); } catch (e) {}
  setToken(null);
  currentUser.value = null;
}

// A token renewed with the refresh token brings the user's current role and permissions.
whenRenewed(user => {
  if (user) currentUser.value = user;
});

// On the POS screen, signing out shows a sign-in window over the screen instead of the sign-in page,
// so the order on screen is kept and the next cashier can sign in straight away.
// lastEmail fills in the email; lastRemember keeps "Keep me signed in" as it was.
export const posSignedOut = ref(false);
export const posLast = { email: '', remember: true };

const onPos = () => window.location.hash.startsWith('#/pos');

function rememberLast() {
  posLast.email = currentUser.value ? currentUser.value.email : posLast.email;
  try { posLast.remember = !!localStorage.getItem('erp.token'); } catch (e) { /* keep the last value */ }
}

// Sign out from the POS: the sign-in window opens over the sales screen.
export async function posSignOut() {
  rememberLast();
  await logout();
  posSignedOut.value = true;
}

// An expired or rejected token signs the user out (the router then shows /login, or the POS its sign-in window).
whenUnauthorized(() => {
  if (onPos()) {
    rememberLast();
    setToken(null);
    currentUser.value = null;
    posSignedOut.value = true;
    return;
  }
  setToken(null);
  currentUser.value = null;
  window.location.hash = '#/login?next=' + encodeURIComponent(window.location.hash.slice(1) || '/');
});
