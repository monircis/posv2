<template>
  <template v-if="vm.pLabels">
    <div>
      <div style="padding:20px 24px 16px;background:#ffffff;border-bottom:1px solid #dfe6ee">
        <div style="display:flex;align-items:center;gap:7px;font-size:11.5px;color:#7d8b9c;margin-bottom:8px"><span>Products</span><span style="color:#c2cedb">/</span><span style="color:#0f1c2b;font-weight:500">Print labels</span></div>
        <h2 style="margin:0 0 3px;font-size:22px;font-weight:700;letter-spacing:-0.018em">Print barcode labels</h2>
        <div style="font-size:12.5px;color:#5d6b7d">Type how many labels you want for each product, then print. Each label has the barcode, the product name and the price.</div>
      </div>

      <div style="padding:20px 24px 32px;display:grid;grid-template-columns:1fr 360px;gap:16px;align-items:start">
        <!-- Products and how many labels -->
        <div style="background:#ffffff;border:1px solid #dfe6ee;border-radius:12px;overflow:hidden">
          <div style="padding:12px 16px;border-bottom:1px solid #eaeff5;display:flex;gap:10px;align-items:center;flex-wrap:wrap">
            <input v-model="search" placeholder="Search name, SKU or barcode" :style="inputStyle + 'width:260px;'" />
            <div style="display:flex;gap:4px">
              <div @click="onlyChosen = false" :style="chip(!onlyChosen)">All {{ rows.length }}</div>
              <div @click="onlyChosen = true" :style="chip(onlyChosen)">Chosen {{ chosen.length }}</div>
            </div>
            <div style="margin-left:auto;display:flex;align-items:center;gap:6px;font-size:12px;color:#5d6b7d">
              Set shown to
              <input v-model="bulk" type="number" min="0" :style="inputStyle + 'width:64px;height:30px;text-align:center;'" />
              <div @click="setShown" :style="smallBtn">Apply</div>
            </div>
          </div>

          <div :style="grid + 'padding:9px 16px;background:#f6f9fc;border-bottom:1px solid #dfe6ee;font-family:\'IBM Plex Mono\',monospace;font-size:9.5px;letter-spacing:0.07em;text-transform:uppercase;color:#5d6b7d'">
            <div>Product</div>
            <div>Barcode</div>
            <div style="text-align:right">Price</div>
            <div style="text-align:center">Labels</div>
          </div>
          <div v-if="loading" style="padding:16px;font-size:12.5px;color:#7d8b9c">Loading…</div>
          <div v-else-if="!shown.length" style="padding:16px;font-size:12.5px;color:#7d8b9c">{{ onlyChosen ? 'No products chosen yet. Type a number in Labels.' : 'No products found.' }}</div>
          <div style="max-height:calc(100vh - 330px);overflow-y:auto">
            <div v-for="r in shown" :key="r.key" :style="grid + 'padding:9px 16px;border-bottom:1px solid #f0f4f8;align-items:center;' + (qty[r.key] > 0 ? 'background:#f6faff;' : '')">
              <div style="min-width:0">
                <div style="font-size:13px;font-weight:500;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">{{ r.name }}</div>
                <div style="font-size:11px;color:#8b98a8">{{ r.category || 'No category' }} · SKU {{ r.sku }}</div>
              </div>
              <div style="min-width:0;font-family:'IBM Plex Mono',monospace;font-size:11.5px;color:#4a5866;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">
                {{ r.code }}<span v-if="!r.barcode" style="color:#8a5300;font-family:'Public Sans',sans-serif"> (SKU, no barcode)</span>
              </div>
              <div style="text-align:right;font-family:'IBM Plex Mono',monospace;font-size:12.5px">{{ priceText(r) }}</div>
              <div style="display:flex;align-items:center;justify-content:center;gap:4px">
                <div @click="change(r, -1)" :style="stepBtn">−</div>
                <input :value="qty[r.key] || ''" @input="setQty(r, ($event.target as HTMLInputElement).value)" type="number" min="0" placeholder="0" :style="inputStyle + 'width:56px;height:28px;text-align:center;padding:0 4px;'" />
                <div @click="change(r, 1)" :style="stepBtn">+</div>
              </div>
            </div>
          </div>
        </div>

        <!-- Label options, preview and print -->
        <div style="display:grid;gap:14px;position:sticky;top:16px">
          <div style="background:#ffffff;border:1px solid #dfe6ee;border-radius:12px;padding:16px 18px;display:grid;gap:14px">
            <div :style="labelStyle">Label size
              <div style="display:grid;gap:6px">
                <div v-for="s in SIZES" :key="s.key" @click="size = s.key" :style="segStyle(size === s.key)">
                  <div>{{ s.name }}</div>
                  <div style="font-size:11px;font-weight:400;opacity:.8">{{ s.hint }}</div>
                </div>
              </div>
            </div>
            <div :style="labelStyle">On the label
              <label style="display:flex;align-items:center;gap:8px;font-weight:400;font-size:12.5px;cursor:pointer"><input type="checkbox" v-model="show.name" style="accent-color:var(--c-primary)" /> Product name</label>
              <label style="display:flex;align-items:center;gap:8px;font-weight:400;font-size:12.5px;cursor:pointer"><input type="checkbox" v-model="show.price" style="accent-color:var(--c-primary)" /> Price</label>
              <label style="display:flex;align-items:center;gap:8px;font-weight:400;font-size:12.5px;cursor:pointer"><input type="checkbox" v-model="show.code" style="accent-color:var(--c-primary)" /> Number under the barcode</label>
            </div>
          </div>

          <div style="background:#ffffff;border:1px solid #dfe6ee;border-radius:12px;overflow:hidden">
            <div style="padding:12px 16px;border-bottom:1px solid #eaeff5;font-size:13px;font-weight:700">Preview</div>
            <iframe :srcdoc="previewHtml" title="Label preview" style="width:100%;height:240px;border:0;background:#eef2f6;display:block"></iframe>
          </div>

          <div style="background:#ffffff;border:1px solid #dfe6ee;border-radius:12px;padding:14px 16px;display:grid;gap:10px">
            <div style="display:flex;justify-content:space-between;font-size:12.5px;color:#5d6b7d">
              <span>{{ chosen.length }} products</span>
              <span><b style="color:#0f1c2b">{{ total }}</b> labels<span v-if="size === 'a4' && total"> · {{ Math.ceil(total / 21) }} A4 sheet{{ Math.ceil(total / 21) > 1 ? 's' : '' }}</span></span>
            </div>
            <div v-if="error" style="font-size:12px;color:#b02020;font-weight:600">{{ error }}</div>
            <div style="display:flex;gap:8px">
              <div @click="clearAll" style="height:38px;padding:0 14px;border-radius:9px;border:1px solid #dfe6ee;display:flex;align-items:center;font-size:13px;font-weight:600;color:#4a5866;cursor:pointer">Clear</div>
              <div @click="print" :style="'flex:1;height:38px;border-radius:9px;background:var(--c-primary);color:#ffffff;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:700;cursor:pointer;' + (total ? '' : 'opacity:.5;')">Print {{ total || '' }} labels</div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </template>
