<template>
  <div>
    <RouterView />

    <!-- Add / edit product (opened from the list and from the details page) -->
    <template v-if="productForm.open">
      <div @click="close" style="position:absolute;inset:0;background:rgba(9,22,35,.42);z-index:20"></div>
      <div style="position:absolute;top:0;right:0;bottom:0;width:600px;max-width:100%;background:#ffffff;z-index:21;box-shadow:-18px 0 40px -20px rgba(15,28,43,.4);display:flex;flex-direction:column">
        <div style="flex:none;padding:18px 22px;border-bottom:1px solid #dfe6ee;display:flex;align-items:center;gap:14px">
          <div>
            <div style="font-size:16px;font-weight:700;letter-spacing:-0.015em">{{ productForm.id ? 'Edit product' : 'Add product' }}</div>
            <div style="font-size:12px;color:#5d6b7d;margin-top:2px">{{ productForm.id ? f.sku : 'Single products or products with variations' }}</div>
          </div>
          <div @click="close" style="margin-left:auto;width:30px;height:30px;border-radius:7px;border:1px solid #dfe6ee;display:grid;place-items:center;cursor:pointer;color:#5d6b7d;font-size:14px" v-pseudo:hover="'background:#f2f6fa'">×</div>
        </div>

        <div v-if="loading" style="flex:1;padding:22px;font-size:12.5px;color:#7d8b9c">Loading…</div>

        <div v-else style="flex:1;overflow-y:auto;padding:20px 22px">
          <!-- Basics -->
          <div :style="sectionTitle">Basics</div>
          <div style="display:grid;gap:14px;margin-bottom:26px">
            <div><label :style="labelStyle">Product name <span style="color:#d93a3a">*</span></label> <input v-model="f.name" placeholder="Beef Scotch Fillet" :style="inputStyle" /></div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
              <div><label :style="labelStyle">SKU</label> <input v-model="f.sku" :placeholder="skuPlaceholder" :style="inputStyle + mono" /></div>
              <div>
                <label :style="labelStyle">Unit <span style="color:#d93a3a">*</span></label>
                <select v-model="f.unit_id" :style="inputStyle">
                  <option v-for="u in lookups.units" :key="u.id" :value="u.id">{{ u.name }} ({{ u.short_name }})</option>
                </select>
              </div>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
              <div>
                <label :style="labelStyle">Category</label>
                <select v-model="f.category_id" :style="inputStyle">
                  <option :value="null">—</option>
                  <option v-for="c in categoryTree(lookups.categories)" :key="c.id" :value="c.id">{{ '  '.repeat(c.level) + c.name }}</option>
                </select>
              </div>
              <div>
                <label :style="labelStyle">Brand</label>
                <select v-model="f.brand_id" :style="inputStyle">
                  <option :value="null">—</option>
                  <option v-for="b in lookups.brands" :key="b.id" :value="b.id">{{ b.name }}</option>
                </select>
              </div>
            </div>
            <div style="display:grid;grid-template-columns:1.4fr 1fr;gap:14px">
              <div><label :style="labelStyle">Barcode</label> <input v-model="f.barcode" placeholder="Scan or type" :style="inputStyle + mono" /></div>
              <div>
                <label :style="labelStyle">Barcode type</label>
                <select v-model="f.barcode_type" :style="inputStyle">
                  <option v-for="b in ['EAN-13', 'Code 128', 'QR']" :key="b" :value="b">{{ b }}</option>
                </select>
              </div>
            </div>
            <div><label :style="labelStyle">Description</label> <input v-model="f.description" placeholder="e.g. diced, bone in" :style="inputStyle" /></div>

            <!-- Photo -->
            <div>
              <label :style="labelStyle">Product photo</label>
              <div style="display:flex;align-items:center;gap:12px">
                <img v-if="imagePreview" :src="imagePreview" alt="" style="width:72px;height:72px;border-radius:10px;object-fit:cover;border:1px solid #dfe6ee" />
                <div v-else style="width:72px;height:72px;border-radius:10px;border:1px dashed #cfdae6;display:grid;place-items:center;font-size:10px;color:#9aa7b6">No photo</div>
                <div @click="pickImage" style="height:32px;padding:0 12px;border-radius:8px;border:1px solid #dfe6ee;display:flex;align-items:center;font-size:12.5px;font-weight:600;color:var(--c-primary-text);cursor:pointer">{{ imagePreview ? 'Replace' : 'Choose photo' }}</div>
                <div v-if="imagePreview" @click="clearImage" style="height:32px;padding:0 12px;border-radius:8px;border:1px solid #f3cfcf;background:#fdf4f4;display:flex;align-items:center;font-size:12.5px;font-weight:600;color:#b02020;cursor:pointer">Remove</div>
              </div>
              <div style="font-size:11.5px;color:#7d8b9c;margin-top:5px">PNG, JPG or WebP, square looks best, up to 5 MB. Saved with the product.</div>
            </div>
          </div>

          <!-- Type -->
          <div :style="sectionTitle">Product type</div>
          <div style="display:flex;gap:7px;margin-bottom:9px">
            <div v-for="t in TYPES" :key="t.key" @click="pickType(t.key)" :style="'flex:1;height:38px;border-radius:9px;display:grid;place-items:center;font-size:13px;cursor:pointer;' + (f.type === t.key ? 'background:var(--c-primary);color:#ffffff;font-weight:700;' : 'border:1px solid #dfe6ee;color:#4a5866;font-weight:500;')">{{ t.name }}</div>
          </div>
          <div style="font-size:11.5px;color:#7d8b9c;line-height:1.5;margin-bottom:22px">{{ TYPES.find(t => t.key === f.type)?.hint }}</div>
          <div v-if="typeError" style="margin:-12px 0 22px;padding:9px 12px;border-radius:8px;background:#fdf4f4;border:1px solid #f3cfcf;font-size:12px;color:#b02020;line-height:1.5">{{ typeError }}</div>

          <!-- Tax -->
          <div :style="sectionTitle">Pricing</div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:12px">
            <div>
              <label :style="labelStyle">Tax</label>
              <select v-model="f.tax_rate_id" :style="inputStyle">
                <option :value="null">No tax</option>
                <option v-for="t in lookups.taxRates" :key="t.id" :value="t.id">{{ t.name }} {{ t.rate }}%</option>
              </select>
            </div>
            <div>
              <label :style="labelStyle">Price is</label>
              <select v-model="f.price_includes_tax" :style="inputStyle">
                <option :value="true">Tax inclusive</option>
                <option :value="false">Tax exclusive</option>
              </select>
            </div>
          </div>

          <!-- Single: cost, margin, price stay linked -->
          <template v-if="f.type === 'single'">
            <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px;margin-bottom:12px">
              <div><label :style="labelStyle">Cost ex. tax</label> <input :value="f.cost" @input="setCost" :style="inputStyle + mono + 'text-align:right;'" /></div>
              <div><label :style="labelStyle">Margin %</label> <input :value="margin" @input="setMargin" :style="inputStyle + mono + 'text-align:right;'" /></div>
              <div><label :style="labelStyle">Price {{ f.price_includes_tax ? 'inc.' : 'ex.' }} tax</label> <input :value="f.price" @input="setPrice" :style="inputStyle + mono + 'text-align:right;font-weight:600;background:var(--c-primary-tint);border-color:#cfe0f5;'" /></div>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:12px">
              <div><label :style="labelStyle">Wholesale price ex. tax</label> <input v-model="f.wholesale_price" placeholder="Optional" :style="inputStyle + mono + 'text-align:right;'" /></div>
            </div>
            <div style="font-size:11.5px;color:#7d8b9c;line-height:1.5;margin-bottom:26px">Edit any of cost, margin or price and the others follow. Margin = how much the price ex. tax is above the cost. The wholesale price is used for customers in the "Wholesale ex-GST" price group.</div>
          </template>

          <!-- Variable: one row per variation -->
          <template v-else>
            <div style="border:1px solid #dfe6ee;border-radius:10px;overflow:hidden;margin-bottom:26px">
              <div style="display:grid;grid-template-columns:1.4fr 0.9fr 0.8fr 0.8fr 0.8fr 28px;gap:8px;padding:9px 12px;background:#f6f9fc;border-bottom:1px solid #dfe6ee;font-family:'IBM Plex Mono',monospace;font-size:9px;letter-spacing:0.06em;text-transform:uppercase;color:#5d6b7d">
                <div>Variation</div>
                <div>SKU</div>
                <div style="text-align:right">Cost</div>
                <div style="text-align:right">Price</div>
                <div style="text-align:right">Stock</div>
                <div></div>
              </div>
              <div v-for="(v, i) in f.variants" :key="i" style="display:grid;grid-template-columns:1.4fr 0.9fr 0.8fr 0.8fr 0.8fr 28px;gap:8px;padding:8px 12px;border-bottom:1px solid #f0f4f8;align-items:center">
                <input v-model="v.name" placeholder="Portioned 250g" :style="cellStyle" />
                <input v-model="v.sku" placeholder="Auto" :style="cellStyle + mono" />
                <input v-model="v.cost" :style="cellStyle + mono + 'text-align:right;'" />
                <input v-model="v.price" :style="cellStyle + mono + 'text-align:right;'" />
                <div style="font-family:'IBM Plex Mono',monospace;font-size:12px;text-align:right;color:#5d6b7d">{{ v.id ? v.stock_qty : '—' }}</div>
                <div @click="f.variants.splice(i, 1)" title="Remove" style="display:grid;place-items:center;color:#b3c0cf;cursor:pointer;font-size:14px" v-pseudo:hover="'color:#b02020'">×</div>
              </div>
              <div @click="addVariant" style="padding:9px 13px;font-size:12px;font-weight:500;color:var(--c-primary);cursor:pointer" v-pseudo:hover="'background:var(--c-primary-tint)'">+ Add variation</div>
            </div>
            <div style="font-size:11.5px;color:#7d8b9c;margin:-18px 0 26px">Opening stock of new variations is entered per branch under "Sold at branches".</div>
          </template>

          <!-- Stock -->
          <div :style="sectionTitle">Stock</div>
          <div style="display:grid;gap:9px;margin-bottom:14px">
            <div v-for="t in stockToggles" :key="t.key" @click="f[t.key] = !f[t.key]" style="display:flex;align-items:center;gap:12px;padding:11px 13px;border:1px solid #dfe6ee;border-radius:9px;cursor:pointer" v-pseudo:hover="'background:#f9fbfd'">
              <div :style="track(f[t.key])"><div :style="knob(f[t.key])"></div></div>
              <div style="flex:1;min-width:0">
                <div style="font-size:12.5px;font-weight:500">{{ t.name }}</div>
                <div style="font-size:11.5px;color:#7d8b9c;margin-top:1px">{{ t.desc }}</div>
              </div>
            </div>
          </div>
          <div v-if="f.manage_stock" style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px;margin-bottom:26px">
            <div><label :style="labelStyle">Alert quantity</label> <input v-model="f.alert_qty" :style="inputStyle + mono + 'text-align:right;'" /></div>
          </div>

          <!-- Branches: where the product is sold, with opening stock per branch -->
          <div :style="sectionTitle">Sold at branches</div>
          <div style="border:1px solid #dfe6ee;border-radius:10px;overflow:hidden;margin-bottom:8px">
            <div @click="toggleAllBranches" style="display:flex;align-items:center;gap:10px;padding:10px 13px;background:#f6f9fc;border-bottom:1px solid #dfe6ee;cursor:pointer">
              <input type="checkbox" :checked="allBranches" style="pointer-events:none;accent-color:var(--c-primary)" />
              <div style="font-size:12.5px;font-weight:600">All branches</div>
              <div v-if="showSingleOpening" style="margin-left:auto;font-family:'IBM Plex Mono',monospace;font-size:9px;letter-spacing:0.06em;text-transform:uppercase;color:#5d6b7d">Opening stock</div>
              <div v-else-if="productForm.id && f.manage_stock" style="margin-left:auto;font-family:'IBM Plex Mono',monospace;font-size:9px;letter-spacing:0.06em;text-transform:uppercase;color:#5d6b7d">Stock now</div>
            </div>
            <div v-for="b in branches" :key="b.id" style="padding:9px 13px;border-bottom:1px solid #f0f4f8">
              <div style="display:flex;align-items:center;gap:10px;min-height:30px">
                <div @click="toggleBranch(b.id)" style="flex:1;min-width:0;display:flex;align-items:center;gap:10px;cursor:pointer">
                  <input type="checkbox" :checked="f.branch_ids.includes(b.id)" style="pointer-events:none;accent-color:var(--c-primary)" />
                  <div style="font-size:12.5px;font-weight:500">{{ b.name }}</div>
                  <div style="font-family:'IBM Plex Mono',monospace;font-size:11px;color:#8b98a8">{{ b.code }}</div>
                  <div v-if="!b.active" style="font-size:11px;color:#8a5300">inactive</div>
                </div>
                <template v-if="f.branch_ids.includes(b.id)">
                  <input v-if="showSingleOpening" v-model="f.opening_stock[b.id]" placeholder="0" :style="cellStyle + mono + 'width:90px;text-align:right;'" />
                  <div v-else-if="productForm.id && f.manage_stock" style="font-family:'IBM Plex Mono',monospace;font-size:12px;color:#5d6b7d">{{ f.stock_at[b.id] || 0 }}</div>
                </template>
                <div v-else style="font-size:11.5px;color:#9aa7b6">Not sold here</div>
              </div>
              <!-- Variable product: opening stock of each new variation at this branch -->
              <div v-if="showVariantOpening && f.branch_ids.includes(b.id)" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:6px 12px;margin:8px 0 2px 26px">
                <div v-for="(v, i) in newVariants" :key="i" style="display:flex;align-items:center;gap:6px">
                  <div style="flex:1;min-width:0;font-size:11.5px;color:#5d6b7d;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ v.name || 'New variation' }}</div>
                  <input v-model="v.opening_stock[b.id]" placeholder="0" :style="cellStyle + mono + 'width:70px;flex:none;text-align:right;'" />
                </div>
              </div>
            </div>
          </div>
          <div style="font-size:11.5px;color:#7d8b9c;line-height:1.5;margin-bottom:26px">
            Only ticked branches sell this product (POS, purchases, transfers). A branch that still has stock of it cannot be unticked.
            <span v-if="productForm.id && f.manage_stock"> Change stock with "Adjust stock" on the product page.</span>
          </div>

          <!-- POS & kitchen -->
          <div :style="sectionTitle">POS &amp; kitchen</div>
          <div style="display:grid;gap:9px">
            <div v-for="t in posToggles" :key="t.key" @click="f[t.key] = !f[t.key]" style="display:flex;align-items:center;gap:12px;padding:11px 13px;border:1px solid #dfe6ee;border-radius:9px;cursor:pointer" v-pseudo:hover="'background:#f9fbfd'">
              <div :style="track(f[t.key])"><div :style="knob(f[t.key])"></div></div>
              <div style="flex:1;min-width:0">
                <div style="font-size:12.5px;font-weight:500">{{ t.name }}</div>
                <div style="font-size:11.5px;color:#7d8b9c;margin-top:1px">{{ t.desc }}</div>
              </div>
            </div>
            <div v-if="f.send_to_kitchen" style="display:grid;grid-template-columns:1fr 2fr;gap:14px;align-items:end">
              <div><label :style="labelStyle">Prep time (minutes)</label> <input v-model="f.prep_minutes" placeholder="e.g. 12" :style="inputStyle + mono + 'text-align:right;'" /></div>
            </div>
          </div>

          <div v-if="error" style="margin-top:16px;font-size:12.5px;color:#b02020;font-weight:600">{{ error }}</div>
        </div>

        <div style="flex:none;padding:14px 22px;border-top:1px solid #dfe6ee;display:flex;align-items:center;gap:10px">
          <div style="flex:1"></div>
          <div @click="close" style="height:36px;padding:0 15px;border-radius:8px;border:1px solid #dfe6ee;display:flex;align-items:center;font-size:13px;font-weight:500;color:#4a5866;cursor:pointer" v-pseudo:hover="'background:#f2f6fa'">Cancel</div>
          <div v-if="!productForm.id" @click="save(true)" style="height:36px;padding:0 15px;border-radius:8px;border:1px solid #dfe6ee;display:flex;align-items:center;font-size:13px;font-weight:600;color:var(--c-primary-text);cursor:pointer" v-pseudo:hover="'background:#f2f6fa'">Save &amp; add another</div>
          <div @click="save(false)" :style="'height:36px;padding:0 17px;border-radius:8px;background:var(--c-primary);color:#ffffff;display:flex;align-items:center;font-size:13px;font-weight:600;cursor:pointer;' + (saving ? 'opacity:.6;pointer-events:none;' : '')">{{ saving ? 'Saving…' : 'Save product' }}</div>
        </div>
      </div>
    </template>
  </div>
