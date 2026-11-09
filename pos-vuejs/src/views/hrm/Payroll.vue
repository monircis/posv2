<template>
  <template v-if="vm.hr_isPay">
    <div style="display:grid;gap:14px">
      <div :style="vm.hr_payBanner">
        <div style="flex:1">
          <div style="font-size:13.5px;font-weight:700">{{ vm.hr_payTitle }}</div>
          <div style="font-size:12px;margin-top:2px;opacity:.85">{{ vm.hr_paySub }}</div>
        </div>
        <div style="display:flex;gap:4px;align-items:center">
          <template v-for="(s, $index) in vm.hr_paySteps" :key="$index">
            <div :style="s.style">{{ s.name }}</div>
          </template>
        </div>
      </div>
      <div style="display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px">
        <template v-for="(k, $index) in vm.hr_payKpis" :key="$index">
          <div style="background:#ffffff;border:1px solid #dfe6ee;border-radius:12px;padding:13px 16px">
            <div style="font-size:11.5px;color:#7d8b9c">{{ k.k }}</div>
            <div style="font-family:'IBM Plex Mono',monospace;font-size:20px;font-weight:600;margin-top:2px">{{ k.v }}</div>
          </div>
        </template>
      </div>
      <div style="background:#ffffff;border:1px solid #dfe6ee;border-radius:12px;overflow:hidden">
        <div style="display:grid;grid-template-columns:1.6fr 0.8fr 0.8fr 0.7fr 0.9fr 0.8fr 0.8fr 0.9fr;gap:10px;padding:10px 16px;background:#f6f9fc;border-bottom:1px solid #dfe6ee;font-family:'IBM Plex Mono',monospace;font-size:9.5px;letter-spacing:0.07em;text-transform:uppercase;color:#5d6b7d">
          <div>Employee</div>
          <div style="text-align:right">Ordinary h</div>
          <div style="text-align:right">Overtime h</div>
          <div style="text-align:right">Rate</div>
          <div style="text-align:right">Gross</div>
          <div style="text-align:right">Tax est.</div>
          <div style="text-align:right">Super</div>
          <div style="text-align:right">Net pay</div>
        </div>
        <template v-for="(p, $index) in vm.hr_pay" :key="$index">
          <div style="display:grid;grid-template-columns:1.6fr 0.8fr 0.8fr 0.7fr 0.9fr 0.8fr 0.8fr 0.9fr;gap:10px;padding:8px 16px;border-bottom:1px solid #f0f4f8;align-items:center"><div style="min-width:0">
            <div style="font-size:13px;font-weight:600">{{ p.name }}</div>
            <div style="font-size:11px;color:#8b98a8">{{ p.type }}</div>
          </div> <input :value="p.ord" @input="p.onOrd" :disabled="p.locked" :style="p.inStyle" /> <input :value="p.ot" @input="p.onOt" :disabled="p.locked" :style="p.inStyle" /> <div style="font-family:'IBM Plex Mono',monospace;font-size:12.5px;text-align:right;color:#4a5866">{{ p.rate }}</div> <div style="font-family:'IBM Plex Mono',monospace;font-size:12.5px;text-align:right">{{ p.gross }}</div> <div style="font-family:'IBM Plex Mono',monospace;font-size:12.5px;text-align:right;color:#b02020">{{ p.tax }}</div> <div style="font-family:'IBM Plex Mono',monospace;font-size:12.5px;text-align:right;color:#5d6b7d">{{ p.sup }}</div> <div style="font-family:'IBM Plex Mono',monospace;font-size:13px;text-align:right;font-weight:700">{{ p.net }}</div></div>
        </template>
        <div style="display:grid;grid-template-columns:1.6fr 0.8fr 0.8fr 0.7fr 0.9fr 0.8fr 0.8fr 0.9fr;gap:10px;padding:11px 16px;background:#f6f9fc;font-weight:700;align-items:center">
          <div style="font-size:13px">Total</div>
          <div style="font-family:'IBM Plex Mono',monospace;font-size:12.5px;text-align:right">{{ vm.hr_pt.ord }}</div>
          <div style="font-family:'IBM Plex Mono',monospace;font-size:12.5px;text-align:right">{{ vm.hr_pt.ot }}</div>
          <div></div>
          <div style="font-family:'IBM Plex Mono',monospace;font-size:12.5px;text-align:right">{{ vm.hr_pt.gross }}</div>
          <div style="font-family:'IBM Plex Mono',monospace;font-size:12.5px;text-align:right">{{ vm.hr_pt.tax }}</div>
          <div style="font-family:'IBM Plex Mono',monospace;font-size:12.5px;text-align:right">{{ vm.hr_pt.sup }}</div>
          <div style="font-family:'IBM Plex Mono',monospace;font-size:13px;text-align:right">{{ vm.hr_pt.net }}</div>
        </div>
      </div>
    </div>
  </template>
</template>

<script setup lang="ts">
import { usePageVm } from '../../erp/useErpPage';

const vm = usePageVm();
</script>
