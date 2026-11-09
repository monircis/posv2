<template>
  <!-- One line of a cash count: "$20 × [ 3 ]   60.00" -->
  <div style="display:flex;align-items:center;gap:8px;padding:3px 0;font-size:12.5px">
    <div style="width:56px;font-weight:600">{{ d >= 1 ? '$' + d : Math.round(d * 100) + 'c' }}</div>
    <div style="color:#8b98a8">×</div>
    <input type="number" min="0" step="1" :value="n || ''" placeholder="0" @input="onInput"
      style="width:70px;height:30px;border:1px solid #dfe6ee;border-radius:7px;padding:0 8px;font-family:'IBM Plex Mono',monospace;font-size:13px;outline:none" />
    <div style="margin-left:auto;font-family:'IBM Plex Mono',monospace;color:#4a5866">{{ money((n || 0) * d) }}</div>
  </div>
</template>

<script setup lang="ts">
import { money } from '../../api/products.js';

defineProps<{ d: number; n?: number }>();
const emit = defineEmits<{ (e: 'set', count: number): void }>();

function onInput(e: Event) {
  emit('set', Math.max(0, parseInt((e.target as HTMLInputElement).value, 10) || 0));
}
</script>
