<template>
  <template v-if="vm.puReturns">
    <div>
      <div style="padding:20px 24px 16px;background:#ffffff;border-bottom:1px solid #dfe6ee">
        <div style="display:flex;align-items:center;gap:7px;font-size:11.5px;color:#7d8b9c;margin-bottom:8px"><span @click="vm.puGoList" style="cursor:pointer;color:var(--c-primary)">Purchases</span><span style="color:#c2cedb">/</span><span style="color:#0f1c2b;font-weight:500">Returns</span></div>
        <div style="display:flex;align-items:flex-start;gap:20px;flex-wrap:wrap">
          <div>
            <h2 style="margin:0 0 3px;font-size:22px;font-weight:700;letter-spacing:-0.018em">Purchase returns</h2>
            <div style="font-size:12.5px;color:#5d6b7d">{{ total }} returns · {{ money(totalCredit) }} credited by suppliers</div>
          </div>
          <div style="margin-left:auto;display:flex;gap:8px;align-items:center">
            <select v-if="!userBranchId" v-model.number="branchId" @change="reload" style="height:34px;border:1px solid #dfe6ee;border-radius:8px;background:#ffffff;padding:0 8px;font-family:'Public Sans',sans-serif;font-size:12.5px;color:#4a5866">
              <option :value="0">All branches</option>
              <option v-for="b in branches" :key="b.id" :value="b.id">{{ b.name }}</option>
            </select>
            <div v-if="can('purchases.create')" @click="vm.puGoReturn" style="height:34px;padding:0 15px;border-radius:8px;background:var(--c-primary);color:#ffffff;display:flex;align-items:center;font-size:12.5px;font-weight:600;cursor:pointer" v-pseudo:hover="'background:var(--c-primary-hover)'">+ New return</div>
          </div>
        </div>
      </div>
      <div style="padding:20px 24px 32px">
        <div style="background:#ffffff;border:1px solid #dfe6ee;border-radius:12px;overflow:hidden">
          <div style="display:grid;grid-template-columns:1.1fr 1fr 1.5fr 1fr 1.4fr 1fr 1fr 1fr;gap:12px;padding:10px 16px;background:#f6f9fc;border-bottom:1px solid #dfe6ee;font-family:'IBM Plex Mono',monospace;font-size:9.5px;letter-spacing:0.07em;text-transform:uppercase;color:#5d6b7d">
            <div>Return</div>
            <div>Against</div>
            <div>Supplier</div>
            <div>Branch</div>
            <div>Reason</div>
            <div>Settled as</div>
            <div style="text-align:right">Credit</div>
            <div>Status</div>
          </div>
          <div v-if="!rows.length" style="padding:24px 16px;font-size:13px;color:#7d8b9c;text-align:center">{{ loading ? 'Loading…' : 'No returns yet.' }}</div>
          <div v-for="r in rows" :key="r.id" style="display:grid;grid-template-columns:1.1fr 1fr 1.5fr 1fr 1.4fr 1fr 1fr 1fr;gap:12px;padding:11px 16px;border-bottom:1px solid #f0f4f8;align-items:center" v-pseudo:hover="'background:#f9fbfd'">
            <div>
              <div style="font-family:'IBM Plex Mono',monospace;font-size:12.5px;font-weight:500">{{ r.ref }}</div>
              <div style="font-size:10.5px;color:#9aa7b6;margin-top:1px">{{ dmy(r.date) }}</div>
            </div>
            <div @click="openPurchase(r)" :style="'font-family:\'IBM Plex Mono\',monospace;font-size:12px;' + (r.purchase_id ? 'color:var(--c-primary);cursor:pointer' : 'color:#5d6b7d')">{{ r.purchase_ref }}</div>
            <div style="font-size:13px;font-weight:500">{{ r.supplier ? r.supplier.name : '' }}</div>
            <div style="font-size:12.5px;color:#4a5866">{{ r.branch ? r.branch.name : '' }}</div>
            <div style="font-size:12.5px;color:#4a5866">{{ reasons(r) }}</div>
            <div style="font-size:12.5px;color:#4a5866">{{ r.settlement }}</div>
            <div style="font-family:'IBM Plex Mono',monospace;font-size:12.5px;text-align:right;font-weight:600">{{ money(r.credit) }}</div>
            <div><span :style="badge(r.status)">{{ r.status }}</span></div>
          </div>
          <div v-if="lastPage > 1" style="display:flex;justify-content:flex-end;gap:4px;padding:10px 16px">
            <div @click="goPage(page - 1)" :style="pageStyle(page > 1)">Prev</div>
            <div @click="goPage(page + 1)" :style="pageStyle(page < lastPage)">Next</div>
          </div>
        </div>
      </div>
    </div>
  </template>
</template>

<script setup lang="ts">
// Purchases > Returns. API: GET /api/purchase-returns?branch_id=&page=
import { onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import { usePageVm } from '../../erp/useErpPage';
import { api } from '../../api/http.js';
import { can } from '../../api/auth.js';
import { branches, loadBranches } from '../../api/branches.js';
import { money, badge, dmy, userBranchId } from '../../api/purchases.js';

const vm = usePageVm();
const router = useRouter();

const rows = ref<any[]>([]);
const total = ref(0);
const totalCredit = ref(0);
const branchId = ref(0);
const page = ref(1);
const lastPage = ref(1);
const loading = ref(true);

async function load() {
  loading.value = true;
  const params = new URLSearchParams({ page: String(page.value) });
  if (branchId.value) params.set('branch_id', String(branchId.value));
  try {
    const res = await api.get('/purchase-returns?' + params.toString());
    rows.value = res.data;
    total.value = res.total;
    totalCredit.value = res.total_credit;
    lastPage.value = res.last_page;
  } catch (e) { /* the error bar shows why */ }
  loading.value = false;
}

onMounted(() => {
  load();
  loadBranches().catch(() => {});
});

function reload() { page.value = 1; load(); }
function goPage(n: number) { if (n < 1 || n > lastPage.value) return; page.value = n; load(); }

function openPurchase(r: any) {
  if (r.purchase_id) router.push({ name: 'purchases-details', query: { id: r.purchase_id } });
}

// The different reasons on the lines. Old returns have one reason in their notes.
function reasons(r: any) {
  const list: string[] = [];
  for (const item of r.items) {
    if (!list.includes(item.reason)) list.push(item.reason);
  }
  return list.length ? list.join(', ') : r.notes;
}

function pageStyle(on: boolean) {
  return 'height:26px;padding:0 8px;border-radius:6px;border:1px solid #dfe6ee;display:flex;align-items:center;font-size:11.5px;' + (on ? 'color:#4a5866;cursor:pointer' : 'color:#c2cedb');
}
</script>
