// HrmLogic
import { ModBase } from "../../runtime/logic.js";
import { api } from "../../../api/http.js";
import { branches, loadBranches } from "../../../api/branches.js";

export const HrmLogic = (() => {
const NAV = [
  ['Home','Hm',0],['User Management','Us',1],['Contacts','Ct',1],['Products','Pr',1],
  ['Manufacturing','Mf',0],['Purchases','Pu',1],['Sell','Sl',1],['Stock Transfers','St',0],
  ['Stock Adjustment','Sa',0],['Expenses','Ex',1],['Payment Accounts','Pa',1],['Accounting','Ac',1],
  ['Reports','Rp',1],['Bookings','Bk',0],['Kitchen','Kt',0],['Orders','Or',0],
  ['Settings','Se',1],['HRM','Hr',1]
];
const TONE = { info:['var(--c-primary-soft)','var(--c-primary-text)','var(--c-primary)'], warn:['#fdf1dd','#8a5300','#d98c1a'], error:['#fdeaea','#b02020','#d93a3a'], success:['#e7f6ee','#0f6b43','#2f9e63'], neutral:['#eef2f6','#5d6b7d','#8fa3b6'], purple:['#f3ecfd','#5b34a8','#7a4fc9'] };
const MONO = "font-family:'IBM Plex Mono',monospace;";
const DEPTS = ['Butchery','Kitchen','Front counter','Delivery','Admin'];
const AVC = ['var(--c-primary)','#2f9e63','#7a4fc9','#c2410c','#0f8b8d','#8e2a3b','#5d6b7d','#d98c1a'];
// The shop's own clock: minutes after midnight and today's date.
const nowMinutes = () => { const d = new Date(); return d.getHours() * 60 + d.getMinutes(); };
const isoDate = d => d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
const todayIso = () => isoDate(new Date());
// '2026-10-19' -> '19/10', or '19/10/2026' with the year.
const dm = iso => iso ? iso.slice(8, 10) + '/' + iso.slice(5, 7) : '';
const dmy = iso => iso ? dm(iso) + '/' + iso.slice(0, 4) : '';
const DAY_NAMES = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
const MONTHS = ['January','February','March','April','May','June','July','August','September','October','November','December'];
const hm = m => m === null || m === undefined ? '\u2014' : String(Math.floor(m / 60)).padStart(2, '0') + ':' + String(m % 60).padStart(2, '0');
const tax = g => Math.max(0, Math.round(((g - 700) * 0.21 + (g > 3000 ? (g - 3000) * 0.12 : 0)) * 100) / 100);
const DOW = ['Mon','Tue','Wed','Thu','Fri','Sat','Sun'];

class Component extends ModBase {
  _id = 0;
  initialState = { tab:'emp', view:'list', sel:null, q:'', dept:'all', loc:'all', status:'Active', emps: [], att: {}, leave: [], lt:'Pending',
    run: null, pay: {}, payStatus:'draft', saving:false, form:null, fTab:'personal', lf:null, toasts:[] };

  // ---- API ----
  // An employee from the API, with the names the screen uses.
  toEmp(r) { return { ...r, loc: r.branch ? r.branch.name : '', superFund: r.super_fund || '', hasPin: r.has_pin, phone: r.phone || '', email: r.email || '', dob: r.dob || '', emerg: r.emerg || '', tfn: r.tfn || '' }; }
  toLeave(r) { return { id: r.id, ref: r.ref, emp: r.employee_id, type: r.type, from: r.date_from, to: r.date_to, days: r.days, status: r.status, note: r.note || '' }; }
  setRun(run) {
    const pay = {};
    (run ? run.items : []).forEach(i => { pay[i.employee_id] = { ord: String(i.ord_hours), ot: String(i.ot_hours) }; });
    this.setState({ run, pay, payStatus: run ? run.status : 'none' });
  }
  async loadEmployees() { const rows = await api.get('/employees?today=' + todayIso()); this.setState({ emps: rows.map(r => this.toEmp(r)) }); }
  async loadAttendance() {
    const rows = await api.get('/attendances?date=' + todayIso());
    const att = {};
    rows.forEach(r => { att[r.employee_id] = { in: r.in, out: r.out, absent: r.absent }; });
    this.setState({ att });
  }
  async loadLeave() { const rows = await api.get('/leave-requests'); this.setState({ leave: rows.map(r => this.toLeave(r)) }); }
  async loadAll() {
    try {
      loadBranches().catch(() => {});
      await Promise.all([this.loadEmployees(), this.loadAttendance(), this.loadLeave()]);
      this.setRun(await api.get('/pay-runs/current'));
    } catch (e) { this.toast(e.message || 'Could not load HRM', 'error'); }
  }
  // Runs one save, shows what went wrong, and stops a second click while it is busy.
  async send(fn) {
    if (this.state.saving) return false;
    this.setState({ saving: true });
    try { await fn(); return true; }
    catch (e) { this.toast(e.message || 'Could not save', 'error'); return false; }
    finally { this.setState({ saving: false }); }
  }
  clock(e, action, msg, tone) {
    return this.send(async () => {
      const r = await api.post('/attendances/clock', { employee_id: e.id, date: todayIso(), action, minutes: nowMinutes() });
      this.setState(ss => ({ att: { ...ss.att, [e.id]: { in: r.in, out: r.out, absent: r.absent } } }));
      this.toast(msg, tone);
    });
  }
  hoursRows() { return Object.keys(this.state.pay).map(id => ({ employee_id: Number(id), ord_hours: parseFloat(this.state.pay[id].ord) || 0, ot_hours: parseFloat(this.state.pay[id].ot) || 0 })); }
  payAction(path, body, msg, tone) {
    return this.send(async () => {
      const run = await api.post('/pay-runs/' + this.state.run.id + '/' + path, body);
      this.setRun(run);
      this.toast(msg, tone);
    });
  }
  later(fn, ms) { (this._t = this._t || []).push(setTimeout(fn, ms)); }
  toast(msg, tone) {
    const id = ++this._id;
    this.setState(s => ({ toasts: s.toasts.concat([{ id, msg, tone: tone || 'info' }]).slice(-3) }));
    this.later(() => this.setState(s => ({ toasts: s.toasts.filter(x => x.id !== id) })), 2600);
  }
  money(n) { return (n || 0).toLocaleString('en-AU', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
  badge(x) { return 'background:' + x[0] + ';color:' + x[1] + ';border-radius:5px;padding:3px 8px;font-size:11.5px;font-weight:600;white-space:nowrap'; }
  seg(on) { return 'flex:1;height:30px;padding:0 12px;border-radius:7px;display:grid;place-items:center;font-size:12.5px;cursor:pointer;white-space:nowrap;' + (on ? 'background:#ffffff;color:#0f1c2b;font-weight:700;box-shadow:0 1px 3px rgba(15,28,43,.14);' : 'color:#5d6b7d;font-weight:500;'); }
  pageTab(on) { return 'padding:9px 14px;font-size:13px;cursor:pointer;white-space:nowrap;border-bottom:2px solid ' + (on ? 'var(--c-primary);color:var(--c-primary);font-weight:600;' : 'transparent;color:#5d6b7d;font-weight:500;'); }
  emp(id) { return this.state.emps.find(e => e.id === id); }
  name(e) { return e.first + ' ' + e.last; }
  ini(e) { return (e.first[0] + e.last[0]).toUpperCase(); }
  av(e, big) { const z = big ? 52 : 32; return 'width:' + z + 'px;height:' + z + 'px;border-radius:' + (big ? 14 : 9) + 'px;flex:none;display:grid;place-items:center;font-size:' + (big ? 17 : 12) + 'px;font-weight:700;color:#ffffff;background:' + AVC[e.id % AVC.length]; }
  hourly(e) { return e.basis === 'Salary' ? e.rate / 52 / 38 : e.rate; }
  rateTxt(e) { return e.basis === 'Salary' ? this.money(e.rate) + ' /yr' : this.money(e.rate) + ' /h'; }
  stTone(st) { return st === 'Active' ? TONE.success : st === 'On leave' ? TONE.purple : TONE.neutral; }
  lvTone(st) { return st === 'Approved' ? TONE.success : st === 'Pending' ? TONE.warn : TONE.error; }
  sBtn(kind) { return 'height:30px;padding:0 11px;border-radius:7px;display:flex;align-items:center;font-size:12px;font-weight:600;cursor:pointer;white-space:nowrap;' + (kind === 'p' ? 'background:var(--c-primary);color:#ffffff;' : kind === 'g' ? 'background:#2f9e63;color:#ffffff;' : kind === 'd' ? 'border:1px solid #f3cfcf;background:#fdf4f4;color:#b02020;' : 'border:1px solid #dfe6ee;background:#ffffff;color:#4a5866;'); }
  dayN(iso) { return new Date(iso + 'T00:00:00'); }
  workDays(a, b) { if (!a || !b) return 0; let d = this.dayN(a), n = 0; const e = this.dayN(b); if (e < d) return 0; while (d <= e) { if (d.getDay() !== 0) n++; d = new Date(d.getFullYear(), d.getMonth(), d.getDate() + 1); } return n; }
  branchOptions() { return branches.value.filter(b => b.active); }

  openForm(e) {
    const firstBranch = this.branchOptions()[0];
    this.setState({ fTab: 'personal', form: e ? { edit: e.id, first: e.first, last: e.last, phone: e.phone, email: e.email, dob: e.dob, emerg: e.emerg, role: e.role, dept: e.dept, loc: e.loc, start: e.start || '', type: e.type, pin: '', hasPin: e.hasPin, basis: e.basis, rate: String(e.rate), hours: String(e.hours), tfn: e.tfn, superFund: e.superFund }
      : { edit: null, first: '', last: '', phone: '', email: '', dob: '', emerg: '', role: '', dept: 'Butchery', loc: firstBranch ? firstBranch.name : '', start: todayIso(), type: 'Casual', pin: '', hasPin: false, basis: 'Hourly', rate: '', hours: '38', tfn: '', superFund: '' } });
  }
  fErrs() {
    const f = this.state.form, e = [];
    if (!f.first.trim() || !f.last.trim()) e.push(['personal', 'Enter first and last name']);
    if (f.phone.replace(/\D/g, '').length < 8) e.push(['personal', 'Enter a mobile number']);
    if (f.email && !/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(f.email)) e.push(['personal', 'Email looks wrong']);
    if (!f.role.trim()) e.push(['job', 'Enter a designation']);
    if (f.pin && !/^\d{4}$/.test(f.pin)) e.push(['job', 'PIN must be 4 digits']);
    if (!f.loc) e.push(['job', 'Pick a location']);
    const r = parseFloat(f.rate);
    if (!(r > 0)) e.push(['pay', 'Enter a pay rate']);
    else if (f.basis === 'Hourly' && r < 24.95) e.push(['pay', 'Below the 24.95 /h national minimum wage']);
    if (f.tfn && f.tfn.indexOf('\u2022') < 0 && f.tfn.replace(/\D/g, '').length !== 9) e.push(['pay', 'TFN must be 9 digits']);
    return e;
  }
  saveEmp() {
    const s = this.state, f = s.form;
    const e = this.fErrs();
    if (e.length) { this.setState({ fTab: e[0][0] }); this.toast(e[0][1], 'error'); return; }
    const branch = branches.value.find(b => b.name === f.loc);
    const body = { first: f.first.trim(), last: f.last.trim(), phone: f.phone, email: f.email || null, dob: f.dob || null, emerg: f.emerg || null, role: f.role.trim(), dept: f.dept, branch_id: branch ? branch.id : null, start: f.start || null, type: f.type, basis: f.basis, rate: parseFloat(f.rate), hours: parseFloat(f.hours) || 0, tfn: f.tfn, super_fund: f.superFund || null };
    if (f.pin) body.pin = f.pin;
    this.send(async () => {
      const saved = this.toEmp(f.edit ? await api.put('/employees/' + f.edit, body) : await api.post('/employees', body));
      this.setState({ form: null, ...(f.edit ? {} : { view: 'emp', sel: saved.id }) });
      await this.loadEmployees();
      if (this.state.run && !f.edit) this.setRun(await api.get('/pay-runs/current'));
      this.toast(this.name(saved) + (f.edit ? ' updated' : ' added'), 'success');
    });
  }
  setStatus(e, status) {
    this.send(async () => {
      await api.post('/employees/' + e.id + '/status', { status });
      await this.loadEmployees();
      this.toast(e.first + (status === 'Inactive' ? ' deactivated' : ' reactivated'), 'neutral');
    });
  }
  decideLeave(l, ok) {
    const e = this.emp(l.emp);
    this.send(async () => {
      await api.post('/leave-requests/' + l.id + '/decide', { approve: ok });
      await Promise.all([this.loadLeave(), this.loadEmployees()]);
      this.toast(l.ref + ' ' + (ok ? 'approved' : 'declined') + ' \u00b7 ' + e.first, ok ? 'success' : 'neutral');
    });
  }
  payRows() {
    const s = this.state;
    return s.emps.filter(e => e.status !== 'Inactive').map(e => {
      const p = s.pay[e.id] || { ord: '0', ot: '0' };
      const hr = this.hourly(e);
      const ord = parseFloat(p.ord) || 0, ot = parseFloat(p.ot) || 0;
      const gross = Math.round((e.basis === 'Salary' ? e.rate / 26 : ord * hr) * 100 + ot * hr * 1.5 * 100) / 100;
      const t = tax(gross), sup = Math.round(gross * 0.12 * 100) / 100;
      return { e, p, ord, ot, gross, t, sup, net: Math.round((gross - t) * 100) / 100 };
    });
  }

  renderVals() {
    if (!this._loaded) { this._loaded = true; this.loadAll(); }
    const s = this.state;
    const NOW = nowMinutes();
    const emps = s.emps;
    const LOCS = this.branchOptions().map(b => b.name);
    const run = s.run;
    const now = new Date();
    const q = s.q.trim().toLowerCase();
    const list = emps.filter(e => (s.status === 'All' || e.status === s.status || (s.status === 'Active' && e.status === 'On leave')) && (s.dept === 'all' || e.dept === s.dept) && (s.loc === 'all' || e.loc === s.loc)
      && (!q || (this.name(e) + ' ' + e.role + ' ' + e.phone).toLowerCase().indexOf(q) > -1));
    const pendingLv = s.leave.filter(l => l.status === 'Pending');
    const onShift = emps.filter(e => s.att[e.id] && s.att[e.id].in !== null && s.att[e.id].out === null && !s.att[e.id].absent);
    const pr = this.payRows();
    const sum = k => Math.round(pr.reduce((a, r) => a + r[k], 0) * 100) / 100;
    const sel = s.sel ? this.emp(s.sel) : null;
    const isEmp = s.tab === 'emp' && s.view === 'emp' && !!sel;
    const A = (label, fn, kind) => ({ label, onClick: fn, style: kind === 'p' ? 'height:34px;padding:0 15px;border-radius:8px;background:var(--c-primary);color:#ffffff;display:flex;align-items:center;font-size:12.5px;font-weight:600;cursor:pointer' : kind === 'g' ? 'height:34px;padding:0 15px;border-radius:8px;background:#2f9e63;color:#ffffff;display:flex;align-items:center;font-size:12.5px;font-weight:600;cursor:pointer' : kind === 'off' ? 'height:34px;padding:0 15px;border-radius:8px;background:#e4eaf1;color:#8b98a8;display:flex;align-items:center;font-size:12.5px;font-weight:600' : 'height:34px;padding:0 13px;border-radius:8px;border:1px solid #dfe6ee;display:flex;align-items:center;font-size:12.5px;font-weight:500;color:#4a5866;cursor:pointer;background:#ffffff' });
    let head = [], title = 'Employees', sub = '';
    if (isEmp) {
      title = this.name(sel); sub = sel.role + ' \u00b7 ' + sel.dept + ' \u00b7 ' + sel.loc;
      head = [A(sel.status === 'Inactive' ? 'Reactivate' : 'Deactivate', () => this.setStatus(sel, sel.status === 'Inactive' ? 'Active' : 'Inactive')), A('Request leave', () => this.setState({ lf: { emp: sel.id, type: 'Annual', from: todayIso(), to: todayIso(), note: '' } })), A('Edit', () => this.openForm(sel), 'p')];
    } else if (s.tab === 'emp') { sub = emps.filter(e => e.status !== 'Inactive').length + ' staff across ' + LOCS.length + ' locations'; head = [A('+ Add employee', () => this.openForm(null), 'p')]; }
    else if (s.tab === 'att') { title = 'Attendance'; sub = DAY_NAMES[now.getDay()] + ' ' + now.getDate() + ' ' + MONTHS[now.getMonth()] + ' \u00b7 ' + hm(NOW); }
    else if (s.tab === 'leave') { title = 'Leave'; sub = pendingLv.length + ' requests waiting for approval'; head = [A('+ New request', () => { const first = emps.find(e => e.status !== 'Inactive'); this.setState({ lf: { emp: first ? first.id : 0, type: 'Annual', from: todayIso(), to: todayIso(), note: '' } }); }, 'p')]; }
    else if (s.tab === 'tips') { title = 'Tips'; sub = 'Tips customers gave at the POS. They are not sales; pay them to the staff here.'; }
    else {
      title = 'Payroll';
      sub = run ? 'Fortnight ' + dm(run.period_from) + '\u2013' + dmy(run.period_to) + ' \u00b7 pay day ' + dm(run.pay_date) : 'No pay runs yet';
      const newRun = () => this.send(async () => { this.setRun(await api.post('/pay-runs')); this.toast('New pay run started', 'success'); });
      head = !run ? [A('Start pay run', newRun, 'p')]
        : s.payStatus === 'draft' ? [A('Save hours', () => this.send(async () => { this.setRun(await api.put('/pay-runs/' + run.id + '/hours', { rows: this.hoursRows() })); this.toast('Hours saved', 'success'); })), A('Approve pay run', () => this.payAction('approve', { rows: this.hoursRows() }, 'Pay run approved \u00b7 hours locked', 'success'), 'p')]
        : s.payStatus === 'approved' ? [A('Back to draft', () => this.payAction('reopen', {}, 'Back to draft', 'neutral')), A('Mark as paid ' + this.money(sum('net')), () => this.payAction('pay', {}, 'Pay run recorded as paid', 'success'), 'g')]
        : [A('Start next pay run', newRun, 'p'), A('Paid', () => {}, 'off')];
    }

    const att = emps.filter(e => e.status !== 'Inactive').map(e => {
      const a = s.att[e.id] || { in: null, out: null };
      const sh = e.shift;
      const onLeave = e.status === 'On leave';
      const late = sh && a.in !== null && a.in > sh[0] + 5;
      const hrs = a.in !== null ? ((a.out === null ? NOW : a.out) - a.in) / 60 : 0;
      let st = onLeave ? ['On leave', TONE.purple] : a.absent ? ['Absent', TONE.error] : a.out !== null ? ['Finished', TONE.neutral] : a.in !== null ? (late ? ['Late ' + (a.in - sh[0]) + 'm', TONE.warn] : ['On shift', TONE.success]) : !sh ? ['Not rostered', TONE.neutral] : sh[0] < NOW ? ['Not in', TONE.error] : ['Starts ' + hm(sh[0]), TONE.info];
      const acts = [];
      if (!onLeave && !a.absent) {
        if (a.in === null) { acts.push({ label: 'Clock in', onClick: () => this.clock(e, 'in', e.first + ' clocked in at ' + hm(nowMinutes()), 'success'), style: this.sBtn('p') }); if (sh && sh[0] < NOW) acts.push({ label: 'Absent', onClick: () => this.clock(e, 'absent', e.first + ' marked absent', 'warn'), style: this.sBtn('d') }); }
        else if (a.out === null) acts.push({ label: 'Clock out', onClick: () => this.clock(e, 'out', e.first + ' clocked out \u00b7 ' + ((nowMinutes() - a.in) / 60).toFixed(2) + ' h', 'neutral'), style: this.sBtn('') });
      }
      if (a.absent) acts.push({ label: 'Undo', onClick: () => this.clock(e, 'undo', e.first + ' no longer absent', 'neutral'), style: this.sBtn('') });
      return { name: this.name(e), ini: this.ini(e), avatar: this.av(e), loc: e.loc, roster: sh ? hm(sh[0]) + '\u2013' + hm(sh[1]) : '\u2014', cin: hm(a.in), cout: a.out !== null ? hm(a.out) : '\u2014',
        inStyle: MONO + 'font-size:12px;' + (late ? 'color:#b45309;font-weight:600;' : ''), hours: a.in !== null ? hrs.toFixed(2) : '\u2014', status: st[0], badge: this.badge(st[1]), actions: acts };
    });
    const lvList = s.leave.filter(l => s.lt === 'All' || l.status === s.lt);
    const lf = s.lf;
    const lfDays = lf ? this.workDays(lf.from, lf.to) : 0;
    const lfEmp = lf ? this.emp(lf.emp) : null;
    const lfKey = lf && lf.type === 'Annual' ? 'al' : lf && lf.type === 'Personal' ? 'pl' : null;
    const f = s.form;
    const fe = f ? this.fErrs() : [];
    const onF = {};
    ['first','last','phone','email','dob','emerg','role','dept','loc','start','pin','rate','hours','tfn','superFund'].forEach(k => { onF[k] = ev => { const v = ev.target.value; this.setState(ss => ({ form: { ...ss.form, [k]: v } })); }; });
    const onL = {};
    ['emp','from','to','note'].forEach(k => { onL[k] = ev => { const v = k === 'emp' ? parseInt(ev.target.value, 10) : ev.target.value; this.setState(ss => ({ lf: { ...ss.lf, [k]: v } })); }; });
    const fr = f ? parseFloat(f.rate) || 0 : 0;
    const fh = f ? parseFloat(f.hours) || 0 : 0;
    const fGross = f ? (f.basis === 'Salary' ? fr / 26 : fr * fh) : 0;
    const locked = s.payStatus !== 'draft';
    const todayIdx = (now.getDay() + 6) % 7;

    return {
      nav: NAV.map(n => {
        const on = n[0] === 'HRM';
        return {
          name: n[0], key: n[1],
          style: 'display:flex;align-items:center;gap:10px;padding:8px 10px;border-radius:8px;cursor:pointer;font-size:13px;font-weight:500;margin-bottom:1px;' + (on ? 'background:rgba(255,255,255,.13);color:#ffffff;box-shadow:inset 2px 0 0 var(--c-accent);' : 'color:#c4dced;'),
          iconStyle: 'width:20px;height:20px;border-radius:5px;display:grid;place-items:center;' + MONO + 'font-size:9px;font-weight:600;flex:none;' + (on ? 'background:var(--c-accent);color:#06283a;' : 'background:rgba(255,255,255,.1);color:#9dbdd2;'),
          caretStyle: 'color:#7fa3bb;font-size:11px;flex:none;' + (n[2] ? '' : 'visibility:hidden;')
        };
      }),
      crumb: { emp: 'Employees', att: 'Attendance', leave: 'Leave', pay: 'Payroll', tips: 'Tips' }[s.tab],
      crumbClick: () => this.setState({ view: 'list' }),
      crumbStyle: isEmp ? 'color:var(--c-primary);cursor:pointer' : 'color:#0f1c2b;font-weight:500',
      title, sub, headActions: head,
      tabs: [['emp','Employees', emps.filter(e => e.status !== 'Inactive').length], ['att','Attendance', onShift.length + ' in'], ['leave','Leave', pendingLv.length || ''], ['pay','Payroll', ''], ['tips','Tips', '']].map(t => ({ name: t[1], count: t[2], onClick: () => this.setState({ tab: t[0], view: 'list' }), style: this.pageTab(s.tab === t[0]),
        countStyle: t[2] === '' ? 'display:none' : MONO + 'font-size:10.5px;border-radius:4px;padding:1px 5px;margin-left:6px;' + (t[0] === 'leave' ? 'background:#fdf1dd;color:#8a5300;' : s.tab === t[0] ? 'background:var(--c-primary-soft);color:var(--c-primary-text);' : 'background:#eef2f6;color:#7d8b9c;') })),
      isList: s.tab === 'emp' && !isEmp, isEmp, isAtt: s.tab === 'att', isLeave: s.tab === 'leave', isPay: s.tab === 'pay', isTips: s.tab === 'tips',
      kpis: [
        { k: 'Headcount', v: String(emps.filter(e => e.status !== 'Inactive').length), n: emps.filter(e => e.type === 'Full-time').length + ' full-time \u00b7 ' + emps.filter(e => e.type === 'Casual').length + ' casual' },
        { k: 'On shift now', v: String(onShift.length), n: 'at ' + hm(NOW) },
        { k: 'On leave', v: String(emps.filter(e => e.status === 'On leave').length), n: pendingLv.length + ' requests pending' },
        { k: 'Fortnight wages', v: this.money(sum('gross')), n: 'plus ' + this.money(sum('sup')) + ' super' }
      ],
      q: s.q, onQ: e => this.setState({ q: e.target.value }),
      dept: s.dept, onDept: e => this.setState({ dept: e.target.value }), deptOpts: DEPTS.map(v => ({ v })),
      loc: s.loc, onLoc: e => this.setState({ loc: e.target.value }), locOpts: LOCS.map(v => ({ v })),
      statusSeg: ['Active','Inactive','All'].map(k => ({ name: k, onClick: () => this.setState({ status: k }), style: this.seg(s.status === k).replace('flex:1;', '') })),
      rows: list.map(e => ({ name: this.name(e), ini: this.ini(e), avatar: this.av(e), role: e.role, dept: e.dept, loc: e.loc, type: e.type, rate: this.rateTxt(e), status: e.status, badge: this.badge(this.stTone(e.status)), open: () => this.setState({ view: 'emp', sel: e.id }) })),
      noRows: !list.length,
      e: sel ? {
        name: this.name(sel), ini: this.ini(sel), avatar: this.av(sel, true), role: sel.role, status: sel.status, badge: this.badge(this.stTone(sel.status)) + ';display:inline-block;margin-top:5px',
        facts: [['Department', sel.dept], ['Location', sel.loc], ['Type', sel.type], ['Started', dmy(sel.start) || '—'], ['Mobile', sel.phone], ['Email', sel.email], ['Pay', this.rateTxt(sel)], ['Super', sel.superFund || '\u2014'], ['POS PIN', sel.hasPin ? '\u2022\u2022\u2022\u2022 set' : 'Not set']].map(x => ({ k: x[0], v: x[1] })),
        stats: [
          { k: 'Annual leave', v: sel.type === 'Casual' ? '\u2014' : sel.al + ' d', n: sel.type === 'Casual' ? 'casual loading instead' : 'available' },
          { k: 'Personal leave', v: sel.type === 'Casual' ? '\u2014' : sel.pl + ' d', n: 'available' },
          { k: 'Hours this fortnight', v: String((parseFloat((s.pay[sel.id] || {}).ord) || 0) + (parseFloat((s.pay[sel.id] || {}).ot) || 0)), n: ((s.pay[sel.id] || {}).ot || 0) + ' overtime' },
          { k: 'Next pay', v: this.money((pr.find(r => r.e.id === sel.id) || { net: 0 }).net), n: run ? 'net on ' + dm(run.pay_date) : 'no pay run yet' }
        ],
        week: DOW.map((d, i) => {
          const leave = sel.status === 'On leave' && i < 5;
          const off = !sel.shift || i === 6 || (sel.type !== 'Full-time' && (i === 2 || i === 3));
          const today = i === todayIdx;
          const h = sel.shift ? ((sel.shift[1] - sel.shift[0]) / 60 - 0.5) : 0;
          return { dow: d + (today ? ' \u00b7 today' : ''), shift: leave ? 'Leave' : off ? 'Off' : hm(sel.shift[0]) + '\u2013' + hm(sel.shift[1]), hours: leave || off ? '' : h.toFixed(1) + ' h',
            box: 'border-radius:9px;padding:9px 10px;' + (leave ? 'background:#f3ecfd;color:#5b34a8;' : off ? 'background:#f6f9fc;color:#9aa7b6;' : today ? 'background:var(--c-primary);color:#ffffff;' : 'background:var(--c-primary-soft);color:var(--c-primary-text);') };
        }),
        leave: s.leave.filter(l => l.emp === sel.id).map(l => ({ type: l.type, range: dm(l.from) + ' \u2192 ' + dm(l.to), days: l.days + ' d', status: l.status, badge: this.badge(this.lvTone(l.status)) })),
        noLeave: !s.leave.some(l => l.emp === sel.id)
      } : { name: '', facts: [], stats: [], week: [], leave: [], noLeave: true },

      attKpis: [
        ['On shift', onShift.length, '#0f6b43'], ['Late today', att.filter(a => a.status.indexOf('Late') === 0).length, '#8a5300'],
        ['Not in / absent', att.filter(a => a.status === 'Not in' || a.status === 'Absent').length, '#b02020'], ['Hours so far', att.reduce((a, r) => a + (parseFloat(r.hours) || 0), 0).toFixed(1), '#0f1c2b']
      ].map(k => ({ k: k[0], v: String(k[1]), vStyle: MONO + 'font-size:21px;font-weight:600;margin-top:2px;color:' + k[2] })),
      att,

      leaveTabs: ['Pending','Approved','Declined','All'].map(k => ({ name: k, count: k === 'All' ? s.leave.length : s.leave.filter(l => l.status === k).length, onClick: () => this.setState({ lt: k }), style: this.pageTab(s.lt === k),
        countStyle: MONO + 'font-size:10.5px;border-radius:4px;padding:1px 5px;margin-left:6px;' + (s.lt === k ? 'background:var(--c-primary-soft);color:var(--c-primary-text);' : 'background:#eef2f6;color:#7d8b9c;') })),
      leave: lvList.map(l => {
        const e = this.emp(l.emp);
        const key = l.type === 'Annual' ? 'al' : l.type === 'Personal' ? 'pl' : null;
        const short = l.status === 'Pending' && key && e[key] < l.days;
        const clash = l.status === 'Pending' && s.leave.some(x => x.id !== l.id && x.status === 'Approved' && this.emp(x.emp) && this.emp(x.emp).dept === e.dept && !(this.dayN(x.to) < this.dayN(l.from) || this.dayN(l.to) < this.dayN(x.from)));
        return { name: this.name(e), ini: this.ini(e), avatar: this.av(e), note: l.note || e.role, type: l.type, range: dm(l.from) + ' \u2192 ' + dm(l.to), days: l.days, status: l.status, badge: this.badge(this.lvTone(l.status)),
          actions: l.status === 'Pending' ? [{ label: 'Decline', onClick: () => this.decideLeave(l, false), style: this.sBtn('d') }, { label: 'Approve', onClick: () => this.decideLeave(l, true), style: this.sBtn(short ? '' : 'g') }] : [],
          warn: short ? 'Only ' + e[key] + ' d left' : clash ? e.dept + ' overlap' : '', warnStyle: short || clash ? 'font-size:11px;font-weight:700;color:#b45309;align-self:center' : 'display:none' };
      }),
      noLeave: !lvList.length,
      balances: emps.filter(e => e.type !== 'Casual' && e.status !== 'Inactive').map(e => ({ name: this.name(e), annual: e.al.toFixed(1), personal: e.pl.toFixed(1) })),

      payBanner: 'display:flex;align-items:center;gap:14px;border-radius:12px;padding:13px 16px;' + (s.payStatus === 'paid' ? 'background:#e7f6ee;color:#0f6b43;border:1px solid #bfe5d1;' : s.payStatus === 'approved' ? 'background:var(--c-primary-soft);color:var(--c-primary-text);border:1px solid #cfe0f5;' : 'background:#fdf1dd;color:#8a5300;border:1px solid #f0d9ac;'),
      payTitle: s.payStatus === 'paid' ? 'Paid \u00b7 ' + this.money(sum('net')) + ' to ' + pr.length + ' staff' : s.payStatus === 'approved' ? 'Approved \u00b7 ready to pay' : 'Draft \u00b7 check hours from timesheets',
      paySub: s.payStatus === 'paid' ? 'Recorded as paid. This does not move money, email payslips or post to Accounting.' : s.payStatus === 'approved' ? 'Hours are locked. Go back to draft to change them.' : 'A new run starts with hours from clock-ins. Overtime is paid at 1.5\u00d7. Super is 12%. Tax here is an estimate, not the ATO table.',
      paySteps: ['Draft','Approved','Paid'].map((n, i) => { const at = ['draft','approved','paid'].indexOf(s.payStatus); return { name: n, style: 'height:26px;padding:0 10px;border-radius:99px;display:grid;place-items:center;font-size:11.5px;font-weight:700;' + (i <= at ? 'background:currentColor;' : 'border:1px solid currentColor;opacity:.5;') }; }).map((x, i) => ({ ...x, style: x.style.replace('background:currentColor;', 'background:#ffffff;') })),
      payKpis: [['Gross wages', sum('gross')], ['Tax estimate', sum('t')], ['Super (12%)', sum('sup')], ['Net to pay', sum('net')]].map(k => ({ k: k[0], v: this.money(k[1]) })),
      pay: pr.map(r => ({
        name: this.name(r.e), type: r.e.type + (r.e.basis === 'Salary' ? ' \u00b7 salary' : ''), ord: r.p.ord, ot: r.p.ot, locked: locked || r.e.basis === 'Salary' ? true : false,
        inStyle: 'width:100%;height:30px;border:1px solid #dfe6ee;border-radius:7px;padding:0 8px;' + MONO + 'font-size:12.5px;text-align:right;outline:none;' + (locked || r.e.basis === 'Salary' ? 'background:#f6f9fc;color:#7d8b9c;' : 'background:#ffffff;'),
        onOrd: ev => { const v = ev.target.value.replace(/[^0-9.]/g, ''); this.setState(ss => ({ pay: { ...ss.pay, [r.e.id]: { ...ss.pay[r.e.id], ord: v } } })); },
        onOt: ev => { const v = ev.target.value.replace(/[^0-9.]/g, ''); this.setState(ss => ({ pay: { ...ss.pay, [r.e.id]: { ...ss.pay[r.e.id], ot: v } } })); },
        rate: this.money(this.hourly(r.e)), gross: this.money(r.gross), tax: this.money(r.t), sup: this.money(r.sup), net: this.money(r.net)
      })),
      pt: { ord: pr.reduce((a, r) => a + r.ord, 0).toFixed(1), ot: pr.reduce((a, r) => a + r.ot, 0).toFixed(1), gross: this.money(sum('gross')), tax: this.money(sum('t')), sup: this.money(sum('sup')), net: this.money(sum('net')) },

      formOpen: !!f, fTitle: f && f.edit ? 'Edit ' + f.first + ' ' + f.last : 'Add employee',
      fTabs: [['personal','Personal'],['job','Job'],['pay','Pay']].map(t => ({ name: t[1], onClick: () => this.setState({ fTab: t[0] }), style: this.pageTab(s.fTab === t[0]),
        errStyle: fe.some(x => x[0] === t[0]) && (f.first || f.rate || f.edit) ? 'margin-left:6px;width:16px;height:16px;border-radius:50%;background:#d93a3a;color:#ffffff;font-size:10.5px;font-weight:800;display:inline-grid;place-items:center' : 'display:none' })),
      fOnPersonal: s.fTab === 'personal', fOnJob: s.fTab === 'job', fOnPay: s.fTab === 'pay',
      f: f || {}, on: onF,
      fTypes: ['Full-time','Part-time','Casual'].map(t => ({ name: t, onClick: () => this.setState(ss => ({ form: { ...ss.form, type: t } })), style: this.seg(f && f.type === t) })),
      fBasis: ['Hourly','Salary'].map(t => ({ name: t, onClick: () => this.setState(ss => ({ form: { ...ss.form, basis: t } })), style: this.seg(f && f.basis === t) })),
      fRateLabel: f && f.basis === 'Salary' ? 'Annual salary' : 'Hourly rate',
      fRateHint: f && f.basis === 'Salary' ? 'Paid in 26 fortnightly amounts' : f && f.type === 'Casual' ? 'Include 25% casual loading' : 'Award minimum 24.95',
      fPayPreview: f ? 'Estimated fortnight: gross ' + this.money(fGross) + ', tax estimate ' + this.money(tax(fGross)) + ', super ' + this.money(fGross * 0.12) + ', net ' + this.money(fGross - tax(fGross)) + '.' : '',
      fHasErr: !!f && fe.some(x => x[0] === s.fTab) && !!(f.first || f.rate || f.edit), fErrs: fe.filter(x => x[0] === s.fTab).map(x => ({ msg: x[1] })),
      fSave: f && f.edit ? 'Save changes' : 'Add employee',
      saveEmp: () => this.saveEmp(),

      leaveFormOpen: !!lf, lf: lf || {}, onLf: onL,
      empOpts: emps.filter(e => e.status !== 'Inactive').map(e => ({ id: String(e.id), name: this.name(e) })),
      lfTypes: ['Annual','Personal','Unpaid'].map(t => ({ name: t, onClick: () => this.setState(ss => ({ lf: { ...ss.lf, type: t } })), style: this.seg(lf && lf.type === t) })),
      lfDays: String(lfDays),
      lfBal: lfEmp ? (lfEmp.type === 'Casual' && lfKey ? lfEmp.first + ' is casual and has no paid leave. Use Unpaid.' : lfKey ? lfEmp.first + ' has ' + lfEmp[lfKey] + ' days ' + lf.type.toLowerCase() + ' leave' + (lfEmp[lfKey] < lfDays ? ', not enough for ' + lfDays : ', ' + Math.round((lfEmp[lfKey] - lfDays) * 10) / 10 + ' left after') : 'Unpaid leave doesn\u2019t use a balance') : '',
      lfBalStyle: 'border-radius:9px;padding:9px 12px;font-size:12px;' + (lfEmp && lfKey && (lfEmp[lfKey] < lfDays || lfEmp.type === 'Casual') ? 'background:#fdeaea;color:#b02020;' : 'background:#f6f9fc;color:#4a5866;'),
      saveLeave: () => {
        if (!lf.from || !lf.to || !lfDays) { this.toast('To must be on or after From, with at least one working day', 'error'); return; }
        if (lfEmp.type === 'Casual' && lfKey) { this.toast('Casual staff can only take unpaid leave', 'error'); return; }
        this.send(async () => {
          const saved = await api.post('/leave-requests', { employee_id: lf.emp, type: lf.type, date_from: lf.from, date_to: lf.to, note: lf.note || null });
          this.setState({ lf: null, lt: 'Pending' });
          await this.loadLeave();
          this.toast(saved.ref + ' submitted for ' + lfEmp.first + ' \u00b7 ' + saved.days + ' days', 'success');
        });
      },
      closeAll: () => this.setState({ form: null, lf: null }),
      toasts: s.toasts.map(x => ({ msg: x.msg, dot: 'width:8px;height:8px;border-radius:50%;flex:none;background:' + TONE[x.tone][2] }))
    };
  }
}
return Component;
})();
