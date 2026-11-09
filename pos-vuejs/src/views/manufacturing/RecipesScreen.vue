<template>
  <div style="height:100%;overflow-y:auto">
    <ManufacturingHeader crumb="Recipes">
      <div v-if="can('mfg.create')" @click="newRecipe" :style="ghostButton" v-pseudo:hover="'background:#f2f6fa'">+ New recipe</div>
      <RouterLink v-if="can('mfg.create')" :to="{ name: 'mfg', query: { new: 1 } }" :style="primaryButton" v-pseudo:hover="'background:var(--c-primary-hover);color:#ffffff'">+ New production order</RouterLink>
    </ManufacturingHeader>
    <div style="padding:16px 24px 32px;display:grid;grid-template-columns:260px minmax(0,1fr);gap:14px;align-items:start">
      <div style="background:#ffffff;border:1px solid #dfe6ee;border-radius:12px;overflow:hidden">
        <div v-for="r in recipes" :key="r.id" @click="pick(r)" :style="listStyle(r.id)" v-pseudo:hover="'background:var(--c-primary-tint)'">
          <div style="font-size:13px;font-weight:600">{{ r.name }}</div>
          <div style="font-size:11px;color:#8b98a8;margin-top:1px">{{ r.items.length }} materials · yields {{ qty(r.output_qty, r.unit) }}</div>
        </div>
        <div v-if="!recipes.length" style="padding:20px;font-size:12.5px;color:#7d8b9c">{{ loading ? 'Loading…' : 'No recipes yet.' }}</div>
      </div>
      <div v-if="open" style="background:#ffffff;border:1px solid #dfe6ee;border-radius:12px;overflow:hidden">
        <div style="padding:14px 18px;display:flex;align-items:flex-start;gap:14px;border-bottom:1px solid #eaeff5">
          <div style="flex:1;display:grid;grid-template-columns:1fr 1fr;gap:12px">
            <div>
              <label :style="labelStyle">Recipe name</label>
              <input v-model="f.name" :disabled="!canSave" :style="inputStyle" />
            </div>
            <div>
              <label :style="labelStyle">Makes this product</label>
              <select v-model="f.product" @change="onProduct" :disabled="!canSave" :style="inputStyle + ';padding:0 8px;cursor:pointer'">
                <option value="">Not linked yet</option>
                <option v-for="p in products" :key="keyOf(p)" :value="keyOf(p)">{{ p.name }}</option>
              </select>
            </div>
          </div>
          <div style="text-align:right;min-width:110px">
            <div style="font-size:11px;color:#7d8b9c">Cost per {{ f.unit }}</div>
            <div style="font-family:'IBM Plex Mono',monospace;font-size:20px;font-weight:600">{{ money(unitCost) }}</div>
            <div :style="'font-size:11.5px;font-weight:600;color:' + (price && unitCost / price > 0.7 ? '#b02020' : '#0f6b43')">{{ price ? 'Margin ' + ((1 - unitCost / price) * 100).toFixed(1) + '%' : 'No price' }}</div>
          </div>
        </div>
        <div v-if="!f.product" style="padding:9px 18px;background:#fdf1dd;color:#8a5300;font-size:12.5px">Without a product, orders can start but cannot finish, because the output has nowhere to go in stock.</div>
        <div style="display:grid;grid-template-columns:minmax(0,2fr) 1fr 0.9fr 1fr 1fr 30px;gap:10px;padding:9px 18px;background:#f6f9fc;border-bottom:1px solid #dfe6ee;font-family:'IBM Plex Mono',monospace;font-size:9.5px;letter-spacing:0.07em;text-transform:uppercase;color:#5d6b7d">
          <div>Material</div>
          <div style="text-align:right">Per batch</div>
          <div>Unit</div>
          <div style="text-align:right">Unit cost</div>
          <div style="text-align:right">Line cost</div>
          <div></div>
        </div>
        <div v-for="(l, i) in f.items" :key="l.material_id" style="display:grid;grid-template-columns:minmax(0,2fr) 1fr 0.9fr 1fr 1fr 30px;gap:10px;padding:8px 18px;border-bottom:1px solid #f0f4f8;align-items:center">
          <div style="font-size:13px;font-weight:500">{{ material(l.material_id).name }}</div>
          <input v-model="l.qty" :disabled="!canSave" style="width:100%;height:30px;border:1px solid #dfe6ee;border-radius:7px;padding:0 8px;font-family:'IBM Plex Mono',monospace;font-size:12.5px;text-align:right;outline:none" />
          <div style="font-size:12px;color:#7d8b9c">{{ material(l.material_id).unit }}</div>
          <div style="font-family:'IBM Plex Mono',monospace;font-size:12.5px;text-align:right;color:#4a5866">{{ money(material(l.material_id).cost) }}</div>
          <div style="font-family:'IBM Plex Mono',monospace;font-size:12.5px;text-align:right">{{ money(material(l.material_id).cost * (Number(l.qty) || 0)) }}</div>
          <div v-if="canSave" @click="f.items.splice(i, 1)" style="width:26px;height:26px;border-radius:6px;display:grid;place-items:center;color:#b3c0cf;cursor:pointer;font-size:14px" v-pseudo:hover="'background:#fdeaea;color:#b02020'">×</div>
        </div>
        <div style="display:flex;align-items:center;gap:10px;padding:10px 18px;background:#fbfcfe;flex-wrap:wrap">
          <select v-if="canSave" value="" @change="addMaterial" style="height:32px;border:1px solid #dfe6ee;border-radius:7px;padding:0 8px;font-family:'Public Sans',sans-serif;font-size:12.5px;background:#ffffff;cursor:pointer">
            <option value="">+ Add material</option>
            <option v-for="m in unusedMaterials" :key="m.id" :value="m.id">{{ m.name }}</option>
          </select>
          <div style="margin-left:auto;display:flex;gap:16px;font-size:12.5px;align-items:center;flex-wrap:wrap">
            <div style="display:flex;align-items:center;gap:6px;color:#5d6b7d">Yields <input v-model="f.output_qty" :disabled="!canSave" style="width:64px;height:30px;border:1px solid #dfe6ee;border-radius:7px;padding:0 8px;font-family:'IBM Plex Mono',monospace;font-size:12.5px;text-align:right;outline:none" />
              <input v-if="!f.product" v-model="f.unit" :disabled="!canSave" placeholder="unit" style="width:52px;height:30px;border:1px solid #dfe6ee;border-radius:7px;padding:0 8px;font-size:12.5px;outline:none" />
              <template v-else>{{ f.unit }}</template>
            </div>
            <div v-if="!f.product" style="display:flex;align-items:center;gap:6px;color:#5d6b7d">Sells at <input v-model="f.price" :disabled="!canSave" style="width:64px;height:30px;border:1px solid #dfe6ee;border-radius:7px;padding:0 8px;font-family:'IBM Plex Mono',monospace;font-size:12.5px;text-align:right;outline:none" /></div>
            <div v-else style="color:#5d6b7d">Sells at <b style="font-family:'IBM Plex Mono',monospace;color:#0f1c2b">{{ money(price) }}</b></div>
            <div style="color:#5d6b7d">Batch cost <b style="font-family:'IBM Plex Mono',monospace;color:#0f1c2b">{{ money(batchCost) }}</b></div>
          </div>
        </div>
        <div v-if="error" :style="errorBox + ';margin:12px 18px 0'">{{ error }}</div>
        <div style="padding:12px 18px;display:flex;gap:8px;align-items:center">
          <div v-if="savedMessage" style="font-size:12.5px;font-weight:600;color:#0f6b43">{{ savedMessage }}</div>
          <div v-if="f.id && can('mfg.delete')" @click="remove" style="height:34px;padding:0 13px;border-radius:8px;border:1px solid #f3cfcf;background:#fdf4f4;display:flex;align-items:center;font-size:12.5px;font-weight:600;color:#b02020;cursor:pointer;margin-left:auto">Delete</div>
          <div v-if="canSave" @click="save" :style="primaryButton + (f.id && can('mfg.delete') ? '' : ';margin-left:auto')" v-pseudo:hover="'background:var(--c-primary-hover)'">{{ saving ? 'Saving…' : f.id ? 'Save recipe' : 'Add recipe' }}</div>
        </div>
      </div>
      <div v-else style="background:#ffffff;border:1px solid #dfe6ee;border-radius:12px;padding:40px 20px;text-align:center;font-size:13px;color:#7d8b9c">Pick a recipe, or add a new one.</div>
    </div>
  </div>
