// AccountsLogic
import { ModBase } from "../../runtime/logic.js";

export const AccountsLogic = (() => {
const NAV = [
  ['Home','Hm',0],['User Management','Us',1],['Contacts','Ct',1],['Products','Pr',1],
  ['Manufacturing','Mf',0],['Purchases','Pu',1],['Sell','Sl',1],['Stock Transfers','St',0],
  ['Stock Adjustment','Sa',0],['Expenses','Ex',1],['Payment Accounts','Pa',1],['Accounting','Ac',1],
  ['Reports','Rp',1],['Bookings','Bk',0],['Kitchen','Kt',0],['Orders','Or',0],
  ['Settings','Se',1],['HRM','Hr',1]
];
const LOCS = [{id:'main', name:'Main branch'}, {id:'bank', name:'Bankstown shop'}, {id:'cold', name:'Cold store'}];
const KIND = {
  cash: { name:'Cash', icon:'$', c:['#e7f6ee','#0f6b43'] },
  bank: { name:'Bank', icon:'B', c:['var(--c-primary-soft)','var(--c-primary-text)'] },
  card: { name:'Card', icon:'C', c:['#f3ecfd','#5b34a8'] },
  wallet: { name:'Wallet', icon:'W', c:['#fdf1dd','#8a5300'] }
};
const TT = {
  sale:['Sale','success'], deposit:['Deposit','success'], purchase:['Purchase','error'], expense:['Expense','error'],
  xin:['Transfer in','info'], xout:['Transfer out','info'], fee:['Fee','neutral'], refund:['Refund','warn'], payment:['Card payment','info']
};
const TONE = {
  info:['var(--c-primary-soft)','var(--c-primary-text)','var(--c-primary)'], warn:['#fdf1dd','#8a5300','#d98c1a'], error:['#fdeaea','#b02020','#d93a3a'],
  success:['#e7f6ee','#0f6b43','#2f9e63'], neutral:['#eef2f6','#5d6b7d','#8fa3b6']
};
const DEFAULTS = [
  ['posCash','POS cash sales','Takings from the register land here'],
  ['posCard','Card settlements','Tyro payouts, usually next business day'],
  ['expenses','Expenses','Pre-selected on the expense form'],
  ['purchases','Supplier payments','Pre-selected when paying purchases']
];
const MONO = "font-family:'IBM Plex Mono',monospace;";
const r2 = n => Math.round(n * 100) / 100;
const T = (date, type, desc, party, ref, amt) => ({ date, type, desc, party, ref, amt });
const ACCTS = [
  { id:'cash', name:'Cash drawer', kind:'cash', loc:'main', num:'', bsb:'', limit:0, bal:1840.50, active:true, defs:['posCash'],
    tx:[T('29/09','sale','POS takings','Register 1 \u00b7 38 sales','Z-0929',1612.40), T('28/09','xout','Banked to CBA Business','Friday takings','TF-0088',-3200), T('28/09','sale','POS takings','Register 1 \u00b7 44 sales','Z-0928',2104.60), T('27/09','sale','POS takings','Register 1 \u00b7 51 sales','Z-0927',2388.10), T('26/09','expense','Fuel','BP Bankstown','EX-1084',-74.80), T('25/09','refund','Customer refund','Walk in \u00b7 sale #4981','SR-0112',-38.20)] },
  { id:'cashb', name:'Cash drawer', kind:'cash', loc:'bank', num:'', bsb:'', limit:0, bal:612.30, active:true, defs:[],
    tx:[T('29/09','sale','POS takings','Register B1 \u00b7 17 sales','Z-B0929',540.30), T('28/09','xout','Banked to CBA Business','','TF-0087',-1100), T('27/09','sale','POS takings','Register B1 \u00b7 22 sales','Z-B0927',812.00)] },
  { id:'cba', name:'CBA Business', kind:'bank', loc:'', num:'1044 7781', bsb:'062-118', limit:0, bal:28412.35, active:true, defs:['posCard','expenses','purchases'],
    tx:[T('29/09','sale','Tyro settlement','Card sales 28/09','TY-0928',3884.20), T('28/09','xin','From Cash drawer','Friday takings','TF-0088',3200), T('28/09','xin','From Cash drawer \u00b7 Bankstown','','TF-0087',1100), T('27/09','purchase','Supplier payment','Baraka Meats \u00b7 PO-2041','PY-0410',-6420), T('26/09','expense','Packaging','Pack & Wrap Supplies','EX-1090',-412.50), T('25/09','expense','Casual wages','Weekend casuals','EX-1089',-1560), T('24/09','xout','To Tax saver','GST set-aside','TF-0086',-2500), T('20/09','expense','Cleaning','Sparkle Commercial','EX-1087',-220), T('15/09','fee','Merchant fees','CBA Merchant','EX-1085',-84.12), T('01/09','expense','Rent','Haldon Properties','EX-1083',-6200)] },
  { id:'tax', name:'Tax saver', kind:'bank', loc:'', num:'1044 9902', bsb:'062-118', limit:0, bal:14250.00, active:true, defs:[],
    tx:[T('24/09','xin','From CBA Business','GST set-aside','TF-0086',2500), T('10/09','xin','From CBA Business','PAYG set-aside','TF-0081',1800)] },
  { id:'amex', name:'Amex Business', kind:'card', loc:'', num:'1004', bsb:'', limit:15000, bal:-3120.80, active:true, defs:[],
    tx:[T('28/09','expense','Fuel','Ampol Chullora','EX-1092',-96.40), T('18/09','expense','Marketing','Lakemba Community News','EX-1086',-150), T('12/09','payment','Card repayment','From CBA Business','TF-0080',2400), T('08/09','purchase','Supplier payment','Halal Spice Co','PY-0398',-860)] },
  { id:'paypal', name:'PayPal', kind:'wallet', loc:'', num:'', bsb:'', limit:0, bal:0, active:false, defs:[],
    tx:[T('02/07','xout','Closed, balance moved to CBA','','TF-0061',-218.40)] }
];

class Component extends ModBase {
  _id = 0;
  initialState = {
    view:'list', sel:'cba', kind:'All', showClosed:false, lt:'All', lq:'',
    accts: ACCTS, next: 89, modal:null, form:null, fErr:'',
    x: { from:'cash', to:'cba', amt:'', note:'' }, xErr:'', toasts:[]
  };
  later(fn, ms) { (this._t = this._t || []).push(setTimeout(fn, ms)); }
  toast(msg, tone) {
    const id = ++this._id;
    this.setState(s => ({ toasts: s.toasts.concat([{ id, msg, tone: tone || 'info' }]).slice(-3) }));
    this.later(() => this.setState(s => ({ toasts: s.toasts.filter(t => t.id !== id) })), 2600);
  }
  money(n) { return (n || 0).toLocaleString('en-AU', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
  sgn(n) { return (n < 0 ? '\u2212' : '') + this.money(Math.abs(n)); }
  acc(id) { return this.state.accts.find(a => a.id === id); }
  label(a) { return a.kind === 'cash' ? a.name + ' \u00b7 ' + (LOCS.find(l => l.id === a.loc) || {name:''}).name : a.name; }
  sub(a) {
    if (a.kind === 'bank') return 'BSB ' + a.bsb + ' \u00b7 ' + a.num;
    if (a.kind === 'card') return 'Card \u2022\u2022 ' + a.num + ' \u00b7 limit ' + this.money(a.limit);
    if (a.kind === 'cash') return 'Cash \u00b7 ' + (LOCS.find(l => l.id === a.loc) || {name:''}).name;
    return 'Digital wallet';
  }
  icon(a, big) {
    const k = KIND[a.kind];
    const z = big ? 44 : 36;
    return 'width:' + z + 'px;height:' + z + 'px;border-radius:10px;display:grid;place-items:center;' + MONO + 'font-size:' + (big ? 17 : 14) + 'px;font-weight:700;flex:none;background:' + k.c[0] + ';color:' + k.c[1];
  }
  pageTab(on) {
    return 'padding:9px 14px;font-size:13px;cursor:pointer;border-bottom:2px solid ' + (on ? 'var(--c-primary);color:var(--c-primary);font-weight:600;' : 'transparent;color:#5d6b7d;font-weight:500;');
  }
  badge(t) { return 'background:' + t[0] + ';color:' + t[1] + ';border-radius:5px;padding:3px 8px;font-size:11.5px;font-weight:600;white-space:nowrap'; }
  sw(on) { return ['width:32px;height:19px;border-radius:99px;position:relative;flex:none;background:' + (on ? 'var(--c-primary)' : '#c2cedb'), 'position:absolute;top:2.5px;width:14px;height:14px;border-radius:50%;background:#ffffff;left:' + (on ? '15.5px' : '2.5px')]; }
  flows(a) {
    let i = 0, o = 0, x = 0;
    a.tx.forEach(t => { if (t.type === 'xin' || t.type === 'xout' || t.type === 'payment') x += Math.abs(t.amt); else if (t.amt > 0) i += t.amt; else o -= t.amt; });
    return { i: r2(i), o: r2(o), x: r2(x),
      inAll: r2(a.tx.filter(t => t.amt > 0).reduce((s, t) => s + t.amt, 0)), outAll: r2(a.tx.filter(t => t.amt < 0).reduce((s, t) => s - t.amt, 0)) };
  }

  openForm(a) {
    this.setState({ modal:'form', fErr:'', form: a
      ? { edit:a.id, kind:a.kind, name:a.name, bsb:a.bsb, num:a.num, limit:a.limit ? String(a.limit) : '', loc:a.loc || 'main', open:'', defs:a.defs.slice(), active:a.active }
      : { edit:null, kind:'bank', name:'', bsb:'', num:'', limit:'', loc:'main', open:'', defs:[], active:true } });
  }
  setF(p) { this.setState(s => ({ form: { ...s.form, ...p }, fErr:'' })); }
  saveAcct() {
    const s = this.state, f = s.form;
    if (!f.name.trim()) { this.setState({ fErr:'Give the account a name' }); return; }
    if (f.kind === 'bank' && f.bsb && !/^\d{3}-?\d{3}$/.test(f.bsb.trim())) { this.setState({ fErr:'BSB should be six digits, e.g. 062-118' }); return; }
    const prev = f.edit ? this.acc(f.edit) : null;
    if (prev && prev.active && !f.active && Math.abs(prev.bal) > 0.005) { this.setState({ fErr:'Move the ' + this.sgn(prev.bal) + ' balance out before closing this account' }); return; }
    let accts = s.accts;
    if (f.defs.length) accts = accts.map(a => a.id === f.edit ? a : { ...a, defs: a.defs.filter(d => f.defs.indexOf(d) < 0) });
    const open = r2(parseFloat(f.open) || 0);
    const base = { name:f.name.trim(), kind:f.kind, bsb:f.kind === 'bank' ? f.bsb.trim() : '', num:f.kind === 'bank' || f.kind === 'card' ? f.num.trim() : '', limit:f.kind === 'card' ? r2(parseFloat(f.limit) || 0) : 0, loc:f.kind === 'cash' ? f.loc : '', defs:f.defs, active:f.active };
    let id = f.edit;
    if (prev) accts = accts.map(a => a.id === f.edit ? { ...a, ...base } : a);
    else {
      id = 'a' + (++this._id) + Date.now().toString(36).slice(-3);
      const bal = f.kind === 'card' ? -open : open;
      accts = accts.concat([{ id, ...base, bal, tx: open ? [T('29/09', f.kind === 'card' ? 'expense' : 'deposit', 'Opening balance', '', 'OB', bal)] : [] }]);
    }
    this.setState({ accts, modal:null, form:null, view:'details', sel:id, lt:'All' });
    this.toast(prev ? base.name + ' updated' : base.name + ' added', 'success');
  }
  xValid() {
    const s = this.state, x = s.x, amt = r2(parseFloat(x.amt) || 0);
    const from = this.acc(x.from), to = this.acc(x.to);
    if (x.from === x.to) return 'Pick two different accounts';
    if (!(amt > 0)) return 'Enter an amount';
    if (from.kind !== 'card' && amt > from.bal + 0.001) return from.name + ' only has ' + this.money(from.bal);
    if (from.kind === 'card' && from.limit && -from.bal + amt > from.limit + 0.001) return 'That would exceed the ' + this.money(from.limit) + ' card limit';
    if (to.kind === 'card' && amt > -to.bal + 0.001) return 'That is more than the ' + this.money(-to.bal) + ' owed on ' + to.name;
    return '';
  }
  doTransfer() {
    const s = this.state, x = s.x;
    const err = this.xValid();
    if (err) { this.setState({ xErr: err }); return; }
    const amt = r2(parseFloat(x.amt));
    const ref = 'TF-00' + s.next;
    const from = this.acc(x.from), to = this.acc(x.to);
    const accts = s.accts.map(a => {
      if (a.id === x.from) return { ...a, bal: r2(a.bal - amt), tx: [T('29/09', 'xout', 'To ' + this.label(to), x.note, ref, -amt)].concat(a.tx) };
      if (a.id === x.to) return { ...a, bal: r2(a.bal + amt), tx: [T('29/09', to.kind === 'card' ? 'payment' : 'xin', 'From ' + this.label(from), x.note, ref, amt)].concat(a.tx) };
      return a;
    });
    this.setState({ accts, next: s.next + 1, modal:null, xErr:'' });
    this.toast(ref + ' \u00b7 ' + this.money(amt) + ' moved to ' + to.name, 'success');
  }
  openX(from) {
    const open = this.state.accts.filter(a => a.active);
    const f = from && this.acc(from).active ? from : 'cash';
    const to = (open.find(a => a.id !== f && a.kind === 'bank') || open.find(a => a.id !== f)).id;
    this.setState({ modal:'xfer', xErr:'', x: { from:f, to, amt:'', note:'' } });
  }
  setX(p) { this.setState(s => ({ x: { ...s.x, ...p }, xErr:'' })); }

  renderVals() {
    const s = this.state;
    const act = s.accts.filter(a => a.active);
    const cashBank = r2(act.filter(a => a.kind !== 'card').reduce((t, a) => t + a.bal, 0));
    const owed = r2(act.filter(a => a.kind === 'card').reduce((t, a) => t - a.bal, 0));
    const shown = s.accts.filter(a => (s.showClosed || a.active) && (s.kind === 'All' || KIND[a.kind].name === s.kind));
    const a = this.acc(s.sel) || s.accts[0];
    const fl = this.flows(a);
    const isX = t => t.type === 'xin' || t.type === 'xout' || t.type === 'payment';
    const lq = s.lq.trim().toLowerCase();
    let run = a.bal;
    const withBal = a.tx.map(t => { const r = { ...t, runBal: run }; run = r2(run - t.amt); return r; });
    const ledger = withBal.filter(t => (s.lt === 'All' || (s.lt === 'Money in' ? t.amt > 0 && !isX(t) : s.lt === 'Money out' ? t.amt < 0 && !isX(t) : isX(t)))
      && (!lq || (t.desc + ' ' + t.party + ' ' + t.ref).toLowerCase().indexOf(lq) > -1));
    const card = a.kind === 'card';
    const f = s.form;
    const x = s.x;
    const xAmt = r2(parseFloat(x.amt) || 0);
    const xFrom = this.acc(x.from), xTo = this.acc(x.to);
    const xErr = s.modal === 'xfer' ? this.xValid() : '';
    const [cs, ck] = this.sw(s.showClosed);
    const [as2, ak] = this.sw(f ? f.active : true);

    return {
      nav: NAV.map(n => {
        const on = n[0] === 'Payment Accounts';
        return {
          name: n[0], key: n[1],
          style: 'display:flex;align-items:center;gap:10px;padding:8px 10px;border-radius:8px;cursor:pointer;font-size:13px;font-weight:500;margin-bottom:1px;' + (on ? 'background:rgba(255,255,255,.13);color:#ffffff;box-shadow:inset 2px 0 0 var(--c-accent);' : 'color:#c4dced;'),
          iconStyle: 'width:20px;height:20px;border-radius:5px;display:grid;place-items:center;' + MONO + 'font-size:9px;font-weight:600;flex:none;' + (on ? 'background:var(--c-accent);color:#06283a;' : 'background:rgba(255,255,255,.1);color:#9dbdd2;'),
          caretStyle: 'color:#7fa3bb;font-size:11px;flex:none;' + (n[2] ? '' : 'visibility:hidden;')
        };
      }),
      isList: s.view === 'list', isDetails: s.view === 'details',
      goList: () => this.setState({ view:'list' }),
      listSub: act.length + ' active accounts \u00b7 balances as of 29/09/2026',
      openAdd: () => this.openForm(null),
      openTransfer: () => this.openX(null),
      totals: [
        { k:'Cash & bank', v:this.money(cashBank), sub:act.filter(t => t.kind !== 'card').length + ' accounts', c:'' },
        { k:'Owed on cards', v:this.money(owed), sub:'Amex due 15/10', c:'#b02020' },
        { k:'Net position', v:this.sgn(r2(cashBank - owed)), sub:'Cash and bank less card balances', c: cashBank - owed < 0 ? '#b02020' : '#0f6b43' }
      ].map(t => ({ ...t, vStyle: MONO + 'font-size:22px;font-weight:600;letter-spacing:-0.02em;margin-top:3px;' + (t.c ? 'color:' + t.c + ';' : '') })),
      typeTabs: ['All','Cash','Bank','Card','Wallet'].map(k => ({
        name: k, onClick: () => this.setState({ kind:k }),
        style: 'height:30px;padding:0 13px;border-radius:7px;display:grid;place-items:center;font-size:12.5px;cursor:pointer;' + (s.kind === k ? 'background:#ffffff;color:#0f1c2b;font-weight:700;box-shadow:0 1px 3px rgba(15,28,43,.14);' : 'color:#5d6b7d;font-weight:500;')
      })),
      toggleClosed: () => this.setState({ showClosed: !s.showClosed }),
      closedSwitch: cs, closedKnob: ck,
      cards: shown.map(c => {
        const cf = this.flows(c);
        const k = KIND[c.kind];
        const isCard = c.kind === 'card';
        const used = isCard && c.limit ? Math.min(100, -c.bal / c.limit * 100) : 0;
        const tag = !c.active ? 'Closed' : c.defs.length ? 'Default' : '';
        return {
          name: c.name, sub: this.sub(c), icon: k.icon, iconStyle: this.icon(c),
          tag, tagStyle: tag ? this.badge(!c.active ? TONE.neutral : TONE.info) : 'display:none',
          balLabel: isCard ? 'Owing' : 'Balance',
          bal: isCard ? this.money(-c.bal) : this.sgn(c.bal),
          balStyle: MONO + 'font-size:24px;font-weight:600;letter-spacing:-0.025em;margin-top:1px;' + (isCard && c.bal < 0 ? 'color:#b02020;' : ''),
          hasLimit: isCard && c.limit > 0,
          limitBar: 'height:100%;border-radius:99px;width:' + used + '%;background:' + (used > 80 ? '#d93a3a' : '#7a4fc9'),
          limitText: isCard ? this.money(c.limit + c.bal) + ' available of ' + this.money(c.limit) : '',
          inM: this.money(cf.inAll), outM: this.money(cf.outAll),
          style: 'background:#ffffff;border:1px solid #dfe6ee;border-radius:13px;padding:16px 17px;cursor:pointer;transition:border-color .12s,box-shadow .12s;' + (c.active ? '' : 'opacity:.6;'),
          open: () => this.setState({ view:'details', sel:c.id, lt:'All', lq:'' })
        };
      }),

      d: {
        name: a.name, sub: this.sub(a) + (a.defs.length ? ' \u00b7 default for ' + a.defs.map(d => DEFAULTS.find(x => x[0] === d)[1].toLowerCase()).join(', ') : ''),
        icon: KIND[a.kind].icon, iconStyle: this.icon(a, true),
        tag: a.active ? KIND[a.kind].name : 'Closed', tagStyle: this.badge(a.active ? [KIND[a.kind].c[0], KIND[a.kind].c[1]] : TONE.neutral),
        balLabel: card ? 'Owing' : 'Current balance',
        bal: card ? this.money(-a.bal) : this.sgn(a.bal),
        balStyle: MONO + 'font-size:28px;font-weight:600;letter-spacing:-0.03em;margin-top:2px;' + (card && a.bal < 0 ? 'color:#b02020;' : ''),
        stats: [
          { k:'Income', v:'+' + this.money(fl.i), n:a.tx.filter(t => t.amt > 0 && !isX(t)).length + ' transactions', c:'#0f6b43' },
          { k:'Expenses & payments', v:'\u2212' + this.money(fl.o), n:a.tx.filter(t => t.amt < 0 && !isX(t)).length + ' transactions', c:'#b02020' },
          { k:'Transfers', v:this.money(fl.x), n:a.tx.filter(isX).length + ' in or out', c:'var(--c-primary-text)' }
        ].map(t => ({ ...t, vStyle: MONO + 'font-size:17px;font-weight:600;margin-top:3px;color:' + t.c }))
      },
      editThis: () => this.openForm(a),
      transferFromThis: () => this.openX(a.id),
      exportLedger: () => this.toast('Exported ' + ledger.length + ' transactions from ' + a.name, 'neutral'),
      ledgerTabs: ['All','Money in','Money out','Transfers'].map(k => ({
        name: k,
        count: k === 'All' ? a.tx.length : k === 'Money in' ? a.tx.filter(t => t.amt > 0 && !isX(t)).length : k === 'Money out' ? a.tx.filter(t => t.amt < 0 && !isX(t)).length : a.tx.filter(isX).length,
        onClick: () => this.setState({ lt:k }),
        style: this.pageTab(s.lt === k),
        countStyle: MONO + 'font-size:10.5px;border-radius:4px;padding:1px 5px;margin-left:6px;' + (s.lt === k ? 'background:var(--c-primary-soft);color:var(--c-primary-text);' : 'background:#eef2f6;color:#7d8b9c;')
      })),
      lq: s.lq, onLq: e => this.setState({ lq: e.target.value }),
      ledger: ledger.map(t => ({
        date: t.date + '/2026', desc: t.desc, party: t.party || '\u00a0', ref: t.ref,
        type: TT[t.type][0], badge: this.badge(TONE[TT[t.type][1]]),
        inAmt: t.amt > 0 ? this.money(t.amt) : '', outAmt: t.amt < 0 ? this.money(-t.amt) : '',
        bal: card ? this.money(-t.runBal) : this.sgn(t.runBal),
        balStyle: MONO + 'font-size:12.5px;text-align:right;font-weight:600;' + (!card && t.runBal < 0 ? 'color:#b02020;' : '')
      })),
      noLedger: !ledger.length,
      ledgerFoot: 'Showing ' + ledger.length + ' of ' + a.tx.length + (card ? ' \u00b7 balance column shows amount owing' : ' \u00b7 running balance after each line'),
      ledgerIn: this.money(r2(ledger.filter(t => t.amt > 0).reduce((q, t) => q + t.amt, 0))),
      ledgerOut: this.money(r2(ledger.filter(t => t.amt < 0).reduce((q, t) => q - t.amt, 0))),

      drawerOpen: s.modal === 'form' && !!f,
      fTitle: f && f.edit ? 'Edit ' + f.name : 'Add payment account',
      fTypes: Object.keys(KIND).map(k => {
        const on = f && f.kind === k;
        return {
          name: KIND[k].name, icon: KIND[k].icon,
          onClick: () => { if (!f.edit) this.setF({ kind:k }); else this.toast('Account type can\u2019t change after transactions exist', 'warn'); },
          style: 'border-radius:10px;padding:10px 6px;display:flex;flex-direction:column;align-items:center;gap:6px;cursor:pointer;' + (on ? 'background:#f4f9ff;box-shadow:inset 0 0 0 1.5px var(--c-primary);' : 'border:1px solid #dfe6ee;') + (f && f.edit && !on ? 'opacity:.45;' : ''),
          iconStyle: 'width:28px;height:28px;border-radius:8px;display:grid;place-items:center;' + MONO + 'font-size:12.5px;font-weight:700;background:' + KIND[k].c[0] + ';color:' + KIND[k].c[1]
        };
      }),
      fName: f ? f.name : '', fNamePh: f ? ({ cash:'e.g. Cash drawer', bank:'e.g. CBA Business', card:'e.g. Amex Business', wallet:'e.g. PayPal' })[f.kind] : '',
      onName: e => this.setF({ name: e.target.value }),
      fIsBank: !!f && f.kind === 'bank', fIsCard: !!f && f.kind === 'card', fIsCash: !!f && f.kind === 'cash',
      fBsb: f ? f.bsb : '', fNum: f ? f.num : '', fLimit: f ? f.limit : '', fLoc: f ? f.loc : 'main', fOpen: f ? f.open : '',
      onBsb: e => this.setF({ bsb: e.target.value }), onNum: e => this.setF({ num: e.target.value }),
      onLimit: e => this.setF({ limit: e.target.value.replace(/[^0-9.]/g, '') }), onLoc: e => this.setF({ loc: e.target.value }),
      onOpen: e => this.setF({ open: e.target.value.replace(/[^0-9.]/g, '') }),
      locOpts: LOCS,
      fIsNew: !!f && !f.edit, fIsEdit: !!f && !!f.edit,
      fOpenHint: f && f.kind === 'card' ? 'Amount currently owing on the card' : 'Recorded as the first transaction',
      fDefaults: DEFAULTS.map(d => {
        const on = !!f && f.defs.indexOf(d[0]) > -1;
        const holder = s.accts.find(x => x.defs.indexOf(d[0]) > -1 && (!f || x.id !== f.edit));
        return {
          name: d[1],
          note: on && holder ? 'Replaces ' + holder.name + ' as default' : d[2],
          noteStyle: 'font-size:11px;' + (on && holder ? 'color:#8a5300;' : 'color:#8b98a8;'),
          mark: on ? '\u2713' : '',
          box: 'width:18px;height:18px;border-radius:5px;display:grid;place-items:center;font-size:11px;font-weight:700;flex:none;' + (on ? 'background:var(--c-primary);color:#ffffff;' : 'box-shadow:inset 0 0 0 1.5px #c2cedb;color:transparent;'),
          onClick: () => this.setF({ defs: on ? f.defs.filter(z => z !== d[0]) : f.defs.concat([d[0]]) })
        };
      }),
      toggleActive: () => this.setF({ active: !f.active }),
      activeSwitch: as2, activeKnob: ak, activeLabel: f && f.active ? 'Active' : 'Closed',
      fHasErr: !!s.fErr, fErr: s.fErr,
      fSave: f && f.edit ? 'Save changes' : 'Add account',
      saveAcct: () => this.saveAcct(),

      xferOpen: s.modal === 'xfer',
      openAccts: act.map(o => ({ id:o.id, name:this.label(o) })),
      xFrom: x.from, xTo: x.to, xAmt: x.amt, xNote: x.note,
      onXFrom: e => this.setX({ from:e.target.value }), onXTo: e => this.setX({ to:e.target.value }),
      onXAmt: e => this.setX({ amt:e.target.value.replace(/[^0-9.]/g, '') }), onXNote: e => this.setX({ note:e.target.value }),
      xSwap: () => this.setX({ from:x.to, to:x.from }),
      xPreview: xFrom && xTo ? [[xFrom, -xAmt, 'From \u00b7 '], [xTo, xAmt, 'To \u00b7 ']].map(p => {
        const c = p[0].kind === 'card';
        const after = r2(p[0].bal + p[1]);
        const bad = !c && after < -0.001;
        return {
          k: p[2] + p[0].name + (c ? ' (owing)' : ''),
          now: c ? this.money(-p[0].bal) : this.sgn(p[0].bal),
          after: c ? this.money(-after) : this.sgn(after),
          box: 'border-radius:10px;padding:9px 12px;' + (bad ? 'background:#fdeaea;color:#b02020;' : p[1] < 0 ? 'background:#f6f9fc;color:#4a5866;' : 'background:#e7f6ee;color:#0f6b43;')
        };
      }) : [],
      xQuick: (xFrom && xFrom.kind === 'cash' && xFrom.bal > 0 ? [['Bank it all \u00b7 ' + this.money(xFrom.bal), xFrom.bal], ['Keep 300 float', Math.max(0, r2(xFrom.bal - 300))]] : [])
        .concat(xTo && xTo.kind === 'card' && xTo.bal < 0 ? [['Pay off card \u00b7 ' + this.money(-xTo.bal), -xTo.bal]] : [])
        .map(q => ({ label:q[0], onClick: () => this.setX({ amt: String(q[1]) }) })),
      xHasErr: !!s.xErr, xErr: s.xErr,
      xBtn: xAmt > 0 ? 'Transfer ' + this.money(xAmt) : 'Transfer',
      xBtnStyle: 'height:40px;padding:0 16px;border-radius:9px;color:#ffffff;display:flex;align-items:center;font-size:13px;font-weight:700;cursor:pointer;background:' + (xErr ? '#9fbde0' : 'var(--c-primary)'),
      doTransfer: () => this.doTransfer(),
      closeAll: () => this.setState({ modal:null, form:null, fErr:'', xErr:'' }),

      toasts: s.toasts.map(t => ({ msg:t.msg, dot:'width:8px;height:8px;border-radius:50%;flex:none;background:' + TONE[t.tone][2] }))
    };
  }
}
return Component;
})();
