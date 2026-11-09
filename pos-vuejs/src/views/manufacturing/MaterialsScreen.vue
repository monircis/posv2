<template>
  <div style="height:100%;overflow-y:auto">
    <ManufacturingHeader crumb="Materials">
      <div v-if="can('mfg.create')" @click="openEdit()" :style="primaryButton" v-pseudo:hover="'background:var(--c-primary-hover)'">+ Add material</div>
    </ManufacturingHeader>
    <div style="padding:16px 24px 32px;display:grid;gap:14px">
      <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
        <select v-if="!userBranchId" v-model.number="branchId" @change="load" style="height:34px;border:1px solid #dfe6ee;border-radius:8px;background:#ffffff;padding:0 8px;font-family:'Public Sans',sans-serif;font-size:12.5px;color:#4a5866">
          <option :value="0">All locations</option>
          <option v-for="b in branches" :key="b.id" :value="b.id">{{ b.name }}</option>
        </select>
        <div style="font-size:12.5px;color:#5d6b7d">Stock value {{ money(stockValue) }}. Materials are separate from products: recipes use them, and starting an order takes them from its branch.</div>
      </div>
      <div style="background:#ffffff;border:1px solid #dfe6ee;border-radius:12px;overflow:hidden">
        <div :style="grid + 'padding:10px 16px;background:#f6f9fc;border-bottom:1px solid #dfe6ee;font-family:\'IBM Plex Mono\',monospace;font-size:9.5px;letter-spacing:0.07em;text-transform:uppercase;color:#5d6b7d'">
          <div>Material</div>
          <div>Unit</div>
          <div style="text-align:right">Unit cost</div>
          <div style="text-align:right">In stock</div>
          <div style="text-align:right">Value</div>
          <div></div>
        </div>
        <div v-for="m in rows" :key="m.id" :style="grid + 'padding:10px 16px;border-bottom:1px solid #f0f4f8'">
          <div style="font-size:13px;font-weight:500">{{ m.name }}</div>
          <div style="font-size:12.5px;color:#4a5866">{{ m.unit }}</div>
          <div style="font-family:'IBM Plex Mono',monospace;font-size:12.5px;text-align:right">{{ money(m.cost) }}</div>
          <div :style="'font-family:\'IBM Plex Mono\',monospace;font-size:12.5px;text-align:right;font-weight:600;' + (m.on_hand <= 0 ? 'color:#b02020' : '')">{{ qty(m.on_hand) }}</div>
          <div style="font-family:'IBM Plex Mono',monospace;font-size:12.5px;text-align:right;color:#4a5866">{{ money(m.on_hand * m.cost) }}</div>
          <div style="display:flex;gap:6px;justify-content:flex-end">
            <div v-if="can('mfg.edit')" @click="openAdjust(m)" style="height:28px;padding:0 10px;border-radius:7px;border:1px solid #dfe6ee;display:flex;align-items:center;font-size:12px;font-weight:600;color:var(--c-primary-text);cursor:pointer" v-pseudo:hover="'background:#f2f6fa'">Stock</div>
            <div v-if="can('mfg.edit')" @click="openEdit(m)" title="Edit" style="width:28px;height:28px;border-radius:6px;display:grid;place-items:center;font-size:11px;color:#9aa7b6;cursor:pointer" v-pseudo:hover="'background:#f2f6fa;color:var(--c-primary)'">✎</div>
          </div>
        </div>
        <div v-if="!rows.length" style="padding:40px 20px;text-align:center;font-size:13px;color:#7d8b9c">{{ loading ? 'Loading…' : 'No materials yet.' }}</div>
      </div>
    </div>

    <!-- Add or edit -->
    <template v-if="editOpen">
      <div @click="editOpen = false" style="position:fixed;inset:0;background:rgba(9,22,35,.48);z-index:30"></div>
      <div style="position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);width:400px;background:#ffffff;border-radius:16px;z-index:31;box-shadow:0 30px 80px -24px rgba(9,22,35,.65);padding:22px;display:grid;gap:12px">
        <div style="font-size:17px;font-weight:700">{{ editId ? 'Edit material' : 'Add material' }}</div>
        <div><label :style="labelStyle">Name</label><input v-model="edit.name" maxlength="120" :style="inputStyle" /></div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
          <div><label :style="labelStyle">Unit</label><input v-model="edit.unit" maxlength="20" placeholder="kg, ea, L" :style="inputStyle" /></div>
          <div><label :style="labelStyle">Unit cost</label><input v-model="edit.cost" :style="inputStyle + ';text-align:right'" /></div>
        </div>
        <div v-if="error" :style="errorBox">{{ error }}</div>
        <div style="display:flex;gap:9px;justify-content:flex-end;margin-top:6px">
          <div v-if="editId && can('mfg.delete')" @click="remove" style="height:34px;padding:0 13px;border-radius:8px;border:1px solid #f3cfcf;background:#fdf4f4;display:flex;align-items:center;font-size:12.5px;font-weight:600;color:#b02020;cursor:pointer;margin-right:auto">Delete</div>
          <div @click="editOpen = false" :style="ghostButton">Cancel</div>
          <div @click="saveEdit" :style="primaryButton">{{ saving ? 'Saving…' : 'Save' }}</div>
        </div>
      </div>
    </template>

    <!-- Stock in or out -->
    <template v-if="adjustFor">
      <div @click="adjustFor = null" style="position:fixed;inset:0;background:rgba(9,22,35,.48);z-index:30"></div>
      <div style="position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);width:400px;background:#ffffff;border-radius:16px;z-index:31;box-shadow:0 30px 80px -24px rgba(9,22,35,.65);padding:22px;display:grid;gap:12px">
        <div style="font-size:17px;font-weight:700">{{ adjustFor.name }}</div>
        <div style="display:flex;gap:3px;background:#eef2f6;padding:3px;border-radius:9px">
          <div @click="adjust.dir = 1" :style="dirStyle(1)">Stock in</div>
          <div @click="adjust.dir = -1" :style="dirStyle(-1)">Stock out</div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
          <div>
            <label :style="labelStyle">Location</label>
            <select v-model.number="adjust.branch_id" :disabled="!!userBranchId" :style="inputStyle + ';padding:0 8px'">
              <option v-for="b in activeBranches" :key="b.id" :value="b.id">{{ b.name }}</option>
            </select>
          </div>
          <div><label :style="labelStyle">Quantity ({{ adjustFor.unit }})</label><input v-model="adjust.qty" :style="inputStyle + ';text-align:right'" /></div>
        </div>
        <div><label :style="labelStyle">Note</label><input v-model="adjust.note" maxlength="190" placeholder="e.g. Delivery from supplier" :style="inputStyle" /></div>
        <div v-if="error" :style="errorBox">{{ error }}</div>
        <div style="display:flex;gap:9px;justify-content:flex-end;margin-top:6px">
          <div @click="adjustFor = null" :style="ghostButton">Cancel</div>
          <div @click="saveAdjust" :style="primaryButton">{{ saving ? 'Saving…' : 'Save' }}</div>
        </div>
      </div>
    </template>
  </div>
