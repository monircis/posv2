<template>
  <div style="padding:18px 24px 0;background:#ffffff;border-bottom:1px solid #dfe6ee">
    <div style="display:flex;align-items:center;gap:7px;font-size:11.5px;color:#7d8b9c;margin-bottom:8px"><span>Manufacturing</span><span style="color:#c2cedb">/</span><span style="color:#0f1c2b;font-weight:500">{{ crumb }}</span></div>
    <div style="display:flex;align-items:flex-start;gap:20px;flex-wrap:wrap">
      <div>
        <h2 style="margin:0 0 3px;font-size:22px;font-weight:700;letter-spacing:-0.018em">Manufacturing</h2>
        <div style="font-size:12.5px;color:#5d6b7d">Turn raw materials into finished stock</div>
      </div>
      <div style="margin-left:auto;display:flex;gap:8px">
        <slot />
      </div>
    </div>
    <div style="display:flex;gap:2px;margin-top:14px">
      <RouterLink v-for="t in TABS" :key="t.route" :to="{ name: t.route }" :style="tabStyle(t.route)">{{ t.name }}</RouterLink>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { useRoute } from 'vue-router';

defineProps<{ crumb: string }>();

const route = useRoute();
const TABS = [
  { route: 'mfg', name: 'Production orders' }, { route: 'mfg-recipes', name: 'Recipes' }, { route: 'mfg-materials', name: 'Materials' }
];
const current = computed(() => route.name);

function tabStyle(name: string) {
  return 'padding:9px 14px;font-size:13px;cursor:pointer;text-decoration:none;border-bottom:2px solid '
    + (current.value === name ? 'var(--c-primary);color:var(--c-primary);font-weight:600;' : 'transparent;color:#5d6b7d;font-weight:500;');
}
</script>
