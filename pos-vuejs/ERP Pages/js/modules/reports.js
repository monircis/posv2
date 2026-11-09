// ReportsLogic
DC.add(function () {
const ReportsLogic = (() => {
const NAV = [
  ['Home','Hm',0],['User Management','Us',1],['Contacts','Ct',1],['Products','Pr',1],
  ['Manufacturing','Mf',0],['Purchases','Pu',1],['Sell','Sl',1],['Stock Transfers','St',0],
  ['Stock Adjustment','Sa',0],['Expenses','Ex',1],['Payment Accounts','Pa',1],['Accounting','Ac',1],
  ['Reports','Rp',1],['Bookings','Bk',0],['Kitchen','Kt',0],['Orders','Or',0],
  ['Settings','Se',1],['HRM','Hr',1]
];
const TONE = {
  info:['#eaf1fb','#14549e','#1668c9'], warn:['#fdf1dd','#8a5300','#d98c1a'], error:['#fdeaea','#b02020','#d93a3a'],
  success:['#e7f6ee','#0f6b43','#2f9e63'], neutral:['#eef2f6','#5d6b7d','#8fa3b6']
};
const MONO = "font-family:'IBM Plex Mono',monospace;";
const LOCS = [{id:'all',name:'All locations'},{id:'main',name:'Main branch'},{id:'bank',name:'Bankstown shop'},{id:'cold',name:'Cold store'}];
const LF = { main:1, bank:0.48, cold:0.22 };
const EXSHARE = { all:1, main:0.7, bank:0.22, cold:0.08 };
const RANGES = [['today','Today',90,90],['week','This week',84,90],['month','This month',62,90],['last','Last month',31,61],['quarter','This quarter',0,90]];
const rnd = i => { const x = Math.sin(i * 12.9898 + 78.233) * 43758.5453; return x - Math.floor(x); };
const dow = d => ((d + 3) % 7 + 7) % 7;
const lbl = d => { const t = new Date(2026, 6, 1 + d); return String(t.getDate()).padStart(2, '0') + '/' + String(t.getMonth() + 1).padStart(2, '0'); };
const r2 = n => Math.round(n * 100) / 100;
const daySales = (d, loc) => { const w = dow(d); const base = 2600 + (w === 5 || w === 6 ? 1500 : w === 0 ? 900 : 0); return LF[loc] * (base + 700 * rnd(d * 7 + { main:1, bank:2, cold:3 }[loc])); };
const sales = (d, loc) => loc === 'all' ? daySales(d, 'main') + daySales(d, 'bank') + daySales(d, 'cold') : daySales(d, loc);
const purch = (d, loc) => sales(d, loc) * 0.62 * (dow(d) === 1 || dow(d) === 4 ? 2.9 : 0.18);
const range = (a, b) => { const o = []; for (let i = a; i <= b; i++) o.push(i); return o; };
const sum = (arr, fn) => arr.reduce((t, x) => t + fn(x), 0);
const MONTH_ALL = sum(range(62, 90), d => sales(d, 'all'));
const CUST = [['walk','Walk-in customers','Retail',0.71,0],['amin','Al Amin Catering','Wholesale',0.09,0.22],['grill','Lakemba Grill House','Wholesale',0.07,0.3],['mosque','Sydney Mosque Kitchen','Wholesale',0.05,0.12],['kebab','Bankstown Kebabs','Wholesale',0.043,0.4],['fatima','Fatima Hassan','Account',0.015,0.35],['omar','Omar Siddiqui','Account',0.012,0]];
const SUPP = [['baraka','Baraka Meats',0.46,2,0.08],['poultry','Sydney Poultry',0.22,1,0.28],['riverina','Riverina Lamb Co',0.18,3,0.31],['spice','Halal Spice Co',0.08,5,0],['pack','Pack & Wrap Supplies',0.06,4,0.12]];
const EXP = [['Rent',6200,1],['Casual wages',6240,0],['Utilities',1284.6,1],['Packaging',890,1],['Repairs',680,1],['Cleaning',440,1],['Fuel & delivery',420,1],['Bank & merchant fees',312,0],['Marketing',150,1]];
const PRODS = [
  ['Beef Mince','Beef',7.2,12.9,0.14,'kg',{main:54,bank:6,cold:180},15],['Beef Rump Steak','Beef',16.8,27.9,0.1,'kg',{main:41,bank:4,cold:95},10],
  ['Beef Scotch Fillet','Beef',19.5,34.9,0.07,'kg',{main:0,bank:2,cold:62},8],['Beef T-Bone','Beef',20.4,32.9,0.04,'kg',{main:18,bank:0,cold:40},8],
  ['Lamb Chops','Lamb',19.9,31.9,0.11,'kg',{main:27,bank:11,cold:88},10],['Lamb Shoulder','Lamb',13.1,21.9,0.06,'kg',{main:3,bank:5,cold:46},8],
  ['Lamb Leg','Lamb',11.6,19.9,0.07,'kg',{main:12,bank:3,cold:70},8],['Chicken Breast','Chicken',8.4,14.9,0.12,'kg',{main:46,bank:7,cold:140},15],
  ['Chicken Thigh','Chicken',5.9,11.9,0.09,'kg',{main:9,bank:21,cold:120},15],['Goat Curry Cut','Goat',15.2,24.9,0.06,'kg',{main:12,bank:1,cold:34},6],
  ['Basmati Rice 5kg','Grocery',11.5,17.9,0.02,'ea',{main:44,bank:8,cold:120},10],['Spice Mix 200g','Grocery',3.1,5.9,0.01,'ea',{main:60,bank:14,cold:0},20],
  ['Pita Bread 6 pack','Grocery',2.4,4.5,0.01,'ea',{main:4,bank:18,cold:0},12]
];
const CATS = ['Beef','Lamb','Chicken','Goat','Grocery'];
const MOS = [['MO-0112','Beef Sausages','kg',120,5.1,0.9,0.96,'Done'],['MO-0113','Lamb Kofta','kg',60,9.8,1.2,0.94,'Done'],['MO-0114','Marinated Chicken Thigh','kg',80,6.6,0.7,0.98,'Done'],['MO-0115','Beef Burger Patties','ea',400,1.1,0.18,0.97,'Done'],['MO-0116','Beef Sausages','kg',120,5.1,0.9,0,'In progress'],['MO-0117','Spiced Lamb Mince','kg',45,8.9,0.6,0,'Planned']];
const C = (h, t, w, sum) => ({ h, t, w: w || '1fr', sum: !!sum });

function buckets(a, b) {
  const n = b - a + 1;
  if (n <= 31) return range(a, b).map(d => ({ label: lbl(d), days: [d] }));
  const o = [];
  for (let s = a; s <= b; s += 7) o.push({ label: lbl(s), days: range(s, Math.min(b, s + 6)) });
  return o;
}
function netOf(g) { return g * (1 - 0.025 - 0.012); }

const REPORTS = [
  { id:'sales', group:'Sales', name:'Sales', hint:'Net sales by period', desc:'Invoices, discounts, returns and GST for every completed sale', chart:'time', contact:'customer',
    gen: x => {
      const sh = x.contact === 'all' ? 1 : CUST.find(c => c[0] === x.contact)[3];
      const dueSh = x.contact === 'all' ? 0.04 : CUST.find(c => c[0] === x.contact)[4];
      const bk = x.bk.map(b => { const g = sum(b.days, d => sales(d, x.loc)) * sh; const n = netOf(g); return { b, g, n }; });
      const rows = bk.map(o => ({ c:[o.b.label, Math.max(1, Math.round(o.g / (sh < 0.5 ? 480 : 42))), o.g, -o.g * 0.025, -o.g * 0.012, o.n, o.n / 11, o.n * dueSh] }));
      const net = sum(bk, o => o.n), inv = sum(rows, r => r.c[1]);
      return { kpis:[['Net sales', net, 'money', 1], ['Invoices', inv, 'int', 1], ['Average sale', net / Math.max(1, inv), 'money', 1], ['Outstanding', net * dueSh, 'money', 0]],
        series: bk.map(o => o.n), seriesName:'Net sales', table:'By period', cols:[C('Period','text','0.9fr'), C('Invoices','int','0.7fr',1), C('Gross','money','',1), C('Discounts','money','',1), C('Returns','money','',1), C('Net','money','',1), C('GST','money','0.8fr',1), C('Due','money','0.8fr',1)], rows };
    } },
  { id:'customers', group:'Sales', name:'Customers', hint:'Sales and balances per customer', desc:'Who buys, how much, and what they still owe', chart:'rank', contact:'customer',
    gen: x => {
      const S = netOf(sum(x.days, d => sales(d, x.loc)));
      const list = CUST.filter(c => x.contact === 'all' || c[0] === x.contact);
      const rows = list.map((c, i) => { const v = S * c[3]; return { c:[c[1], c[2], Math.max(1, Math.round(v / (c[0] === 'walk' ? 42 : 520))), v, v * (1 - c[4]), v * c[4], c[0] === 'walk' ? lbl(x.to) : lbl(x.to - (i % 4))] }; });
      const tot = sum(rows, r => r.c[3]), due = sum(rows, r => r.c[5]);
      const ws = sum(rows.filter(r => r.c[1] !== 'Retail'), r => r.c[3]);
      return { kpis:[['Customers with sales', rows.length, 'int', 1], ['Sales', tot, 'money', 1], ['Outstanding', due, 'money', 0], ['Wholesale share', tot ? ws / tot * 100 : 0, 'pct', 1]],
        rankName:'Sales by customer', rank: rows.map(r => [r.c[0], r.c[3]]), table:'Customer balances',
        cols:[C('Customer','text','1.8fr'), C('Type','text','0.9fr'), C('Invoices','int','0.7fr',1), C('Sales','money','',1), C('Paid','money','',1), C('Due','money','',1), C('Last sale','text','0.8fr')], rows };
    } },
  { id:'products', group:'Sales', name:'Product sales', hint:'Best sellers and margin', desc:'Quantity, revenue and gross margin per product', chart:'rank', cat:true,
    gen: x => {
      const S = netOf(sum(x.days, d => sales(d, x.loc)));
      const rows = PRODS.filter(p => x.cat === 'all' || p[1] === x.cat).map(p => { const rev = S * p[4] / 1.0; const q = rev / p[3]; const cost = q * p[2]; return { c:[p[0], p[1], q, p[5], rev, cost, rev ? (rev - cost) / rev * 100 : 0] }; });
      const rev = sum(rows, r => r.c[4]), cost = sum(rows, r => r.c[5]);
      const top = rows.slice().sort((a, b) => b.c[4] - a.c[4])[0];
      return { kpis:[['Products sold', rows.length, 'int', 1], ['Revenue', rev, 'money', 1], ['Gross margin', rev ? (rev - cost) / rev * 100 : 0, 'pct', 1], ['Top product', top ? top.c[0] : '\u2014', 'text']],
        rankName:'Revenue by product', rank: rows.map(r => [r.c[0], r.c[4]]), table:'Products',
        cols:[C('Product','text','1.7fr'), C('Category','text','0.8fr'), C('Qty sold','qty','0.8fr'), C('Unit','text','0.4fr'), C('Revenue','money','',1), C('Cost','money','',1), C('Margin','pct','0.7fr')], rows };
    } },
  { id:'payments', group:'Sales', name:'Payments', hint:'Money in and out by method', desc:'How customers paid and what went out of each payment method', chart:'rank',
    gen: x => {
      const S = netOf(sum(x.days, d => sales(d, x.loc)));
      const P = sum(x.days, d => purch(d, x.loc));
      const E = sum(EXP, e => e[1]) * x.days.length / 29 * EXSHARE[x.loc];
      const M = [['Cash', 0.34, 31, S * 0.03 + E * 0.05], ['Card \u00b7 Tyro', 0.58, 48, S * 0.004], ['Bank transfer', 0.06, 620, P * 0.82 + E * 0.8], ['Amex Business', 0, 1, P * 0.18 + E * 0.15], ['Store credit', 0.02, 55, 0]];
      const rows = M.map(m => ({ c:[m[0], Math.round(S * m[1] / m[2]) + (m[3] ? Math.round(m[3] / 900) : 0), S * m[1], -m[3], S * m[1] - m[3]] }));
      const inn = sum(rows, r => r.c[2]), out = -sum(rows, r => r.c[3]);
      return { kpis:[['Received', inn, 'money', 1], ['Paid out', out, 'money', 0], ['Net cash flow', inn - out, 'money', 1], ['Card share', 58, 'pct', 1]],
        rankName:'Received by method', rank: rows.filter(r => r.c[2] > 0).map(r => [r.c[0], r.c[2]]), table:'By payment method',
        cols:[C('Method','text','1.6fr'), C('Transactions','int','',1), C('Received','money','',1), C('Paid out','money','',1), C('Net','money','',1)], rows };
    } },
  { id:'purchases', group:'Purchasing', name:'Purchases', hint:'Purchase orders by period', desc:'What was ordered, received and returned to suppliers', chart:'time', contact:'supplier',
    gen: x => {
      const sh = x.contact === 'all' ? 1 : SUPP.find(s => s[0] === x.contact)[2];
      const bk = x.bk.map(b => ({ b, v: sum(b.days, d => purch(d, x.loc)) * sh }));
      const rows = bk.map((o, i) => { const rec = o.v * (0.97 + 0.03 * rnd(i + 40)); return { c:[o.b.label, o.v > 0 ? Math.max(1, Math.round(o.v / 1400)) : 0, o.v, rec, -rec * 0.01, rec * 0.99] }; });
      const tot = sum(rows, r => r.c[5]);
      return { kpis:[['Purchases', tot, 'money', 0], ['Purchase orders', sum(rows, r => r.c[1]), 'int', 1], ['Paid', tot * 0.86, 'money', 1], ['Owed to suppliers', tot * 0.14, 'money', 0]],
        series: bk.map(o => o.v), seriesName:'Purchases', table:'By period',
        cols:[C('Period','text','0.9fr'), C('POs','int','0.6fr',1), C('Ordered','money','',1), C('Received','money','',1), C('Returns','money','',1), C('Net','money','',1)], rows };
    } },
  { id:'suppliers', group:'Purchasing', name:'Suppliers', hint:'Spend and balances per supplier', desc:'Spend, balances owed and delivery lead time for each supplier', chart:'rank', contact:'supplier',
    gen: x => {
      const P = sum(x.days, d => purch(d, x.loc));
      const rows = SUPP.filter(s => x.contact === 'all' || s[0] === x.contact).map(s => { const v = P * s[2]; return { c:[s[1], Math.max(1, Math.round(v / 1400)), v, v * (1 - s[4]), v * s[4], s[3] + (s[3] === 1 ? ' day' : ' days')] }; });
      const tot = sum(rows, r => r.c[2]), due = sum(rows, r => r.c[4]);
      return { kpis:[['Suppliers used', rows.length, 'int', 1], ['Spend', tot, 'money', 0], ['Owed', due, 'money', 0], ['Largest supplier', rows.length ? rows.slice().sort((a, b) => b.c[2] - a.c[2])[0].c[0] : '\u2014', 'text']],
        rankName:'Spend by supplier', rank: rows.map(r => [r.c[0], r.c[2]]), table:'Supplier balances',
        cols:[C('Supplier','text','1.8fr'), C('POs','int','0.6fr',1), C('Spend','money','',1), C('Paid','money','',1), C('Owed','money','',1), C('Lead time','text','0.8fr')], rows };
    } },
  { id:'stock', group:'Stock', name:'Stock on hand', hint:'Quantity and value now', desc:'Current quantity, value at cost and retail, and reorder status', chart:'rank', cat:true, pit:true,
    gen: x => {
      const rows = PRODS.filter(p => x.cat === 'all' || p[1] === x.cat).map(p => {
        const q = x.loc === 'all' ? p[6].main + p[6].bank + p[6].cold : p[6][x.loc];
        const alert = x.loc === 'all' ? p[7] * 3 : p[7];
        const st = q <= 0 ? ['Out of stock', 'error'] : q < alert ? ['Low', 'warn'] : ['OK', 'success'];
        return { c:[p[0], p[1], q, p[5], q * p[2], q * p[3], st] };
      });
      return { kpis:[['Value at cost', sum(rows, r => r.c[4]), 'money', 1], ['Value at retail', sum(rows, r => r.c[5]), 'money', 1], ['Low stock', rows.filter(r => r.c[6][0] === 'Low').length, 'int', 0], ['Out of stock', rows.filter(r => r.c[6][0] === 'Out of stock').length, 'int', 0]],
        rankName:'Value at cost', rank: rows.map(r => [r.c[0], r.c[4]]), table:'Products',
        cols:[C('Product','text','1.7fr'), C('Category','text','0.8fr'), C('On hand','qty','0.7fr'), C('Unit','text','0.4fr'), C('At cost','money','',1), C('At retail','money','',1), C('Status','badge','0.9fr')], rows };
    } },
  { id:'mfg', group:'Stock', name:'Manufacturing', hint:'Output, cost and yield', desc:'Production orders with material cost, overhead and yield', chart:'rank',
    gen: x => {
      const f = sum(x.days, d => sales(d, x.loc)) / MONTH_ALL;
      const n = Math.max(1, Math.min(MOS.length, Math.round(MOS.length * f)));
      const rows = MOS.slice(0, n).map(m => ({ c:[m[0], m[1], m[3], m[2], m[3] * m[4], m[3] * m[5], m[4] + m[5], m[6] ? m[6] * 100 : null, [m[7], m[7] === 'Done' ? 'success' : m[7] === 'Planned' ? 'neutral' : 'info']] }));
      const done = rows.filter(r => r.c[7] !== null);
      return { kpis:[['Production orders', rows.length, 'int', 1], ['Output (kg)', sum(rows.filter(r => r.c[3] === 'kg'), r => r.c[2]), 'int', 1], ['Production cost', sum(rows, r => r.c[4] + r.c[5]), 'money', 0], ['Average yield', done.length ? sum(done, r => r.c[7]) / done.length : 0, 'pct', 1]],
        rankName:'Cost by order', rank: rows.map(r => [r.c[0] + ' \u00b7 ' + r.c[1], r.c[4] + r.c[5]]), table:'Production orders',
        cols:[C('Order','text','0.8fr'), C('Product','text','1.5fr'), C('Output','int','0.6fr',1), C('Unit','text','0.4fr'), C('Materials','money','',1), C('Overhead','money','0.8fr',1), C('Unit cost','money','0.7fr'), C('Yield','pct','0.6fr'), C('Status','badge','0.9fr')], rows };
    } },
  { id:'pl', group:'Finance', name:'Profit & loss', hint:'Income less costs', desc:'Net sales less cost of goods and overheads for the period', chart:'time', noSort:true,
    gen: x => {
      const fx = sum(EXP, e => e[1]) / 29 * EXSHARE[x.loc];
      const bk = x.bk.map(b => { const n = netOf(sum(b.days, d => sales(d, x.loc))); return n - n * 0.62 - fx * b.days.length; });
      const N = netOf(sum(x.days, d => sales(d, x.loc))), cg = N * 0.62, k = x.days.length / 29 * EXSHARE[x.loc];
      const ex = EXP.map(e => [e[0], e[1] * k]);
      const E = sum(ex, e => e[1]);
      const pc = v => N ? v / N * 100 : 0;
      const rows = [{ c:['Net sales', N, 100], bold:1 }, { c:['Cost of goods sold', -cg, -pc(cg)] }, { c:['Gross profit', N - cg, pc(N - cg)], bold:1 }]
        .concat(ex.map(e => ({ c:['   ' + e[0], -e[1], -pc(e[1])] })))
        .concat([{ c:['Total expenses', -E, -pc(E)], bold:1 }, { c:['Net profit', N - cg - E, pc(N - cg - E)], bold:2 }]);
      return { kpis:[['Net sales', N, 'money', 1], ['Gross profit', N - cg, 'money', 1], ['Gross margin', pc(N - cg), 'pct', 1], ['Net profit', N - cg - E, 'money', 1]],
        series: bk, seriesName:'Net profit', table:'Statement', cols:[C('Line','text','2.4fr'), C('Amount','money'), C('% of sales','pct','0.7fr')], rows, noTotals:true };
    } },
  { id:'expenses', group:'Finance', name:'Expenses', hint:'Spend by category', desc:'Overheads by category with GST you can claim', chart:'rank',
    gen: x => {
      const k = x.days.length / 29 * EXSHARE[x.loc];
      const rows = EXP.map((e, i) => { const v = e[1] * k; return { c:[e[0], Math.max(1, Math.round((i === 1 ? 4 : i === 0 ? 1 : 2) * x.days.length / 29)), v, e[2] ? v / 11 : 0, 0] }; });
      const T = sum(rows, r => r.c[2]);
      rows.forEach(r => { r.c[4] = T ? r.c[2] / T * 100 : 0; });
      return { kpis:[['Total expenses', T, 'money', 0], ['Entries', sum(rows, r => r.c[1]), 'int', 1], ['Largest category', 'Rent', 'text'], ['GST claimable', sum(rows, r => r.c[3]), 'money', 1]],
        rankName:'By category', rank: rows.map(r => [r.c[0], r.c[2]]), table:'Categories',
        cols:[C('Category','text','1.7fr'), C('Entries','int','0.6fr',1), C('Amount','money','',1), C('GST','money','',1), C('Share','pct','0.7fr')], rows };
    } },
  { id:'tax', group:'Finance', name:'GST / BAS', hint:'GST collected and paid', desc:'Figures for your Business Activity Statement, lodged quarterly', chart:'time', noSort:true, two:true,
    gen: x => {
      const ex = d => sum(EXP.filter(e => e[2]), e => e[1]) / 29 * EXSHARE[x.loc];
      const a = x.bk.map(b => sum(b.days, d => netOf(sales(d, x.loc))) / 11);
      const bb = x.bk.map(b => sum(b.days, d => (purch(d, x.loc) + ex(d)) / 11));
      const G1 = sum(x.days, d => netOf(sales(d, x.loc))), G11 = sum(x.days, d => purch(d, x.loc) + ex(d));
      const rows = [{ c:['G1', 'Total sales (incl. GST)', G1] }, { c:['1A', 'GST on sales', G1 / 11], bold:1 }, { c:['G11', 'Non-capital purchases', G11] }, { c:['1B', 'GST on purchases', -G11 / 11], bold:1 }, { c:['', 'Net GST payable', (G1 - G11) / 11], bold:2 }];
      return { kpis:[['GST collected', G1 / 11, 'money', 1], ['GST credits', G11 / 11, 'money', 1], ['Net payable', (G1 - G11) / 11, 'money', 0], ['Next BAS due', '28/10/2026', 'text']],
        series: a, seriesB: bb, seriesName:'GST collected', seriesBName:'GST paid', table:'BAS summary', cols:[C('Label','text','0.5fr'), C('Description','text','2fr'), C('Amount','money')], rows, noTotals:true };
    } }
];
const GROUPS = ['Sales', 'Purchasing', 'Stock', 'Finance'];

class Component extends ModBase {
  _id = 0;
  initialState = { rep:'sales', range:'month', loc:'all', contact:'all', cat:'all', cmp:true, rq:'', tq:'', sort:null, dir:-1, page:0, toasts:[] };
  later(fn, ms) { (this._t = this._t || []).push(setTimeout(fn, ms)); }
  toast(msg, tone) {
    const id = ++this._id;
    this.setState(s => ({ toasts: s.toasts.concat([{ id, msg, tone: tone || 'info' }]).slice(-3) }));
    this.later(() => this.setState(s => ({ toasts: s.toasts.filter(t => t.id !== id) })), 2600);
  }
  money(n) { const v = Math.abs(n) < 0.005 ? 0 : n; return (v < 0 ? '\u2212' : '') + Math.abs(v).toLocaleString('en-AU', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
  short(n) { return n >= 10000 ? (n / 1000).toFixed(0) + 'k' : n >= 1000 ? (n / 1000).toFixed(1) + 'k' : Math.round(n).toString(); }
  fmt(v, t) {
    if (v === null || v === undefined) return '\u2014';
    if (t === 'money') return this.money(v);
    if (t === 'int') return Math.round(v).toLocaleString('en-AU');
    if (t === 'pct') return (v < 0 ? '\u2212' : '') + Math.abs(v).toFixed(1) + '%';
    if (t === 'qty') return (Math.round(v * 10) / 10).toLocaleString('en-AU');
    if (t === 'badge') return v[0];
    return String(v);
  }
  seg(on) { return 'height:30px;padding:0 12px;border-radius:7px;display:grid;place-items:center;font-size:12.5px;cursor:pointer;white-space:nowrap;' + (on ? 'background:#ffffff;color:#0f1c2b;font-weight:700;box-shadow:0 1px 3px rgba(15,28,43,.14);' : 'color:#5d6b7d;font-weight:500;'); }
  ctx(from, to) { const s = this.state; return { from, to, days: range(from, to), bk: buckets(from, to), loc: s.loc, contact: s.contact, cat: s.cat }; }

  renderVals() {
    const s = this.state;
    const def = REPORTS.find(r => r.id === s.rep);
    const R = RANGES.find(r => r[0] === s.range);
    const len = R[3] - R[2] + 1;
    const cur = def.gen(this.ctx(R[2], R[3]));
    const canCmp = !def.pit;
    const cmp = canCmp && s.cmp;
    const prev = cmp ? def.gen(this.ctx(R[2] - len, R[2] - 1)) : null;
    const rq = s.rq.trim().toLowerCase();
    const tq = s.tq.trim().toLowerCase();
    const cols = cur.cols;
    let rows = cur.rows.filter(r => !tq || r.c.some((v, i) => cols[i].t === 'text' && String(v).toLowerCase().indexOf(tq) > -1));
    if (s.sort !== null && !def.noSort && cols[s.sort]) {
      const k = s.sort, t = cols[k].t;
      rows = rows.slice().sort((a, b) => { const x = t === 'badge' ? a.c[k][0] : a.c[k], y = t === 'badge' ? b.c[k][0] : b.c[k]; return (typeof x === 'string' ? x.localeCompare(y) : (x || 0) - (y || 0)) * s.dir; });
    }
    const PS = 12;
    const pages = Math.max(1, Math.ceil(rows.length / PS));
    const page = Math.min(s.page, pages - 1);
    const shown = rows.slice(page * PS, page * PS + PS);
    const grid = 'display:grid;grid-template-columns:' + cols.map(c => c.w).join(' ') + ';gap:12px;align-items:center;padding:0 16px;';
    const num = t => t === 'money' || t === 'int' || t === 'pct' || t === 'qty';
    const cellStyle = (t, i, bold) => 'min-width:0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;' + (num(t) ? MONO + 'font-size:12.5px;text-align:right;' : 'font-size:13px;') + (i === 0 || bold ? 'font-weight:' + (bold ? 700 : 500) + ';' : 'color:#4a5866;');
    const set = p => this.setState({ ...p, page: 0 });
    // chart
    const A = cur.series || [], B = cur.seriesB || (prev && prev.series ? prev.series : null);
    const max = Math.max(1, ...A.map(v => Math.max(0, v)), ...(B || []).map(v => Math.max(0, v)));
    const every = Math.ceil(A.length / 10);
    const bw = A.length > 20 ? 'max(3px,38%)' : '34%';
    const curBk = this.ctx(R[2], R[3]).bk;
    const rank = (cur.rank || []).slice().sort((a, b) => b[1] - a[1]).slice(0, 8);
    const rmax = Math.max(1, ...rank.map(r => r[1]));
    const [sw, kn] = [
      'width:32px;height:19px;border-radius:99px;position:relative;flex:none;background:' + (s.cmp ? '#1668c9' : '#c2cedb'),
      'position:absolute;top:2.5px;width:14px;height:14px;border-radius:50%;background:#ffffff;left:' + (s.cmp ? '15.5px' : '2.5px')
    ];
    const filters = s.loc !== 'all' || s.contact !== 'all' || s.cat !== 'all' || s.range !== 'month';
    const contactList = def.contact === 'customer' ? CUST.map(c => ({ id:c[0], name:c[1] })) : def.contact === 'supplier' ? SUPP.map(c => ({ id:c[0], name:c[1] })) : [];
    const ctxLabel = (def.pit ? 'As at 29/09/2026' : R[1]) + ' \u00b7 ' + LOCS.find(l => l.id === s.loc).name;

    return {
      nav: NAV.map(n => {
        const on = n[0] === 'Reports';
        return {
          name: n[0], key: n[1],
          style: 'display:flex;align-items:center;gap:10px;padding:8px 10px;border-radius:8px;cursor:pointer;font-size:13px;font-weight:500;margin-bottom:1px;' + (on ? 'background:rgba(255,255,255,.13);color:#ffffff;box-shadow:inset 2px 0 0 #4da3e8;' : 'color:#c4dced;'),
          iconStyle: 'width:20px;height:20px;border-radius:5px;display:grid;place-items:center;' + MONO + 'font-size:9px;font-weight:600;flex:none;' + (on ? 'background:#4da3e8;color:#06283a;' : 'background:rgba(255,255,255,.1);color:#9dbdd2;'),
          caretStyle: 'color:#7fa3bb;font-size:11px;flex:none;' + (n[2] ? '' : 'visibility:hidden;')
        };
      }),
      rq: s.rq, onRq: e => this.setState({ rq: e.target.value }),
      groups: GROUPS.map(g => ({
        name: g,
        items: REPORTS.filter(r => r.group === g && (!rq || (r.name + ' ' + r.hint).toLowerCase().indexOf(rq) > -1)).map(r => ({
          name: r.name, hint: r.hint,
          onClick: () => this.setState({ rep: r.id, contact: 'all', cat: 'all', sort: null, dir: -1, page: 0, tq: '' }),
          style: 'display:flex;align-items:center;padding:7px 9px;border-radius:8px;cursor:pointer;margin-bottom:1px;' + (r.id === s.rep ? 'background:#eaf1fb;box-shadow:inset 2px 0 0 #1668c9;color:#14549e;font-weight:600;' : 'color:#0f1c2b;')
        }))
      })).filter(g => g.items.length),
      groupName: def.group, name: def.name, desc: def.desc,
      print: () => this.toast(def.name + ' report sent to printer', 'neutral'),
      export: () => this.toast(def.name + ' \u00b7 ' + ctxLabel + ' exported to Excel', 'success'),
      hasRange: !def.pit, isPit: !!def.pit,
      ranges: RANGES.map(r => ({ name: r[1], onClick: () => set({ range: r[0] }), style: this.seg(s.range === r[0]) })),
      loc: s.loc, locOpts: LOCS, onLoc: e => set({ loc: e.target.value }),
      hasContact: !!def.contact, contact: s.contact, contactOpts: contactList,
      contactAll: def.contact === 'supplier' ? 'All suppliers' : 'All customers',
      onContact: e => set({ contact: e.target.value }),
      hasCat: !!def.cat, cat: s.cat, catOpts: [{ id:'all', name:'All categories' }].concat(CATS.map(c => ({ id:c, name:c }))), onCat: e => set({ cat: e.target.value }),
      canCompare: canCmp && def.id !== 'tax', toggleCompare: () => this.setState({ cmp: !s.cmp }), cmpSwitch: sw, cmpKnob: kn,
      hasFilters: filters, reset: () => set({ range:'month', loc:'all', contact:'all', cat:'all', tq:'' }),

      kpis: cur.kpis.map((k, i) => {
        let d = '', dStyle = 'font-size:11.5px;margin-top:3px;color:#8b98a8;min-height:15px';
        if (prev && k[2] !== 'text' && def.id !== 'tax') {
          const p = prev.kpis[i][1];
          if (k[2] === 'pct') { const diff = k[1] - p; d = (diff >= 0 ? '+' : '\u2212') + Math.abs(diff).toFixed(1) + ' pts vs previous'; dStyle += ';color:' + ((diff >= 0) === !!k[3] ? '#0f6b43' : '#b02020'); }
          else if (Math.abs(p) > 0.001) { const pc = (k[1] - p) / Math.abs(p) * 100; d = (pc >= 0 ? '\u25b2 ' : '\u25bc ') + Math.abs(pc).toFixed(1) + '% vs previous'; dStyle += ';color:' + (Math.abs(pc) < 0.05 ? '#8b98a8' : (pc >= 0) === !!k[3] ? '#0f6b43' : '#b02020'); }
        } else if (i === 0) d = ctxLabel;
        return { k: k[0], v: this.fmt(k[1], k[2]), d, dStyle };
      }),
      isTime: def.chart === 'time', isRank: def.chart === 'rank',
      chartTitle: def.chart === 'time' ? (cur.seriesName + (len > 31 ? ' by week' : ' by day')) : cur.rankName + (rank.length < (cur.rank || []).length ? ' \u00b7 top ' + rank.length : ''),
      legend: def.chart === 'time' ? [[cur.seriesName, '#1668c9']].concat(B ? [[cur.seriesBName || 'Previous period', cur.seriesB ? '#d98c1a' : '#c9d6e4']] : []).map(l => ({ name: l[0], sw: 'width:10px;height:10px;border-radius:3px;background:' + l[1] })) : [],
      yMax: this.short(max), yMid: this.short(max / 2),
      bars: A.map((v, i) => {
        const b = B ? B[i] : null;
        const lb = curBk[i] ? curBk[i].label : '';
        return {
          label: i % every === 0 ? lb : '',
          tip: lb + ': ' + this.money(v) + (b !== null && b !== undefined ? ' \u00b7 ' + (cur.seriesBName || 'previous') + ' ' + this.money(b) : ''),
          aStyle: 'width:' + bw + ';border-radius:3px 3px 0 0;height:' + Math.max(0, v / max * 100) + '%;background:' + (v < 0 ? '#d93a3a' : '#1668c9'),
          bStyle: B ? 'width:' + bw + ';border-radius:3px 3px 0 0;height:' + Math.max(0, (b || 0) / max * 100) + '%;background:' + (cur.seriesB ? '#d98c1a' : '#c9d6e4') : 'display:none'
        };
      }),
      rank: rank.map((r, i) => ({ name: r[0], v: this.money(r[1]), bar: 'height:100%;border-radius:4px;width:' + Math.max(1, r[1] / rmax * 100) + '%;background:' + (i === 0 ? '#1668c9' : '#7fb0e6') })),

      tableTitle: cur.table + ' \u00b7 ' + rows.length + (rows.length === 1 ? ' row' : ' rows'),
      tq: s.tq, onTq: e => this.setState({ tq: e.target.value, page: 0 }),
      headStyle: grid + 'background:#f6f9fc;border-bottom:1px solid #dfe6ee;padding-top:10px;padding-bottom:10px;',
      cols: cols.map((c, i) => ({
        h: c.h, arrow: s.sort === i ? (s.dir > 0 ? ' \u2191' : ' \u2193') : '',
        onClick: () => { if (def.noSort) return; this.setState({ sort: i, dir: s.sort === i ? -s.dir : (c.t === 'text' ? 1 : -1), page: 0 }); },
        style: "font-family:'IBM Plex Mono',monospace;font-size:9.5px;letter-spacing:0.07em;text-transform:uppercase;color:#5d6b7d;white-space:nowrap;user-select:none;" + (num(c.t) ? 'text-align:right;' : '') + (def.noSort ? '' : 'cursor:pointer;')
      })),
      rows: shown.map(r => ({
        style: grid + 'padding-top:9px;padding-bottom:9px;border-bottom:1px solid #f0f4f8;' + (r.bold === 2 ? 'background:#f6f9fc;border-top:1.5px solid #0f1c2b;' : ''),
        cells: r.c.map((v, i) => {
          const t = cols[i].t;
          const neg = num(t) && typeof v === 'number' && v < -0.004;
          return {
            v: this.fmt(v, t),
            style: cellStyle(t, i, r.bold) + (neg ? 'color:#b02020;' : '') + (r.bold === 2 ? 'font-size:14px;' : ''),
            inner: t === 'badge' ? 'background:' + TONE[v[1]][0] + ';color:' + TONE[v[1]][1] + ';border-radius:5px;padding:3px 8px;font-size:11.5px;font-weight:600;white-space:nowrap' : (cols[i].t === 'text' && typeof v === 'string' && v.indexOf('   ') === 0 ? 'padding-left:18px' : '')
          };
        })
      })),
      noRows: !rows.length,
      hasTotals: !cur.noTotals && cols.some(c => c.sum) && rows.length > 0,
      footStyle: grid + 'padding-top:11px;padding-bottom:11px;background:#f6f9fc;border-top:1px solid #dfe6ee;',
      totals: cols.map((c, i) => ({
        v: i === 0 ? 'Total' : c.sum ? this.fmt(sum(rows, r => r.c[i] || 0), c.t) : '',
        style: 'min-width:0;white-space:nowrap;font-weight:700;' + (num(c.t) ? MONO + 'font-size:12.5px;text-align:right;' : 'font-size:13px;') + (c.sum && sum(rows, r => r.c[i] || 0) < -0.004 ? 'color:#b02020;' : '')
      })),
      pageInfo: rows.length ? 'Showing ' + (page * PS + 1) + '\u2013' + Math.min(rows.length, page * PS + PS) + ' of ' + rows.length + (def.noSort ? '' : ' \u00b7 click a column to sort') : '',
      prevPage: () => { if (page > 0) this.setState({ page: page - 1 }); },
      nextPage: () => { if (page < pages - 1) this.setState({ page: page + 1 }); },
      prevStyle: 'height:28px;padding:0 10px;border-radius:7px;border:1px solid #dfe6ee;display:flex;align-items:center;font-size:12px;font-weight:600;' + (page > 0 ? 'color:#4a5866;cursor:pointer;' : 'color:#c2cedb;'),
      nextStyle: 'height:28px;padding:0 10px;border-radius:7px;border:1px solid #dfe6ee;display:flex;align-items:center;font-size:12px;font-weight:600;' + (page < pages - 1 ? 'color:#4a5866;cursor:pointer;' : 'color:#c2cedb;'),

      toasts: s.toasts.map(t => ({ msg:t.msg, dot:'width:8px;height:8px;border-radius:50%;flex:none;background:' + TONE[t.tone][2] }))
    };
  }
}
return Component;
})();
});
