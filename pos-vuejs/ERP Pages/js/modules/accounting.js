// AccountingLogic
DC.add(function () {
const AccountingLogic = (() => {
const NAV = [
  ['Home','Hm',0],['User Management','Us',1],['Contacts','Ct',1],['Products','Pr',1],
  ['Manufacturing','Mf',0],['Purchases','Pu',1],['Sell','Sl',1],['Stock Transfers','St',0],
  ['Stock Adjustment','Sa',0],['Expenses','Ex',1],['Payment Accounts','Pa',1],['Accounting','Ac',1],
  ['Reports','Rp',1],['Bookings','Bk',0],['Kitchen','Kt',0],['Orders','Or',0],
  ['Settings','Se',1],['HRM','Hr',1]
];
const TYPES = [
  {id:'asset', name:'Assets', dr:true, c:'#1668c9', note:'What the business owns'},
  {id:'liability', name:'Liabilities', dr:false, c:'#d98c1a', note:'What the business owes'},
  {id:'equity', name:'Equity', dr:false, c:'#7a4fc9', note:'Owner\u2019s stake'},
  {id:'income', name:'Income', dr:false, c:'#2f9e63', note:'Sales and other income'},
  {id:'cogs', name:'Cost of sales', dr:true, c:'#c2410c', note:'Direct cost of stock sold'},
  {id:'expense', name:'Expenses', dr:true, c:'#b02020', note:'Overheads'}
];
const A = (code, name, type, open, tax, tag) => ({ code, name, type, open: open || 0, tax: tax || 'BAS excluded', tag: tag || '' });
const ACCTS0 = [
  A('1-1100','Cash drawers','asset',1200,'','Payment account'), A('1-1200','CBA Business','asset',32000,'','Payment account'), A('1-1210','Tax saver','asset',9950,'','Payment account'),
  A('1-1300','Accounts receivable','asset',6480,'','System'), A('1-1400','Inventory','asset',38400,'','System'), A('1-1500','Shop equipment','asset',64000,'GST 10%'),
  A('1-1510','Accumulated depreciation','asset',-12800,'','Contra'),
  A('2-1100','Accounts payable','liability',9200,'','System'), A('2-1200','Amex Business','liability',2600,'','Payment account'),
  A('2-1300','GST collected','liability',5100,'','System'), A('2-1310','GST paid','liability',-1900,'','System'),
  A('2-1400','PAYG withholding','liability',1400), A('2-1500','Wages payable','liability',0), A('2-2000','Equipment loan','liability',28000),
  A('3-1000','Owner\u2019s capital','equity',50000), A('3-2000','Retained earnings','equity',0,'','System'), A('3-3000','Owner drawings','equity',-4000),
  A('4-1000','Sales \u00b7 retail','income',0,'GST 10%'), A('4-1100','Sales \u00b7 wholesale','income',0,'GST 10%'), A('4-1200','Sales \u00b7 grill & kitchen','income',0,'GST 10%'), A('4-9000','Stock gain','income',0,''),
  A('5-1000','Cost of goods sold','cogs',0,'','System'), A('5-1100','Stock write-off','cogs',0,'','System'),
  A('6-1000','Rent','expense',0,'GST 10%'), A('6-1100','Utilities','expense',0,'GST 10%'), A('6-1200','Wages','expense',0,'BAS excluded'),
  A('6-1300','Packaging','expense',0,'GST 10%'), A('6-1400','Fuel & delivery','expense',0,'GST 10%'), A('6-1500','Repairs','expense',0,'GST 10%'),
  A('6-1600','Marketing','expense',0,'GST 10%'), A('6-1700','Cleaning','expense',0,'GST 10%'), A('6-1800','Bank & merchant fees','expense',0,'GST free'), A('6-1900','Depreciation','expense',0,'BAS excluded')
];
const SRC = { sale:['Sale','success'], purchase:['Purchase','warn'], expense:['Expense','error'], stock:['Stock','neutral'], transfer:['Transfer','info'], manual:['Manual','purple'] };
const TONE = {
  info:['#eaf1fb','#14549e','#1668c9'], warn:['#fdf1dd','#8a5300','#d98c1a'], error:['#fdeaea','#b02020','#d93a3a'],
  success:['#e7f6ee','#0f6b43','#2f9e63'], neutral:['#eef2f6','#5d6b7d','#8fa3b6'], purple:['#f3ecfd','#5b34a8','#7a4fc9']
};
const MONO = "font-family:'IBM Plex Mono',monospace;";
const r2 = n => Math.round(n * 100) / 100;
const J = (no, date, src, desc, ref, status, lines) => ({ no, date, src, desc, ref, status, lines: lines.map(l => ({ code:l[0], dr:l[1] || 0, cr:l[2] || 0 })) });
const sales = (no, date, wk, cash, card, retail, grill, gst) => J(no, date, 'sale', 'POS sales, week ' + wk, 'Z-W' + wk, 'posted', [['1-1100',cash],['1-1200',card],['4-1000',0,retail],['4-1200',0,grill],['2-1300',0,gst]]);
const cogs = (no, date, wk, v) => J(no, date, 'stock', 'Cost of goods sold, week ' + wk, 'COGS-W' + wk, 'posted', [['5-1000',v],['1-1400',0,v]]);
const J0 = [
  J('JN-0401','01/09','expense','Rent \u00b7 Haldon Properties','EX-1083','posted',[['6-1000',5636.36],['2-1310',563.64],['1-1200',0,6200]]),
  sales('JN-0402','07/09',36,9800,14200,17200,4618.18,2181.82), cogs('JN-0403','07/09',36,11200),
  J('JN-0404','08/09','purchase','Halal Spice Co','PU-2031','posted',[['1-1400',781.82],['2-1310',78.18],['2-1200',0,860]]),
  J('JN-0405','12/09','transfer','Amex repayment','TF-0080','posted',[['2-1200',2400],['1-1200',0,2400]]),
  sales('JN-0406','14/09',37,10600,15800,18900,5100,2400), cogs('JN-0407','14/09',37,12300),
  J('JN-0408','15/09','expense','Merchant fees \u00b7 CBA','EX-1085','posted',[['6-1800',84.12],['1-1200',0,84.12]]),
  J('JN-0409','18/09','sale','Wholesale invoice \u00b7 Al Amin Catering','INV-3310','posted',[['1-1300',4540.80],['4-1100',0,4128],['2-1300',0,412.80]]),
  sales('JN-0410','21/09',38,9900,15400,18100,4900,2300), cogs('JN-0411','21/09',38,11800),
  J('JN-0412','24/09','transfer','GST set-aside','TF-0086','posted',[['1-1210',2500],['1-1200',0,2500]]),
  J('JN-0413','25/09','expense','Casual wages \u00b7 week 39','EX-1089','posted',[['6-1200',1560],['1-1200',0,1560]]),
  J('JN-0414','27/09','purchase','Baraka Meats','PU-2041','posted',[['1-1400',5836.36],['2-1310',583.64],['2-1100',0,6420]]),
  J('JN-0415','27/09','purchase','Supplier payment \u00b7 Baraka Meats','PY-0410','posted',[['2-1100',6420],['1-1200',0,6420]]),
  J('JN-0416','27/09','expense','Ausgrid \u00b7 unpaid bill','EX-1091','posted',[['6-1100',1167.82],['2-1310',116.78],['2-1100',0,1284.60]]),
  J('JN-0417','28/09','stock','Stock adjustment \u00b7 spoilage','SA-0231','posted',[['5-1100',85.80],['1-1400',0,85.80]]),
  J('JN-0418','28/09','transfer','Banked Friday takings','TF-0088','posted',[['1-1200',3200],['1-1100',0,3200]]),
  J('JN-0419','28/09','expense','Fuel \u00b7 Ampol Chullora','EX-1092','posted',[['6-1400',87.64],['2-1310',8.76],['2-1200',0,96.40]]),
  J('JN-0420','29/09','manual','Accrue September wages','ACR-09','posted',[['6-1200',4200],['2-1500',0,3400],['2-1400',0,800]]),
  J('JN-0421','30/09','manual','Depreciation \u00b7 September','DEP-09','draft',[['6-1900',1066.67],['1-1510',0,1066.67]])
].reverse();
const TEMPLATES = [
  ['Depreciation', 'Depreciation for September', [['6-1900','1066.67',''],['1-1510','','1066.67']]],
  ['Owner drawings', 'Owner drawings', [['3-3000','',''],['1-1200','','']]],
  ['Accrued expense', 'Accrued expense', [['6-1100','',''],['2-1100','','']]]
];

class Component extends ModBase {
  _id = 0;
  initialState = {
    sub:'coa', ctype:'All', cq:'', jsrc:'All', jq:'', openJ:'JN-0421', st:'pl',
    accts: ACCTS0, journals: J0, next: 422, form: null, modal: null, lgCode: null,
    nAcct: null, nErr: '', locked: { '07':true, '08':true, '09':false }, recon: false, toasts: []
  };
  later(fn, ms) { (this._t = this._t || []).push(setTimeout(fn, ms)); }
  toast(msg, tone) {
    const id = ++this._id;
    this.setState(s => ({ toasts: s.toasts.concat([{ id, msg, tone: tone || 'info' }]).slice(-3) }));
    this.later(() => this.setState(s => ({ toasts: s.toasts.filter(t => t.id !== id) })), 2600);
  }
  money(n) { return (n || 0).toLocaleString('en-AU', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
  sgn(n) { return Math.abs(n) < 0.005 ? '0.00' : (n < 0 ? '(' + this.money(-n) + ')' : this.money(n)); }
  type(id) { return TYPES.find(t => t.id === id); }
  acct(code) { return this.state.accts.find(a => a.code === code); }
  badge(t) { return 'background:' + t[0] + ';color:' + t[1] + ';border-radius:5px;padding:3px 8px;font-size:11.5px;font-weight:600;white-space:nowrap'; }
  pageTab(on) { return 'padding:9px 14px;font-size:13px;cursor:pointer;border-bottom:2px solid ' + (on ? '#1668c9;color:#1668c9;font-weight:600;' : 'transparent;color:#5d6b7d;font-weight:500;'); }
  seg(on) { return 'height:30px;padding:0 13px;border-radius:7px;display:grid;place-items:center;font-size:12.5px;cursor:pointer;white-space:nowrap;' + (on ? 'background:#ffffff;color:#0f1c2b;font-weight:700;box-shadow:0 1px 3px rgba(15,28,43,.14);' : 'color:#5d6b7d;font-weight:500;'); }
  // signed debit-positive balances
  balances() {
    const s = this.state;
    const b = {}, cnt = {};
    s.accts.forEach(a => { b[a.code] = this.type(a.type).dr ? a.open : -a.open; cnt[a.code] = 0; });
    const plug = -Object.keys(b).reduce((t, k) => t + b[k], 0);
    b['3-2000'] = r2(b['3-2000'] + plug);
    s.journals.filter(j => j.status === 'posted').forEach(j => j.lines.forEach(l => { b[l.code] = r2((b[l.code] || 0) + l.dr - l.cr); cnt[l.code] = (cnt[l.code] || 0) + 1; }));
    return { b, cnt, plug };
  }
  normal(a, signed) { return this.type(a.type).dr ? signed : -signed; }
  jTotal(j) { return r2(j.lines.reduce((t, l) => t + l.dr, 0)); }
  month(d) { const p = d.split('/'); return p.length >= 2 ? p[1] : ''; }

  newForm(tpl) {
    const t = tpl ? TEMPLATES.find(x => x[0] === tpl) : null;
    const lines = t ? t[2].map(l => ({ code:l[0], desc:'', dr:l[1], cr:l[2] })) : [{ code:'', desc:'', dr:'', cr:'' }, { code:'', desc:'', dr:'', cr:'' }];
    this.setState(s => ({ sub:'form', form: { edit: s.form && s.form.edit, date: s.form ? s.form.date : '30/09/2026', ref: s.form ? s.form.ref : '', desc: t ? t[1] : (s.form ? s.form.desc : ''), lines } }));
  }
  editDraft(j) {
    this.setState({ sub:'form', form: { edit:j.no, date:j.date + '/2026', ref:j.ref, desc:j.desc, lines:j.lines.map(l => ({ code:l.code, desc:'', dr:l.dr ? String(l.dr) : '', cr:l.cr ? String(l.cr) : '' })) } });
  }
  setF(p) { this.setState(s => ({ form: { ...s.form, ...p } })); }
  setLine(i, p) { this.setState(s => ({ form: { ...s.form, lines: s.form.lines.map((l, j) => j === i ? { ...l, ...p } : l) } })); }
  fTotals() {
    const f = this.state.form;
    const dr = r2(f.lines.reduce((t, l) => t + (parseFloat(l.dr) || 0), 0));
    const cr = r2(f.lines.reduce((t, l) => t + (parseFloat(l.cr) || 0), 0));
    return { dr, cr, diff: r2(dr - cr) };
  }
  fErrors(post) {
    const f = this.state.form, e = [];
    const t = this.fTotals();
    if (!/^\d{2}\/\d{2}\/\d{4}$/.test(f.date)) e.push('Date must be DD/MM/YYYY');
    else if (this.state.locked[f.date.split('/')[1]]) e.push(this.monthName(f.date.split('/')[1]) + ' is locked. Pick a date in an open month');
    if (!f.desc.trim()) e.push('Add a narration');
    const used = f.lines.filter(l => l.code || l.dr || l.cr);
    if (used.length < 2) e.push('A journal needs at least two lines');
    used.forEach((l, i) => {
      if (!l.code) e.push('Line ' + (f.lines.indexOf(l) + 1) + ': choose an account');
      else if (!(parseFloat(l.dr) > 0) && !(parseFloat(l.cr) > 0)) e.push('Line ' + (f.lines.indexOf(l) + 1) + ': enter a debit or a credit');
    });
    if (post && Math.abs(t.diff) > 0.001) e.push('Out of balance by ' + this.money(Math.abs(t.diff)));
    return e;
  }
  monthName(m) { return ({ '07':'July', '08':'August', '09':'September', '10':'October' })[m] || 'That month'; }
  saveJ(post) {
    const s = this.state, f = s.form;
    const e = this.fErrors(post);
    if (e.length) { this.toast(e[0], 'error'); return; }
    const lines = f.lines.filter(l => l.code).map(l => ({ code:l.code, dr:r2(parseFloat(l.dr) || 0), cr:r2(parseFloat(l.cr) || 0) }));
    const no = f.edit || 'JN-0' + s.next;
    const j = { no, date:f.date.slice(0, 5), src:'manual', desc:f.desc.trim(), ref:f.ref.trim() || '\u2014', status: post ? 'posted' : 'draft', lines };
    const journals = f.edit ? s.journals.map(x => x.no === f.edit ? j : x) : [j].concat(s.journals);
    this.setState({ journals, next: f.edit ? s.next : s.next + 1, sub:'journals', form:null, openJ:no, jsrc:'All' });
    this.toast(no + (post ? ' posted \u00b7 ' + this.money(this.jTotal(j)) : ' saved as draft'), post ? 'success' : 'neutral');
  }
  postDraft(j) {
    const s = this.state;
    if (s.locked[this.month(j.date)]) { this.toast(this.monthName(this.month(j.date)) + ' is locked', 'error'); return; }
    const dr = r2(j.lines.reduce((t, l) => t + l.dr, 0)), cr = r2(j.lines.reduce((t, l) => t + l.cr, 0));
    if (Math.abs(dr - cr) > 0.001) { this.toast('Out of balance. Edit the draft first', 'error'); return; }
    this.setState({ journals: s.journals.map(x => x.no === j.no ? { ...x, status:'posted' } : x) });
    this.toast(j.no + ' posted', 'success');
  }
  reverseJ(j) {
    const s = this.state;
    if (s.locked['09']) { this.toast('September is locked', 'error'); return; }
    const no = 'JN-0' + s.next;
    const r = { no, date:'30/09', src:'manual', desc:'Reversal of ' + j.no + ' \u00b7 ' + j.desc, ref:j.no, status:'posted', lines:j.lines.map(l => ({ code:l.code, dr:l.cr, cr:l.dr })) };
    this.setState({ journals: [r].concat(s.journals.map(x => x.no === j.no ? { ...x, status:'reversed' } : x)), next: s.next + 1, openJ: no });
    this.toast(j.no + ' reversed by ' + no, 'neutral');
  }
  saveNewAcct() {
    const s = this.state, n = s.nAcct;
    if (!n.name.trim()) { this.setState({ nErr:'Give the account a name' }); return; }
    if (!/^\d-\d{4}$/.test(n.code)) { this.setState({ nErr:'Code format is 6-1950' }); return; }
    if (s.accts.some(a => a.code === n.code)) { this.setState({ nErr:'Code ' + n.code + ' is already used' }); return; }
    const accts = s.accts.concat([A(n.code, n.name.trim(), n.type, 0, n.tax)]).sort((a, b) => a.code.localeCompare(b.code));
    this.setState({ accts, modal:null, nAcct:null, ctype:'All', cq:'' });
    this.toast(n.code + ' ' + n.name.trim() + ' added', 'success');
  }
  suggestCode(type) {
    const pre = { asset:'1', liability:'2', equity:'3', income:'4', cogs:'5', expense:'6' }[type];
    const used = this.state.accts.filter(a => a.code[0] === pre).map(a => parseInt(a.code.slice(2), 10));
    const n = Math.max.apply(null, used.concat([1000])) + 50;
    return pre + '-' + String(n).padStart(4, '0');
  }

  renderVals() {
    const s = this.state;
    const { b, cnt } = this.balances();
    const drafts = s.journals.filter(j => j.status === 'draft');
    const posted = s.journals.filter(j => j.status === 'posted');
    const nb = a => this.normal(a, b[a.code] || 0);
    const sumT = t => r2(s.accts.filter(a => a.type === t).reduce((q, a) => q + nb(a), 0));
    const inc = sumT('income'), cg = sumT('cogs'), ex = sumT('expense');
    const gross = r2(inc - cg), net = r2(gross - ex);
    const assets = sumT('asset'), liab = sumT('liability'), eq = sumT('equity');
    const cq = s.cq.trim().toLowerCase();
    const jq = s.jq.trim().toLowerCase();
    const subDefs = [['coa','Chart of accounts', s.accts.length], ['journals','Journal entries', s.journals.length], ['statements','Statements', ''], ['close','Period close', '']];
    const f = s.form;
    const ft = f ? this.fTotals() : { dr:0, cr:0, diff:0 };
    const fe = f ? this.fErrors(true) : [];
    const gstNet = r2(-(b['2-1300'] || 0) - (b['2-1310'] || 0));
    const gstCovered = (b['1-1210'] || 0) >= gstNet;
    const checks = [
      ['recon', 'Bank accounts reconciled', s.recon ? 'Marked done by Aminul' : 'Tick once statements match the ledger', s.recon, true],
      ['count', 'Stock count posted', 'SA-0230 posted 27/09', true, false],
      ['drafts', 'No draft journals', drafts.length ? drafts.length + ' draft' + (drafts.length > 1 ? 's' : '') + ' still open: ' + drafts.map(d => d.no).join(', ') : 'All journals posted', !drafts.length, false],
      ['gst', 'GST set aside for BAS', 'Net GST owing ' + this.money(gstNet) + ' \u00b7 Tax saver holds ' + this.money(b['1-1210'] || 0), gstCovered, false]
    ];
    const allOk = checks.every(c => c[3]);
    const stRow = (label, a, bb, kind, code, onClick) => {
      const hd = kind === 'head', tot = kind === 'total', grand = kind === 'grand';
      return {
        label, a: a === null ? '' : a, b: bb === null ? '' : bb, code: code || '',
        onClick: onClick || (() => {}),
        codeStyle: MONO + 'font-size:11px;color:#9aa7b6;width:52px;flex:none;' + (code ? '' : 'display:none;'),
        style: 'display:flex;align-items:center;gap:14px;padding:' + (hd ? '14px 22px 6px' : '7px 22px') + ';font-size:' + (hd ? '11px' : '13px') + ';'
          + (hd ? MONO + 'letter-spacing:.08em;text-transform:uppercase;color:#5d6b7d;font-weight:600;' : '')
          + (tot ? 'font-weight:700;border-top:1px solid #e4eaf1;margin-top:2px;' : '')
          + (grand ? 'font-weight:700;font-size:14.5px;background:#f6f9fc;border-top:1.5px solid #0f1c2b;margin-top:6px;padding:11px 22px;' : '')
          + (onClick ? 'cursor:pointer;' : ''),
        aStyle: MONO + 'width:140px;text-align:right;font-size:inherit;',
        bStyle: MONO + 'width:140px;text-align:right;font-size:inherit;' + (s.st === 'pl' ? 'color:#8b98a8;' : '')
      };
    };
    const openLg = code => () => this.setState({ modal:'ledger', lgCode: code });
    let stRows = [], stCheck = '', stOk = true;
    if (s.st === 'pl') {
      const pct = v => inc ? (v / inc * 100).toFixed(1) + '%' : '\u2014';
      const block = (t, title) => [stRow(title, null, null, 'head')].concat(s.accts.filter(a => a.type === t && Math.abs(nb(a)) > 0.004).map(a => stRow(a.name, this.sgn(nb(a)), pct(nb(a)), '', a.code, openLg(a.code))));
      stRows = block('income', 'Income').concat([stRow('Total income', this.money(inc), '100.0%', 'total')])
        .concat(block('cogs', 'Cost of sales')).concat([stRow('Gross profit', this.sgn(gross), pct(gross), 'total')])
        .concat(block('expense', 'Expenses')).concat([stRow('Total expenses', this.money(ex), pct(ex), 'total'), stRow('Net profit', this.sgn(net), pct(net), 'grand')]);
      stCheck = 'Net margin ' + pct(net); stOk = net >= 0;
    } else if (s.st === 'bs') {
      const block = (t, title) => [stRow(title, null, null, 'head')].concat(s.accts.filter(a => a.type === t && Math.abs(nb(a)) > 0.004).map(a => stRow(a.name, null, this.sgn(nb(a)), '', a.code, openLg(a.code))));
      stRows = block('asset', 'Assets').concat([stRow('Total assets', null, this.money(assets), 'total')])
        .concat(block('liability', 'Liabilities')).concat([stRow('Total liabilities', null, this.money(liab), 'total')])
        .concat(block('equity', 'Equity')).concat([stRow('Current year earnings', null, this.sgn(net), ''), stRow('Total equity', null, this.money(r2(eq + net)), 'total'),
          stRow('Liabilities + equity', null, this.money(r2(liab + eq + net)), 'grand')]);
      stOk = Math.abs(assets - liab - eq - net) < 0.01;
      stCheck = stOk ? 'Balanced \u00b7 assets equal liabilities plus equity' : 'Out of balance by ' + this.money(Math.abs(assets - liab - eq - net));
    } else {
      let td = 0, tc = 0;
      stRows = [stRow('Account', 'Debit', 'Credit', 'head')].concat(s.accts.filter(a => Math.abs(b[a.code] || 0) > 0.004).map(a => {
        const v = b[a.code]; if (v > 0) td += v; else tc -= v;
        return stRow(a.name, v > 0 ? this.money(v) : '', v < 0 ? this.money(-v) : '', '', a.code, openLg(a.code));
      }));
      td = r2(td); tc = r2(tc);
      stRows.push(stRow('Totals', this.money(td), this.money(tc), 'grand'));
      stOk = Math.abs(td - tc) < 0.01;
      stCheck = stOk ? 'Debits equal credits' : 'Out by ' + this.money(Math.abs(td - tc));
    }
    const lgA = s.lgCode ? this.acct(s.lgCode) : null;
    let lgRows = [];
    if (lgA) {
      const dr = this.type(lgA.type).dr;
      const openSigned = (b[lgA.code] || 0) - posted.reduce((t, j) => t + j.lines.filter(l => l.code === lgA.code).reduce((q, l) => q + l.dr - l.cr, 0), 0);
      let run = openSigned;
      const lines = posted.slice().reverse().flatMap(j => j.lines.filter(l => l.code === lgA.code).map(l => ({ j, l })));
      lgRows = [{ date:'01/09', no:'', desc:'Opening balance', dr:'', cr:'', bal:this.sgn(dr ? run : -run), style:'display:grid;grid-template-columns:0.7fr 0.8fr 2fr 0.9fr 0.9fr 1fr;gap:10px;padding:9px 22px;border-bottom:1px solid #eef2f6;background:#fbfcfe;color:#5d6b7d' }]
        .concat(lines.map(x => { run = r2(run + x.l.dr - x.l.cr); return { date:x.j.date, no:x.j.no, desc:x.j.desc, dr:x.l.dr ? this.money(x.l.dr) : '', cr:x.l.cr ? this.money(x.l.cr) : '', bal:this.sgn(dr ? run : -run), style:'display:grid;grid-template-columns:0.7fr 0.8fr 2fr 0.9fr 0.9fr 1fr;gap:10px;padding:9px 22px;border-bottom:1px solid #f0f4f8' }; }));
    }
    const n = s.nAcct;

    return {
      nav: NAV.map(x => {
        const on = x[0] === 'Accounting';
        return {
          name: x[0], key: x[1],
          style: 'display:flex;align-items:center;gap:10px;padding:8px 10px;border-radius:8px;cursor:pointer;font-size:13px;font-weight:500;margin-bottom:1px;' + (on ? 'background:rgba(255,255,255,.13);color:#ffffff;box-shadow:inset 2px 0 0 #4da3e8;' : 'color:#c4dced;'),
          iconStyle: 'width:20px;height:20px;border-radius:5px;display:grid;place-items:center;' + MONO + 'font-size:9px;font-weight:600;flex:none;' + (on ? 'background:#4da3e8;color:#06283a;' : 'background:rgba(255,255,255,.1);color:#9dbdd2;'),
          caretStyle: 'color:#7fa3bb;font-size:11px;flex:none;' + (x[2] ? '' : 'visibility:hidden;')
        };
      }),
      subTabs: subDefs.map(d => {
        const on = s.sub === d[0] || (d[0] === 'journals' && s.sub === 'form');
        return { name:d[1], count:d[2], onClick: () => this.setState({ sub:d[0], form:null }), style:this.pageTab(on),
          countStyle: d[2] === '' ? 'display:none' : MONO + 'font-size:10.5px;border-radius:4px;padding:1px 5px;margin-left:6px;' + (on ? 'background:#eaf1fb;color:#14549e;' : 'background:#eef2f6;color:#7d8b9c;') };
      }),
      isCoa: s.sub === 'coa', isJournals: s.sub === 'journals', isForm: s.sub === 'form' && !!f, isStatements: s.sub === 'statements', isClose: s.sub === 'close',
      coaSub: s.accts.length + ' accounts \u00b7 balances at 29/09/2026 \u00b7 click an account for its ledger',
      exportCoa: () => this.toast('Chart of accounts exported to CSV', 'neutral'),
      openAddAcct: () => this.setState({ modal:'acct', nErr:'', nAcct:{ type:'expense', code:this.suggestCode('expense'), name:'', tax:'GST 10%' } }),
      typeTabs: [{ id:'All', name:'All' }].concat(TYPES).map(t => ({ name:t.name, onClick: () => this.setState({ ctype:t.id }), style:this.seg(s.ctype === t.id) })),
      cq: s.cq, onCq: e => this.setState({ cq: e.target.value }),
      groups: TYPES.filter(t => s.ctype === 'All' || s.ctype === t.id).map(t => {
        const rows = s.accts.filter(a => a.type === t.id && (!cq || (a.code + ' ' + a.name).toLowerCase().indexOf(cq) > -1));
        return {
          name: t.name, note: t.note + ' \u00b7 normal balance ' + (t.dr ? 'debit' : 'credit'),
          dot: 'width:9px;height:9px;border-radius:3px;flex:none;background:' + t.c,
          total: this.sgn(r2(rows.reduce((q, a) => q + nb(a), 0))),
          rows: rows.map(a => {
            const v = nb(a);
            return {
              code:a.code, name:a.name, tax:a.tax, count: cnt[a.code] || '\u2014',
              tag:a.tag, tagStyle: a.tag ? this.badge(a.tag === 'Payment account' ? TONE.info : a.tag === 'Contra' ? TONE.warn : TONE.neutral) + ';font-size:10.5px;padding:2px 6px' : 'display:none',
              bal: this.sgn(v), balStyle: MONO + 'font-size:12.5px;text-align:right;font-weight:600;' + (v < -0.004 ? 'color:#b02020;' : Math.abs(v) < 0.005 ? 'color:#9aa7b6;' : ''),
              open: openLg(a.code)
            };
          })
        };
      }).filter(g => g.rows.length),

      jSub: 'September 2026 \u00b7 ' + posted.length + ' posted \u00b7 ' + drafts.length + ' draft \u00b7 module entries post automatically',
      exportJ: () => this.toast('Journals exported to CSV', 'neutral'),
      newJournal: () => this.setState({ sub:'form', form: { edit:null, date:'30/09/2026', ref:'', desc:'', lines:[{ code:'', desc:'', dr:'', cr:'' }, { code:'', desc:'', dr:'', cr:'' }] } }),
      srcTabs: ['All','Sale','Purchase','Expense','Stock','Transfer','Manual'].map(k => ({ name:k, onClick: () => this.setState({ jsrc:k }), style:this.seg(s.jsrc === k) })),
      jq: s.jq, onJq: e => this.setState({ jq: e.target.value }),
      journals: s.journals.filter(j => (s.jsrc === 'All' || SRC[j.src][0] === s.jsrc) && (!jq || (j.no + ' ' + j.ref + ' ' + j.desc).toLowerCase().indexOf(jq) > -1)).map(j => {
        const open = s.openJ === j.no;
        const st = j.status === 'posted' ? ['Posted', TONE.success] : j.status === 'draft' ? ['Draft', TONE.warn] : ['Reversed', TONE.neutral];
        const acts = [];
        const A2 = (label, fn, danger, primary) => ({ label, onClick: fn, style:'height:28px;padding:0 11px;border-radius:7px;display:flex;align-items:center;font-size:12px;font-weight:600;cursor:pointer;' + (primary ? 'background:#1668c9;color:#ffffff;' : danger ? 'border:1px solid #f3cfcf;background:#fdf4f4;color:#b02020;' : 'border:1px solid #dfe6ee;background:#ffffff;color:#4a5866;') });
        if (j.status === 'draft') {
          acts.push(A2('Delete', () => { this.setState(ss => ({ journals: ss.journals.filter(x => x.no !== j.no) })); this.toast(j.no + ' deleted', 'neutral'); }, true));
          acts.push(A2('Edit', () => this.editDraft(j)));
          acts.push(A2('Post', () => this.postDraft(j), false, true));
        } else if (j.src === 'manual' && j.status === 'posted') acts.push(A2('Reverse', () => this.reverseJ(j), true));
        else if (j.src !== 'manual') acts.push(A2('Open ' + j.ref, () => this.toast('Opens ' + j.ref + ' in ' + SRC[j.src][0] + (j.src === 'sale' ? 's' : j.src === 'stock' ? '' : 's'), 'neutral')));
        return {
          no:j.no, date:j.date + '/26', desc:j.desc, ref:j.ref, amount:this.money(this.jTotal(j)),
          src:SRC[j.src][0], srcBadge:this.badge(TONE[SRC[j.src][1]]),
          status:st[0], stBadge:this.badge(st[1]), open,
          toggle: () => this.setState({ openJ: open ? null : j.no }),
          rowStyle:'display:grid;grid-template-columns:0.8fr 0.8fr 0.9fr 2.4fr 1fr 1fr 0.9fr;gap:12px;padding:10px 16px;border-bottom:1px solid #f0f4f8;align-items:center;cursor:pointer;' + (open ? 'background:#f6faff;' : '') + (j.status === 'reversed' ? 'opacity:.6;' : ''),
          lines: j.lines.map(l => { const a = this.acct(l.code); return { code:l.code, name:a ? a.name : l.code, dr:l.dr ? this.money(l.dr) : '', cr:l.cr ? this.money(l.cr) : '', nameStyle: l.cr ? 'padding-left:22px;color:#4a5866' : 'font-weight:500' }; }),
          note: j.src === 'manual' ? (j.status === 'draft' ? 'Draft. Not in balances or statements until posted' : 'Manual journal by Aminul') : 'Created automatically from ' + SRC[j.src][0] + (j.src === 'stock' ? ' movements' : 's') + '. Change it at the source',
          actions: acts
        };
      }),
      noJ: !s.journals.some(j => (s.jsrc === 'All' || SRC[j.src][0] === s.jsrc) && (!jq || (j.no + ' ' + j.ref + ' ' + j.desc).toLowerCase().indexOf(jq) > -1)),

      goJournals: () => this.setState({ sub:'journals', form:null }),
      fCrumb: f && f.edit ? 'Edit ' + f.edit : 'Manual journal',
      fTitle: f && f.edit ? 'Edit ' + f.edit : 'New manual journal',
      fDate: f ? f.date : '', fRef: f ? f.ref : '', fDesc: f ? f.desc : '',
      fDateStyle: 'width:100%;height:36px;border:1px solid ' + (f && s.locked[(f.date.split('/')[1]) || ''] ? '#d93a3a' : '#dfe6ee') + ';border-radius:8px;padding:0 11px;' + MONO + 'font-size:12.5px;outline:none',
      onFDate: e => this.setF({ date: e.target.value }), onFRef: e => this.setF({ ref: e.target.value }), onFDesc: e => this.setF({ desc: e.target.value }),
      templates: TEMPLATES.map(t => ({ name:t[0], onClick: () => this.newForm(t[0]) })),
      acctOpts: s.accts.map(a => ({ code:a.code, label:a.code + '  ' + a.name })),
      fLines: f ? f.lines.map((l, i) => ({
        n: i + 1, code:l.code, desc:l.desc, dr:l.dr, cr:l.cr,
        selStyle: 'width:100%;height:32px;border:1px solid #dfe6ee;border-radius:8px;padding:0 6px;font-family:\'Public Sans\',sans-serif;font-size:12.5px;background:#ffffff;outline:none;cursor:pointer;min-width:0',
        onCode: e => this.setLine(i, { code: e.target.value }),
        onDesc: e => this.setLine(i, { desc: e.target.value }),
        onDr: e => { const v = e.target.value.replace(/[^0-9.]/g, ''); this.setLine(i, v ? { dr:v, cr:'' } : { dr:v }); },
        onCr: e => { const v = e.target.value.replace(/[^0-9.]/g, ''); this.setLine(i, v ? { cr:v, dr:'' } : { cr:v }); },
        remove: () => this.setState(ss => ({ form: { ...ss.form, lines: ss.form.lines.length > 2 ? ss.form.lines.filter((_, j) => j !== i) : ss.form.lines.map((x, j) => j === i ? { code:'', desc:'', dr:'', cr:'' } : x) } }))
      })) : [],
      addLine: () => this.setState(ss => ({ form: { ...ss.form, lines: ss.form.lines.concat([{ code:'', desc:'', dr:'', cr:'' }]) } })),
      fDr: this.money(ft.dr), fCr: this.money(ft.cr),
      balBox: 'border-radius:12px;padding:15px 17px;' + (Math.abs(ft.diff) < 0.001 && ft.dr > 0 ? 'background:#e7f6ee;color:#0f6b43;border:1px solid #bfe5d1;' : ft.dr || ft.cr ? 'background:#fdeaea;color:#b02020;border:1px solid #f3cfcf;' : 'background:#ffffff;color:#5d6b7d;border:1px solid #dfe6ee;'),
      balLabel: Math.abs(ft.diff) < 0.001 && ft.dr > 0 ? 'Balanced' : ft.dr || ft.cr ? (ft.diff > 0 ? 'Needs more credit' : 'Needs more debit') : 'Enter amounts',
      balValue: this.money(Math.abs(ft.diff) < 0.001 ? ft.dr : Math.abs(ft.diff)),
      balHint: Math.abs(ft.diff) < 0.001 && ft.dr > 0 ? 'Debits and credits both total ' + this.money(ft.dr) : 'Debits ' + this.money(ft.dr) + ' \u00b7 credits ' + this.money(ft.cr),
      fHasErr: !!f && fe.length > 0 && (ft.dr > 0 || ft.cr > 0), fErrs: fe.slice(0, 4).map(m => ({ msg:m })),
      postStyle: 'height:34px;padding:0 15px;border-radius:8px;display:flex;align-items:center;font-size:12.5px;font-weight:600;color:#ffffff;cursor:pointer;background:' + (fe.length ? '#9fbde0' : '#1668c9'),
      saveDraft: () => this.saveJ(false), postJ: () => this.saveJ(true),

      stTabs: [['pl','Profit & loss'],['bs','Balance sheet'],['tb','Trial balance']].map(t => ({ name:t[1], onClick: () => this.setState({ st:t[0] }), style:this.seg(s.st === t[0]) })),
      stRows, stCheckText: stCheck,
      stCheck: 'font-size:12px;font-weight:600;border-radius:7px;padding:6px 11px;' + (stOk ? 'background:#e7f6ee;color:#0f6b43;' : 'background:#fdeaea;color:#b02020;'),
      printSt: () => this.toast('Statement sent to printer', 'neutral'),
      exportSt: () => this.toast('Statement exported as PDF', 'neutral'),

      periods: [['07','July 2026','Locked 05/08 by Sara Rahman'],['08','August 2026','Locked 04/09 by Sara Rahman'],['09','September 2026', s.locked['09'] ? 'Locked just now by Aminul' : posted.length + ' posted journals \u00b7 ' + drafts.length + ' draft']].map(p => {
        const lk = s.locked[p[0]];
        return {
          name:p[1], meta:p[2], status: lk ? 'Locked' : 'Open', badge: this.badge(lk ? TONE.neutral : TONE.info),
          mark: lk ? '\u2713' : '', dot: 'width:30px;height:30px;border-radius:50%;display:grid;place-items:center;font-size:12px;font-weight:700;flex:none;' + (lk ? 'background:#2f9e63;color:#ffffff;' : 'box-shadow:inset 0 0 0 2px #1668c9;color:#1668c9;'),
          action: lk ? 'Unlock' : '', actStyle: lk && p[0] === '09' ? 'height:30px;padding:0 11px;border-radius:7px;border:1px solid #dfe6ee;display:flex;align-items:center;font-size:12px;font-weight:600;color:#4a5866;cursor:pointer' : 'display:none',
          onAction: () => { this.setState(ss => ({ locked: { ...ss.locked, [p[0]]: false } })); this.toast(p[1] + ' reopened \u00b7 logged in audit trail', 'warn'); }
        };
      }),
      checks: checks.map(c => ({
        name:c[1], note:c[2], mark: c[3] ? '\u2713' : '',
        noteStyle: 'font-size:11.5px;margin-top:1px;line-height:1.4;' + (c[3] ? 'color:#7d8b9c;' : 'color:#b45309;'),
        box: 'width:20px;height:20px;border-radius:6px;display:grid;place-items:center;font-size:11px;font-weight:700;flex:none;margin-top:1px;' + (c[3] ? 'background:#2f9e63;color:#ffffff;' : 'box-shadow:inset 0 0 0 1.5px #d98c1a;color:transparent;'),
        rowStyle: 'display:flex;gap:10px;align-items:flex-start;' + (c[4] ? 'cursor:pointer;' : ''),
        onClick: c[4] ? () => this.setState({ recon: !s.recon }) : c[0] === 'drafts' && !c[3] ? () => this.setState({ sub:'journals', jsrc:'Manual' }) : () => {}
      })),
      lockLabel: s.locked['09'] ? 'September is locked' : allOk ? 'Lock September' : 'Complete the checklist to lock',
      lockStyle: 'margin-top:18px;height:42px;border-radius:10px;display:grid;place-items:center;font-size:13px;font-weight:700;' + (s.locked['09'] ? 'background:#e7f6ee;color:#0f6b43;' : allOk ? 'background:#1668c9;color:#ffffff;cursor:pointer;' : 'background:#eef2f6;color:#8b98a8;'),
      lockSep: () => { if (s.locked['09'] || !allOk) return; this.setState(ss => ({ locked: { ...ss.locked, '09': true } })); this.toast('September 2026 locked', 'success'); },

      ledgerOpen: s.modal === 'ledger' && !!lgA,
      lg: lgA ? { code:lgA.code, name:lgA.name, type:this.type(lgA.type).name, bal:this.sgn(nb(lgA)), rows:lgRows } : { code:'', name:'', type:'', bal:'', rows:[] },
      acctFormOpen: s.modal === 'acct' && !!n,
      typeOpts: TYPES.map(t => ({ id:t.id, name:t.name })),
      nType: n ? n.type : '', nCode: n ? n.code : '', nName: n ? n.name : '', nTax: n ? n.tax : '',
      onNType: e => { const t = e.target.value; this.setState(ss => ({ nAcct: { ...ss.nAcct, type:t, code:this.suggestCode(t) }, nErr:'' })); },
      onNCode: e => this.setState(ss => ({ nAcct: { ...ss.nAcct, code:e.target.value }, nErr:'' })),
      onNName: e => this.setState(ss => ({ nAcct: { ...ss.nAcct, name:e.target.value }, nErr:'' })),
      onNTax: e => this.setState(ss => ({ nAcct: { ...ss.nAcct, tax:e.target.value } })),
      nHint: n ? this.type(n.type).name + ' accounts carry a ' + (this.type(n.type).dr ? 'debit' : 'credit') + ' balance and appear on the ' + (['income','cogs','expense'].indexOf(n.type) > -1 ? 'profit & loss.' : 'balance sheet.') : '',
      nHasErr: !!s.nErr, nErr: s.nErr,
      saveNewAcct: () => this.saveNewAcct(),
      closeAll: () => this.setState({ modal:null, nAcct:null, nErr:'' }),

      toasts: s.toasts.map(t => ({ msg:t.msg, dot:'width:8px;height:8px;border-radius:50%;flex:none;background:' + TONE[t.tone][2] }))
    };
  }
}
return Component;
})();
});
