<template>
  <template v-if="vm.pDetails">
    <div>
      <div v-if="!p" style="padding:24px;font-size:13px;color:#5d6b7d">
        {{ loading ? 'Loading…' : 'Product not found.' }}
        <span v-if="!loading" @click="goList" style="color:var(--c-primary);cursor:pointer;margin-left:6px">Back to products</span>
      </div>

      <template v-else>
        <div style="padding:20px 24px 0;background:#ffffff;border-bottom:1px solid #dfe6ee">
          <div style="display:flex;align-items:center;gap:7px;font-size:11.5px;color:#7d8b9c;margin-bottom:14px"><span @click="goList" style="cursor:pointer;color:var(--c-primary)">Products</span><span style="color:#c2cedb">/</span><span style="color:#0f1c2b;font-weight:500">{{ p.name }}</span></div>
          <div style="display:flex;align-items:flex-start;gap:16px;flex-wrap:wrap">
            <img v-if="p.image_url" :src="p.image_url" alt="" style="width:56px;height:56px;border-radius:12px;object-fit:cover;border:1px solid #e4eaf1;flex:none" />
            <div v-else style="width:56px;height:56px;border-radius:12px;background:#eef2f6;border:1px solid #e4eaf1;display:grid;place-items:center;font-family:'IBM Plex Mono',monospace;font-size:9px;color:#9aa7b6;flex:none">IMG</div>
            <div style="min-width:0">
              <div style="display:flex;align-items:center;gap:9px;flex-wrap:wrap;margin-bottom:4px">
                <h2 style="margin:0;font-size:22px;font-weight:700;letter-spacing:-0.018em">{{ p.name }}</h2>
                <span :style="stockBadge(status) + ';font-family:inherit;font-size:11.5px;padding:3px 9px'">{{ STOCK_LABELS[status] }}</span>
                <span style="background:#eef2f6;color:#5d6b7d;border-radius:5px;padding:3px 9px;font-size:11.5px;font-weight:600;text-transform:capitalize">{{ p.type }}</span>
                <span v-if="!p.active" style="background:#fdeaea;color:#b02020;border-radius:5px;padding:3px 9px;font-size:11.5px;font-weight:600">Inactive</span>
              </div>
              <div style="font-family:'IBM Plex Mono',monospace;font-size:12px;color:#5d6b7d">SKU {{ p.sku }} · {{ p.category?.name || 'No category' }} · {{ p.unit?.name }}<span v-if="p.barcode"> · barcode {{ p.barcode }}</span></div>
            </div>
            <div style="margin-left:auto;display:flex;gap:8px;align-items:center">
              <div v-if="can('products.delete')" @click="remove" style="height:34px;padding:0 13px;border-radius:8px;border:1px solid #f3cfcf;background:#fdf4f4;display:flex;align-items:center;font-size:12.5px;font-weight:600;color:#b02020;cursor:pointer">Delete</div>
              <div v-if="p.manage_stock && (can('products.edit') || can('adjust.create'))" @click="openAdjust()" style="height:34px;padding:0 13px;border-radius:8px;border:1px solid #dfe6ee;display:flex;align-items:center;font-size:12.5px;font-weight:500;color:#4a5866;cursor:pointer" v-pseudo:hover="'background:#f2f6fa'">Adjust stock</div>
              <div @click="router.push({ name: 'products-labels', query: { product: p.id } })" style="height:34px;padding:0 13px;border-radius:8px;border:1px solid #dfe6ee;display:flex;align-items:center;font-size:12.5px;font-weight:500;color:#4a5866;cursor:pointer">Print barcode</div>
              <div v-if="can('products.edit')" @click="openProductForm(p.id)" style="height:34px;padding:0 15px;border-radius:8px;background:var(--c-primary);color:#ffffff;display:flex;align-items:center;font-size:12.5px;font-weight:600;cursor:pointer">Edit product</div>
            </div>
          </div>
          <div style="display:flex;gap:2px;margin-top:16px">
            <div v-for="t in tabs" :key="t[0]" @click="tab = t[0]" :style="'padding:9px 12px;font-size:13px;cursor:pointer;' + (tab === t[0] ? 'color:#0f1c2b;font-weight:600;box-shadow:inset 0 -2px 0 var(--c-primary);' : 'color:#5d6b7d;font-weight:500;')">{{ t[1] }}</div>
          </div>
        </div>

        <div style="padding:20px 24px 32px;display:grid;grid-template-columns:1fr 320px;gap:16px;align-items:start">
          <div style="display:grid;gap:14px">
            <!-- Stock by branch -->
            <template v-if="tab === 'stock'">
              <div v-if="!p.manage_stock" style="background:#ffffff;border:1px solid #dfe6ee;border-radius:12px;padding:16px 18px;font-size:12.5px;color:#5d6b7d">This product does not manage stock (made to order).</div>
              <div v-else style="background:#ffffff;border:1px solid #dfe6ee;border-radius:12px;overflow:hidden">
                <div style="padding:14px 18px;border-bottom:1px solid #eaeff5;font-size:13px;font-weight:700">Stock by branch <span v-if="soldBranches.length < branches.length" style="font-weight:400;font-size:11.5px;color:#7d8b9c">· only branches that sell it</span></div>
                <div style="display:grid;grid-template-columns:1.5fr 1.2fr 1fr 1fr 70px;gap:12px;padding:9px 18px;background:#f6f9fc;border-bottom:1px solid #dfe6ee;font-family:'IBM Plex Mono',monospace;font-size:9.5px;letter-spacing:0.07em;text-transform:uppercase;color:#5d6b7d">
                  <div>Branch</div>
                  <div>{{ p.type === 'variable' ? 'Variation' : '' }}</div>
                  <div style="text-align:right">On hand</div>
                  <div style="text-align:right">Value</div>
                  <div></div>
                </div>
                <div v-for="s in stockRows" :key="s.key" style="display:grid;grid-template-columns:1.5fr 1.2fr 1fr 1fr 70px;gap:12px;padding:11px 18px;border-bottom:1px solid #f0f4f8;align-items:center">
                  <div style="font-size:12.5px;font-weight:500">{{ s.branch }}</div>
                  <div style="font-size:12.5px;color:#4a5866">{{ s.variant }}</div>
                  <div style="text-align:right"><span :style="stockBadge(s.status)">{{ qtyText(s.qty, unit) }}</span></div>
                  <div style="font-family:'IBM Plex Mono',monospace;font-size:12.5px;text-align:right;font-weight:500">{{ money(s.qty * s.cost) }}</div>
                  <div style="text-align:right"><span v-if="can('products.edit') || can('adjust.create')" @click="openAdjust(s.branchId, s.variantId)" style="font-size:12px;color:var(--c-primary);cursor:pointer">Adjust</span></div>
                </div>
              </div>
            </template>

            <!-- Movements -->
            <div v-if="tab === 'moves'" style="background:#ffffff;border:1px solid #dfe6ee;border-radius:12px;overflow:hidden">
              <div style="padding:14px 18px;border-bottom:1px solid #eaeff5">
                <div style="font-size:13px;font-weight:700">Stock movements</div>
                <div style="font-size:11.5px;color:#7d8b9c;margin-top:2px">Every change to quantity, with the reason. Last 100.</div>
              </div>
              <div style="display:grid;grid-template-columns:1fr 1.2fr 1.2fr 0.8fr 0.8fr;gap:12px;padding:9px 18px;background:#f6f9fc;border-bottom:1px solid #dfe6ee;font-family:'IBM Plex Mono',monospace;font-size:9.5px;letter-spacing:0.07em;text-transform:uppercase;color:#5d6b7d">
                <div>Date</div>
                <div>Type</div>
                <div>Branch</div>
                <div style="text-align:right">Change</div>
                <div style="text-align:right">Balance</div>
              </div>
              <div v-if="!movements.length" style="padding:16px 18px;font-size:12.5px;color:#7d8b9c">No movements yet.</div>
              <div v-for="m in movements" :key="m.id" style="display:grid;grid-template-columns:1fr 1.2fr 1.2fr 0.8fr 0.8fr;gap:12px;padding:10px 18px;border-bottom:1px solid #f0f4f8;align-items:center">
                <div style="font-family:'IBM Plex Mono',monospace;font-size:11.5px;color:#5d6b7d">{{ new Date(m.created_at).toLocaleDateString('en-AU') }}</div>
                <div style="min-width:0">
                  <div style="font-size:12.5px;color:#4a5866">{{ m.kind }}<span v-if="m.ref" style="font-family:'IBM Plex Mono',monospace;font-size:11.5px;color:#7d8b9c"> · {{ m.ref }}</span></div>
                  <div v-if="m.note || m.user_name" style="font-size:11px;color:#9aa7b6;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">{{ [m.note, m.user_name].filter(Boolean).join(' · ') }}</div>
                </div>
                <div style="font-size:12.5px;color:#4a5866">{{ m.branch?.name }}<span v-if="m.variant" style="color:#8b98a8"> · {{ m.variant.name }}</span></div>
                <div :style="'font-family:\'IBM Plex Mono\',monospace;font-size:12.5px;text-align:right;font-weight:600;color:' + (m.change < 0 ? '#b02020' : '#0f6b43')">{{ (m.change > 0 ? '+' : '') + qtyText(m.change) }}</div>
                <div style="font-family:'IBM Plex Mono',monospace;font-size:12.5px;text-align:right;font-weight:600">{{ qtyText(m.balance) }}</div>
              </div>
            </div>

            <!-- Variations -->
            <div v-if="tab === 'variants'" style="background:#ffffff;border:1px solid #dfe6ee;border-radius:12px;overflow:hidden">
              <div style="padding:14px 18px;border-bottom:1px solid #eaeff5;font-size:13px;font-weight:700">Variations</div>
              <div style="display:grid;grid-template-columns:1.4fr 1fr 0.9fr 0.9fr 0.9fr;gap:12px;padding:9px 18px;background:#f6f9fc;border-bottom:1px solid #dfe6ee;font-family:'IBM Plex Mono',monospace;font-size:9.5px;letter-spacing:0.07em;text-transform:uppercase;color:#5d6b7d">
                <div>Variation</div>
                <div>SKU</div>
                <div style="text-align:right">Cost</div>
                <div style="text-align:right">Price</div>
                <div style="text-align:right">Stock</div>
              </div>
              <div v-for="v in p.variants" :key="v.id" style="display:grid;grid-template-columns:1.4fr 1fr 0.9fr 0.9fr 0.9fr;gap:12px;padding:11px 18px;border-bottom:1px solid #f0f4f8;align-items:center">
                <div style="font-size:12.5px;font-weight:500">{{ v.name }}</div>
                <div style="font-family:'IBM Plex Mono',monospace;font-size:12px;color:#5d6b7d">{{ v.sku }}</div>
                <div style="font-family:'IBM Plex Mono',monospace;font-size:12.5px;text-align:right;color:#4a5866">{{ money(v.cost) }}</div>
                <div style="font-family:'IBM Plex Mono',monospace;font-size:12.5px;text-align:right;font-weight:500">{{ money(v.price) }}</div>
                <div style="text-align:right"><span :style="stockBadge(stockStatus({ manage_stock: p.manage_stock, stock_qty: v.stock_qty, alert_qty: p.alert_qty }))">{{ p.manage_stock ? qtyText(v.stock_qty, unit) : '—' }}</span></div>
              </div>
            </div>
          </div>

          <div style="display:grid;gap:12px">
            <!-- On hand -->
            <div style="background:#ffffff;border:1px solid #dfe6ee;border-radius:12px;padding:16px 18px">
              <div style="font-size:11.5px;color:#7d8b9c;margin-bottom:7px">On hand, all branches</div>
              <div :style="'font-family:\'IBM Plex Mono\',monospace;font-size:27px;font-weight:600;letter-spacing:-0.025em;line-height:1;color:' + (status === 'out' ? '#b02020' : status === 'low' ? '#8a5300' : '#0f1c2b')">{{ p.manage_stock ? qtyText(p.stock_qty, unit) : '—' }}</div>
              <div v-if="p.manage_stock" style="font-size:11.5px;color:#5d6b7d;margin-top:6px">Alert at {{ qtyText(p.alert_qty, unit) }}<span v-if="p.allow_negative_stock"> · can go below 0</span></div>
            </div>

            <!-- Pricing -->
            <div v-if="p.type === 'single'" style="background:#ffffff;border:1px solid #dfe6ee;border-radius:12px;padding:16px 18px">
              <div style="font-size:12.5px;font-weight:700;margin-bottom:13px">Pricing</div>
              <div style="display:grid;gap:11px">
                <div v-for="r in pricing" :key="r.k" style="display:flex;align-items:baseline;gap:10px">
                  <div style="font-size:12.5px;color:#5d6b7d;flex:1">{{ r.k }}</div>
                  <div :style="'font-family:\'IBM Plex Mono\',monospace;font-size:' + (r.strong ? '14px;font-weight:700' : '12.5px;font-weight:500')">{{ r.v }}</div>
                </div>
              </div>
            </div>

            <!-- Attributes -->
            <div style="background:#ffffff;border:1px solid #dfe6ee;border-radius:12px;padding:16px 18px">
              <div style="font-size:12.5px;font-weight:700;margin-bottom:13px">Attributes</div>
              <div style="display:grid;gap:11px">
                <div v-for="a in attributes" :key="a.k">
                  <div style="font-size:11px;color:#8b98a8;margin-bottom:2px">{{ a.k }}</div>
                  <div style="font-size:12.5px;font-weight:500">{{ a.v }}</div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </template>

      <!-- Adjust stock -->
      <template v-if="adjust.open">
        <div @click="adjust.open = false" style="position:fixed;inset:0;background:rgba(9,22,35,.48);z-index:40"></div>
        <div style="position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);width:420px;max-width:calc(100vw - 32px);background:#ffffff;border-radius:16px;z-index:41;box-shadow:0 30px 80px -24px rgba(9,22,35,.65);padding:22px">
          <div style="font-size:17px;font-weight:700;margin-bottom:4px">Adjust stock</div>
          <div style="font-size:12px;color:#5d6b7d;margin-bottom:16px">Enter the quantity you counted. The difference is saved as an adjustment.</div>
          <div style="display:grid;gap:12px">
            <label :style="labelStyle">Branch
              <select v-model="adjust.branch_id" @change="fillCurrent" :style="inputStyle">
                <option v-for="b in soldBranches" :key="b.id" :value="b.id">{{ b.name }}</option>
              </select>
            </label>
            <label v-if="p.type === 'variable'" :style="labelStyle">Variation
              <select v-model="adjust.variant_id" @change="fillCurrent" :style="inputStyle">
                <option v-for="v in p.variants" :key="v.id" :value="v.id">{{ v.name }}</option>
              </select>
            </label>
            <label :style="labelStyle">New quantity ({{ unit }}) <span style="font-weight:400;color:#7d8b9c">now {{ qtyText(adjust.current) }}</span>
              <input v-model="adjust.qty" :style="inputStyle" />
            </label>
            <label :style="labelStyle">Note <input v-model="adjust.note" placeholder="e.g. weekly count, wastage" :style="inputStyle" /></label>
          </div>
          <div v-if="adjust.error" style="margin-top:12px;font-size:12px;color:#b02020;font-weight:600">{{ adjust.error }}</div>
          <div style="display:flex;gap:9px;justify-content:flex-end;margin-top:20px">
            <div @click="adjust.open = false" style="height:38px;padding:0 16px;border-radius:9px;border:1px solid #dfe6ee;display:flex;align-items:center;font-size:13px;font-weight:600;color:#4a5866;cursor:pointer">Cancel</div>
            <div @click="saveAdjust" style="height:38px;padding:0 16px;border-radius:9px;background:var(--c-primary);color:#ffffff;display:flex;align-items:center;font-size:13px;font-weight:700;cursor:pointer">Save</div>
          </div>
        </div>
      </template>
    </div>
  </template>
