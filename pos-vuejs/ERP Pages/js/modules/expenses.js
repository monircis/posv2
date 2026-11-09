// ExpensesLogic
DC.add(function () {
const ExpensesLogic = (() => {

const NAV = [
  ['Home','Hm',0],['User Management','Us',1],['Contacts','Ct',1],['Products','Pr',1],
  ['Manufacturing','Mf',0],['Purchases','Pu',1],['Sell','Sl',1],['Stock Transfers','St',0],
  ['Stock Adjustment','Sa',0],['Expenses','Ex',1],['Payment Accounts','Pa',1],['Accounting','Ac',1],
  ['Reports','Rp',1],['Bookings','Bk',0],['Kitchen','Kt',0],['Orders','Or',0],
  ['Settings','Se',1],['HRM','Hr',1]
];
const LOCS = [{id:'main', name:'Main branch'}, {id:'bank', name:'Bankstown shop'}, {id:'cold', name:'Cold store'}];
const CATS = [
  {id:'rent', name:'Rent', c:'#1668c9'}, {id:'util', name:'Utilities', c:'#0f8b8d'}, {id:'wage', name:'Casual wages', c:'#7a4fc9'},
  {id:'pack', name:'Packaging', c:'#d98c1a'}, {id:'fuel', name:'Fuel & delivery', c:'#c2410c'}, {id:'rep', name:'Repairs', c:'#b02020'},
  {id:'mkt', name:'Marketing', c:'#c43d86'}, {id:'clean', name:'Cleaning', c:'#2f9e63'}, {id:'fees', name:'Bank fees', c:'#5d6b7d'},
  {id:'other', name:'Other', c:'#8fa3b6'}
];
const ACCTS = [
  {id:'cash', name:'Cash drawer', type:'Cash \u00b7 Main branch', bal:1840.50},
  {id:'cba', name:'CBA Business', type:'Bank \u00b7 \u2022\u2022 4417', bal:28412.35},
  {id:'amex', name:'Amex Business', type:'Credit card \u00b7 limit 15,000', bal:-3120.80}
];
const TONE = {
  info:['#eaf1fb','#14549e','#1668c9'], warn:['#fdf1dd','#8a5300','#d98c1a'], error:['#fdeaea','#b02020','#d93a3a'],
  success:['#e7f6ee','#0f6b43','#2f9e63'], neutral:['#eef2f6','#5d6b7d','#8fa3b6']
};
const MONO = "font-family:'IBM Plex Mono',monospace;";
const r2 = n => Math.round(n * 100) / 100;
const E = (ref, date, cat, payee, amount, acct, loc, gst, file, notes, due, by) => ({ ref, date, cat, payee, amount, acct, loc, gst, file, notes, due: due || '', by: by || 'Aminul', inv: '' });

const SEED = [
  E('EX-1092','28/09/2026','fuel','Ampol Chullora',96.40,'amex','cold',true,'ampol-2809.jpg','Van 2 refuel'),
  E('EX-1091','27/09/2026','util','Ausgrid',1284.60,null,'main',true,'ausgrid-sep.pdf','Quarterly electricity, cool rooms','08/10/2026'),
  E('EX-1090','26/09/2026','pack','Pack & Wrap Supplies',412.50,'cba','main',true,'pw-inv-5521.pdf','Vacuum bags, trays, labels'),
  E('EX-1089','25/09/2026','wage','Casual staff',1560.00,'cba','bank',false,'','Weekend casuals, week 39',null,'Sara'),
  E('EX-1088','23/09/2026','rep','Coolaire Refrigeration',680.00,null,'main',true,'','Cool room 2 compressor callout','25/09/2026'),
  E('EX-1087','20/09/2026','clean','Sparkle Commercial',220.00,'cba','main',true,'sparkle-sep.pdf','Fortnightly deep clean'),
  E('EX-1086','18/09/2026','mkt','Lakemba Community News',150.00,'amex','main',true,'lcn-ad.pdf','Eid special half-page'),
  E('EX-1085','15/09/2026','fees','CBA Merchant',84.12,'cba','main',true,'','Terminal fees'),
  E('EX-1084','12/09/2026','fuel','BP Bankstown',74.80,'cash','bank',true,'bp-1209.jpg',''),
  E('EX-1083','01/09/2026','rent','Haldon Properties',6200.00,'cba','main',true,'rent-sep.pdf','September rent, Main branch')
];

class Component extends ModBase {
  _id = 0;
  initialState = {
    view: 'list', tab: 'All', q: '', catF: 'all', acctF: 'all',
    rows: SEED, accts: ACCTS.reduce((a, x) => { a[x.id] = x.bal; return a; }, {}), next: 1093,
    sel: 'EX-1091', form: null, modal: null, payAcct: 'cba', toasts: [],
    logs: { 'EX-1091': [['Added by Aminul \u00b7 bill due 08/10', '27/09 \u00b7 09:12', 'neutral']] }
  };
  componentWillUnmount() { (this._t || []).forEach(clearTimeout); }
  later(fn, ms) { (this._t = this._t || []).push(setTimeout(fn, ms)); }
  toast(msg, tone) {
    const id = ++this._id;
    this.setState(s => ({ toasts: s.toasts.concat([{ id, msg, tone: tone || 'info' }]).slice(-3) }));
    this.later(() => this.setState(s => ({ toasts: s.toasts.filter(t => t.id !== id) })), 2600);
  }
  money(n) { return (n || 0).toLocaleString('en-AU', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
  bal(n) { return (n < 0 ? '\u2212' : '') + this.money(Math.abs(n)); }
  cat(id) { return CATS.find(c => c.id === id) || CATS[CATS.length - 1]; }
  acct(id) { return ACCTS.find(a => a.id === id); }
  loc(id) { return LOCS.find(l => l.id === id); }
  badge(t) { return 'background:' + t[0] + ';color:' + t[1] + ';border-radius:5px;padding:3px 8px;font-size:11.5px;font-weight:600;white-space:nowrap'; }
  pageTab(on) {
    return 'padding:9px 14px;font-size:13px;cursor:pointer;border-bottom:2px solid '
      + (on ? '#1668c9;color:#1668c9;font-weight:600;' : 'transparent;color:#5d6b7d;font-weight:500;');
  }
  now() { const d = new Date(); return '29/09 \u00b7 ' + String(d.getHours()).padStart(2, '0') + ':' + String(d.getMinutes()).padStart(2, '0'); }
  daysTo(dmy) { const p = dmy.split('/'); if (p.length !== 3) return 0; return Math.round((new Date(+p[2], +p[1] - 1, +p[0]) - new Date(2026, 8, 29)) / 864e5); }
  status(r) {
    if (r.acct) return ['Paid', TONE.success];
    const d = this.daysTo(r.due);
    if (d < 0) return ['Overdue ' + -d + 'd', TONE.error];
    return ['Due ' + (d === 0 ? 'today' : 'in ' + d + 'd'), TONE.warn];
  }

  blank() { return { edit: null, cat: '', amount: '', gst: true, date: '29/09/2026', loc: 'main', payee: '', ref: '', notes: '', file: '', paid: true, acct: 'cba', due: '29/10/2026' }; }
  openNew() { this.setState({ view: 'form', form: this.blank() }); }
  openEdit(r) {
    this.setState({ view: 'form', form: { edit: r.ref, cat: r.cat, amount: String(r.amount), gst: r.gst, date: r.date, loc: r.loc, payee: r.payee, ref: r.inv, notes: r.notes, file: r.file, paid: !!r.acct, acct: r.acct || 'cba', due: r.due || '29/10/2026' } });
  }
  setForm(p) { this.setState(s => ({ form: { ...s.form, ...p } })); }
  errs() {
    const f = this.state.form, e = [];
    if (!f.cat) e.push('Pick a category');
    if (!(parseFloat(f.amount) > 0)) e.push('Enter an amount');
    if (!/^\d{2}\/\d{2}\/\d{4}$/.test(f.date)) e.push('Date must be DD/MM/YYYY');
    if (!f.paid && !/^\d{2}\/\d{2}\/\d{4}$/.test(f.due)) e.push('Due date must be DD/MM/YYYY');
    return e;
  }
  save(another) {
    const s = this.state, f = s.form;
    const e = this.errs();
    if (e.length) { this.toast(e[0], 'error'); return; }
    const amount = r2(parseFloat(f.amount));
    const prev = f.edit ? s.rows.find(r => r.ref === f.edit) : null;
    const accts = { ...s.accts };
    if (prev && prev.acct) accts[prev.acct] = r2(accts[prev.acct] + prev.amount);
    if (f.paid) accts[f.acct] = r2(accts[f.acct] - amount);
    const ref = prev ? prev.ref : 'EX-' + s.next;
    const row = { ref, date: f.date, cat: f.cat, payee: f.payee.trim() || this.cat(f.cat).name, amount, acct: f.paid ? f.acct : null, loc: f.loc, gst: f.gst, file: f.file, notes: f.notes, due: f.paid ? '' : f.due, by: prev ? prev.by : 'Aminul', inv: f.ref };
    const rows = prev ? s.rows.map(r => r.ref === ref ? row : r) : [row].concat(s.rows);
    const log = [[prev ? 'Edited by Aminul' : 'Added by Aminul' + (f.paid ? ', paid from ' + this.acct(f.acct).name : ', bill due ' + f.due), this.now(), prev ? 'info' : 'neutral']].concat(s.logs[ref] || []);
    this.setState({ rows, accts, next: prev ? s.next : s.next + 1, logs: { ...s.logs, [ref]: log },
      view: another ? 'form' : 'details', sel: ref, form: another ? { ...this.blank(), acct: f.acct, loc: f.loc } : null });
    this.toast(prev ? ref + ' updated' : ref + ' added \u00b7 ' + this.money(amount) + (f.paid ? ' from ' + this.acct(f.acct).name : ' as unpaid'), 'success');
  }
  pay() {
    const s = this.state, r = s.rows.find(x => x.ref === s.sel);
    const accts = { ...s.accts, [s.payAcct]: r2(s.accts[s.payAcct] - r.amount) };
    this.setState({ rows: s.rows.map(x => x.ref === r.ref ? { ...x, acct: s.payAcct, due: '' } : x), accts, modal: null,
      logs: { ...s.logs, [r.ref]: [['Paid from ' + this.acct(s.payAcct).name + ' by Aminul', this.now(), 'success']].concat(s.logs[r.ref] || []) } });
    this.toast(r.ref + ' paid \u00b7 ' + this.money(r.amount) + ' from ' + this.acct(s.payAcct).name, 'success');
  }
  del() {
    const s = this.state, r = s.rows.find(x => x.ref === s.sel);
    const accts = { ...s.accts };
    if (r.acct) accts[r.acct] = r2(accts[r.acct] + r.amount);
    this.setState({ rows: s.rows.filter(x => x.ref !== r.ref), accts, modal: null, view: 'list', sel: s.rows.find(x => x.ref !== r.ref).ref });
    this.toast(r.ref + ' deleted' + (r.acct ? ' \u00b7 ' + this.money(r.amount) + ' back in ' + this.acct(r.acct).name : ''), 'neutral');
  }
  acctCards(sel, amount, onPick, refund) {
    return ACCTS.map(a => {
      const on = a.id === sel;
      const base = this.state.accts[a.id] + (refund && refund.acct === a.id ? refund.amount : 0);
      const after = r2(base - (on ? amount : 0));
      const low = on && a.type.indexOf('Credit') < 0 && after < 0;
      return {
        name: a.name, type: a.type, bal: this.bal(base),
        after: on && amount ? 'after ' + this.bal(after) : '',
        afterStyle: MONO + 'font-size:10.5px;' + (low ? 'color:#b02020;font-weight:600;' : 'color:#7d8b9c;'),
        onClick: () => onPick(a.id),
        style: 'display:flex;align-items:center;gap:11px;border-radius:10px;padding:10px 12px;cursor:pointer;' + (on ? 'background:#f4f9ff;box-shadow:inset 0 0 0 1.5px #1668c9;' : 'border:1px solid #dfe6ee;'),
        radio: 'width:16px;height:16px;border-radius:50%;flex:none;' + (on ? 'box-shadow:inset 0 0 0 5px #1668c9;' : 'box-shadow:inset 0 0 0 1.5px #c2cedb;')
      };
    });
  }

  renderVals() {
    const s = this.state;
    const all = s.rows;
    const qq = s.q.trim().toLowerCase();
    const inTab = r => s.tab === 'All' || (s.tab === 'Paid' ? !!r.acct : s.tab === 'Unpaid' ? !r.acct : s.tab === 'No receipt' ? !r.file : true);
    const filt = all.filter(r => inTab(r) && (s.catF === 'all' || r.cat === s.catF) && (s.acctF === 'all' || r.acct === s.acctF)
      && (!qq || (r.payee + ' ' + r.ref + ' ' + r.notes + ' ' + r.inv).toLowerCase().indexOf(qq) > -1));
    const total = r2(all.reduce((a, r) => a + r.amount, 0));
    const unpaid = all.filter(r => !r.acct);
    const overdue = unpaid.filter(r => this.daysTo(r.due) < 0);
    const byCat = {};
    all.forEach(r => { byCat[r.cat] = (byCat[r.cat] || 0) + r.amount; });
    const cats = Object.keys(byCat).sort((a, b) => byCat[b] - byCat[a]);
    const maxC = byCat[cats[0]] || 1;

    const f = s.form;
    let fv = {};
    if (f) {
      const amt = r2(parseFloat(f.amount) || 0);
      const gst = f.gst ? r2(amt / 11) : 0;
      const prev = f.edit ? all.find(r => r.ref === f.edit) : null;
      const e = this.errs();
      const warns = [];
      if (f.gst && amt > 82.5 && !f.file) warns.push(['!', 'Tax invoice needed to claim GST over 82.50. Attach the receipt.', 'warn']);
      if (f.paid) {
        const a = this.acct(f.acct);
        const after = s.accts[f.acct] + (prev && prev.acct === f.acct ? prev.amount : 0) - amt;
        if (a.type.indexOf('Credit') < 0 && after < 0) warns.push(['\u00d7', a.name + ' would go overdrawn by ' + this.money(-after) + '.', 'error']);
      }
      fv = {
        formTitle: f.edit ? 'Edit ' + f.edit : 'Add expense',
        formCrumb: f.edit ? 'Edit ' + f.edit : 'Add expense',
        canAddAnother: !f.edit,
        catChips: CATS.map(c => {
          const on = f.cat === c.id;
          return {
            name: c.name,
            onClick: () => this.setForm({ cat: c.id }),
            style: 'height:38px;border-radius:9px;display:flex;align-items:center;gap:8px;padding:0 11px;font-size:12.5px;cursor:pointer;min-width:0;'
              + (on ? 'background:#0f1c2b;color:#ffffff;font-weight:600;' : 'border:1px solid #dfe6ee;color:#0f1c2b;font-weight:500;'),
            dot: 'width:8px;height:8px;border-radius:50%;flex:none;background:' + c.c
          };
        }),
        fAmount: f.amount, fDate: f.date, fLoc: f.loc, fPayee: f.payee, fRef: f.ref, fNotes: f.notes, fDue: f.due, fFile: f.file,
        amountBox: 'display:flex;align-items:center;height:40px;border:1px solid ' + (f.amount && !(amt > 0) ? '#d93a3a' : '#1668c9') + ';border-radius:8px;background:#f6faff',
        onAmount: ev => this.setForm({ amount: ev.target.value.replace(/[^0-9.]/g, '') }),
        onDate: ev => this.setForm({ date: ev.target.value }),
        onLoc: ev => this.setForm({ loc: ev.target.value }),
        onPayee: ev => this.setForm({ payee: ev.target.value }),
        onRef: ev => this.setForm({ ref: ev.target.value }),
        onNotes: ev => this.setForm({ notes: ev.target.value }),
        onDue: ev => this.setForm({ due: ev.target.value }),
        toggleGst: () => this.setForm({ gst: !f.gst }),
        gstSwitch: 'width:30px;height:18px;border-radius:99px;position:relative;cursor:pointer;flex:none;background:' + (f.gst ? '#1668c9' : '#c2cedb'),
        gstKnob: 'position:absolute;top:2px;width:14px;height:14px;border-radius:50%;background:#ffffff;left:' + (f.gst ? '14px' : '2px'),
        gstLabel: f.gst ? 'Includes GST' + (amt ? ' of ' + this.money(gst) : '') : 'No GST (GST-free or not registered)',
        locOpts: LOCS,
        noFile: !f.file, hasFile: !!f.file,
        attach: () => this.setForm({ file: (f.payee.trim() || this.cat(f.cat || 'other').name).toLowerCase().replace(/[^a-z0-9]+/g, '-') + '-receipt.pdf' }),
        detach: () => this.setForm({ file: '' }),
        payModes: [['Paid', true], ['Unpaid bill', false]].map(m => ({
          name: m[0],
          onClick: () => this.setForm({ paid: m[1] }),
          style: 'flex:1;height:32px;border-radius:7px;display:grid;place-items:center;font-size:12.5px;cursor:pointer;'
            + (f.paid === m[1] ? 'background:#ffffff;color:#0f1c2b;font-weight:700;box-shadow:0 1px 3px rgba(15,28,43,.14);' : 'color:#5d6b7d;font-weight:500;')
        })),
        isPaid: f.paid, isUnpaid: !f.paid,
        acctCards: this.acctCards(f.acct, amt, id => this.setForm({ acct: id }), prev && prev.acct ? prev : null),
        sEx: this.money(amt - gst), sGst: this.money(gst), sTotal: this.money(amt),
        hasWarn: warns.length > 0,
        warns: warns.map(w => ({ mark: w[0], msg: w[1], style: 'display:flex;gap:8px;font-size:12px;line-height:1.5;border-radius:10px;padding:10px 12px;background:' + TONE[w[2]][0] + ';color:' + TONE[w[2]][1] })),
        saveLabel: f.edit ? 'Save changes' : f.paid ? 'Save & pay' : 'Save bill',
        saveBtnStyle: 'height:34px;padding:0 15px;border-radius:8px;display:flex;align-items:center;font-size:12.5px;font-weight:600;color:#ffffff;cursor:pointer;background:' + (e.length ? '#9fbde0' : '#1668c9'),
        save: () => this.save(false), saveAnother: () => this.save(true),
        cancelForm: () => this.setState({ view: f.edit ? 'details' : 'list', form: null })
      };
    }

    const r = all.find(x => x.ref === s.sel) || all[0];
    const st = this.status(r);
    const rg = r.gst ? r2(r.amount / 11) : 0;
    const c = this.cat(r.cat);

    return {
      nav: NAV.map(n => {
        const on = n[0] === 'Expenses';
        return {
          name: n[0], key: n[1], open: on,
          children: on ? [['All expenses', 'All'], ['Unpaid bills', 'Unpaid']].map(ch => ({
            name: ch[0],
            onClick: () => this.setState({ view: 'list', tab: ch[1], form: null }),
            style: 'padding:6px 8px;border-radius:6px;font-size:12.5px;cursor:pointer;' + (s.view === 'list' && s.tab === ch[1] ? 'color:#ffffff;font-weight:600;background:rgba(255,255,255,.08);' : 'color:#9dbdd2;')
          })) : [],
          style: 'display:flex;align-items:center;gap:10px;padding:8px 10px;border-radius:8px;cursor:pointer;font-size:13px;font-weight:500;margin-bottom:1px;'
            + (on ? 'background:rgba(255,255,255,.13);color:#ffffff;box-shadow:inset 2px 0 0 #4da3e8;' : 'color:#c4dced;'),
          iconStyle: 'width:20px;height:20px;border-radius:5px;display:grid;place-items:center;' + MONO + 'font-size:9px;font-weight:600;flex:none;'
            + (on ? 'background:#4da3e8;color:#06283a;' : 'background:rgba(255,255,255,.1);color:#9dbdd2;'),
          caretStyle: 'color:#7fa3bb;font-size:11px;flex:none;' + (n[2] ? '' : 'visibility:hidden;') + (on ? 'transform:rotate(90deg);' : '')
        };
      }),
      isList: s.view === 'list', isForm: s.view === 'form' && !!f, isDetails: s.view === 'details',
      goList: () => this.setState({ view: 'list', form: null }),
      newExpense: () => this.openNew(),
      exportList: () => this.toast('Exported ' + filt.length + ' expenses to CSV', 'neutral'),
      listSub: 'September 2026 \u00b7 ' + all.length + ' expenses \u00b7 ' + this.money(r2(unpaid.reduce((a, x) => a + x.amount, 0))) + ' unpaid',
      statusTabs: ['All', 'Paid', 'Unpaid', 'No receipt'].map(n => {
        const cnt = all.filter(x => n === 'All' || (n === 'Paid' ? !!x.acct : n === 'Unpaid' ? !x.acct : !x.file)).length;
        return {
          name: n, count: cnt,
          onClick: () => this.setState({ tab: n }),
          style: this.pageTab(s.tab === n),
          countStyle: MONO + 'font-size:10.5px;border-radius:4px;padding:1px 5px;margin-left:6px;' + (s.tab === n ? 'background:#eaf1fb;color:#14549e;' : 'background:#eef2f6;color:#7d8b9c;')
        };
      }),
      showOverdue: overdue.length > 0 && s.tab !== 'Unpaid',
      overdueText: overdue.length + (overdue.length === 1 ? ' bill is' : ' bills are') + ' overdue: ' + overdue.map(x => x.payee + ' ' + this.money(x.amount)).join(', '),
      showUnpaid: () => this.setState({ tab: 'Unpaid' }),
      q: s.q, onQ: e => this.setState({ q: e.target.value }),
      catOpts: CATS, acctOpts: ACCTS,
      catFilter: s.catF, acctFilter: s.acctF,
      onCatFilter: e => this.setState({ catF: e.target.value }),
      onAcctFilter: e => this.setState({ acctF: e.target.value }),
      hasFilters: s.catF !== 'all' || s.acctF !== 'all' || !!qq,
      clearFilters: () => this.setState({ catF: 'all', acctF: 'all', q: '' }),
      rows: filt.map(x => {
        const t = this.status(x);
        return {
          ref: x.ref, date: x.date, cat: this.cat(x.cat).name, payee: x.payee, note: x.notes || this.loc(x.loc).name,
          catDot: 'width:8px;height:8px;border-radius:50%;flex:none;background:' + this.cat(x.cat).c,
          acct: x.acct ? this.acct(x.acct).name : '\u2014',
          amount: this.money(x.amount),
          status: t[0], badge: this.badge(t[1]),
          fileType: x.file && /\.pdf$/i.test(x.file) ? 'PDF' : 'IMG',
          clipStyle: MONO + 'font-size:9.5px;font-weight:600;text-align:center;background:#eef2f6;color:#5d6b7d;border-radius:4px;padding:2px 0;' + (x.file ? '' : 'visibility:hidden;'),
          open: () => this.setState({ view: 'details', sel: x.ref })
        };
      }),
      noRows: !filt.length, rowCount: filt.length,
      rowTotal: this.money(r2(filt.reduce((a, x) => a + x.amount, 0))),
      rowGst: this.money(r2(filt.reduce((a, x) => a + (x.gst ? x.amount / 11 : 0), 0))),
      monthTotal: this.money(total),
      catBars: cats.map(k => ({
        name: this.cat(k).name, v: this.money(byCat[k]),
        nameStyle: 'flex:1;font-size:12.5px;' + (s.catF === k ? 'font-weight:700;color:#1668c9;' : 'color:#0f1c2b;'),
        bar: 'height:100%;border-radius:99px;width:' + Math.max(3, byCat[k] / maxC * 100) + '%;background:' + this.cat(k).c,
        onClick: () => this.setState({ catF: s.catF === k ? 'all' : k })
      })),

      ...fv,

      d: {
        ref: r.ref, payee: r.payee, status: st[0], badge: this.badge(st[1]), unpaid: !r.acct,
        sub: r.ref + ' \u00b7 ' + c.name + ' \u00b7 ' + r.date + ' \u00b7 ' + this.loc(r.loc).name,
        amount: this.money(r.amount),
        gstLine: r.gst ? 'includes GST ' + this.money(rg) : 'no GST',
        facts: [
          { k: 'Category', v: c.name }, { k: 'Date', v: r.date }, { k: 'Location', v: this.loc(r.loc).name },
          { k: r.acct ? 'Paid from' : 'Due', v: r.acct ? this.acct(r.acct).name : r.due },
          { k: 'Reference', v: r.inv || '\u2014' }, { k: 'Added by', v: r.by }
        ],
        hasFile: !!r.file, noFile: !r.file, file: r.file, by: r.by,
        noFileMsg: r.gst && r.amount > 82.5 ? 'No receipt attached. You need the tax invoice to claim ' + this.money(rg) + ' GST.' : 'No receipt attached.',
        hasNotes: !!r.notes, notes: r.notes,
        journal: [
          { acct: 'Account', dr: 'Debit', cr: 'Credit', h: true },
          { acct: c.name + ' (expense)', dr: this.money(r.amount - rg), cr: '' },
          rg ? { acct: 'GST paid', dr: this.money(rg), cr: '' } : null,
          { acct: r.acct ? this.acct(r.acct).name : 'Accounts payable', dr: '', cr: this.money(r.amount) }
        ].filter(Boolean).map(j => ({ ...j, style: 'display:flex;font-size:12px;padding:4px 0;' + (j.h ? MONO + 'font-size:9.5px;letter-spacing:.07em;text-transform:uppercase;color:#7d8b9c;border-bottom:1px solid #eaeff5;padding-bottom:6px;margin-bottom:2px;' : 'color:#4a5866;') })),
        log: (s.logs[r.ref] || [['Added by ' + r.by + (r.acct ? ', paid from ' + this.acct(r.acct).name : ''), r.date.slice(0, 5) + ' \u00b7 09:00', 'neutral']])
          .map(a => ({ what: a[0], when: a[1], dot: 'width:8px;height:8px;border-radius:50%;margin-top:5px;flex:none;background:' + TONE[a[2]][2] }))
      },
      editIt: () => this.openEdit(r),
      openPay: () => this.setState({ modal: 'pay', payAcct: 'cba' }),
      askDelete: () => this.setState({ modal: 'del' }),
      download: () => this.toast('Downloading ' + r.file, 'neutral'),
      attachLater: () => {
        const file = r.payee.toLowerCase().replace(/[^a-z0-9]+/g, '-') + '-receipt.pdf';
        this.setState(ss => ({ rows: ss.rows.map(x => x.ref === r.ref ? { ...x, file } : x) }));
        this.toast('Receipt attached to ' + r.ref, 'success');
      },

      payOpen: s.modal === 'pay', delOpen: s.modal === 'del',
      payWhat: r.payee + ' \u00b7 ' + r.ref + ' \u00b7 due ' + (r.due || '\u2014'),
      payAmt: this.money(r.amount),
      modalAccts: this.acctCards(s.payAcct, r.amount, id => this.setState({ payAcct: id })),
      confirmPay: () => this.pay(),
      delText: r.ref + ' for ' + this.money(r.amount) + ' will be removed from reports' + (r.acct ? ', and the amount goes back into ' + this.acct(r.acct).name + '.' : '.') + ' This is logged in the audit trail.',
      confirmDelete: () => this.del(),
      closeModal: () => this.setState({ modal: null }),

      toasts: s.toasts.map(x => ({ msg: x.msg, dot: 'width:8px;height:8px;border-radius:50%;flex:none;background:' + TONE[x.tone][2] }))
    };
  }
}


return Component;
})();
});