</template>

<script setup lang="ts">
// Products. The child routes are the list, details and categories.
// The add / edit product form lives here.
// API: POST /api/products, PUT /api/products/{id}, POST/DELETE /api/products/{id}/image
import { computed, reactive, ref, watch } from 'vue';
import { useErpPage } from '../../erp/useErpPage';
import { api } from '../../api/http.js';
import { branches } from '../../api/branches.js';
import { productForm, productsVersion, lookups, loadLookups, categoryTree, priceExTax, marginPercent } from '../../api/products.js';

useErpPage('products', 'productNav');

const TYPES = [
  { key: 'single', name: 'Single', hint: 'One SKU, one price, one stock figure. Covers most of the catalogue.' },
  { key: 'variable', name: 'Variable', hint: 'Several variations under one product — cut, weight, marinade. Each row has its own SKU, price and stock.' }
];
const stockToggles = [
  { key: 'manage_stock', name: 'Manage stock', desc: 'Track quantity per branch and warn at the alert quantity' },
  { key: 'allow_negative_stock', name: 'Allow negative stock', desc: 'Sell past zero; use for made-to-order items' }
];
const posToggles = [
  { key: 'available_at_pos', name: 'Available at POS', desc: 'Appears in the terminal product grid' },
  { key: 'pos_favourite', name: 'POS favourite', desc: 'Shown first on the POS screen' },
  { key: 'send_to_kitchen', name: 'Send to kitchen', desc: 'Sales of this product print a kitchen ticket' },
  { key: 'active', name: 'Active', desc: 'Inactive products are hidden from sales and purchases' }
];

