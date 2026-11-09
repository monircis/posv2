<template>
  <template v-if="vm.uDetails">
    <div>
      <div style="padding:20px 24px 0;background:#ffffff;border-bottom:1px solid #dfe6ee">
        <div style="display:flex;align-items:center;gap:7px;font-size:11.5px;color:#7d8b9c;margin-bottom:14px"><span @click="vm.uGoList" style="cursor:pointer;color:var(--c-primary)">Users</span><span style="color:#c2cedb">/</span><span style="color:#0f1c2b;font-weight:500">{{ u?.name }}</span></div>
        <div style="display:flex;align-items:flex-start;gap:16px;flex-wrap:wrap">
          <div style="width:52px;height:52px;border-radius:12px;background:var(--c-sidebar);color:#ffffff;display:grid;place-items:center;font-size:18px;font-weight:600;flex:none">{{ initials }}</div>
          <div style="min-width:0">
            <div style="display:flex;align-items:center;gap:9px;flex-wrap:wrap;margin-bottom:4px"><h2 style="margin:0;font-size:22px;font-weight:700;letter-spacing:-0.018em">{{ u?.name }}</h2> <span style="background:var(--c-primary-soft);color:var(--c-primary-text);border-radius:5px;padding:3px 9px;font-size:11.5px;font-weight:600">{{ u?.role }}</span> <span :style="statusStyle">{{ statusLabel }}</span></div>
            <div style="font-size:12.5px;color:#5d6b7d">{{ u?.email }}{{ u?.phone ? ' · ' + u.phone : '' }} · {{ u?.branch || 'All branches' }}</div>
          </div>
          <div style="margin-left:auto;display:flex;gap:8px;align-items:center">
            <div v-if="u && admin.can('users.edit')" @click="admin.openForm(u)" style="height:34px;padding:0 13px;border-radius:8px;border:1px solid #dfe6ee;display:flex;align-items:center;font-size:12.5px;font-weight:500;color:#4a5866;cursor:pointer" v-pseudo:hover="'background:#f2f6fa'">Reset password</div>
            <div v-if="u && u.role !== 'Owner' && admin.can('users.delete')" @click="admin.deleteUser(u)" style="height:34px;padding:0 13px;border-radius:8px;border:1px solid #dfe6ee;display:flex;align-items:center;font-size:12.5px;font-weight:500;color:#b02020;cursor:pointer" v-pseudo:hover="'background:#fdf4f4'">Delete</div>
            <div v-if="u && u.role !== 'Owner' && admin.can('users.edit')" @click="admin.setStatus(u, u.status === 'off' ? 'active' : 'off')" style="height:34px;padding:0 13px;border-radius:8px;border:1px solid #f3cfcf;background:#fdf4f4;display:flex;align-items:center;font-size:12.5px;font-weight:600;color:#b02020;cursor:pointer" v-pseudo:hover="'background:#fdeaea'">{{ u.status === 'off' ? 'Reactivate' : 'Deactivate' }}</div>
            <div v-if="u && admin.can('users.edit')" @click="admin.openForm(u)" style="height:34px;padding:0 15px;border-radius:8px;background:var(--c-primary);color:#ffffff;display:flex;align-items:center;font-size:12.5px;font-weight:600;cursor:pointer" v-pseudo:hover="'background:var(--c-primary-hover)'">Edit user</div>
          </div>
        </div>
        <div style="display:flex;gap:2px;margin-top:16px">
          <template v-for="(t, $index) in vm.detailTabs" :key="$index">
            <div @click="t.onClick" :style="t.style">{{ t.name }}</div>
          </template>
        </div>
      </div>
      <div style="padding:20px 24px 32px;display:grid;grid-template-columns:1fr 320px;gap:16px;align-items:start">
        <div style="display:grid;gap:14px">
          <template v-if="vm.dtActivity">
            <div style="background:#ffffff;border:1px solid #dfe6ee;border-radius:12px;overflow:hidden">
              <div style="padding:14px 18px;border-bottom:1px solid #eaeff5;display:flex;align-items:center;gap:12px">
                <div style="font-size:13px;font-weight:700">Activity</div>
                <div style="margin-left:auto;height:30px;padding:0 11px;border-radius:7px;border:1px solid #dfe6ee;display:flex;align-items:center;font-family:'IBM Plex Mono',monospace;font-size:11px;color:#4a5866;cursor:pointer">Last 7 days</div>
              </div>
              <template v-for="(a, $index) in vm.activity" :key="$index">
                <div style="display:flex;gap:14px;padding:13px 18px;border-bottom:1px solid #f0f4f8;align-items:flex-start">
                  <div style="font-family:'IBM Plex Mono',monospace;font-size:11px;color:#9aa7b6;flex:none;width:88px;padding-top:2px">{{ a.when }}</div>
                  <div :style="a.dot"></div>
                  <div style="flex:1;min-width:0">
                    <div style="font-size:13px;line-height:1.45">{{ a.what }}</div>
                    <div style="font-size:11.5px;color:#8b98a8;margin-top:2px">{{ a.meta }}</div>
                  </div>
                </div>
              </template>
            </div>
          </template>
          <template v-if="vm.dtPerms">
            <div style="background:#ffffff;border:1px solid #dfe6ee;border-radius:12px;overflow:hidden">
              <div style="padding:14px 18px;border-bottom:1px solid #eaeff5;display:flex;align-items:center;gap:12px;flex-wrap:wrap">
                <div>
                  <div style="font-size:13px;font-weight:700">Effective permissions</div>
                  <div style="font-size:11.5px;color:#7d8b9c;margin-top:2px">Inherited from Manager, plus 2 overrides</div>
                </div>
                <div @click="vm.uGoMatrix" style="margin-left:auto;height:30px;padding:0 11px;border-radius:7px;border:1px solid #dfe6ee;display:flex;align-items:center;font-size:12px;font-weight:500;color:#4a5866;cursor:pointer" v-pseudo:hover="'background:#f2f6fa'">Edit role</div>
              </div>
              <template v-for="(e, $index) in vm.effective" :key="$index">
                <div style="display:grid;grid-template-columns:1.6fr 1fr auto;gap:14px;padding:10px 18px;border-bottom:1px solid #f0f4f8;align-items:center">
                  <div style="display:flex;align-items:center;gap:10px;min-width:0">
                    <div style="width:22px;height:22px;border-radius:6px;background:#eef2f6;display:grid;place-items:center;font-family:'IBM Plex Mono',monospace;font-size:9px;color:#7d8b9c;flex:none">{{ e.key }}</div>
                    <div style="font-size:12.5px;font-weight:500">{{ e.name }}</div>
                  </div>
                  <div style="font-size:12px;color:#5d6b7d">{{ e.actions }}</div>
                  <div><span :style="e.srcStyle">{{ e.src }}</span></div>
                </div>
              </template>
            </div>
          </template>
          <template v-if="vm.dtSessions">
            <div style="background:#ffffff;border:1px solid #dfe6ee;border-radius:12px;overflow:hidden">
              <div style="padding:14px 18px;border-bottom:1px solid #eaeff5;display:flex;align-items:center;gap:12px">
                <div style="font-size:13px;font-weight:700">Sessions</div>
                <div style="margin-left:auto;height:30px;padding:0 11px;border-radius:7px;border:1px solid #f3cfcf;background:#fdf4f4;display:flex;align-items:center;font-size:12px;font-weight:600;color:#b02020;cursor:pointer" v-pseudo:hover="'background:#fdeaea'">Sign out everywhere</div>
              </div>
              <template v-for="(s, $index) in vm.sessions" :key="$index">
                <div style="display:grid;grid-template-columns:1.5fr 1.2fr 1fr auto;gap:14px;padding:12px 18px;border-bottom:1px solid #f0f4f8;align-items:center">
                  <div>
                    <div style="font-size:12.5px;font-weight:500">{{ s.device }}</div>
                    <div style="font-size:11px;color:#8b98a8">{{ s.agent }}</div>
                  </div>
                  <div style="font-size:12px;color:#5d6b7d">{{ s.where }}</div>
                  <div style="font-family:'IBM Plex Mono',monospace;font-size:11.5px;color:#5d6b7d">{{ s.when }}</div>
                  <div><span :style="s.badge">{{ s.state }}</span></div>
                </div>
              </template>
            </div>
          </template>
        </div>
        <div style="display:grid;gap:12px">
          <div style="background:#ffffff;border:1px solid #dfe6ee;border-radius:12px;padding:16px 18px">
            <div style="font-size:12.5px;font-weight:700;margin-bottom:13px">Details</div>
            <div style="display:grid;gap:11px">
              <template v-for="(m, $index) in vm.meta" :key="$index">
                <div>
                  <div style="font-size:11px;color:#8b98a8;margin-bottom:2px">{{ m.k }}</div>
                  <div :style="m.vStyle">{{ m.v }}</div>
                </div>
              </template>
            </div>
          </div>
          <div style="background:#ffffff;border:1px solid #dfe6ee;border-radius:12px;padding:16px 18px">
            <div style="font-size:12.5px;font-weight:700;margin-bottom:12px">This month</div>
            <div style="display:grid;gap:12px">
              <template v-for="(st, $index) in vm.userStats" :key="$index">
                <div style="display:flex;align-items:baseline;gap:10px">
                  <div style="font-size:12.5px;color:#5d6b7d;flex:1">{{ st.k }}</div>
                  <div :style="st.vStyle">{{ st.v }}</div>
                </div>
              </template>
            </div>
          </div>
        </div>
      </div>
    </div>
  </template>
</template>

<script setup lang="ts">
import { computed, inject } from 'vue';
import { usePageVm } from '../../erp/useErpPage';

const vm = usePageVm();
const admin = inject('users') as any; // API actions, see UsersPage.vue

// The user picked on the list (the first user when the page is opened directly).
const u = computed(() => admin.selected || admin.users[0] || null);
const initials = computed(() => (u.value?.name || '').split(' ').map((p: string) => p[0]).join('').slice(0, 2).toUpperCase());
const statusLabel = computed(() => ({ active: 'Active', invited: 'Invited', off: 'Deactivated' } as Record<string, string>)[u.value?.status] || '');
const statusStyle = computed(() => 'border-radius:5px;padding:3px 9px;font-size:11.5px;font-weight:600;'
  + (u.value?.status === 'active' ? 'background:#e7f6ee;color:#0f6b43' : u.value?.status === 'invited' ? 'background:#fdf1dd;color:#8a5300' : 'background:#eef2f6;color:#5d6b7d'));
</script>
