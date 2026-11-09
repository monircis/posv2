<template>
  <template v-if="vm.ac_isForm">
    <div>
      <div style="padding:20px 24px 16px;background:#ffffff;border-bottom:1px solid #dfe6ee">
        <div style="display:flex;align-items:center;gap:7px;font-size:11.5px;color:#7d8b9c;margin-bottom:8px"><span @click="vm.ac_goJournals" style="cursor:pointer;color:var(--c-primary)">Journal entries</span><span style="color:#c2cedb">/</span><span style="color:#0f1c2b;font-weight:500">{{ vm.ac_fCrumb }}</span></div>
        <div style="display:flex;align-items:flex-start;gap:20px;flex-wrap:wrap">
          <div>
            <h2 style="margin:0 0 3px;font-size:22px;font-weight:700;letter-spacing:-0.018em">{{ vm.ac_fTitle }}</h2>
            <div style="font-size:12.5px;color:#5d6b7d">For entries no module creates: depreciation, accruals, owner drawings, corrections. Debits must equal credits.</div>
          </div>
          <div style="margin-left:auto;display:flex;gap:8px;align-items:center">
            <div @click="vm.ac_goJournals" style="height:34px;padding:0 13px;border-radius:8px;border:1px solid #dfe6ee;display:flex;align-items:center;font-size:12.5px;font-weight:500;color:#4a5866;cursor:pointer;background:#ffffff" v-pseudo:hover="'background:#f2f6fa'">Cancel</div>
            <div @click="vm.ac_saveDraft" style="height:34px;padding:0 13px;border-radius:8px;border:1px solid #dfe6ee;display:flex;align-items:center;font-size:12.5px;font-weight:500;color:#4a5866;cursor:pointer;background:#ffffff;font-weight:600;color:var(--c-primary-text)" v-pseudo:hover="'background:#f2f6fa'">Save draft</div>
            <div @click="vm.ac_postJ" :style="vm.ac_postStyle">Post journal</div>
          </div>
        </div>
      </div>
      <div style="padding:20px 24px 32px;display:grid;grid-template-columns:minmax(0,1fr) 300px;gap:16px;align-items:start">
        <div style="display:grid;gap:14px">
          <div style="background:#ffffff;border:1px solid #dfe6ee;border-radius:12px;padding:17px 19px;display:grid;grid-template-columns:1fr 1fr 2fr;gap:14px">
            <div><label style="display:block;font-size:12px;font-weight:600;margin-bottom:5px">Date</label> <input :value="vm.ac_fDate" @input="vm.ac_onFDate" :style="vm.ac_fDateStyle" /></div>
            <div><label style="display:block;font-size:12px;font-weight:600;margin-bottom:5px">Reference</label> <input :value="vm.ac_fRef" @input="vm.ac_onFRef" placeholder="Optional" style="width:100%;height:36px;border:1px solid #dfe6ee;border-radius:8px;padding:0 11px;font-family:'Public Sans',sans-serif;font-size:13px;outline:none;background:#ffffff;font-family:'IBM Plex Mono',monospace;font-size:12.5px" v-pseudo:focus="'border-color:var(--c-primary)'" /></div>
            <div><label style="display:block;font-size:12px;font-weight:600;margin-bottom:5px">Narration <span style="color:#d93a3a">*</span></label> <input :value="vm.ac_fDesc" @input="vm.ac_onFDesc" placeholder="e.g. Depreciation for September" style="width:100%;height:36px;border:1px solid #dfe6ee;border-radius:8px;padding:0 11px;font-family:'Public Sans',sans-serif;font-size:13px;outline:none;background:#ffffff" v-pseudo:focus="'border-color:var(--c-primary)'" /></div>
          </div>
          <div style="background:#ffffff;border:1px solid #dfe6ee;border-radius:12px;overflow:hidden">
            <div style="padding:11px 16px;border-bottom:1px solid #eaeff5;display:flex;align-items:center;gap:8px;flex-wrap:wrap">
              <div style="font-size:12px;color:#7d8b9c;margin-right:4px">Start from:</div>
              <template v-for="(t, $index) in vm.ac_templates" :key="$index">
                <div @click="t.onClick" style="height:28px;padding:0 10px;border-radius:7px;border:1px solid #dfe6ee;display:flex;align-items:center;font-size:12px;color:#4a5866;cursor:pointer" v-pseudo:hover="'border-color:var(--c-primary);color:var(--c-primary)'">{{ t.name }}</div>
              </template>
            </div>
            <div style="display:grid;grid-template-columns:24px minmax(0,2fr) minmax(0,1.6fr) 1fr 1fr 28px;gap:10px;padding:9px 16px;background:#f6f9fc;border-bottom:1px solid #dfe6ee;font-family:'IBM Plex Mono',monospace;font-size:9.5px;letter-spacing:0.07em;text-transform:uppercase;color:#5d6b7d">
              <div>#</div>
              <div>Account</div>
              <div>Line description</div>
              <div style="text-align:right">Debit</div>
              <div style="text-align:right">Credit</div>
              <div></div>
            </div>
            <template v-for="(l, $index) in vm.ac_fLines" :key="$index">
              <div style="display:grid;grid-template-columns:24px minmax(0,2fr) minmax(0,1.6fr) 1fr 1fr 28px;gap:10px;padding:8px 16px;border-bottom:1px solid #f0f4f8;align-items:center"><div style="font-family:'IBM Plex Mono',monospace;font-size:11px;color:#9aa7b6">{{ l.n }}</div> <select :value="l.code" @change="l.onCode" :style="l.selStyle">
                <option value="">Choose account</option>
                <template v-for="(o, $index) in vm.ac_acctOpts" :key="$index">
                  <option :value="o.code">{{ o.label }}</option>
                </template>
              </select> <input :value="l.desc" @input="l.onDesc" placeholder="Optional" style="width:100%;height:36px;border:1px solid #dfe6ee;border-radius:8px;padding:0 11px;font-family:'Public Sans',sans-serif;font-size:13px;outline:none;background:#ffffff;height:32px;font-size:12.5px" v-pseudo:focus="'border-color:var(--c-primary)'" /> <input :value="l.dr" @input="l.onDr" placeholder="0.00" style="width:100%;height:36px;border:1px solid #dfe6ee;border-radius:8px;padding:0 11px;font-family:'Public Sans',sans-serif;font-size:13px;outline:none;background:#ffffff;height:32px;font-family:'IBM Plex Mono',monospace;font-size:12.5px;text-align:right" v-pseudo:focus="'border-color:var(--c-primary)'" /> <input :value="l.cr" @input="l.onCr" placeholder="0.00" style="width:100%;height:36px;border:1px solid #dfe6ee;border-radius:8px;padding:0 11px;font-family:'Public Sans',sans-serif;font-size:13px;outline:none;background:#ffffff;height:32px;font-family:'IBM Plex Mono',monospace;font-size:12.5px;text-align:right" v-pseudo:focus="'border-color:var(--c-primary)'" /> <div @click="l.remove" style="width:26px;height:26px;border-radius:6px;display:grid;place-items:center;color:#b3c0cf;cursor:pointer;font-size:14px" v-pseudo:hover="'background:#fdeaea;color:#b02020'">×</div></div>
            </template>
            <div style="display:grid;grid-template-columns:24px minmax(0,2fr) minmax(0,1.6fr) 1fr 1fr 28px;gap:10px;padding:10px 16px;background:#fbfcfe;align-items:center">
              <div></div>
              <div @click="vm.ac_addLine" style="font-size:12.5px;font-weight:600;color:var(--c-primary);cursor:pointer">+ Add line</div>
              <div style="font-size:12px;color:#7d8b9c;text-align:right">Totals</div>
              <div style="font-family:'IBM Plex Mono',monospace;font-size:13px;font-weight:700;text-align:right">{{ vm.ac_fDr }}</div>
              <div style="font-family:'IBM Plex Mono',monospace;font-size:13px;font-weight:700;text-align:right">{{ vm.ac_fCr }}</div>
              <div></div>
            </div>
          </div>
        </div>
        <div style="display:grid;gap:14px;position:sticky;top:20px">
          <div :style="vm.ac_balBox">
            <div style="font-size:12px;font-weight:600">{{ vm.ac_balLabel }}</div>
            <div style="font-family:'IBM Plex Mono',monospace;font-size:24px;font-weight:600;letter-spacing:-0.02em;margin-top:3px">{{ vm.ac_balValue }}</div>
            <div style="font-size:11.5px;margin-top:4px;opacity:.85">{{ vm.ac_balHint }}</div>
          </div>
          <template v-if="vm.ac_fHasErr">
            <div style="background:#fdeaea;border:1px solid #f3cfcf;border-radius:12px;padding:12px 15px;display:grid;gap:5px">
              <template v-for="(e, $index) in vm.ac_fErrs" :key="$index">
                <div style="font-size:12px;color:#b02020;display:flex;gap:7px"><span style="font-weight:700">×</span>{{ e.msg }}</div>
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
