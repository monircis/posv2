// POS terminal
import { DCLogic, createRef } from "../runtime/logic.js";
import { goRoute, goView } from "../runtime/nav.js";
import { api } from "../../api/http.js";
import { can, currentUser } from "../../api/auth.js";

// Stands in for a product that is not in the list, e.g. before the list has loaded.
const NO_PRODUCT = {id:'', name:'', sku:'', barcode:'', category_id:null, price:0, tax_rate:0, unit:'ea', stock:null, alert_qty:0, kitchen:false, normal_price:0, price_rule:null};
// A sale with no customer.
const WALK_IN = {id:0, name:'Walk in', group:'Retail', discount:0, phone:'', balance:0, limit:0, coins:0, address:''};

const KEYS = ['7','8','9','4','5','6','1','2','3','.','0','\u232b'];
const TONES = {
  info:['var(--c-primary-soft)','var(--c-primary-text)','var(--c-primary)'], success:['#e7f6ee','#0f6b43','#2f9e63'],
  warn:['#fdf1dd','#8a5300','#d98c1a'], error:['#fdeaea','#b02020','#d93a3a'], neutral:['#eef2f6','#5d6b7d','#8fa3b6']
};
const MONO = "font-family:'IBM Plex Mono',monospace;";
const r2 = n => Math.round(n * 100) / 100;
const rq = n => Math.round(n * 1000) / 1000;

export class PosLogic extends DCLogic {
  _id = 100;
  searchRef = createRef();

  state = {
    cat: 'all', q: '',
    // From GET /api/pos/catalog
    products: [], categories: [], customers: [], branch: null, branches: [], stock: {},
    orderNo: '', maxDiscount: 0, deliveryFee: 0,
    // From Settings: cash rounding, split payments, card surcharge, scale keypad
    roundCash: true, allowSplit: true, cardSurcharge: 0, useScale: true, searchKeypad: true,
    // Settings > Loyalty coins; useCoins: the cashier pressed "Use coins" on the pay screen
    coins: { enabled: false, earn_per_dollar: 0, coin_value: 0, min_redeem: 0, max_redeem_percent: 0 }, useCoins: false,
    catalogLoading: true, catalogError: '',
    lines: [],
    sel: null, flash: null,
    type: 'Takeaway', table: 4, cust: 0, disc: 0, discFix: 0, discBy: '', discMode: 'pct', discInput: '',
    held: [],
    // From GET /api/sales: today's POS sales at this branch
    recent: [], todayCount: 0, todayTotal: 0,
    toasts: [], overlay: null,
    kp: {mode:'add', pid:'', lineId:null, val:''},
    custQ: '',
    tender: 'Cash', payInput: '', card: 'idle', splits: [], splitMethod: 'Card',
    saving: false, payError: '',
    // Tip: kept apart from the sale and paid out to the staff member later (HRM > Tips)
    staff: [], tip: '', tipMethod: 'Cash', tipStaff: 0,
    done: null, clock: '14:38'
  };

  componentDidMount() {
    this.tick();
    this._clock = setInterval(() => this.tick(), 15000);
    this._key = e => this.onKey(e);
    window.addEventListener('keydown', this._key);
    this.loadCatalog();
  }

  // Products, categories and customers of one branch. Without a branch the API picks the user's own or the default.
  async loadCatalog(branchId) {
    const request = this._catalogRequest = (this._catalogRequest || 0) + 1;
    this.setState({ catalogLoading: !this.state.products.length || !!branchId, catalogError: '' });
    try {
      const data = await api.get('/pos/catalog' + (branchId ? '?branch_id=' + branchId : ''));
      if (request !== this._catalogRequest) return; // a newer request is on its way
      const stock = {};
      data.products.forEach(p => { stock[p.id] = p.stock; });
      // A cashier from another branch signed in: the order on screen used the old branch's prices and stock.
      const prev = this.state.branch;
      if (prev && data.branch && prev.id !== data.branch.id && (this.state.lines.length || this.state.held.length)) {
        this.setState({ lines: [], held: [], sel: null, cust: 0, disc: 0, discFix: 0, discBy: '' });
        this.toast('Now at ' + data.branch.name + ': the order on screen was cleared', 'warn');
      }
      this.setState(s => ({
        products: data.products, categories: data.categories, customers: data.customers,
        branch: data.branch, branches: data.branches, stock,
        orderNo: data.next_ref, maxDiscount: data.max_discount, deliveryFee: data.delivery_fee,
        roundCash: data.payments.round_cash_5c, allowSplit: data.payments.allow_split_payments,
        cardSurcharge: data.payments.card_surcharge, useScale: data.use_scale, searchKeypad: data.search_keypad !== false, requireRegister: !!data.require_register, coins: data.coins, staff: data.staff || [], custGroups: data.customer_groups || [],
        cat: data.categories.some(c => c.id === s.cat) ? s.cat : 'all',
        catalogLoading: false
      }));
      this.loadToday();
    } catch (e) {
      if (request !== this._catalogRequest) return;
      this.setState({ catalogLoading: false, catalogError: e.message || 'Could not load the products.' });
    }
  }

  // Today's POS sales at this branch, for the top bar and Recent sales.
  async loadToday() {
    const s = this.state;
    if (!s.branch) return;
    const now = new Date();
    const start = new Date(now.getFullYear(), now.getMonth(), now.getDate()).toISOString();
    try {
      const res = await api.get('/sales?channel=POS&branch_id=' + s.branch.id + '&from=' + encodeURIComponent(start));
      this.setState({ recent: res.data, todayCount: res.sales_count, todayTotal: res.total_amount });
    } catch (e) { /* the top bar just keeps its last numbers */ }
  }

