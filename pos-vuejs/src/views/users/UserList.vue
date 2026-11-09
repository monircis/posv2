<template>
  <template v-if="vm.uList">
    <div>
      <div style="padding:20px 24px 0;background:#ffffff;border-bottom:1px solid #dfe6ee">
        <div style="display:flex;align-items:center;gap:7px;font-size:11.5px;color:#7d8b9c;margin-bottom:8px"><span>User Management</span><span style="color:#c2cedb">/</span><span style="color:#0f1c2b;font-weight:500">Users</span></div>
        <div style="display:flex;align-items:flex-start;gap:20px;flex-wrap:wrap">
          <div>
            <h2 style="margin:0 0 3px;font-size:22px;font-weight:700;letter-spacing:-0.018em">Users</h2>
            <div style="font-size:12.5px;color:#5d6b7d">{{ admin.summary }}</div>
          </div>
          <div style="margin-left:auto;display:flex;gap:8px;align-items:center">
            <div @click="vm.uGoRoles" style="height:34px;padding:0 13px;border-radius:8px;border:1px solid #dfe6ee;display:flex;align-items:center;font-size:12.5px;font-weight:500;color:#4a5866;cursor:pointer" v-pseudo:hover="'background:#f2f6fa'">Manage roles</div>
            <div v-if="admin.can('users.create')" @click="admin.openForm()" style="height:34px;padding:0 15px;border-radius:8px;background:var(--c-primary);color:#ffffff;display:flex;align-items:center;font-size:12.5px;font-weight:600;cursor:pointer" v-pseudo:hover="'background:var(--c-primary-hover)'">+ Add user</div>
          </div>
        </div>
        <div style="display:flex;gap:2px;margin-top:16px">
          <template v-for="(t, $index) in vm.statusTabs" :key="$index">
            <div @click="t.onClick" :style="t.style">{{ t.name }}<span :style="t.countStyle">{{ t.count }}</span></div>
          </template>
        </div>
      </div>
      <div style="padding:20px 24px 32px">
        <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-bottom:14px">
          <div style="position:relative;width:250px"><div style="position:absolute;left:10px;top:50%;transform:translateY(-50%);color:#9aa7b6;font-size:12px">⚲</div> <input placeholder="Name, email or phone" style="width:100%;height:34px;border:1px solid #dfe6ee;border-radius:8px;background:#ffffff;padding:0 10px 0 29px;font-family:'Public Sans',sans-serif;font-size:12.5px;outline:none" v-pseudo:focus="'border-color:var(--c-primary)'" /></div>
          <div style="height:34px;padding:0 11px;border-radius:8px;border:1px solid #dfe6ee;background:#ffffff;display:flex;align-items:center;gap:20px;font-size:12.5px;color:#4a5866;cursor:pointer" v-pseudo:hover="'border-color:#c9d4e0'">Role<span style="color:#9aa7b6;font-size:10px">▾</span></div>
          <div style="height:34px;padding:0 11px;border-radius:8px;border:1px solid #dfe6ee;background:#ffffff;display:flex;align-items:center;gap:20px;font-size:12.5px;color:#4a5866;cursor:pointer" v-pseudo:hover="'border-color:#c9d4e0'">Location<span style="color:#9aa7b6;font-size:10px">▾</span></div>
          <div style="margin-left:auto;display:flex;align-items:center;gap:8px">
            <div style="height:34px;padding:0 12px;border-radius:8px;border:1px solid #dfe6ee;background:#ffffff;display:flex;align-items:center;font-size:12.5px;font-weight:500;color:#4a5866;cursor:pointer" v-pseudo:hover="'background:#f2f6fa'">Export</div>
            <div style="height:34px;padding:0 12px;border-radius:8px;border:1px solid #dfe6ee;background:#ffffff;display:flex;align-items:center;font-size:12.5px;font-weight:500;color:#4a5866;cursor:pointer" v-pseudo:hover="'background:#f2f6fa'">Columns</div>
          </div>
        </div>
        <div style="background:#ffffff;border:1px solid #dfe6ee;border-radius:12px;overflow:hidden">
          <div style="display:grid;grid-template-columns:36px 2.2fr 1.3fr 1fr 1.1fr 1fr 90px;gap:12px;padding:10px 16px;background:#f6f9fc;border-bottom:1px solid #dfe6ee;font-family:'IBM Plex Mono',monospace;font-size:9.5px;letter-spacing:0.07em;text-transform:uppercase;color:#5d6b7d;align-items:center">
            <div>
              <div style="width:15px;height:15px;border:1.5px solid #c2cedb;border-radius:4px"></div>
            </div>
            <div>User</div>
            <div>Role</div>
            <div>Locations</div>
            <div>Last active</div>
            <div>Status</div>
            <div></div>
          </div>
          <template v-for="(u, $index) in vm.users" :key="$index">
            <div style="display:grid;grid-template-columns:36px 2.2fr 1.3fr 1fr 1.1fr 1fr 90px;gap:12px;padding:11px 16px;border-bottom:1px solid #f0f4f8;align-items:center" v-pseudo:hover="'background:#f9fbfd'">
              <div>
                <div style="width:15px;height:15px;border:1.5px solid #c2cedb;border-radius:4px"></div>
              </div>
              <div style="display:flex;align-items:center;gap:11px;min-width:0">
                <div :style="u.avatar">{{ u.initials }}</div>
                <div style="min-width:0">
                  <div @click="admin.openDetails(u.email)" style="font-size:13px;font-weight:500;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;cursor:pointer;color:var(--c-primary)">{{ u.name }}</div>
                  <div style="font-size:11px;color:#8b98a8;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">{{ u.email }}</div>
                </div>
              </div>
              <div><span :style="u.roleBadge">{{ u.role }}</span></div>
              <div style="font-size:12.5px;color:#4a5866">{{ u.locations }}</div>
              <div style="font-family:'IBM Plex Mono',monospace;font-size:11.5px;color:#5d6b7d">{{ u.lastActive }}</div>
              <div><span :style="u.statusBadge">{{ u.status }}</span></div>
              <div style="display:flex;justify-content:flex-end;gap:5px">
                <div v-if="admin.can('users.edit')" @click="admin.editByEmail(u.email)" title="Edit user" style="width:26px;height:26px;border-radius:6px;border:1px solid #e4eaf1;display:grid;place-items:center;font-size:11px;color:#5d6b7d;cursor:pointer" v-pseudo:hover="'background:#f2f6fa;color:var(--c-primary)'">✎</div>
                <div @click="admin.openDetails(u.email)" title="Details" style="width:26px;height:26px;border-radius:6px;border:1px solid #e4eaf1;display:grid;place-items:center;font-size:13px;color:#5d6b7d;cursor:pointer" v-pseudo:hover="'background:#f2f6fa'">⋯</div>
              </div>
            </div>
          </template>
          <div style="display:flex;align-items:center;justify-content:space-between;gap:16px;padding:12px 16px;flex-wrap:wrap">
            <div style="font-size:12px;color:#7d8b9c">Showing {{ vm.userCount }} of {{ admin.users.length }}</div>
            <div style="display:flex;gap:4px;align-items:center">
              <div style="height:29px;padding:0 10px;border-radius:7px;border:1px solid #dfe6ee;display:flex;align-items:center;font-size:12px;color:#9aa7b6">Prev</div>
              <div style="min-width:29px;height:29px;border-radius:7px;background:var(--c-primary);color:#ffffff;display:grid;place-items:center;font-family:'IBM Plex Mono',monospace;font-size:12px;font-weight:600">1</div>
              <div style="height:29px;padding:0 10px;border-radius:7px;border:1px solid #dfe6ee;display:flex;align-items:center;font-size:12px;color:#9aa7b6">Next</div>
            </div>
          </div>
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
