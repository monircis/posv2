<template>
  <template v-if="vm.sReturn">
    <div>
      <div style="padding:20px 24px 16px;background:#ffffff;border-bottom:1px solid #dfe6ee">
        <div style="display:flex;align-items:center;gap:7px;font-size:11.5px;color:#7d8b9c;margin-bottom:8px"><span @click="vm.sGoDetails" style="cursor:pointer;color:var(--c-primary)">2026-5009</span><span style="color:#c2cedb">/</span><span style="color:#0f1c2b;font-weight:500">Return items</span></div>
        <div style="display:flex;align-items:flex-start;gap:20px;flex-wrap:wrap">
          <div>
            <h2 style="margin:0 0 3px;font-size:22px;font-weight:700;letter-spacing:-0.018em">Return from Al Amin Catering</h2>
            <div style="font-size:12.5px;color:#5d6b7d">Against invoice 2026-5009 · same return form as purchases, reversed direction</div>
          </div>
          <div style="margin-left:auto;display:flex;gap:8px;align-items:center">
            <div @click="vm.sGoDetails" style="height:34px;padding:0 13px;border-radius:8px;border:1px solid #dfe6ee;display:flex;align-items:center;font-size:12.5px;font-weight:500;color:#4a5866;cursor:pointer" v-pseudo:hover="'background:#f2f6fa'">Cancel</div>
            <div @click="vm.sGoList" :style="vm.srSubmitStyle">{{ vm.srSubmitLabel }}</div>
          </div>
        </div>
      </div>
      <div style="padding:20px 24px 32px;display:grid;grid-template-columns:1fr 330px;gap:16px;align-items:start">
        <div style="background:#ffffff;border:1px solid #dfe6ee;border-radius:12px;overflow:hidden">
          <div style="padding:14px 16px;border-bottom:1px solid #eaeff5;display:flex;align-items:center;gap:12px;flex-wrap:wrap">
            <div style="max-width:480px">
              <div style="font-size:13px;font-weight:700">What's coming back</div>
              <div style="font-size:11.5px;color:#7d8b9c;margin-top:2px;line-height:1.5">Fresh meat that has left the shop usually can't be resold. Restock is off by default on chilled lines and on for packaged goods. Click it to change.</div>
            </div>
          </div>
          <div style="display:grid;grid-template-columns:1.7fr 0.75fr 1.15fr 1.1fr 0.85fr 0.9fr;gap:10px;padding:9px 16px;background:#f6f9fc;border-bottom:1px solid #dfe6ee;font-family:'IBM Plex Mono',monospace;font-size:9.5px;letter-spacing:0.07em;text-transform:uppercase;color:#5d6b7d">
            <div>Item</div>
            <div style="text-align:right">Sold</div>
            <div style="text-align:center">Return qty</div>
            <div>Reason</div>
            <div style="text-align:center">Restock</div>
            <div style="text-align:right">Refund</div>
          </div>
          <template v-for="(l, $index) in vm.srLines" :key="$index">
            <div :style="l.rowStyle">
              <div style="min-width:0">
                <div style="font-size:13px;font-weight:500;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">{{ l.name }}</div>
                <div style="font-size:10.5px;color:#9aa7b6">{{ l.price }} each</div>
              </div>
              <div style="font-family:'IBM Plex Mono',monospace;font-size:12.5px;text-align:right;color:#5d6b7d">{{ l.sold }}</div>
              <div style="display:flex;align-items:center;border:1px solid #dfe6ee;border-radius:7px;overflow:hidden;height:32px;background:#ffffff">
                <div @click="l.dec" style="width:28px;height:100%;display:grid;place-items:center;font-size:14px;color:#5d6b7d;cursor:pointer;flex:none" v-pseudo:hover="'background:#f2f6fa;color:var(--c-primary)'">−</div>
                <div :style="l.qtyStyle">{{ l.qty }}</div>
                <div @click="l.inc" :style="l.incStyle" v-pseudo:hover="'background:#f2f6fa;color:var(--c-primary)'">+</div>
              </div>
              <div @click="l.cycle" :style="l.reasonStyle">{{ l.reason }}<span style="color:#9aa7b6;font-size:10px">▾</span></div>
              <div @click="l.toggleRestock" style="display:flex;justify-content:center;cursor:pointer">
                <div :style="l.restockTrack">
                  <div :style="l.restockKnob"></div>
                </div>
              </div>
              <div :style="l.refundStyle">{{ l.refund }}</div>
            </div>
          </template>
        </div>
        <div style="display:grid;gap:12px">
          <div style="background:#ffffff;border:1px solid #dfe6ee;border-radius:12px;padding:16px 18px">
            <div style="font-size:12.5px;font-weight:700;margin-bottom:13px">Refund</div>
            <div style="display:grid;gap:9px">
              <div style="display:flex;gap:10px">
                <div style="font-size:12.5px;color:#5d6b7d;flex:1">Items less 8% discount</div>
                <div style="font-family:'IBM Plex Mono',monospace;font-size:13px">{{ vm.srSub }}</div>
              </div>
              <div style="display:flex;gap:10px">
                <div style="font-size:12.5px;color:#5d6b7d;flex:1">GST</div>
                <div style="font-family:'IBM Plex Mono',monospace;font-size:13px">{{ vm.srTax }}</div>
              </div>
            </div>
            <div style="display:flex;align-items:center;gap:12px;padding:12px 14px;background:#0f1c2b;border-radius:10px;margin-top:14px">
              <div style="font-size:12.5px;font-weight:600;color:#cfdbe6;flex:1">Total refund</div>
              <div style="font-family:'IBM Plex Mono',monospace;font-size:22px;font-weight:600;color:#ffffff;letter-spacing:-0.02em">{{ vm.srTotal }}</div>
            </div>
          </div>
          <div style="background:#ffffff;border:1px solid #dfe6ee;border-radius:12px;padding:16px 18px">
            <div style="font-size:12.5px;font-weight:700;margin-bottom:11px">Refund as</div>
            <div style="display:grid;gap:7px;margin-bottom:12px">
              <template v-for="(s, $index) in vm.srSettle" :key="$index">
                <div @click="s.onClick" :style="s.style">
                  <div :style="s.radio">
                    <div :style="s.inner"></div>
                  </div>
                  <div style="flex:1;min-width:0">
                    <div style="font-size:12.5px;font-weight:600">{{ s.name }}</div>
                    <div style="font-size:11.5px;color:#7d8b9c;line-height:1.45;margin-top:1px">{{ s.desc }}</div>
                  </div>
                </div>
              </template>
            </div>
            <div style="font-size:11.5px;color:#7d8b9c;line-height:1.5">{{ vm.srHint }}</div>
          </div>
          <div :style="vm.srImpactBox">
            <div style="font-size:12px;font-weight:700;margin-bottom:7px">On submit</div>
            <div style="font-size:12px;line-height:1.6">{{ vm.srImpact }}</div>
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
