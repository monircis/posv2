// AdjustLogic
DC.add(function () {
const AdjustLogic = (() => {

const NAV = [
  ['Home','Hm',0],['User Management','Us',1],['Contacts','Ct',1],['Products','Pr',1],
  ['Manufacturing','Mf',0],['Purchases','Pu',1],['Sell','Sl',1],['Stock Transfers','St',0],
  ['Stock Adjustment','Sa',0],['Expenses','Ex',1],['Payment Accounts','Pa',1],['Accounting','Ac',1],
  ['Reports','Rp',1],['Bookings','Bk',0],['Kitchen','Kt',0],['Orders','Or',0],
  ['Settings','Se',1],['HRM','Hr',1]
];
const LOCS = [
  {id:'main', name:'Main branch'}, {id:'bank', name:'Bankstown shop'}, {id:'cold', name:'Cold store'}
];
const PRODS = [
  {id:1, name:'Beef Mince', sku:'6603', unit:'kg', cost:7.20},
  {id:2, name:'Beef Rump Steak', sku:'6600', unit:'kg', cost:16.80},
  {id:3, name:'Beef Scotch Fillet', sku:'6595', unit:'kg', cost:19.50},
  {id:4, name:'Beef T-Bone', sku:'6598', unit:'kg', cost:20.40},
  {id:5, name:'Lamb Chops', sku:'6622', unit:'kg', cost:19.90},
  {id:6, name:'Lamb Shoulder', sku:'6621', unit:'kg', cost:13.10},
  {id:7, name:'Lamb Leg', sku:'6624', unit:'kg', cost:11.60},
  {id:8, name:'Chicken Breast', sku:'6642', unit:'kg', cost:8.40},
  {id:9, name:'Chicken Thigh', sku:'6641', unit:'kg', cost:5.90},
  {id:10, name:'Goat Curry Cut', sku:'6631', unit:'kg', cost:15.20},
  {id:11, name:'Basmati Rice 5kg', sku:'7102', unit:'ea', cost:11.50},
  {id:12, name:'Spice Mix 200g', sku:'7120', unit:'ea', cost:3.10},
  {id:13, name:'Pita Bread 6 pack', sku:'7131', unit:'ea', cost:2.40}
];
const STOCK0 = {
  main: {1:54,2:41,3:0,4:18,5:27,6:3,7:12,8:46,9:9,10:12,11:44,12:60,13:4},
  bank: {1:6,2:4,3:2,4:0,5:11,6:5,7:3,8:7,9:21,10:1,11:8,12:14,13:18},
  cold: {1:180,2:95,3:62,4:40,5:88,6:46,7:70,8:140,9:120,10:34,11:120,12:0,13:0}
};
const REASONS = {
  dec: ['Spoilage','Damaged','Trim & waste','Missing','Staff meal','Count shortfall'],
  inc: ['Found in count','Returned to shelf','Supplier bonus','Opening stock']
};
const TYPE = { dec:['Decrease','error'], inc:['Increase','success'], count:['Stock count','info'] };
const TONE = {
  info:['#eaf1fb','#14549e','#1668c9'], warn:['#fdf1dd','#8a5300','#d98c1a'], error:['#fdeaea','#b02020','#d93a3a'],
  success:['#e7f6ee','#0f6b43','#2f9e63'], neutral:['#eef2f6','#5d6b7d','#8fa3b6']
};
const MONO = "font-family:'IBM Plex Mono',monospace;";
const r2 = n => Math.round(n * 100) / 100;
const rq = n => Math.round(n * 1000) / 1000;
const A = (pid, before, delta, reason) => ({ pid, before, delta, reason });

const SEED = [
  {ref:'SA-0231', date:'28/09/2026', loc:'main', type:'dec', by:'Aminul', approved:'\u2014', notes:'Cool room 2 alarm overnight. Temperature log in the shared drive.',
    lines:[A(1,62,-6,'Spoilage'), A(8,52,-4.5,'Spoilage'), A(13,6,-2,'Damaged')]},
  {ref:'SA-0230', date:'27/09/2026', loc:'bank', type:'count', by:'Karim', approved:'Sara Rahman', notes:'Monthly count, Bankstown.',
    lines:[A(2,5.2,-1.2,'Count shortfall'), A(5,10.4,0.6,'Found in count'), A(9,21.8,-0.8,'Count shortfall'), A(12,13,1,'Found in count')]},
  {ref:'SA-0229', date:'25/09/2026', loc:'main', type:'inc', by:'Aminul', approved:'\u2014', notes:'',
    lines:[A(11,40,4,'Supplier bonus')]},
  {ref:'SA-0228', date:'22/09/2026', loc:'cold', type:'dec', by:'Sara', approved:'Sara Rahman', notes:'Freezer burn on bottom shelf stock.',
    lines:[A(3,70,-8,'Spoilage'), A(4,46,-6,'Damaged'), A(6,52,-6,'Trim & waste'), A(10,40,-6,'Spoilage')]},
  {ref:'SA-0227', date:'18/09/2026', loc:'main', type:'dec', by:'Aminul', approved:'\u2014', notes:'',
    lines:[A(1,58,-2.5,'Trim & waste'), A(2,44,-1,'Staff meal')]},
  {ref:'SA-0226', date:'14/09/2026', loc:'bank', type:'inc', by:'Karim', approved:'\u2014', notes:'Customer returned unopened.',
    lines:[A(11,6,2,'Returned to shelf')]},
  {ref:'SA-0225', date:'08/09/2026', loc:'main', type:'dec', by:'Aminul', approved:'\u2014', notes:'',
    lines:[A(12,64,-4,'Missing')]}
];

class Component extends ModBase {
  _id = 0;
  initialState = {
    view: 'list', tab: 'All', q: '', locF: 'all', reasonF: 'all',
    adj: SEED, stock: JSON.parse(JSON.stringify(STOCK0)), next: 232,
    sel: 'SA-0231', form: null, fq: '', confirm: null, reversed: {}, toasts: []
  };

  componentWillUnmount() { (this._t || []).forEach(clearTimeout); }
  later(fn, ms) { (this._t = this._t || []).push(setTimeout(fn, ms)); }
  toast(msg, tone) {
    const id = ++this._id;
    this.setState(s => ({ toasts: s.toasts.concat([{ id, msg, tone: tone || 'info' }]).slice(-3) }));
    this.later(() => this.setState(s => ({ toasts: s.toasts.filter(t => t.id !== id) })), 2600);
  }
  money(n) { return (n || 0).toLocaleString('en-AU', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
  prod(id) { return PRODS.find(p => p.id === id); }
  loc(id) { return LOCS.find(l => l.id === id); }
  q(n, unit) { return unit === 'kg' ? rq(n) + ' kg' : String(rq(n)); }
  badge(t) { return 'background:' + t[0] + ';color:' + t[1] + ';border-radius:5px;padding:3px 8px;font-size:11.5px;font-weight:600;white-space:nowrap'; }
  pageTab(on) {
    return 'padding:9px 14px;font-size:13px;cursor:pointer;border-bottom:2px solid '
      + (on ? '#1668c9;color:#1668c9;font-weight:600;' : 'transparent;color:#5d6b7d;font-weight:500;');
  }
  sums(lines) {
    let inc = 0, dec = 0;
    lines.forEach(l => { const v = l.delta * this.prod(l.pid).cost; if (v > 0) inc += v; else dec -= v; });
    return { inc: r2(inc), dec: r2(dec), net: r2(inc - dec) };
  }
  signed(n) { return (n > 0 ? '+' : n < 0 ? '\u2212' : '') + this.money(Math.abs(n)); }

  openForm(mode) {
    this.setState({ view: 'form', fq: '', form: { mode, loc: 'main', notes: '', lines: [] } });
  }
  setForm(p) { this.setState(s => ({ form: { ...s.form, ...p } })); }
  addLine(pid) {
    const f = this.state.form;
    if (f.lines.some(l => l.pid === pid)) { this.toast(this.prod(pid).name + ' is already listed', 'warn'); this.setState({ fq: '' }); return; }
    const dir = f.mode === 'inc' ? 1 : -1;
    this.setForm({ lines: f.lines.concat([{ pid, qty: '', dir, reason: f.mode === 'count' ? '' : '' }]) });
    this.setState({ fq: '' });
  }
  // computed delta for a form line
  delta(l, f) {
    const onHand = this.state.stock[f.loc][l.pid];
    const v = parseFloat(l.qty);
    if (f.mode === 'count') return isNaN(v) ? 0 : rq(v - onHand);
    if (isNaN(v)) return null;
    if (f.mode === 'dec') return -v;
    if (f.mode === 'inc') return v;
    return v * l.dir;
  }
  errors() {
    const f = this.state.form, st = this.state.stock;
    const e = [];
    if (!f.lines.length) e.push('Add at least one product');
    f.lines.forEach(l => {
      const p = this.prod(l.pid), d = this.delta(l, f), on = st[f.loc][l.pid];
      if (d === null || (f.mode !== 'count' && !(parseFloat(l.qty) > 0))) e.push(p.name + ': enter ' + (f.mode === 'count' ? 'the counted quantity' : 'a quantity'));
      else if (on + d < -1e-9) e.push(p.name + ': can\u2019t remove more than the ' + this.q(on, p.unit) + ' on hand');
      else if (p.unit === 'ea' && Math.abs(d % 1) > 1e-9) e.push(p.name + ': whole units only');
      else if (Math.abs(d) > 1e-9 && !l.reason) e.push(p.name + ': pick a reason');
    });
    return e;
  }
  post(approved) {
    const s = this.state, f = s.form;
    const errs = this.errors();
    if (errs.length) { this.toast(errs[0], 'error'); return; }
    const lines = f.lines.map(l => ({ pid: l.pid, before: s.stock[f.loc][l.pid], delta: this.delta(l, f), reason: l.reason || 'No change' }))
      .filter(l => Math.abs(l.delta) > 1e-9);
    if (!lines.length) { this.toast('Counts match the system, nothing to post', 'neutral'); return; }
    const sm = this.sums(lines);
    if (sm.dec > 500 && !approved) {
      this.setState({ confirm: { kind: 'approve', title: 'Manager approval', text: 'This removes ' + this.money(sm.dec) + ' of stock at ' + this.loc(f.loc).name + '. Sara Rahman will be asked to approve it before stock changes. In the prototype, approval is instant.', btn: 'Request & post' } });
      return;
    }
    const stock = JSON.parse(JSON.stringify(s.stock));
    lines.forEach(l => { stock[f.loc][l.pid] = rq(stock[f.loc][l.pid] + l.delta); });
    const ref = 'SA-0' + s.next;
    const type = f.mode === 'count' ? 'count' : sm.net >= 0 ? 'inc' : 'dec';
    const a = { ref, date: '29/09/2026', loc: f.loc, type, by: 'Aminul', approved: approved ? 'Sara Rahman' : '\u2014', notes: f.notes, lines };
    this.setState({ adj: [a].concat(s.adj), stock, next: s.next + 1, view: 'details', sel: ref, form: null, confirm: null });
    this.toast(ref + ' posted \u00b7 ' + this.signed(sm.net) + ' stock value', sm.net < 0 ? 'warn' : 'success');
  }
  reverse(a) {
    const s = this.state;
    const stock = JSON.parse(JSON.stringify(s.stock));
    const bad = a.lines.find(l => stock[a.loc][l.pid] - l.delta < -1e-9);
    if (bad) { this.toast('Not enough ' + this.prod(bad.pid).name + ' left to reverse this', 'error'); this.setState({ confirm: null }); return; }
    const lines = a.lines.map(l => { const b = stock[a.loc][l.pid]; stock[a.loc][l.pid] = rq(b - l.delta); return { pid: l.pid, before: b, delta: rq(-l.delta), reason: 'Reversal of ' + a.ref }; });
    const ref = 'SA-0' + s.next;
    const sm = this.sums(lines);
    const r = { ref, date: '29/09/2026', loc: a.loc, type: sm.net >= 0 ? 'inc' : 'dec', by: 'Aminul', approved: '\u2014', notes: 'Reverses ' + a.ref + '.', lines, reverses: a.ref };
    this.setState({ adj: [r].concat(s.adj), stock, next: s.next + 1, sel: ref, confirm: null, reversed: { ...s.reversed, [a.ref]: ref } });
    this.toast(a.ref + ' reversed by ' + ref, 'neutral');
  }

  renderVals() {
    const s = this.state;
    const all = s.adj;
    const qq = s.q.trim().toLowerCase();
    const tabKey = { All: null, Decrease: 'dec', Increase: 'inc', 'Stock count': 'count' };
    const filt = all.filter(a =>
      (s.tab === 'All' || a.type === tabKey[s.tab])
      && (s.locF === 'all' || a.loc === s.locF)
      && (s.reasonF === 'all' || a.lines.some(l => l.reason === s.reasonF))
      && (!qq || a.ref.toLowerCase().indexOf(qq) > -1 || a.lines.some(l => this.prod(l.pid).name.toLowerCase().indexOf(qq) > -1)));
    const month = all.reduce((m, a) => { const x = this.sums(a.lines); m.inc += x.inc; m.dec += x.dec; return m; }, { inc: 0, dec: 0 });
    const byReason = {};
    all.forEach(a => a.lines.forEach(l => { if (l.delta < 0 && REASONS.dec.indexOf(l.reason) > -1) byReason[l.reason] = (byReason[l.reason] || 0) + -l.delta * this.prod(l.pid).cost; }));
    const top = Object.keys(byReason).sort((x, y) => byReason[y] - byReason[x]).slice(0, 3);
    const netAll = r2(filt.reduce((a, x) => a + this.sums(x.lines).net, 0));

    const f = s.form;
    let fv = {};
    if (f) {
      const st = s.stock;
      const fqq = s.fq.trim().toLowerCase();
      const res = fqq ? PRODS.filter(p => p.name.toLowerCase().indexOf(fqq) > -1 || p.sku.indexOf(fqq) === 0).slice(0, 6) : [];
      const errs = this.errors();
      const dLines = f.lines.map(l => ({ pid: l.pid, delta: this.delta(l, f) || 0 }));
      const sm = this.sums(dLines);
      const nInc = dLines.filter(l => l.delta > 0).length, nDec = dLines.filter(l => l.delta < 0).length;
      const modeDef = [
        ['dec', 'Decrease', 'Spoilage, damage, waste, missing', '\u2212', TONE.error],
        ['inc', 'Increase', 'Found stock, returns, bonus', '+', TONE.success],
        ['count', 'Stock count', 'Enter counted qty, we work out the difference', '=', TONE.info]
      ];
      fv = {
        modes: modeDef.map(m => {
          const on = f.mode === m[0];
          return {
            name: m[1], sub: m[2], icon: m[3],
            onClick: () => this.setForm({ mode: m[0], lines: f.lines.map(l => ({ ...l, qty: '', reason: '', dir: m[0] === 'inc' ? 1 : -1 })) }),
            style: 'display:flex;gap:11px;align-items:flex-start;border-radius:11px;padding:12px 13px;cursor:pointer;'
              + (on ? 'background:' + m[4][0] + ';color:' + m[4][1] + ';box-shadow:inset 0 0 0 1.5px ' + m[4][2] + ';' : 'border:1px solid #dfe6ee;color:#0f1c2b;'),
            iconStyle: 'width:28px;height:28px;border-radius:8px;display:grid;place-items:center;font-size:16px;font-weight:700;flex:none;'
              + (on ? 'background:' + m[4][2] + ';color:#ffffff;' : 'background:#eef2f6;color:#5d6b7d;')
          };
        }),
        isCount: f.mode === 'count',
        addAll: () => this.setForm({ lines: PRODS.filter(p => !f.lines.some(l => l.pid === p.id)).map(p => ({ pid: p.id, qty: '', dir: -1, reason: '' })).concat([]).reduce((a, x) => a.concat([x]), f.lines.slice()) }),
        fLoc: f.loc, fRef: 'SA-0' + s.next, fNotes: f.notes, fq: s.fq,
        onLoc: e => this.setForm({ loc: e.target.value }),
        onNotes: e => this.setForm({ notes: e.target.value }),
        onFq: e => this.setState({ fq: e.target.value }),
        onFqKey: e => {
          if (e.key === 'Enter') { const hit = PRODS.find(p => p.sku === s.fq.trim()) || (res.length === 1 ? res[0] : null); if (hit) this.addLine(hit.id); }
          else if (e.key === 'Escape') this.setState({ fq: '' });
        },
        showResults: !!fqq, noResults: !!fqq && !res.length,
        results: res.map(p => ({ name: p.name, sku: p.sku, stock: this.q(st[f.loc][p.id], p.unit) + ' on hand', onClick: () => this.addLine(p.id) })),
        qtyHead: f.mode === 'count' ? 'Counted' : f.mode === 'inc' ? 'Add' : 'Remove',
        fEmpty: !f.lines.length,
        emptyText: f.mode === 'count' ? 'Add the products you counted, or add all products for a full count.' : 'Search above to add the products you are adjusting.',
        fLines: f.lines.map((l, i) => {
          const p = this.prod(l.pid);
          const on = st[f.loc][l.pid];
          const d = this.delta(l, f);
          const after = d === null ? null : rq(on + d);
          const neg = after !== null && after < -1e-9;
          const dirReason = d === null ? (f.mode === 'inc' ? 'inc' : 'dec') : d > 0 ? 'inc' : 'dec';
          const t = d === null || Math.abs(d) < 1e-9 ? null : d > 0 ? TONE.success : TONE.error;
          return {
            n: i + 1, name: p.name, unit: p.unit, qty: l.qty,
            ph: f.mode === 'count' ? String(rq(on)) : '0',
            hint: neg ? 'Only ' + this.q(on, p.unit) + ' on hand' : d !== null && f.mode === 'count' ? (Math.abs(d) < 1e-9 ? 'Matches system' : (d > 0 ? '+' : '\u2212') + this.q(Math.abs(d), p.unit) + ' vs system') : 'SKU ' + p.sku + ' \u00b7 ' + this.money(p.cost) + ' / ' + p.unit,
            hintStyle: neg ? 'font-size:11px;color:#b02020;font-weight:600' : t && f.mode === 'count' ? 'font-size:11px;font-weight:600;color:' + t[1] : MONO + 'font-size:10.5px;color:#8b98a8',
            onHand: this.q(on, p.unit),
            showDir: false,
            qtyBox: 'display:flex;align-items:center;height:32px;border-radius:8px;background:#ffffff;border:1px solid ' + (neg ? '#d93a3a' : t ? t[2] : '#dfe6ee'),
            onQty: e => { const nv = e.target.value.replace(/[^0-9.]/g, ''); this.setState(ss => ({ form: { ...ss.form, lines: ss.form.lines.map((x, j) => j === i ? { ...x, qty: nv } : x) } })); },
            newStock: after === null ? '\u2014' : this.q(Math.max(0, after), p.unit),
            newStyle: MONO + 'font-size:12px;text-align:right;' + (t ? 'font-weight:600;color:' + t[1] + ';' : 'color:#9aa7b6;'),
            reason: l.reason,
            reasons: REASONS[dirReason].map(n => ({ name: n })),
            onReason: e => { const v = e.target.value; this.setState(ss => ({ form: { ...ss.form, lines: ss.form.lines.map((x, j) => j === i ? { ...x, reason: v } : x) } })); },
            reasonStyle: 'width:100%;height:32px;border:1px solid ' + (t && !l.reason ? '#e3c38a' : '#dfe6ee') + ';border-radius:8px;padding:0 6px;font-family:\'Public Sans\',sans-serif;font-size:12px;background:' + (t && !l.reason ? '#fffaf0' : '#ffffff') + ';outline:none;cursor:pointer;min-width:0',
            value: d === null ? '\u2014' : this.signed(r2(d * p.cost)),
            valueStyle: MONO + 'font-size:12.5px;text-align:right;' + (t ? 'color:' + t[1] + ';' : 'color:#9aa7b6;'),
            rowStyle: 'display:grid;grid-template-columns:24px minmax(0,1.9fr) 0.8fr 1.3fr 0.9fr 1.3fr 0.9fr 28px;gap:10px;padding:9px 16px;border-bottom:1px solid #f0f4f8;align-items:center;'
              + (t ? 'box-shadow:inset 3px 0 0 ' + t[2] + ';' : ''),
            remove: () => this.setState(ss => ({ form: { ...ss.form, lines: ss.form.lines.filter((_, j) => j !== i) } }))
          };
        }),
        sInc: this.money(sm.inc), sDec: this.money(sm.dec),
        sIncN: nInc + (nInc === 1 ? ' line' : ' lines'), sDecN: nDec + (nDec === 1 ? ' line' : ' lines'),
        sNet: this.signed(sm.net),
        sNetStyle: MONO + 'font-size:20px;font-weight:600;letter-spacing:-0.02em;' + (sm.net < 0 ? 'color:#b02020;' : sm.net > 0 ? 'color:#0f6b43;' : ''),
        needsApproval: sm.dec > 500,
        hasErrors: f.lines.length > 0 && errs.length > 0,
        errors: errs.slice(0, 5).map(m => ({ msg: m })),
        postLabel: sm.dec > 500 ? 'Post for approval' : 'Post adjustment',
        postBtnStyle: 'height:34px;padding:0 15px;border-radius:8px;display:flex;align-items:center;font-size:12.5px;font-weight:600;color:#ffffff;cursor:pointer;'
          + (errs.length ? 'background:#9fbde0;' : 'background:#1668c9;'),
        post: () => this.post(false)
      };
    }

    const a = all.find(x => x.ref === s.sel) || all[0];
    const dsm = this.sums(a.lines);
    const reversedBy = s.reversed[a.ref];
    const c = s.confirm;

    return {
      nav: NAV.map(n => {
        const on = n[0] === 'Stock Adjustment';
        return {
          name: n[0], key: n[1],
          style: 'display:flex;align-items:center;gap:10px;padding:8px 10px;border-radius:8px;cursor:pointer;font-size:13px;font-weight:500;margin-bottom:1px;'
            + (on ? 'background:rgba(255,255,255,.13);color:#ffffff;box-shadow:inset 2px 0 0 #4da3e8;' : 'color:#c4dced;'),
          iconStyle: 'width:20px;height:20px;border-radius:5px;display:grid;place-items:center;' + MONO + 'font-size:9px;font-weight:600;flex:none;'
            + (on ? 'background:#4da3e8;color:#06283a;' : 'background:rgba(255,255,255,.1);color:#9dbdd2;'),
          caretStyle: 'color:#7fa3bb;font-size:11px;flex:none;' + (n[2] ? '' : 'visibility:hidden;')
        };
      }),
      isList: s.view === 'list', isForm: s.view === 'form' && !!f, isDetails: s.view === 'details',
      goList: () => this.setState({ view: 'list', form: null }),
      newAdj: () => this.openForm('dec'),
      newCount: () => this.openForm('count'),
      exportList: () => this.toast('Exported ' + filt.length + ' adjustments to CSV', 'neutral'),
      listSub: 'September 2026 \u00b7 ' + all.length + ' adjustments \u00b7 net ' + this.signed(r2(month.inc - month.dec)) + ' at cost',
      mDec: this.money(month.dec), mInc: this.money(month.inc),
      topReasons: top.map(k => ({ name: k, v: this.money(byReason[k]) })),
      typeTabs: ['All', 'Decrease', 'Increase', 'Stock count'].map(n => ({
        name: n, count: n === 'All' ? all.length : all.filter(x => x.type === tabKey[n]).length,
        onClick: () => this.setState({ tab: n }),
        style: this.pageTab(s.tab === n),
        countStyle: MONO + 'font-size:10.5px;border-radius:4px;padding:1px 5px;margin-left:6px;' + (s.tab === n ? 'background:#eaf1fb;color:#14549e;' : 'background:#eef2f6;color:#7d8b9c;')
      })),
      q: s.q, onQ: e => this.setState({ q: e.target.value }),
      locOpts: LOCS,
      allReasons: REASONS.dec.concat(REASONS.inc).map(n => ({ name: n })),
      locFilter: s.locF, reasonFilter: s.reasonF,
      onLocFilter: e => this.setState({ locF: e.target.value }),
      onReasonFilter: e => this.setState({ reasonF: e.target.value }),
      hasFilters: s.locF !== 'all' || s.reasonF !== 'all' || !!qq,
      clearFilters: () => this.setState({ locF: 'all', reasonF: 'all', q: '' }),
      rows: filt.map(x => {
        const sm = this.sums(x.lines);
        const rs = x.lines.map(l => l.reason).filter((v, i, arr) => arr.indexOf(v) === i);
        return {
          ref: x.ref, date: x.date, loc: this.loc(x.loc).name, by: x.by,
          type: TYPE[x.type][0], badge: this.badge(TONE[TYPE[x.type][1]]),
          items: x.lines.map(l => this.prod(l.pid).name).join(', '),
          reasons: x.lines.length + (x.lines.length === 1 ? ' line' : ' lines') + ' \u00b7 ' + rs.join(', '),
          value: this.signed(sm.net),
          valueStyle: MONO + 'font-size:12.5px;text-align:right;font-weight:600;' + (sm.net < 0 ? 'color:#b02020;' : 'color:#0f6b43;'),
          open: () => this.setState({ view: 'details', sel: x.ref })
        };
      }),
      noRows: !filt.length,
      rowCount: filt.length,
      rowTotal: this.signed(netAll),
      rowTotalStyle: MONO + 'font-size:13px;text-align:right;font-weight:700;' + (netAll < 0 ? 'color:#b02020;' : 'color:#0f6b43;'),

      ...fv,

      d: {
        ref: a.ref, type: TYPE[a.type][0], badge: this.badge(TONE[TYPE[a.type][1]]),
        reversed: !!reversedBy, reversedBy: reversedBy || '',
        canReverse: !reversedBy && !a.reverses,
        sub: this.loc(a.loc).name + ' \u00b7 ' + a.date + ' \u00b7 ' + a.lines.length + (a.lines.length === 1 ? ' line' : ' lines') + ' \u00b7 posted by ' + a.by,
        lines: a.lines.map((l, i) => {
          const p = this.prod(l.pid);
          const t = l.delta > 0 ? TONE.success : TONE.error;
          return {
            n: i + 1, name: p.name, sku: p.sku, cost: this.money(p.cost) + ' / ' + p.unit,
            before: this.q(l.before, p.unit), after: this.q(l.before + l.delta, p.unit),
            change: (l.delta > 0 ? '+' : '\u2212') + this.q(Math.abs(l.delta), p.unit),
            chgStyle: MONO + 'font-size:12px;font-weight:600;border-radius:5px;padding:2px 7px;background:' + t[0] + ';color:' + t[1],
            reason: l.reason,
            value: this.signed(r2(l.delta * p.cost)),
            valueStyle: MONO + 'font-size:12.5px;text-align:right;color:' + t[1]
          };
        }),
        foot: 'Stock updated at ' + this.loc(a.loc).name + ' when posted',
        net: 'Net ' + this.signed(dsm.net),
        netStyle: MONO + 'font-size:13px;font-weight:700;' + (dsm.net < 0 ? 'color:#b02020;' : 'color:#0f6b43;'),
        loc: this.loc(a.loc).name, date: a.date, by: a.by, approved: a.approved,
        inc: this.money(dsm.inc), dec: this.money(dsm.dec),
        hasNotes: !!a.notes, notes: a.notes,
        journal: [
          { acct: 'Account', dr: 'Debit', cr: 'Credit' },
          dsm.dec ? { acct: 'Stock write-off (expense)', dr: this.money(dsm.dec), cr: '' } : null,
          dsm.dec ? { acct: 'Inventory', dr: '', cr: this.money(dsm.dec) } : null,
          dsm.inc ? { acct: 'Inventory', dr: this.money(dsm.inc), cr: '' } : null,
          dsm.inc ? { acct: 'Stock gain (income)', dr: '', cr: this.money(dsm.inc) } : null
        ].filter(Boolean)
      },
      printDoc: () => this.toast('Adjustment ' + a.ref + ' sent to printer', 'neutral'),
      askReverse: () => this.setState({ confirm: { kind: 'reverse', title: 'Reverse ' + a.ref + '?', text: 'This posts a new adjustment with every line flipped, putting stock back to where it was. ' + a.ref + ' stays in the history, marked as reversed.', btn: 'Reverse' } }),

      confirmOpen: !!c,
      confirmTitle: c ? c.title : '', confirmText: c ? c.text : '', confirmBtn: c ? c.btn : '',
      confirmBtnStyle: 'height:40px;padding:0 16px;border-radius:9px;color:#ffffff;display:flex;align-items:center;font-size:13px;font-weight:700;cursor:pointer;background:' + (c && c.kind === 'reverse' ? '#c62828' : '#1668c9'),
      closeConfirm: () => this.setState({ confirm: null }),
      confirmGo: () => { if (!c) return; if (c.kind === 'reverse') this.reverse(a); else this.post(true); },

      toasts: s.toasts.map(x => ({ msg: x.msg, dot: 'width:8px;height:8px;border-radius:50%;flex:none;background:' + TONE[x.tone][2] }))
    };
  }
}


return Component;
})();
});