  // Stock and prices are per branch, so an open order cannot move to another branch.
  switchBranch(e) {
    const s = this.state;
    const id = Number(e.target.value);
    if ((s.lines.length || s.held.length) && !window.confirm('Switching branch clears the current order and held orders. Continue?')) {
      e.target.value = s.branch.id;
      return;
    }
    this.setState({ lines: [], held: [], sel: null, q: '', cust: 0, disc: 0, discFix: 0, discBy: '' });
    this.loadCatalog(id);
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
  prod(id) { return this.state.products.find(p => p.id === id) || NO_PRODUCT; }
  cust(id) { return this.state.customers.find(c => c.id === id) || WALK_IN; }
  userName() { return currentUser.value ? currentUser.value.name : ''; }
  // A discount over the POS limit needs a user who can edit sales.
  canDiscount(percent) { return percent <= this.state.maxDiscount || can('sell.edit'); }
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

  // The same sums as totals() in SaleController, so the screen and the server get the same total.
  calc(lines, custId, type, disc, useCoins, discFix) {
    const c = this.cust(custId);
    const gp = c.discount;
    let items = 0, taxPart = 0;
    lines.forEach(l => {
      const p = this.prod(l.pid);
      const lineTotal = r2(p.price * l.qty * (1 - (l.disc || 0) / 100));
      items += lineTotal;
      taxPart += lineTotal * p.tax_rate / (100 + p.tax_rate);
    });
    items = r2(items);
    const group = r2(items * gp / 100);
    // The order discount: a percentage, or a fixed amount that is never more than the goods.
    const order = discFix ? r2(Math.min(discFix, items - group)) : r2((items - group) * disc / 100);
    const fee = type === 'Delivery' && lines.length ? this.state.deliveryFee : 0;
    const cs = this.state.coins;
    // Coins: as many as allowed, up to the customer's balance (worked out again by the server)
    const most = cs.enabled && cs.coin_value > 0 ? Math.floor((items - group - order) * cs.max_redeem_percent / 100 / cs.coin_value + 0.000001) : 0;
    const coins = useCoins && c.id && c.coins >= cs.min_redeem ? Math.max(0, Math.min(c.coins, most)) : 0;
    const coinDisc = r2(coins * cs.coin_value);
    const goods = r2(items - group - order - coinDisc);
    const total = r2(goods + fee);
    const share = items > 0 ? goods / items : 0;
    const earn = cs.enabled && c.id ? Math.floor(Math.max(0, goods) * cs.earn_per_dollar + 0.000001) : 0;
    return { c, gp, items, group, order, fee, coins, coinDisc, earn, total, gst: r2(taxPart * share + fee / 11) };
  }

  filtered() {
    const s = this.state;
    const q = s.q.trim().toLowerCase();
    if (q) return s.products.filter(p => p.name.toLowerCase().indexOf(q) > -1 || this.codeStarts(p.sku, q) || this.codeStarts(p.barcode, q));
    if (s.cat === 'all') return s.products;
    return s.products.filter(p => p.category_id === s.cat);
  }

  codeStarts(code, q) { return String(code || '').toLowerCase().indexOf(q) === 0; }
  codeIs(code, q) { return String(code || '').toLowerCase() === q; }

  focusSearch() { const el = this.searchRef.current; if (el) el.focus(); }

  tap(p) {
    if (this.avail(p.id) <= 0) { this.toast(p.name + ' is out of stock', 'error'); return; }
    if (p.unit === 'kg' && this.state.useScale) this.setState({ overlay:'kp', kp:{mode:'add', pid:p.id, lineId:null, val:''} });
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
    this.setState({ cust: id, useCoins: false, overlay: wasPay ? 'pay' : null, custQ: '', returnToPay: false, tender: wasPay ? 'Account' : this.state.tender });
    if (c.discount) this.toast(c.group + ' pricing applied \u00b7 ' + c.discount + '% off', 'success');
    else if (id !== 0) this.toast(c.name + ' added to order', 'info');
  }

  setType(t) {
    this.setState({ type: t });
    if (t === 'Delivery' && this.state.cust === 0) this.toast('Delivery needs a customer with an address', 'warn');
  }

  pickDisc(p) {
    if (!this.canDiscount(p)) {
      this.toast('A discount over ' + this.state.maxDiscount + '% needs a manager to sign in', 'error');
      return;
    }
    this.setState({ disc: p, discFix: 0, discBy: p > this.state.maxDiscount ? this.userName() : '', overlay: null });
    this.toast(p ? p + '% order discount applied' : 'Order discount removed', p ? 'success' : 'neutral');
  }

  // A fixed amount off the order. It counts against the POS limit by the percentage it works out to.
  pickFix() {
    const s = this.state;
    const amount = r2(parseFloat(s.discInput) || 0);
    if (amount <= 0) { this.toast('Type the amount to take off', 'warn'); return; }
    const T = this.calc(s.lines, s.cust, s.type, 0, false, 0);
    const goods = r2(T.items - T.group);
    if (amount > goods) { this.toast('The discount cannot be more than the order (' + this.money(goods) + ')', 'error'); return; }
    const pct = goods > 0 ? amount / goods * 100 : 0;
    if (!this.canDiscount(pct)) {
      this.toast('That is ' + Math.round(pct * 10) / 10 + '% off. Over ' + s.maxDiscount + '% needs a manager to sign in', 'error');
      return;
    }
    this.setState({ disc: 0, discFix: amount, discBy: pct > s.maxDiscount ? this.userName() : '', overlay: null, discInput: '' });
    this.toast(this.money(amount) + ' order discount applied', 'success');
  }

  holdLabel(s) {
    const c = this.cust(s.cust);
    if (s.type === 'Dine in') return 'Table ' + s.table;
    if (c.id !== 0) return c.name;
    return s.type + ' #' + s.orderNo;
  }

  snapshot(s) {
    return { id: 'h' + (++this._id), no: s.orderNo, label: this.holdLabel(s), type: s.type, table: s.table, cust: s.cust,
      lines: s.lines, disc: s.disc, discFix: s.discFix, discBy: s.discBy, time: s.clock };
  }

  hold() {
    const s = this.state;
    if (!s.lines.length) { this.toast('Nothing to hold \u2014 the order is empty', 'warn'); return; }
    const h = this.snapshot(s);
    this.setState(st => ({ held: [h].concat(st.held), lines: [], sel: null, type: 'Takeaway', disc: 0, discFix: 0, discBy: '', cust: 0, overlay: null }));
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
    this.setState({ held, lines: h.lines, type: h.type, table: h.table, cust: h.cust, disc: h.disc, discFix: h.discFix || 0, discBy: h.discBy, sel: null, overlay: null });
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
    this.setState({ overlay: 'pay', tender: 'Cash', payInput: '', card: 'idle', splits: [], splitMethod: 'Card', payError: '', useCoins: false, tip: '', tipMethod: 'Cash' });
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

  // The POS is not connected to the card terminal: the cashier confirms what the terminal shows.
  confirmCard() {
    this.setState({ card: 'approved' });
  }

  payState() {
    const s = this.state;
    const T = this.calc(s.lines, s.cust, s.type, s.disc, s.useCoins, s.discFix);
    const cashDue = s.roundCash ? Math.round(T.total * 20) / 20 : T.total;
    const tendered = parseFloat(s.payInput) || 0;
    const paidSplit = r2(s.splits.reduce((a, x) => a + x.amount, 0));
    const rem = r2(T.total - paidSplit);
    const c = T.c;
    const avail = r2(c.limit - c.balance);
    let ready = false, blocked = '';
    // The tip is on top of the sale. Paid in cash with a cash sale, it is part of the cash handed over;
    // paid by card with a card sale, it goes on the terminal with the sale.
    const tip = Math.max(0, r2(parseFloat(s.tip) || 0));
    const cashTip = s.tender === 'Cash' && s.tipMethod === 'Cash' ? tip : 0;
    const cardTip = s.tender === 'Card' && s.tipMethod === 'Card' ? tip : 0;
    const tipProblem = tip > 0 && !s.tipStaff ? 'Choose who the tip is for' : '';
    if (s.tender === 'Cash') ready = tendered >= cashDue + cashTip - 0.001;
    else if (s.tender === 'Card') ready = s.card === 'approved';
    else if (s.tender === 'Split') {
      // What is not paid can stay on the customer's account.
      const canOwe = c.id !== 0 && c.limit > 0 && rem <= avail;
      ready = s.splits.length > 0 && (rem <= 0.005 || canOwe);
    } else {
      if (c.id === 0) blocked = 'walkin';
      else if (c.limit <= 0) blocked = 'nocredit';
      else if (T.total > avail) blocked = 'limit';
      ready = !blocked;
    }
    if (!s.lines.length || (T.total <= 0 && !T.coinDisc)) ready = false;
    if (tipProblem) ready = false;
    // The surcharge goes on top of what is paid by card.
    const cardPart = s.tender === 'Card' ? T.total : s.tender === 'Split' ? s.splits.filter(x => x.method === 'Card').reduce((a, x) => a + x.amount, 0) : 0;
    const surcharge = r2(cardPart * s.cardSurcharge / 100);
    return { T, cashDue, tendered, change: r2(tendered - cashDue - cashTip), paidSplit, rem, avail, ready, blocked, surcharge, tip, cashTip, cardTip, tipProblem };
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

  // What was paid, for the API: cash, card, or both for a split. On account pays nothing now.
  salePayments(pv) {
    const s = this.state;
    if (pv.T.total <= 0) return [];
    if (s.tender === 'Cash') return [{ method: 'cash', amount: pv.cashDue }];
    if (s.tender === 'Card') return [{ method: 'card', amount: pv.T.total }];
    if (s.tender === 'Split') return s.splits.map(x => ({ method: x.method.toLowerCase(), amount: x.amount }));
    return [];
  }

  // Save the sale. It is only finished when the server says so; if it refuses, the order stays on screen.
  async complete() {
    const s = this.state;
    const pv = this.payState();
    if (!pv.ready || s.saving) return;

    this.setState({ saving: true, payError: '' });
    try {
      const sale = await api.post('/sales', {
        branch_id: s.branch.id,
        contact_id: s.cust || null,
        order_type: s.type,
        table_no: s.type === 'Dine in' ? s.table : null,
        discount_percent: s.disc,
        discount_type: s.discFix ? 'amount' : 'percent',
        discount_amount: s.discFix || null,
        coins_used: pv.T.coins,
        total: pv.T.total,
        tendered: s.tender === 'Cash' ? pv.tendered : null,
        lines: s.lines.map(l => {
          const p = this.prod(l.pid);
          return { product_id: p.product_id, variant_id: p.variant_id, qty: l.qty, discount_percent: l.disc, note: l.note };
        }),
        payments: this.salePayments(pv),
        tip: pv.tip || null,
        tip_method: pv.tip ? s.tipMethod.toLowerCase() : null,
        tip_employee_id: pv.tip ? s.tipStaff : null
      });
      this.setState({
        lines: [], sel: null, type: 'Takeaway', disc: 0, discFix: 0, discBy: '', cust: 0, useCoins: false, tip: '',
        orderNo: sale.next_ref, overlay: 'done', done: sale, saving: false
      });
      this.loadCatalog(); // the new stock, customer balances and today's sales
    } catch (e) {
      this.setState({ saving: false, payError: e.message || 'The sale could not be saved. Nothing was charged here; try again.' });
    }
  }

  newSale() { this.setState({ overlay: null, done: null }); this.later(() => this.focusSearch(), 50); }

  closeOverlay() {
    const o = this.state.overlay;
    if (o === 'done') { this.newSale(); return; }
    if (o === 'cust' && this.state.returnToPay) { this.setState({ overlay: 'pay', returnToPay: false, custQ: '' }); return; }
    this.setState({ overlay: null, custQ: '' });
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
    if (s.overlay === 'done' && k === 'Enter') { e.preventDefault(); this.newSale(); return; }
    if (!s.overlay && digit) this.focusSearch();
  }

  onSearchKey(e) {
    if (e.key === 'Enter') {
      const q = this.state.q.trim();
      if (!q) return;
      const code = q.toLowerCase();
      const exact = this.state.products.find(p => this.codeIs(p.sku, code) || this.codeIs(p.barcode, code));
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
    const T = this.calc(s.lines, s.cust, s.type, s.disc, s.useCoins, s.discFix);
    const coinMax = this.calc(s.lines, s.cust, s.type, s.disc, true, s.discFix); // what "Use coins" would take off
    const list = this.filtered();
    const qTrim = s.q.trim();
    const allCats = [{ id: 'all', name: 'All items', code: 'All', count: s.products.length }].concat(s.categories);
    const catObj = allCats.find(c => c.id === s.cat) || allCats[0];
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

    const ctx = s.type === 'Dine in'
      ? { kicker:'Table', label:'Table ' + s.table, sub:'Tap to change', onClick: () => this.setState({ overlay:'table' }),
          style:'border:1px solid #dfe6ee;border-radius:10px;padding:8px 11px;cursor:pointer;min-width:0;background:#ffffff;' }
      : s.type === 'Takeaway'
      ? { kicker:'Pickup', label:'Order #' + s.orderNo, sub:'Ready in about 15 min', onClick: () => {},
          style:'border:1px solid #dfe6ee;border-radius:10px;padding:8px 11px;min-width:0;background:#f6f9fc;' }
      : deliveryBlocked
      ? { kicker:'Deliver to', label:'No address yet', sub:'Pick a customer', onClick: () => this.setState({ overlay:'cust' }),
          style:'border:1px solid #f0d9ac;border-radius:10px;padding:8px 11px;cursor:pointer;min-width:0;background:#fdf1dd;color:#8a5300;' }
      : { kicker:'Deliver to', label:c.name, sub:(c.address || 'No address saved') + ' \u00b7 +' + this.money(s.deliveryFee), onClick: () => this.setState({ overlay:'cust' }),
          style:'border:1px solid #dfe6ee;border-radius:10px;padding:8px 11px;cursor:pointer;min-width:0;background:#ffffff;' };

    const cq = s.custQ.trim().toLowerCase();
    const custList = [WALK_IN].concat(s.customers).filter(x => !cq || x.name.toLowerCase().indexOf(cq) > -1 || (x.phone && x.phone.replace(/\s/g, '').indexOf(cq.replace(/\s/g, '')) > -1));

    const cashNeed = r2(pv.cashDue + pv.cashTip);
    const quick = [cashNeed, Math.ceil(cashNeed / 5) * 5, Math.ceil(cashNeed / 10) * 10, Math.ceil(cashNeed / 20) * 20, Math.ceil(cashNeed / 50) * 50, Math.ceil(cashNeed / 100) * 100]
      .map(r2).filter((v, i, a) => a.indexOf(v) === i).slice(0, 5);

    const cardState = {
      idle: { title:'Charge the card terminal', body:'Enter ' + this.money(T.total + pv.surcharge + pv.cardTip) + (pv.cardTip ? ' (with the ' + this.money(pv.cardTip) + ' tip)' : '') + ' on the card terminal. When the terminal shows it is approved, press the button below.', tone:'info', showSend:true, sendLabel:'Card approved' },
      approved: { title:'Card approved', body:'Press Complete sale to save it.', tone:'success', showSend:false, sendLabel:'' }
    }[s.card];
    const ct = TONES[cardState.tone];

    const acctMsg = pv.blocked === 'walkin' ? { mark:'!', msg:'Walk-in customers have no credit account. Choose a customer to charge this to their account.', tone:'warn', showPick:true }
      : pv.blocked === 'nocredit' ? { mark:'!', msg:c.name + ' is a retail customer without credit terms. Take another tender.', tone:'warn', showPick:false }
      : pv.blocked === 'limit' ? { mark:'\u00d7', msg:'This sale would take ' + c.name + ' ' + this.money(T.total - pv.avail) + ' over their ' + this.money(c.limit) + ' limit. Take part payment first, or ask a manager to raise the limit.', tone:'error', showPick:false }
      : { mark:'\u2713', msg:'Charges ' + this.money(T.total) + ' to ' + c.name + '. The sale shows as Due until it is paid.', tone:'success', showPick:false };
    const at = TONES[acctMsg.tone];

    const payReadyLabel = s.saving ? 'Saving\u2026' : pv.tipProblem ? pv.tipProblem : pv.ready
      ? (s.tender === 'Cash' ? 'Complete \u00b7 change ' + this.money(Math.max(0, pv.change))
        : s.tender === 'Split' && pv.rem > 0.005 ? 'Complete \u00b7 ' + this.money(pv.rem) + ' on account' : 'Complete sale')
      : s.tender === 'Cash' ? 'Enter cash tendered'
      : s.tender === 'Card' ? 'Confirm the card first'
      : s.tender === 'Split' ? 'Still ' + this.money(pv.rem) + ' to take'
      : 'Can\u2019t charge to account';

    const d = s.done;

    return {
      hasExit: true,
      exit: () => { goView('sell'); },
      sessLine: s.todayCount + ' sales \u00b7 ' + this.money(s.todayTotal),
      userName: this.userName(),
      userInitials: this.userName().split(' ').map(w => w[0]).join('').slice(0, 2).toUpperCase(),
      clock: s.clock,
      heldCount: s.held.length,
      heldBadgeStyle: MONO + 'font-size:11px;font-weight:600;border-radius:99px;min-width:20px;height:20px;padding:0 6px;display:grid;place-items:center;'
        + (s.held.length ? 'background:#d98c1a;color:#1f1300;' : 'background:rgba(255,255,255,.14);color:#9fb2c4;'),
      openHeld: () => this.setState({ overlay: 'held' }),
      openRecent: () => this.setState({ overlay: 'recent' }),

      branchName: s.branch ? s.branch.name : '',
      branchId: s.branch ? s.branch.id : '',
      requireRegister: !!s.requireRegister,
      catalogReady: !s.catalogLoading && !!s.branch,
      branches: s.branches,
      canPickBranch: s.branches.length > 1,
      onBranch: e => this.switchBranch(e),
      catalogNote: s.catalogLoading ? 'Loading products…'
        : s.catalogError ? s.catalogError
        : !s.products.length ? 'No products to sell at this branch yet.' : '',
      catalogFailed: !!s.catalogError,
      retryCatalog: () => this.loadCatalog(s.branch ? s.branch.id : undefined),

      cats: allCats.map(cat => {
        const on = !qTrim && s.cat === cat.id;
        return {
          code: cat.code, name: cat.name, count: cat.count,
          onClick: () => this.setState({ cat: cat.id, q: '' }),
          style: 'min-height:78px;border-radius:12px;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:4px;cursor:pointer;padding:6px 4px;flex:none;'
            + (on ? 'background:#0f1c2b;color:#ffffff;' : 'color:#4a5866;'),
          codeStyle: 'width:30px;height:30px;border-radius:8px;display:grid;place-items:center;' + MONO + 'font-size:10.5px;font-weight:600;'
            + (on ? 'background:var(--c-accent);color:#06283a;' : 'background:#eef2f6;color:#5d6b7d;')
        };
      }),

      searchRef: this.searchRef,
      q: s.q,
      hasQuery: !!s.q,
      searchKeypad: s.searchKeypad,
      onSearch: e => this.setState({ q: e.target.value }),
      onSearchKey: e => this.onSearchKey(e),
      clearSearch: () => { this.setState({ q: '' }); this.focusSearch(); },
      gridTitle: qTrim ? 'Results for \u201c' + qTrim + '\u201d' : catObj.name,
      gridMeta: qTrim
        ? (list.length ? list.length + (list.length === 1 ? ' match \u00b7 Enter adds it' : ' matches \u00b7 Enter on an exact SKU adds it') : 'No matches')
        : list.length + ' products \u00b7 cuts by weight open the scale keypad',
      noResults: !!qTrim && !list.length && !s.catalogLoading && !s.catalogError && s.products.length > 0,
      gridStyle: 'display:grid;gap:10px;grid-template-columns:repeat(auto-fill,minmax(' + (compact ? 132 : 158) + 'px,1fr))',
      products: list.map(p => {
        const st = s.stock[p.id];
        const av = this.avail(p.id);
        const out = av <= 0;
        const low = st !== null && !out && av <= p.alert_qty;
        const n = inCartQty(p.id);
        return {
          name: p.name, price: this.money(p.price), per: p.unit === 'ea' ? 'each' : '/ ' + p.unit,
          // Today's price rule (Settings > Price rules), e.g. "Eid week +10% · was $20.00"
          rule: p.price_rule ? p.price_rule + ' · was ' + this.money(p.normal_price) : '',
          ruleStyle: p.price_rule ? 'font-size:10.5px;font-weight:600;margin-top:5px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;color:' + (p.price > p.normal_price ? '#8a5300' : '#0f6b43') : 'display:none',
          tag: (p.kitchen ? 'Kitchen \u00b7 ' : 'SKU ') + p.sku,
          inCart: p.unit === 'kg' ? this.trimQ(n) + ' kg' : '\u00d7' + n,
          inCartStyle: n ? 'margin-left:auto;height:22px;border-radius:99px;background:var(--c-primary);color:#ffffff;' + MONO + 'font-size:10.5px;font-weight:600;display:grid;place-items:center;padding:0 8px;flex:none;' : 'display:none',
          stock: st === null ? '' : out ? 'Out' : (p.unit === 'kg' ? this.trimQ(av) + ' kg' : av + ' left'),
          stockStyle: st === null ? 'display:none' : 'margin-left:auto;' + MONO + 'font-size:10.5px;font-weight:600;border-radius:5px;padding:2px 6px;flex:none;'
            + (out ? 'background:#fdeaea;color:#b02020;' : low ? 'background:#fdf1dd;color:#8a5300;' : 'background:#eef2f6;color:#5d6b7d;'),
          nameStyle: 'font-size:' + (compact ? 12.5 : 13.5) + 'px;font-weight:600;line-height:1.3;flex:1;text-wrap:pretty',
          style: 'background:#ffffff;border:1px solid #dfe6ee;border-radius:12px;padding:11px 12px 12px;cursor:pointer;display:flex;flex-direction:column;user-select:none;transition:border-color .12s,box-shadow .12s;'
            + 'min-height:' + (compact ? 98 : 122) + 'px;'
            + (n ? 'border-color:var(--c-chart);box-shadow:inset 0 0 0 1px var(--c-chart);' : '')
            + (out ? 'opacity:.5;cursor:not-allowed;' : ''),
          // For the list view on the POS screen.
          hasCart: n > 0, out,
          onClick: () => this.tap(p)
        };
      }),

      toasts: s.toasts.map(t => ({ msg: t.msg, dot: 'width:8px;height:8px;border-radius:50%;flex:none;background:' + TONES[t.tone][2] })),

      orderNo: s.orderNo,
      orderMeta: s.lines.length ? 'started ' + s.clock + ' \u00b7 ' + this.userName() : 'new order',
      types: ['Dine in', 'Takeaway', 'Delivery'].map(t => ({
        name: t,
        onClick: () => this.setType(t),
        style: 'flex:1;height:34px;border-radius:8px;display:grid;place-items:center;font-size:12.5px;cursor:pointer;'
          + (s.type === t ? 'background:#ffffff;color:#0f1c2b;font-weight:700;box-shadow:0 1px 3px rgba(15,28,43,.14);' : 'color:#5d6b7d;font-weight:500;')
      })),
      ctx,
      custChip: {
        name: c.name,
        sub: c.id === 0 ? 'No account \u00b7 tap to pick' : c.group + (T.gp ? ' \u00b7 ' + T.gp + '% off' : '') + (c.limit ? ' \u00b7 owes ' + this.money(c.balance) : ''),
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
          meta: this.money(p.price) + (p.unit === 'ea' ? ' each' : ' / ' + p.unit) + (p.price_rule ? ' · ' + p.price_rule : ''),
          qty: this.qtyLabel(l.qty, p.unit),
          total: this.money(net),
          orig: this.money(gross),
          origStyle: l.disc ? MONO + 'font-size:10.5px;color:#9aa7b6;text-decoration:line-through' : 'display:none',
          discTag: '\u2212' + l.disc + '%',
          discTagStyle: l.disc ? MONO + 'font-size:10.5px;font-weight:600;background:#fdeaea;color:#b02020;border-radius:4px;padding:1px 5px' : 'display:none',
          note: l.note,
          noteStyle: l.note ? 'font-size:11.5px;color:#b45309;margin-top:3px;font-style:italic;white-space:nowrap;overflow:hidden;text-overflow:ellipsis' : 'display:none',
          kitchenStyle: p.kitchen ? MONO + 'font-size:9px;letter-spacing:.06em;text-transform:uppercase;background:var(--c-primary-soft);color:var(--c-primary-text);border-radius:4px;padding:2px 5px;flex:none' : 'display:none',
          selected: sel,
          rowStyle: 'padding:11px 16px;transition:background .35s ease;'
            + (i < s.lines.length - 1 ? 'border-bottom:1px solid #f0f4f8;' : 'border-bottom:1px solid #f0f4f8;')
            + (sel ? 'background:#f4f8fd;box-shadow:inset 3px 0 0 var(--c-primary);' : s.flash === l.id ? 'background:#e7f6ee;' : ''),
          select: () => this.setState({ sel: sel ? null : l.id }),
          inc: () => this.bump(l.id, 1),
          dec: () => this.bump(l.id, -1),
          edit: () => this.setState({ overlay: 'kp', kp: { mode: 'edit', pid: p.id, lineId: l.id, val: this.trimQ(l.qty) } }),
          remove: () => this.removeLine(l.id),
          onNote: e => { const v = e.target.value; this.setState(st => ({ lines: st.lines.map(x => x.id === l.id ? { ...x, note: v } : x) })); },
          discChips: [0, 5, 10, 15, 20].filter(dv => this.canDiscount(dv)).map(dv => ({
            label: dv ? dv + '%' : 'None',
            onClick: () => this.setState(st => ({ lines: st.lines.map(x => x.id === l.id ? { ...x, disc: dv } : x) })),
            style: 'height:30px;padding:0 11px;border-radius:8px;display:grid;place-items:center;font-size:12px;cursor:pointer;'
              + (l.disc === dv ? 'background:var(--c-primary);color:#ffffff;font-weight:700;' : 'border:1px solid #dfe6ee;background:#ffffff;color:#4a5866;font-weight:500;')
          }))
        };
      }),

      itemCount: s.lines.length,
      itemsTotal: this.money(T.items),
      showGroup: T.group > 0,
      groupLabel: c.group + ' pricing ' + T.gp + '%',
      groupAmt: this.money(T.group),
      discLabel: s.disc || s.discFix ? 'Order discount ' + (s.discFix ? this.money(s.discFix) : s.disc + '%') + (s.discBy ? ' \u00b7 approved by ' + s.discBy.split(' ')[0] : '') : 'Order discount',
      discAmt: s.disc || s.discFix ? '\u2212' + this.money(T.order) : 'Add',
      discAmtStyle: s.disc || s.discFix ? MONO + 'font-size:13px;color:#b02020' : 'font-size:12.5px;font-weight:600;color:var(--c-primary)',
      showFee: T.fee > 0,
      feeAmt: this.money(T.fee),
      gst: this.money(T.gst),
      total: this.money(T.total),
      discHint: s.discFix ? this.money(s.discFix) + ' off' : s.disc ? s.disc + '% on' : 'order',
      lineCountHint: s.lines.length ? s.lines.length + (s.lines.length === 1 ? ' line' : ' lines') : 'empty',
      hold: () => this.hold(),
      openDisc: () => { if (!s.lines.length) { this.toast('Add items before discounting', 'warn'); return; } this.setState({ overlay: 'disc', discMode: s.discFix ? 'amt' : 'pct', discInput: s.discFix ? String(s.discFix) : '' }); },
      openVoid: () => { if (!s.lines.length) { this.toast('The order is already empty', 'neutral'); return; } this.setState({ overlay: 'void' }); },
      openPay: () => this.openPay(),
      payBtnLabel: cartEmpty ? 'Pay' : deliveryBlocked ? 'Pick a delivery customer' : 'Pay ' + this.money(T.total),
      payBtnStyle: 'height:58px;border-radius:13px;display:flex;align-items:center;justify-content:center;gap:12px;color:#ffffff;'
        + (cartEmpty ? 'background:#b7c6d6;cursor:not-allowed;' : deliveryBlocked ? 'background:#b45309;cursor:pointer;' : 'background:var(--c-primary);cursor:pointer;box-shadow:0 10px 22px -12px rgba(22,104,201,.9);'),

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
          + (kpVal && !kpOver ? 'background:var(--c-primary);cursor:pointer;' : 'background:#b7c6d6;cursor:not-allowed;pointer-events:none;')
      },
      kpKeys: pad(KEYS, k => this.kpPress(k)),
      kpConfirm: () => this.kpConfirm(),

      custOpen: s.overlay === 'cust',
      custGroups: s.custGroups || [],
      // A customer just added on the POS: put them in the list and pick them (group pricing applies at once).
      addCustomer: row => { this.setState(st => ({ customers: st.customers.concat([row]).sort((a, b) => a.name.localeCompare(b.name)) })); this.setCust(row.id); },
      pickCustomer: id => this.setCust(id),
      custAll: s.customers,
      custQ: s.custQ,
      onCustQ: e => this.setState({ custQ: e.target.value }),
      custNone: !custList.length,
      custRows: custList.map(x => {
        const on = x.id === s.cust;
        const gp = x.discount;
        const near = x.limit && x.balance / x.limit > 0.8;
        return {
          initials: x.id === 0 ? 'W' : x.name.split(' ').map(w => w[0]).join('').slice(0, 2).toUpperCase(),
          name: x.name,
          sub: x.id === 0 ? 'No account \u00b7 retail pricing' : x.group + (gp ? ' \u00b7 ' + gp + '% off' : '') + (x.phone ? ' \u00b7 ' + x.phone : '') + (s.coins.enabled && x.coins ? ' \u00b7 ' + x.coins + ' coins' : ''),
          right: x.limit ? 'owes ' + this.money(x.balance) : '',
          rightSub: x.limit ? 'limit ' + this.money(x.limit) : '',
          rightStyle: MONO + 'font-size:12.5px;font-weight:600;' + (near ? 'color:#b02020;' : x.balance ? 'color:#8a5300;' : 'color:#5d6b7d;'),
          style: 'display:flex;align-items:center;gap:12px;padding:10px 12px;border-radius:11px;cursor:pointer;' + (on ? 'background:#f4f9ff;box-shadow:inset 0 0 0 1px #cfe0f5;' : ''),
          checkStyle: 'width:22px;height:22px;border-radius:50%;display:grid;place-items:center;font-size:11px;flex:none;'
            + (on ? 'background:var(--c-primary);color:#ffffff;' : 'color:transparent;'),
          onClick: () => this.setCust(x.id)
        };
      }),

      tableOpen: s.overlay === 'table',
      tables: Array.from({ length: 12 }, (_, i) => i + 1).map(n => {
        const h = s.held.find(x => x.type === 'Dine in' && x.table === n);
        const cur = s.type === 'Dine in' && s.table === n;
        const tone = cur ? 'info' : h ? 'warn' : 'success';
        const status = cur ? 'This order' : h ? 'Held \u00b7 ' + this.money(this.calc(h.lines, h.cust, h.type, h.disc, false, h.discFix).total) : 'Free';
        const t = TONES[tone];
        return {
          n: 'T' + n, status,
          dot: 'width:7px;height:7px;border-radius:50%;flex:none;background:' + t[2],
          style: 'height:86px;border-radius:12px;padding:12px;display:flex;flex-direction:column;justify-content:space-between;cursor:pointer;'
            + (cur ? 'background:var(--c-primary);color:#ffffff;border:1px solid var(--c-primary);' : 'background:' + t[0] + ';color:' + t[1] + ';border:1px solid transparent;'),
          onClick: () => {
            if (h && !cur) this.resume(h.id);
            else { this.setState({ table: n, type: 'Dine in', overlay: null }); if (!cur) this.toast('Seated at table ' + n, 'info'); }
          }
        };
      }),

      discOpen: s.overlay === 'disc',
      discNote: can('sell.edit') ? 'Over ' + s.maxDiscount + '% is recorded as approved by you.' : 'You can give up to ' + s.maxDiscount + '%. More needs a manager to sign in.',
      discMode: s.discMode,
      discPct: () => this.setState({ discMode: 'pct' }),
      discAmtMode: () => this.setState({ discMode: 'amt' }),
      discTabStyle: on => 'flex:1;height:36px;border-radius:9px;display:grid;place-items:center;font-size:13px;font-weight:700;cursor:pointer;'
        + (on ? 'background:var(--c-primary);color:#ffffff;' : 'color:#4a5866;'),
      discInput: s.discInput,
      onDiscInput: e => this.setState({ discInput: e.target.value }),
      applyFix: () => this.pickFix(),
      removeDisc: () => this.pickDisc(0),
      discChoices: [0, 5, 10, 15, 20].map(pct => ({
        label: pct ? pct + '%' : 'None',
        onClick: () => this.pickDisc(pct),
        style: 'height:52px;border-radius:11px;display:flex;flex-direction:column;align-items:center;justify-content:center;font-size:14px;font-weight:700;cursor:pointer;'
          + (!s.discFix && s.disc === pct ? 'background:var(--c-primary);color:#ffffff;' : pct > s.maxDiscount ? 'border:1px dashed #e3c38a;color:#8a5300;background:#fffaf0;' : 'border:1px solid #dfe6ee;color:#0f1c2b;')
      })),

      voidOpen: s.overlay === 'void',
      voidText: s.lines.length + (s.lines.length === 1 ? ' line' : ' lines') + ' worth ' + this.money(T.total) + ' will be removed. Nothing has been charged.',
      holdInstead: () => this.hold(),
      confirmVoid: () => { this.setState({ lines: [], sel: null, type: 'Takeaway', disc: 0, discFix: 0, discBy: '', cust: 0, overlay: null }); this.toast('Order cleared', 'neutral'); },

      heldOpen: s.overlay === 'held',
      heldEmpty: !s.held.length,
      heldRows: s.held.map(h => {
        const ht = this.calc(h.lines, h.cust, h.type, h.disc, false, h.discFix);
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
      recentEmpty: !s.recent.length,
      recentRows: s.recent.map(r => ({
        no: r.ref, cust: r.customer_name, total: this.money(r.total),
        meta: new Date(r.sold_at).toTimeString().slice(0, 5) + ' \u00b7 ' + (r.status === 'cancelled' ? 'Cancelled' : r.method) + ' \u00b7 ' + r.item_count + (r.item_count === 1 ? ' line' : ' lines') + (r.due ? ' \u00b7 ' + this.money(r.due) + ' due' : ''),
        open: () => goRoute('sell-details', { id: r.id })
      })),

      payOpen: s.overlay === 'pay',
      coinBox: {
        show: s.coins.enabled && c.id !== 0,
        label: s.useCoins ? 'Using ' + T.coins + ' coins \u00b7 \u2212' + this.money(T.coinDisc)
          : coinMax.coins ? 'Use ' + coinMax.coins + ' coins \u00b7 \u2212' + this.money(coinMax.coinDisc)
          : c.coins < s.coins.min_redeem ? 'Needs ' + s.coins.min_redeem + ' coins to spend' : 'No coins can be used on this sale',
        sub: c.name.split(' ')[0] + ' has ' + c.coins + ' coins' + (T.earn ? ' \u00b7 earns ' + T.earn + ' on this sale' : ''),
        action: s.useCoins ? 'Remove' : coinMax.coins ? 'Use' : '',
        // The total changes, so a card approval or split taken so far has to be done again
        onClick: () => { if (s.useCoins || coinMax.coins) this.setState({ useCoins: !s.useCoins, card: 'idle', splits: [], payInput: '' }); },
        style: 'display:flex;align-items:center;gap:11px;border-radius:12px;padding:10px 13px;margin-bottom:16px;'
          + (s.useCoins ? 'background:#fff6dc;border:1.5px solid #f5b301;color:#5a3d00;cursor:pointer;' : coinMax.coins ? 'border:1px dashed #e0b53c;color:#0f1c2b;cursor:pointer;' : 'border:1px dashed #dfe6ee;color:#7d8b9c;')
      },
      tipBox: {
        value: s.tip,
        onInput: e => this.setState({ tip: e.target.value.replace(/[^0-9.]/g, '') }),
        chips: [['None', 0], ['5%', 'p5'], ['10%', 'p10'], ['$2', 2], ['$5', 5], ['$10', 10]].map(x => {
          const amt = typeof x[1] === 'string' ? r2(T.total * parseInt(x[1].slice(1), 10) / 100) : x[1];
          const on = Math.abs(pv.tip - amt) < 0.005;
          return { label: x[0], onClick: () => this.setState({ tip: amt ? String(amt) : '' }),
            style: 'height:32px;padding:0 11px;border-radius:8px;display:grid;place-items:center;font-size:12px;font-weight:600;cursor:pointer;user-select:none;'
              + (on ? 'background:var(--c-primary);color:#ffffff;' : 'border:1px solid #dfe6ee;color:#4a5866;background:#ffffff;') };
        }),
        methods: ['Cash', 'Card'].map(m => ({ name: m, onClick: () => this.setState({ tipMethod: m }),
          style: 'height:32px;padding:0 12px;display:grid;place-items:center;font-size:12px;font-weight:600;cursor:pointer;user-select:none;'
            + (s.tipMethod === m ? 'background:#0f1c2b;color:#ffffff;' : 'color:#4a5866;') })),
        staff: s.staff, staffId: s.tipStaff,
        onStaff: e => this.setState({ tipStaff: Number(e.target.value) }),
        hasTip: pv.tip > 0,
        staffStyle: 'height:32px;border-radius:8px;padding:0 8px;font-family:inherit;font-size:12.5px;background:#ffffff;flex:1;min-width:0;'
          + (pv.tipProblem ? 'border:1.5px solid #d97706;color:#8a5300;' : 'border:1px solid #dfe6ee;color:#0f1c2b;'),
        note: pv.tip <= 0 ? 'Kept apart from the sale and paid to the staff member later.'
          : s.tipMethod === 'Cash' && s.tender !== 'Cash' ? 'Take the ' + this.money(pv.tip) + ' tip in cash, apart from the sale.'
          : s.tipMethod === 'Card' && s.tender !== 'Card' ? 'Charge the ' + this.money(pv.tip) + ' tip on the card terminal, apart from the sale.'
          : 'The ' + this.money(pv.tip) + ' tip is added to what the customer pays, but not to the sale.'
      },
      payWho: c.name + ' \u00b7 ' + s.type + (s.type === 'Dine in' ? ' \u00b7 Table ' + s.table : ''),
      tenders: [['Cash', 'Cash drawer'], ['Card', s.cardSurcharge ? s.cardSurcharge + '% surcharge' : 'Card terminal'], ['Split', 'Card + cash'], ['Account', c.id ? 'Charge ' + c.name.split(' ')[0] : 'Needs customer']].filter(x => x[0] !== 'Split' || s.allowSplit).map(x => ({
        name: x[0], sub: x[1],
        // A cash sale takes the tip in cash, a card sale on the card. Split and account keep the choice.
        onClick: () => this.setState({ tender: x[0], payInput: '', tipMethod: x[0] === 'Cash' || x[0] === 'Card' ? x[0] : s.tipMethod }),
        style: 'border-radius:12px;padding:12px 13px;cursor:pointer;min-height:62px;'
          + (s.tender === x[0] ? 'background:var(--c-primary);color:#ffffff;' : 'border:1px solid #dfe6ee;color:#0f1c2b;')
      })),
      tenderCash: s.tender === 'Cash', tenderCard: s.tender === 'Card', tenderSplit: s.tender === 'Split', tenderAccount: s.tender === 'Account',
      showPayKeypad: s.tender === 'Cash' || s.tender === 'Split',
      cashDisplay: s.payInput || '0.00',
      quickCash: quick.map((v, i) => ({
        label: i === 0 ? 'Exact' : String(v).replace(/\.0+$/, ''),
        onClick: () => this.setState({ payInput: String(v) }),
        style: 'height:44px;border-radius:10px;display:grid;place-items:center;' + MONO + 'font-size:13px;font-weight:600;cursor:pointer;'
          + (Math.abs(pv.tendered - v) < 0.001 ? 'background:var(--c-primary-soft);border:1px solid var(--c-primary);color:var(--c-primary-text);' : 'border:1px solid #dfe6ee;color:#0f1c2b;')
      })),
      payKeys: pad(KEYS, k => this.payPress(k)),
      splitRows: s.splits.map((x, i) => ({
        method: x.method, amt: this.money(x.amount), state: 'Taken',
        remove: () => this.setState(st => ({ splits: st.splits.filter((_, j) => j !== i) }))
      })),
      splitMethods: ['Card', 'Cash'].map(m => ({
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
      confirmCard: () => this.confirmCard(),

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
        s.disc || s.discFix ? { k: s.discFix ? 'Discount' : 'Discount ' + s.disc + '%', v: '\u2212' + this.money(T.order), c: 'neg' } : null,
        T.coins ? { k: 'Coins ' + T.coins, v: '\u2212' + this.money(T.coinDisc), c: 'neg' } : null,
        T.fee ? { k: 'Delivery', v: this.money(T.fee), c: '' } : null,
        pv.surcharge ? { k: 'Card surcharge ' + s.cardSurcharge + '%', v: this.money(pv.surcharge), c: '' } : null,
        { k: 'GST included', v: this.money(T.gst + pv.surcharge / 11), c: '' },
        s.tender === 'Cash' && Math.abs(pv.cashDue - T.total) > 0.001 ? { k: 'Cash rounding', v: (pv.cashDue > T.total ? '+' : '\u2212') + this.money(Math.abs(pv.cashDue - T.total)), c: '' } : null,
        s.tender === 'Split' && s.splits.length ? { k: 'Taken so far', v: this.money(pv.paidSplit), c: 'pos' } : null,
        pv.tip ? { k: 'Tip \u00b7 not in sale', v: '+' + this.money(pv.tip), c: 'pos' } : null
      ].filter(Boolean).map(r => ({ k: r.k, v: r.v, vStyle: MONO + (r.c === 'neg' ? 'color:#ff9c9c;' : r.c === 'pos' ? 'color:#86dcaa;' : 'color:#ffffff;') })),
      dueLabel: s.tender === 'Cash' ? 'Cash due' : 'Total payable',
      dueLabel2: pv.cashTip || pv.cardTip ? 'Includes ' + this.money(pv.cashTip || pv.cardTip) + ' tip' : '',
      dueValue: this.money(s.tender === 'Cash' ? pv.cashDue + pv.cashTip : T.total + pv.surcharge + pv.cardTip),
      changeLabel: s.tender === 'Cash' ? (pv.change < -0.001 ? 'Still to collect' : 'Change to give')
        : s.tender === 'Card' ? (s.card === 'approved' ? 'Card approved' : 'Awaiting terminal')
        : s.tender === 'Split' ? (pv.rem > 0.005 ? 'Remaining' : 'Covered') : (pv.ready ? 'On account' : 'Blocked'),
      changeValue: s.tender === 'Cash' ? this.money(Math.abs(pv.change))
        : s.tender === 'Card' ? this.money(T.total + pv.surcharge + pv.cardTip)
        : s.tender === 'Split' ? this.money(Math.max(0, pv.rem)) : this.money(T.total),
      changeBox: 'border-radius:12px;padding:12px 14px;margin-top:12px;'
        + (pv.ready ? 'background:#2f9e63;color:#ffffff;' : (s.tender === 'Account' && pv.blocked) ? 'background:rgba(217,58,58,.22);color:#ffb3b3;' : 'background:rgba(217,140,26,.2);color:#ffd08a;'),
      payError: s.payError,
      completeLabel: payReadyLabel,
      completeStyle: 'height:52px;border-radius:12px;display:grid;place-items:center;font-size:15px;font-weight:700;'
        + (pv.ready && !s.saving ? 'background:var(--c-accent);color:#06283a;cursor:pointer;' : 'background:rgba(255,255,255,.1);color:#6f8599;cursor:not-allowed;pointer-events:none;'),
      complete: () => this.complete(),

      doneOpen: s.overlay === 'done' && !!d,
      done: d ? {
        no: d.ref,
        title: d.due ? 'Sale #' + d.ref + ' saved \u00b7 ' + this.money(d.due) + ' due' : 'Sale #' + d.ref + ' complete',
        line: d.method + ' \u00b7 ' + this.money(d.total) + ' \u00b7 ' + d.customer_name + ' \u00b7 ' + d.order_type + (d.table_no ? ' T' + d.table_no : ''),
        showChange: d.change_given > 0.001,
        change: this.money(d.change_given),
        head: 'Tax invoice \u00b7 ' + new Date(d.sold_at).toTimeString().slice(0, 5) + ' \u00b7 ' + d.branch.name,
        lines: d.items.map(x => ({ name: x.name, note: x.note, total: this.money(x.total),
          qty: x.unit === 'kg' ? this.qtyLabel(x.qty, 'kg') : x.qty + ' \u00d7',
          noteStyle: x.note ? 'font-size:11px;color:#b45309;font-style:italic' : 'display:none' })),
        rows: [
          ['Items', this.money(d.subtotal)],
          d.group_discount ? ['Group discount ' + d.group_percent + '%', '\u2212' + this.money(d.group_discount)] : null,
          d.discount ? [d.discount_type === 'amount' ? 'Discount' : 'Discount ' + d.discount_percent + '%', '\u2212' + this.money(d.discount)] : null,
          d.coins_used ? ['Coins used ' + d.coins_used, '\u2212' + this.money(d.coin_discount)] : null,
          d.delivery_fee ? ['Delivery', this.money(d.delivery_fee)] : null,
          d.card_surcharge ? ['Card surcharge', this.money(d.card_surcharge)] : null,
          d.rounding ? ['Rounding', (d.rounding > 0 ? '+' : '\u2212') + this.money(Math.abs(d.rounding))] : null,
          ['Total', this.money(d.total), true],
          ['GST included', this.money(d.gst)]
        ].concat(d.payments.map(p => [p.method === 'cash' ? 'Cash' : 'Card', this.money(p.amount)]))
          .concat([d.tendered ? ['Cash handed over', this.money(d.tendered)] : null, d.due ? ['Still owing', this.money(d.due)] : null,
            d.coins_earned ? ['Coins earned', '+' + d.coins_earned] : null,
            d.tip ? ['Tip for ' + (d.tip.employee ? d.tip.employee.first : 'staff') + ' (' + d.tip.method + ')', this.money(d.tip.amount)] : null,
            d.contact && (d.coins_used || d.coins_earned) ? ['Coin balance', String(d.contact.coins)] : null])
          .filter(Boolean).map(r => ({ k: r[0], v: r[1],
            style: 'display:flex;gap:10px;' + (r[2] ? 'font-size:14px;font-weight:700;padding-top:6px;border-top:1.5px solid #0f1c2b;margin-top:4px;' : 'font-size:12px;color:#5d6b7d;') }))
      } : { no: '', title: '', line: '', showChange: false, change: '', head: '', lines: [], rows: [] },
      // The full sale, for the printed invoice (PosView.vue).
      doneSale: d,
      newSale: () => this.newSale()
    };
  }
}
