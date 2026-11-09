<template>
  <template v-if="vm.hr_isEmp">
    <div style="display:grid;grid-template-columns:300px minmax(0,1fr);gap:14px;align-items:start">
      <div style="background:#ffffff;border:1px solid #dfe6ee;border-radius:12px;padding:18px">
        <div style="display:flex;align-items:center;gap:12px">
          <div :style="vm.hr_e.avatar">{{ vm.hr_e.ini }}</div>
          <div style="min-width:0"><div style="font-size:15px;font-weight:700">{{ vm.hr_e.name }}</div> <div style="font-size:12px;color:#7d8b9c">{{ vm.hr_e.role }}</div> <span :style="vm.hr_e.badge">{{ vm.hr_e.status }}</span></div>
        </div>
        <div style="display:grid;gap:10px;margin-top:16px;padding-top:14px;border-top:1px solid #eef2f6">
          <template v-for="(f, $index) in vm.hr_e.facts" :key="$index">
            <div style="display:flex;gap:10px;font-size:12.5px">
              <div style="width:96px;flex:none;color:#7d8b9c">{{ f.k }}</div>
              <div style="font-weight:500;min-width:0;overflow:hidden;text-overflow:ellipsis">{{ f.v }}</div>
            </div>
          </template>
        </div>
      </div>
      <div style="display:grid;gap:14px">
        <div style="display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px">
          <template v-for="(k, $index) in vm.hr_e.stats" :key="$index">
            <div style="background:#ffffff;border:1px solid #dfe6ee;border-radius:12px;padding:13px 16px">
              <div style="font-size:11.5px;color:#7d8b9c">{{ k.k }}</div>
              <div style="font-family:'IBM Plex Mono',monospace;font-size:19px;font-weight:600;margin-top:2px">{{ k.v }}</div>
              <div style="font-size:11px;color:#8b98a8;margin-top:1px">{{ k.n }}</div>
            </div>
          </template>
        </div>
        <div style="background:#ffffff;border:1px solid #dfe6ee;border-radius:12px;padding:16px 18px">
          <div style="font-size:13px;font-weight:700;margin-bottom:12px">This week</div>
          <div style="display:grid;grid-template-columns:repeat(7,1fr);gap:8px">
            <template v-for="(d, $index) in vm.hr_e.week" :key="$index">
              <div :style="d.box">
                <div style="font-size:10.5px;font-weight:600;text-transform:uppercase;letter-spacing:.05em">{{ d.dow }}</div>
                <div style="font-family:'IBM Plex Mono',monospace;font-size:12px;margin-top:4px">{{ d.shift }}</div>
                <div style="font-family:'IBM Plex Mono',monospace;font-size:11px;margin-top:2px;opacity:.8">{{ d.hours }}</div>
              </div>
            </template>
          </div>
        </div>
        <div style="background:#ffffff;border:1px solid #dfe6ee;border-radius:12px;overflow:hidden">
          <div style="padding:13px 18px;font-size:13px;font-weight:700;border-bottom:1px solid #eaeff5">Leave history</div>
          <template v-for="(l, $index) in vm.hr_e.leave" :key="$index">
            <div style="display:grid;grid-template-columns:1fr 1.4fr 0.6fr 0.9fr;gap:12px;padding:10px 18px;border-bottom:1px solid #f0f4f8;align-items:center;font-size:12.5px">
              <div style="font-weight:500">{{ l.type }}</div>
              <div style="font-family:'IBM Plex Mono',monospace;font-size:12px;color:#4a5866">{{ l.range }}</div>
              <div style="font-family:'IBM Plex Mono',monospace;text-align:right">{{ l.days }}</div>
              <div><span :style="l.badge">{{ l.status }}</span></div>
            </div>
          </template>
          <template v-if="vm.hr_e.noLeave">
            <div style="padding:20px 18px;font-size:12.5px;color:#7d8b9c">No leave taken this year.</div>
          </template>
        </div>
      </div>
    </div>
  </template>
</template>

<script setup lang="ts">
import { usePageVm } from '../../erp/useErpPage';

const vm = usePageVm();
</script>
