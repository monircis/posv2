<template>
  <template v-if="vm.ac_isClose">
    <div>
      <div style="padding:20px 24px 0;background:#ffffff;border-bottom:1px solid #dfe6ee">
        <div style="display:flex;align-items:center;gap:7px;font-size:11.5px;color:#7d8b9c;margin-bottom:8px"><span>Accounting</span><span style="color:#c2cedb">/</span><span style="color:#0f1c2b;font-weight:500">Period close</span></div>
        <div style="display:flex;align-items:flex-start;gap:20px;flex-wrap:wrap">
          <div>
            <h2 style="margin:0 0 3px;font-size:22px;font-weight:700;letter-spacing:-0.018em">Period close</h2>
            <div style="font-size:12.5px;color:#5d6b7d">Locking a month stops anyone posting or editing entries dated in it</div>
          </div>
          <div style="margin-left:auto;display:flex;gap:8px;align-items:center"></div>
        </div>
        <div style="display:flex;gap:2px;margin-top:16px">
          <template v-for="(t, $index) in vm.ac_subTabs" :key="$index">
            <div @click="t.onClick" :style="t.style">{{ t.name }}<span :style="t.countStyle">{{ t.count }}</span></div>
          </template>
        </div>
      </div>
      <div style="padding:20px 24px 32px;display:grid;grid-template-columns:minmax(0,1fr) 340px;gap:16px;align-items:start">
        <div style="background:#ffffff;border:1px solid #dfe6ee;border-radius:12px;overflow:hidden">
          <template v-for="(p, $index) in vm.ac_periods" :key="$index">
            <div style="display:flex;align-items:center;gap:14px;padding:15px 18px;border-bottom:1px solid #f0f4f8"><div :style="p.dot">{{ p.mark }}</div> <div style="flex:1">
              <div style="font-size:14px;font-weight:600">{{ p.name }}</div>
              <div style="font-size:12px;color:#7d8b9c;margin-top:1px">{{ p.meta }}</div>
            </div> <span :style="p.badge">{{ p.status }}</span> <div @click="p.onAction" :style="p.actStyle">{{ p.action }}</div></div>
          </template>
        </div>
        <div style="background:#ffffff;border:1px solid #dfe6ee;border-radius:12px;padding:17px 19px">
          <div style="font-size:13px;font-weight:700;margin-bottom:3px">Before locking September</div>
          <div style="font-size:11.5px;color:#7d8b9c;margin-bottom:14px">All four must be done</div>
          <div style="display:grid;gap:11px">
            <template v-for="(c, $index) in vm.ac_checks" :key="$index">
              <div @click="c.onClick" :style="c.rowStyle">
                <div :style="c.box">{{ c.mark }}</div>
                <div style="flex:1">
                  <div style="font-size:12.5px;font-weight:600">{{ c.name }}</div>
                  <div :style="c.noteStyle">{{ c.note }}</div>
                </div>
              </div>
            </template>
          </div>
          <div @click="vm.ac_lockSep" :style="vm.ac_lockStyle">{{ vm.ac_lockLabel }}</div>
        </div>
      </div>
    </div>
  </template>
</template>

<script setup lang="ts">
import { usePageVm } from '../../erp/useErpPage';

const vm = usePageVm();
</script>
