<template>
  <template v-if="vm.pTaxonomy">
    <div>
      <div style="padding:20px 24px 16px;background:#ffffff;border-bottom:1px solid #dfe6ee">
        <div style="display:flex;align-items:center;gap:7px;font-size:11.5px;color:#7d8b9c;margin-bottom:8px"><span>Products</span><span style="color:#c2cedb">/</span><span style="color:#0f1c2b;font-weight:500">Categories, brands &amp; units</span></div>
        <h2 style="margin:0 0 3px;font-size:22px;font-weight:700;letter-spacing:-0.018em">Categories, brands &amp; units</h2>
        <div style="font-size:12.5px;color:#5d6b7d">Lists used by products. Categories can have sub-categories at any level.</div>
      </div>

      <div style="padding:20px 24px 32px">
        <div v-if="loading" style="font-size:12.5px;color:#7d8b9c">Loading…</div>

        <div v-else style="display:grid;grid-template-columns:1.25fr 1fr 1fr;gap:14px;align-items:start">
          <!-- One card per list -->
          <div v-for="card in cards" :key="card.list" style="background:#ffffff;border:1px solid #dfe6ee;border-radius:12px;overflow:hidden">
            <div style="padding:14px 18px;border-bottom:1px solid #eaeff5;display:flex;align-items:center;gap:10px">
              <div style="font-size:13px;font-weight:700">{{ card.title }}</div>
              <div style="font-family:'IBM Plex Mono',monospace;font-size:10.5px;background:#eef2f6;color:#7d8b9c;border-radius:4px;padding:2px 6px">{{ card.rows.length }}</div>
              <div v-if="can('products.create')" @click="openAdd(card.list)" style="margin-left:auto;height:28px;padding:0 10px;border-radius:7px;border:1px solid #dfe6ee;display:flex;align-items:center;font-size:12px;font-weight:500;color:#4a5866;cursor:pointer" v-pseudo:hover="'background:#f2f6fa'">+ Add</div>
            </div>

            <div v-if="!card.rows.length" style="padding:18px;font-size:12.5px;color:#8b98a8">Nothing yet.</div>

            <div v-for="r in card.rows" :key="r.id" :style="'display:flex;align-items:center;gap:11px;padding:10px 18px;border-bottom:1px solid #f0f4f8;' + (r.active ? '' : 'opacity:.55;')" v-pseudo:hover="'background:#f9fbfd'">
              <!-- Sub-categories are indented by their level -->
              <div :style="'flex:1;min-width:0;padding-left:' + (r.level || 0) * 16 + 'px'">
                <div style="font-size:12.5px;font-weight:500;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><span v-if="r.level" style="color:#c2cedb">└ </span>{{ r.name }}</div>
                <div v-if="r.sub" style="font-size:11px;color:#8b98a8">{{ r.sub }}</div>
              </div>
              <div style="font-family:'IBM Plex Mono',monospace;font-size:11.5px;color:#5d6b7d;flex:none" :title="r.count === '' ? '' : 'products'">{{ r.count }}</div>
              <div v-if="can('products.edit')" @click="openEdit(card.list, r.id)" title="Edit" style="width:24px;height:24px;border-radius:6px;display:grid;place-items:center;font-size:11px;color:#9aa7b6;cursor:pointer;flex:none" v-pseudo:hover="'background:#f2f6fa;color:var(--c-primary)'">✎</div>
            </div>
          </div>
        </div>
      </div>

      <!-- Add / edit form -->
      <template v-if="form.open">
        <div @click="closeForm" style="position:fixed;inset:0;background:rgba(9,22,35,.48);z-index:40"></div>
        <div style="position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);width:440px;max-width:calc(100vw - 32px);background:#ffffff;border-radius:16px;z-index:41;box-shadow:0 30px 80px -24px rgba(9,22,35,.65);padding:22px">
          <div style="font-size:17px;font-weight:700;margin-bottom:16px">{{ form.id ? 'Edit' : 'Add' }} {{ LABELS[form.list] }}</div>

          <div style="display:grid;gap:12px">
            <label style="display:grid;gap:5px;font-size:12px;font-weight:600">Name
              <input v-model="form.name" :style="inputStyle" placeholder="Name" />
            </label>

            <label v-if="form.list === 'categories'" style="display:grid;gap:5px;font-size:12px;font-weight:600">Parent category
              <select v-model="form.parent_id" :style="inputStyle">
                <option :value="null">— None (top category) —</option>
                <option v-for="c in parentChoices" :key="c.id" :value="c.id">{{ '  '.repeat(c.level) + c.name }}</option>
              </select>
            </label>

            <label v-if="form.list === 'units'" style="display:grid;gap:5px;font-size:12px;font-weight:600">Short name
              <input v-model="form.short_name" :style="inputStyle" placeholder="kg, ea, pack" />
            </label>

            <label v-if="form.list !== 'units'" style="display:grid;gap:5px;font-size:12px;font-weight:600">Description
              <input v-model="form.description" :style="inputStyle" placeholder="Optional" />
            </label>

            <label v-if="form.list === 'units'" style="display:flex;align-items:center;gap:8px;font-size:12.5px;cursor:pointer">
              <input type="checkbox" v-model="form.allow_decimals" /> Allow decimals (weight or volume, e.g. 1.25 kg)
            </label>

            <label style="display:flex;align-items:center;gap:8px;font-size:12.5px;cursor:pointer">
              <input type="checkbox" v-model="form.active" /> Active
            </label>
          </div>

          <div v-if="form.error" style="margin-top:12px;font-size:12px;color:#b02020;font-weight:600">{{ form.error }}</div>

          <div style="display:flex;gap:9px;margin-top:20px">
            <div v-if="form.id && can('products.delete')" @click="remove" style="height:38px;padding:0 14px;border-radius:9px;border:1px solid #f3cfcf;background:#fdf4f4;color:#b02020;display:flex;align-items:center;font-size:13px;font-weight:600;cursor:pointer">Delete</div>
            <div style="flex:1"></div>
            <div @click="closeForm" style="height:38px;padding:0 16px;border-radius:9px;border:1px solid #dfe6ee;display:flex;align-items:center;font-size:13px;font-weight:600;color:#4a5866;cursor:pointer">Cancel</div>
            <div @click="save" :style="'height:38px;padding:0 16px;border-radius:9px;background:var(--c-primary);color:#ffffff;display:flex;align-items:center;font-size:13px;font-weight:700;cursor:pointer;' + (form.saving ? 'opacity:.6;pointer-events:none;' : '')">{{ form.saving ? 'Saving…' : 'Save' }}</div>
          </div>
        </div>
      </template>
    </div>
  </template>
