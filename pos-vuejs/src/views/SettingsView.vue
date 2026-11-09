<template>
  <div style="height:100%;display:flex">
    <div style="width:240px;flex:none;background:#ffffff;border-right:1px solid #dfe6ee;display:flex;flex-direction:column;min-height:0">
      <div style="padding:16px 14px 10px">
        <div style="font-size:15px;font-weight:700;margin-bottom:10px">Settings</div>
        <div style="position:relative"><div style="position:absolute;left:10px;top:50%;transform:translateY(-50%);color:#9aa7b6;font-size:12px">⚲</div> <input :value="vm.se_q" @input="vm.se_onQ" placeholder="Search all settings" style="width:100%;height:32px;border:1px solid #dfe6ee;border-radius:8px;background:#f7fafc;padding:0 10px 0 28px;font-family:'Public Sans',sans-serif;font-size:12.5px;outline:none" v-pseudo:focus="'border-color:var(--c-primary);background:#ffffff'" /></div>
      </div>
      <div style="flex:1;overflow-y:auto;padding:4px 8px 16px">
        <template v-for="(g, $index) in vm.se_groups" :key="$index">
          <div @click="g.onClick" :style="g.style" v-pseudo:hover="'background:#f2f6fa'"><div :style="g.iconStyle">{{ g.icon }}</div> <div style="flex:1;min-width:0;font-size:13px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">{{ g.name }}</div> <span :style="g.dirtyStyle">{{ g.dirty }}</span></div>
        </template>
      </div>
    </div>

    <div style="flex:1;min-width:0;display:flex;flex-direction:column;min-height:0">
      <div style="flex:1;overflow-y:auto;min-height:0">
        <div style="padding:18px 28px 14px;background:#ffffff;border-bottom:1px solid #dfe6ee">
          <div style="display:flex;align-items:center;gap:7px;font-size:11.5px;color:#7d8b9c;margin-bottom:8px"><span>Settings</span><span style="color:#c2cedb">/</span><span style="color:#0f1c2b;font-weight:500">{{ vm.se_title }}</span></div>
          <h2 style="margin:0 0 3px;font-size:22px;font-weight:700;letter-spacing:-0.018em">{{ vm.se_title }}</h2>
          <div style="font-size:12.5px;color:#5d6b7d">{{ vm.se_desc }}</div>
        </div>
        <div :style="vm.se_layout">
          <div style="display:grid;gap:14px;min-width:0">
            <template v-for="(sec, $index) in vm.se_sections" :key="$index">
              <div style="background:#ffffff;border:1px solid #dfe6ee;border-radius:12px;overflow:hidden">
                <div style="padding:13px 18px;display:flex;align-items:center;gap:10px">
                  <div style="font-size:13.5px;font-weight:700;flex:1">{{ sec.t }}</div>
                  <template v-if="sec.hasTable">
                    <div @click="sec.addRow" style="font-size:12.5px;font-weight:600;color:var(--c-primary);cursor:pointer">+ {{ sec.addLabel }}</div>
                  </template>
                </div>
                <template v-if="sec.hasTable">
                  <div>
                    <div :style="sec.headStyle">
                      <template v-for="(c, $index) in sec.cols" :key="$index">
                        <div>{{ c.h }}</div>
                      </template>
                      <div>Active</div>
                      <div></div>
                    </div>
                    <template v-for="(r, $index) in sec.rows" :key="$index">
                      <div :style="r.style"><template v-for="(c, $index) in r.cells" :key="$index"><input :value="c.v" @input="c.onInput" :placeholder="c.ph" :style="c.style" /></template> <div @click="r.onToggle" style="cursor:pointer;display:flex;align-items:center">
                        <div :style="r.sw">
                          <div :style="r.knob"></div>
                        </div>
                      </div> <div @click="r.onDelete" title="Remove" style="width:28px;height:28px;border-radius:6px;display:grid;place-items:center;color:#b3c0cf;cursor:pointer;font-size:15px" v-pseudo:hover="'background:#fdeaea;color:#b02020'">×</div></div>
                    </template>
                  </div>
                </template>
                <template v-for="(f, $index) in sec.fields" :key="$index">
                  <div style="display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1.25fr);gap:22px;padding:13px 18px;border-top:1px solid #f0f4f8;align-items:start">
                    <div style="min-width:0;padding-top:2px">
                      <div style="display:flex;align-items:center;gap:7px"><div style="font-size:13px;font-weight:600">{{ f.label }}</div> <span :style="f.dirtyDot"></span> <span :style="f.groupTag">{{ f.group }}</span></div>
                      <div :style="f.helpStyle">{{ f.help }}</div>
                    </div>
                    <div style="min-width:0"><template v-if="f.isText"><input :value="f.v" @input="f.onInput" :style="f.inputStyle" /></template> <template v-if="f.isArea"><textarea :value="f.v" @input="f.onInput" style="width:100%;min-height:64px;border:1px solid #dfe6ee;border-radius:8px;padding:9px 11px;font-family:'Public Sans',sans-serif;font-size:13px;outline:none;resize:vertical" v-pseudo:focus="'border-color:var(--c-primary)'"></textarea></template> <template v-if="f.isSelect"><select :value="f.v" @change="f.onInput" style="width:100%;height:36px;border:1px solid #dfe6ee;border-radius:8px;padding:0 11px;font-family:'Public Sans',sans-serif;font-size:13px;outline:none;background:#ffffff;cursor:pointer;padding:0 8px">
                      <template v-for="(o, $index) in f.opts" :key="$index">
                        <option :value="o.v">{{ o.v }}</option>
                      </template>
                    </select></template> <template v-if="f.isToggle">
                      <div @click="f.onToggle" style="display:flex;align-items:center;gap:10px;cursor:pointer;height:36px">
                        <div :style="f.sw">
                          <div :style="f.knob"></div>
                        </div>
                        <div style="font-size:12.5px;color:#4a5866">{{ f.toggleLabel }}</div>
                      </div>
                    </template> <template v-if="f.isSeg">
                      <div style="display:inline-flex;gap:3px;background:#eef2f6;padding:3px;border-radius:9px;flex-wrap:wrap">
                        <template v-for="(o, $index) in f.segs" :key="$index">
                          <div @click="o.onClick" :style="o.style">{{ o.v }}</div>
                        </template>
                      </div>
                    </template> <template v-if="f.isUpload">
                      <div style="display:flex;align-items:center;gap:12px">
                        <div v-if="f.logoUrl" style="width:64px;height:64px;border-radius:12px;border:1px solid #dfe6ee;background:#ffffff;display:grid;place-items:center;overflow:hidden"><img :src="f.logoUrl" alt="Logo" style="max-width:100%;max-height:100%;object-fit:contain" /></div>
                        <div v-else style="width:64px;height:64px;border-radius:12px;background:repeating-linear-gradient(135deg,#f2f5f9 0 6px,#e8edf3 6px 12px);display:grid;place-items:center;font-family:'IBM Plex Mono',monospace;font-size:9px;color:#7d8b9c;text-align:center">{{ f.uploadText }}</div>
                        <div @click="f.onUpload" style="height:32px;padding:0 12px;border-radius:8px;border:1px solid #dfe6ee;display:flex;align-items:center;font-size:12.5px;font-weight:600;color:var(--c-primary-text);cursor:pointer" v-pseudo:hover="'background:#f2f6fa'">{{ f.uploadBtn }}</div>
                        <div v-if="f.logoUrl" @click="f.onRemove" style="height:32px;padding:0 12px;border-radius:8px;border:1px solid #f3cfcf;background:#fdf4f4;display:flex;align-items:center;font-size:12.5px;font-weight:600;color:#b02020;cursor:pointer">Remove</div>
                      </div>
                    </template> <template v-if="f.isAction">
                      <div @click="f.onAction" :style="f.actionStyle">{{ f.actionLabel }}</div>
                    </template> <div :style="f.errStyle">{{ f.err }}</div></div>
                  </div>
                </template>
              </div>
            </template>
            <template v-if="vm.se_noResults">
              <div style="background:#ffffff;border:1px solid #dfe6ee;border-radius:12px;padding:36px 20px;text-align:center;font-size:12.5px;color:#7d8b9c">No settings match that search.</div>
            </template>
          </div>
          <template v-if="vm.se_hasPreview">
            <div style="position:sticky;top:18px;align-self:start">
              <div style="font-family:'IBM Plex Mono',monospace;font-size:9.5px;letter-spacing:0.07em;text-transform:uppercase;color:#5d6b7d;margin-bottom:8px">Live preview · {{ vm.se_pv.paper }}</div>
              <div :style="vm.se_pv.box">
                <template v-if="vm.se_pv.showLogo">
                  <img :src="vm.se_pv.logoUrl" alt="Logo" style="display:block;max-width:120px;max-height:56px;object-fit:contain;margin:0 auto 8px" />
                </template>
                <div style="text-align:center;font-size:14px;font-weight:700">{{ vm.se_pv.biz }}</div>
                <div style="text-align:center;font-size:11px;color:#5d6b7d;white-space:pre-line;margin-top:2px">{{ vm.se_pv.addr }}</div>
                <div :style="vm.se_pv.abnStyle">ABN {{ vm.se_pv.abn }}</div>
                <div style="border-top:1px dashed #c2cedb;margin:12px 0 10px"></div>
                <div style="display:flex;font-size:11.5px;font-family:'IBM Plex Mono',monospace">
                  <div style="flex:1;font-weight:600">{{ vm.se_pv.title }}</div>
                  <div>{{ vm.se_pv.no }}</div>
                </div>
                <div style="display:flex;font-size:11px;color:#5d6b7d;font-family:'IBM Plex Mono',monospace;margin-top:2px">
                  <div style="flex:1">{{ vm.se_pv.date }}</div>
                  <div>{{ vm.se_pv.terms }}</div>
                </div>
                <div style="display:grid;gap:5px;margin:12px 0;font-size:12px">
                  <div style="display:flex">
                    <div style="flex:1">Lamb Chops 1.25 kg</div>
                    <div style="font-family:'IBM Plex Mono',monospace">{{ vm.se_pv.l1 }}</div>
                  </div>
                  <div style="display:flex">
                    <div style="flex:1">Beef Mince 2.00 kg</div>
                    <div style="font-family:'IBM Plex Mono',monospace">{{ vm.se_pv.l2 }}</div>
                  </div>
                  <div style="display:flex">
                    <div style="flex:1">Spice Mix 200g ×2</div>
                    <div style="font-family:'IBM Plex Mono',monospace">{{ vm.se_pv.l3 }}</div>
                  </div>
                </div>
                <div style="border-top:1px solid #e4eaf1;padding-top:8px;display:grid;gap:4px;font-size:12px">
                  <div style="display:flex;color:#5d6b7d">
                    <div style="flex:1">{{ vm.se_pv.taxLabel }}</div>
                    <div style="font-family:'IBM Plex Mono',monospace">{{ vm.se_pv.tax }}</div>
                  </div>
                  <div style="display:flex;font-weight:700;font-size:14px">
                    <div style="flex:1">Total</div>
                    <div style="font-family:'IBM Plex Mono',monospace">{{ vm.se_pv.total }}</div>
                  </div>
                </div>
                <div style="border-top:1px dashed #c2cedb;margin:12px 0 8px"></div>
                <div style="text-align:center;font-size:11px;color:#5d6b7d;white-space:pre-line;text-wrap:pretty">{{ vm.se_pv.footer }}</div>
              </div>
            </div>
          </template>
        </div>
      </div>
      <template v-if="vm.se_dirty">
        <div style="flex:none;background:#0f1c2b;color:#ffffff;display:flex;align-items:center;gap:12px;padding:12px 24px">
          <div style="width:8px;height:8px;border-radius:50%;background:#f59e0b"></div>
          <div style="font-size:13px;font-weight:600;flex:1">{{ vm.se_dirtyText }}</div>
          <div @click="vm.se_discard" style="height:36px;padding:0 14px;border-radius:8px;border:1px solid rgba(255,255,255,.25);display:flex;align-items:center;font-size:12.5px;font-weight:600;cursor:pointer" v-pseudo:hover="'background:rgba(255,255,255,.08)'">Discard</div>
          <div @click="vm.se_save" :style="vm.se_saveStyle">Save changes</div>
        </div>
      </template>
    </div>

    <!-- overlays -->
    <template v-if="vm.se_confirmOpen">
      <div @click="vm.se_closeConfirm" style="position:absolute;inset:0;background:rgba(9,22,35,.48);z-index:40"></div>
      <div style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);width:420px;background:#ffffff;border-radius:16px;z-index:41;box-shadow:0 30px 80px -24px rgba(9,22,35,.65);padding:22px">
        <div style="font-size:17px;font-weight:700;margin-bottom:8px">{{ vm.se_cf.title }}</div>
        <div style="font-size:13px;line-height:1.6;color:#5d6b7d;margin-bottom:20px;text-wrap:pretty">{{ vm.se_cf.body }}</div>
        <div style="display:flex;gap:9px;justify-content:flex-end">
          <div @click="vm.se_closeConfirm" style="height:40px;padding:0 16px;border-radius:9px;border:1px solid #dfe6ee;display:flex;align-items:center;font-size:13px;font-weight:600;color:#4a5866;cursor:pointer">Cancel</div>
          <div @click="vm.se_cf.go" style="height:40px;padding:0 16px;border-radius:9px;background:#c62828;color:#ffffff;display:flex;align-items:center;font-size:13px;font-weight:700;cursor:pointer">{{ vm.se_cf.btn }}</div>
        </div>
      </div>
    </template>

    <ToastStack :toasts="vm.se_toasts" :z-index="50" :bottom="76" />
  </div>
