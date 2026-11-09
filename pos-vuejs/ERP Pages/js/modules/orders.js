// OrdersLogic
DC.add(function () {
const OrdersLogic = (() => {
const NAV = [
  ['Home','Hm',0],['User Management','Us',1],['Contacts','Ct',1],['Products','Pr',1],
  ['Manufacturing','Mf',0],['Purchases','Pu',1],['Sell','Sl',1],['Stock Transfers','St',0],
  ['Stock Adjustment','Sa',0],['Expenses','Ex',1],['Payment Accounts','Pa',1],['Accounting','Ac',1],
  ['Reports','Rp',1],['Bookings','Bk',0],['Kitchen','Kt',0],['Orders','Or',0],
  ['Settings','Se',1],['HRM','Hr',1]
];
const TONE = {
  info:['#eaf1fb','#14549e','#1668c9'], warn:['#fdf1dd','#8a5300','#d98c1a'], error:['#fdeaea','#b02020','#d93a3a'],
  success:['#e7f6ee','#0f6b43','#2f9e63'], neutral:['#eef2f6','#5d6b7d','#8fa3b6'], purple:['#f3ecfd','#5b34a8','#7a4fc9']
};
const MONO = "font-family:'IBM Plex Mono',monospace;";
const CH = { dine:['Dine-in','info'], take:['Takeaway','warn'], deliv:['Delivery','purple'], online:['Online','success'] };
const ST = { new:['New','info'], prep:['Preparing','warn'], ready:['Ready','success'], out:['Out for delivery','purple'], done:['Completed','neutral'], cancelled:['Cancelled','error'] };
const STEPS = [['new','Placed'],['prep','Kitchen'],['ready','Ready'],['out','On the way'],['done','Done']];
const MENU = [
  ['Mixed grill platter','Grill',34.9],['Lamb chops plate','Grill',29.9],['Chicken shish wrap','Grill',14.9],['Beef burger','Grill',16.9],['Kofta plate','Grill',22.9],
  ['Hummus','Sides',8.9],['Tabbouleh','Sides',9.9],['Fattoush','Sides',10.9],['Garlic sauce tub','Sides',3.5],
  ['Chips','Fryer',6.9],['Falafel \u00d76','Fryer',9.9],['Chicken wings','Fryer',15.9],
  ['Beef Mince 1kg','Butcher',12.9],['Lamb Chops 1kg','Butcher',31.9],['Chicken Breast 1kg','Butcher',14.9],
  ['Mint lemonade','Drinks',5.9],['Ayran','Drinks',4.5]
];
const CATS = ['Grill','Sides','Fryer','Butcher','Drinks'];
const CUSTS = [['walk','Walk-in customer'],['yusuf','Yusuf Rahman'],['hamza','Hamza Malik'],['layla','Layla Haddad'],['omar','Omar Siddiqui'],['fatima','Fatima Hassan'],['amin','Al Amin Catering']];
const TABLES = ['Table 1','Table 2','Table 3','Table 4','Table 5','Table 6','Table 7','Table 8'];
const DRIVERS = ['Rafi','Tariq','Uber Eats','DoorDash'];
const NOW = 14 * 60 + 38;
const hm = m => String(Math.floor(m / 60)).padStart(2, '0') + ':' + String(m % 60).padStart(2, '0');
const L = (n, q, note) => ({ n, q, note: note || '', p: MENU.find(m => m[0] === n)[2] });
const O = (no, ch, cust, where, at, status, pay, items, x) => ({ no, ch, cust, where, at, status, pay, paid: 0, items, disc: 0, fee: ch === 'deliv' ? 6.9 : 0, note: '', method: '', driver: '', times: { new: at }, log: [['Order placed' + (ch === 'online' ? ' online' : ''), at, 'neutral']], ...(x || {}) });
const KT = { '#4517':[1], '#4516':[1], '#4515':[1], '#4514':[1], '#4513':[1,11], '#4512':[1,14], '#4509':[1,17], '#4511':[1,15], '#4510':[1,15] };
const SEED = [
  O('#4520','deliv','Bilal Ahmed','12 Haldon St, Lakemba',NOW - 1,'new','paid',[L('Beef burger',1,'Well done'),L('Chips',1),L('Chicken wings',1)],{ method:'Card \u00b7 online' }),
  O('#4519','online','Hamza Malik','Pickup ASAP',NOW - 1,'new','paid',[L('Chicken shish wrap',3,'No onion'),L('Garlic sauce tub',1)],{ method:'Card \u00b7 online' }),
  O('#4518','dine','Walk-in customer','Table 6 \u00b7 5 guests',NOW - 1,'new','unpaid',[L('Mixed grill platter',2),L('Fattoush',1),L('Chips',2)]),
  O('#4517','online','Layla Haddad','Pickup 14:50',NOW - 5,'prep','paid',[L('Chicken shish wrap',1),L('Hummus',1)],{ method:'Card \u00b7 online' }),
  O('#4516','dine','Ibrahim Ali','Table 8 \u00b7 8 guests',NOW - 9,'prep','part',[L('Mixed grill platter',3),L('Fattoush',2),L('Chips',4),L('Chicken wings',1),L('Mint lemonade',8)],{ note:'Pre-ordered with booking BK-2035 \u00b7 80.00 deposit', paidAmt: 80 }),
  O('#4515','take','Yusuf Rahman','Counter',NOW - 18,'prep','paid',[L('Kofta plate',2),L('Falafel \u00d76',1)],{ method:'Cash', note:'Customer waiting at counter' }),
  O('#4514','dine','Walk-in customer','Table 4 \u00b7 4 guests',NOW - 13,'prep','unpaid',[L('Lamb chops plate',2,'Medium'),L('Hummus',1),L('Tabbouleh',1),L('Chips',2)]),
  O('#4513','deliv','Sara Nguyen','DoorDash \u00b7 Bankstown',NOW - 12,'ready','paid',[L('Beef burger',2),L('Chips',2)],{ method:'DoorDash' }),
  O('#4512','dine','Walk-in customer','Table 3 \u00b7 2 guests',NOW - 16,'ready','unpaid',[L('Kofta plate',2),L('Hummus',1),L('Ayran',2)]),
  O('#4509','deliv','Al Amin Catering','45 Canterbury Rd, Punchbowl',NOW - 42,'out','paid',[L('Beef Mince 1kg',5),L('Lamb Chops 1kg',3),L('Chicken Breast 1kg',6)],{ method:'Account', driver:'Rafi' }),
  O('#4511','dine','Walk-in customer','Table 1 \u00b7 2 guests',NOW - 25,'done','paid',[L('Mixed grill platter',1),L('Tabbouleh',1)],{ method:'Card' }),
  O('#4510','take','Omar Siddiqui','Counter',NOW - 28,'done','paid',[L('Lamb chops plate',1)],{ method:'Cash' }),
  O('#4508','online','Aisha Karim','Pickup 13:45',NOW - 70,'cancelled','refunded',[L('Kofta plate',1),L('Chips',1)],{ method:'Card \u00b7 online', note:'Customer cancelled, refunded to card' })
].map(o => {
  const k = KT[o.no], times = { new: o.at }, log = o.log.slice();
  if (k) {
    times.prep = o.at + k[0]; log.unshift(['Sent to kitchen', times.prep, 'warn']);
    if (k[1] !== undefined) { times.ready = o.at + k[1]; log.unshift(['Marked ready', times.ready, 'success']); }
    if (o.status === 'out') { times.out = times.ready + 2; log.unshift(['Out with ' + (o.driver || 'driver'), times.out, 'purple']); }
    if (o.status === 'done') { times.done = times.ready + 4; log.unshift(['Completed', times.done, 'neutral']); }
  }
  return { ...o, times, log, paid: o.pay === 'paid' || o.pay === 'refunded' ? null : o.paidAmt || 0 };
});

class Component extends ModBase {
  _id = 0;
  initialState = { orders: SEED, tab: 'Open', ch: 'all', q: '', unpaid: false, sel: '#4514', next: 4521, form: null, pay: null, toasts: [], tick: 0 };
  later(fn, ms) { (this._t = this._t || []).push(setTimeout(fn, ms)); }
  toast(msg, tone) {
    const id = ++this._id;
    this.setState(s => ({ toasts: s.toasts.concat([{ id, msg, tone: tone || 'info' }]).slice(-3) }));
    this.later(() => this.setState(s => ({ toasts: s.toasts.filter(x => x.id !== id) })), 2600);
  }
  money(n) { return (n || 0).toLocaleString('en-AU', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
  badge(x) { return 'background:' + x[0] + ';color:' + x[1] + ';border-radius:5px;padding:3px 8px;font-size:11.5px;font-weight:600;white-space:nowrap'; }
  seg(on) { return 'height:30px;padding:0 12px;border-radius:7px;display:grid;place-items:center;font-size:12.5px;cursor:pointer;white-space:nowrap;' + (on ? 'background:#ffffff;color:#0f1c2b;font-weight:700;box-shadow:0 1px 3px rgba(15,28,43,.14);' : 'color:#5d6b7d;font-weight:500;'); }
  pageTab(on) { return 'padding:9px 8px;font-size:12.5px;cursor:pointer;white-space:nowrap;border-bottom:2px solid ' + (on ? '#1668c9;color:#1668c9;font-weight:600;' : 'transparent;color:#5d6b7d;font-weight:500;'); }
  tot(o) {
    const sub = o.items.reduce((q, l) => q + l.p * l.q, 0);
    const disc = sub * o.disc / 100;
    const total = Math.round((sub - disc + o.fee) * 100) / 100;
    const paid = o.paid === null ? total : o.paid;
    return { sub, disc, total, gst: total / 11, paid, due: Math.max(0, Math.round((total - paid) * 100) / 100) };
  }
  now() { return NOW + this.state.tick; }
  upd(no, fn) { this.setState(s => ({ orders: s.orders.map(o => o.no === no ? fn(o) : o) })); }
  move(o, to, msg) {
    const t = this.now();
    this.upd(o.no, x => ({ ...x, status: to, times: { ...x.times, [to]: t }, log: [[msg, t, ST[to][1]]].concat(x.log) }));
    this.setState(s => ({ tick: s.tick + 1 }));
    this.toast(o.no + ' \u00b7 ' + msg, to === 'cancelled' ? 'error' : 'success');
  }
  blank(ch) { return { ch: ch || 'take', cust: 'walk', table: 'Table 2', addr: '', pickup: 'ASAP', cat: 'Grill', lines: [], disc: 0, note: '', send: true }; }
  fTot(f) { return this.tot({ items: f.lines, disc: f.disc, fee: f.ch === 'deliv' ? 6.9 : 0, paid: 0 }); }
  saveOrder(payNow) {
    const s = this.state, f = s.form;
    if (!f.lines.length) { this.toast('Add at least one item', 'error'); return; }
    if (f.ch === 'deliv' && !f.addr.trim()) { this.toast('Enter a delivery address', 'error'); return; }
    if (f.ch === 'dine' && s.orders.some(o => o.ch === 'dine' && o.where.indexOf(f.table + ' ') === 0 && ['done','cancelled'].indexOf(o.status) < 0)) {
      this.toast(f.table + ' already has an open order', 'warn'); return;
    }
    const no = '#' + s.next;
    const custName = CUSTS.find(c => c[0] === f.cust)[1];
    const where = f.ch === 'dine' ? f.table : f.ch === 'deliv' ? f.addr.trim() : f.pickup === 'ASAP' ? 'Pickup ASAP' : 'Pickup ' + hm(this.now() + parseInt(f.pickup, 10));
    const t = this.now();
    let o = O(no, f.ch === 'online' ? 'online' : f.ch, custName, where, t, f.send ? 'prep' : 'new', 'unpaid', f.lines.map(l => ({ ...l })), { disc: f.disc, note: f.note, fee: f.ch === 'deliv' ? 6.9 : 0 });
    o.paid = 0;
    if (f.send) { o.times.prep = t; o.log = [['Sent to kitchen', t, 'warn']].concat(o.log); }
    this.setState({ orders: [o].concat(s.orders), next: s.next + 1, form: null, sel: no, tab: 'Open', tick: s.tick + 1 });
    this.toast(no + ' created' + (f.send ? ' \u00b7 on the kitchen display' : ''), 'success');
    if (payNow) this.later(() => this.setState({ pay: { no, method: 'Card', tendered: '' } }), 50);
  }
  confirmPay() {
    const s = this.state, p = s.pay;
    const o = s.orders.find(x => x.no === p.no);
    const tt = this.tot(o);
    const tend = parseFloat(p.tendered) || 0;
    if (p.method === 'Cash' && tend < tt.due - 0.001) { this.toast('Cash received is less than ' + this.money(tt.due), 'error'); return; }
    const t = this.now();
    this.upd(o.no, x => ({ ...x, paid: null, pay: 'paid', method: p.method, log: [['Paid ' + this.money(tt.due) + ' by ' + p.method.toLowerCase() + (p.method === 'Cash' && tend > tt.due ? ' \u00b7 change ' + this.money(tend - tt.due) : ''), t, 'success']].concat(x.log) }));
    this.setState({ pay: null });
    this.toast(o.no + ' paid \u00b7 ' + this.money(tt.due), 'success');
  }

  renderVals() {
    const s = this.state;
    const now = this.now();
    const open = o => ['new','prep','ready','out'].indexOf(o.status) > -1;
    const TABS = [['Open', o => open(o)], ['New', o => o.status === 'new'], ['Preparing', o => o.status === 'prep'], ['Ready', o => o.status === 'ready'], ['On the way', o => o.status === 'out'], ['Completed', o => o.status === 'done'], ['All', () => true]];
    const tf = TABS.find(t => t[0] === s.tab)[1];
    const q = s.q.trim().toLowerCase();
    const rows = s.orders.filter(o => tf(o) && (s.ch === 'all' || o.ch === s.ch) && (!s.unpaid || this.tot(o).due > 0 && o.status !== 'cancelled')
      && (!q || (o.no + ' ' + o.cust + ' ' + o.where + ' ' + o.items.map(l => l.n).join(' ')).toLowerCase().indexOf(q) > -1));
    const unpaidOpen = s.orders.filter(o => o.status !== 'cancelled' && this.tot(o).due > 0);
    const done = s.orders.filter(o => o.times.ready && o.times.prep);
    const avgPrep = done.length ? Math.round(done.reduce((a, o) => a + (o.times.ready - o.times.prep), 0) / done.length) : 0;
    const value = s.orders.filter(o => o.status !== 'cancelled').reduce((a, o) => a + this.tot(o).total, 0);
    const sel = s.orders.find(o => o.no === s.sel);
    const f = s.form;
    const ft = f ? this.fTot(f) : null;
    const p = s.pay;
    const po = p ? s.orders.find(o => o.no === p.no) : null;
    const pt = po ? this.tot(po) : null;
    const A = (label, fn, kind) => ({ label, onClick: fn, style: 'height:40px;padding:0 14px;border-radius:9px;display:flex;align-items:center;font-size:13px;font-weight:600;cursor:pointer;' + (kind === 'p' ? 'flex:1;justify-content:center;background:#1668c9;color:#ffffff;' : kind === 'g' ? 'flex:1;justify-content:center;background:#2f9e63;color:#ffffff;' : kind === 'd' ? 'border:1px solid #f3cfcf;background:#fdf4f4;color:#b02020;' : 'border:1px solid #dfe6ee;background:#ffffff;color:#4a5866;') });
    let actions = [], d = null;
    if (sel) {
      const tt = this.tot(sel);
      const payFirst = fn => () => { if (this.tot(sel).due > 0) { this.setState({ pay: { no: sel.no, method: 'Card', tendered: '', then: 'done' } }); return; } fn(); };
      const st = sel.status;
      if (st === 'new') actions = [A('Cancel', () => this.move(sel, 'cancelled', 'Cancelled by Aminul'), 'd'), A('Send to kitchen', () => this.move(sel, 'prep', 'Sent to kitchen'), 'p')];
      else if (st === 'prep') actions = [A('Open kitchen display', () => this._host.setState({ view: 'kitchen' })), A('Mark ready', () => this.move(sel, 'ready', 'Marked ready'), 'g')];
      else if (st === 'ready' && sel.ch === 'deliv') actions = DRIVERS.slice(0, 2).map(dr => A('Send with ' + dr, () => { this.upd(sel.no, x => ({ ...x, driver: dr })); this.move(sel, 'out', 'Out with ' + dr); })).concat(sel.where.indexOf('DoorDash') > -1 || sel.where.indexOf('Uber') > -1 ? [A('Driver collected', () => this.move(sel, 'out', 'Collected by courier'), 'p')] : []);
      else if (st === 'ready') actions = [A(sel.ch === 'dine' ? 'Print bill' : 'Call customer', () => this.toast(sel.ch === 'dine' ? 'Bill printed for ' + sel.where.split(' \u00b7')[0] : 'Pickup SMS sent to ' + sel.cust, 'neutral')), A(tt.due > 0 ? 'Take payment & complete' : sel.ch === 'dine' ? 'Served & close' : 'Picked up', payFirst(() => this.move(sel, 'done', 'Completed')), 'g')];
      else if (st === 'out') actions = [A('Track ' + (sel.driver || 'driver'), () => this.toast((sel.driver || 'Driver') + ' is 6 min away', 'neutral')), A('Mark delivered', payFirst(() => this.move(sel, 'done', 'Delivered')), 'g')];
      else if (st === 'done') actions = [A('Refund', () => { this.upd(sel.no, x => ({ ...x, pay: 'refunded' })); this.move(sel, 'cancelled', 'Refunded ' + this.money(tt.total)); }, 'd'), A('Print receipt', () => this.toast('Receipt printed for ' + sel.no, 'neutral'), 'p')];
      else actions = [A('Reorder', () => this.setState({ form: { ...this.blank(sel.ch === 'online' ? 'take' : sel.ch), lines: sel.items.map(l => ({ ...l })) } }), 'p')];
      const reach = STEPS.map(x => x[0]);
      const idx = st === 'cancelled' ? -1 : reach.indexOf(st);
      const skipOut = sel.ch !== 'deliv';
      d = {
        no: sel.no, ch: CH[sel.ch][0], chStyle: this.badge(TONE[CH[sel.ch][1]]), status: ST[st][0], stBadge: this.badge(TONE[ST[st][1]]),
        cust: sel.cust, where: sel.where + ' \u00b7 placed ' + hm(sel.at),
        steps: STEPS.map((x, i) => {
          const na = skipOut && x[0] === 'out';
          const hit = !na && idx >= i;
          return { name: na ? '\u2014' : x[1], at: sel.times[x[0]] !== undefined ? hm(sel.times[x[0]]) : '',
            bar: 'height:5px;border-radius:99px;background:' + (st === 'cancelled' ? '#f3cfcf' : na ? '#f2f5f8' : hit ? (i === idx ? '#1668c9' : '#2f9e63') : '#e4eaf1'),
            lbl: 'font-size:11px;font-weight:600;' + (hit ? 'color:#0f1c2b;' : 'color:#9aa7b6;') };
        }),
        items: sel.items.map(l => ({ q: l.q, name: l.n, note: l.note, noteStyle: l.note ? 'font-size:11.5px;color:#b02020;font-weight:600' : 'display:none', total: this.money(l.p * l.q) })),
        sums: [['Subtotal', this.money(tt.sub)]].concat(sel.disc ? [['Discount ' + sel.disc + '%', '\u2212' + this.money(tt.disc)]] : []).concat(sel.fee ? [['Delivery fee', this.money(sel.fee)]] : []).concat([['GST included', this.money(tt.gst)], ['Total', this.money(tt.total)]])
          .map((r, i, arr) => ({ k: r[0], v: r[1], style: 'display:flex;' + (i === arr.length - 1 ? 'font-size:15px;font-weight:700;border-top:1px solid #eaeff5;padding-top:8px;margin-top:2px;' : 'color:#5d6b7d;') })),
        payTitle: sel.pay === 'refunded' ? 'Refunded \u00b7 ' + this.money(tt.total) : tt.due <= 0 ? 'Paid \u00b7 ' + this.money(tt.total) : tt.paid > 0 ? 'Part paid \u00b7 ' + this.money(tt.due) + ' due' : 'Unpaid \u00b7 ' + this.money(tt.due) + ' due',
        paySub: sel.pay === 'refunded' ? 'Returned to ' + (sel.method || 'card') : tt.due <= 0 ? (sel.method || 'Paid') : tt.paid > 0 ? this.money(tt.paid) + ' deposit from booking applied' : sel.ch === 'dine' ? 'Pay at the table or the counter when done' : 'Collect on pickup',
        payBox: 'display:flex;align-items:center;gap:10px;border-radius:10px;padding:11px 13px;' + (sel.pay === 'refunded' ? 'background:#eef2f6;color:#5d6b7d;' : tt.due <= 0 ? 'background:#e7f6ee;color:#0f6b43;' : 'background:#fdf1dd;color:#8a5300;'),
        canPay: tt.due > 0 && st !== 'cancelled',
        hasNote: !!sel.note, note: sel.note,
        log: sel.log.map(a => ({ what: a[0], when: hm(a[1]), dot: 'width:8px;height:8px;border-radius:50%;margin-top:5px;flex:none;background:' + TONE[a[2]][2] })),
        actions
      };
    }
    const [usw, ukn] = ['width:32px;height:19px;border-radius:99px;position:relative;flex:none;background:' + (s.unpaid ? '#1668c9' : '#c2cedb'), 'position:absolute;top:2.5px;width:14px;height:14px;border-radius:50%;background:#ffffff;left:' + (s.unpaid ? '15.5px' : '2.5px')];

    return {
      nav: NAV.map(n => {
        const on = n[0] === 'Orders';
        return {
          name: n[0], key: n[1],
          style: 'display:flex;align-items:center;gap:10px;padding:8px 10px;border-radius:8px;cursor:pointer;font-size:13px;font-weight:500;margin-bottom:1px;' + (on ? 'background:rgba(255,255,255,.13);color:#ffffff;box-shadow:inset 2px 0 0 #4da3e8;' : 'color:#c4dced;'),
          iconStyle: 'width:20px;height:20px;border-radius:5px;display:grid;place-items:center;' + MONO + 'font-size:9px;font-weight:600;flex:none;' + (on ? 'background:#4da3e8;color:#06283a;' : 'background:rgba(255,255,255,.1);color:#9dbdd2;'),
          caretStyle: 'color:#7fa3bb;font-size:11px;flex:none;' + (n[2] ? '' : 'visibility:hidden;')
        };
      }),
      sub: 'Dine-in, takeaway, delivery and online in one queue',
      goKitchen: () => this._host.setState({ view: 'kitchen' }),
      newOrder: () => this.setState({ form: this.blank('take') }),
      tabs: TABS.map(t => ({ name: t[0], count: s.orders.filter(t[1]).length, onClick: () => this.setState({ tab: t[0] }), style: this.pageTab(s.tab === t[0]),
        countStyle: MONO + 'font-size:10.5px;border-radius:4px;padding:1px 5px;margin-left:6px;' + (s.tab === t[0] ? 'background:#eaf1fb;color:#14549e;' : 'background:#eef2f6;color:#7d8b9c;') })),
      kpis: [
        { k: 'Open orders', v: String(s.orders.filter(open).length), n: s.orders.filter(o => o.status === 'ready').length + ' ready to hand over' },
        { k: 'Awaiting payment', v: this.money(unpaidOpen.reduce((a, o) => a + this.tot(o).due, 0)), n: unpaidOpen.length + ' orders' },
        { k: 'Avg kitchen time', v: avgPrep ? avgPrep + ' min' : '\u2014', n: 'sent to ready' },
        { k: 'Order value today', v: this.money(value), n: s.orders.filter(o => o.status !== 'cancelled').length + ' orders' }
      ],
      q: s.q, onQ: e => this.setState({ q: e.target.value }),
      chans: [['all','All']].concat(Object.keys(CH).map(k => [k, CH[k][0]])).map(c => ({ name: c[1], onClick: () => this.setState({ ch: c[0] }), style: this.seg(s.ch === c[0]) })),
      toggleUnpaid: () => this.setState({ unpaid: !s.unpaid }), upSw: usw, upKnob: ukn,
      rows: rows.map(o => {
        const tt = this.tot(o);
        const age = now - o.at;
        const late = open(o) && age > 15 && o.status !== 'out';
        const pay = o.pay === 'refunded' ? ['Refunded', 'neutral'] : tt.due <= 0 ? ['Paid', 'success'] : tt.paid > 0 ? ['Part paid', 'warn'] : ['Unpaid', 'warn'];
        return {
          no: o.no, time: hm(o.at), age: open(o) ? age + 'm ago' : '', ageStyle: 'font-size:10.5px;' + (late ? 'color:#b02020;font-weight:600;' : 'color:#9aa7b6;'),
          cust: o.cust, where: o.where, ch: CH[o.ch][0], chStyle: this.badge(TONE[CH[o.ch][1]]),
          items: o.items.reduce((a, l) => a + l.q, 0), total: this.money(tt.total),
          pay: pay[0], payBadge: this.badge(TONE[pay[1]]), status: ST[o.status][0], stBadge: this.badge(TONE[ST[o.status][1]]),
          open: () => this.setState({ sel: o.no }),
          style: "display:grid;grid-template-columns:0.7fr 0.6fr 1.6fr 1.1fr 0.5fr 0.9fr 0.9fr 1fr;gap:12px;padding:10px 16px;border-bottom:1px solid #f0f4f8;align-items:center;cursor:pointer;" + (o.no === s.sel ? 'background:#f0f6fd;box-shadow:inset 3px 0 0 #1668c9;' : '') + (o.status === 'cancelled' ? 'opacity:.6;' : '')
        };
      }),
      noRows: !rows.length,
      hasSel: !!d, d: d || {}, closeSel: () => this.setState({ sel: null }),
      openPay: () => this.setState({ pay: { no: sel.no, method: 'Card', tendered: '' } }),

      formOpen: !!f,
      fTitle: 'New order',
      fChans: [['take','Takeaway'],['dine','Dine-in'],['deliv','Delivery'],['online','Phone / online']].map(c => ({ name: c[1], onClick: () => this.setState(ss => ({ form: { ...ss.form, ch: c[0] } })), style: this.seg(f && f.ch === c[0]) + 'flex:1;' })),
      fCust: f ? f.cust : 'walk', custOpts: CUSTS.map(c => ({ id: c[0], name: c[1] })), onCust: e => this.setState(ss => ({ form: { ...ss.form, cust: e.target.value } })),
      fIsDine: !!f && f.ch === 'dine', fIsDeliv: !!f && f.ch === 'deliv', fIsPickup: !!f && (f.ch === 'take' || f.ch === 'online'),
      fTable: f ? f.table : '', tableOpts: TABLES.map(t => {
        const busy = s.orders.some(o => o.ch === 'dine' && o.where.indexOf(t + ' ') === 0 && ['done','cancelled'].indexOf(o.status) < 0);
        return { id: t, name: t + (busy ? ' \u00b7 has open order' : ' \u00b7 free') };
      }),
      onTable: e => this.setState(ss => ({ form: { ...ss.form, table: e.target.value } })),
      fAddr: f ? f.addr : '', onAddr: e => this.setState(ss => ({ form: { ...ss.form, addr: e.target.value } })),
      fPickup: f ? f.pickup : 'ASAP', onPickup: e => this.setState(ss => ({ form: { ...ss.form, pickup: e.target.value } })),
      menuCats: CATS.map(c => ({ name: c, onClick: () => this.setState(ss => ({ form: { ...ss.form, cat: c } })),
        style: 'height:30px;padding:0 12px;border-radius:99px;display:flex;align-items:center;font-size:12.5px;cursor:pointer;' + (f && f.cat === c ? 'background:#0f1c2b;color:#ffffff;font-weight:600;' : 'border:1px solid #dfe6ee;color:#4a5866;font-weight:500;') })),
      menu: f ? MENU.filter(m => m[1] === f.cat).map(m => {
        const inC = f.lines.filter(l => l.n === m[0]).reduce((a, l) => a + l.q, 0);
        return { name: m[0], price: this.money(m[2]), inCart: inC ? inC + ' in order' : '',
          qStyle: inC ? 'background:#1668c9;color:#ffffff;border-radius:5px;padding:2px 7px;font-size:10.5px;font-weight:700' : 'display:none',
          style: 'border-radius:10px;padding:10px 11px;cursor:pointer;border:1px solid ' + (inC ? '#1668c9' : '#dfe6ee') + ';background:' + (inC ? '#f6faff' : '#ffffff'),
          onClick: () => this.setState(ss => {
            const ls = ss.form.lines;
            const i = ls.findIndex(l => l.n === m[0] && !l.note);
            return { form: { ...ss.form, lines: i > -1 ? ls.map((l, j) => j === i ? { ...l, q: l.q + 1 } : l) : ls.concat([L(m[0], 1)]) } };
          }) };
      }) : [],
      fNote: f ? f.note : '', onNote: e => this.setState(ss => ({ form: { ...ss.form, note: e.target.value } })),
      cartTitle: f ? 'Order \u00b7 ' + f.lines.reduce((a, l) => a + l.q, 0) + ' items' : '',
      cart: f ? f.lines.map((l, i) => ({
        name: l.n, q: l.q, note: l.note, total: this.money(l.p * l.q),
        dec: () => this.setState(ss => ({ form: { ...ss.form, lines: ss.form.lines.map((x, j) => j === i ? { ...x, q: x.q - 1 } : x).filter(x => x.q > 0) } })),
        inc: () => this.setState(ss => ({ form: { ...ss.form, lines: ss.form.lines.map((x, j) => j === i ? { ...x, q: x.q + 1 } : x) } })),
        onNote: e => { const v = e.target.value; this.setState(ss => ({ form: { ...ss.form, lines: ss.form.lines.map((x, j) => j === i ? { ...x, note: v } : x) } })); }
      })) : [],
      cartEmpty: !f || !f.lines.length,
      discs: [0, 5, 10, 15].map(v => ({ name: v ? v + '%' : 'None', onClick: () => this.setState(ss => ({ form: { ...ss.form, disc: v } })),
        style: 'height:26px;padding:0 8px;border-radius:6px;display:grid;place-items:center;font-size:11.5px;cursor:pointer;' + (f && f.disc === v ? 'background:#0f1c2b;color:#ffffff;font-weight:700;' : 'border:1px solid #dfe6ee;color:#4a5866;') })),
      fSums: ft ? [['Subtotal', this.money(ft.sub)]].concat(f.disc ? [['Discount', '\u2212' + this.money(ft.disc)]] : []).concat(f.ch === 'deliv' ? [['Delivery fee', '6.90']] : []).concat([['Total', this.money(ft.total)]])
        .map((r, i, arr) => ({ k: r[0], v: r[1], style: 'display:flex;' + (i === arr.length - 1 ? 'font-size:16px;font-weight:700;' : 'color:#5d6b7d;') })) : [],
      toggleSend: () => this.setState(ss => ({ form: { ...ss.form, send: !ss.form.send } })),
      sendBox: 'width:18px;height:18px;border-radius:5px;display:grid;place-items:center;font-size:11px;font-weight:700;' + (f && f.send ? 'background:#1668c9;color:#ffffff;' : 'box-shadow:inset 0 0 0 1.5px #c2cedb;color:transparent;'),
      saveLater: () => this.saveOrder(false), savePay: () => this.saveOrder(true),
      payNowLabel: ft && ft.total ? 'Pay ' + this.money(ft.total) : 'Pay now',
      payNowStyle: 'flex:1.4;height:42px;border-radius:9px;display:grid;place-items:center;font-size:13.5px;font-weight:700;color:#ffffff;cursor:pointer;background:' + (f && f.lines.length ? '#1668c9' : '#9fbde0'),
      closeAll: () => this.setState({ form: null }),

      payOpen: !!po,
      payWhat: po ? po.no + ' \u00b7 ' + po.cust + ' \u00b7 ' + po.where : '',
      payDue: pt ? this.money(pt.due) : '',
      methods: ['Card', 'Cash', 'Account'].map(m => ({ name: m, onClick: () => this.setState(ss => ({ pay: { ...ss.pay, method: m } })),
        style: 'height:44px;border-radius:10px;display:grid;place-items:center;font-size:13.5px;font-weight:600;cursor:pointer;' + (p && p.method === m ? 'background:#f4f9ff;box-shadow:inset 0 0 0 1.5px #1668c9;color:#14549e;' : 'border:1px solid #dfe6ee;color:#4a5866;') })),
      isCash: !!p && p.method === 'Cash',
      tendered: p ? p.tendered : '', onTendered: e => { const v = e.target.value.replace(/[^0-9.]/g, ''); this.setState(ss => ({ pay: { ...ss.pay, tendered: v } })); },
      change: pt ? this.money(Math.max(0, (parseFloat(p.tendered) || 0) - pt.due)) : '',
      quickCash: pt ? [pt.due, Math.ceil(pt.due / 10) * 10, Math.ceil(pt.due / 50) * 50].filter((v, i, a) => a.indexOf(v) === i).map(v => ({ label: this.money(v), onClick: () => this.setState(ss => ({ pay: { ...ss.pay, tendered: String(v) } })) })) : [],
      confirmLabel: pt ? 'Confirm ' + this.money(pt.due) : 'Confirm',
      confirmStyle: 'height:40px;padding:0 16px;border-radius:9px;background:#2f9e63;color:#ffffff;display:flex;align-items:center;font-size:13px;font-weight:700;cursor:pointer',
      confirmPay: () => { const then = s.pay.then; const o = po; this.confirmPay(); if (then === 'done') this.later(() => { const cur = this.state.orders.find(x => x.no === o.no); this.move(cur, 'done', o.ch === 'deliv' ? 'Delivered' : 'Completed'); }, 60); },
      closePay: () => this.setState({ pay: null }),

      toasts: s.toasts.map(x => ({ msg: x.msg, dot: 'width:8px;height:8px;border-radius:50%;flex:none;background:' + TONE[x.tone][2] }))
    };
  }
}
return Component;
})();
});
