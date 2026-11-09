// TransfersLogic
DC.add(function () {
const TransfersLogic = (() => {

const NAV = [
  ['Home','Hm',0],['User Management','Us',1],['Contacts','Ct',1],['Products','Pr',1],
  ['Manufacturing','Mf',0],['Purchases','Pu',1],['Sell','Sl',1],['Stock Transfers','St',0],
  ['Stock Adjustment','Sa',0],['Expenses','Ex',1],['Payment Accounts','Pa',1],['Accounting','Ac',1],
  ['Reports','Rp',1],['Bookings','Bk',0],['Kitchen','Kt',0],['Orders','Or',0],
  ['Settings','Se',1],['HRM','Hr',1]
];
const LOCS = [
  {id:'main', name:'Main branch', short:'Main', sub:'Haldon St, Lakemba'},
  {id:'bank', name:'Bankstown shop', short:'Bankstown', sub:'Chapel Rd, Bankstown'},
  {id:'cold', name:'Cold store', short:'Cold store', sub:'Warehouse, Chullora'}
];
const PRODS = [
  {id:1, name:'Beef Mince', sku:'6603', unit:'kg', cost:7.20, alert:15},
  {id:2, name:'Beef Rump Steak', sku:'6600', unit:'kg', cost:16.80, alert:10},
  {id:3, name:'Beef Scotch Fillet', sku:'6595', unit:'kg', cost:19.50, alert:8},
  {id:4, name:'Beef T-Bone', sku:'6598', unit:'kg', cost:20.40, alert:8},
  {id:5, name:'Lamb Chops', sku:'6622', unit:'kg', cost:19.90, alert:10},
  {id:6, name:'Lamb Shoulder', sku:'6621', unit:'kg', cost:13.10, alert:8},
  {id:7, name:'Lamb Leg', sku:'6624', unit:'kg', cost:11.60, alert:8},
  {id:8, name:'Chicken Breast', sku:'6642', unit:'kg', cost:8.40, alert:15},
  {id:9, name:'Chicken Thigh', sku:'6641', unit:'kg', cost:5.90, alert:15},
  {id:10, name:'Goat Curry Cut', sku:'6631', unit:'kg', cost:15.20, alert:6},
  {id:11, name:'Basmati Rice 5kg', sku:'7102', unit:'ea', cost:11.50, alert:10},
  {id:12, name:'Spice Mix 200g', sku:'7120', unit:'ea', cost:3.10, alert:20},
  {id:13, name:'Pita Bread 6 pack', sku:'7131', unit:'ea', cost:2.40, alert:12}
];
const STOCK0 = {
  main: {1:54,2:41,3:0,4:18,5:27,6:3,7:12,8:46,9:9,10:12,11:44,12:60,13:4},
  bank: {1:6,2:4,3:2,4:0,5:11,6:5,7:3,8:7,9:21,10:1,11:8,12:14,13:18},
  cold: {1:180,2:95,3:62,4:40,5:88,6:46,7:70,8:140,9:120,10:34,11:120,12:0,13:0}
};
const ST = {
  draft:['Draft','neutral'], transit:['In transit','info'], received:['Received','success'],
  variance:['Received with variance','warn'], cancelled:['Cancelled','error']
};
const TONE = {
  info:['#eaf1fb','#14549e','#1668c9'], warn:['#fdf1dd','#8a5300','#d98c1a'], error:['#fdeaea','#b02020','#d93a3a'],
  success:['#e7f6ee','#0f6b43','#2f9e63'], neutral:['#eef2f6','#5d6b7d','#8fa3b6']
};
const MONO = "font-family:'IBM Plex Mono',monospace;";
const r2 = n => Math.round(n * 100) / 100;
const rq = n => Math.round(n * 1000) / 1000;
const L = (a, b, c) => ({ pid:a, qty:b, recv:c === undefined ? null : c });

const SEED = [
  {ref:'TR-0418', date:'29/09/2026', from:'main', to:'bank', status:'transit', ship:25, by:'Aminul', notes:'Two crates on van 2. Driver Rafi, leaving 10:30.',
    lines:[L(2,8), L(5,6), L(8,10)], log:[['Sent by Aminul','29/09 · 10:24','info'],['Created by Aminul','29/09 · 10:11','neutral']]},
  {ref:'TR-0417', date:'27/09/2026', from:'cold', to:'main', status:'received', ship:0, by:'Sara', notes:'',
    lines:[L(1,40,40), L(3,20,20), L(7,15,15), L(11,24,24)], log:[['Received in full by Karim','27/09 · 15:02','success'],['Sent by Sara','27/09 · 13:40','info'],['Created by Sara','27/09 · 13:31','neutral']]},
  {ref:'TR-0416', date:'25/09/2026', from:'main', to:'bank', status:'variance', ship:25, by:'Aminul', notes:'',
    lines:[L(1,12,12), L(9,10,9.4), L(13,12,10)], log:[['Received by Karim, 0.6 kg and 2 units short','25/09 · 16:45','warn'],['Sent by Aminul','25/09 · 14:10','info'],['Created by Aminul','25/09 · 14:02','neutral']]},
  {ref:'TR-0415', date:'24/09/2026', from:'cold', to:'bank', status:'draft', ship:40, by:'Sara', notes:'Waiting on the Friday van.',
    lines:[L(4,10), L(6,8), L(10,6)], log:[['Created by Sara','24/09 · 09:18','neutral']]},
  {ref:'TR-0414', date:'20/09/2026', from:'main', to:'cold', status:'received', ship:0, by:'Aminul', notes:'Overflow from the Saturday delivery.',
    lines:[L(8,30,30), L(9,25,25)], log:[['Received in full by Sara','20/09 · 17:20','success'],['Sent by Aminul','20/09 · 16:05','info'],['Created by Aminul','20/09 · 16:01','neutral']]},
  {ref:'TR-0413', date:'17/09/2026', from:'bank', to:'main', status:'cancelled', ship:0, by:'Karim', notes:'',
    lines:[L(12,10)], log:[['Cancelled by Karim, stock not needed','17/09 · 11:40','error'],['Created by Karim','17/09 · 11:12','neutral']]},
  {ref:'TR-0412', date:'12/09/2026', from:'cold', to:'main', status:'received', ship:0, by:'Sara', notes:'',
    lines:[L(2,30,30), L(5,20,20), L(6,12,12)], log:[['Received in full by Aminul','12/09 · 12:15','success'],['Sent by Sara','12/09 · 10:50','info'],['Created by Sara','12/09 · 10:42','neutral']]}
];

class Component extends ModBase {
  _id = 0;
  initialState = {
    view: 'list', status: 'All', q: '', fromF: 'all', toF: 'all',
    transfers: SEED, stock: JSON.parse(JSON.stringify(STOCK0)), next: 419,
    sel: 'TR-0418', recv: {}, form: null, fq: '', toasts: []
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
  value(lines) { return r2(lines.reduce((a, l) => a + this.prod(l.pid).cost * (parseFloat(l.qty) || 0), 0)); }
  now() { const d = new Date(); return '29/09 \u00b7 ' + String(d.getHours()).padStart(2, '0') + ':' + String(d.getMinutes()).padStart(2, '0'); }

  newForm() {
    this.setState({ view: 'form', fq: '', form: { edit: null, ref: 'TR-0' + this.state.next, from: 'cold', to: 'bank', ship: '', notes: '', lines: [] } });
  }
  editForm(t) {
    this.setState({ view: 'form', fq: '', form: { edit: t.ref, ref: t.ref, from: t.from, to: t.to, ship: t.ship ? String(t.ship) : '', notes: t.notes,
      lines: t.lines.map(l => ({ pid: l.pid, qty: String(l.qty) })) } });
  }
  setForm(patch) { this.setState(s => ({ form: { ...s.form, ...patch } })); }
  addLine(pid) {
    const f = this.state.form;
    if (f.lines.some(l => l.pid === pid)) { this.toast(this.prod(pid).name + ' is already on this transfer', 'warn'); this.setState({ fq: '' }); return; }
    const p = this.prod(pid);
    const has = this.state.stock[f.from][pid];
    if (has <= 0) { this.toast(p.name + ' is out of stock at ' + this.loc(f.from).short, 'error'); return; }
    const dst = this.state.stock[f.to][pid];
    const want = Math.max(p.unit === 'kg' ? 5 : 6, p.alert * 2 - dst);
    const qty = String(rq(Math.min(has, want)));
    this.setForm({ lines: f.lines.concat([{ pid, qty }]) });
    this.setState({ fq: '' });
  }
  formErrors() {
    const f = this.state.form, st = this.state.stock;
    const e = [];
    if (f.from === f.to) e.push('Pick two different locations');
    if (!f.lines.length) e.push('Add at least one product');
    f.lines.forEach(l => {
      const v = parseFloat(l.qty), p = this.prod(l.pid);
      if (!v || v <= 0) e.push(p.name + ': enter a quantity');
      else if (v > st[f.from][l.pid] + 1e-9) e.push(p.name + ': only ' + this.q(st[f.from][l.pid], p.unit) + ' at ' + this.loc(f.from).short);
      else if (p.unit === 'ea' && v % 1) e.push(p.name + ': whole units only');
    });
    return e;
  }
  saveForm(send) {
    const s = this.state, f = s.form;
    const errs = this.formErrors();
    const hardForDraft = errs.filter(x => x.indexOf('different') > -1 || x.indexOf('at least') > -1);
    if (send ? errs.length : hardForDraft.length) { this.toast((send ? errs : hardForDraft)[0], 'error'); return; }
    const by = 'Aminul', when = this.now();
    const lines = f.lines.map(l => L(l.pid, rq(parseFloat(l.qty) || 0)));
    const prev = f.edit ? s.transfers.find(t => t.ref === f.edit) : null;
    const log = (prev ? prev.log : [[ 'Created by ' + by, when, 'neutral' ]]).slice();
    if (prev) log.unshift(['Edited by ' + by, when, 'neutral']);
    if (send) log.unshift(['Sent by ' + by, when, 'info']);
    const t = { ref: f.ref, date: '29/09/2026', from: f.from, to: f.to, status: send ? 'transit' : 'draft', ship: r2(parseFloat(f.ship) || 0), by, notes: f.notes, lines, log };
    let stock = s.stock;
    if (send) {
      stock = JSON.parse(JSON.stringify(s.stock));
      lines.forEach(l => { stock[f.from][l.pid] = rq(stock[f.from][l.pid] - l.qty); });
    }
    const transfers = prev ? s.transfers.map(x => x.ref === f.edit ? t : x) : [t].concat(s.transfers);
    this.setState({ transfers, stock, next: prev ? s.next : s.next + 1, view: 'details', sel: t.ref, form: null, recv: {} });
    this.toast(send ? t.ref + ' sent \u00b7 stock deducted from ' + this.loc(f.from).short : t.ref + ' saved as draft', send ? 'success' : 'neutral');
  }
  sendDraft(t) {
    const st = this.state.stock;
    const short = t.lines.find(l => l.qty > st[t.from][l.pid] + 1e-9);
    if (short) { const p = this.prod(short.pid); this.toast('Only ' + this.q(st[t.from][short.pid], p.unit) + ' of ' + p.name + ' at ' + this.loc(t.from).short + '. Edit the draft first.', 'error'); return; }
    const stock = JSON.parse(JSON.stringify(st));
    t.lines.forEach(l => { stock[t.from][l.pid] = rq(stock[t.from][l.pid] - l.qty); });
    this.updateT(t.ref, { status: 'transit', log: [['Sent by Aminul', this.now(), 'info']].concat(t.log) }, { stock });
    this.toast(t.ref + ' sent \u00b7 stock deducted from ' + this.loc(t.from).short, 'success');
  }
  receive(t) {
    const R = this.state.recv;
    const got = t.lines.map(l => { const v = R[l.pid]; return v === undefined ? l.qty : parseFloat(v); });
    const bad = got.findIndex((v, i) => isNaN(v) || v < 0 || v > t.lines[i].qty + 1e-9);
    if (bad > -1) { this.toast(this.prod(t.lines[bad].pid).name + ': received must be between 0 and what was sent', 'error'); return; }
    const stock = JSON.parse(JSON.stringify(this.state.stock));
    t.lines.forEach((l, i) => { stock[t.to][l.pid] = rq(stock[t.to][l.pid] + got[i]); });
    const variance = t.lines.some((l, i) => Math.abs(l.qty - got[i]) > 1e-9);
    const lossV = r2(t.lines.reduce((a, l, i) => a + (l.qty - got[i]) * this.prod(l.pid).cost, 0));
    this.updateT(t.ref, {
      status: variance ? 'variance' : 'received',
      lines: t.lines.map((l, i) => ({ ...l, recv: rq(got[i]) })),
      log: [[variance ? 'Received by Aminul, ' + this.money(lossV) + ' short written off' : 'Received in full by Aminul', this.now(), variance ? 'warn' : 'success']].concat(t.log)
    }, { stock, recv: {} });
    this.toast(variance ? 'Received with variance \u00b7 ' + this.money(lossV) + ' written off' : 'Received in full \u00b7 stock added to ' + this.loc(t.to).short, variance ? 'warn' : 'success');
  }
  updateT(ref, patch, extra) {
    this.setState(s => ({ transfers: s.transfers.map(x => x.ref === ref ? { ...x, ...patch } : x), ...(extra || {}) }));
  }

  renderVals() {
    const s = this.state;
    const T = s.transfers;
    const qq = s.q.trim().toLowerCase();
    const filt = T.filter(t =>
      (s.status === 'All' || (s.status === 'Received' ? (t.status === 'received' || t.status === 'variance') : ST[t.status][0] === s.status))
      && (s.fromF === 'all' || t.from === s.fromF) && (s.toF === 'all' || t.to === s.toF)
      && (!qq || t.ref.toLowerCase().indexOf(qq) > -1 || t.lines.some(l => this.prod(l.pid).name.toLowerCase().indexOf(qq) > -1)));
    const tabs = ['All', 'Draft', 'In transit', 'Received', 'Cancelled'];
    const cnt = n => n === 'All' ? T.length : n === 'Received' ? T.filter(t => t.status === 'received' || t.status === 'variance').length : T.filter(t => ST[t.status][0] === n).length;
    const transit = T.filter(t => t.status === 'transit');

    const f = s.form;
    let formVals = {};
    if (f) {
      const st = s.stock;
      const fqq = s.fq.trim().toLowerCase();
      const res = fqq ? PRODS.filter(p => p.name.toLowerCase().indexOf(fqq) > -1 || p.sku.indexOf(fqq) === 0).slice(0, 6) : [];
      const val = this.value(f.lines);
      const ship = r2(parseFloat(f.ship) || 0);
      const errs = this.formErrors();
      const sugg = f.from === f.to ? [] : PRODS.filter(p => st[f.to][p.id] < p.alert && st[f.from][p.id] > 0 && !f.lines.some(l => l.pid === p.id)).slice(0, 4);
      formVals = {
        formTitle: f.edit ? 'Edit ' + f.ref : 'New stock transfer',
        formCrumb: f.edit ? 'Edit ' + f.ref : 'New transfer',
        fFrom: f.from, fTo: f.to, fRef: f.ref, fShip: f.ship, fNotes: f.notes, fq: s.fq,
        onFrom: e => this.setForm({ from: e.target.value }),
        onTo: e => this.setForm({ to: e.target.value }),
        swap: () => this.setForm({ from: f.to, to: f.from }),
        onShip: e => this.setForm({ ship: e.target.value.replace(/[^0-9.]/g, '') }),
        onNotes: e => this.setForm({ notes: e.target.value }),
        onFq: e => this.setState({ fq: e.target.value }),
        onFqKey: e => {
          if (e.key === 'Enter') { const hit = PRODS.find(p => p.sku === s.fq.trim()) || (res.length === 1 ? res[0] : null); if (hit) this.addLine(hit.id); }
          else if (e.key === 'Escape') this.setState({ fq: '' });
        },
        sameLoc: f.from === f.to,
        toSelectStyle: 'width:100%;height:38px;border:1px solid ' + (f.from === f.to ? '#d93a3a' : '#dfe6ee') + ';border-radius:8px;padding:0 10px;font-family:\'Public Sans\',sans-serif;font-size:13px;background:#ffffff;outline:none;cursor:pointer',
        fromShort: this.loc(f.from).short, toShort: this.loc(f.to).short,
        showResults: !!fqq, noResults: !!fqq && !res.length,
        results: res.map(p => {
          const has = st[f.from][p.id];
          return {
            name: p.name, sku: p.sku,
            stock: has > 0 ? this.q(has, p.unit) + ' at ' + this.loc(f.from).short : 'None at ' + this.loc(f.from).short,
            stockStyle: MONO + 'font-size:11.5px;' + (has > 0 ? 'color:#5d6b7d;' : 'color:#b02020;'),
            style: 'display:flex;align-items:center;gap:10px;padding:9px 14px;border-bottom:1px solid #f0f4f8;cursor:pointer;' + (has > 0 ? '' : 'opacity:.55;'),
            onClick: () => this.addLine(p.id)
          };
        }),
        hasSuggest: sugg.length > 0,
        suggestLabel: 'Low at ' + this.loc(f.to).short + ':',
        suggest: sugg.map(p => ({ name: p.name, note: this.q(st[f.to][p.id], p.unit) + ' left', onClick: () => this.addLine(p.id) })),
        fEmpty: !f.lines.length,
        fLines: f.lines.map((l, i) => {
          const p = this.prod(l.pid);
          const v = parseFloat(l.qty) || 0;
          const has = st[f.from][l.pid], dst = st[f.to][l.pid];
          const over = v > has + 1e-9;
          const after = rq(dst + v);
          return {
            n: i + 1, name: p.name, unit: p.unit, qty: l.qty,
            hint: over ? 'Only ' + this.q(has, p.unit) + ' available' : 'SKU ' + p.sku + ' \u00b7 ' + this.money(p.cost) + ' / ' + p.unit,
            hintStyle: over ? 'font-size:11px;color:#b02020;font-weight:600' : MONO + 'font-size:10.5px;color:#8b98a8',
            src: this.q(has, p.unit),
            dest: this.q(after, p.unit),
            destStyle: MONO + 'font-size:12px;text-align:right;' + (after < p.alert ? 'color:#8a5300;' : 'color:#0f6b43;font-weight:600;'),
            value: this.money(p.cost * v),
            qtyBox: 'display:flex;align-items:center;height:32px;border-radius:8px;background:#ffffff;border:1px solid ' + (over || !v ? '#d93a3a' : '#dfe6ee'),
            onQty: e => { const nv = e.target.value.replace(/[^0-9.]/g, ''); this.setState(ss => ({ form: { ...ss.form, lines: ss.form.lines.map((x, j) => j === i ? { ...x, qty: nv } : x) } })); },
            remove: () => this.setState(ss => ({ form: { ...ss.form, lines: ss.form.lines.filter((_, j) => j !== i) } }))
          };
        }),
        sLines: f.lines.length, sValue: this.money(val), sShip: this.money(ship), sTotal: this.money(val + ship),
        hasErrors: f.lines.length > 0 && errs.length > 0,
        errors: errs.map(m => ({ msg: m })),
        sendBtnStyle: 'height:34px;padding:0 15px;border-radius:8px;display:flex;align-items:center;font-size:12.5px;font-weight:600;color:#ffffff;cursor:pointer;'
          + (errs.length ? 'background:#9fbde0;' : 'background:#1668c9;'),
        saveDraft: () => this.saveForm(false),
        sendNow: () => this.saveForm(true),
        cancelForm: () => this.setState(ss => ({ view: ss.form && ss.form.edit ? 'details' : 'list', form: null }))
      };
    }

    const t = T.find(x => x.ref === s.sel) || T[0];
    const done = t.status === 'received' || t.status === 'variance';
    const R = s.recv;
    const dLines = t.lines.map((l, i) => {
      const p = this.prod(l.pid);
      const editable = t.status === 'transit';
      const got = editable ? (R[l.pid] === undefined ? l.qty : parseFloat(R[l.pid])) : l.recv;
      const diff = got === null || isNaN(got) ? null : rq(got - l.qty);
      return {
        n: i + 1, name: p.name, sku: p.sku, cost: this.money(p.cost) + ' / ' + p.unit, unit: p.unit,
        sent: this.q(l.qty, p.unit),
        editable, readonly: !editable,
        recvInput: R[l.pid] === undefined ? String(l.qty) : R[l.pid],
        recv: l.recv === null ? '\u2014' : this.q(l.recv, p.unit),
        recvBox: 'display:flex;align-items:center;height:32px;border-radius:8px;background:#ffffff;border:1px solid ' + (diff !== null && diff < 0 ? '#d98c1a' : diff > 0 || diff === null ? '#d93a3a' : '#bfe5d1'),
        onRecv: e => { const nv = e.target.value.replace(/[^0-9.]/g, ''); this.setState(ss => ({ recv: { ...ss.recv, [l.pid]: nv } })); },
        variance: diff === null || (!editable && !done) ? '\u2014' : Math.abs(diff) < 1e-9 ? '0' : (diff > 0 ? '+' : '\u2212') + this.q(Math.abs(diff), p.unit),
        varStyle: MONO + 'font-size:12.5px;text-align:right;' + (diff !== null && diff < -1e-9 && (editable || done) ? 'color:#b02020;font-weight:600;' : 'color:#9aa7b6;'),
        value: this.money(p.cost * l.qty)
      };
    });
    const lossV = r2(t.lines.reduce((a, l) => {
      const got = t.status === 'transit' ? (R[l.pid] === undefined ? l.qty : parseFloat(R[l.pid]) || 0) : (l.recv === null ? l.qty : l.recv);
      return a + Math.max(0, l.qty - got) * this.prod(l.pid).cost;
    }, 0));
    const val = this.value(t.lines);
    const stepDef = [
      ['Created', t.log[t.log.length - 1][1] + ' \u00b7 ' + t.by],
      ['Sent', t.status === 'draft' ? 'Not sent yet' : t.status === 'cancelled' ? 'Cancelled' : (t.log.find(x => x[0].indexOf('Sent') === 0) || ['', ''])[1]],
      ['Received', done ? t.log[0][1] : t.status === 'transit' ? 'Waiting at ' + this.loc(t.to).short : '\u2014']
    ];
    const reached = t.status === 'draft' || t.status === 'cancelled' ? 0 : t.status === 'transit' ? 1 : 2;
    const cancelled = t.status === 'cancelled';

    return {
      nav: NAV.map(n => {
        const on = n[0] === 'Stock Transfers';
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
      newTransfer: () => this.newForm(),
      exportList: () => this.toast('Exported ' + filt.length + ' transfers to CSV', 'neutral'),
      listSub: 'September 2026 \u00b7 ' + T.length + ' transfers across ' + LOCS.length + ' locations',
      statusTabs: tabs.map(n => ({
        name: n, count: cnt(n),
        onClick: () => this.setState({ status: n }),
        style: this.pageTab(s.status === n),
        countStyle: MONO + 'font-size:10.5px;border-radius:4px;padding:1px 5px;margin-left:6px;' + (s.status === n ? 'background:#eaf1fb;color:#14549e;' : 'background:#eef2f6;color:#7d8b9c;')
      })),
      showTransitBanner: transit.length > 0 && s.status !== 'In transit',
      transitBanner: transit.length + (transit.length === 1 ? ' transfer is' : ' transfers are') + ' on the road, worth ' + this.money(transit.reduce((a, x) => a + this.value(x.lines), 0)) + ' at cost. The receiving branch confirms counts on arrival.',
      showTransit: () => this.setState({ status: 'In transit' }),
      q: s.q, onQ: e => this.setState({ q: e.target.value }),
      locOpts: LOCS,
      fromFilter: s.fromF, toFilter: s.toF,
      onFromFilter: e => this.setState({ fromF: e.target.value }),
      onToFilter: e => this.setState({ toF: e.target.value }),
      hasFilters: s.fromF !== 'all' || s.toF !== 'all' || !!qq,
      clearFilters: () => this.setState({ fromF: 'all', toF: 'all', q: '' }),
      rows: filt.map(x => ({
        ref: x.ref, date: x.date, from: this.loc(x.from).name, to: this.loc(x.to).name, by: x.by,
        items: x.lines.map(l => this.prod(l.pid).name).join(', '),
        lineCount: x.lines.length + (x.lines.length === 1 ? ' line' : ' lines'),
        value: this.money(this.value(x.lines) + x.ship),
        status: ST[x.status][0], badge: this.badge(TONE[ST[x.status][1]]),
        open: () => this.setState({ view: 'details', sel: x.ref, recv: {} })
      })),
      noRows: !filt.length,
      rowCount: filt.length,
      rowTotal: this.money(filt.reduce((a, x) => a + this.value(x.lines) + x.ship, 0)),

      ...formVals,

      d: {
        ref: t.ref, from: this.loc(t.from).name, to: this.loc(t.to).name, fromSub: this.loc(t.from).sub, toSub: this.loc(t.to).sub,
        status: ST[t.status][0], badge: this.badge(TONE[ST[t.status][1]]),
        sub: t.ref + ' \u00b7 ' + t.date + ' \u00b7 ' + t.lines.length + (t.lines.length === 1 ? ' line' : ' lines') + ' \u00b7 created by ' + t.by,
        isDraft: t.status === 'draft', isTransit: t.status === 'transit',
        receiveLabel: lossV > 0 ? 'Receive with ' + this.money(lossV) + ' short' : 'Receive in full',
        steps: stepDef.map((x, i) => {
          const hit = !cancelled && i <= reached;
          const cur = !cancelled && i === reached + 1 && t.status !== 'draft';
          return {
            label: i === 1 && cancelled ? 'Cancelled' : x[0], meta: x[1],
            mark: hit ? '\u2713' : i === 1 && cancelled ? '\u00d7' : String(i + 1),
            dot: 'width:26px;height:26px;border-radius:50%;display:grid;place-items:center;font-size:11.5px;font-weight:700;flex:none;'
              + (hit ? 'background:#2f9e63;color:#ffffff;' : i === 1 && cancelled ? 'background:#d93a3a;color:#ffffff;' : cur ? 'background:#ffffff;color:#1668c9;box-shadow:inset 0 0 0 2px #1668c9;' : 'background:#eef2f6;color:#8b98a8;'),
            line: 'flex:1;height:2px;margin:0 8px;' + (i === 2 ? 'visibility:hidden;' : '') + (!cancelled && i < reached ? 'background:#2f9e63;' : 'background:#e4eaf1;'),
            labelStyle: 'font-size:13px;font-weight:600;' + (hit || cur ? 'color:#0f1c2b;' : 'color:#8b98a8;')
          };
        }),
        lines: dLines,
        linesFoot: t.status === 'transit' ? 'Received quantities default to what was sent' : done ? 'Counts confirmed at ' + this.loc(t.to).short : 'Stock has not moved yet',
        loss: lossV > 0 && (done || t.status === 'transit') ? 'Transit loss ' + this.money(lossV) : '',
        lossStyle: MONO + 'font-size:12.5px;font-weight:600;color:#b02020',
        value: this.money(val), ship: this.money(t.ship), total: this.money(val + t.ship),
        hasNotes: !!t.notes, notes: t.notes,
        log: t.log.map(a => ({ what: a[0], when: a[1], dot: 'width:8px;height:8px;border-radius:50%;margin-top:5px;flex:none;background:' + TONE[a[2]][2] }))
      },
      printDoc: () => this.toast('Delivery note for ' + t.ref + ' sent to printer', 'neutral'),
      editDraft: () => this.editForm(t),
      sendDraft: () => this.sendDraft(t),
      cancelTransfer: () => { this.updateT(t.ref, { status: 'cancelled', log: [['Cancelled by Aminul', this.now(), 'error']].concat(t.log) }); this.toast(t.ref + ' cancelled', 'neutral'); },
      confirmReceive: () => this.receive(t),

      toasts: s.toasts.map(x => ({ msg: x.msg, dot: 'width:8px;height:8px;border-radius:50%;flex:none;background:' + TONE[x.tone][2] }))
    };
  }
}


return Component;
})();
});