</template>

<script setup lang="ts">
import { onMounted, onUnmounted, watch } from 'vue';
import ToastStack from '../components/ToastStack.vue';
import { useErpPage, useScreen } from '../erp/useErpPage';
import { SettingsLogic } from '../erp/logic/modules/settings.js';
import { api } from '../api/http.js';
import { branches, loadBranches } from '../api/branches.js';

const vm = useErpPage('settings', 'se_nav');
const screen = useScreen('se');

// ---------------------------------------------------------------------------
// API — every Settings page is saved in the POS backend (pos/routes/api.php):
//   pages saved in one row: GET/PUT /api/settings/<page>
//   lists: GET/POST/PUT/DELETE /api/branches, /api/settings/tax-rates, /api/settings/payment-methods
// The screen edits values locally; "Save changes" sends only what changed.
// "Appearance > Theme" is not here: it is saved on this device.
// ---------------------------------------------------------------------------

// Screen field -> API column, for each page.
const PAGES: { url: string; fields: Record<string, string> }[] = [
  // The logo is not here: it is a file, uploaded on its own (see pickLogo in settings.js).
  { url: '/settings/business', fields: { bizName: 'business_name', legal: 'legal_name', abn: 'abn', phone: 'phone', email: 'email', addr: 'address', brand: 'brand_colour' } },
  { url: '/settings/stock', fields: { defLoc: 'default_branch_id', perLoc: 'track_stock_per_branch' } },
  { url: '/settings/tax', fields: { gstReg: 'gst_registered', priceInc: 'prices_include_tax', bas: 'bas_period', taxRound: 'tax_rounding' } },
  { url: '/settings/formats', fields: { cur: 'currency', symPos: 'symbol_position', dec: 'decimal_places', dateFmt: 'date_format', h24: 'time_24h', week: 'week_starts_on', weight: 'weight_unit' } },
  { url: '/settings/payments', fields: { surch: 'card_surcharge', round5: 'round_cash_5c', split: 'allow_split_payments' } },
  { url: '/settings/invoice', fields: { invPrefix: 'invoice_prefix', invNext: 'next_invoice_number', rcpPrefix: 'receipt_prefix', paper: 'paper', showLogo: 'show_logo', showAbn: 'show_abn', terms: 'payment_terms', footer: 'footer_text' } },
  { url: '/settings/products', fields: { skuAuto: 'auto_sku', skuPrefix: 'sku_prefix', unit: 'default_unit', lowAlert: 'low_stock_alert', barcode: 'barcode_type', expiry: 'track_expiry', batch: 'track_batches' } },
  { url: '/settings/pos', fields: { defCust: 'default_customer', belowStock: 'allow_below_stock', maxDisc: 'max_discount_percent', refundPin: 'refund_needs_pin', scale: 'use_scale', searchKp: 'search_keypad', requireReg: 'require_register', printRcp: 'print_receipt', holdExp: 'clear_held_sales_after' } },
  { url: '/settings/coins', fields: { coinOn: 'enabled', coinEarn: 'earn_per_dollar', coinValue: 'coin_value', coinMin: 'min_redeem', coinMax: 'max_redeem_percent' } },
  { url: '/settings/purchases', fields: { poPrefix: 'po_prefix', approve: 'approval_over', poTerms: 'default_supplier_terms', partial: 'allow_partial_receiving', costing: 'cost_method' } },
  { url: '/settings/notifications', fields: { nEmail: 'alert_email', nLow: 'low_stock', nDaily: 'daily_summary', nBills: 'bills_due', nBook: 'booking_reminder', nLead: 'reminder_lead', nReady: 'order_ready' } },
  { url: '/settings/security', fields: { timeout: 'session_timeout', twofa: 'two_step_admins', posPin: 'pos_pin_sign_in' } },
  { url: '/settings/backup', fields: { backup: 'daily_backup', backupAt: 'backup_time' } }
];

