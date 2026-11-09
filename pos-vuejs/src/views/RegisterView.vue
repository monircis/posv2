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
        <h1>Create your staff account.</h1>
        <p>The first account becomes the Owner. Everyone after starts as a Cashier; the Owner sets their role and branch in User Management.</p>
      </div>

      <div class="brand-foot">GST registered · Prices in AUD</div>
    </aside>

    <section class="form-panel">
      <div class="mobile-brand">
        <div class="logo">M</div>
        <div class="brand-name">Madinah Butchery</div>
      </div>

      <div class="card">
        <div class="card-head">
          <h2>Create account</h2>
          <p class="muted">Use your work email. You can sign in straight away.</p>
        </div>

        <form novalidate @submit.prevent="submit">
          <div class="alert" role="alert" :hidden="!error">{{ error }}</div>

          <div class="field">
            <label for="name">Full name</label>
            <input id="name" v-model="form.name" type="text" autocomplete="name" placeholder="Sara Rahman">
            <div class="field-error">{{ errors.name }}</div>
          </div>

          <div class="field">
            <label for="email">Work email</label>
            <input id="email" v-model="form.email" type="email" autocomplete="username" placeholder="name@madinah.com.au" spellcheck="false">
            <div class="field-error">{{ errors.email }}</div>
          </div>

          <div class="field">
            <label for="phone">Mobile <span class="muted">(optional)</span></label>
            <input id="phone" v-model="form.phone" type="tel" autocomplete="tel" placeholder="+61">
          </div>

          <div class="field">
            <label for="password">Password</label>
            <input id="password" v-model="form.password" type="password" autocomplete="new-password" placeholder="At least 6 characters">
            <div class="field-error">{{ errors.password }}</div>
          </div>

          <div class="field">
            <label for="password_confirmation">Confirm password</label>
            <input id="password_confirmation" v-model="form.password_confirmation" type="password" autocomplete="new-password">
            <div class="field-error">{{ errors.password_confirmation }}</div>
          </div>

          <button :class="['primary', { busy }]" type="submit" :disabled="busy">{{ busy ? 'Creating account' : 'Create account' }}</button>
        </form>
      </div>

      <p class="help">Already have an account? <RouterLink to="/login">Sign in</RouterLink></p>
    </section>

  </main>
</template>

<script setup lang="ts">
// Register: POST /api/auth/register, then signed in with the returned token.
import { reactive, ref } from 'vue';
import { useRouter } from 'vue-router';
import { register } from '../api/auth.js';

const router = useRouter();

const form = reactive({ name: '', email: '', phone: '', password: '', password_confirmation: '' });
const errors = reactive<Record<string, string>>({});
const error = ref('');
const busy = ref(false);

// Same checks as the server, so most mistakes show before sending.
function check() {
  for (const k of Object.keys(errors)) delete errors[k];
  if (!form.name.trim()) errors.name = 'Enter your name.';
  if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.email.trim())) errors.email = 'Enter a valid email.';
  if (form.password.length < 6) errors.password = 'Use at least 6 characters.';
  if (form.password !== form.password_confirmation) errors.password_confirmation = 'Passwords do not match.';
  return Object.keys(errors).length === 0;
}

async function submit() {
  error.value = '';
  if (!check()) return;
  busy.value = true;
  try {
    await register({ ...form, name: form.name.trim(), email: form.email.trim() });
    router.push('/');
  } catch (e: any) {
    error.value = e.message;
    busy.value = false;
  }
}
</script>

<style scoped src="../assets/css/login.css"></style>