</template>

<script setup lang="ts">
// Materials (/manufacturing/materials).
// API: GET and POST /api/materials, PUT and DELETE /api/materials/{id}, POST /api/materials/{id}/adjust
import { computed, onMounted, reactive, ref } from 'vue';
import { api } from '../../api/http.js';
import { can, currentUser } from '../../api/auth.js';
import { branches, loadBranches } from '../../api/branches.js';
import { money, qty, userBranchId, inputStyle, labelStyle, ghostButton, primaryButton, errorBox } from '../../api/manufacturing.js';
import ManufacturingHeader from './ManufacturingHeader.vue';

const grid = 'display:grid;grid-template-columns:minmax(0,2fr) 0.6fr 0.9fr 1fr 1fr 110px;gap:12px;align-items:center;';

const rows = ref<any[]>([]);
const branchId = ref(0);
const loading = ref(true);
const saving = ref(false);
const error = ref('');

const editOpen = ref(false);
const editId = ref(0);
const edit = reactive({ name: '', unit: '', cost: '0' });

const adjustFor = ref<any>(null);
const adjust = reactive({ dir: 1, branch_id: 0, qty: '', note: '' });

const stockValue = computed(() => rows.value.reduce((sum, m) => sum + m.on_hand * m.cost, 0));
const activeBranches = computed(() => branches.value.filter(b => b.active || b.id === adjust.branch_id));

async function load() {
  loading.value = true;
  try {
    rows.value = await api.get('/materials' + (branchId.value ? '?branch_id=' + branchId.value : ''));
  } catch (e) { /* the error bar shows why */ }
  loading.value = false;
}

onMounted(() => {
  load();
  loadBranches().catch(() => {});
});

function openEdit(m?: any) {
  error.value = '';
  editId.value = m ? m.id : 0;
  Object.assign(edit, { name: m ? m.name : '', unit: m ? m.unit : '', cost: m ? String(m.cost) : '0' });
  editOpen.value = true;
}

async function saveEdit() {
  if (saving.value) return;
  error.value = '';
  if (!edit.name.trim() || !edit.unit.trim()) { error.value = 'Enter a name and a unit.'; return; }
  saving.value = true;
  const body = { name: edit.name.trim(), unit: edit.unit.trim(), cost: Number(edit.cost) || 0 };
  try {
    if (editId.value) await api.put('/materials/' + editId.value, body);
    else await api.post('/materials', body);
    editOpen.value = false;
    await load();
  } catch (e: any) {
    error.value = e.message;
  }
  saving.value = false;
}

async function remove() {
  if (!confirm('Delete ' + edit.name + '?')) return;
  error.value = '';
  try {
    await api.del('/materials/' + editId.value);
    editOpen.value = false;
    await load();
  } catch (e: any) {
    error.value = e.message;
  }
}

function openAdjust(m: any) {
  error.value = '';
  const first = activeBranches.value.length ? activeBranches.value[0].id : 0;
  Object.assign(adjust, { dir: 1, qty: '', note: '', branch_id: (currentUser.value && currentUser.value.branch_id) || branchId.value || first });
  adjustFor.value = m;
}

async function saveAdjust() {
  if (saving.value) return;
  error.value = '';
  const amount = Number(adjust.qty);
  if (!(amount > 0)) { error.value = 'Enter a quantity.'; return; }
  if (!adjust.branch_id) { error.value = 'Pick a location.'; return; }
  saving.value = true;
  try {
    await api.post('/materials/' + adjustFor.value.id + '/adjust', { branch_id: adjust.branch_id, change: adjust.dir * amount, note: adjust.note });
    adjustFor.value = null;
    await load();
  } catch (e: any) {
    error.value = e.message;
  }
  saving.value = false;
}

function dirStyle(dir: number) {
  return 'flex:1;height:32px;border-radius:7px;display:grid;place-items:center;font-size:12.5px;cursor:pointer;'
    + (adjust.dir === dir ? 'background:#ffffff;color:#0f1c2b;font-weight:700;box-shadow:0 1px 3px rgba(15,28,43,.14);' : 'color:#5d6b7d;font-weight:500;');
}
</script>