</template>

<script setup lang="ts">
// Products > one product (/products/details?id=5).
// API: GET /api/products/{id}, GET /api/products/{id}/movements, POST /api/products/{id}/adjust-stock, DELETE /api/products/{id}
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { usePageVm } from '../../erp/useErpPage';
import { api } from '../../api/http.js';
import { can } from '../../api/auth.js';
import { branches, loadBranches } from '../../api/branches.js';
import { openProductForm, productsVersion, money, qtyText, stockStatus, stockBadge, STOCK_LABELS, priceExTax, marginPercent } from '../../api/products.js';

const vm = usePageVm();
const route = useRoute();
const router = useRouter();

const labelStyle = 'display:grid;gap:5px;font-size:12px;font-weight:600';
const inputStyle = "width:100%;height:36px;border:1px solid #dfe6ee;border-radius:8px;padding:0 11px;font-family:'Public Sans',sans-serif;font-size:13px;font-weight:400;outline:none;background:#ffffff";

const p = ref<any>(null);
const movements = ref<any[]>([]);
const loading = ref(true);
const tab = ref('stock');

const id = computed(() => Number(route.query.id));

async function load() {
  if (!id.value) { p.value = null; loading.value = false; return; }
  loading.value = true;
  try {
    if (!branches.value.length) await loadBranches();
    p.value = await api.get('/products/' + id.value);
    movements.value = await api.get('/products/' + id.value + '/movements');
  } catch (e) {
    p.value = null;
  }
  loading.value = false;
}