const sectionTitle = "font-family:'IBM Plex Mono',monospace;font-size:9.5px;letter-spacing:0.09em;text-transform:uppercase;color:#7d8b9c;margin-bottom:12px";
const labelStyle = 'display:block;font-size:12px;font-weight:600;margin-bottom:5px';
const inputStyle = "width:100%;height:36px;border:1px solid #dfe6ee;border-radius:8px;padding:0 11px;font-family:'Public Sans',sans-serif;font-size:13px;outline:none;background:#ffffff;";
const cellStyle = "width:100%;min-width:0;height:32px;border:1px solid #dfe6ee;border-radius:7px;padding:0 8px;font-family:'Public Sans',sans-serif;font-size:12.5px;outline:none;";
const mono = "font-family:'IBM Plex Mono',monospace;font-size:12.5px;";
const track = (on: boolean) => 'width:34px;height:20px;border-radius:99px;position:relative;flex:none;background:' + (on ? 'var(--c-primary)' : '#c2cedb');
const knob = (on: boolean) => 'position:absolute;top:3px;width:14px;height:14px;border-radius:50%;background:#ffffff;left:' + (on ? '17px' : '3px');

// opening_stock: branch id => quantity, only for new variations.
type Variant = { id?: number; name: string; sku: string; cost: string; price: string; opening_stock: Record<number, string>; stock_qty?: number };

