<template>
  <div style="height:100vh;min-height:720px;position:relative">
    <div style="height:100%;overflow:auto">
      <div style="min-width:1280px;height:100%;min-height:720px;display:flex;background:#eef2f6;position:relative;overflow:hidden;font-family:'Public Sans',system-ui,sans-serif;color:#0f1c2b">
        <AppSidebar />
        <div style="flex:1;min-width:0;display:flex;flex-direction:column">
          <div v-if="apiError" style="flex:none;display:flex;align-items:center;gap:12px;padding:9px 20px;background:#fdeaea;color:#b02020;font-size:12.5px;font-weight:500;border-bottom:1px solid #f3c7c7">
            <div style="flex:1">{{ apiError }}</div>
            <div @click="clearError" style="cursor:pointer;font-weight:700">×</div>
          </div>
          <AppHeader />
          <!-- Not positioned on purpose: page overlays (modals, toasts) are absolutely
               positioned against the frame above, as in the original pages. -->
          <div style="flex:1;overflow-y:auto;min-height:0">
            <RouterView />
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import AppSidebar from '../components/layout/AppSidebar.vue';
import AppHeader from '../components/layout/AppHeader.vue';
import { apiError } from '../api/http.js';

const clearError = () => { apiError.value = ''; };
</script>