onMounted(load);
watch(id, load);
watch(productsVersion, load); // edited in the form

const unit = computed(() => p.value?.unit?.short_name || '');
const status = computed(() => (p.value ? stockStatus(p.value) : 'untracked'));
const tabs = computed(() => {
  const list = [['stock', 'Stock'], ['moves', 'Movements']];
  if (p.value?.type === 'variable') list.push(['variants', 'Variations (' + p.value.variants.length + ')']);
  return list;
});

// The branches that sell this product (ticked in the product form).
const soldBranches = computed(() => branches.value.filter(b => p.value.branch_ids.includes(b.id)));

// One row per branch (single) or per branch and variant (variable), including branches with 0.
const stockRows = computed(() => {
  const rows: any[] = [];
  const qtyAt = (branchId: number, variantId: number | null) =>
    p.value.stocks.filter((s: any) => s.branch_id === branchId && s.variant_id === variantId).reduce((a: number, s: any) => a + s.qty, 0);
  for (const b of soldBranches.value) {
    if (p.value.type === 'variable') {
      for (const v of p.value.variants) {
        const qty = qtyAt(b.id, v.id);
        rows.push({ key: b.id + '-' + v.id, branch: b.name, branchId: b.id, variant: v.name, variantId: v.id, qty, cost: v.cost, status: stockStatus({ manage_stock: true, stock_qty: qty, alert_qty: p.value.alert_qty }) });
      }
    } else {
      const qty = qtyAt(b.id, null);
      rows.push({ key: String(b.id), branch: b.name, branchId: b.id, variant: '', variantId: null, qty, cost: p.value.cost, status: stockStatus({ manage_stock: true, stock_qty: qty, alert_qty: p.value.alert_qty }) });
    }
  }
  return rows;
});