// The form fields (the same names as the API).
function empty() {
  const d = lookups.defaults || {};
  return {
    name: '', sku: '', type: 'single', unit_id: d.unit_id ?? null, category_id: null as number | null, brand_id: null as number | null,
    barcode: '', barcode_type: d.barcode_type || 'EAN-13', description: '',
    cost: '0', price: '0', wholesale_price: '', tax_rate_id: d.tax_rate_id ?? null, price_includes_tax: d.price_includes_tax ?? true,
    manage_stock: true, alert_qty: String(d.alert_qty ?? 0), allow_negative_stock: false,
    branch_ids: branches.value.map(b => b.id) as number[], // new products: every branch
    opening_stock: {} as Record<number, string>,           // branch id => quantity (new single products)
    stock_at: {} as Record<number, number>,                // branch id => stock now (edit only, to show)
    available_at_pos: true, pos_favourite: false, send_to_kitchen: false, prep_minutes: '', active: true,
    variants: [] as Variant[]
  };
}

const f = reactive<any>(empty());
const error = ref('');
const saving = ref(false);
const loading = ref(false);

// Photo: a new file waits here until the product is saved.
const imageFile = ref<File | null>(null);
const imagePreview = ref('');
const removeImage = ref(false);

const skuPlaceholder = computed(() => (lookups.defaults?.auto_sku ? 'Auto: ' + lookups.defaults.next_sku : 'Required'));