</template>

<script setup lang="ts">
// Products > Print labels: barcode labels with the product name and price.
// API: GET /api/products/labels (one row per product or variant). ?product=ID ticks that product.
import { computed, onMounted, reactive, ref } from 'vue';
import { useRoute } from 'vue-router';
import JsBarcode from 'jsbarcode';
import { usePageVm } from '../../erp/useErpPage';
import { api } from '../../api/http.js';

const vm = usePageVm();
const route = useRoute();

// Label sizes. "roll" prints one label per page (label printers); "a4" prints 21 labels per sheet.
const SIZES = [
  { key: 'roll50', name: 'Roll 50 × 25 mm', hint: 'Label printer, one label at a time', w: 50, h: 25 },
  { key: 'roll38', name: 'Roll 38 × 25 mm', hint: 'Label printer, small labels', w: 38, h: 25 },
  { key: 'a4', name: 'A4 sheet, 21 labels', hint: '3 × 7 labels of 63.5 × 38.1 mm', w: 63.5, h: 38.1 },
];

const grid = 'display:grid;grid-template-columns:1.8fr 1.2fr 0.7fr 130px;gap:12px;';
const labelStyle = 'display:grid;gap:7px;font-size:12px;font-weight:600';
const inputStyle = "height:34px;border:1px solid #dfe6ee;border-radius:8px;padding:0 10px;font-family:'Public Sans',sans-serif;font-size:12.5px;outline:none;background:#ffffff;box-sizing:border-box;";
const smallBtn = 'height:30px;padding:0 10px;border-radius:7px;border:1px solid #dfe6ee;display:flex;align-items:center;font-size:12px;font-weight:600;color:#4a5866;cursor:pointer';
const stepBtn = 'width:24px;height:28px;border-radius:6px;border:1px solid #dfe6ee;display:grid;place-items:center;font-size:14px;color:#4a5866;cursor:pointer;user-select:none';
const chip = (on: boolean) => 'height:30px;padding:0 11px;border-radius:99px;display:flex;align-items:center;font-size:12px;cursor:pointer;'
  + (on ? 'background:#0f1c2b;color:#ffffff;font-weight:600;' : 'border:1px solid #dfe6ee;color:#5d6b7d;');
