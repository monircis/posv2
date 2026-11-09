// POS terminal
DC.add(function () {
const PRODUCTS = [
  {id:1, name:'Beef Mince', cat:'beef', price:11.00, unit:'kg', stock:54, sku:'6603', fav:true},
  {id:2, name:'Beef Rump Steak', cat:'beef', price:24.90, unit:'kg', stock:41, sku:'6600', fav:true},
  {id:3, name:'Beef Scotch Fillet', cat:'beef', price:27.32, unit:'kg', stock:0, sku:'6595'},
  {id:4, name:'Beef T-Bone', cat:'beef', price:29.90, unit:'kg', stock:18, sku:'6598'},
  {id:5, name:'Beef Osso Buco', cat:'beef', price:21.30, unit:'kg', stock:23, sku:'6605'},
  {id:6, name:'Beef Liver', cat:'beef', price:11.00, unit:'kg', stock:6, sku:'6604'},
  {id:7, name:'Beef Curry Dice', cat:'beef', price:11.00, unit:'kg', stock:1.5, sku:'6592'},
  {id:8, name:'Lamb Chops', cat:'lamb', price:28.40, unit:'kg', stock:27, sku:'6622', fav:true},
  {id:9, name:'Lamb Shoulder', cat:'lamb', price:19.80, unit:'kg', stock:3, sku:'6621'},
  {id:10, name:'Lamb Leg', cat:'lamb', price:17.50, unit:'kg', stock:12, sku:'6624'},
  {id:11, name:'Lamb Mince', cat:'lamb', price:16.90, unit:'kg', stock:34, sku:'6623'},
  {id:12, name:'Chicken Breast', cat:'chicken', price:13.20, unit:'kg', stock:46, sku:'6642', fav:true},
  {id:13, name:'Chicken Thigh', cat:'chicken', price:9.40, unit:'kg', stock:9, sku:'6641'},
  {id:14, name:'Chicken Wings', cat:'chicken', price:8.10, unit:'kg', stock:52, sku:'6640'},
  {id:15, name:'Whole Chicken', cat:'chicken', price:12.90, unit:'ea', stock:14, sku:'6645'},
  {id:16, name:'Goat Curry Cut', cat:'goat', price:22.60, unit:'kg', stock:12, sku:'6631'},
  {id:17, name:'Goat Leg', cat:'goat', price:24.80, unit:'kg', stock:5, sku:'6632'},
  {id:18, name:'Beef Kabab Plate', cat:'grill', price:18.50, unit:'ea', stock:null, sku:'8101', fav:true, kitchen:true},
  {id:19, name:'Lamb Chop Plate', cat:'grill', price:24.00, unit:'ea', stock:null, sku:'8102', kitchen:true},
  {id:20, name:'Chicken Tikka Wrap', cat:'grill', price:13.50, unit:'ea', stock:null, sku:'8103', fav:true, kitchen:true},
  {id:21, name:'Mixed Grill Platter', cat:'grill', price:42.00, unit:'ea', stock:null, sku:'8104', kitchen:true},
  {id:22, name:'Rice & Salad', cat:'grill', price:6.00, unit:'ea', stock:null, sku:'8105', kitchen:true},
  {id:23, name:'Basmati Rice 5kg', cat:'grocery', price:18.00, unit:'ea', stock:44, sku:'7102'},
  {id:24, name:'Olive Oil 1L', cat:'grocery', price:15.60, unit:'ea', stock:26, sku:'7108'},
  {id:25, name:'Spice Mix 200g', cat:'grocery', price:6.40, unit:'ea', stock:60, sku:'7120'},
  {id:26, name:'Pita Bread 6 pack', cat:'grocery', price:4.50, unit:'ea', stock:4, sku:'7131'},
  {id:27, name:'Mango Lassi', cat:'drinks', price:5.50, unit:'ea', stock:null, sku:'9001', fav:true},
  {id:28, name:'Soft Drink 375ml', cat:'drinks', price:3.50, unit:'ea', stock:80, sku:'9002'},
  {id:29, name:'Water 600ml', cat:'drinks', price:3.00, unit:'ea', stock:60, sku:'9003'}
];

const CATS = [
  {id:'all', name:'All items', code:'All'},
  {id:'beef', name:'Beef', code:'Bf'},
  {id:'lamb', name:'Lamb', code:'Lb'},
  {id:'chicken', name:'Chicken', code:'Ch'},
  {id:'goat', name:'Goat', code:'Gt'},
  {id:'grill', name:'Grill & plates', code:'Gr'},
  {id:'grocery', name:'Grocery', code:'Gc'},
  {id:'drinks', name:'Drinks', code:'Dr'}
];

const CUSTOMERS = [
  {id:0, name:'Walk in', group:'Retail', phone:'', balance:0, limit:0, address:'', email:''},
  {id:1, name:'Al Amin Catering', group:'Wholesale', phone:'+61 413 220 887', balance:1842.45, limit:10000, address:'88 Canterbury Rd, Bankstown', email:'accounts@alamincatering.com.au'},
  {id:2, name:'Hassan Grocers', group:'Wholesale', phone:'+61 412 118 340', balance:4254.80, limit:5000, address:'21 The Boulevarde, Lakemba', email:'hassan@hassangrocers.com.au'},
  {id:3, name:'Riverside Cafe', group:'Wholesale', phone:'+61 411 907 552', balance:0, limit:3000, address:'4 Wharf St, Parramatta', email:'elena@riversidecafe.com.au'},
  {id:4, name:'Noor Restaurant', group:'Wholesale', phone:'+61 414 552 001', balance:620.80, limit:2000, address:'140 Haldon St, Lakemba', email:'bilal@noorrestaurant.com.au'},
  {id:5, name:'Mariam Chowdhury', group:'Retail', phone:'+61 402 771 118', balance:0, limit:0, address:'9 Ridgewell St, Roselands', email:'mariam.c@gmail.com'},
  {id:6, name:'Lakemba Community Kitchen', group:'Charity', phone:'+61 2 9759 0044', balance:0, limit:1000, address:'65 Haldon St, Lakemba', email:'kitchen@lakembacommunity.org.au'}
];

const GROUP_DISC = {Retail:0, Wholesale:8, Charity:15};
const SEATED = [9, 11];
const KEYS = ['7','8','9','4','5','6','1','2','3','.','0','\u232b'];
const PIN_KEYS = ['1','2','3','4','5','6','7','8','9','','0','\u232b'];
const TONES = {
  info:['#eaf1fb','#14549e','#1668c9'], success:['#e7f6ee','#0f6b43','#2f9e63'],
  warn:['#fdf1dd','#8a5300','#d98c1a'], error:['#fdeaea','#b02020','#d93a3a'], neutral:['#eef2f6','#5d6b7d','#8fa3b6']
};
const MONO = "font-family:'IBM Plex Mono',monospace;";
const r2 = n => Math.round(n * 100) / 100;
const rq = n => Math.round(n * 1000) / 1000;

class Component extends DCLogic {
  _id = 100;
  searchRef = React.createRef();

  state = {
    cat: 'all', q: '',
    lines: [
      {id:1, pid:2, qty:1.2, disc:0, note:''},
      {id:2, pid:18, qty:2, disc:0, note:'No onion on one'},
      {id:3, pid:27, qty:2, disc:0, note:''}
    ],
    sel: null, flash: null,
    type: 'Dine in', table: 4, cust: 0, disc: 0, discBy: '',
    stock: PRODUCTS.reduce((a, p) => { a[p.id] = p.stock; return a; }, {}),
    held: [
      {id:'h1', no:5011, label:'Table 7', type:'Dine in', table:7, cust:0, disc:0, discBy:'', time:'14:12',
        lines:[{id:11, pid:21, qty:1, disc:0, note:''}, {id:12, pid:22, qty:2, disc:0, note:''}, {id:13, pid:28, qty:3, disc:0, note:''}]},
      {id:'h2', no:5013, label:'Riverside Cafe', type:'Takeaway', table:4, cust:3, disc:0, discBy:'', time:'14:26',
        lines:[{id:14, pid:12, qty:5, disc:0, note:'Skin off'}, {id:15, pid:14, qty:4, disc:0, note:''}]}
    ],
    recent: [
      {no:5014, time:'14:31', cust:'Walk in', method:'Card', total:86.40, items:3},
      {no:5012, time:'14:05', cust:'Hassan Grocers', method:'On account', total:412.80, items:5},
      {no:5010, time:'13:58', cust:'Walk in', method:'Cash', total:64.80, items:2}
    ],
    orderNo: 5015, nextNo: 5016, sessCount: 38, sessTotal: 4265.75,
    toasts: [], overlay: null,
    kp: {mode:'add', pid:1, lineId:null, val:''},
    custQ: '', discPending: null, pin: '',
    tender: 'Cash', payInput: '', card: 'idle', splits: [], splitMethod: 'Card',
    done: null, clock: '14:38'
  };

  componentDidMount() {
    this.tick();
    this._clock = setInterval(() => this.tick(), 15000);
    this._key = e => this.onKey(e);
    window.addEventListener('keydown', this._key);
  }
  componentWillUnmount() {
    clearInterval(this._clock);
    window.removeEventListener('keydown', this._key);
    (this._timers || []).forEach(clearTimeout);
  }
  later(fn, ms) { const t = setTimeout(fn, ms); (this._timers = this._timers || []).push(t); }
  tick() {
    const d = new Date();
    this.setState({ clock: String(d.getHours()).padStart(2, '0') + ':' + String(d.getMinutes()).padStart(2, '0') });
  }

  money(n) { return (n || 0).toLocaleString('en-AU', {minimumFractionDigits:2, maximumFractionDigits:2}); }
  prod(id) { return PRODUCTS.find(p => p.id === id); }
  cust(id) { return CUSTOMERS.find(c => c.id === id) || CUSTOMERS[0]; }
  trimQ(q) { return String(rq(q)); }
  qtyLabel(q, unit) { return unit === 'kg' ? this.trimQ(q) + ' kg' : String(rq(q)); }

  toast(msg, tone) {
    const id = ++this._id;
    this.setState(s => ({ toasts: s.toasts.concat([{id, msg, tone: tone || 'info'}]).slice(-3) }));
    this.later(() => this.setState(s => ({ toasts: s.toasts.filter(t => t.id !== id) })), 2600);
  }

  avail(pid, exceptLine) {
    const st = this.state.stock[pid];
    if (st === null || st === undefined) return Infinity;
    const used = this.state.lines.filter(l => l.pid === pid && l.id !== exceptLine).reduce((a, l) => a + l.qty, 0);
    return rq(st - used);
  }

  calc(lines, custId, type, disc) {
    const c = this.cust(custId);
    const gp = GROUP_DISC[c.group] || 0;
    let items = 0;
    lines.forEach(l => { const p = this.prod(l.pid); items += p.price * l.qty * (1 - (l.disc || 0) / 100); });
    items = r2(items);
    const group = r2(items * gp / 100);
    const order = r2((items - group) * disc / 100);
    const fee = type === 'Delivery' && lines.length ? 15 : 0;
    const total = r2(items - group - order + fee);
    return { c, gp, items, group, order, fee, total, gst: r2(total / 11) };
  }

  filtered() {
    const s = this.state;
    const q = s.q.trim().toLowerCase();
    if (q) return PRODUCTS.filter(p => p.name.toLowerCase().indexOf(q) > -1 || p.sku.indexOf(q) === 0);
    if (s.cat === 'all') return PRODUCTS;
    return PRODUCTS.filter(p => p.cat === s.cat);
  }

  focusSearch() { const el = this.searchRef.current; if (el) el.focus(); }

  tap(p) {
    if (this.avail(p.id) <= 0) { this.toast(p.name + ' is out of stock', 'error'); return; }
    if (p.unit === 'kg' && (this.props.askWeight ?? true)) this.setState({ overlay:'kp', kp:{mode:'add', pid:p.id, lineId:null, val:''} });
    else this.addQty(p.id, 1);
  }

  addQty(pid, qty) {
    const p = this.prod(pid);
    const av = this.avail(pid);
    if (qty > av + 1e-9) {
      this.toast(av <= 0 ? p.name + ' is sold out' : 'Only ' + this.qtyLabel(av, p.unit) + ' of ' + p.name + ' left', 'error');
      return false;
    }
    let fid;
    this.setState(s => {
      const hit = s.lines.find(l => l.pid === pid && !l.note && !l.disc);
      if (hit) { fid = hit.id; return { lines: s.lines.map(l => l === hit ? { ...l, qty: rq(l.qty + qty) } : l), flash: hit.id }; }
      fid = ++this._id;
      return { lines: s.lines.concat([{ id: fid, pid, qty: rq(qty), disc: 0, note: '' }]), flash: fid };
    });
    this.later(() => this.setState(s => s.flash === fid ? { flash: null } : null), 700);
    return true;
  }

  bump(id, dir) {
    const l = this.state.lines.find(x => x.id === id);
    if (!l) return;
    const p = this.prod(l.pid);
    const step = p.unit === 'kg' ? 0.25 : 1;
    const nq = rq(l.qty + dir * step);
    if (nq <= 0.0001) { this.removeLine(id); return; }
    if (dir > 0 && nq > this.avail(l.pid, id) + 1e-9) {
      this.toast('Only ' + this.qtyLabel(this.avail(l.pid, id), p.unit) + ' of ' + p.name + ' in stock', 'error');
      return;
    }
    this.setState(s => ({ lines: s.lines.map(x => x.id === id ? { ...x, qty: nq } : x) }));
  }

  removeLine(id) {
    const l = this.state.lines.find(x => x.id === id);
    if (!l) return;
    this.setState(s => ({ lines: s.lines.filter(x => x.id !== id), sel: s.sel === id ? null : s.sel }));
    this.toast('Removed ' + this.prod(l.pid).name, 'neutral');
  }

  kpPress(k) {
    this.setState(s => {
      const kp = s.kp;
      const p = this.prod(kp.pid);
      let v = kp.val;
      if (k === '\u232b') v = v.slice(0, -1);
      else if (k === '.') { if (p.unit !== 'kg' || v.indexOf('.') > -1) return null; v = (v || '0') + '.'; }
      else {
        const dec = v.split('.')[1];
        if (v.length >= 6 || (dec !== undefined && dec.length >= 3)) return null;
        v = v === '0' ? k : v + k;
      }
      return { kp: { ...kp, val: v } };
    });
  }

  kpConfirm() {
    const kp = this.state.kp;
    const v = parseFloat(kp.val);
    if (!v || v <= 0) return;
    const p = this.prod(kp.pid);
    if (kp.mode === 'add') { if (this.addQty(p.id, rq(v))) this.setState({ overlay: null }); return; }
    const av = this.avail(p.id, kp.lineId);
    if (v > av + 1e-9) { this.toast('Only ' + this.qtyLabel(av, p.unit) + ' of ' + p.name + ' in stock', 'error'); return; }
    this.setState(s => ({ lines: s.lines.map(x => x.id === kp.lineId ? { ...x, qty: rq(v) } : x), overlay: null }));
  }

  setCust(id) {
    const c = this.cust(id);
    const wasPay = this.state.returnToPay;
    this.setState({ cust: id, overlay: wasPay ? 'pay' : null, custQ: '', returnToPay: false, tender: wasPay ? 'Account' : this.state.tender });
    const gp = GROUP_DISC[c.group];
    if (gp) this.toast(c.group + ' pricing applied \u00b7 ' + gp + '% off', 'success');
    else if (id !== 0) this.toast(c.name + ' added to order', 'info');
  }

  setType(t) {
    this.setState({ type: t });
    if (t === 'Delivery' && this.state.cust === 0) this.toast('Delivery needs a customer with an address', 'warn');
  }

  pickDisc(p) {
    if (p <= 10) {
      this.setState({ disc: p, discBy: '', overlay: null, discPending: null, pin: '' });
      this.toast(p ? p + '% order discount applied' : 'Order discount removed', p ? 'success' : 'neutral');
    } else this.setState({ discPending: p, pin: '' });
  }

  pinPress(k) {
    const s = this.state;
    if (!k) return;
    if (k === '\u232b') { this.setState({ pin: s.pin.slice(0, -1) }); return; }
    if (s.pin.length >= 4) return;
    const pin = s.pin + k;
    this.setState({ pin });
    if (pin.length === 4) this.later(() => {
      const p = this.state.discPending;
      if (!p) return;
      this.setState({ disc: p, discBy: 'Sara Rahman', overlay: null, discPending: null, pin: '' });
      this.toast(p + '% discount approved by Sara Rahman', 'success');
    }, 320);
  }

  holdLabel(s) {
    const c = this.cust(s.cust);
    if (s.type === 'Dine in') return 'Table ' + s.table;
    if (c.id !== 0) return c.name;
    return s.type + ' #' + s.orderNo;
  }

  snapshot(s) {
    return { id: 'h' + (++this._id), no: s.orderNo, label: this.holdLabel(s), type: s.type, table: s.table, cust: s.cust,
      lines: s.lines, disc: s.disc, discBy: s.discBy, time: s.clock };
  }

  hold() {
    const s = this.state;
    if (!s.lines.length) { this.toast('Nothing to hold \u2014 the order is empty', 'warn'); return; }
    const h = this.snapshot(s);
    this.setState(st => ({ held: [h].concat(st.held), lines: [], sel: null, disc: 0, discBy: '', cust: 0,
      orderNo: st.nextNo, nextNo: st.nextNo + 1, overlay: null }));
    this.toast('Held as ' + h.label, 'success');
  }

  resume(id) {
    const s = this.state;
    const h = s.held.find(x => x.id === id);
    if (!h) return;
    let held = s.held.filter(x => x.id !== id);
    if (s.lines.length) {
      const cur = this.snapshot(s);
      held = [cur].concat(held);
      this.toast('Current order held as ' + cur.label, 'info');
    }
    this.setState({ held, lines: h.lines, type: h.type, table: h.table, cust: h.cust, disc: h.disc, discBy: h.discBy,
      orderNo: h.no, sel: null, overlay: null });
    this.toast('Resumed ' + h.label, 'success');
  }

  openPay() {
    const s = this.state;
    if (!s.lines.length) { this.toast('Add items before taking payment', 'warn'); return; }
    if (s.type === 'Delivery' && s.cust === 0) {
      this.toast('Pick the delivery customer first', 'warn');
      this.setState({ overlay: 'cust' });
      return;
    }
    this.setState({ overlay: 'pay', tender: 'Cash', payInput: '', card: 'idle', splits: [], splitMethod: 'Card' });
  }

  payPress(k) {
    this.setState(s => {
      let v = s.payInput;
      if (k === '\u232b') v = v.slice(0, -1);
      else if (k === '.') { if (v.indexOf('.') > -1) return null; v = (v || '0') + '.'; }
      else {
        const d = v.split('.')[1];
        if (v.length >= 7 || (d !== undefined && d.length >= 2)) return null;
        v = v === '0' ? k : v + k;
      }
      return { payInput: v };
    });
  }

  sendCard(decline) {
    this.setState({ card: 'processing' });
    this.later(() => {
      if (this.state.overlay === 'pay' && this.state.card === 'processing') {
        this.setState({ card: decline ? 'declined' : 'approved' });
        if (!decline) this.toast('Card approved', 'success');
      }
    }, 1600);
  }

  payState() {
    const s = this.state;
    const T = this.calc(s.lines, s.cust, s.type, s.disc);
    const cashDue = Math.round(T.total * 20) / 20;
    const tendered = parseFloat(s.payInput) || 0;
    const paidSplit = r2(s.splits.reduce((a, x) => a + x.amount, 0));
    const rem = r2(T.total - paidSplit);
    const c = T.c;
    const avail = r2(c.limit - c.balance);
    let ready = false, blocked = '';
    if (s.tender === 'Cash') ready = tendered >= cashDue - 0.001;
    else if (s.tender === 'Card') ready = s.card === 'approved';
    else if (s.tender === 'Split') ready = s.splits.length > 0 && rem <= 0.005;
    else {
      if (c.id === 0) blocked = 'walkin';
      else if (c.limit <= 0) blocked = 'nocredit';
      else if (T.total > avail) blocked = 'limit';
      ready = !blocked;
    }
    if (!s.lines.length || T.total <= 0) ready = false;
    return { T, cashDue, tendered, change: r2(tendered - cashDue), paidSplit, rem, avail, ready, blocked };
  }

  addSplit() {
    const s = this.state;
    const pv = this.payState();
    if (pv.rem <= 0.005) return;
    let amt = parseFloat(s.payInput);
    if (!amt) amt = pv.rem;
    amt = r2(Math.min(amt, pv.rem));
    this.setState({ splits: s.splits.concat([{ method: s.splitMethod, amount: amt }]), payInput: '' });
  }

  complete() {
    const s = this.state;
    const pv = this.payState();
    if (!pv.ready) return;
    const T = pv.T;
    const method = s.tender === 'Cash' ? 'Cash' : s.tender === 'Card' ? 'Card'
      : s.tender === 'Split' ? s.splits.map(x => x.method).filter((v, i, a) => a.indexOf(v) === i).join(' + ') : 'On account';
    const charged = s.tender === 'Cash' ? pv.cashDue : T.total;
    const kitchen = s.lines.some(l => this.prod(l.pid).kitchen);
    const receipt = {
      no: s.orderNo, time: s.clock, cust: T.c, type: s.type, table: s.table,
      lines: s.lines.map(l => { const p = this.prod(l.pid); return { name: p.name, qty: p.unit === 'kg' ? this.qtyLabel(l.qty, 'kg') : l.qty + ' \u00d7', total: this.money(p.price * l.qty * (1 - l.disc / 100)), note: l.note }; }),
      items: T.items, group: T.group, gp: T.gp, order: T.order, disc: s.disc, fee: T.fee, total: charged, gst: T.gst,
      rounding: s.tender === 'Cash' ? r2(pv.cashDue - T.total) : 0,
      method, tendered: s.tender === 'Cash' ? pv.tendered : charged, change: s.tender === 'Cash' ? pv.change : 0, kitchen
    };
    const stock = { ...s.stock };
    s.lines.forEach(l => { if (stock[l.pid] !== null && stock[l.pid] !== undefined) stock[l.pid] = rq(stock[l.pid] - l.qty); });
    this.setState({
      stock,
      recent: [{ no: s.orderNo, time: s.clock, cust: T.c.name, method, total: charged, items: s.lines.length }].concat(s.recent),
      sessCount: s.sessCount + 1, sessTotal: r2(s.sessTotal + charged),
      lines: [], sel: null, disc: 0, discBy: '', cust: 0,
      orderNo: s.nextNo, nextNo: s.nextNo + 1, overlay: 'done', done: receipt
    });
  }

  newSale() { this.setState({ overlay: null, done: null }); this.later(() => this.focusSearch(), 50); }

  closeOverlay() {
    const o = this.state.overlay;
    if (o === 'done') { this.newSale(); return; }
    if (o === 'cust' && this.state.returnToPay) { this.setState({ overlay: 'pay', returnToPay: false, custQ: '' }); return; }
    this.setState({ overlay: null, discPending: null, pin: '', custQ: '' });
  }

  onKey(e) {
    const s = this.state;
    const tag = (e.target && e.target.tagName) || '';
    const typing = tag === 'INPUT' || tag === 'TEXTAREA';
    const k = e.key;
    if (k === 'F2') { e.preventDefault(); if (!s.overlay) this.focusSearch(); return; }
    if (k === 'F9') { e.preventDefault(); if (!s.overlay) this.openPay(); return; }
    if (k === 'F4') { e.preventDefault(); if (!s.overlay) this.hold(); return; }
    if (k === 'Escape') { if (s.overlay) { e.preventDefault(); this.closeOverlay(); } return; }
    if (typing) return;
    const digit = /^[0-9]$/.test(k);
    if (s.overlay === 'kp') {
      if (digit || k === '.') this.kpPress(k);
      else if (k === 'Backspace') this.kpPress('\u232b');
      else if (k === 'Enter') this.kpConfirm();
      else return;
      e.preventDefault(); return;
    }
    if (s.overlay === 'pay') {
      if ((s.tender === 'Cash' || s.tender === 'Split') && (digit || k === '.')) { e.preventDefault(); this.payPress(k); return; }
      if ((s.tender === 'Cash' || s.tender === 'Split') && k === 'Backspace') { e.preventDefault(); this.payPress('\u232b'); return; }
      if (k === 'Enter') { e.preventDefault(); if (s.tender === 'Split' && this.payState().rem > 0.005) this.addSplit(); else this.complete(); }
      return;
    }
    if (s.overlay === 'disc' && s.discPending) {
      if (digit) this.pinPress(k); else if (k === 'Backspace') this.pinPress('\u232b');
      return;
    }
    if (s.overlay === 'done' && k === 'Enter') { e.preventDefault(); this.newSale(); return; }
    if (!s.overlay && digit) this.focusSearch();
  }

  onSearchKey(e) {
    if (e.key === 'Enter') {
      const q = this.state.q.trim();
      if (!q) return;
      const exact = PRODUCTS.find(p => p.sku === q);
      const list = this.filtered();
      const hit = exact || (list.length === 1 ? list[0] : null);
      if (hit) {
        this.setState({ q: '' });
        if (exact) this.toast('Scanned ' + q + ' \u00b7 ' + hit.name, 'info');
        this.tap(hit);
      } else if (!list.length) this.toast('No product matches \u201c' + q + '\u201d', 'error');
      else this.toast(list.length + ' matches \u2014 keep typing or tap one', 'neutral');
    } else if (e.key === 'Escape') this.setState({ q: '' });
  }

  renderVals() {
    const s = this.state;
    const T = this.calc(s.lines, s.cust, s.type, s.disc);
    const list = this.filtered();
    const qTrim = s.q.trim();
    const catObj = CATS.find(c => c.id === s.cat) || CATS[0];
    const inCartQty = pid => s.lines.filter(l => l.pid === pid).reduce((a, l) => a + l.qty, 0);
    const cartEmpty = !s.lines.length;
    const deliveryBlocked = s.type === 'Delivery' && s.cust === 0;
    const compact = (this.props.density ?? 'Comfortable') === 'Compact';
    const pv = this.payState();
    const c = T.c;
    const kpP = this.prod(s.kp.pid);
    const kpVal = parseFloat(s.kp.val) || 0;
    const kpAvail = this.avail(kpP.id, s.kp.mode === 'edit' ? s.kp.lineId : undefined);
    const kpOver = kpVal > kpAvail + 1e-9;
    const pad = (keys, fn) => keys.map(k => ({ label: k, onClick: () => fn(k) }));

    const catCount = id => id === 'all' ? PRODUCTS.length : PRODUCTS.filter(p => p.cat === id).length;

    const ctx = s.type === 'Dine in'
      ? { kicker:'Table', label:'Table ' + s.table, sub:'Tap to change', onClick: () => this.setState({ overlay:'table' }),
          style:'border:1px solid #dfe6ee;border-radius:10px;padding:8px 11px;cursor:pointer;min-width:0;background:#ffffff;' }
      : s.type === 'Takeaway'
      ? { kicker:'Pickup', label:'Order #' + s.orderNo, sub:'Ready in about 15 min', onClick: () => {},
          style:'border:1px solid #dfe6ee;border-radius:10px;padding:8px 11px;min-width:0;background:#f6f9fc;' }
      : deliveryBlocked
      ? { kicker:'Deliver to', label:'No address yet', sub:'Pick a customer', onClick: () => this.setState({ overlay:'cust' }),
          style:'border:1px solid #f0d9ac;border-radius:10px;padding:8px 11px;cursor:pointer;min-width:0;background:#fdf1dd;color:#8a5300;' }
      : { kicker:'Deliver to', label:(c.address.split(',')[1] || c.address).trim(), sub:c.address.split(',')[0] + ' \u00b7 +15.00', onClick: () => this.setState({ overlay:'cust' }),
          style:'border:1px solid #dfe6ee;border-radius:10px;padding:8px 11px;cursor:pointer;min-width:0;background:#ffffff;' };

    const cq = s.custQ.trim().toLowerCase();
    const custList = CUSTOMERS.filter(x => !cq || x.name.toLowerCase().indexOf(cq) > -1 || x.phone.replace(/\s/g, '').indexOf(cq.replace(/\s/g, '')) > -1);

    const quick = [pv.cashDue, Math.ceil(pv.cashDue / 5) * 5, Math.ceil(pv.cashDue / 10) * 10, Math.ceil(pv.cashDue / 20) * 20, Math.ceil(pv.cashDue / 50) * 50, Math.ceil(pv.cashDue / 100) * 100]
      .map(r2).filter((v, i, a) => a.indexOf(v) === i).slice(0, 5);

    const cardState = {
      idle: { title:'Ready for the terminal', body:'Sends ' + this.money(T.total) + ' to Tyro terminal 01. The customer taps, inserts or swipes there.', tone:'info', showSend:true, sendLabel:'Send ' + this.money(T.total) },
      processing: { title:'Waiting for the customer\u2026', body:'Tyro terminal 01 is showing ' + this.money(T.total) + '. Keep this open until it approves.', tone:'warn', showSend:false, sendLabel:'' },
      approved: { title:'Approved', body:'Auth 004812 \u00b7 Visa \u2022\u2022\u2022\u2022 4417. Merchant copy printed at the terminal.', tone:'success', showSend:false, sendLabel:'' },
      declined: { title:'Declined', body:'The bank declined this card. Ask for another card or switch tender.', tone:'error', showSend:true, sendLabel:'Try again' }
    }[s.card];
    const ct = TONES[cardState.tone];

    const acctMsg = pv.blocked === 'walkin' ? { mark:'!', msg:'Walk-in customers have no credit account. Choose a customer to charge this to their account.', tone:'warn', showPick:true }
      : pv.blocked === 'nocredit' ? { mark:'!', msg:c.name + ' is a retail customer without credit terms. Take another tender.', tone:'warn', showPick:false }
      : pv.blocked === 'limit' ? { mark:'\u00d7', msg:'This sale would take ' + c.name + ' ' + this.money(T.total - pv.avail) + ' over their ' + this.money(c.limit) + ' limit. Take part payment first, or ask a manager to raise the limit.', tone:'error', showPick:false }
      : { mark:'\u2713', msg:'Charges ' + this.money(T.total) + ' to ' + c.name + '. Invoice due in 30 days on wholesale terms.', tone:'success', showPick:false };
    const at = TONES[acctMsg.tone];

    const payReadyLabel = pv.ready
      ? (s.tender === 'Cash' ? 'Complete \u00b7 change ' + this.money(Math.max(0, pv.change)) : 'Complete sale')
      : s.tender === 'Cash' ? 'Enter cash tendered'
      : s.tender === 'Card' ? (s.card === 'processing' ? 'Waiting for terminal\u2026' : 'Send to terminal first')
      : s.tender === 'Split' ? 'Still ' + this.money(pv.rem) + ' to take'
      : 'Can\u2019t charge to account';

    const d = s.done;

    return {
      hasExit: true,
      exit: () => { location.href = 'sell.html'; },
      sessLine: s.sessCount + ' sales \u00b7 ' + this.money(s.sessTotal),
      clock: s.clock,
      heldCount: s.held.length,
      heldBadgeStyle: MONO + 'font-size:11px;font-weight:600;border-radius:99px;min-width:20px;height:20px;padding:0 6px;display:grid;place-items:center;'
        + (s.held.length ? 'background:#d98c1a;color:#1f1300;' : 'background:rgba(255,255,255,.14);color:#9fb2c4;'),
      openHeld: () => this.setState({ overlay: 'held' }),
      openRecent: () => this.setState({ overlay: 'recent' }),
      noSale: () => this.toast('Cash drawer opened \u00b7 logged as no-sale by Aminul', 'neutral'),

      cats: CATS.map(cat => {
        const on = !qTrim && s.cat === cat.id;
        return {
          code: cat.code, name: cat.name, count: catCount(cat.id),
          onClick: () => this.setState({ cat: cat.id, q: '' }),
          style: 'min-height:78px;border-radius:12px;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:4px;cursor:pointer;padding:6px 4px;flex:none;'
            + (on ? 'background:#0f1c2b;color:#ffffff;' : 'color:#4a5866;'),
          codeStyle: 'width:30px;height:30px;border-radius:8px;display:grid;place-items:center;' + MONO + 'font-size:10.5px;font-weight:600;'
            + (on ? 'background:#4da3e8;color:#06283a;' : 'background:#eef2f6;color:#5d6b7d;')
        };
      }),

      searchRef: this.searchRef,
      q: s.q,
      hasQuery: !!s.q,
      onSearch: e => this.setState({ q: e.target.value }),
      onSearchKey: e => this.onSearchKey(e),
      clearSearch: () => { this.setState({ q: '' }); this.focusSearch(); },
      gridTitle: qTrim ? 'Results for \u201c' + qTrim + '\u201d' : catObj.name,
      gridMeta: qTrim
        ? (list.length ? list.length + (list.length === 1 ? ' match \u00b7 Enter adds it' : ' matches \u00b7 Enter on an exact SKU adds it') : 'No matches')
        : list.length + ' products \u00b7 cuts by weight open the scale keypad',
      noResults: !!qTrim && !list.length,
      gridStyle: 'display:grid;gap:10px;grid-template-columns:repeat(auto-fill,minmax(' + (compact ? 132 : 158) + 'px,1fr))',
      products: list.map(p => {
        const st = s.stock[p.id];
        const av = this.avail(p.id);
        const out = av <= 0;
        const low = st !== null && !out && av <= 5;
        const n = inCartQty(p.id);
        return {
          name: p.name, price: this.money(p.price), per: p.unit === 'kg' ? '/ kg' : 'each',
          tag: (p.kitchen ? 'Kitchen \u00b7 ' : 'SKU ') + p.sku,
          inCart: p.unit === 'kg' ? this.trimQ(n) + ' kg' : '\u00d7' + n,
          inCartStyle: n ? 'margin-left:auto;height:22px;border-radius:99px;background:#1668c9;color:#ffffff;' + MONO + 'font-size:10.5px;font-weight:600;display:grid;place-items:center;padding:0 8px;flex:none;' : 'display:none',
          stock: st === null ? '' : out ? 'Out' : (p.unit === 'kg' ? this.trimQ(av) + ' kg' : av + ' left'),
          stockStyle: st === null ? 'display:none' : 'margin-left:auto;' + MONO + 'font-size:10.5px;font-weight:600;border-radius:5px;padding:2px 6px;flex:none;'
            + (out ? 'background:#fdeaea;color:#b02020;' : low ? 'background:#fdf1dd;color:#8a5300;' : 'background:#eef2f6;color:#5d6b7d;'),
          nameStyle: 'font-size:' + (compact ? 12.5 : 13.5) + 'px;font-weight:600;line-height:1.3;flex:1;text-wrap:pretty',
          style: 'background:#ffffff;border:1px solid #dfe6ee;border-radius:12px;padding:11px 12px 12px;cursor:pointer;display:flex;flex-direction:column;user-select:none;transition:border-color .12s,box-shadow .12s;'
            + 'min-height:' + (compact ? 98 : 122) + 'px;'
            + (n ? 'border-color:#9cc6ea;box-shadow:inset 0 0 0 1px #9cc6ea;' : '')
            + (out ? 'opacity:.5;cursor:not-allowed;' : ''),
          onClick: () => this.tap(p)
        };
      }),

      toasts: s.toasts.map(t => ({ msg: t.msg, dot: 'width:8px;height:8px;border-radius:50%;flex:none;background:' + TONES[t.tone][2] })),

      orderNo: s.orderNo,
      orderMeta: s.lines.length ? 'started ' + s.clock + ' \u00b7 Aminul' : 'new order',
      types: ['Dine in', 'Takeaway', 'Delivery'].map(t => ({
        name: t,
        onClick: () => this.setType(t),
        style: 'flex:1;height:34px;border-radius:8px;display:grid;place-items:center;font-size:12.5px;cursor:pointer;'
          + (s.type === t ? 'background:#ffffff;color:#0f1c2b;font-weight:700;box-shadow:0 1px 3px rgba(15,28,43,.14);' : 'color:#5d6b7d;font-weight:500;')
      })),
      ctx,
      custChip: {
        name: c.name,
        sub: c.id === 0 ? 'Retail pricing \u00b7 tap to pick' : c.group + (T.gp ? ' \u00b7 ' + T.gp + '% off' : '') + (c.limit ? ' \u00b7 owes ' + this.money(c.balance) : ''),
        style: 'border-radius:10px;padding:8px 11px;cursor:pointer;min-width:0;'
          + (c.id === 0 ? 'border:1px dashed #c2cedb;background:#ffffff;color:#4a5866;' : 'border:1px solid #cfe0f5;background:#f4f9ff;color:#0f1c2b;')
      },
      openCust: () => this.setState({ overlay: 'cust', custQ: '' }),

      cartEmpty,
      lines: s.lines.map((l, i) => {
        const p = this.prod(l.pid);
        const gross = p.price * l.qty;
        const net = gross * (1 - l.disc / 100);
        const sel = s.sel === l.id;
        return {
          name: p.name,
          meta: this.money(p.price) + (p.unit === 'kg' ? ' / kg' : ' each'),
          qty: this.qtyLabel(l.qty, p.unit),
          total: this.money(net),
          orig: this.money(gross),
          origStyle: l.disc ? MONO + 'font-size:10.5px;color:#9aa7b6;text-decoration:line-through' : 'display:none',
          discTag: '\u2212' + l.disc + '%',
          discTagStyle: l.disc ? MONO + 'font-size:10.5px;font-weight:600;background:#fdeaea;color:#b02020;border-radius:4px;padding:1px 5px' : 'display:none',
          note: l.note,
          noteStyle: l.note ? 'font-size:11.5px;color:#b45309;margin-top:3px;font-style:italic;white-space:nowrap;overflow:hidden;text-overflow:ellipsis' : 'display:none',
          kitchenStyle: p.kitchen ? MONO + 'font-size:9px;letter-spacing:.06em;text-transform:uppercase;background:#eaf1fb;color:#14549e;border-radius:4px;padding:2px 5px;flex:none' : 'display:none',
          selected: sel,
          rowStyle: 'padding:11px 16px;transition:background .35s ease;'
            + (i < s.lines.length - 1 ? 'border-bottom:1px solid #f0f4f8;' : 'border-bottom:1px solid #f0f4f8;')
            + (sel ? 'background:#f4f8fd;box-shadow:inset 3px 0 0 #1668c9;' : s.flash === l.id ? 'background:#e7f6ee;' : ''),
          select: () => this.setState({ sel: sel ? null : l.id }),
          inc: () => this.bump(l.id, 1),
          dec: () => this.bump(l.id, -1),
          edit: () => this.setState({ overlay: 'kp', kp: { mode: 'edit', pid: p.id, lineId: l.id, val: this.trimQ(l.qty) } }),
          remove: () => this.removeLine(l.id),
          onNote: e => { const v = e.target.value; this.setState(st => ({ lines: st.lines.map(x => x.id === l.id ? { ...x, note: v } : x) })); },
          discChips: [0, 5, 10].map(dv => ({
            label: dv ? dv + '%' : 'None',
            onClick: () => this.setState(st => ({ lines: st.lines.map(x => x.id === l.id ? { ...x, disc: dv } : x) })),
            style: 'height:30px;padding:0 11px;border-radius:8px;display:grid;place-items:center;font-size:12px;cursor:pointer;'
              + (l.disc === dv ? 'background:#1668c9;color:#ffffff;font-weight:700;' : 'border:1px solid #dfe6ee;background:#ffffff;color:#4a5866;font-weight:500;')
          }))
        };
      }),

      itemCount: s.lines.length,
      itemsTotal: this.money(T.items),
      showGroup: T.group > 0,
      groupLabel: c.group + ' pricing ' + T.gp + '%',
      groupAmt: this.money(T.group),
      discLabel: s.disc ? 'Order discount ' + s.disc + '%' + (s.discBy ? ' \u00b7 approved by ' + s.discBy.split(' ')[0] : '') : 'Order discount',
      discAmt: s.disc ? '\u2212' + this.money(T.order) : 'Add',
      discAmtStyle: s.disc ? MONO + 'font-size:13px;color:#b02020' : 'font-size:12.5px;font-weight:600;color:#1668c9',
      showFee: T.fee > 0,
      gst: this.money(T.gst),
      total: this.money(T.total),
      discHint: s.disc ? s.disc + '% on' : 'order',
      lineCountHint: s.lines.length ? s.lines.length + (s.lines.length === 1 ? ' line' : ' lines') : 'empty',
      hold: () => this.hold(),
      openDisc: () => { if (!s.lines.length) { this.toast('Add items before discounting', 'warn'); return; } this.setState({ overlay: 'disc', discPending: null, pin: '' }); },
      openVoid: () => { if (!s.lines.length) { this.toast('The order is already empty', 'neutral'); return; } this.setState({ overlay: 'void' }); },
      openPay: () => this.openPay(),
      payBtnLabel: cartEmpty ? 'Pay' : deliveryBlocked ? 'Pick a delivery customer' : 'Pay ' + this.money(T.total),
      payBtnStyle: 'height:58px;border-radius:13px;display:flex;align-items:center;justify-content:center;gap:12px;color:#ffffff;'
        + (cartEmpty ? 'background:#b7c6d6;cursor:not-allowed;' : deliveryBlocked ? 'background:#b45309;cursor:pointer;' : 'background:#1668c9;cursor:pointer;box-shadow:0 10px 22px -12px rgba(22,104,201,.9);'),

      closeOverlay: () => this.closeOverlay(),

      kpOpen: s.overlay === 'kp',
      kp: {
        title: s.kp.mode === 'add' ? (kpP.unit === 'kg' ? 'Weigh ' + kpP.name : 'Quantity \u00b7 ' + kpP.name) : 'Change quantity \u00b7 ' + kpP.name,
        sub: this.money(kpP.price) + (kpP.unit === 'kg' ? ' per kg \u00b7 read the scale or type the weight' : ' each'),
        display: s.kp.val || '0',
        unit: kpP.unit === 'kg' ? 'kg' : 'qty',
        preview: '= ' + this.money(kpP.price * kpVal),
        stock: kpAvail === Infinity ? 'Made to order' : kpOver ? 'Only ' + this.qtyLabel(kpAvail, kpP.unit) + ' available' : this.qtyLabel(kpAvail, kpP.unit) + (kpP.unit === 'kg' ? '' : ' left') + ' in stock',
        stockStyle: 'font-size:11.5px;' + (kpOver ? 'color:#b02020;font-weight:600;' : 'color:#7d8b9c;'),
        presets: (kpP.unit === 'kg' ? [0.25, 0.5, 1, 1.5, 2] : [1, 2, 3, 5, 10]).map(v => ({
          label: kpP.unit === 'kg' ? String(v) : '\u00d7' + v,
          onClick: () => this.setState(st => ({ kp: { ...st.kp, val: String(v) } }))
        })),
        confirmLabel: kpOver ? 'Not enough stock' : !kpVal ? (kpP.unit === 'kg' ? 'Enter a weight' : 'Enter a quantity') : (s.kp.mode === 'add' ? 'Add \u00b7 ' : 'Update \u00b7 ') + this.money(kpP.price * kpVal),
        confirmStyle: 'height:48px;border-radius:11px;display:grid;place-items:center;font-size:14px;font-weight:700;color:#ffffff;'
          + (kpVal && !kpOver ? 'background:#1668c9;cursor:pointer;' : 'background:#b7c6d6;cursor:not-allowed;pointer-events:none;')
      },
      kpKeys: pad(KEYS, k => this.kpPress(k)),
      kpConfirm: () => this.kpConfirm(),

      custOpen: s.overlay === 'cust',
      custQ: s.custQ,
      onCustQ: e => this.setState({ custQ: e.target.value }),
      custNone: !custList.length,
      custRows: custList.map(x => {
        const on = x.id === s.cust;
        const gp = GROUP_DISC[x.group];
        const near = x.limit && x.balance / x.limit > 0.8;
        return {
          initials: x.id === 0 ? 'W' : x.name.split(' ').map(w => w[0]).join('').slice(0, 2).toUpperCase(),
          name: x.name,
          sub: x.id === 0 ? 'No account \u00b7 retail pricing' : x.group + (gp ? ' \u00b7 ' + gp + '% off' : '') + ' \u00b7 ' + x.phone,
          right: x.limit ? 'owes ' + this.money(x.balance) : '',
          rightSub: x.limit ? 'limit ' + this.money(x.limit) : '',
          rightStyle: MONO + 'font-size:12.5px;font-weight:600;' + (near ? 'color:#b02020;' : x.balance ? 'color:#8a5300;' : 'color:#5d6b7d;'),
          style: 'display:flex;align-items:center;gap:12px;padding:10px 12px;border-radius:11px;cursor:pointer;' + (on ? 'background:#f4f9ff;box-shadow:inset 0 0 0 1px #cfe0f5;' : ''),
          checkStyle: 'width:22px;height:22px;border-radius:50%;display:grid;place-items:center;font-size:11px;flex:none;'
            + (on ? 'background:#1668c9;color:#ffffff;' : 'color:transparent;'),
          onClick: () => this.setCust(x.id)
        };
      }),
      newCustomer: () => this.toast('Opens the contact form in a drawer', 'neutral'),

      tableOpen: s.overlay === 'table',
      tables: Array.from({ length: 12 }, (_, i) => i + 1).map(n => {
        const h = s.held.find(x => x.type === 'Dine in' && x.table === n);
        const cur = s.type === 'Dine in' && s.table === n;
        const seated = SEATED.indexOf(n) > -1;
        const tone = cur ? 'info' : h ? 'warn' : seated ? 'neutral' : 'success';
        const status = cur ? 'This order' : h ? 'Held \u00b7 ' + this.money(this.calc(h.lines, h.cust, h.type, h.disc).total) : seated ? 'Seated' : 'Free';
        const t = TONES[tone];
        return {
          n: 'T' + n, status,
          dot: 'width:7px;height:7px;border-radius:50%;flex:none;background:' + t[2],
          style: 'height:86px;border-radius:12px;padding:12px;display:flex;flex-direction:column;justify-content:space-between;cursor:pointer;'
            + (cur ? 'background:#1668c9;color:#ffffff;border:1px solid #1668c9;' : 'background:' + t[0] + ';color:' + t[1] + ';border:1px solid transparent;'),
          onClick: () => {
            if (h && !cur) this.resume(h.id);
            else { this.setState({ table: n, type: 'Dine in', overlay: null }); if (!cur) this.toast('Seated at table ' + n, 'info'); }
          }
        };
      }),

      discOpen: s.overlay === 'disc',
      discChoices: [0, 5, 10, 15, 20].map(pct => {
        const on = s.discPending ? s.discPending === pct : s.disc === pct;
        return {
          label: pct ? pct + '%' : 'None',
          onClick: () => this.pickDisc(pct),
          style: 'height:52px;border-radius:11px;display:flex;flex-direction:column;align-items:center;justify-content:center;font-size:14px;font-weight:700;cursor:pointer;'
            + (on ? 'background:#1668c9;color:#ffffff;' : pct > 10 ? 'border:1px dashed #e3c38a;color:#8a5300;background:#fffaf0;' : 'border:1px solid #dfe6ee;color:#0f1c2b;')
        };
      }),
      pinOpen: !!s.discPending,
      pinPrompt: s.discPending ? s.discPending + '% takes ' + this.money(r2((T.items - T.group) * s.discPending / 100)) + ' off. A manager needs to enter their PIN.' : '',
      pinDots: [0, 1, 2, 3].map(i => ({
        style: 'width:16px;height:16px;border-radius:50%;' + (i < s.pin.length ? 'background:#1668c9;' : 'box-shadow:inset 0 0 0 2px #c2cedb;')
      })),
      pinKeys: pad(PIN_KEYS, k => this.pinPress(k)),

      voidOpen: s.overlay === 'void',
      voidText: s.lines.length + (s.lines.length === 1 ? ' line' : ' lines') + ' worth ' + this.money(T.total) + ' will be removed. Nothing has been charged, and the void is logged against Aminul.',
      holdInstead: () => this.hold(),
      confirmVoid: () => { this.setState({ lines: [], sel: null, disc: 0, discBy: '', cust: 0, overlay: null }); this.toast('Order cleared', 'neutral'); },

      heldOpen: s.overlay === 'held',
      heldEmpty: !s.held.length,
      heldRows: s.held.map(h => {
        const ht = this.calc(h.lines, h.cust, h.type, h.disc);
        return {
          label: h.label, no: h.no,
          total: this.money(ht.total),
          meta: h.type + ' \u00b7 held at ' + h.time + ' \u00b7 ' + h.lines.length + (h.lines.length === 1 ? ' line' : ' lines'),
          items: h.lines.map(l => { const p = this.prod(l.pid); return this.qtyLabel(l.qty, p.unit) + (p.unit === 'kg' ? ' ' : '\u00d7 ') + p.name; }).join(', '),
          resume: () => this.resume(h.id),
          discard: () => { this.setState(st => ({ held: st.held.filter(x => x.id !== h.id) })); this.toast('Discarded ' + h.label, 'neutral'); }
        };
      }),

      recentOpen: s.overlay === 'recent',
      recentRows: s.recent.map(r => ({
        no: r.no, cust: r.cust, total: this.money(r.total),
        meta: r.time + ' \u00b7 ' + r.method + ' \u00b7 ' + r.items + (r.items === 1 ? ' line' : ' lines'),
        reprint: () => this.toast('Reprinting receipt #' + r.no, 'neutral')
      })),

      payOpen: s.overlay === 'pay',
      payWho: c.name + ' \u00b7 ' + s.type + (s.type === 'Dine in' ? ' \u00b7 Table ' + s.table : ''),
      tenders: [['Cash', 'Drawer 1'], ['Card', 'Tyro 01'], ['Split', '2+ tenders'], ['Account', c.id ? 'Charge ' + c.name.split(' ')[0] : 'Needs customer']].map(x => ({
        name: x[0], sub: x[1],
        onClick: () => this.setState({ tender: x[0], payInput: '' }),
        style: 'border-radius:12px;padding:12px 13px;cursor:pointer;min-height:62px;'
          + (s.tender === x[0] ? 'background:#1668c9;color:#ffffff;' : 'border:1px solid #dfe6ee;color:#0f1c2b;')
      })),
      tenderCash: s.tender === 'Cash', tenderCard: s.tender === 'Card', tenderSplit: s.tender === 'Split', tenderAccount: s.tender === 'Account',
      showPayKeypad: s.tender === 'Cash' || s.tender === 'Split',
      cashDisplay: s.payInput || '0.00',
      quickCash: quick.map((v, i) => ({
        label: i === 0 ? 'Exact' : String(v).replace(/\.0+$/, ''),
        onClick: () => this.setState({ payInput: String(v) }),
        style: 'height:44px;border-radius:10px;display:grid;place-items:center;' + MONO + 'font-size:13px;font-weight:600;cursor:pointer;'
          + (Math.abs(pv.tendered - v) < 0.001 ? 'background:#eaf1fb;border:1px solid #1668c9;color:#14549e;' : 'border:1px solid #dfe6ee;color:#0f1c2b;')
      })),
      payKeys: pad(KEYS, k => this.payPress(k)),
      splitRows: s.splits.map((x, i) => ({
        method: x.method, amt: this.money(x.amount), state: x.method === 'Card' ? 'Approved' : 'Received',
        remove: () => this.setState(st => ({ splits: st.splits.filter((_, j) => j !== i) }))
      })),
      splitMethods: ['Card', 'Cash', 'Voucher'].map(m => ({
        name: m,
        onClick: () => this.setState({ splitMethod: m }),
        style: 'flex:1;height:36px;border-radius:9px;display:grid;place-items:center;font-size:12.5px;cursor:pointer;'
          + (s.splitMethod === m ? 'background:#0f1c2b;color:#ffffff;font-weight:700;' : 'border:1px solid #dfe6ee;color:#4a5866;font-weight:500;')
      })),
      splitHint: pv.rem > 0.005 ? s.splitMethod + ' amount \u00b7 blank takes the rest' : 'Fully covered',
      splitDisplay: s.payInput || this.money(Math.max(0, pv.rem)),
      splitDisplayStyle: MONO + 'font-size:24px;font-weight:600;letter-spacing:-0.02em;' + (s.payInput ? 'color:#0f1c2b;' : 'color:#9aa7b6;'),
      addSplit: () => this.addSplit(),
      addSplitLabel: pv.rem > 0.005 ? 'Add ' + s.splitMethod.toLowerCase() + ' \u00b7 ' + this.money(Math.min(parseFloat(s.payInput) || pv.rem, pv.rem)) : 'All tenders taken',
      addSplitStyle: 'margin-top:10px;height:46px;border-radius:11px;display:grid;place-items:center;font-size:14px;font-weight:700;'
        + (pv.rem > 0.005 ? 'background:#0f1c2b;color:#ffffff;cursor:pointer;' : 'background:#e7f6ee;color:#0f6b43;pointer-events:none;'),

      card: {
        title: cardState.title, body: cardState.body, showSend: cardState.showSend, sendLabel: cardState.sendLabel,
        box: 'border-radius:14px;padding:28px 22px;text-align:center;background:' + ct[0] + ';color:' + ct[1] + ';border:1px solid ' + ct[0],
        icon: 'width:56px;height:56px;border-radius:14px;background:#ffffff;color:' + ct[1] + ';display:grid;place-items:center;' + MONO + 'font-size:12px;font-weight:700;margin:0 auto 14px;box-shadow:0 0 0 3px ' + ct[2]
      },
      sendCard: () => this.sendCard(false),
      sendCardDecline: () => this.sendCard(true),

      acctStats: [
        { k: 'Credit limit', v: c.limit ? this.money(c.limit) : '\u2014', tone: '' },
        { k: 'Owed now', v: c.limit ? this.money(c.balance) : '\u2014', tone: c.balance ? 'warn' : '' },
        { k: 'Available', v: c.limit ? this.money(Math.max(0, pv.avail)) : '\u2014', tone: pv.blocked === 'limit' ? 'error' : '' }
      ].map(a => ({ k: a.k, v: a.v, vStyle: MONO + 'font-size:17px;font-weight:600;' + (a.tone === 'warn' ? 'color:#8a5300;' : a.tone === 'error' ? 'color:#b02020;' : '') })),
      acct: {
        mark: acctMsg.mark, msg: acctMsg.msg, showPick: acctMsg.showPick,
        box: 'display:flex;gap:10px;align-items:flex-start;border-radius:11px;padding:12px 14px;font-size:12.5px;line-height:1.55;background:' + at[0] + ';color:' + at[1]
      },
      pickFromPay: () => this.setState({ overlay: 'cust', returnToPay: true, custQ: '' }),

      paySummary: [
        { k: 'Items', v: this.money(T.items), c: '' },
        T.group ? { k: c.group + ' ' + T.gp + '%', v: '\u2212' + this.money(T.group), c: 'neg' } : null,
        s.disc ? { k: 'Discount ' + s.disc + '%', v: '\u2212' + this.money(T.order), c: 'neg' } : null,
        T.fee ? { k: 'Delivery', v: this.money(T.fee), c: '' } : null,
        { k: 'GST included', v: this.money(T.gst), c: '' },
        s.tender === 'Cash' && Math.abs(pv.cashDue - T.total) > 0.001 ? { k: 'Cash rounding', v: (pv.cashDue > T.total ? '+' : '\u2212') + this.money(Math.abs(pv.cashDue - T.total)), c: '' } : null,
        s.tender === 'Split' && s.splits.length ? { k: 'Taken so far', v: this.money(pv.paidSplit), c: 'pos' } : null
      ].filter(Boolean).map(r => ({ k: r.k, v: r.v, vStyle: MONO + (r.c === 'neg' ? 'color:#ff9c9c;' : r.c === 'pos' ? 'color:#86dcaa;' : 'color:#ffffff;') })),
      dueLabel: s.tender === 'Cash' ? 'Cash due' : 'Total payable',
      dueValue: this.money(s.tender === 'Cash' ? pv.cashDue : T.total),
      changeLabel: s.tender === 'Cash' ? (pv.change < -0.001 ? 'Still to collect' : 'Change to give')
        : s.tender === 'Card' ? (s.card === 'approved' ? 'Card approved' : 'Awaiting terminal')
        : s.tender === 'Split' ? (pv.rem > 0.005 ? 'Remaining' : 'Covered') : (pv.ready ? 'On account' : 'Blocked'),
      changeValue: s.tender === 'Cash' ? this.money(Math.abs(pv.change))
        : s.tender === 'Card' ? this.money(T.total)
        : s.tender === 'Split' ? this.money(Math.max(0, pv.rem)) : this.money(T.total),
      changeBox: 'border-radius:12px;padding:12px 14px;margin-top:12px;'
        + (pv.ready ? 'background:#2f9e63;color:#ffffff;' : (s.tender === 'Account' && pv.blocked) ? 'background:rgba(217,58,58,.22);color:#ffb3b3;' : 'background:rgba(217,140,26,.2);color:#ffd08a;'),
      completeLabel: payReadyLabel,
      completeStyle: 'height:52px;border-radius:12px;display:grid;place-items:center;font-size:15px;font-weight:700;'
        + (pv.ready ? 'background:#4da3e8;color:#06283a;cursor:pointer;' : 'background:rgba(255,255,255,.1);color:#6f8599;cursor:not-allowed;pointer-events:none;'),
      complete: () => this.complete(),

      doneOpen: s.overlay === 'done' && !!d,
      done: d ? {
        no: d.no,
        line: d.method + ' \u00b7 ' + this.money(d.total) + ' \u00b7 ' + d.cust.name + ' \u00b7 ' + d.type + (d.type === 'Dine in' ? ' T' + d.table : ''),
        showChange: d.method === 'Cash' && d.change > 0.001,
        change: this.money(d.change),
        head: 'Tax invoice \u00b7 ' + d.time + ' \u00b7 Register 1',
        lines: d.lines.map(x => ({ ...x, noteStyle: x.note ? 'font-size:11px;color:#b45309;font-style:italic' : 'display:none' })),
        rows: [
          ['Items', this.money(d.items)],
          d.group ? [d.cust.group + ' ' + d.gp + '%', '\u2212' + this.money(d.group)] : null,
          d.order ? ['Discount ' + d.disc + '%', '\u2212' + this.money(d.order)] : null,
          d.fee ? ['Delivery', this.money(d.fee)] : null,
          d.rounding ? ['Rounding', (d.rounding > 0 ? '+' : '\u2212') + this.money(Math.abs(d.rounding))] : null,
          ['Total', this.money(d.total), true],
          ['GST included', this.money(d.gst)],
          [d.method === 'Cash' ? 'Cash tendered' : 'Paid by ' + d.method, this.money(d.tendered)]
        ].filter(Boolean).map(r => ({ k: r[0], v: r[1],
          style: 'display:flex;gap:10px;' + (r[2] ? 'font-size:14px;font-weight:700;padding-top:6px;border-top:1.5px solid #0f1c2b;margin-top:4px;' : 'font-size:12px;color:#5d6b7d;') })),
        kitchen: d.kitchen
      } : { no: '', line: '', showChange: false, change: '', head: '', lines: [], rows: [], kitchen: false },
      printReceipt: () => this.toast('Receipt sent to printer', 'neutral'),
      emailReceipt: () => d && d.cust.email ? this.toast('Receipt emailed to ' + d.cust.email, 'success') : this.toast('No email on file for walk-in customers', 'warn'),
      newSale: () => this.newSale()
    };
  }
}
});