// ---- Linked cost / margin / price (single products) ----

const taxRate = computed(() => lookups.taxRates.find((t: any) => t.id === f.tax_rate_id)?.rate || 0);
const margin = ref('');

function showMargin() {
  const m = marginPercent(f.cost, priceExTax(f.price, f.price_includes_tax, taxRate.value));
  margin.value = m === null ? '' : m.toFixed(1);
}

// Price from cost and margin: cost x (1 + margin%), plus tax when the price includes tax.
function priceFromMargin() {
  const ex = (Number(f.cost) || 0) * (1 + (Number(margin.value) || 0) / 100);
  const price = f.price_includes_tax ? ex * (1 + taxRate.value / 100) : ex;
  f.price = price.toFixed(2);
}

function setCost(e: Event) { f.cost = (e.target as HTMLInputElement).value; priceFromMargin(); }
function setMargin(e: Event) { margin.value = (e.target as HTMLInputElement).value; priceFromMargin(); }
function setPrice(e: Event) { f.price = (e.target as HTMLInputElement).value; showMargin(); }
watch(() => [f.tax_rate_id, f.price_includes_tax], showMargin);

// ---- Open / close ----

watch(() => productForm.open, async open => {
  if (!open) return;
  error.value = '';
  typeError.value = '';
  imageFile.value = null;
  removeImage.value = false;
  loading.value = true;
  try {
    await loadLookups(true); // fresh next SKU and lists
    Object.assign(f, empty());
    imagePreview.value = '';
    if (productForm.id) {
      const p = await api.get('/products/' + productForm.id);
      Object.assign(f, {
        name: p.name, sku: p.sku, type: p.type, unit_id: p.unit_id, category_id: p.category_id, brand_id: p.brand_id,
        barcode: p.barcode || '', barcode_type: p.barcode_type, description: p.description || '',
        cost: String(p.cost), price: String(p.price), wholesale_price: p.wholesale_price === null ? '' : String(p.wholesale_price),
        tax_rate_id: p.tax_rate_id, price_includes_tax: p.price_includes_tax,
        manage_stock: p.manage_stock, alert_qty: String(p.alert_qty), allow_negative_stock: p.allow_negative_stock,
        available_at_pos: p.available_at_pos, pos_favourite: p.pos_favourite, send_to_kitchen: p.send_to_kitchen,
        prep_minutes: p.prep_minutes === null ? '' : String(p.prep_minutes), active: p.active,
        variants: p.variants.map((v: any) => ({ id: v.id, name: v.name, sku: v.sku, cost: String(v.cost), price: String(v.price), opening_stock: {}, stock_qty: v.stock_qty })),
        branch_ids: p.branch_ids,
        stock_at: {}
      });
      for (const s of p.stocks) f.stock_at[s.branch_id] = (f.stock_at[s.branch_id] || 0) + s.qty;
      imagePreview.value = p.image_url || '';
    }
    showMargin();
  } catch (e: any) {
    error.value = e.message;
  }
  loading.value = false;
});

