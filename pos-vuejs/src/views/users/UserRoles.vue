<template>
  <template v-if="vm.uRoles">
    <div>
      <div style="padding:20px 24px 16px;background:#ffffff;border-bottom:1px solid #dfe6ee">
        <div style="display:flex;align-items:center;gap:7px;font-size:11.5px;color:#7d8b9c;margin-bottom:8px"><span>User Management</span><span style="color:#c2cedb">/</span><span style="color:#0f1c2b;font-weight:500">Roles</span></div>
        <div style="display:flex;align-items:flex-start;gap:20px;flex-wrap:wrap">
          <div>
            <h2 style="margin:0 0 3px;font-size:22px;font-weight:700;letter-spacing:-0.018em">Roles</h2>
            <div style="font-size:12.5px;color:#5d6b7d">{{ admin.rolesSummary }}</div>
          </div>
          <div v-if="admin.can('users.create')" @click="admin.newRole()" style="margin-left:auto;height:34px;padding:0 15px;border-radius:8px;background:var(--c-primary);color:#ffffff;display:flex;align-items:center;font-size:12.5px;font-weight:600;cursor:pointer" v-pseudo:hover="'background:var(--c-primary-hover)'">+ New role</div>
        </div>
      </div>
      <div style="padding:20px 24px 32px">
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(330px,1fr));gap:14px">
          <template v-for="(r, $index) in vm.roles" :key="$index">
            <div @click="admin.openRole(r.name)" style="background:#ffffff;border:1px solid #dfe6ee;border-radius:12px;padding:17px 19px;cursor:pointer" v-pseudo:hover="'border-color:var(--c-primary);box-shadow:0 6px 18px -10px rgba(22,104,201,.4)'">
              <div style="margin-bottom:12px">
                <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap"><div style="font-size:14px;font-weight:700;letter-spacing:-0.01em">{{ r.name }}</div> <span :style="r.kindStyle">{{ r.kind }}</span></div>
                <div style="font-size:12px;color:#5d6b7d;line-height:1.5;margin-top:5px;text-wrap:pretty">{{ r.desc }}</div>
              </div>
              <div style="display:flex;align-items:center;gap:9px;padding-top:12px;border-top:1px solid #f0f4f8">
                <div style="font-size:12px;color:#5d6b7d">{{ r.userLabel }}</div>
                <div style="margin-left:auto;font-family:'IBM Plex Mono',monospace;font-size:11px;color:var(--c-primary-text);background:var(--c-primary-soft);border-radius:5px;padding:3px 8px">{{ r.permLabel }}</div>
              </div>
            </div>
          </template>
        </div>
      </div>
    </div>
  </template>
</template>

<script setup lang="ts">
import { inject } from 'vue';
import { usePageVm } from '../../erp/useErpPage';

const vm = usePageVm();
const admin = inject('users') as any; // API actions, see UsersPage.vue
</script>
