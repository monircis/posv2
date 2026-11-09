<template>
  <div>
    <div style="padding:20px 24px 16px;background:#ffffff;border-bottom:1px solid #dfe6ee">
      <div style="display:flex;align-items:center;gap:7px;font-size:11.5px;color:#7d8b9c;margin-bottom:8px"><RouterLink :to="{ name: 'expenses' }" style="color:var(--c-primary)">Expenses</RouterLink><span style="color:#c2cedb">/</span><span style="color:#0f1c2b;font-weight:500">Categories</span></div>
      <h2 style="margin:0 0 3px;font-size:22px;font-weight:700;letter-spacing:-0.018em">Expense categories</h2>
      <div style="font-size:12.5px;color:#5d6b7d">{{ rows.length }} categories. Switch one off to stop offering it on the expense form; old expenses keep it.</div>
    </div>
    <div style="padding:20px 24px 32px;display:grid;grid-template-columns:minmax(0,1fr) 330px;gap:16px;align-items:start">
      <div style="background:#ffffff;border:1px solid #dfe6ee;border-radius:12px;overflow:hidden">
        <div style="display:grid;grid-template-columns:2fr 1fr 1fr 60px;gap:12px;padding:10px 16px;background:#f6f9fc;border-bottom:1px solid #dfe6ee;font-family:'IBM Plex Mono',monospace;font-size:9.5px;letter-spacing:0.07em;text-transform:uppercase;color:#5d6b7d">
          <div>Name</div>
          <div style="text-align:right">Expenses</div>
          <div>Status</div>
          <div></div>
        </div>
        <div v-for="c in rows" :key="c.id" style="display:grid;grid-template-columns:2fr 1fr 1fr 60px;gap:12px;padding:11px 16px;border-bottom:1px solid #f0f4f8;align-items:center">
          <div style="display:flex;align-items:center;gap:8px;min-width:0">
            <div :style="categoryDot(c.color)"></div>
            <div style="font-size:13px;font-weight:500;overflow-wrap:anywhere">{{ c.name }}</div>
          </div>
          <div style="font-family:'IBM Plex Mono',monospace;font-size:12.5px;text-align:right">{{ c.expenses_count }}</div>
          <div><span :style="statusStyle(c.active)">{{ c.active ? 'Active' : 'Off' }}</span></div>
          <div v-if="can('expenses.edit')" @click="startEdit(c)" title="Edit" style="width:24px;height:24px;border-radius:6px;display:grid;place-items:center;font-size:11px;color:#9aa7b6;cursor:pointer;justify-self:end" v-pseudo:hover="'background:#f2f6fa;color:var(--c-primary)'">✎</div>
        </div>
        <div v-if="!rows.length" style="padding:40px 20px;text-align:center;font-size:13px;color:#7d8b9c">{{ loading ? 'Loading…' : 'No categories yet.' }}</div>
      </div>
      <div v-if="canUseForm" style="background:#ffffff;border:1px solid #dfe6ee;border-radius:12px;padding:17px 19px;display:grid;gap:14px">
        <div style="font-size:13px;font-weight:700">{{ editingId ? 'Edit category' : 'Add category' }}</div>
        <div>
          <label style="display:block;font-size:12px;font-weight:600;margin-bottom:5px">Name <span style="color:#d93a3a">*</span></label>
          <input v-model="form.name" maxlength="80" placeholder="e.g. Insurance" style="width:100%;height:36px;border:1px solid #dfe6ee;border-radius:8px;padding:0 11px;font-family:'Public Sans',sans-serif;font-size:13px;outline:none" v-pseudo:focus="'border-color:var(--c-primary)'" />
        </div>
        <div>
          <div style="font-size:12px;font-weight:600;margin-bottom:7px">Colour</div>
          <div style="display:flex;gap:7px;flex-wrap:wrap">
            <div v-for="color in colors" :key="color" @click="form.color = color" :style="swatchStyle(color)"></div>
          </div>
        </div>
        <div v-if="editingId" style="display:flex;align-items:center;gap:8px">
          <div @click="form.active = !form.active" :style="'width:30px;height:18px;border-radius:99px;position:relative;cursor:pointer;flex:none;background:' + (form.active ? 'var(--c-primary)' : '#c2cedb')">
            <div :style="'position:absolute;top:2px;width:14px;height:14px;border-radius:50%;background:#ffffff;left:' + (form.active ? '14px' : '2px')"></div>
          </div>
          <div style="font-size:12px;color:#4a5866">{{ form.active ? 'Active' : 'Switched off' }}</div>
        </div>
        <div v-if="error" style="background:#fdeaea;color:#b02020;border-radius:9px;padding:10px 13px;font-size:12.5px">{{ error }}</div>
        <div style="display:flex;gap:8px;justify-content:flex-end">
          <div v-if="editingId" @click="startAdd" style="height:34px;padding:0 13px;border-radius:8px;border:1px solid #dfe6ee;display:flex;align-items:center;font-size:12.5px;font-weight:500;color:#4a5866;cursor:pointer" v-pseudo:hover="'background:#f2f6fa'">Cancel</div>
          <div @click="save" style="height:34px;padding:0 15px;border-radius:8px;background:var(--c-primary);color:#ffffff;display:flex;align-items:center;font-size:12.5px;font-weight:600;cursor:pointer" v-pseudo:hover="'background:var(--c-primary-hover)'">{{ saving ? 'Saving…' : editingId ? 'Save changes' : 'Add category' }}</div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
