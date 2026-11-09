<template>
  <main class="auth">

    <aside class="brand-panel">
      <div class="brand">
        <div class="logo">M</div>
        <div>
          <div class="brand-name">Madinah Butchery</div>
          <div class="brand-sub">Dooit ERP</div>
        </div>
      </div>

      <div class="pitch">
        <h1>Shop, kitchen and books in one place.</h1>
        <p>Sales, stock across branches, kitchen orders and accounts stay in step as you work.</p>
      </div>

      <ul class="locations" aria-label="Branches">
        <li v-for="b in branches.filter(b => b.active)" :key="b.id"><span class="dot"></span>{{ b.name }}</li>
      </ul>

      <div class="brand-foot">GST registered · Prices in AUD</div>
    </aside>

    <section class="form-panel">
      <div class="mobile-brand">
        <div class="logo">M</div>
        <div class="brand-name">Madinah Butchery</div>
      </div>

      <div class="card">
        <div class="card-head">
          <h2>Sign in</h2>
          <p class="muted">Use your work email and password.</p>
        </div>

        <form novalidate @submit.prevent="submit">
          <div :class="['alert', alert.tone]" role="alert" :hidden="!alert.text">{{ alert.text }}</div>

          <div class="field">
            <label for="email">Work email</label>
            <input id="email" ref="emailEl" v-model="email" name="email" type="email" autocomplete="username" placeholder="name@madinah.com.au" spellcheck="false"
              :aria-invalid="emailErr ? 'true' : 'false'" :aria-describedby="emailErr ? 'email-err' : undefined" @input="emailErr = ''">
            <div class="field-error" id="email-err">{{ emailErr }}</div>
          </div>

          <div class="field">
            <div class="label-row">
              <label for="password">Password</label>
              <button type="button" class="link" @click="showAlert('Ask the business owner to send you a reset link from User Management.', 'info')">Forgot password?</button>
            </div>
            <div class="pw">
              <input id="password" ref="passwordEl" v-model="password" name="password" :type="showPw ? 'text' : 'password'" autocomplete="current-password"
                :aria-invalid="passwordErr ? 'true' : 'false'" :aria-describedby="passwordErr ? 'password-err' : undefined" @input="passwordErr = ''">
              <button type="button" :aria-label="showPw ? 'Hide password' : 'Show password'" :aria-pressed="showPw ? 'true' : 'false'" @click="togglePw">{{ showPw ? 'Hide' : 'Show' }}</button>
            </div>
            <div class="field-error" id="password-err">{{ passwordErr }}</div>
          </div>

          <label class="check">
            <input v-model="remember" type="checkbox" name="remember">
            <span>Keep me signed in on this device for 30 days</span>
          </label>

          <button :class="['primary', { busy }]" type="submit" :disabled="busy || locked">{{ busy ? 'Signing in' : 'Sign in' }}</button>
        </form>
      </div>

      <!-- <p class="help">New here? <RouterLink to="/register">Create an account</RouterLink></p> -->
      <p class="help">Locked out? The business owner can reset your access in User Management.</p>
    </section>

  </main>
</template>

<script setup lang="ts">
// Sign-in page: work email + password, checked by the API (POST /api/auth/login).
// The server refuses deactivated and invited staff; its message is shown as it comes.
// A user's branch is set on the user in User Management, not picked here.
import { onMounted, onUnmounted, reactive, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { homeFor, login } from '../api/auth.js';
import { branches, loadBranches } from '../api/branches.js';

const MAX_TRIES = 5, LOCK_SECONDS = 30;

const route = useRoute();
const router = useRouter();

const email = ref(''), password = ref(''), remember = ref(false);
const emailErr = ref(''), passwordErr = ref('');
const showPw = ref(false), busy = ref(false), locked = ref(false);
const alert = reactive({ text: '', tone: '' });
const emailEl = ref<HTMLInputElement>(), passwordEl = ref<HTMLInputElement>();

let tries = 0, lockedUntil = 0;
let lockTimer: ReturnType<typeof setInterval> | undefined;


// Return to the page that asked for sign-in, else the role's home page.
function nextPage(role: string) {
  return homeFor(role, route.query.next);
}

function showAlert(text: string, tone = '') {
  alert.text = text;
  alert.tone = tone;
}

function startLock() {
  lockedUntil = Date.now() + LOCK_SECONDS * 1000;
  locked.value = true;
  const tick = () => {
    const left = Math.ceil((lockedUntil - Date.now()) / 1000);
    if (left <= 0) { clearInterval(lockTimer); tries = 0; locked.value = false; showAlert(''); return; }
    showAlert('Too many attempts. Try again in ' + left + ' second' + (left === 1 ? '' : 's') + '.', 'warn');
  };
  tick();
  lockTimer = setInterval(tick, 1000);
}

function togglePw() {
  showPw.value = !showPw.value;
  passwordEl.value?.focus();
}

async function submit() {
  if (Date.now() < lockedUntil || busy.value) return;
  showAlert('');
  const em = email.value.trim().toLowerCase(), pw = password.value;
  let bad = false;
  if (!em) { emailErr.value = 'Enter your work email.'; bad = true; }
  else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(em)) { emailErr.value = 'That doesn’t look like an email address.'; bad = true; }
  if (!pw) { passwordErr.value = 'Enter your password.'; bad = true; }
  if (bad) { (emailErr.value ? emailEl : passwordEl).value?.focus(); return; }

  busy.value = true;
  try {
    const user = await login(em, pw, remember.value);
    router.push(nextPage(user.role));
  } catch (e: any) {
    busy.value = false;
    if (e.status === 401) {
      tries++;
      if (tries >= MAX_TRIES) { startLock(); return; }
      showAlert(e.message + ' ' + (MAX_TRIES - tries) + ' attempt' + (MAX_TRIES - tries === 1 ? '' : 's') + ' left.');
      passwordEl.value?.select();
    } else {
      showAlert(e.message);
    }
  }
}

onMounted(() => {
  if (route.query.signedout) showAlert('You’ve signed out.', 'info');
  else if (route.query.next) showAlert('Sign in to continue.', 'info');
  emailEl.value?.focus();
  loadBranches().catch(() => {}); // the panel just stays empty if the API is down
});

onUnmounted(() => clearInterval(lockTimer));
</script>

<style scoped src="../assets/css/login.css"></style>
