<template>
  <template v-if="vm.ac_isStatements">
    <div>
      <div style="padding:20px 24px 0;background:#ffffff;border-bottom:1px solid #dfe6ee">
        <div style="display:flex;align-items:center;gap:7px;font-size:11.5px;color:#7d8b9c;margin-bottom:8px"><span>Accounting</span><span style="color:#c2cedb">/</span><span style="color:#0f1c2b;font-weight:500">Statements</span></div>
        <div style="display:flex;align-items:flex-start;gap:20px;flex-wrap:wrap">
          <div>
            <h2 style="margin:0 0 3px;font-size:22px;font-weight:700;letter-spacing:-0.018em">Financial statements</h2>
            <div style="font-size:12.5px;color:#5d6b7d">September 2026 · posted journals only · drafts excluded</div>
          </div>
          <div style="margin-left:auto;display:flex;gap:8px;align-items:center">
            <div @click="vm.ac_printSt" style="height:34px;padding:0 13px;border-radius:8px;border:1px solid #dfe6ee;display:flex;align-items:center;font-size:12.5px;font-weight:500;color:#4a5866;cursor:pointer;background:#ffffff" v-pseudo:hover="'background:#f2f6fa'">Print</div>
            <div @click="vm.ac_exportSt" style="height:34px;padding:0 13px;border-radius:8px;border:1px solid #dfe6ee;display:flex;align-items:center;font-size:12.5px;font-weight:500;color:#4a5866;cursor:pointer;background:#ffffff" v-pseudo:hover="'background:#f2f6fa'">Export PDF</div>
          </div>
        </div>
        <div style="display:flex;gap:2px;margin-top:16px">
          <template v-for="(t, $index) in vm.ac_subTabs" :key="$index">
            <div @click="t.onClick" :style="t.style">{{ t.name }}<span :style="t.countStyle">{{ t.count }}</span></div>
          </template>
        </div>
      </div>
      <div style="padding:20px 24px 32px">
        <div style="display:flex;align-items:center;gap:10px;margin-bottom:14px">
          <div style="display:flex;gap:3px;background:#e4eaf1;padding:3px;border-radius:9px">
            <template v-for="(t, $index) in vm.ac_stTabs" :key="$index">
              <div @click="t.onClick" :style="t.style">{{ t.name }}</div>
            </template>
          </div>
          <div :style="vm.ac_stCheck">{{ vm.ac_stCheckText }}</div>
        </div>
        <div style="max-width:860px;background:#ffffff;border:1px solid #dfe6ee;border-radius:12px;padding:8px 0 4px">
          <template v-for="(r, $index) in vm.ac_stRows" :key="$index">
            <div @click="r.onClick" :style="r.style">
              <div style="flex:1;min-width:0;display:flex;align-items:baseline;gap:10px">
                <div :style="r.codeStyle">{{ r.code }}</div>
                <div>{{ r.label }}</div>
              </div>
              <div :style="r.aStyle">{{ r.a }}</div>
              <div :style="r.bStyle">{{ r.b }}</div>
            </div>
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
