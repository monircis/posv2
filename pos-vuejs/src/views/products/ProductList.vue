<template>
  <template v-if="vm.pList">
    <div>
      <div style="padding:20px 24px 0;background:#ffffff;border-bottom:1px solid #dfe6ee">
        <div style="display:flex;align-items:center;gap:7px;font-size:11.5px;color:#7d8b9c;margin-bottom:8px"><span>Products</span><span style="color:#c2cedb">/</span><span style="color:#0f1c2b;font-weight:500">All products</span></div>
        <div style="display:flex;align-items:flex-start;gap:20px;flex-wrap:wrap">
          <div>
            <h2 style="margin:0 0 3px;font-size:22px;font-weight:700;letter-spacing:-0.018em">Products</h2>
            <div style="font-size:12.5px;color:#5d6b7d">{{ summary.products }} products · {{ summary.categories }} categories · stock valued at {{ money(summary.stock_value) }}</div>
          </div>
          <div style="margin-left:auto;display:flex;gap:8px;align-items:center">
            <div v-if="can('products.create')" @click="openProductForm()" style="height:34px;padding:0 15px;border-radius:8px;background:var(--c-primary);color:#ffffff;display:flex;align-items:center;font-size:12.5px;font-weight:600;cursor:pointer" v-pseudo:hover="'background:var(--c-primary-hover)'">+ Add product</div>
          </div>
        </div>
        <!-- Stock tabs -->
        <div style="display:flex;gap:2px;margin-top:16px">
          <div v-for="t in TABS" :key="t.key" @click="setTab(t.key)" :style="'padding:9px 12px;font-size:13px;cursor:pointer;display:flex;align-items:center;' + (tab === t.key ? 'color:#0f1c2b;font-weight:600;box-shadow:inset 0 -2px 0 var(--c-primary);' : 'color:#5d6b7d;font-weight:500;')">
            {{ t.name }}<span :style="'font-family:\'IBM Plex Mono\',monospace;font-size:10.5px;border-radius:4px;padding:1px 5px;margin-left:6px;' + (tab === t.key ? 'background:var(--c-primary-soft);color:var(--c-primary-text);' : 'background:#eef2f6;color:#7d8b9c;')">{{ counts[t.key] ?? 0 }}</span>
          </div>
        </div>
      </div>

      <div style="padding:20px 24px 32px">
        <!-- Filters -->
        <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-bottom:14px">
          <div style="position:relative;width:250px"><div style="position:absolute;left:10px;top:50%;transform:translateY(-50%);color:#9aa7b6;font-size:12px">⚲</div> <input :value="search" @input="onSearch" placeholder="Name, SKU or barcode" style="width:100%;height:34px;border:1px solid #dfe6ee;border-radius:8px;background:#ffffff;padding:0 10px 0 29px;font-family:'Public Sans',sans-serif;font-size:12.5px;outline:none" v-pseudo:focus="'border-color:var(--c-primary)'" /></div>
          <select :value="branchId" @change="setBranch" title="Stock and products of one branch" style="height:34px;border:1px solid #dfe6ee;border-radius:8px;background:#ffffff;padding:0 8px;font-family:'Public Sans',sans-serif;font-size:12.5px;outline:none">
            <option :value="0">All branches</option>
            <option v-for="b in branches" :key="b.id" :value="b.id">{{ b.name }}</option>
          </select>
          <div v-for="c in categoryChips" :key="c.id" @click="setCategory(c.id)" :style="'height:30px;padding:0 11px;border-radius:99px;display:flex;align-items:center;font-size:12px;cursor:pointer;' + (categoryId === c.id ? 'background:#0f1c2b;color:#ffffff;font-weight:600;' : 'border:1px solid #dfe6ee;color:#4a5866;background:#ffffff;')">{{ c.name }}</div>
          <div style="margin-left:auto;display:flex;gap:2px;background:#eef2f6;padding:3px;border-radius:8px">
            <div v-for="m in ['table', 'cards']" :key="m" @click="view = m" :style="'height:28px;padding:0 11px;border-radius:6px;display:grid;place-items:center;font-size:12px;cursor:pointer;text-transform:capitalize;' + (view === m ? 'background:#ffffff;font-weight:600;box-shadow:0 1px 3px rgba(15,28,43,.14);' : 'color:#5d6b7d;')">{{ m }}</div>
          </div>
        </div>

        <div v-if="loading && !rows.length" style="font-size:12.5px;color:#7d8b9c">Loading…</div>

        <!-- Table view -->
        <div v-if="view === 'table'" style="background:#ffffff;border:1px solid #dfe6ee;border-radius:12px;overflow:hidden">
          <div style="display:grid;grid-template-columns:2.3fr 1fr 0.9fr 0.85fr 1fr 0.8fr 1fr 64px;gap:12px;padding:10px 16px;background:#f6f9fc;border-bottom:1px solid #dfe6ee;font-family:'IBM Plex Mono',monospace;font-size:9.5px;letter-spacing:0.07em;text-transform:uppercase;color:#5d6b7d;align-items:center">
            <div>Product</div>
            <div>Category</div>
            <div>SKU</div>
            <div style="text-align:right">Cost</div>
            <div style="text-align:right">Price</div>
            <div style="text-align:right">Margin</div>
            <div style="text-align:right">Stock</div>
            <div></div>
          </div>
          <div v-if="!loading && !rows.length" style="padding:16px;font-size:12.5px;color:#7d8b9c">No products match.</div>
          <div v-for="p in rows" :key="p.id" :style="'display:grid;grid-template-columns:2.3fr 1fr 0.9fr 0.85fr 1fr 0.8fr 1fr 64px;gap:12px;padding:11px 16px;border-bottom:1px solid #f0f4f8;align-items:center;' + (p.active ? '' : 'opacity:.55;')" v-pseudo:hover="'background:#f9fbfd'">
            <div style="display:flex;align-items:center;gap:11px;min-width:0">
              <img v-if="p.image_url" :src="p.image_url" alt="" style="width:32px;height:32px;border-radius:7px;object-fit:cover;border:1px solid #e4eaf1;flex:none" />
              <div v-else style="width:32px;height:32px;border-radius:7px;background:#eef2f6;border:1px solid #e4eaf1;flex:none;display:grid;place-items:center;font-family:'IBM Plex Mono',monospace;font-size:8px;color:#9aa7b6">IMG</div>
              <div style="min-width:0">
                <div @click="goDetails(p.id)" style="font-size:13px;font-weight:500;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;cursor:pointer;color:var(--c-primary)">{{ p.name }}</div>
                <div style="font-size:11px;color:#8b98a8">{{ meta(p) }}</div>
              </div>
            </div>
            <div style="font-size:12.5px;color:#4a5866;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">{{ p.category?.name || '—' }}</div>
            <div style="font-family:'IBM Plex Mono',monospace;font-size:12px;color:#5d6b7d">{{ p.sku }}</div>
            <div style="font-family:'IBM Plex Mono',monospace;font-size:12.5px;text-align:right;color:#4a5866">{{ p.type === 'variable' ? '—' : money(p.cost) }}</div>
            <div style="font-family:'IBM Plex Mono',monospace;font-size:12.5px;text-align:right;font-weight:500">{{ priceText(p) }}</div>
            <div :style="'font-family:\'IBM Plex Mono\',monospace;font-size:12.5px;text-align:right;' + marginStyle(p)">{{ marginText(p) }}</div>
            <div style="text-align:right"><span :style="stockBadge(stockStatus(p))">{{ p.manage_stock ? qtyText(p.stock_qty, p.unit?.short_name) : '—' }}</span></div>
            <div style="display:flex;justify-content:flex-end;gap:6px">
              <div @click="printLabels(p.id)" title="Print barcode labels" style="width:26px;height:26px;border-radius:6px;border:1px solid #e4eaf1;display:grid;place-items:center;font-size:12px;color:#5d6b7d;cursor:pointer" v-pseudo:hover="'background:#f2f6fa;color:var(--c-primary)'">▥</div>
              <div v-if="can('products.edit')" @click="openProductForm(p.id)" title="Edit" style="width:26px;height:26px;border-radius:6px;border:1px solid #e4eaf1;display:grid;place-items:center;font-size:11px;color:#5d6b7d;cursor:pointer" v-pseudo:hover="'background:#f2f6fa;color:var(--c-primary)'">✎</div>
            </div>
          </div>
          <div style="display:flex;align-items:center;justify-content:space-between;gap:16px;padding:12px 16px;flex-wrap:wrap">
            <div style="font-size:12px;color:#7d8b9c">Showing {{ rows.length }} of {{ total }}</div>
            <div v-if="lastPage > 1" style="display:flex;gap:4px;align-items:center">
              <div @click="goPage(page - 1)" :style="pageBtn(false, page > 1)">Prev</div>
              <div v-for="n in lastPage" :key="n" @click="goPage(n)" :style="pageBtn(n === page, true)">{{ n }}</div>
              <div @click="goPage(page + 1)" :style="pageBtn(false, page < lastPage)">Next</div>
            </div>
          </div>
        </div>

        <!-- Card view -->
        <div v-if="view === 'cards'" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(210px,1fr));gap:12px">
          <div v-for="p in rows" :key="p.id" @click="goDetails(p.id)" style="background:#ffffff;border:1px solid #dfe6ee;border-radius:12px;padding:12px;cursor:pointer" v-pseudo:hover="'border-color:var(--c-primary);box-shadow:0 6px 18px -10px rgba(22,104,201,.4)'">
            <img v-if="p.image_url" :src="p.image_url" alt="" style="width:100%;height:104px;border-radius:9px;object-fit:cover;border:1px solid #e4eaf1;margin-bottom:11px;display:block" />
            <div v-else style="height:104px;border-radius:9px;background:#eef2f6;border:1px solid #e4eaf1;display:grid;place-items:center;font-family:'IBM Plex Mono',monospace;font-size:9px;color:#9aa7b6;margin-bottom:11px">IMG</div>
            <div style="font-size:13px;font-weight:600;line-height:1.3;margin-bottom:3px">{{ p.name }}</div>
            <div style="font-family:'IBM Plex Mono',monospace;font-size:10.5px;color:#9aa7b6;margin-bottom:9px">{{ p.sku }} · {{ p.category?.name || '—' }}</div>
            <div style="display:flex;align-items:baseline;gap:8px">
              <div style="font-family:'IBM Plex Mono',monospace;font-size:14px;font-weight:600;flex:1">{{ priceText(p) }}</div>
              <div :style="stockBadge(stockStatus(p))">{{ p.manage_stock ? qtyText(p.stock_qty, p.unit?.short_name) : '—' }}</div>
            </div>
          </div>
        </div>
        <div v-if="view === 'cards' && lastPage > 1" style="display:flex;gap:4px;justify-content:flex-end;margin-top:14px">
          <div @click="goPage(page - 1)" :style="pageBtn(false, page > 1)">Prev</div>
          <div @click="goPage(page + 1)" :style="pageBtn(false, page < lastPage)">Next</div>
        </div>
      </div>
    </div>
  </template>
