<template>
  <div style="height:56px;flex:none;background:#ffffff;border-bottom:1px solid #dfe6ee;display:flex;align-items:center;gap:14px;padding:0 20px">
    <div @click="toggleNav" :title="navCollapsed ? 'Expand menu' : 'Collapse menu'" style="width:30px;height:30px;border-radius:7px;border:1px solid #dfe6ee;display:grid;place-items:center;cursor:pointer;color:#5d6b7d;font-size:13px;flex:none" v-pseudo:hover="'background:#f2f6fa;color:var(--c-primary)'">&#9776;</div>
    <div style="flex:1;max-width:420px;position:relative">
      <div style="position:absolute;left:11px;top:50%;transform:translateY(-50%);color:#9aa7b6;font-size:12px">&#9906;</div>
      <input placeholder="Search products, invoices, contacts&hellip;" style="width:100%;height:34px;border:1px solid #dfe6ee;border-radius:8px;background:#f7fafc;padding:0 10px 0 30px;font-family:'Public Sans',sans-serif;font-size:13px;outline:none" v-pseudo:focus="'background:#ffffff;border-color:var(--c-primary)'" />
    </div>
    <div style="margin-left:auto;display:flex;align-items:center;gap:8px">
      <RouterLink to="/my-hr" style="height:32px;padding:0 12px;border-radius:8px;border:1px solid #dfe6ee;color:#4a5866;display:flex;align-items:center;font-size:12.5px;font-weight:600;cursor:pointer;text-decoration:none" v-pseudo:hover="'background:#f2f6fa;color:var(--c-primary)'">My HR</RouterLink>
      <RouterLink to="/pos" style="height:32px;padding:0 12px;border-radius:8px;background:var(--c-primary);color:#ffffff;display:flex;align-items:center;font-size:12.5px;font-weight:600;cursor:pointer;text-decoration:none" v-pseudo:hover="'background:var(--c-primary-hover);color:#ffffff'">Open POS</RouterLink>
      <div style="height:32px;padding:0 11px;border-radius:8px;border:1px solid #dfe6ee;display:flex;align-items:center;font-family:'IBM Plex Mono',monospace;font-size:11.5px;color:#4a5866;cursor:pointer">29/09/2026</div>
      <div style="width:32px;height:32px;border-radius:8px;border:1px solid #dfe6ee;display:grid;place-items:center;cursor:pointer;position:relative;color:#5d6b7d">&#9737;<div style="position:absolute;top:6px;right:6px;width:6px;height:6px;border-radius:50%;background:#d93a3a;border:1.5px solid #ffffff"></div></div>
      <div :title="user ? user.email + ' · ' + user.role : ''" style="display:flex;align-items:center;gap:8px;padding:3px 9px 3px 3px;border-radius:8px;border:1px solid #dfe6ee">
        <div style="width:25px;height:25px;border-radius:6px;background:var(--c-sidebar);color:#ffffff;display:grid;place-items:center;font-size:11px;font-weight:600">{{ initials }}</div>
        <div style="font-size:12.5px;font-weight:500">{{ firstName }}</div>
        <div style="font-size:11px;color:#7d8b9c">{{ user?.role }}</div>
      </div>
      <div @click="signOut" style="height:32px;padding:0 11px;border-radius:8px;border:1px solid #dfe6ee;display:flex;align-items:center;font-size:12.5px;color:#4a5866;cursor:pointer" v-pseudo:hover="'background:#f2f6fa;color:#b02020'">Sign out</div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { useRouter } from 'vue-router';
import { navCollapsed, toggleNav } from '../../erp/runtime/nav.js';
import { currentUser as user, logout } from '../../api/auth.js';

const router = useRouter();
const initials = computed(() => (user.value?.name || '').split(' ').map((p: string) => p[0]).join('').slice(0, 2).toUpperCase());
const firstName = computed(() => (user.value?.name || '').split(' ')[0]);

async function signOut() {
  await logout();
  router.push({ name: 'login', query: { signedout: '1' } });
}
</script>
