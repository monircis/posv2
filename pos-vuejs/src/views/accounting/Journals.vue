<template>
  <template v-if="vm.ac_isJournals">
    <div>
      <div style="padding:20px 24px 0;background:#ffffff;border-bottom:1px solid #dfe6ee">
        <div style="display:flex;align-items:center;gap:7px;font-size:11.5px;color:#7d8b9c;margin-bottom:8px"><span>Accounting</span><span style="color:#c2cedb">/</span><span style="color:#0f1c2b;font-weight:500">Journal entries</span></div>
        <div style="display:flex;align-items:flex-start;gap:20px;flex-wrap:wrap">
          <div>
            <h2 style="margin:0 0 3px;font-size:22px;font-weight:700;letter-spacing:-0.018em">Journal entries</h2>
            <div style="font-size:12.5px;color:#5d6b7d">{{ vm.ac_jSub }}</div>
          </div>
          <div style="margin-left:auto;display:flex;gap:8px;align-items:center">
            <div @click="vm.ac_exportJ" style="height:34px;padding:0 13px;border-radius:8px;border:1px solid #dfe6ee;display:flex;align-items:center;font-size:12.5px;font-weight:500;color:#4a5866;cursor:pointer;background:#ffffff" v-pseudo:hover="'background:#f2f6fa'">Export</div>
            <div @click="vm.ac_newJournal" style="height:34px;padding:0 15px;border-radius:8px;background:var(--c-primary);color:#ffffff;display:flex;align-items:center;font-size:12.5px;font-weight:600;cursor:pointer" v-pseudo:hover="'background:var(--c-primary-hover)'">+ Manual journal</div>
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
            <template v-for="(t, $index) in vm.ac_srcTabs" :key="$index">
              <div @click="t.onClick" :style="t.style">{{ t.name }}</div>
            </template>
          </div>
          <div style="position:relative;width:230px;margin-left:auto"><div style="position:absolute;left:10px;top:50%;transform:translateY(-50%);color:#9aa7b6;font-size:12px">⚲</div> <input :value="vm.ac_jq" @input="vm.ac_onJq" placeholder="Number, reference or narration" style="width:100%;height:34px;border:1px solid #dfe6ee;border-radius:8px;background:#ffffff;padding:0 10px 0 29px;font-family:'Public Sans',sans-serif;font-size:12.5px;outline:none" v-pseudo:focus="'border-color:var(--c-primary)'" /></div>
        </div>
        <div style="background:#ffffff;border:1px solid #dfe6ee;border-radius:12px;overflow:hidden">
          <div style="display:grid;grid-template-columns:0.8fr 0.8fr 0.9fr 2.4fr 1fr 1fr 0.9fr;gap:12px;padding:10px 16px;background:#f6f9fc;border-bottom:1px solid #dfe6ee;font-family:'IBM Plex Mono',monospace;font-size:9.5px;letter-spacing:0.07em;text-transform:uppercase;color:#5d6b7d;align-items:center">
            <div>Journal</div>
            <div>Date</div>
            <div>Source</div>
            <div>Narration</div>
            <div>Reference</div>
            <div style="text-align:right">Amount</div>
            <div>Status</div>
          </div>
          <template v-for="(j, $index) in vm.ac_journals" :key="$index">
            <div>
              <div @click="j.toggle" :style="j.rowStyle" v-pseudo:hover="'background:var(--c-primary-tint)'">
                <div style="font-family:'IBM Plex Mono',monospace;font-size:12.5px;font-weight:500;color:var(--c-primary)">{{ j.no }}</div>
                <div style="font-family:'IBM Plex Mono',monospace;font-size:12px;color:#4a5866">{{ j.date }}</div>
                <div><span :style="j.srcBadge">{{ j.src }}</span></div>
                <div style="font-size:13px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">{{ j.desc }}</div>
                <div style="font-family:'IBM Plex Mono',monospace;font-size:12px;color:#5d6b7d">{{ j.ref }}</div>
                <div style="font-family:'IBM Plex Mono',monospace;font-size:12.5px;text-align:right;font-weight:600">{{ j.amount }}</div>
                <div><span :style="j.stBadge">{{ j.status }}</span></div>
              </div>
              <template v-if="j.open">
                <div style="padding:4px 16px 14px 16px;background:var(--c-primary-tint);border-bottom:1px solid #dfe6ee">
                  <div style="background:#ffffff;border:1px solid #dfe6ee;border-radius:10px;overflow:hidden">
                    <div style="display:grid;grid-template-columns:0.8fr 2.2fr 1fr 1fr;gap:12px;padding:8px 14px;border-bottom:1px solid #eef2f6;font-family:'IBM Plex Mono',monospace;font-size:9.5px;letter-spacing:0.07em;text-transform:uppercase;color:#5d6b7d">
                      <div>Code</div>
                      <div>Account</div>
                      <div style="text-align:right">Debit</div>
                      <div style="text-align:right">Credit</div>
                    </div>
                    <template v-for="(l, $index) in j.lines" :key="$index">
                      <div style="display:grid;grid-template-columns:0.8fr 2.2fr 1fr 1fr;gap:12px;padding:7px 14px;border-bottom:1px solid #f4f7fa;font-size:12.5px">
                        <div style="font-family:'IBM Plex Mono',monospace;color:#5d6b7d">{{ l.code }}</div>
                        <div :style="l.nameStyle">{{ l.name }}</div>
                        <div style="font-family:'IBM Plex Mono',monospace;text-align:right">{{ l.dr }}</div>
                        <div style="font-family:'IBM Plex Mono',monospace;text-align:right">{{ l.cr }}</div>
                      </div>
                    </template>
                    <div style="display:flex;align-items:center;gap:8px;padding:9px 14px;background:#fbfcfe">
                      <div style="font-size:11.5px;color:#7d8b9c;flex:1">{{ j.note }}</div>
                      <template v-for="(a, $index) in j.actions" :key="$index">
                        <div @click="a.onClick" :style="a.style">{{ a.label }}</div>
                      </template>
                    </div>
                  </div>
                </div>
              </template>
            </div>
          </template>
          <template v-if="vm.ac_noJ">
            <div style="padding:40px 20px;text-align:center;font-size:12.5px;color:#7d8b9c">No journals match.</div>
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
