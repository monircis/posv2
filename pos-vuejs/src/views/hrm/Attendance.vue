<template>
  <template v-if="vm.hr_isAtt">
    <div style="display:grid;gap:14px">
      <div style="display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px">
        <template v-for="(k, $index) in vm.hr_attKpis" :key="$index">
          <div style="background:#ffffff;border:1px solid #dfe6ee;border-radius:12px;padding:13px 16px">
            <div style="font-size:11.5px;color:#7d8b9c">{{ k.k }}</div>
            <div :style="k.vStyle">{{ k.v }}</div>
          </div>
        </template>
      </div>
      <div style="background:#ffffff;border:1px solid #dfe6ee;border-radius:12px;overflow:hidden">
        <div style="display:grid;grid-template-columns:1.8fr 1fr 0.9fr 0.9fr 0.8fr 1fr 1.3fr;gap:12px;padding:10px 16px;background:#f6f9fc;border-bottom:1px solid #dfe6ee;font-family:'IBM Plex Mono',monospace;font-size:9.5px;letter-spacing:0.07em;text-transform:uppercase;color:#5d6b7d">
          <div>Employee</div>
          <div>Rostered</div>
          <div>Clock in</div>
          <div>Clock out</div>
          <div style="text-align:right">Hours</div>
          <div>Status</div>
          <div></div>
        </div>
        <template v-for="(a, $index) in vm.hr_att" :key="$index">
          <div style="display:grid;grid-template-columns:1.8fr 1fr 0.9fr 0.9fr 0.8fr 1fr 1.3fr;gap:12px;padding:9px 16px;border-bottom:1px solid #f0f4f8;align-items:center">
            <div style="display:flex;align-items:center;gap:10px;min-width:0">
              <div :style="a.avatar">{{ a.ini }}</div>
              <div style="min-width:0">
                <div style="font-size:13px;font-weight:600">{{ a.name }}</div>
                <div style="font-size:11px;color:#8b98a8">{{ a.loc }}</div>
              </div>
            </div>
            <div style="font-family:'IBM Plex Mono',monospace;font-size:12px;color:#4a5866">{{ a.roster }}</div>
            <div :style="a.inStyle">{{ a.cin }}</div>
            <div style="font-family:'IBM Plex Mono',monospace;font-size:12px">{{ a.cout }}</div>
            <div style="font-family:'IBM Plex Mono',monospace;font-size:12.5px;text-align:right;font-weight:600">{{ a.hours }}</div>
            <div><span :style="a.badge">{{ a.status }}</span></div>
            <div style="display:flex;gap:6px;justify-content:flex-end">
              <template v-for="(b, $index) in a.actions" :key="$index">
                <div @click="b.onClick" :style="b.style">{{ b.label }}</div>
              </template>
            </div>
          </div>
        </template>
      </div>
    </div>
  </template>
</template>

<script setup lang="ts">
import { usePageVm } from '../../erp/useErpPage';

const vm = usePageVm();
</script>