</template>

<script setup lang="ts">
// Products > All products. API: GET /api/products?search=&category_id=&branch_id=&stock=&page= (the form is in ProductsPage.vue).
import { computed, onMounted, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import { usePageVm } from '../../erp/useErpPage';
import { api } from '../../api/http.js';
import { can } from '../../api/auth.js';
import { branches } from '../../api/branches.js';
import { lookups, loadLookups, openProductForm, productsVersion, money, qtyText, stockStatus, stockBadge, priceExTax, marginPercent } from '../../api/products.js';

const vm = usePageVm();
const router = useRouter();

const TABS = [
  { key: 'all', name: 'All' }, { key: 'in', name: 'In stock' }, { key: 'low', name: 'Low stock' }, { key: 'out', name: 'Out of stock' }
];

const tab = ref('all');
const search = ref('');
const categoryId = ref(0);
const branchId = ref(0); // 0 = all branches
const view = ref('table');
const page = ref(1);

const rows = ref<any[]>([]);
const total = ref(0);
const lastPage = ref(1);
const counts = ref<Record<string, number>>({});
const summary = ref<any>({ products: 0, categories: 0, stock_value: 0 });
const loading = ref(true);

async function load() {
  loading.value = true;
  const params = new URLSearchParams({ page: String(page.value) });
  if (search.value.trim()) params.set('search', search.value.trim());
  if (categoryId.value) params.set('category_id', String(categoryId.value));
  if (branchId.value) params.set('branch_id', String(branchId.value));
  if (tab.value !== 'all') params.set('stock', tab.value);
  try {
    const res = await api.get('/products?' + params.toString());
    rows.value = res.data;
    total.value = res.total;
    lastPage.value = res.last_page;
    counts.value = res.counts;
    summary.value = res.summary;
  } catch (e) { /* the error bar shows why */ }
  loading.value = false;
}

onMounted(async () => {
  await load();
  await loadLookups().catch(() => {});
});
watch(productsVersion, load); // a product was saved or deleted

// Wait until typing stops for a moment before searching.
let timer: any = null;
function onSearch(e: Event) {
  search.value = (e.target as HTMLInputElement).value;
  clearTimeout(timer);
  timer = setTimeout(() => { page.value = 1; load(); }, 300);
}

function setTab(key: string) { tab.value = key; page.value = 1; load(); }
function setCategory(id: number) { categoryId.value = id; page.value = 1; load(); }
function setBranch(e: Event) { branchId.value = Number((e.target as HTMLSelectElement).value); page.value = 1; load(); }
function goPage(n: number) { if (n < 1 || n > lastPage.value) return; page.value = n; load(); }

// "All" plus the top categories (a top category also shows its sub-categories).
const categoryChips = computed(() => [{ id: 0, name: 'All' }].concat(lookups.categories.filter((c: any) => c.parent_id === null)));

// Products > Print labels, with this product ticked.
function printLabels(id: number) {
  router.push({ name: 'products-labels', query: { product: id } });
}

function goDetails(id: number) {
  router.push({ name: 'products-details', query: { id } });
}

function meta(p: any) {
  return [p.unit?.name, p.brand?.name, p.type === 'variable' ? 'variable' : ''].filter(Boolean).join(' · ');
}

function priceText(p: any) {
  if (p.type !== 'variable') return money(p.price);
  if (p.price_min === null) return '—';
  return Number(p.price_min) === Number(p.price_max) ? money(p.price_min) : money(p.price_min) + '–' + money(p.price_max);
}

function margin(p: any) {
  if (p.type === 'variable') return null;
  return marginPercent(p.cost, priceExTax(p.price, p.price_includes_tax, p.tax_rate?.rate));
}
function marginText(p: any) {
  const m = margin(p);
  return m === null ? '—' : m.toFixed(1) + '%';
}
// Margins under 20% are amber.
function marginStyle(p: any) {
  const m = margin(p);
  return m !== null && m < 20 ? 'color:#8a5300;font-weight:600;' : 'color:#4a5866;';
}

function pageBtn(on: boolean, enabled: boolean) {
  return "min-width:29px;height:29px;padding:0 9px;border-radius:7px;display:grid;place-items:center;font-family:'IBM Plex Mono',monospace;font-size:12px;"
    + (on ? 'background:var(--c-primary);color:#ffffff;font-weight:600;' : 'border:1px solid #dfe6ee;')
    + (enabled ? 'cursor:pointer;color:' + (on ? '#ffffff' : '#4a5866') + ';' : 'color:#c2cedb;');
}
</script>