const segStyle = (on: boolean) => 'padding:8px 11px;border-radius:8px;font-size:12.5px;cursor:pointer;'
  + (on ? 'background:var(--c-primary);color:#ffffff;font-weight:700;' : 'border:1px solid #dfe6ee;color:#4a5866;font-weight:500;');

// ---- Products ----

const rows = ref<any[]>([]);
const loading = ref(true);
const search = ref('');
const onlyChosen = ref(false);
const qty = reactive<Record<string, number>>({});
const bulk = ref('1');
const error = ref('');

const shown = computed(() => {
  const q = search.value.trim().toLowerCase();
  return rows.value.filter(r => {
    if (onlyChosen.value && !(qty[r.key] > 0)) return false;
    return !q || r.name.toLowerCase().includes(q) || String(r.sku || '').toLowerCase().includes(q) || String(r.barcode || '').toLowerCase().includes(q);
  });
});
const chosen = computed(() => rows.value.filter(r => qty[r.key] > 0));
const total = computed(() => chosen.value.reduce((sum, r) => sum + qty[r.key], 0));

onMounted(async () => {
  try {
    rows.value = await api.get('/products/labels');
  } catch (e) { /* the error bar shows why */ }
  loading.value = false;

  // From the Print barcode button of a product: one label for it (each variant), and show only it.
  const productId = Number(route.query.product);
  if (productId) {
    rows.value.filter(r => r.product_id === productId).forEach(r => { qty[r.key] = 1; });
    onlyChosen.value = true;
  }
});

function setQty(r: any, value: string) {
  const n = Math.floor(Number(value));
  qty[r.key] = n > 0 ? Math.min(n, 999) : 0;
}

function change(r: any, by: number) {
  setQty(r, String((qty[r.key] || 0) + by));
}

function setShown() {
  shown.value.forEach(r => setQty(r, bulk.value));
}

function clearAll() {
  Object.keys(qty).forEach(k => { qty[k] = 0; });
  onlyChosen.value = false;
  error.value = '';
}

function priceText(r: any) {
  return '$' + Number(r.price).toFixed(2) + (r.unit === 'kg' ? '/kg' : '');
}

// ---- Labels ----

const show = reactive({ name: true, price: true, code: true });
const size = ref('roll50');