// API value -> screen value. Toggles stay true/false; everything else is shown as text.
function toScreen(column: string, value: any) {
  if (column === 'default_branch_id') return branches.value.find(b => b.id === value)?.name || '';
  if (typeof value === 'boolean') return value;
  return value === null || value === undefined ? '' : String(value);
}

// Screen value -> API value. An empty box is sent as null.
function toApi(column: string, value: any) {
  if (column === 'default_branch_id') return branches.value.find(b => b.name === value)?.id ?? null;
  return value === '' ? null : value;
}

let loadedVals: Record<string, any> = {}; // page values as last loaded from the API

async function loadPages() {
  const vals: Record<string, any> = {};
  for (const page of PAGES) {
    const row = await api.get(page.url);
    for (const key in page.fields) vals[key] = toScreen(page.fields[key], row[page.fields[key]]);
    if (page.url === '/settings/business') vals.logo = row.logo_url || ''; // image address for the logo box
  }
  loadedVals = vals;
  const s = screen.state;
  screen.setState({ vals: { ...s.vals, ...vals }, saved: { ...s.saved, ...vals } });
}

function pagesChanged(saved: Record<string, any>) {
  return PAGES.some(page => Object.keys(page.fields).some(key => saved[key] !== loadedVals[key]));
}

// After "Save changes": PUT every page that has a changed field.
async function savePages(saved: Record<string, any>) {
  for (const page of PAGES) {
    if (!Object.keys(page.fields).some(key => saved[key] !== loadedVals[key])) continue;
    const body: Record<string, any> = {};
    for (const key in page.fields) body[page.fields[key]] = toApi(page.fields[key], saved[key]);
    await api.put(page.url, body).catch(() => {}); // a refused value shows in the error bar
  }
  await loadPages(); // what the API now has
}