watch(() => f.type, type => {
  if (type === 'variable' && !f.variants.length) addVariant();
});

function close() {
  productForm.open = false;
}

// A saved product that still has stock cannot change between single and variable.
const typeError = ref('');
function pickType(type: string) {
  typeError.value = '';
  if (type === f.type) return;
  const stock = Object.values(f.stock_at).reduce((a: number, q: any) => a + Number(q), 0);
  if (productForm.id && stock !== 0) {
    typeError.value = 'This product still has ' + stock + ' in stock, so it cannot change to ' + type + '. ' +
      'Set its stock to 0 first (Adjust stock on the product page), or add a new product with variations instead.';
    return;
  }
  f.type = type;
}

function addVariant() {
  f.variants.push({ name: '', sku: '', cost: f.cost || '0', price: f.price || '0', opening_stock: {} });
}

// ---- Branches ----

const allBranches = computed(() => branches.value.length > 0 && f.branch_ids.length === branches.value.length);
const newVariants = computed(() => f.variants.filter((v: Variant) => !v.id));
const showSingleOpening = computed(() => f.manage_stock && f.type === 'single' && !productForm.id);
const showVariantOpening = computed(() => f.manage_stock && f.type === 'variable' && newVariants.value.length > 0);

function toggleBranch(id: number) {
  if (!f.branch_ids.includes(id)) {
    f.branch_ids.push(id);
    return;
  }
  if (f.stock_at[id]) {
    const name = branches.value.find(b => b.id === id)?.name;
    error.value = name + ' still has ' + f.stock_at[id] + ' in stock. Adjust it to 0 there first.';
    return;
  }
  f.branch_ids = f.branch_ids.filter((b: number) => b !== id);
}