</template>

<script setup lang="ts">
// Products > Categories, brands & units.
// API (POS backend): GET/POST/PUT/DELETE /api/categories, /api/brands, /api/units
import { computed, onMounted, reactive, ref } from 'vue';
import { usePageVm } from '../../erp/useErpPage';
import { api } from '../../api/http.js';
import { can } from '../../api/auth.js';

const vm = usePageVm();

type Category = { id: number; name: string; parent_id: number | null; description: string | null; active: boolean; children_count: number; products_count: number };
type Brand = { id: number; name: string; description: string | null; active: boolean };
type Unit = { id: number; name: string; short_name: string; allow_decimals: boolean; active: boolean; products_count: number };
type ListName = 'categories' | 'brands' | 'units';

const LABELS: Record<ListName, string> = { categories: 'category', brands: 'brand', units: 'unit' };
const inputStyle = "width:100%;height:36px;border:1px solid #dfe6ee;border-radius:8px;padding:0 11px;font-family:'Public Sans',sans-serif;font-size:13px;font-weight:400;outline:none;background:#ffffff";

const categories = ref<Category[]>([]);
const brands = ref<Brand[]>([]);
const units = ref<Unit[]>([]);
const loading = ref(true);

async function load() {
  categories.value = await api.get('/categories');
  brands.value = await api.get('/brands');
  units.value = await api.get('/units');
}

onMounted(async () => {
  try { await load(); } catch (e) { /* the error bar shows why */ }
  loading.value = false;
});

// ---- Category tree ----