// ---- Lists: Branches, Tax rates, Payment methods ----

type Row = { id?: number; on: boolean; [col: string]: any };

const LISTS: Record<string, { url: string; toRow: (x: any) => Row; toBody: (r: Row) => any }> = {
  locs: {
    url: '/branches',
    toRow: b => ({ id: b.id, name: b.name, addr: b.address || '', code: b.invoice_prefix || '', on: b.active }),
    toBody: r => ({ name: r.name.trim(), address: r.addr || null, invoice_prefix: r.code || null, active: r.on })
  },
  rates: {
    url: '/settings/tax-rates',
    toRow: t => ({ id: t.id, name: t.name, rate: String(t.rate), code: t.code || '', on: t.active }),
    toBody: r => ({ name: r.name.trim(), rate: r.rate === '' ? 0 : r.rate, code: r.code || null, active: r.on })
  },
  pays: {
    url: '/settings/payment-methods',
    toRow: m => ({ id: m.id, name: m.name, acct: m.pays_into || '', on: m.active }),
    toBody: r => ({ name: r.name.trim(), pays_into: r.acct || null, active: r.on })
  }
};

const loadedRows: Record<string, string> = { locs: '[]', rates: '[]', pays: '[]' }; // rows as last loaded (JSON)

async function loadList(key: string) {
  const list = LISTS[key];
  const data = key === 'locs' ? await loadBranches() : await api.get(list.url);
  const rows = data.map(list.toRow);
  loadedRows[key] = JSON.stringify(rows);
  const s = screen.state;
  screen.setState({ tables: { ...s.tables, [key]: rows }, savedTables: { ...s.savedTables, [key]: rows } });
  if (key === 'locs') showBranchChoices();
}

