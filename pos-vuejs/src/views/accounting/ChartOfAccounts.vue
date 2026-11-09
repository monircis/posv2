<template>
  <template v-if="vm.ac_isCoa">
    <div>
      <div style="padding:20px 24px 0;background:#ffffff;border-bottom:1px solid #dfe6ee">
        <div style="display:flex;align-items:center;gap:7px;font-size:11.5px;color:#7d8b9c;margin-bottom:8px"><span>Accounting</span><span style="color:#c2cedb">/</span><span style="color:#0f1c2b;font-weight:500">Chart of accounts</span></div>
        <div style="display:flex;align-items:flex-start;gap:20px;flex-wrap:wrap">
          <div>
            <h2 style="margin:0 0 3px;font-size:22px;font-weight:700;letter-spacing:-0.018em">Chart of accounts</h2>
            <div style="font-size:12.5px;color:#5d6b7d">{{ vm.ac_coaSub }}</div>
          </div>
          <div style="margin-left:auto;display:flex;gap:8px;align-items:center">
            <div @click="vm.ac_exportCoa" style="height:34px;padding:0 13px;border-radius:8px;border:1px solid #dfe6ee;display:flex;align-items:center;font-size:12.5px;font-weight:500;color:#4a5866;cursor:pointer;background:#ffffff" v-pseudo:hover="'background:#f2f6fa'">Export</div>
            <div @click="vm.ac_openAddAcct" style="height:34px;padding:0 15px;border-radius:8px;background:var(--c-primary);color:#ffffff;display:flex;align-items:center;font-size:12.5px;font-weight:600;cursor:pointer" v-pseudo:hover="'background:var(--c-primary-hover)'">+ Add account</div>
          </div>
        </div>
        <div style="display:flex;gap:2px;margin-top:16px">
          <template v-for="(t, $index) in vm.ac_subTabs" :key="$index">
            <div @click="t.onClick" :style="t.style">{{ t.name }}<span :style="t.countStyle">{{ t.count }}</span></div>
          </template>
        </div>
      </div>
      <div style="padding:20px 24px 32px">
        <div style="display:flex;gap:8px;align-items:center;margin-bottom:14px;flex-wrap:wrap">
          <div style="display:flex;gap:3px;background:#e4eaf1;padding:3px;border-radius:9px">
            <template v-for="(t, $index) in vm.ac_typeTabs" :key="$index">
              <div @click="t.onClick" :style="t.style">{{ t.name }}</div>
            </template>
          </div>
          <div style="position:relative;width:230px;margin-left:auto"><div style="position:absolute;left:10px;top:50%;transform:translateY(-50%);color:#9aa7b6;font-size:12px">⚲</div> <input :value="vm.ac_cq" @input="vm.ac_onCq" placeholder="Code or account name" style="width:100%;height:34px;border:1px solid #dfe6ee;border-radius:8px;background:#ffffff;padding:0 10px 0 29px;font-family:'Public Sans',sans-serif;font-size:12.5px;outline:none" v-pseudo:focus="'border-color:var(--c-primary)'" /></div>
        </div>
        <div style="background:#ffffff;border:1px solid #dfe6ee;border-radius:12px;overflow:hidden">
          <div style="display:grid;grid-template-columns:0.8fr 2.4fr 1fr 0.8fr 1.1fr;gap:12px;padding:10px 16px;background:#f6f9fc;border-bottom:1px solid #dfe6ee;font-family:'IBM Plex Mono',monospace;font-size:9.5px;letter-spacing:0.07em;text-transform:uppercase;color:#5d6b7d;align-items:center">
            <div>Code</div>
            <div>Account</div>
            <div>Default tax</div>
            <div style="text-align:right">Entries</div>
            <div style="text-align:right">Balance</div>
          </div>
          <template v-for="(g, $index) in vm.ac_groups" :key="$index">
            <div>
              <div style="display:flex;align-items:center;gap:10px;padding:10px 16px;background:#fbfcfe;border-bottom:1px solid #eef2f6">
                <div :style="g.dot"></div>
                <div style="font-size:12.5px;font-weight:700;flex:1">{{ g.name }}</div>
                <div style="font-size:11px;color:#8b98a8">{{ g.note }}</div>
                <div style="font-family:'IBM Plex Mono',monospace;font-size:12.5px;font-weight:700;width:120px;text-align:right">{{ g.total }}</div>
              </div>
              <template v-for="(r, $index) in g.rows" :key="$index">
                <div @click="r.open" style="display:grid;grid-template-columns:0.8fr 2.4fr 1fr 0.8fr 1.1fr;gap:12px;padding:9px 16px;border-bottom:1px solid #f0f4f8;align-items:center;cursor:pointer" v-pseudo:hover="'background:var(--c-primary-tint)'">
                  <div style="font-family:'IBM Plex Mono',monospace;font-size:12px;color:#5d6b7d">{{ r.code }}</div>
                  <div style="min-width:0;display:flex;align-items:center;gap:8px"><div style="font-size:13px;font-weight:500;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">{{ r.name }}</div> <span :style="r.tagStyle">{{ r.tag }}</span></div>
                  <div style="font-size:12px;color:#7d8b9c">{{ r.tax }}</div>
                  <div style="font-family:'IBM Plex Mono',monospace;font-size:12px;text-align:right;color:#7d8b9c">{{ r.count }}</div>
                  <div :style="r.balStyle">{{ r.bal }}</div>
                </div>
              </template>
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