// Every category in tree order (parent, then its children), with its level: 0 = top.
function categoryTree(): (Category & { level: number })[] {
  const out: (Category & { level: number })[] = [];
  const addChildren = (parentId: number | null, level: number) => {
    for (const c of categories.value) {
      if (c.parent_id === parentId) {
        out.push({ ...c, level });
        addChildren(c.id, level + 1);
      }
    }
  };
  addChildren(null, 0);
  return out;
}

// The category and everything below it (it cannot be moved there).
function selfAndBelow(id: number): number[] {
  const ids = [id];
  // Add children of what we have, and repeat until nothing new is added (children of children ...).
  let before = 0;
  while (before !== ids.length) {
    before = ids.length;
    for (const c of categories.value) {
      if (c.parent_id !== null && ids.includes(c.parent_id) && !ids.includes(c.id)) ids.push(c.id);
    }
  }
  return ids;
}

// ---- The three cards ----

const cards = computed(() => [
  {
    list: 'categories' as ListName, title: 'Categories',
    rows: categoryTree().map(c => ({
      id: c.id, name: c.name, level: c.level, active: c.active, count: String(c.products_count),
      sub: [c.description, c.children_count ? c.children_count + ' sub-categor' + (c.children_count === 1 ? 'y' : 'ies') : ''].filter(Boolean).join(' · ')
    }))
  },
  {
    list: 'brands' as ListName, title: 'Brands',
    rows: brands.value.map(b => ({ id: b.id, name: b.name, level: 0, active: b.active, count: '', sub: b.description || '' }))
  },
  {
    list: 'units' as ListName, title: 'Units',
    rows: units.value.map(u => ({
      id: u.id, name: u.name + ' (' + u.short_name + ')', level: 0, active: u.active, count: String(u.products_count),
      sub: u.allow_decimals ? 'allows decimals' : 'whole numbers only'
    }))
  }
]);

// ---- Add / edit form ----

const form = reactive({
  open: false, list: 'categories' as ListName, id: 0, saving: false, error: '',
  name: '', parent_id: null as number | null, description: '', short_name: '', allow_decimals: false, active: true
});

// Parent choices: every category except the one being edited and those below it.
const parentChoices = computed(() => {
  const blocked = form.id ? selfAndBelow(form.id) : [];
  return categoryTree().filter(c => !blocked.includes(c.id));
});

function openAdd(list: ListName) {
  Object.assign(form, { open: true, list, id: 0, error: '', name: '', parent_id: null, description: '', short_name: '', allow_decimals: list === 'units', active: true });
}

function openEdit(list: ListName, id: number) {
  const source: any[] = list === 'categories' ? categories.value : list === 'brands' ? brands.value : units.value;
  const row = source.find(x => x.id === id);
  if (!row) return;
  Object.assign(form, {
    open: true, list, id, error: '',
    name: row.name, parent_id: row.parent_id ?? null, description: row.description || '',
    short_name: row.short_name || '', allow_decimals: !!row.allow_decimals, active: row.active
  });
}

function closeForm() {
  form.open = false;
}

// What the API expects for each list.
function body() {
  if (form.list === 'categories') return { name: form.name.trim(), parent_id: form.parent_id, description: form.description || null, active: form.active };
  if (form.list === 'brands') return { name: form.name.trim(), description: form.description || null, active: form.active };
  return { name: form.name.trim(), short_name: form.short_name.trim(), allow_decimals: form.allow_decimals, active: form.active };
}

async function save() {
  if (!form.name.trim()) { form.error = 'Enter a name.'; return; }
  form.saving = true;
  form.error = '';
  try {
    if (form.id) await api.put('/' + form.list + '/' + form.id, body());
    else await api.post('/' + form.list, body());
    await load();
    form.open = false;
  } catch (e: any) {
    form.error = e.message; // e.g. "The name has already been taken."
  }
  form.saving = false;
}

async function remove() {
  if (!confirm('Delete ' + form.name + '?')) return;
  form.error = '';
  try {
    await api.del('/' + form.list + '/' + form.id);
    await load();
    form.open = false;
  } catch (e: any) {
    form.error = e.message; // e.g. "Products still use Beef."
  }
}
</script>