// After "Save changes": create new rows, update changed ones, delete removed ones.
async function saveList(key: string, rows: Row[]) {
  const list = LISTS[key];
  const before: Row[] = JSON.parse(loadedRows[key]);
  for (const row of rows) {
    const old = before.find(b => b.id === row.id);
    if (!row.id) await api.post(list.url, list.toBody(row)).catch(() => {});
    else if (old && JSON.stringify(old) !== JSON.stringify(row)) await api.put(list.url + '/' + row.id, list.toBody(row)).catch(() => {});
  }
  for (const old of before) {
    if (!rows.some(r => r.id === old.id)) await api.del(list.url + '/' + old.id).catch(() => {});
  }
  await loadList(key); // what the API now has (new ids, or a refused delete back in the table)
}

// ---- Small helpers for fields defined in erp/logic/modules/settings.js ----

function field(key: string) {
  return (SettingsLogic as any).data.G.flatMap((g: any) => g.sections).flatMap((sec: any) => sec.f || []).find((f: any) => f.k === key);
}

// "Default location" choices = the active branches.
function showBranchChoices() {
  const current = screen.state.vals.defLoc; // kept in the list even if that branch was switched off
  field('defLoc').opts = branches.value.filter(b => b.active || b.name === current).map(b => b.name);
  screen.refresh();
}

// "Back up now" help text = the last backup request.
async function showLastBackup() {
  const list = await api.get('/settings/backups');
  const last = list[0];
  field('backupNow').help = last
    ? 'Last request ' + new Date(last.created_at).toLocaleString('en-AU') + ' by ' + (last.requested_by || '—') + ' · ' + last.status
    : 'No backups yet';
  screen.refresh();
}

const stops: (() => void)[] = [];
onMounted(async () => {
  try {
    await loadList('locs'); // first: "Default location" needs the branch names
    await loadList('rates');
    await loadList('pays');
    await loadPages();
    await showLastBackup();
  } catch (e) {
    return; // the error bar shows why
  }
  // "Save changes" in settings.js copies vals to saved; send what changed.
  stops.push(watch(() => screen.state.saved, saved => { if (pagesChanged(saved)) savePages(saved); }));
  for (const key of Object.keys(LISTS)) {
    stops.push(watch(() => screen.state.savedTables[key], rows => { if (JSON.stringify(rows) !== loadedRows[key]) saveList(key, rows); }));
  }
});
onUnmounted(() => stops.forEach(stop => stop()));
</script>
