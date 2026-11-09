// Products from the POS API, shared by the product list, product details and the add/edit form (ProductsPage.vue).
import { reactive, ref } from 'vue';
import { api } from './http.js';
import { loadBranches } from './branches.js';

// The add / edit product form. id = 0 adds a new product.
export const productForm = reactive({ open: false, id: 0 });

export function openProductForm(id = 0) {
  productForm.id = id;
  productForm.open = true;
}

// Goes up by one after a product is saved, deleted or its stock changes, so the list and details reload.
export const productsVersion = ref(0);

// Lists the form and the filters need. Loaded once.
export const lookups = reactive({
  loaded: false,
  categories: /** @type {any[]} */ ([]),
  brands: /** @type {any[]} */ ([]),
  units: /** @type {any[]} */ ([]),
  taxRates: /** @type {any[]} */ ([]),
  defaults: /** @type {any} */ ({})
});

export async function loadLookups(again = false) {
  if (lookups.loaded && !again) return;
  lookups.categories = await api.get('/categories');
  lookups.brands = await api.get('/brands');
  lookups.units = await api.get('/units');
  const formData = await api.get('/products/form-data');
  lookups.taxRates = formData.tax_rates;
  lookups.defaults = formData.defaults;
  await loadBranches();
  lookups.loaded = true;
}

// Categories in tree order with their level (0 = top), for an indented <select>.
export function categoryTree(categories) {
  const out = [];
  const addChildren = (parentId, level) => {
    for (const c of categories) {
      if (c.parent_id === parentId) {
        out.push({ ...c, level });
        addChildren(c.id, level + 1);
      }
    }
  };
  addChildren(null, 0);
  return out;
}

// 1842.5 -> "1,842.50"
export function money(n) {
  return (Number(n) || 0).toLocaleString('en-AU', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

// 12.5 + "kg" -> "12.5 kg"
export function qtyText(qty, unit) {
  const n = Number(qty) || 0;
  return (Number.isInteger(n) ? String(n) : n.toFixed(3).replace(/0+$/, '')) + (unit ? ' ' + unit : '');
}

// Stock status: 'untracked', 'out', 'low' or 'in'.
export function stockStatus(product) {
  if (!product.manage_stock) return 'untracked';
  const qty = Number(product.stock_qty) || 0;
  if (qty <= 0) return 'out';
  if (qty <= Number(product.alert_qty)) return 'low';
  return 'in';
}

export const STOCK_LABELS = { in: 'In stock', low: 'Low stock', out: 'Out of stock', untracked: 'Not tracked' };

export function stockBadge(status) {
  const colors = status === 'out' ? ['#fdeaea', '#b02020'] : status === 'low' ? ['#fdf1dd', '#8a5300'] : status === 'in' ? ['#e7f6ee', '#0f6b43'] : ['#eef2f6', '#5d6b7d'];
  return "font-family:'IBM Plex Mono',monospace;font-size:12px;font-weight:600;border-radius:5px;padding:3px 7px;background:" + colors[0] + ';color:' + colors[1];
}

// Selling price without tax.
export function priceExTax(price, includesTax, rate) {
  const p = Number(price) || 0;
  return includesTax ? p / (1 + (Number(rate) || 0) / 100) : p;
}

// Margin = how much the price (ex tax) is above the cost, in % of the cost. Cost 18.40, price ex 24.84 -> 35%.
export function marginPercent(cost, priceEx) {
  const c = Number(cost) || 0;
  if (c <= 0) return null;
  return ((priceEx - c) / c) * 100;
}