</template>

<script setup lang="ts">
// Recipes (/manufacturing/recipes).
// API: GET /api/recipes, GET /api/recipes/form-data, POST, PUT and DELETE /api/recipes/{id}
import { computed, onMounted, reactive, ref } from 'vue';
import { api } from '../../api/http.js';
import { can } from '../../api/auth.js';
import { money, qty, inputStyle, labelStyle, ghostButton, primaryButton, errorBox } from '../../api/manufacturing.js';
import ManufacturingHeader from './ManufacturingHeader.vue';

const recipes = ref<any[]>([]);
const materials = ref<any[]>([]);
const products = ref<any[]>([]);
const loading = ref(true);
const saving = ref(false);
const open = ref(false);
const error = ref('');
const savedMessage = ref('');

const f = reactive<any>({ id: 0, name: '', product: '', unit: 'kg', output_qty: '1', price: '0', sell_price: 0, items: [] });

const canSave = computed(() => (f.id ? can('mfg.edit') : can('mfg.create')));
const unusedMaterials = computed(() => materials.value.filter(m => !f.items.some((l: any) => l.material_id === m.id)));
const batchCost = computed(() => f.items.reduce((sum: number, l: any) => sum + material(l.material_id).cost * (Number(l.qty) || 0), 0));
const unitCost = computed(() => batchCost.value / (Number(f.output_qty) || 1));
const price = computed(() => (f.product ? f.sell_price : Number(f.price) || 0));

