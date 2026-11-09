<template>
  <div>
    <RouterView />

    <!-- overlays -->
    <template v-if="vm.ac_ledgerOpen">
      <div @click="vm.ac_closeAll" style="position:absolute;inset:0;background:rgba(9,22,35,.42);z-index:30"></div>
      <div style="position:absolute;top:0;right:0;bottom:0;width:560px;background:#ffffff;z-index:31;display:flex;flex-direction:column;box-shadow:-20px 0 50px -24px rgba(9,22,35,.6)">
        <div style="padding:18px 22px;border-bottom:1px solid #eaeff5;display:flex;align-items:flex-start;gap:12px">
          <div>
            <div style="font-family:'IBM Plex Mono',monospace;font-size:11.5px;color:#7d8b9c">{{ vm.ac_lg.code }} · {{ vm.ac_lg.type }}</div>
            <div style="font-size:17px;font-weight:700;margin-top:2px">{{ vm.ac_lg.name }}</div>
          </div>
          <div style="margin-left:auto;text-align:right">
            <div style="font-size:11px;color:#7d8b9c">Balance</div>
            <div style="font-family:'IBM Plex Mono',monospace;font-size:18px;font-weight:600">{{ vm.ac_lg.bal }}</div>
          </div>
          <div @click="vm.ac_closeAll" style="width:32px;height:32px;border-radius:8px;border:1px solid #dfe6ee;display:grid;place-items:center;cursor:pointer;color:#5d6b7d;font-size:15px;flex:none" v-pseudo:hover="'background:#f2f6fa'">×</div>
        </div>
        <div style="display:grid;grid-template-columns:0.7fr 0.8fr 2fr 0.9fr 0.9fr 1fr;gap:10px;padding:9px 22px;background:#f6f9fc;border-bottom:1px solid #dfe6ee;font-family:'IBM Plex Mono',monospace;font-size:9.5px;letter-spacing:0.07em;text-transform:uppercase;color:#5d6b7d">
          <div>Date</div>
          <div>Journal</div>
          <div>Narration</div>
          <div style="text-align:right">Debit</div>
          <div style="text-align:right">Credit</div>
          <div style="text-align:right">Balance</div>
        </div>
        <div style="flex:1;overflow-y:auto">
          <template v-for="(r, $index) in vm.ac_lg.rows" :key="$index">
            <div :style="r.style">
              <div style="font-family:'IBM Plex Mono',monospace;font-size:11.5px;color:#4a5866">{{ r.date }}</div>
              <div style="font-family:'IBM Plex Mono',monospace;font-size:11.5px;color:var(--c-primary)">{{ r.no }}</div>
              <div style="font-size:12.5px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">{{ r.desc }}</div>
              <div style="font-family:'IBM Plex Mono',monospace;font-size:12px;text-align:right">{{ r.dr }}</div>
              <div style="font-family:'IBM Plex Mono',monospace;font-size:12px;text-align:right">{{ r.cr }}</div>
              <div style="font-family:'IBM Plex Mono',monospace;font-size:12px;text-align:right;font-weight:600">{{ r.bal }}</div>
            </div>
          </template>
        </div>
      </div>
    </template>

    <template v-if="vm.ac_acctFormOpen">
      <div @click="vm.ac_closeAll" style="position:absolute;inset:0;background:rgba(9,22,35,.42);z-index:30"></div>
      <div style="position:absolute;top:0;right:0;bottom:0;width:440px;background:#ffffff;z-index:31;display:flex;flex-direction:column;box-shadow:-20px 0 50px -24px rgba(9,22,35,.6)">
        <div style="padding:18px 22px;border-bottom:1px solid #eaeff5;display:flex;align-items:center;gap:12px">
          <div style="font-size:16px;font-weight:700">Add account</div>
          <div @click="vm.ac_closeAll" style="margin-left:auto;width:32px;height:32px;border-radius:8px;border:1px solid #dfe6ee;display:grid;place-items:center;cursor:pointer;color:#5d6b7d;font-size:15px" v-pseudo:hover="'background:#f2f6fa'">×</div>
        </div>
        <div style="flex:1;overflow-y:auto;padding:18px 22px;display:grid;gap:15px;align-content:start">
          <div><label style="display:block;font-size:12px;font-weight:600;margin-bottom:5px">Type</label> <select :value="vm.ac_nType" @change="vm.ac_onNType" style="width:100%;height:36px;border:1px solid #dfe6ee;border-radius:8px;padding:0 11px;font-family:'Public Sans',sans-serif;font-size:13px;outline:none;background:#ffffff;cursor:pointer;padding:0 8px">
            <template v-for="(o, $index) in vm.ac_typeOpts" :key="$index">
              <option :value="o.id">{{ o.name }}</option>
            </template>
          </select></div>
          <div style="display:grid;grid-template-columns:1fr 2fr;gap:12px">
            <div><label style="display:block;font-size:12px;font-weight:600;margin-bottom:5px">Code</label> <input :value="vm.ac_nCode" @input="vm.ac_onNCode" style="width:100%;height:36px;border:1px solid #dfe6ee;border-radius:8px;padding:0 11px;font-family:'Public Sans',sans-serif;font-size:13px;outline:none;background:#ffffff;font-family:'IBM Plex Mono',monospace;font-size:12.5px" v-pseudo:focus="'border-color:var(--c-primary)'" /></div>
            <div><label style="display:block;font-size:12px;font-weight:600;margin-bottom:5px">Name <span style="color:#d93a3a">*</span></label> <input :value="vm.ac_nName" @input="vm.ac_onNName" placeholder="e.g. Insurance" style="width:100%;height:36px;border:1px solid #dfe6ee;border-radius:8px;padding:0 11px;font-family:'Public Sans',sans-serif;font-size:13px;outline:none;background:#ffffff" v-pseudo:focus="'border-color:var(--c-primary)'" /></div>
          </div>
          <div><label style="display:block;font-size:12px;font-weight:600;margin-bottom:5px">Default tax</label> <select :value="vm.ac_nTax" @change="vm.ac_onNTax" style="width:100%;height:36px;border:1px solid #dfe6ee;border-radius:8px;padding:0 11px;font-family:'Public Sans',sans-serif;font-size:13px;outline:none;background:#ffffff;cursor:pointer;padding:0 8px">
            <option value="GST 10%">GST 10%</option>
            <option value="GST free">GST free</option>
            <option value="BAS excluded">BAS excluded</option>
          </select></div>
          <div style="font-size:11.5px;color:#7d8b9c;line-height:1.5">{{ vm.ac_nHint }}</div>
          <template v-if="vm.ac_nHasErr">
            <div style="background:#fdeaea;border:1px solid #f3cfcf;border-radius:10px;padding:10px 13px;font-size:12px;color:#b02020">{{ vm.ac_nErr }}</div>
          </template>
        </div>
        <div style="padding:14px 22px;border-top:1px solid #eaeff5;display:flex;gap:8px;justify-content:flex-end">
          <div @click="vm.ac_closeAll" style="height:34px;padding:0 13px;border-radius:8px;border:1px solid #dfe6ee;display:flex;align-items:center;font-size:12.5px;font-weight:500;color:#4a5866;cursor:pointer;background:#ffffff" v-pseudo:hover="'background:#f2f6fa'">Cancel</div>
          <div @click="vm.ac_saveNewAcct" style="height:34px;padding:0 15px;border-radius:8px;background:var(--c-primary);color:#ffffff;display:flex;align-items:center;font-size:12.5px;font-weight:600;cursor:pointer" v-pseudo:hover="'background:var(--c-primary-hover)'">Add account</div>
        </div>
      </div>
    </template>

    <ToastStack :toasts="vm.ac_toasts" />
  </div>
</template>

<script setup lang="ts">
import ToastStack from '../../components/ToastStack.vue';
import { useErpPage } from '../../erp/useErpPage';

const vm = useErpPage('accounting', 'ac_nav');
</script>
