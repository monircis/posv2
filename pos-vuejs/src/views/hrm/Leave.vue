<template>
  <template v-if="vm.hr_isLeave">
    <div style="display:grid;grid-template-columns:minmax(0,1fr) 320px;gap:14px;align-items:start">
      <div style="background:#ffffff;border:1px solid #dfe6ee;border-radius:12px;overflow:hidden">
        <div style="display:flex;gap:2px;padding:4px 12px 0;border-bottom:1px solid #eaeff5">
          <template v-for="(t, $index) in vm.hr_leaveTabs" :key="$index">
            <div @click="t.onClick" :style="t.style">{{ t.name }}<span :style="t.countStyle">{{ t.count }}</span></div>
          </template>
        </div>
        <template v-for="(l, $index) in vm.hr_leave" :key="$index">
          <div style="display:grid;grid-template-columns:1.6fr 0.9fr 1.3fr 0.5fr 1fr 1.3fr;gap:12px;padding:11px 16px;border-bottom:1px solid #f0f4f8;align-items:center">
            <div style="display:flex;align-items:center;gap:10px;min-width:0">
              <div :style="l.avatar">{{ l.ini }}</div>
              <div style="min-width:0">
                <div style="font-size:13px;font-weight:600">{{ l.name }}</div>
                <div style="font-size:11px;color:#8b98a8;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">{{ l.note }}</div>
              </div>
            </div>
            <div style="font-size:12.5px">{{ l.type }}</div>
            <div style="font-family:'IBM Plex Mono',monospace;font-size:12px;color:#4a5866">{{ l.range }}</div>
            <div style="font-family:'IBM Plex Mono',monospace;font-size:12.5px;text-align:right">{{ l.days }}</div>
            <div><span :style="l.badge">{{ l.status }}</span></div>
            <div style="display:flex;gap:6px;justify-content:flex-end">
              <template v-for="(b, $index) in l.actions" :key="$index">
                <div @click="b.onClick" :style="b.style">{{ b.label }}</div>
              </template>
              <div :style="l.warnStyle">{{ l.warn }}</div>
            </div>
          </div>
        </template>
        <template v-if="vm.hr_noLeave">
          <div style="padding:30px 20px;text-align:center;font-size:12.5px;color:#7d8b9c">Nothing here.</div>
        </template>
      </div>
      <div style="background:#ffffff;border:1px solid #dfe6ee;border-radius:12px;padding:16px 18px">
        <div style="font-size:13px;font-weight:700;margin-bottom:3px">Leave balances</div>
        <div style="font-size:11.5px;color:#7d8b9c;margin-bottom:12px">Days available · casuals don't accrue</div>
        <div style="display:grid;gap:9px">
          <template v-for="(b, $index) in vm.hr_balances" :key="$index">
            <div style="display:flex;align-items:center;gap:10px">
              <div style="flex:1;min-width:0;font-size:12.5px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">{{ b.name }}</div>
              <div style="font-family:'IBM Plex Mono',monospace;font-size:12px;width:56px;text-align:right">{{ b.annual }}</div>
              <div style="font-family:'IBM Plex Mono',monospace;font-size:12px;width:56px;text-align:right;color:#5d6b7d">{{ b.personal }}</div>
            </div>
          </template>
          <div style="display:flex;gap:10px;font-family:'IBM Plex Mono',monospace;font-size:9.5px;letter-spacing:0.07em;text-transform:uppercase;color:#5d6b7d;border-top:1px solid #eef2f6;padding-top:8px">
            <div style="flex:1"></div>
            <div style="width:56px;text-align:right">Annual</div>
            <div style="width:56px;text-align:right">Personal</div>
          </div>
        </div>
      </div>
    </div>
  </template>
</template>

<script setup lang="ts">
import { usePageVm } from '../../erp/useErpPage';

const vm = usePageVm();
</script>