const pricing = computed(() => {
  const rate = p.value.tax_rate?.rate || 0;
  const ex = priceExTax(p.value.price, p.value.price_includes_tax, rate);
  const m = marginPercent(p.value.cost, ex);
  const rows = [
    { k: 'Cost ex. tax', v: money(p.value.cost), strong: false },
    { k: 'Margin', v: m === null ? '—' : m.toFixed(1) + '%', strong: false },
    { k: 'Price ex. tax', v: money(ex), strong: false },
    { k: (p.value.tax_rate?.name || 'Tax') + ' ' + rate + '%', v: money(ex * rate / 100), strong: false },
    { k: 'Price inc. tax', v: money(ex * (1 + rate / 100)), strong: true }
  ];
  if (p.value.wholesale_price !== null) rows.push({ k: 'Wholesale ex. tax', v: money(p.value.wholesale_price), strong: false });
  return rows;
});

const attributes = computed(() => {
  const x = p.value;
  const rows = [
    { k: 'SKU', v: x.sku },
    { k: 'Barcode', v: x.barcode ? x.barcode + ' (' + x.barcode_type + ')' : '— (' + x.barcode_type + ')' },
    { k: 'Category / brand', v: [x.category?.name, x.brand?.name].filter(Boolean).join(' · ') || '—' },
    { k: 'Unit', v: x.unit ? x.unit.name + ' (' + x.unit.short_name + ')' : '—' },
    { k: 'Sold at', v: soldBranches.value.length === branches.value.length ? 'All branches' : soldBranches.value.map(b => b.name).join(', ') },
    { k: 'POS', v: [x.available_at_pos ? 'available' : 'hidden', x.pos_favourite ? 'favourite' : ''].filter(Boolean).join(' · ') },
    { k: 'Kitchen', v: x.send_to_kitchen ? 'sends a ticket' + (x.prep_minutes ? ' · ' + x.prep_minutes + ' min prep' : '') : 'no' }
  ];
  if (x.description) rows.push({ k: 'Description', v: x.description });
  if (x.created_at) rows.push({ k: 'Added', v: x.created_at.split('-').reverse().join('/') });
  return rows;
});

