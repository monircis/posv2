// SettingsLogic
import { ModBase } from "../../runtime/logic.js";
import { applyTheme, savedTheme, THEME_NAMES } from "../../theme.js";
import { api } from "../../../api/http.js";

export const SettingsLogic = (() => {
const NAV = [
  ['Home','Hm',0],['User Management','Us',1],['Contacts','Ct',1],['Products','Pr',1],
  ['Manufacturing','Mf',0],['Purchases','Pu',1],['Sell','Sl',1],['Stock Transfers','St',0],
  ['Stock Adjustment','Sa',0],['Expenses','Ex',1],['Payment Accounts','Pa',1],['Accounting','Ac',1],
  ['Reports','Rp',1],['Bookings','Bk',0],['Kitchen','Kt',0],['Orders','Or',0],
  ['Settings','Se',1],['HRM','Hr',1]
];
const TONE = { info:['var(--c-primary-soft)','var(--c-primary-text)','var(--c-primary)'], warn:['#fdf1dd','#8a5300','#d98c1a'], error:['#fdeaea','#b02020','#d93a3a'], success:['#e7f6ee','#0f6b43','#2f9e63'], neutral:['#eef2f6','#5d6b7d','#8fa3b6'] };
const MONO = "font-family:'IBM Plex Mono',monospace;";
const BRAND = { Blue:'#1668c9', Green:'#2f9e63', Maroon:'#8e2a3b', Black:'#0f1c2b' };
const F = (k, label, type, def, a, b) => ({ k, label, type, def, opts: Array.isArray(a) ? a : null, help: Array.isArray(a) ? (b || '') : (a || ''), check: null });
const G = [
  { id:'business', name:'Business', icon:'Bu', desc:'Name, contact details and branding printed on invoices and receipts', sections:[
    { t:'Business details', f:[F('bizName','Business name','text','Madinah Butchery'), F('legal','Legal name','text','Madinah Halal Meats Pty Ltd'), F('abn','ABN','text','51 824 753 556','11 digits, printed on tax invoices'), F('phone','Phone','text','02 9759 4410'), F('email','Accounts email','text','accounts@madinahbutchery.com.au','Replies to emailed invoices go here'), F('addr','Address','area','118 Haldon St\nLakemba NSW 2195')] },
    { t:'Branding', f:[F('logo','Logo','upload','','PNG, JPG or WebP, at least 256px, up to 2 MB. Saved as soon as it is uploaded.'), F('brand','Brand colour','seg','Blue',['Blue','Green','Maroon','Black'],'Used on receipts and the customer display')] } ] },
  { id:'appearance', name:'Appearance', icon:'Ap', desc:'Theme colour of the app on this device', sections:[
    { t:'Theme', f:[F('theme','Theme colour','seg',savedTheme(),THEME_NAMES,'Buttons, links, highlights and the sidebar. Applied when you save.')] } ] },
  { id:'locations', name:'Branches & locations', icon:'Lo', desc:'Shops and stores that hold stock and take sales', sections:[
    { t:'Locations', table:{ key:'locs', add:'Add location', cols:[['name','Name','1.2fr','Location name'],['addr','Address','2fr','Street, suburb'],['code','Invoice prefix','0.7fr','XX-']] } },
    { t:'Defaults', f:[F('defLoc','Default location','select','Main branch',['Main branch','Bankstown shop','Cold store'],'Pre-selected on new sales and purchases'), F('perLoc','Track stock per location','toggle',true,'Off means one shared stock count')] } ] },
  { id:'tax', name:'Tax', icon:'Tx', desc:'GST registration, rates and how tax is calculated', sections:[
    { t:'GST', f:[F('gstReg','Registered for GST','toggle',true), F('priceInc','Prices include tax','toggle',true,'Shelf and menu prices already include GST'), F('bas','BAS lodgement','seg','Quarterly',['Monthly','Quarterly','Annually']), F('taxRound','Round tax','select','Per line',['Per line','Per invoice'])] },
    { t:'Tax rates', table:{ key:'rates', add:'Add rate', cols:[['name','Name','1.6fr','Rate name'],['rate','Rate %','0.6fr','0'],['code','Code','0.6fr','CODE']] } } ] },
  { id:'currency', name:'Currency & formats', icon:'Cu', desc:'Money, dates and units across the app', sections:[
    { t:'Formats', f:[F('cur','Currency','select','AUD \u00b7 Australian dollar',['AUD \u00b7 Australian dollar','NZD \u00b7 New Zealand dollar','USD \u00b7 US dollar','GBP \u00b7 British pound']), F('symPos','Symbol position','seg','Before',['Before','After']), F('dec','Decimal places','seg','2',['0','2','3']), F('dateFmt','Date format','select','DD/MM/YYYY',['DD/MM/YYYY','MM/DD/YYYY','YYYY-MM-DD']), F('h24','24-hour time','toggle',true), F('week','Week starts on','seg','Monday',['Monday','Sunday']), F('weight','Weight unit','seg','kg',['kg','lb'])] } ] },
  { id:'payments', name:'Payment methods', icon:'Pm', desc:'Tenders shown on the POS and the account each one pays into', sections:[
    { t:'Methods', table:{ key:'pays', add:'Add method', cols:[['name','Method','1.3fr','Method name'],['acct','Pays into','1.3fr','Payment account']] } },
    { t:'Rules', f:[F('surch','Card surcharge %','text','0','Leave 0 to absorb card fees'), F('round5','Round cash to 5c','toggle',true), F('split','Allow split payments','toggle',true)] } ] },
  { id:'invoice', name:'Invoice & receipt', icon:'In', desc:'Numbering, layout and wording on invoices and receipts', preview:true, sections:[
    { t:'Numbering', f:[F('invPrefix','Invoice prefix','text','INV-'), F('invNext','Next invoice number','text','3311','Must be higher than the last issued (3310)'), F('rcpPrefix','Receipt prefix','text','R-')] },
    { t:'Layout', f:[F('paper','Paper','seg','80mm roll',['80mm roll','A4']), F('showLogo','Show logo','toggle',true), F('showAbn','Show ABN','toggle',true), F('terms','Payment terms','select','Due on receipt',['Due on receipt','7 days','14 days','30 days']), F('footer','Footer text','area','Thank you for shopping with us.\nAll meat is certified halal.')] } ] },
  { id:'products', name:'Products', icon:'Pr', desc:'Defaults for new products and stock alerts', sections:[
    { t:'Defaults', f:[F('skuAuto','Generate SKU automatically','toggle',true), F('skuPrefix','SKU prefix','text','66'), F('unit','Default unit','select','kg',['kg','each','pack','box']), F('lowAlert','Default low-stock alert','text','10','Used when a product has no alert set'), F('barcode','Barcode type','select','EAN-13',['EAN-13','Code 128','QR'])] },
    { t:'Tracking', f:[F('expiry','Track expiry dates','toggle',true,'Shows days to expiry on stock and adjustments'), F('batch','Track batches','toggle',false)] } ] },
  { id:'sales', name:'Sales & POS', icon:'Sa', desc:'How the register behaves at the counter', sections:[
    { t:'Register', f:[F('defCust','Default customer','select','Walk-in customer',['Walk-in customer','Ask every sale']), F('belowStock','Allow selling below stock','toggle',false,'Off blocks the sale when stock would go negative'), F('maxDisc','Max discount without manager %','text','15'), F('refundPin','Manager PIN for refunds','toggle',true), F('scale','Weigh items from scale','toggle',true,'Connected scale at Register 1'), F('searchKp','On-screen keypad for product search','toggle',true,'Shows a keypad button next to the POS search box, for touch screens'), F('requireReg','Count cash before selling','toggle',true,'Each cashier counts the cash in the drawer to open the register, and again to close it'), F('printRcp','Print receipt','seg','Ask',['Always','Ask','Never'])] },
    { t:'Held sales', f:[F('holdExp','Clear held sales after','select','End of day',['2 hours','End of day','Never'])] } ] },
  { id:'coins', name:'Loyalty coins', icon:'Co', desc:'Customers earn coins on POS sales and spend them on later sales', sections:[
    { t:'Coins', f:[F('coinOn','Use loyalty coins','toggle',false,'Off: no coins are earned or spent. Balances are kept'), F('coinEarn','Coins earned per $1','text','1','Counted on the goods, after discounts and coins spent. Not on delivery or surcharge'), F('coinValue','Value of 1 coin $','text','0.01','At 0.01, 100 coins take $1.00 off'), F('coinMin','Fewest coins spent at a time','text','100'), F('coinMax','Most of a sale paid by coins %','text','50','Of the goods, after discounts')] } ] },
  { id:'pricerules', name:'Price rules', icon:'Pz', desc:'Sell dearer or cheaper on chosen dates, by a percent or a fixed amount', sections:[
    { t:'Price rules', f:[F('goPriceRules','Price rules by date','action','Open price rules','e.g. Eid week +10% on all products, or -$2.00 on lamb for one weekend. The POS uses the rule on its dates')] } ] },
  { id:'purchases', name:'Purchases', icon:'Pu', desc:'Purchase orders, approvals and costing', sections:[
    { t:'Purchase orders', f:[F('poPrefix','PO prefix','text','PO-'), F('approve','Needs approval over','text','5000','Orders above this wait for an owner to approve'), F('poTerms','Default supplier terms','select','14 days',['On delivery','7 days','14 days','30 days']), F('partial','Allow partial receiving','toggle',true), F('costing','Update product cost from','seg','Weighted average',['Last cost','Weighted average'])] } ] },
  { id:'notify', name:'Notifications', icon:'No', desc:'Emails and SMS sent by the system', sections:[
    { t:'Email', f:[F('nEmail','Send alerts to','text','aminul@madinahbutchery.com.au'), F('nLow','Low stock','toggle',true), F('nDaily','Daily sales summary','toggle',true,'Sent at 22:15 after close'), F('nBills','Bills due in 3 days','toggle',true)] },
    { t:'SMS to customers', f:[F('nBook','Booking reminder','toggle',true), F('nLead','Reminder sent','seg','2 hours before',['2 hours before','Day before']), F('nReady','Order ready for pickup','toggle',true)] } ] },
  { id:'security', name:'Users & security', icon:'Us', desc:'Sign-in rules. Users and roles are managed in User Management', sections:[
    { t:'Sign-in', f:[F('timeout','Sign out after inactivity','select','30 minutes',['15 minutes','30 minutes','2 hours','Never']), F('twofa','Two-step sign-in for admins','toggle',true), F('posPin','POS staff sign in with PIN','toggle',true)] },
    { t:'Users and roles', f:[F('goUsers','Manage users and roles','action','Open User Management','6 users, 4 roles')] } ] },
  { id:'system', name:'System', icon:'Sy', desc:'Backups, exports and data', sections:[
    { t:'Backups', f:[F('backup','Daily backup','toggle',true), F('backupAt','Backup time','select','03:00',['01:00','03:00','05:00']), F('backupNow','Back up now','action','Run backup','Last backup today 03:00 \u00b7 182 MB')] },
    { t:'Data', f:[F('export','Export all data','action','Export to Excel','Products, contacts, sales and purchases'), F('reset','Clear demo data','action','Clear data','Removes sample transactions. Settings stay.')] } ] }
];
const TABLES0 = {
  locs: [{ name:'Main branch', addr:'118 Haldon St, Lakemba', code:'MB-', on:true }, { name:'Bankstown shop', addr:'22 Chapel Rd, Bankstown', code:'BK-', on:true }, { name:'Cold store', addr:'7 Muir Rd, Chullora', code:'CS-', on:true }],
  rates: [{ name:'GST', rate:'10', code:'GST', on:true }, { name:'GST free', rate:'0', code:'FRE', on:true }, { name:'Input taxed', rate:'0', code:'ITS', on:false }],
  pays: [{ name:'Cash', acct:'Cash drawer', on:true }, { name:'Card \u00b7 Tyro', acct:'CBA Business', on:true }, { name:'Bank transfer', acct:'CBA Business', on:true }, { name:'Store credit', acct:'Customer balances', on:true }, { name:'Afterpay', acct:'CBA Business', on:false }]
};
const DEF = {};
G.forEach(g => g.sections.forEach(s => (s.f || []).forEach(f => { if (f.type !== 'action') DEF[f.k] = f.def; })));
const CHECK = {
  abn: v => v.replace(/\s/g, '').length === 11 && /^\d+$/.test(v.replace(/\s/g, '')) ? '' : 'ABN must be 11 digits',
  email: v => /^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(v) ? '' : 'Enter a valid email',
  nEmail: v => /^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(v) ? '' : 'Enter a valid email',
  bizName: v => v.trim() ? '' : 'Required',
  invNext: v => /^\d+$/.test(v) && +v > 3310 ? '' : 'Must be a whole number above 3310',
  surch: v => /^\d+(\.\d+)?$/.test(v) && +v <= 3 ? '' : '0 to 3 only',
  maxDisc: v => /^\d+$/.test(v) && +v <= 100 ? '' : '0 to 100',
  coinEarn: v => /^\d+(\.\d+)?$/.test(v) && +v <= 100 ? '' : '0 to 100',
  coinValue: v => /^\d+(\.\d+)?$/.test(v) && +v > 0 && +v <= 100 ? '' : 'More than 0, up to 100',
  coinMin: v => /^\d+$/.test(v) ? '' : 'Whole number',
  coinMax: v => /^\d+$/.test(v) && +v <= 100 ? '' : '0 to 100',
  lowAlert: v => /^\d+$/.test(v) ? '' : 'Whole number',
  approve: v => /^\d+$/.test(v) ? '' : 'Whole number'
};
const clone = o => JSON.parse(JSON.stringify(o));

class Component extends ModBase {
  _id = 0;
  // theme is read when Settings opens, so it always shows the colour saved last
  initialState = { g:'business', q:'', vals: { ...DEF, theme: savedTheme() }, saved: { ...DEF, theme: savedTheme() }, tables: clone(TABLES0), savedTables: clone(TABLES0), confirm: null, toasts: [] };
  later(fn, ms) { (this._t = this._t || []).push(setTimeout(fn, ms)); }
  toast(msg, tone) {
    const id = ++this._id;
    this.setState(s => ({ toasts: s.toasts.concat([{ id, msg, tone: tone || 'info' }]).slice(-3) }));
    this.later(() => this.setState(s => ({ toasts: s.toasts.filter(x => x.id !== id) })), 2600);
  }
  money(n) { return (n || 0).toLocaleString('en-AU', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
  setV(k, v) { this.setState(s => ({ vals: { ...s.vals, [k]: v } })); }
  setRow(key, i, p) { this.setState(s => ({ tables: { ...s.tables, [key]: s.tables[key].map((r, j) => j === i ? { ...r, ...p } : r) } })); }
  dirtyKeys() { const s = this.state; return Object.keys(DEF).filter(k => s.vals[k] !== s.saved[k]); }
  dirtyTables() { const s = this.state; return Object.keys(s.tables).filter(k => JSON.stringify(s.tables[k]) !== JSON.stringify(s.savedTables[k])); }
  errs() { const s = this.state; const e = {}; Object.keys(CHECK).forEach(k => { const m = CHECK[k](String(s.vals[k])); if (m) e[k] = m; }); return e; }
  sw(on) { return ['width:34px;height:20px;border-radius:99px;position:relative;flex:none;background:' + (on ? 'var(--c-primary)' : '#c2cedb'), 'position:absolute;top:3px;width:14px;height:14px;border-radius:50%;background:#ffffff;left:' + (on ? '17px' : '3px')]; }
  action(k) {
    if (k === 'goUsers') { this._host.setState({ view: 'users' }); return; }
    if (k === 'goPriceRules') { location.hash = '#/settings/price-rules'; return; }
    if (k === 'backupNow') {
      // POST /api/settings/backups saves the request (the backup job itself is not built yet)
      api.post('/settings/backups').then(b => {
        const f = G.flatMap(g => g.sections).flatMap(sec => sec.f || []).find(x => x.k === 'backupNow');
        f.help = 'Last request just now by ' + (b.requested_by || '\u2014') + ' \u00b7 ' + b.status;
        this.toast('Backup requested', 'info');
      }).catch(() => {});
      return;
    }
    if (k === 'export') { this.toast('Export is being prepared \u00b7 182 MB', 'neutral'); return; }
    if (k === 'reset') this.setState({ confirm: { title:'Clear demo data?', body:'All sample sales, purchases, stock movements and journals will be removed. Products, contacts and these settings stay. This can\u2019t be undone.', btn:'Clear data', k } });
  }
  // Business > Logo is a file: it is uploaded at once (POST /api/settings/business/logo),
  // not with "Save changes". vals.logo holds the image address (logo_url).
  pickLogo() {
    const input = document.createElement('input');
    input.type = 'file';
    input.accept = 'image/png,image/jpeg,image/webp';
    input.onchange = () => {
      const file = input.files[0];
      if (!file) return;
      if (file.size > 2 * 1024 * 1024) { this.toast('The logo must be 2 MB or smaller', 'error'); return; }
      const form = new FormData();
      form.append('logo', file);
      api.post('/settings/business/logo', form).then(row => {
        this.setLogo(row.logo_url || '');
        this.toast('Logo uploaded', 'success');
      }).catch(() => {});
    };
    input.click();
  }
  removeLogo() {
    api.del('/settings/business/logo').then(() => {
      this.setLogo('');
      this.toast('Logo removed', 'neutral');
    }).catch(() => {});
  }
  // The file is already saved, so the logo is not an unsaved change.
  setLogo(url) { this.setState(s => ({ vals: { ...s.vals, logo: url }, saved: { ...s.saved, logo: url } })); }
  fieldVals(f, g, errs) {
    const s = this.state;
    const v = s.vals[f.k];
    const dirty = f.type !== 'action' && v !== s.saved[f.k];
    const err = errs[f.k] || '';
    const [sw, kn] = this.sw(!!v);
    const mono = ['abn','phone','invPrefix','invNext','rcpPrefix','skuPrefix','surch','maxDisc','lowAlert','approve','poPrefix','coinEarn','coinValue','coinMin','coinMax'].indexOf(f.k) > -1;
    return {
      label: f.label, help: f.help, helpStyle: f.help ? 'font-size:11.5px;color:#7d8b9c;margin-top:2px;line-height:1.45' : 'display:none',
      group: g ? g.name : '', groupTag: g ? 'background:#eef2f6;color:#5d6b7d;border-radius:4px;padding:1px 6px;font-size:10.5px;font-weight:600' : 'display:none',
      dirtyDot: dirty ? 'width:7px;height:7px;border-radius:50%;background:#f59e0b' : 'display:none',
      isText: f.type === 'text', isArea: f.type === 'area', isSelect: f.type === 'select', isToggle: f.type === 'toggle', isSeg: f.type === 'seg', isUpload: f.type === 'upload', isAction: f.type === 'action',
      v: f.type === 'toggle' ? '' : String(v === undefined ? '' : v),
      onInput: e => this.setV(f.k, e.target.value),
      inputStyle: 'width:100%;height:36px;border:1px solid ' + (err ? '#d93a3a' : '#dfe6ee') + ';border-radius:8px;padding:0 11px;font-size:13px;outline:none;background:#ffffff;' + (mono ? MONO + 'font-size:12.5px;' : "font-family:'Public Sans',sans-serif;"),
      opts: (f.opts || []).map(o => ({ v: o })),
      onToggle: () => this.setV(f.k, !v), sw, knob: kn, toggleLabel: v ? 'On' : 'Off',
      segs: (f.opts || []).map(o => ({ v: o, onClick: () => this.setV(f.k, o), style: 'height:30px;padding:0 ' + ((f.opts || []).length > 5 ? '8px' : '12px') + ';border-radius:7px;display:grid;place-items:center;font-size:12.5px;cursor:pointer;white-space:nowrap;' + (v === o ? 'background:#ffffff;color:#0f1c2b;font-weight:700;box-shadow:0 1px 3px rgba(15,28,43,.14);' : 'color:#5d6b7d;font-weight:500;') })),
      uploadText: 'no logo', uploadBtn: v ? 'Replace' : 'Upload', logoUrl: f.type === 'upload' ? v || '' : '',
      onUpload: () => this.pickLogo(), onRemove: () => this.removeLogo(),
      onAction: () => this.action(f.k), actionLabel: f.def,
      actionStyle: 'display:inline-flex;height:34px;padding:0 13px;border-radius:8px;align-items:center;font-size:12.5px;font-weight:600;cursor:pointer;' + (f.k === 'reset' ? 'border:1px solid #f3cfcf;background:#fdf4f4;color:#b02020;' : 'border:1px solid #dfe6ee;background:#ffffff;color:var(--c-primary-text);'),
      err, errStyle: err ? 'font-size:11.5px;color:#b02020;margin-top:4px;font-weight:600' : 'display:none'
    };
  }

  renderVals() {
    const s = this.state;
    const errs = this.errs();
    const q = s.q.trim().toLowerCase();
    const g = G.find(x => x.id === s.g);
    const dk = this.dirtyKeys(), dt = this.dirtyTables();
    const nDirty = dk.length + dt.length;
    const tableSec = (sec) => {
      const key = sec.table.key, cols = sec.table.cols;
      const grid = 'display:grid;grid-template-columns:' + cols.map(c => c[2]).join(' ') + ' 60px 32px;gap:10px;align-items:center;padding:0 18px;';
      return {
        t: sec.t, hasTable: true, addLabel: sec.table.add, fields: [],
        addRow: () => { const r = { on: true }; cols.forEach(c => { r[c[0]] = ''; }); this.setState(ss => ({ tables: { ...ss.tables, [key]: ss.tables[key].concat([r]) } })); },
        headStyle: grid + "padding-top:8px;padding-bottom:8px;background:#f6f9fc;border-top:1px solid #eaeff5;border-bottom:1px solid #eaeff5;font-family:'IBM Plex Mono',monospace;font-size:9.5px;letter-spacing:0.07em;text-transform:uppercase;color:#5d6b7d",
        cols: cols.map(c => ({ h: c[1] })),
        rows: s.tables[key].map((r, i) => {
          const [sw, kn] = this.sw(r.on);
          return {
            style: grid + 'padding-top:7px;padding-bottom:7px;border-bottom:1px solid #f0f4f8;' + (r.on ? '' : 'opacity:.55;'),
            cells: cols.map(c => ({ v: r[c[0]], ph: c[3], onInput: e => this.setRow(key, i, { [c[0]]: e.target.value }),
              style: 'width:100%;min-width:0;height:32px;border:1px solid ' + (!String(r[c[0]]).trim() && c[0] === 'name' ? '#d93a3a' : '#e4eaf1') + ';border-radius:7px;padding:0 9px;font-size:12.5px;outline:none;background:#ffffff;' + (c[0] === 'code' || c[0] === 'rate' ? MONO : "font-family:'Public Sans',sans-serif;") })),
            sw, knob: kn,
            onToggle: () => this.setRow(key, i, { on: !r.on }),
            onDelete: () => { if (s.tables[key].length <= 1) { this.toast('Keep at least one', 'warn'); return; } this.setState(ss => ({ tables: { ...ss.tables, [key]: ss.tables[key].filter((_, j) => j !== i) } })); }
          };
        })
      };
    };
    let sections;
    if (q) {
      const hits = [];
      G.forEach(gg => gg.sections.forEach(sec => (sec.f || []).forEach(f => { if ((f.label + ' ' + f.help + ' ' + sec.t + ' ' + gg.name).toLowerCase().indexOf(q) > -1) hits.push(this.fieldVals(f, gg, errs)); })));
      sections = hits.length ? [{ t: hits.length + (hits.length === 1 ? ' setting' : ' settings') + ' matching \u201c' + s.q.trim() + '\u201d', hasTable: false, fields: hits, cols: [], rows: [] }] : [];
    } else {
      sections = g.sections.map(sec => sec.table ? tableSec(sec) : { t: sec.t, hasTable: false, cols: [], rows: [], fields: sec.f.map(f => this.fieldVals(f, null, errs)) });
    }
    const v = s.vals;
    const sub = 15.94 * 1.25 + 12.9 * 2 + 5.9 * 2;
    const preview = !q && g.preview;
    const errCount = Object.keys(errs).length;
    const groupDirty = gg => gg.sections.reduce((a, sec) => a + (sec.f || []).filter(f => dk.indexOf(f.k) > -1).length + (sec.table && dt.indexOf(sec.table.key) > -1 ? 1 : 0), 0);

    return {
      nav: NAV.map(n => {
        const on = n[0] === 'Settings';
        return {
          name: n[0], key: n[1],
          style: 'display:flex;align-items:center;gap:10px;padding:8px 10px;border-radius:8px;cursor:pointer;font-size:13px;font-weight:500;margin-bottom:1px;' + (on ? 'background:rgba(255,255,255,.13);color:#ffffff;box-shadow:inset 2px 0 0 var(--c-accent);' : 'color:#c4dced;'),
          iconStyle: 'width:20px;height:20px;border-radius:5px;display:grid;place-items:center;' + MONO + 'font-size:9px;font-weight:600;flex:none;' + (on ? 'background:var(--c-accent);color:#06283a;' : 'background:rgba(255,255,255,.1);color:#9dbdd2;'),
          caretStyle: 'color:#7fa3bb;font-size:11px;flex:none;' + (n[2] ? '' : 'visibility:hidden;')
        };
      }),
      q: s.q, onQ: e => this.setState({ q: e.target.value }),
      groups: G.map(gg => {
        const on = !q && gg.id === s.g;
        const n = groupDirty(gg);
        return {
          name: gg.name, icon: gg.icon, dirty: n || '',
          onClick: () => this.setState({ g: gg.id, q: '' }),
          style: 'display:flex;align-items:center;gap:10px;padding:7px 9px;border-radius:8px;cursor:pointer;margin-bottom:1px;' + (on ? 'background:var(--c-primary-soft);color:var(--c-primary-text);font-weight:600;box-shadow:inset 2px 0 0 var(--c-primary);' : 'color:#0f1c2b;'),
          iconStyle: 'width:22px;height:22px;border-radius:6px;display:grid;place-items:center;' + MONO + 'font-size:9.5px;font-weight:600;flex:none;' + (on ? 'background:var(--c-primary);color:#ffffff;' : 'background:#eef2f6;color:#5d6b7d;'),
          dirtyStyle: n ? 'background:#fdf1dd;color:#8a5300;border-radius:99px;padding:1px 7px;font-size:10.5px;font-weight:700' : 'display:none'
        };
      }),
      title: q ? 'Search results' : g.name, desc: q ? 'Change a setting here; it saves with the rest' : g.desc,
      layout: 'padding:18px 28px 32px;display:grid;gap:18px;align-items:start;grid-template-columns:' + (preview ? 'minmax(0,1fr) 300px' : 'minmax(0,820px)'),
      sections, noResults: !!q && !sections.length,
      hasPreview: preview,
      pv: {
        paper: v.paper, brand: BRAND[v.brand] || 'var(--c-primary)', showLogo: !!v.showLogo && !!v.logo, logoUrl: v.logo || '',
        box: 'background:#ffffff;border:1px solid #dfe6ee;border-radius:' + (v.paper === 'A4' ? '4px' : '2px') + ';padding:18px 18px 16px;box-shadow:0 10px 26px -18px rgba(15,28,43,.5);' + (v.paper === 'A4' ? 'min-height:420px;' : 'max-width:250px;margin:0 auto;'),
        biz: v.bizName, addr: v.addr, abn: v.abn, abnStyle: v.showAbn ? 'text-align:center;font-size:10.5px;color:#5d6b7d;margin-top:3px;' + MONO : 'display:none',
        title: v.gstReg ? 'TAX INVOICE' : 'INVOICE', no: v.invPrefix + v.invNext,
        date: (v.dateFmt === 'MM/DD/YYYY' ? '09/29/2026' : v.dateFmt === 'YYYY-MM-DD' ? '2026-09-29' : '29/09/2026') + ' ' + (v.h24 ? '14:38' : '2:38 pm'),
        terms: v.terms,
        l1: this.money(15.94 * 1.25), l2: this.money(12.9 * 2), l3: this.money(5.9 * 2),
        taxLabel: v.gstReg ? (v.priceInc ? 'GST included' : 'GST 10%') : 'No GST',
        tax: this.money(v.gstReg ? (v.priceInc ? sub / 11 : sub * 0.1) : 0),
        total: (v.symPos === 'Before' ? '$' : '') + this.money(v.gstReg && !v.priceInc ? sub * 1.1 : sub) + (v.symPos === 'After' ? ' $' : ''),
        footer: v.footer
      },
      dirty: nDirty > 0,
      dirtyText: nDirty + (nDirty === 1 ? ' unsaved change' : ' unsaved changes') + (errCount ? ' \u00b7 fix ' + errCount + (errCount === 1 ? ' error' : ' errors') + ' to save' : ''),
      saveStyle: 'height:36px;padding:0 16px;border-radius:8px;display:flex;align-items:center;font-size:12.5px;font-weight:700;cursor:pointer;color:#ffffff;background:' + (errCount ? '#5d6b7d' : 'var(--c-primary)'),
      discard: () => { this.setState({ vals: { ...s.saved }, tables: clone(s.savedTables) }); this.toast('Changes discarded', 'neutral'); },
      save: () => {
        if (errCount) { const k = Object.keys(errs)[0]; const gg = G.find(x => x.sections.some(sec => (sec.f || []).some(f => f.k === k))); this.setState({ g: gg.id, q: '' }); this.toast(errs[k], 'error'); return; }
        const emptyRow = Object.keys(s.tables).find(k => s.tables[k].some(r => !String(r.name).trim()));
        if (emptyRow) { this.toast('Name every row before saving', 'error'); return; }
        this.setState({ saved: { ...s.vals }, savedTables: clone(s.tables) });
        applyTheme(s.vals.theme);
        this.toast(nDirty + (nDirty === 1 ? ' change' : ' changes') + ' saved', 'success');
      },
      confirmOpen: !!s.confirm, cf: s.confirm ? { ...s.confirm, go: () => { this.setState({ confirm: null }); this.toast('Demo data cleared', 'neutral'); } } : { title:'', body:'', btn:'', go: () => {} },
      closeConfirm: () => this.setState({ confirm: null }),
      toasts: s.toasts.map(x => ({ msg: x.msg, dot: 'width:8px;height:8px;border-radius:50%;flex:none;background:' + TONE[x.tone][2] }))
    };
  }
}
Component.data = { G };
return Component;
})();
