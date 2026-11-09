// MfgLogic
DC.add(function () {
const MfgLogic = (() => {
const NAV = [['Home','Hm',0],['User Management','Us',1],['Contacts','Ct',1],['Products','Pr',1],['Manufacturing','Mf',0],['Purchases','Pu',1],['Sell','Sl',1],['Stock Transfers','St',0],['Stock Adjustment','Sa',0],['Expenses','Ex',1],['Payment Accounts','Pa',1],['Accounting','Ac',1],['Reports','Rp',1],['Bookings','Bk',0],['Kitchen','Kt',0],['Orders','Or',0],['Settings','Se',1],['HRM','Hr',1]];
const TONE = { info:['#eaf1fb','#14549e','#1668c9'], warn:['#fdf1dd','#8a5300','#d98c1a'], error:['#fdeaea','#b02020','#d93a3a'], success:['#e7f6ee','#0f6b43','#2f9e63'], neutral:['#eef2f6','#5d6b7d','#8fa3b6'] };
const MONO = "font-family:'IBM Plex Mono',monospace;";
const MATS = { bm:['Beef Mince','kg',7.2], bc:['Beef Chuck','kg',11.4], lm:['Lamb Shoulder','kg',13.1], ct:['Chicken Thigh','kg',5.9], cas:['Sausage casing','m',0.42], sp:['Spice Mix 200g','ea',3.1], on:['Onion','kg',1.8], pa:['Parsley','bunch',1.5], ma:['Marinade base','L',4.2], rb:['Breadcrumb','kg',2.6], tr:['Trays & film','ea',0.35] };
const ST = { planned:['Planned','neutral'], progress:['In progress','info'], done:['Done','success'], cancelled:['Cancelled','error'] };
const RECIPES0 = [
  { id:'saus', name:'Beef Sausages', unit:'kg', out:20, price:16.9, lines:[['bm',18],['cas',60],['sp',4],['rb',1.5],['tr',20]] },
  { id:'kofta', name:'Lamb Kofta', unit:'kg', out:10, price:24.9, lines:[['lm',9],['on',1],['pa',3],['sp',2],['tr',10]] },
  { id:'mari', name:'Marinated Chicken Thigh', unit:'kg', out:15, price:14.5, lines:[['ct',14.5],['ma',2],['sp',2],['tr',15]] },
  { id:'patty', name:'Beef Burger Patties', unit:'ea', out:100, price:2.2, lines:[['bm',11],['on',0.8],['rb',0.6],['sp',1],['tr',25]] },
  { id:'mince', name:'Spiced Lamb Mince', unit:'kg', out:8, price:22.9, lines:[['lm',8],['sp',2],['tr',8]] }
];
const STOCK0 = { bm:180, bc:60, lm:46, ct:120, cas:900, sp:60, on:25, pa:20, ma:14, rb:12, tr:600 };
const MO0 = [
  { ref:'MO-0117', rec:'mince', b:1, loc:'Cold store', status:'planned', date:'30/09', actual:null, log:[['Planned by Karim','28/09 16:10','neutral']] },
  { ref:'MO-0116', rec:'saus', b:6, loc:'Cold store', status:'progress', date:'29/09', actual:null, log:[['Started by Karim \u00b7 materials reserved','29/09 07:40','info'],['Planned by Karim','28/09 15:55','neutral']] },
  { ref:'MO-0115', rec:'patty', b:4, loc:'Main branch', status:'done', date:'27/09', actual:388, log:[['Finished \u00b7 388 ea','27/09 13:20','success'],['Started by Omar','27/09 08:05','info']] },
  { ref:'MO-0114', rec:'mari', b:5, loc:'Main branch', status:'done', date:'26/09', actual:73.5, log:[['Finished \u00b7 73.5 kg','26/09 11:45','success'],['Started by Tariq','26/09 07:30','info']] },
  { ref:'MO-0113', rec:'kofta', b:6, loc:'Cold store', status:'done', date:'24/09', actual:56.4, log:[['Finished \u00b7 56.4 kg','24/09 14:10','success'],['Started by Karim','24/09 08:00','info']] },
  { ref:'MO-0112', rec:'saus', b:6, loc:'Cold store', status:'done', date:'22/09', actual:115.2, log:[['Finished \u00b7 115.2 kg','22/09 13:30','success'],['Started by Karim','22/09 07:45','info']] }
];
class Component extends ModBase {
  _id = 0;
  initialState = { tab:'orders', st:'All', mos: MO0, recipes: RECIPES0, stock: { ...STOCK0 }, made: {}, sel:'MO-0116', rsel:'saus', form:null, actual:'', next:118, toasts:[] };
  later(fn, ms) { (this._t = this._t || []).push(setTimeout(fn, ms)); }
  toast(msg, tone) { const id = ++this._id; this.setState(s => ({ toasts: s.toasts.concat([{ id, msg, tone: tone || 'info' }]).slice(-3) })); this.later(() => this.setState(s => ({ toasts: s.toasts.filter(x => x.id !== id) })), 2600); }
  money(n) { return (n || 0).toLocaleString('en-AU', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
  q(n, u) { return (Math.round(n * 100) / 100).toLocaleString('en-AU') + ' ' + u; }
  badge(x) { return 'background:' + x[0] + ';color:' + x[1] + ';border-radius:5px;padding:3px 8px;font-size:11.5px;font-weight:600;white-space:nowrap'; }
  seg(on) { return 'height:30px;padding:0 12px;border-radius:7px;display:grid;place-items:center;font-size:12.5px;cursor:pointer;' + (on ? 'background:#ffffff;color:#0f1c2b;font-weight:700;box-shadow:0 1px 3px rgba(15,28,43,.14);' : 'color:#5d6b7d;font-weight:500;'); }
  pageTab(on) { return 'padding:9px 14px;font-size:13px;cursor:pointer;border-bottom:2px solid ' + (on ? '#1668c9;color:#1668c9;font-weight:600;' : 'transparent;color:#5d6b7d;font-weight:500;'); }
  rec(id) { return this.state.recipes.find(r => r.id === id); }
  batchCost(r) { return r.lines.reduce((a, l) => a + MATS[l[0]][2] * l[1], 0); }
  need(r, b) { return r.lines.map(l => ({ id: l[0], q: l[1] * b })); }
  short(r, b) { const s = this.state.stock; return this.need(r, b).filter(n => s[n.id] < n.q - 1e-9); }
  now() { const d = new Date(); return '29/09 ' + String(d.getHours()).padStart(2, '0') + ':' + String(d.getMinutes()).padStart(2, '0'); }
  start(mo) {
    const r = this.rec(mo.rec), sh = this.short(r, mo.b);
    if (sh.length) { this.toast('Not enough ' + MATS[sh[0].id][0] + ' to start', 'error'); return false; }
    const stock = { ...this.state.stock };
    this.need(r, mo.b).forEach(n => { stock[n.id] = Math.round((stock[n.id] - n.q) * 100) / 100; });
    this.setState(s => ({ stock, mos: s.mos.map(x => x.ref === mo.ref ? { ...x, status: 'progress', log: [['Started by Aminul \u00b7 materials used', this.now(), 'info']].concat(x.log) } : x) }));
    this.toast(mo.ref + ' started \u00b7 ' + r.lines.length + ' materials taken from ' + mo.loc, 'success');
    return true;
  }
  renderVals() {
    const s = this.state;
    const mos = s.mos.filter(m => s.st === 'All' || ST[m.status][0] === s.st);
    const sel = s.mos.find(m => m.ref === s.sel);
    const rc = this.rec(s.rsel);
    const f = s.form;
    const fr = f ? this.rec(f.rec) : null;
    const done = s.mos.filter(m => m.status === 'done');
    const yields = done.map(m => m.actual / (this.rec(m.rec).out * m.b));
    const A = (label, fn, kind) => ({ label, onClick: fn, style: 'height:40px;padding:0 14px;border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:600;cursor:pointer;' + (kind === 'p' ? 'flex:1;background:#1668c9;color:#ffffff;' : kind === 'g' ? 'flex:1;background:#2f9e63;color:#ffffff;' : kind === 'd' ? 'border:1px solid #f3cfcf;background:#fdf4f4;color:#b02020;' : 'border:1px solid #dfe6ee;color:#4a5866;') });
    let d = null;
    if (sel) {
      const r = this.rec(sel.rec), exp = r.out * sel.b, cost = this.batchCost(r) * sel.b;
      const act = parseFloat(s.actual);
      const sh = sel.status === 'planned' ? this.short(r, sel.b) : [];
      const actions = sel.status === 'planned' ? [A('Cancel', () => { this.setState(ss => ({ mos: ss.mos.map(x => x.ref === sel.ref ? { ...x, status: 'cancelled', log: [['Cancelled', this.now(), 'error']].concat(x.log) } : x) })); this.toast(sel.ref + ' cancelled', 'neutral'); }, 'd'), A('Start production', () => this.start(sel), 'p')]
        : sel.status === 'progress' ? [A('Finish & add to stock', () => {
            if (!(act > 0)) { this.toast('Enter the actual output', 'error'); return; }
            if (act > exp * 1.1) { this.toast('Output is more than 110% of expected. Check the weight', 'warn'); return; }
            this.setState(ss => ({ actual: '', made: { ...ss.made, [r.id]: (ss.made[r.id] || 0) + act }, mos: ss.mos.map(x => x.ref === sel.ref ? { ...x, status: 'done', actual: act, log: [['Finished \u00b7 ' + this.q(act, r.unit) + ' added to ' + sel.loc, this.now(), 'success']].concat(x.log) } : x) }));
            this.toast(this.q(act, r.unit) + ' ' + r.name + ' added to stock \u00b7 unit cost ' + this.money(cost / act), 'success');
          }, 'g')] : [A('Duplicate', () => this.setState({ form: { rec: sel.rec, b: sel.b, loc: sel.loc } }))];
      const y = sel.actual ? sel.actual / exp * 100 : act > 0 ? act / exp * 100 : null;
      d = { ref: sel.ref, status: ST[sel.status][0], badge: this.badge(TONE[ST[sel.status][1]]), product: r.name, unit: r.unit,
        sub: sel.b + ' batch' + (sel.b > 1 ? 'es' : '') + ' \u00b7 ' + sel.loc + ' \u00b7 ' + sel.date,
        inLabel: sel.status === 'planned' ? 'Will use' : 'Used', outLabel: sel.status === 'done' ? 'Made' : 'Expected',
        ins: this.need(r, sel.b).map(n => ({ name: MATS[n.id][0], q: '\u2212' + this.q(n.q, MATS[n.id][1]), qStyle: MONO + 'font-weight:600;color:' + (sh.some(x => x.id === n.id) ? '#b02020' : '#4a5866') })),
        outQty: this.q(sel.actual || exp, r.unit), unitCost: this.money(cost / (sel.actual || exp)) + ' per ' + r.unit,
        short: sh.length > 0, shortText: 'Short of ' + sh.map(x => MATS[x.id][0] + ' (' + this.q(x.q - s.stock[x.id], MATS[x.id][1]) + ')').join(', '),
        canFinish: sel.status === 'progress',
        costs: [['Materials', this.money(cost)], ['Expected', this.q(exp, r.unit)], ['Yield', y === null ? '\u2014' : y.toFixed(1) + '%'], ['Sell value', this.money((sel.actual || exp) * r.price)]].map((c, i) => ({ k: c[0], v: c[1], style: 'display:flex;' + (i === 3 ? 'font-weight:700;border-top:1px solid #eaeff5;padding-top:7px;' : 'color:#5d6b7d;') })),
        log: sel.log.map(a => ({ what: a[0], when: a[1], dot: 'width:8px;height:8px;border-radius:50%;margin-top:5px;flex:none;background:' + TONE[a[2]][2] })), actions };
    }
    const sAct = sel ? parseFloat(s.actual) : NaN;
    const sExp = sel ? this.rec(sel.rec).out * sel.b : 1;
    return {
      nav: NAV.map(n => { const on = n[0] === 'Manufacturing'; return { name: n[0], key: n[1],
        style: 'display:flex;align-items:center;gap:10px;padding:8px 10px;border-radius:8px;cursor:pointer;font-size:13px;font-weight:500;margin-bottom:1px;' + (on ? 'background:rgba(255,255,255,.13);color:#ffffff;box-shadow:inset 2px 0 0 #4da3e8;' : 'color:#c4dced;'),
        iconStyle: 'width:20px;height:20px;border-radius:5px;display:grid;place-items:center;' + MONO + 'font-size:9px;font-weight:600;flex:none;' + (on ? 'background:#4da3e8;color:#06283a;' : 'background:rgba(255,255,255,.1);color:#9dbdd2;'),
        caretStyle: 'color:#7fa3bb;font-size:11px;flex:none;visibility:hidden;' }; }),
      crumb: s.tab === 'orders' ? 'Production orders' : 'Recipes',
      tabs: [['orders','Production orders', s.mos.length], ['recipes','Recipes', s.recipes.length]].map(t => ({ name: t[1], count: t[2], onClick: () => this.setState({ tab: t[0] }), style: this.pageTab(s.tab === t[0]), countStyle: MONO + 'font-size:10.5px;border-radius:4px;padding:1px 5px;margin-left:6px;background:#eef2f6;color:#7d8b9c' })),
      newMo: () => this.setState({ form: { rec: s.rsel || 'saus', b: 1, loc: 'Cold store' } }),
      isOrders: s.tab === 'orders', isRecipes: s.tab === 'recipes',
      kpis: [
        { k: 'In progress', v: String(s.mos.filter(m => m.status === 'progress').length), n: s.mos.filter(m => m.status === 'planned').length + ' planned' },
        { k: 'Output this month', v: this.q(done.filter(m => this.rec(m.rec).unit === 'kg').reduce((a, m) => a + m.actual, 0), 'kg'), n: done.filter(m => this.rec(m.rec).unit === 'ea').reduce((a, m) => a + m.actual, 0) + ' patties' },
        { k: 'Average yield', v: yields.length ? (yields.reduce((a, b) => a + b, 0) / yields.length * 100).toFixed(1) + '%' : '\u2014', n: 'actual vs expected' },
        { k: 'Material cost', v: this.money(done.reduce((a, m) => a + this.batchCost(this.rec(m.rec)) * m.b, 0)), n: done.length + ' finished orders' }
      ],
      stTabs: ['All','Planned','In progress','Done'].map(k => ({ name: k, onClick: () => this.setState({ st: k }), style: this.seg(s.st === k) })),
      rows: mos.map(m => { const r = this.rec(m.rec), exp = r.out * m.b, y = m.actual ? m.actual / exp * 100 : null; return {
        ref: m.ref, date: m.date + '/26', product: r.name, loc: m.loc + ' \u00b7 ' + m.b + ' batch' + (m.b > 1 ? 'es' : ''), planned: this.q(exp, r.unit), output: m.actual ? this.q(m.actual, r.unit) : '\u2014', cost: this.money(this.batchCost(r) * m.b),
        yield: y === null ? '\u2014' : y.toFixed(1) + '%', yStyle: MONO + 'font-size:12.5px;text-align:right;' + (y !== null && y < 95 ? 'color:#b45309;font-weight:600;' : ''),
        status: ST[m.status][0], badge: this.badge(TONE[ST[m.status][1]]), open: () => this.setState({ sel: m.ref, actual: '' }),
        style: 'display:grid;grid-template-columns:0.8fr 1.7fr 0.9fr 0.9fr 0.9fr 0.8fr 1fr;gap:12px;padding:10px 16px;border-bottom:1px solid #f0f4f8;align-items:center;cursor:pointer;' + (m.ref === s.sel ? 'background:#f0f6fd;box-shadow:inset 3px 0 0 #1668c9;' : '') }; }),
      hasSel: !!d && s.tab === 'orders', d: d || {}, closeSel: () => this.setState({ sel: null }),
      actual: s.actual, onActual: e => this.setState({ actual: e.target.value.replace(/[^0-9.]/g, '') }),
      yieldText: sAct > 0 ? 'Yield ' + (sAct / sExp * 100).toFixed(1) + '% \u00b7 ' + (sAct < sExp ? this.q(sExp - sAct, this.rec(sel.rec).unit) + ' trim and loss' : 'on or over target') : 'Weigh the finished product. Expected ' + this.q(sExp, sel ? this.rec(sel.rec).unit : ''),
      yieldStyle: 'font-size:12px;' + (sAct > 0 && sAct / sExp < 0.95 ? 'color:#b45309;font-weight:600;' : 'color:#5d6b7d;'),
      recipes: s.recipes.map(r => ({ name: r.name, meta: r.lines.length + ' materials \u00b7 yields ' + this.q(r.out, r.unit), onClick: () => this.setState({ rsel: r.id }),
        style: 'padding:11px 14px;border-bottom:1px solid #f0f4f8;cursor:pointer;' + (r.id === s.rsel ? 'background:#eaf1fb;box-shadow:inset 3px 0 0 #1668c9;' : '') })),
      rc: { name: rc.name, unit: rc.unit, out: String(rc.out), sub: 'Sells at ' + this.money(rc.price) + ' per ' + rc.unit + (s.made[rc.id] ? ' \u00b7 ' + this.q(s.made[rc.id], rc.unit) + ' made this session' : ''),
        unitCost: this.money(this.batchCost(rc) / rc.out), batchCost: this.money(this.batchCost(rc)),
        margin: 'Margin ' + ((1 - this.batchCost(rc) / rc.out / rc.price) * 100).toFixed(1) + '%', marginStyle: 'font-size:11.5px;font-weight:600;color:' + (this.batchCost(rc) / rc.out / rc.price > 0.7 ? '#b02020' : '#0f6b43'),
        lines: rc.lines.map((l, i) => ({ name: MATS[l[0]][0], qty: String(l[1]), unit: MATS[l[0]][1], cost: this.money(MATS[l[0]][2]), total: this.money(MATS[l[0]][2] * l[1]),
          onQty: e => { const v = parseFloat(e.target.value.replace(/[^0-9.]/g, '')) || 0; this.setState(ss => ({ recipes: ss.recipes.map(r => r.id === rc.id ? { ...r, lines: r.lines.map((x, j) => j === i ? [x[0], v] : x) } : r) })); },
          remove: () => this.setState(ss => ({ recipes: ss.recipes.map(r => r.id === rc.id ? { ...r, lines: r.lines.filter((_, j) => j !== i) } : r) })) })) },
      matOpts: Object.keys(MATS).filter(k => !rc.lines.some(l => l[0] === k)).map(k => ({ id: k, name: MATS[k][0] })),
      addMat: e => { const k = e.target.value; if (!k) return; this.setState(ss => ({ recipes: ss.recipes.map(r => r.id === rc.id ? { ...r, lines: r.lines.concat([[k, 1]]) } : r) })); },
      onOut: e => { const v = parseFloat(e.target.value) || 0; this.setState(ss => ({ recipes: ss.recipes.map(r => r.id === rc.id ? { ...r, out: v || 1 } : r) })); },
      formOpen: !!f,
      fRecipe: f ? f.rec : '', recipeOpts: s.recipes.map(r => ({ id: r.id, name: r.name + ' \u00b7 ' + this.q(r.out, r.unit) + ' per batch' })),
      onRecipe: e => this.setState(ss => ({ form: { ...ss.form, rec: e.target.value } })),
      fBatches: f ? f.b : 1, bDown: () => this.setState(ss => ({ form: { ...ss.form, b: Math.max(1, ss.form.b - 1) } })), bUp: () => this.setState(ss => ({ form: { ...ss.form, b: ss.form.b + 1 } })),
      fLoc: f ? f.loc : '', onLoc: e => this.setState(ss => ({ form: { ...ss.form, loc: e.target.value } })),
      fLines: fr ? this.need(fr, f.b).map(n => { const have = s.stock[n.id], after = have - n.q; return { name: MATS[n.id][0], need: this.q(n.q, MATS[n.id][1]), have: this.q(have, MATS[n.id][1]), after: this.q(after, MATS[n.id][1]), afterStyle: MONO + 'text-align:right;font-weight:600;color:' + (after < 0 ? '#b02020' : '#0f6b43') }; }) : [],
      fOut: fr ? this.q(fr.out * f.b, fr.unit) : '', fCost: fr ? this.money(this.batchCost(fr) * f.b) : '',
      fShort: !!fr && this.short(fr, f.b).length > 0, fShortText: fr ? 'Not enough stock to start now. You can still save it as planned.' : '',
      startStyle: 'height:34px;padding:0 15px;border-radius:8px;display:flex;align-items:center;font-size:12.5px;font-weight:600;color:#ffffff;cursor:pointer;background:' + (fr && this.short(fr, f.b).length ? '#9fbde0' : '#1668c9'),
      savePlan: () => { const ref = 'MO-0' + s.next; this.setState(ss => ({ mos: [{ ref, rec: f.rec, b: f.b, loc: f.loc, status: 'planned', date: '29/09', actual: null, log: [['Planned by Aminul', this.now(), 'neutral']] }].concat(ss.mos), next: ss.next + 1, form: null, sel: ref, tab: 'orders', st: 'All' })); this.toast(ref + ' planned', 'neutral'); },
      saveStart: () => { if (this.short(fr, f.b).length) { this.toast('Not enough materials to start', 'error'); return; } const ref = 'MO-0' + s.next; const mo = { ref, rec: f.rec, b: f.b, loc: f.loc, status: 'planned', date: '29/09', actual: null, log: [['Planned by Aminul', this.now(), 'neutral']] }; this.setState(ss => ({ mos: [mo].concat(ss.mos), next: ss.next + 1, form: null, sel: ref, tab: 'orders', st: 'All' })); this.later(() => this.start(mo), 30); },
      closeAll: () => this.setState({ form: null }),
      toasts: s.toasts.map(x => ({ msg: x.msg, dot: 'width:8px;height:8px;border-radius:50%;flex:none;background:' + TONE[x.tone][2] }))
    };
  }
}
return Component;
})();
});
