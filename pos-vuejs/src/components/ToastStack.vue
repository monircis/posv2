<template>
  <div :style="`position:absolute;left:50%;bottom:${bottom}px;transform:translateX(-50%);display:flex;flex-direction:column;align-items:center;gap:6px;z-index:${zIndex};pointer-events:none`">
    <div v-for="(t, i) in toasts" :key="i" style="background:#0f1c2b;color:#ffffff;border-radius:10px;padding:10px 15px;font-size:13px;display:flex;align-items:center;gap:10px;box-shadow:0 14px 30px -12px rgba(9,22,35,.6);white-space:nowrap"><div :style="t.dot"></div>{{ t.msg }}</div>
  </div>
</template>

<script setup lang="ts">
withDefaults(defineProps<{
  toasts?: { dot: string; msg: string }[];
  bottom?: number;
  zIndex?: number;
}>(), { toasts: () => [], bottom: 20, zIndex: 40 });
</script>
