<template>
  <template v-if="vm.hr_isList">
    <div style="display:grid;gap:14px">
      <div style="display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px">
        <template v-for="(k, $index) in vm.hr_kpis" :key="$index">
          <div style="background:#ffffff;border:1px solid #dfe6ee;border-radius:12px;padding:13px 16px">
            <div style="font-size:11.5px;color:#7d8b9c">{{ k.k }}</div>
            <div style="font-family:'IBM Plex Mono',monospace;font-size:21px;font-weight:600;letter-spacing:-0.02em;margin-top:2px">{{ k.v }}</div>
            <div style="font-size:11.5px;color:#8b98a8;margin-top:1px">{{ k.n }}</div>
          </div>
        </template>
      </div>
      <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap"><div style="position:relative;width:230px"><div style="position:absolute;left:10px;top:50%;transform:translateY(-50%);color:#9aa7b6;font-size:12px">⚲</div><input :value="vm.hr_q" @input="vm.hr_onQ" placeholder="Name, role or phone" style="width:100%;height:34px;border:1px solid #dfe6ee;border-radius:8px;background:#ffffff;padding:0 10px 0 29px;font-family:'Public Sans',sans-serif;font-size:12.5px;outline:none" v-pseudo:focus="'border-color:var(--c-primary)'" /></div> <select :value="vm.hr_dept" @change="vm.hr_onDept" style="height:34px;border:1px solid #dfe6ee;border-radius:8px;background:#ffffff;padding:0 10px;font-family:'Public Sans',sans-serif;font-size:12.5px;color:#4a5866;outline:none;cursor:pointer">
        <option value="all">All departments</option>
        <template v-for="(o, $index) in vm.hr_deptOpts" :key="$index">
          <option :value="o.v">{{ o.v }}</option>
        </template>
      </select> <select :value="vm.hr_loc" @change="vm.hr_onLoc" style="height:34px;border:1px solid #dfe6ee;border-radius:8px;background:#ffffff;padding:0 10px;font-family:'Public Sans',sans-serif;font-size:12.5px;color:#4a5866;outline:none;cursor:pointer">
        <option value="all">All locations</option>
        <template v-for="(o, $index) in vm.hr_locOpts" :key="$index">
          <option :value="o.v">{{ o.v }}</option>
        </template>
      </select> <div style="display:flex;gap:3px;background:#e4eaf1;padding:3px;border-radius:9px;margin-left:auto">
        <template v-for="(s, $index) in vm.hr_statusSeg" :key="$index">
          <div @click="s.onClick" :style="s.style">{{ s.name }}</div>
        </template>
      </div></div>
      <div style="background:#ffffff;border:1px solid #dfe6ee;border-radius:12px;overflow:hidden">
        <div style="display:grid;grid-template-columns:1.9fr 1.1fr 1fr 0.9fr 0.8fr 0.9fr;gap:12px;padding:10px 16px;background:#f6f9fc;border-bottom:1px solid #dfe6ee;font-family:'IBM Plex Mono',monospace;font-size:9.5px;letter-spacing:0.07em;text-transform:uppercase;color:#5d6b7d">
          <div>Employee</div>
          <div>Department</div>
          <div>Location</div>
          <div>Type</div>
          <div style="text-align:right">Rate</div>
          <div>Status</div>
        </div>
        <template v-for="(r, $index) in vm.hr_rows" :key="$index">
          <div @click="r.open" style="display:grid;grid-template-columns:1.9fr 1.1fr 1fr 0.9fr 0.8fr 0.9fr;gap:12px;padding:10px 16px;border-bottom:1px solid #f0f4f8;align-items:center;cursor:pointer" v-pseudo:hover="'background:var(--c-primary-tint)'">
            <div style="display:flex;align-items:center;gap:10px;min-width:0">
              <div :style="r.avatar">{{ r.ini }}</div>
              <div style="min-width:0">
                <div style="font-size:13px;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">{{ r.name }}</div>
                <div style="font-size:11.5px;color:#8b98a8;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">{{ r.role }}</div>
              </div>
            </div>
            <div style="font-size:12.5px;color:#4a5866">{{ r.dept }}</div>
            <div style="font-size:12.5px;color:#4a5866">{{ r.loc }}</div>
            <div style="font-size:12.5px;color:#4a5866">{{ r.type }}</div>
            <div style="font-family:'IBM Plex Mono',monospace;font-size:12.5px;text-align:right">{{ r.rate }}</div>
            <div><span :style="r.badge">{{ r.status }}</span></div>
          </div>
        </template>
        <template v-if="vm.hr_noRows">
          <div style="padding:36px 20px;text-align:center;font-size:12.5px;color:#7d8b9c">No employees match.</div>
        </template>
      </div>
    </div>
  </template>
</template>

<script setup lang="ts">
import { usePageVm } from '../../erp/useErpPage';

const vm = usePageVm();
</script>