function toggleAllBranches() {
  if (!allBranches.value) {
    f.branch_ids = branches.value.map(b => b.id);
    return;
  }
  // Untick all, but keep branches that still have stock.
  f.branch_ids = f.branch_ids.filter((id: number) => f.stock_at[id]);
}

function pickImage() {
  const input = document.createElement('input');
  input.type = 'file';
  input.accept = 'image/png,image/jpeg,image/webp';
  input.onchange = () => {
    const file = input.files?.[0];
    if (!file) return;
    if (file.size > 5 * 1024 * 1024) { error.value = 'The photo must be 5 MB or smaller.'; return; }
    imageFile.value = file;
    imagePreview.value = URL.createObjectURL(file);
    removeImage.value = false;
  };
  input.click();
}

function clearImage() {
  imageFile.value = null;
  imagePreview.value = '';
  removeImage.value = true;
}

// ---- Save ----

const num = (v: any) => (v === '' || v === null || v === undefined ? null : Number(String(v).replace(/,/g, '')));

// { 1: '20', 2: '' } -> { 1: 20 }, only ticked branches with a quantity.
function branchQuantities(quantities: Record<number, string>) {
  const out: Record<number, number> = {};
  for (const id of f.branch_ids) {
    const qty = num(quantities[id]);
    if (qty) out[id] = qty;
  }
  return out;
}

function body() {
  return {
    name: f.name.trim(), sku: f.sku.trim() || null, type: f.type,
    unit_id: f.unit_id, category_id: f.category_id, brand_id: f.brand_id,
    barcode: f.barcode.trim() || null, barcode_type: f.barcode_type, description: f.description.trim() || null,
    cost: f.type === 'single' ? num(f.cost) ?? 0 : null,
    price: f.type === 'single' ? num(f.price) ?? 0 : null,
    wholesale_price: f.type === 'single' ? num(f.wholesale_price) : null,
    tax_rate_id: f.tax_rate_id, price_includes_tax: f.price_includes_tax,
    manage_stock: f.manage_stock, alert_qty: num(f.alert_qty) ?? 0, allow_negative_stock: f.allow_negative_stock,
    branch_ids: f.branch_ids,
    opening_stock: showSingleOpening.value ? branchQuantities(f.opening_stock) : null,
    available_at_pos: f.available_at_pos, pos_favourite: f.pos_favourite, send_to_kitchen: f.send_to_kitchen,
    prep_minutes: f.send_to_kitchen ? num(f.prep_minutes) : null, active: f.active,
    variants: f.type === 'variable'
      ? f.variants.map((v: Variant) => ({ id: v.id || null, name: v.name.trim(), sku: v.sku.trim() || null, cost: num(v.cost) ?? 0, price: num(v.price) ?? 0, opening_stock: v.id ? null : branchQuantities(v.opening_stock) }))
      : undefined
  };
}

async function save(addAnother: boolean) {
  if (!f.name.trim()) { error.value = 'Enter a product name.'; return; }
  if (f.type === 'variable' && !f.variants.length) { error.value = 'Add at least one variation.'; return; }
  if (f.type === 'variable' && f.variants.some((v: Variant) => !v.name.trim())) { error.value = 'Give every variation a name.'; return; }
  if (!f.branch_ids.length) { error.value = 'Tick at least one branch that sells this product.'; return; }
  saving.value = true;
  error.value = '';
  try {
    const saved = productForm.id ? await api.put('/products/' + productForm.id, body()) : await api.post('/products', body());

    // The photo is sent after the product exists.
    if (imageFile.value) {
      const form = new FormData();
      form.append('image', imageFile.value);
      await api.post('/products/' + saved.id + '/image', form);
    } else if (removeImage.value && productForm.id) {
      await api.del('/products/' + saved.id + '/image');
    }

    productsVersion.value++;
    if (addAnother) {
      await loadLookups(true); // the next automatic SKU
      Object.assign(f, empty(), { type: f.type, category_id: f.category_id, unit_id: f.unit_id, branch_ids: f.branch_ids });
      if (f.type === 'variable') addVariant(); // start the next product with one empty row
      imageFile.value = null;
      imagePreview.value = '';
      showMargin();
    } else {
      productForm.open = false;
    }
  } catch (e: any) {
    error.value = e.message; // e.g. "The sku has already been taken."
  }
  saving.value = false;
}
</script>