function esc(text: string) {
  return String(text).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

// The barcode as an SVG: EAN-13 when the product is EAN-13 and the number is a good one, otherwise Code 128
// (Code 128 can hold any SKU; a QR product also prints as Code 128).
function barcodeSvg(code: string, barcodeType: string) {
  const draw = (format: string) => {
    const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
    let ok = true;
    // marginLeft/Right 20 = 10 bar widths of blank space each side, which scanners need.
    JsBarcode(svg, code, { format, displayValue: false, margin: 0, marginLeft: 20, marginRight: 20, height: 60, width: 2, valid: (v: boolean) => { ok = v; } });
    return ok ? svg : null;
  };
  const svg = (barcodeType === 'EAN-13' && /^\d{12,13}$/.test(code) && draw('EAN13')) || draw('CODE128');
  if (!svg) return '';
  // Stretch the bars to the label width.
  const w = svg.getAttribute('width'), h = svg.getAttribute('height');
  svg.setAttribute('viewBox', '0 0 ' + parseFloat(w || '0') + ' ' + parseFloat(h || '0'));
  svg.setAttribute('preserveAspectRatio', 'none');
  svg.removeAttribute('width');
  svg.removeAttribute('height');
  svg.removeAttribute('style');
  svg.setAttribute('class', 'bars');
  return svg.outerHTML;
}

function labelHtml(r: any, svgs: Record<string, string>) {
  return '<div class="label">'
    + (show.name ? '<div class="name">' + esc(r.name) + '</div>' : '')
    + svgs[r.key]
    + (show.code ? '<div class="code">' + esc(r.code) + '</div>' : '')
    + (show.price ? '<div class="price">' + esc(priceText(r)) + '</div>' : '')
    + '</div>';
}

// The whole page to print. `copies` decides how many labels per product; the preview sends 1 each.
function pageHtml(list: { row: any, copies: number }[], preview: boolean) {
  const s = SIZES.find(x => x.key === size.value)!;
  const a4 = s.key === 'a4';
  const svgs: Record<string, string> = {};
  list.forEach(({ row }) => { svgs[row.key] = barcodeSvg(String(row.code), row.barcode_type); });

  const labels: string[] = [];
  list.forEach(({ row, copies }) => { for (let i = 0; i < copies; i++) labels.push(labelHtml(row, svgs)); });

  let body: string;
  if (a4) {
    const sheets: string[] = [];
    for (let i = 0; i < labels.length; i += 21) sheets.push('<div class="sheet">' + labels.slice(i, i + 21).join('') + '</div>');
    body = sheets.join('');
  } else {
    body = labels.map(l => '<div class="page">' + l + '</div>').join('');
  }

  const big = a4 ? 1.25 : (s.w < 45 ? 0.85 : 1); // text size for the label size
  const css = `
    @page { size: ${a4 ? 'A4' : s.w + 'mm ' + s.h + 'mm'}; margin: 0; }
    * { box-sizing: border-box; }
    body { margin: 0; font-family: Arial, Helvetica, sans-serif; color: #000; }
    .label { width: ${s.w}mm; height: ${s.h}mm; padding: 1.5mm 2.5mm; overflow: hidden;
      display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 0.6mm; text-align: center; }
    .name { font-size: ${(2.7 * big).toFixed(2)}mm; font-weight: bold; line-height: 1.15; max-width: 100%;
      display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
    .bars { width: 100%; height: ${(a4 ? 14 : 8.5)}mm; flex: none; }
    .code { font-size: ${(2.3 * big).toFixed(2)}mm; letter-spacing: 0.3mm; line-height: 1; }
    .price { font-size: ${(3.8 * big).toFixed(2)}mm; font-weight: bold; line-height: 1; }
    ${a4
      // 21 labels fill the sheet exactly; fewer labels sit in the middle of the page.
      ? `.sheet { width: 210mm; height: 297mm; padding: 15.15mm 7.25mm; overflow: hidden;
          display: flex; flex-wrap: wrap; justify-content: center; align-content: center; column-gap: 2.5mm; }
         .sheet + .sheet { break-before: page; page-break-before: always; }`
      // One label per page, in the middle of the page (also when the paper is bigger than the label).
      : `.page { width: ${s.w}mm; height: ${s.h}mm; display: flex; align-items: center; justify-content: center; overflow: hidden; }
         .page + .page { break-before: page; page-break-before: always; }`}
    ${preview
      ? `body { background: #eef2f6; padding: 12px; display: flex; flex-wrap: wrap; gap: 10px; align-content: flex-start; }
         .label { background: #fff; outline: 1px dashed #b8c4d2; }
         .sheet, .page { display: contents; }
         .empty { font: 12.5px Arial, sans-serif; color: #7d8b9c; }`
      : ''}
  `;
  return '<!doctype html><html><head><meta charset="utf-8"><title>Labels</title><style>' + css + '</style></head><body>'
    + (body || (preview ? '<div class="empty">Type a number in Labels to see the labels here.</div>' : '')) + '</body></html>';
}

// One of each chosen product (the first 6), at its real size.
const previewHtml = computed(() => pageHtml(chosen.value.slice(0, 6).map(row => ({ row, copies: 1 })), true));

// Prints through a hidden frame, so only the labels print (not the menu and the page).
function print() {
  error.value = '';
  if (!total.value) { error.value = 'Type how many labels you want for at least one product.'; return; }
  const html = pageHtml(chosen.value.map(row => ({ row, copies: qty[row.key] })), false);

  const frame = document.createElement('iframe');
  // The frame has the paper's real size (off screen), so the browser lays the labels out on the right page size.
  const s = SIZES.find(x => x.key === size.value)!;
  const paper = s.key === 'a4' ? 'width:210mm;height:297mm;' : 'width:' + s.w + 'mm;height:' + s.h + 'mm;';
  frame.setAttribute('style', 'position:fixed;left:-10000px;top:0;border:0;' + paper);
  document.body.appendChild(frame);
  const doc = frame.contentWindow!.document;
  doc.open();
  doc.write(html);
  doc.close();
  const win = frame.contentWindow!;
  win.onafterprint = () => frame.remove();
  setTimeout(() => {
    win.focus();
    win.print();
  }, 200);
  setTimeout(() => frame.remove(), 120000); // in case the browser never says printing is done
}
</script>
