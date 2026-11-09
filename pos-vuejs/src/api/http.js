// Calls the Laravel APIs with the signed-in user's JWT:
// the POS backend (pos/routes/api.php) and the auth service (auth/routes/api.php),
// which signs users in and owns users, roles and permissions.
import { ref } from 'vue';

export const API_URL = import.meta.env.VITE_API_URL || 'http://127.0.0.1:8001/api';
export const AUTH_URL = import.meta.env.VITE_AUTH_URL || 'http://127.0.0.1:8000/api';

const TOKEN_KEY = 'erp.token';
// The refresh token: long-lived, only used to get a new token when the short-lived one runs out.
const REFRESH_KEY = 'erp.refresh';

// "Keep me signed in" keeps the token after the browser closes (localStorage);
// otherwise it lasts for this tab only (sessionStorage).
export function getToken() {
  try { return localStorage.getItem(TOKEN_KEY) || sessionStorage.getItem(TOKEN_KEY); } catch (e) { return null; }
}

export function getRefreshToken() {
  try { return localStorage.getItem(REFRESH_KEY) || sessionStorage.getItem(REFRESH_KEY); } catch (e) { return null; }
}

// Without "remember", a new token goes where the old one was. The refresh token is kept in the same place;
// leave refreshToken out to keep the one there is. No token clears both.
export function setToken(token, remember, refreshToken) {
  try {
    if (remember === undefined) remember = !sessionStorage.getItem(TOKEN_KEY);
    const keepRefresh = refreshToken === undefined ? getRefreshToken() : refreshToken;
    localStorage.removeItem(TOKEN_KEY);
    sessionStorage.removeItem(TOKEN_KEY);
    localStorage.removeItem(REFRESH_KEY);
    sessionStorage.removeItem(REFRESH_KEY);
    if (token) {
      const store = remember ? localStorage : sessionStorage;
      store.setItem(TOKEN_KEY, token);
      if (keepRefresh) store.setItem(REFRESH_KEY, keepRefresh);
    }
  } catch (e) {}
}

// Last API error, shown in a bar at the top of the app (AdminLayout).
export const apiError = ref('');

// Called when the token is rejected and cannot be renewed (set by auth.js).
let onUnauthorized = () => {};
export function whenUnauthorized(fn) { onUnauthorized = fn; }

// Called with the user after the token was renewed with the refresh token (set by auth.js).
let onRenewed = () => {};
export function whenRenewed(fn) { onRenewed = fn; }

// Swaps the refresh token for a new token pair (POST /api/auth/token/refresh). Several calls that fail
// at the same moment share one renewal. Returns true when there is a new token to retry with.
let renewing = null;
export function renewToken() {
  if (!renewing) {
    renewing = (async () => {
      const refreshToken = getRefreshToken();
      if (!refreshToken) return false;
      try {
        const res = await fetch(AUTH_URL + '/auth/token/refresh', {
          method: 'POST',
          headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
          body: JSON.stringify({ refresh_token: refreshToken })
        });
        if (!res.ok) return false;
        const data = await res.json();
        setToken(data.token, undefined, data.refresh_token);
        onRenewed(data.user);
        return true;
      } catch (e) {
        return false;
      }
    })().finally(() => { renewing = null; });
  }
  return renewing;
}

// Calls that never retry with a renewed token: signing in and renewing itself.
const NO_RENEW = ['/auth/login', '/auth/register', '/auth/token/refresh', '/auth/token/revoke', '/auth/logout'];

export async function request(method, path, body, base = API_URL, retried = false) {
  // A FormData body (file upload) is sent as it is; the browser sets its Content-Type.
  const isForm = body instanceof FormData;
  const headers = { Accept: 'application/json' };
  if (body !== undefined && !isForm) headers['Content-Type'] = 'application/json';
  const token = getToken();
  if (token) headers.Authorization = 'Bearer ' + token;

  let res;
  try {
    res = await fetch(base + path, { method, headers, body: body === undefined ? undefined : isForm ? body : JSON.stringify(body) });
  } catch (e) {
    throw showError('Cannot reach the server at ' + base + '. Is "php artisan serve" running?');
  }

  if (res.status === 204) return null;
  const data = await res.json().catch(() => null);
  if (res.ok) return data;

  if (res.status === 401 && token && !retried && !NO_RENEW.includes(path)) {
    // The token ran out: renew it with the refresh token and try once more. Another tab may have
    // renewed it already, in which case the saved token is new and is used as it is.
    if (getToken() !== token || await renewToken()) return request(method, path, body, base, true);
  }
  if (res.status === 401 && token) onUnauthorized();
  const message = data?.errors ? Object.values(data.errors).flat().join(' ') : data?.message || 'Request failed (' + res.status + ')';
  throw showError(message, res.status);
}

function showError(message, status) {
  apiError.value = message;
  const err = new Error(message);
  err.status = status;
  return err;
}

export const api = {
  get: path => request('GET', path),
  post: (path, body) => request('POST', path, body),
  put: (path, body) => request('PUT', path, body),
  del: path => request('DELETE', path)
};

// Sign-in, users and roles (the auth service).
export const authApi = {
  get: path => request('GET', path, undefined, AUTH_URL),
  post: (path, body) => request('POST', path, body, AUTH_URL),
  put: (path, body) => request('PUT', path, body, AUTH_URL),
  del: path => request('DELETE', path, undefined, AUTH_URL)
};
