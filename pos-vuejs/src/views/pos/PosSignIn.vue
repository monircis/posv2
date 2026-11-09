<template>
  <div v-if="posSignedOut" ref="root" @keydown.stop style="position:fixed;inset:0;z-index:60;display:grid;place-items:center;background:rgba(9,22,35,.72);backdrop-filter:blur(3px)">
    <form @submit.prevent="submit" style="width:400px;background:#ffffff;border-radius:18px;padding:24px;box-shadow:0 30px 80px -24px rgba(9,22,35,.75);color:#0f1c2b">
      <div style="display:flex;align-items:center;gap:12px">
        <div style="width:40px;height:40px;border-radius:11px;background:var(--c-accent);color:#06283a;display:grid;place-items:center;font-weight:700;font-size:17px;flex:none">M</div>
        <div>
          <div style="font-size:18px;font-weight:700">Sign in to the POS</div>
          <div style="font-size:12.5px;color:#5d6b7d;margin-top:2px">{{ note }}</div>
        </div>
      </div>

      <label style="display:block;margin-top:18px;font-size:12px;font-weight:600;color:#5d6b7d">Email
        <input ref="emailEl" v-model="email" type="email" autocomplete="username" placeholder="you@madinah.com.au" :style="inputStyle" />
      </label>
      <label style="display:block;margin-top:12px;font-size:12px;font-weight:600;color:#5d6b7d">Password
        <div style="position:relative">
          <input ref="passwordEl" v-model="password" :type="showPw ? 'text' : 'password'" autocomplete="current-password" :style="inputStyle + 'padding-right:60px;'" />
          <span @click="showPw = !showPw" style="position:absolute;right:12px;top:50%;transform:translateY(-35%);font-size:12px;color:var(--c-primary);cursor:pointer;user-select:none">{{ showPw ? 'Hide' : 'Show' }}</span>
        </div>
      </label>
      <label style="display:flex;align-items:center;gap:8px;margin-top:12px;font-size:12.5px;color:#4a5866;cursor:pointer">
        <input v-model="remember" type="checkbox" /> Keep me signed in on this computer
      </label>

      <div v-if="error" style="margin-top:12px;background:#fdeaea;color:#b02020;border-radius:10px;padding:9px 12px;font-size:12.5px">{{ error }}</div>

      <button type="submit" :disabled="busy" :style="'margin-top:16px;width:100%;height:46px;border:0;border-radius:11px;background:var(--c-primary);color:#ffffff;font-family:inherit;font-size:14.5px;font-weight:700;cursor:pointer;' + (busy ? 'opacity:.7;' : '')">{{ busy ? 'Signing in…' : 'Sign in' }}</button>
      <div style="display:flex;margin-top:12px;font-size:12px">
        <RouterLink to="/login" @click="leave" style="color:#5d6b7d;text-decoration:underline;margin-left:auto">Go to the sign-in page</RouterLink>
      </div>
    </form>
  </div>
</template>

<script setup lang="ts">
// The sign-in window on the POS. It opens when the cashier signs out (user button > Sign out)
// or when their sign-in runs out, so the next cashier signs in right here: the order on screen,
// held orders and the cart are kept. Emits `signed-in` with `changed: true` when someone else signed in.
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import { can, login, posLast, posSignedOut } from '../../api/auth.js';

const emit = defineEmits<{ (e: 'signed-in', info: { changed: boolean }): void }>();
const router = useRouter();

const email = ref('');
const password = ref('');
const remember = ref(true);
const showPw = ref(false);
const busy = ref(false);
const error = ref('');
const root = ref<HTMLElement | null>(null);
const emailEl = ref<HTMLInputElement | null>(null);
const passwordEl = ref<HTMLInputElement | null>(null);
// Who was signed in before, to know if a different cashier signs in.
const before = ref('');

const note = computed(() => before.value
  ? 'Signed out. The order on screen is kept.'
  : 'Sign in to keep selling.');

// Each time the window opens: the last cashier's email is filled in, so they only type the password.
watch(posSignedOut, async (open) => {
  if (!open) return;
  before.value = posLast.email;
  email.value = posLast.email;
  remember.value = posLast.remember;
  password.value = '';
  error.value = '';
  busy.value = false;
  await nextTick();
  (email.value ? passwordEl : emailEl).value?.focus();
}, { immediate: true });

async function submit() {
  if (busy.value) return;
  const em = email.value.trim().toLowerCase();
  if (!em) { error.value = 'Enter your work email.'; emailEl.value?.focus(); return; }
  if (!password.value) { error.value = 'Enter your password.'; passwordEl.value?.focus(); return; }
  busy.value = true;
  error.value = '';
  try {
    const user = await login(em, password.value, remember.value);
    posSignedOut.value = false;
    password.value = '';
    // Someone who cannot sell (e.g. kitchen staff) goes to their own page.
    if (!can('sell.view')) { router.push('/'); return; }
    emit('signed-in', { changed: user.email.toLowerCase() !== before.value.toLowerCase() });
  } catch (e: any) {
    error.value = e.message || 'Could not sign in.';
    passwordEl.value?.select();
  }
  busy.value = false;
}

function leave() {
  posSignedOut.value = false;
}

// While the window is open, the POS keyboard shortcuts (F9, Esc, barcode typing) must not fire.
function onKeyFirst(e: KeyboardEvent) {
  if (!posSignedOut.value) return;
  if (root.value && e.target instanceof Node && root.value.contains(e.target)) return;
  e.stopImmediatePropagation();
}
onMounted(() => window.addEventListener('keydown', onKeyFirst, true));
onUnmounted(() => {
  window.removeEventListener('keydown', onKeyFirst, true);
  posSignedOut.value = false;
});

const inputStyle = "display:block;width:100%;height:44px;margin-top:5px;border:1px solid #dfe6ee;border-radius:10px;padding:0 12px;font-family:inherit;font-size:14px;font-weight:400;color:#0f1c2b;outline:none;";
</script>