function keyOf(p: any) {
  return p.product_id + '-' + (p.variant_id || 0);
}
function material(id: number) {
  return materials.value.find(m => m.id === id) || { name: '', unit: '', cost: 0 };
}

async function load() {
  try {
    const [list, lists] = await Promise.all([api.get('/recipes'), api.get('/recipes/form-data')]);
    recipes.value = list;
    materials.value = lists.materials;
    products.value = lists.products;
    if (!open.value && list.length) pick(list[0]);
  } catch (e) { /* the error bar shows why */ }
  loading.value = false;
}

onMounted(load);

function pick(r: any) {
  error.value = '';
  savedMessage.value = '';
  open.value = true;
  Object.assign(f, {
    id: r.id, name: r.name, unit: r.unit, output_qty: String(r.output_qty), price: String(r.price), sell_price: r.sell_price,
    product: r.product_id ? r.product_id + '-' + (r.variant_id || 0) : '',
    items: r.items.map((i: any) => ({ material_id: i.material_id, qty: String(i.qty) }))
  });
}

function newRecipe() {
  error.value = '';
  savedMessage.value = '';
  open.value = true;
  Object.assign(f, { id: 0, name: '', product: '', unit: 'kg', output_qty: '1', price: '0', sell_price: 0, items: [] });
}

function onProduct() {
  const p = products.value.find(x => keyOf(x) === f.product);
  if (p) { f.unit = p.unit; f.sell_price = p.price ?? f.sell_price; }
}

function addMaterial(e: Event) {
  const select = e.target as HTMLSelectElement;
  if (select.value) f.items.push({ material_id: Number(select.value), qty: '1' });
  select.value = '';
}

async function save() {
  if (saving.value) return;
  error.value = '';
  savedMessage.value = '';
  if (!f.name.trim()) { error.value = 'Enter a recipe name.'; return; }
  if (!f.items.length) { error.value = 'Add at least one material.'; return; }
  if (f.items.some((l: any) => !(Number(l.qty) > 0))) { error.value = 'Every material needs a quantity.'; return; }
  if (!(Number(f.output_qty) > 0)) { error.value = 'Enter how much one batch yields.'; return; }

  const p = products.value.find(x => keyOf(x) === f.product);
  const body = {
    name: f.name.trim(), product_id: p ? p.product_id : null, variant_id: p ? p.variant_id : null,
    unit: f.unit, output_qty: Number(f.output_qty), price: Number(f.price) || 0,
    items: f.items.map((l: any) => ({ material_id: l.material_id, qty: Number(l.qty) }))
  };

  saving.value = true;
  try {
    const saved = f.id ? await api.put('/recipes/' + f.id, body) : await api.post('/recipes', body);
    recipes.value = await api.get('/recipes');
    pick(saved);
    savedMessage.value = 'Saved';
  } catch (e: any) {
    error.value = e.message;
  }
  saving.value = false;
}

async function remove() {
  if (!confirm('Delete ' + f.name + '?')) return;
  error.value = '';
  try {
    await api.del('/recipes/' + f.id);
    open.value = false;
    await load();
  } catch (e: any) {
    error.value = e.message;
  }
}

function listStyle(id: number) {
  return 'padding:11px 14px;border-bottom:1px solid #f0f4f8;cursor:pointer;' + (open.value && f.id === id ? 'background:var(--c-primary-soft);box-shadow:inset 3px 0 0 var(--c-primary);' : '');
}
</script>
