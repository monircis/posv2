<template>
  <div :style="sideStyle">
    <div style="height:56px;display:flex;align-items:center;gap:10px;padding:0 14px;flex:none;border-bottom:1px solid rgba(255,255,255,.09)">
      <div style="width:26px;height:26px;border-radius:7px;background:var(--c-accent);color:#06283a;display:grid;place-items:center;font-weight:700;font-size:13px;flex:none">M</div>
      <div :style="collapsed ? 'display:none' : 'min-width:0;flex:1'">
        <div style="font-size:13px;font-weight:600;color:#ffffff;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">Madinah Butchery</div>
        <div style="font-size:10.5px;color:#89aec6">All locations</div>
      </div>
      <div :style="collapsed ? 'display:none' : 'color:#89aec6;font-size:11px'">&#9662;</div>
    </div>
    <div :style="listStyle">
      <div v-for="n in items" :key="n.name">
        <div @click="n.onClick" :title="n.name" :style="n.style" v-pseudo:hover="'background:rgba(255,255,255,.07)'">
          <div :style="n.iconStyle">{{ n.key }}</div>
          <div :style="n.nameStyle">{{ n.name }}</div>
          <div :style="n.caretStyle">&#8250;</div>
        </div>
        <div v-if="n.open" style="margin:2px 0 6px 26px;border-left:1px solid rgba(255,255,255,.12);padding-left:10px;display:flex;flex-direction:column;gap:1px">
          <div v-for="(c, i) in n.children" :key="i" @click="c.onClick" :style="c.style" v-pseudo:hover="'color:#ffffff'">{{ c.name }}</div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { navCollapsed, sidebarItems, SCREEN_OF_NAME } from '../../erp/runtime/nav.js';
import { can } from '../../api/auth.js';

// Items (with their active/collapsed styles) come from the page logic via useErpPage.
// Screens the user's role cannot view are hidden.
const items = computed<any[]>(() => sidebarItems.value.filter((n: any) => can((SCREEN_OF_NAME as Record<string, string>)[n.name] + '.view')));
const collapsed = computed(() => navCollapsed.value);

const sideStyle = computed(() => 'flex:none;width:' + (collapsed.value ? '64px' : '244px')
  + ';background:var(--c-sidebar);display:flex;flex-direction:column;overflow:hidden;transition:width .18s ease');
const listStyle = computed(() => 'flex:1;overflow-y:auto;padding:10px 8px 20px;'
  + (collapsed.value ? 'scrollbar-width:none;' : 'scrollbar-width:thin;scrollbar-color:rgba(255,255,255,.22) transparent;'));
</script>
