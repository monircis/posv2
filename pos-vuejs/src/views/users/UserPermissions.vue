<template>
  <template v-if="vm.uMatrix">
    <div>
      <div style="padding:20px 24px 16px;background:#ffffff;border-bottom:1px solid #dfe6ee">
        <div style="display:flex;align-items:center;gap:7px;font-size:11.5px;color:#7d8b9c;margin-bottom:8px"><span @click="vm.uGoRoles" style="cursor:pointer;color:var(--c-primary)">Roles</span><span style="color:#c2cedb">/</span><span style="color:#0f1c2b;font-weight:500">{{ vm.presetName }}</span></div>
        <div style="display:flex;align-items:flex-start;gap:20px;flex-wrap:wrap">
          <div>
            <h2 style="margin:0 0 3px;font-size:22px;font-weight:700;letter-spacing:-0.018em">Role permissions</h2>
            <div style="font-size:12.5px;color:#5d6b7d">{{ vm.grantSummary }}</div>
          </div>
          <div style="margin-left:auto;display:flex;gap:8px;align-items:center">
            <div @click="admin.cancelRole()" style="height:34px;padding:0 13px;border-radius:8px;border:1px solid #dfe6ee;display:flex;align-items:center;font-size:12.5px;font-weight:500;color:#4a5866;cursor:pointer" v-pseudo:hover="'background:#f2f6fa'">Cancel</div>
            <div v-if="admin.can('users.edit')" @click="admin.saveRole()" style="height:34px;padding:0 15px;border-radius:8px;background:var(--c-primary);color:#ffffff;display:flex;align-items:center;font-size:12.5px;font-weight:600;cursor:pointer" v-pseudo:hover="'background:var(--c-primary-hover)'">Save role</div>
          </div>
        </div>
      </div>
      <div style="padding:20px 24px 32px">
        <div style="background:#ffffff;border:1px solid #dfe6ee;border-radius:12px;padding:17px 19px;margin-bottom:14px;display:grid;grid-template-columns:1.1fr 1.2fr;gap:20px">
          <div><label style="display:block;font-size:12px;font-weight:600;margin-bottom:5px">Role name</label> <div style="height:36px;border:1px solid #dfe6ee;border-radius:8px;padding:0 11px;display:flex;align-items:center;font-size:13px">{{ vm.presetName }}</div> <div style="font-size:11.5px;color:#7d8b9c;margin-top:7px;line-height:1.5">Start from a template, then adjust. Any change marks the role as custom.</div></div>
          <div><label style="display:block;font-size:12px;font-weight:600;margin-bottom:5px">Start from</label> <div style="display:flex;flex-wrap:wrap;gap:6px">
            <template v-for="(p, $index) in vm.presets" :key="$index">
              <div @click="p.onClick" :style="p.style">{{ p.name }}</div>
            </template>
          </div></div>
        </div>
        <div style="background:#ffffff;border:1px solid #dfe6ee;border-radius:12px;overflow:hidden">
          <div style="padding:14px 18px;border-bottom:1px solid #dfe6ee;display:flex;align-items:center;gap:12px;flex-wrap:wrap">
            <div style="max-width:520px">
              <div style="font-size:13px;font-weight:700">Permission grid</div>
              <div style="font-size:11.5px;color:#7d8b9c;margin-top:2px;line-height:1.5;text-wrap:pretty">One grid covers all 18 modules. Click any cell to toggle, or a column header to toggle the whole action.</div>
            </div>
            <div style="margin-left:auto;display:flex;gap:7px">
              <div @click="vm.grantAll" style="height:30px;padding:0 11px;border-radius:7px;border:1px solid #dfe6ee;display:flex;align-items:center;font-size:12px;font-weight:500;color:#4a5866;cursor:pointer" v-pseudo:hover="'background:#f2f6fa'">Grant all</div>
              <div @click="vm.revokeAll" style="height:30px;padding:0 11px;border-radius:7px;border:1px solid #dfe6ee;display:flex;align-items:center;font-size:12px;font-weight:500;color:#4a5866;cursor:pointer" v-pseudo:hover="'background:#f2f6fa'">Revoke all</div>
            </div>
          </div>
          <div style="display:grid;grid-template-columns:1.9fr repeat(5,84px);padding:0 18px;background:#f6f9fc;border-bottom:1px solid #dfe6ee;align-items:stretch">
            <div style="font-family:'IBM Plex Mono',monospace;font-size:9.5px;letter-spacing:0.07em;text-transform:uppercase;color:#5d6b7d;padding:11px 0;align-self:center">Module</div>
            <template v-for="(c, $index) in vm.cols" :key="$index">
              <div @click="c.onClick" style="text-align:center;padding:11px 0;cursor:pointer;font-family:'IBM Plex Mono',monospace;font-size:9.5px;letter-spacing:0.07em;text-transform:uppercase;color:var(--c-primary-text)" v-pseudo:hover="'background:var(--c-primary-soft)'">{{ c.name }}</div>
            </template>
          </div>
          <template v-for="(row, $index) in vm.grid" :key="$index">
            <div style="display:grid;grid-template-columns:1.9fr repeat(5,84px);padding:0 18px;border-bottom:1px solid #f0f4f8;align-items:stretch" v-pseudo:hover="'background:#f9fbfd'">
              <div style="display:flex;align-items:center;gap:10px;min-width:0;padding:9px 0">
                <div style="width:22px;height:22px;border-radius:6px;background:#eef2f6;display:grid;place-items:center;font-family:'IBM Plex Mono',monospace;font-size:9px;color:#7d8b9c;flex:none">{{ row.key }}</div>
                <div style="font-size:12.5px;font-weight:500;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">{{ row.name }}</div>
                <div :style="row.extraStyle">{{ row.extra }}</div>
              </div>
              <template v-for="(cell, $index) in row.cells" :key="$index">
                <div @click="cell.onClick" style="display:grid;place-items:center;cursor:pointer;padding:9px 0">
                  <div :style="cell.style">{{ cell.mark }}</div>
                </div>
              </template>
            </div>
          </template>
        </div>

        <!-- Permissions that are not a screen x action: ticked here, saved with the role -->
        <div style="background:#ffffff;border:1px solid #dfe6ee;border-radius:12px;padding:14px 18px;margin-top:14px">
          <div style="font-size:13px;font-weight:700">Special permissions · see everyone’s records, HRM approvals and payroll</div>
          <div style="font-size:11.5px;color:#7d8b9c;margin:2px 0 10px">Ticked: the role sees everyone’s records on that screen. Not ticked: only the records each user made.</div>
          <div v-for="x in admin.extraPermissions" :key="x.name" @click="admin.toggleExtra(vm.presetName, x.name)" style="display:flex;align-items:center;gap:10px;cursor:pointer;padding:6px 0">
            <div :style="'width:20px;height:20px;border-radius:5px;display:grid;place-items:center;font-size:12px;font-weight:700;flex:none;' + ((admin.extras[vm.presetName] || []).includes(x.name) ? 'background:var(--c-primary);color:#ffffff;' : 'box-shadow:inset 0 0 0 1.5px #c2cedb;color:transparent;')">✓</div>
            <div>
              <div style="font-size:12.5px;font-weight:600">{{ x.label }}</div>
              <div style="font-size:11.5px;color:#7d8b9c">{{ x.hint }}</div>
            </div>
          </div>
          <div style="font-size:11.5px;color:#8b98a8;margin-top:6px">Saved with the role's other permissions.</div>
        </div>
      </div>
    </div>
  </template>
</template>

<script setup lang="ts">
import { inject } from 'vue';
import { usePageVm } from '../../erp/useErpPage';

const vm = usePageVm();
const admin = inject('users') as any; // API actions, see UsersPage.vue
</script>