// Expense categories (/expenses/categories).
// API: GET and POST /api/expense-categories, PUT /api/expense-categories/{id}
import { computed, onMounted, reactive, ref } from 'vue';
import { api } from '../../api/http.js';
import { can } from '../../api/auth.js';
import { categoryDot } from '../../api/expenses.js';

const rows = ref<any[]>([]);
const colors = ref<string[]>([]);
const editingId = ref(0);
const form = reactive({ name: '', color: '', active: true });
const loading = ref(true);
const saving = ref(false);
const error = ref('');

const canUseForm = computed(() => (editingId.value ? can('expenses.edit') : can('expenses.create')));

async function load() {
  try {
    const res = await api.get('/expense-categories');
    rows.value = res.data;
    colors.value = res.colors;
    if (!form.color) form.color = res.colors[0];
  } catch (e) { /* the error bar shows why */ }
  loading.value = false;
}

onMounted(load);

function startAdd() {
  editingId.value = 0;
  error.value = '';
  Object.assign(form, { name: '', color: colors.value[0], active: true });
}

function startEdit(c: any) {
  editingId.value = c.id;
  error.value = '';
  Object.assign(form, { name: c.name, color: c.color, active: c.active });
}

async function save() {
  if (saving.value) return;
  error.value = '';
  if (!form.name.trim()) { error.value = 'Enter a name.'; return; }
  saving.value = true;
  const body = { name: form.name.trim(), color: form.color, active: form.active };
  try {
    if (editingId.value) await api.put('/expense-categories/' + editingId.value, body);
    else await api.post('/expense-categories', body);
    startAdd();
    await load();
  } catch (e: any) {
    error.value = e.message;
  }
  saving.value = false;
}

function statusStyle(active: boolean) {
  return 'border-radius:5px;padding:3px 8px;font-size:11.5px;font-weight:600;' + (active ? 'background:#e7f6ee;color:#0f6b43' : 'background:#eef2f6;color:#5d6b7d');
}
function swatchStyle(color: string) {
  return 'width:26px;height:26px;border-radius:50%;cursor:pointer;background:' + color + ';'
    + (form.color === color ? 'box-shadow:0 0 0 2px #ffffff,0 0 0 4px #0f1c2b;' : '');
}
</script>