function goList() {
  router.push({ name: 'products' });
}

async function remove() {
  if (!confirm('Delete ' + p.value.name + '?')) return;
  try {
    await api.del('/products/' + p.value.id);
    productsVersion.value++;
    goList();
  } catch (e) { /* the error bar shows why, e.g. "still has stock" */ }
}

// ---- Adjust stock ----

const adjust = reactive({ open: false, branch_id: 0, variant_id: null as number | null, qty: '', current: 0, note: '', error: '' });

function currentQty() {
  return p.value.stocks
    .filter((s: any) => s.branch_id === adjust.branch_id && s.variant_id === (p.value.type === 'variable' ? adjust.variant_id : null))
    .reduce((a: number, s: any) => a + s.qty, 0);
}

function fillCurrent() {
  adjust.current = currentQty();
  adjust.qty = String(adjust.current);
}

function openAdjust(branchId?: number, variantId?: number | null) {
  Object.assign(adjust, {
    open: true, error: '', note: '',
    branch_id: branchId || soldBranches.value[0]?.id || 0,
    variant_id: variantId ?? (p.value.variants[0]?.id ?? null)
  });
  fillCurrent();
}

async function saveAdjust() {
  if (adjust.qty === '' || isNaN(Number(adjust.qty))) { adjust.error = 'Enter a number.'; return; }
  adjust.error = '';
  try {
    await api.post('/products/' + p.value.id + '/adjust-stock', {
      branch_id: adjust.branch_id,
      variant_id: p.value.type === 'variable' ? adjust.variant_id : null,
      qty: Number(adjust.qty),
      note: adjust.note || null
    });
    adjust.open = false;
    productsVersion.value++; // reloads this page and the list
  } catch (e: any) {
    adjust.error = e.message;
  }
}
</script>
