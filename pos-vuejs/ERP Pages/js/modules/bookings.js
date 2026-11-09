// BookingsLogic
DC.add(function () {
const BookingsLogic = (() => {
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
const ST = { pending:['Pending','warn'], confirmed:['Confirmed','info'], seated:['Seated','purple'], completed:['Completed','success'], noshow:['No-show','error'], cancelled:['Cancelled','neutral'] };
const MONO = "font-family:'IBM Plex Mono',monospace;";
const RES = [
  {id:'t1',name:'Table 1',seats:2,zone:'Window'},{id:'t2',name:'Table 2',seats:2,zone:'Window'},{id:'t3',name:'Table 3',seats:4,zone:'Main floor'},
  {id:'t4',name:'Table 4',seats:4,zone:'Main floor'},{id:'t5',name:'Table 5',seats:4,zone:'Main floor'},{id:'t6',name:'Table 6',seats:6,zone:'Main floor'},
  {id:'t7',name:'Table 7',seats:6,zone:'Back'},{id:'t8',name:'Table 8',seats:8,zone:'Back'},{id:'pr',name:'Party room',seats:20,zone:'Upstairs \u00b7 deposit'}
];
const DAYS = ['28/09','29/09','30/09','01/10','02/10','03/10','04/10'];
const DOW = ['Mon','Tue','Wed','Thu','Fri','Sat','Sun'];
const DOWL = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];
const MON = { '09':'September', '10':'October' };
const TODAY = '29/09', NOW = 14 * 60 + 38;
const OPEN = 11 * 60, CLOSE = 22 * 60;
const CUST = [['fatima','Fatima Hassan','0412 334 901'],['omar','Omar Siddiqui','0433 118 270'],['yusuf','Yusuf Rahman','0401 552 019'],['aisha','Aisha Karim','0422 670 334'],['bilal','Bilal Ahmed','0419 004 822'],['sara','Sara Nguyen','0450 221 778'],['ibrahim','Ibrahim Ali','0438 915 006'],['layla','Layla Haddad','0405 773 140'],['hamza','Hamza Malik','0416 309 552'],['amin','Al Amin Catering','02 9758 1120']];
const t = (h, m) => h * 60 + (m || 0);
const B = (id, day, res, start, dur, cust, size, status, dep, depPaid, source, notes) => ({ id, day, res, start, dur, cust, size, status, dep: dep || 0, depPaid: !!depPaid, source: source || 'Phone', notes: notes || '', log: [] });
const SEED = [
  B('BK-2031','29/09','t3',t(12),60,'yusuf',3,'completed',0,0,'Walk-in'),
  B('BK-2032','29/09','t6',t(12,30),90,'aisha',5,'completed',0,0,'Online','Birthday, bring the cake out with dessert'),
  B('BK-2033','29/09','t1',t(13),60,'sara',2,'noshow',0,0,'Online'),
  B('BK-2034','29/09','t4',t(17,30),90,'bilal',4,'confirmed',0,0,'Phone'),
  B('BK-2035','29/09','t8',t(14),120,'ibrahim',8,'seated',80,1,'Phone','Mixed grill platter pre-ordered \u00b7 order #4516'),
  B('BK-2036','29/09','t2',t(19),90,'layla',2,'confirmed',0,0,'Online','Anniversary'),
  B('BK-2037','29/09','t5',t(19,15),90,'hamza',4,'confirmed',0,0,'Phone','One high chair'),
  B('BK-2038','29/09','pr',t(18,30),180,'amin',18,'confirmed',360,1,'Phone','Family function. Set menu B, no nuts'),
  B('BK-2039','29/09','t3',t(20),90,'omar',4,'pending',0,0,'Online'),
  B('BK-2040','29/09','t7',t(20,30),90,'fatima',6,'confirmed',0,0,'Phone'),
  B('BK-2041','30/09','t6',t(19),90,'yusuf',6,'confirmed',0,0,'Phone'),
  B('BK-2042','30/09','t2',t(12,30),60,'sara',2,'pending',0,0,'Online'),
  B('BK-2043','01/10','t8',t(18,30),120,'aisha',7,'confirmed',70,0,'Online'),
  B('BK-2044','02/10','pr',t(18),240,'fatima',20,'pending',400,0,'Phone','Engagement dinner, needs deposit to confirm'),
  B('BK-2045','02/10','t4',t(19),90,'bilal',4,'confirmed',0,0,'Phone'),
  B('BK-2046','03/10','t6',t(13),90,'hamza',5,'confirmed',0,0,'Online'),
  B('BK-2047','03/10','t3',t(19,30),90,'ibrahim',4,'confirmed',0,0,'Phone'),
  B('BK-2048','03/10','t5',t(19,30),90,'layla',3,'pending',0,0,'Online'),
  B('BK-2049','04/10','t7',t(12),120,'omar',6,'confirmed',0,0,'Phone')
];
const hm = m => String(Math.floor(m / 60)).padStart(2, '0') + ':' + String(m % 60).padStart(2, '0');

class Component extends ModBase {
  _id = 0;
  initialState = { day: TODAY, view: 'timeline', st: 'All', list: SEED, next: 2050, modal: null, sel: null, form: null, cust: CUST.slice(), toasts: [] };
  later(fn, ms) { (this._t = this._t || []).push(setTimeout(fn, ms)); }
  toast(msg, tone) {
    const id = ++this._id;
    this.setState(s => ({ toasts: s.toasts.concat([{ id, msg, tone: tone || 'info' }]).slice(-3) }));
    this.later(() => this.setState(s => ({ toasts: s.toasts.filter(x => x.id !== id) })), 2600);
  }
  money(n) { return (n || 0).toLocaleString('en-AU', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
  badge(x) { return 'background:' + x[0] + ';color:' + x[1] + ';border-radius:5px;padding:3px 8px;font-size:11.5px;font-weight:600;white-space:nowrap'; }
  seg(on) { return 'flex:1;height:30px;padding:0 10px;border-radius:7px;display:grid;place-items:center;font-size:12.5px;cursor:pointer;white-space:nowrap;' + (on ? 'background:#ffffff;color:#0f1c2b;font-weight:700;box-shadow:0 1px 3px rgba(15,28,43,.14);' : 'color:#5d6b7d;font-weight:500;'); }
  pageTab(on) { return 'padding:9px 14px;font-size:13px;cursor:pointer;border-bottom:2px solid ' + (on ? '#1668c9;color:#1668c9;font-weight:600;' : 'transparent;color:#5d6b7d;font-weight:500;'); }
  cust(id) { const c = this.state.cust.find(x => x[0] === id); return c ? { id: c[0], name: c[1], phone: c[2] } : { id, name: id, phone: '' }; }
  res(id) { return RES.find(r => r.id === id); }
  dayIdx(d) { return DAYS.indexOf(d); }
  dayLabel(d, long) { const i = this.dayIdx(d); const p = d.split('/'); return (long ? DOWL[i] : DOW[i]) + ' ' + parseInt(p[0], 10) + ' ' + (long ? MON[p[1]] : MON[p[1]].slice(0, 3)); }
  active(b) { return b.status !== 'cancelled' && b.status !== 'noshow'; }
  clash(day, res, start, dur, skip) { return this.state.list.find(b => b.id !== skip && b.day === day && b.res === res && this.active(b) && b.status !== 'completed' && start < b.start + b.dur && b.start < start + dur); }
  logAdd(b, what, tone) { return [[what, this.dayLabel(TODAY) + ' \u00b7 ' + hm(NOW + this._id), tone]].concat(b.log); }
  update(id, patch, msg, tone) {
    this.setState(s => ({ list: s.list.map(b => b.id === id ? { ...b, ...patch, log: patch.status ? this.logAdd(b, ST[patch.status][0] + ' by Aminul', ST[patch.status][1]) : b.log } : b) }));
    if (msg) this.toast(msg, tone || 'success');
  }
  newForm(p) {
    const s = this.state;
    const start = p && p.start !== undefined ? p.start : (s.day === TODAY ? Math.max(OPEN, Math.ceil((NOW + 20) / 15) * 15) : t(19));
    this.setState({ modal: 'form', form: { edit: null, cust: '', name: '', phone: '', size: 2, day: s.day, start: Math.min(start, CLOSE - 60), dur: 90, res: p && p.res ? p.res : '', dep: '', source: 'Phone', notes: '' } });
  }
  editForm(b) { this.setState({ modal: 'form', form: { edit: b.id, cust: b.cust, name: '', phone: '', size: b.size, day: b.day, start: b.start, dur: b.dur, res: b.res, dep: b.dep ? String(b.dep) : '', source: b.source, notes: b.notes } }); }
  setF(p) { this.setState(s => ({ form: { ...s.form, ...p } })); }
  fit(f) {
    const free = RES.filter(r => r.seats >= f.size && !this.clash(f.day, r.id, f.start, f.dur, f.edit) && (r.id !== 'pr' || f.size > 8));
    return free.sort((a, b) => a.seats - b.seats)[0];
  }
  fErrs() {
    const f = this.state.form, e = [];
    if (!f.cust) e.push('Choose a customer');
    if (f.cust === 'new' && (!f.name.trim() || f.phone.replace(/\D/g, '').length < 8)) e.push('Enter the new customer\u2019s name and mobile');
    if (!f.res) e.push('Pick a table or room');
    else {
      const r = this.res(f.res);
      if (r.seats < f.size) e.push(r.name + ' seats ' + r.seats + ', party is ' + f.size);
      const c = this.clash(f.day, f.res, f.start, f.dur, f.edit);
      if (c) e.push(r.name + ' is booked ' + hm(c.start) + '\u2013' + hm(c.start + c.dur) + ' by ' + this.cust(c.cust).name);
    }
    if (f.start + f.dur > CLOSE) e.push('Ends after closing (22:00)');
    if (f.day === TODAY && f.start < NOW && !f.edit) e.push('That time has passed');
    return e;
  }
  save() {
    const s = this.state, f = s.form;
    const e = this.fErrs();
    if (e.length) { this.toast(e[0], 'error'); return; }
    let cust = s.cust, cid = f.cust;
    if (f.cust === 'new') { cid = 'c' + Date.now().toString(36); cust = cust.concat([[cid, f.name.trim(), f.phone.trim()]]); }
    const dep = Math.round((parseFloat(f.dep) || 0) * 100) / 100;
    if (f.edit) {
      this.setState({ cust, modal: 'detail', sel: f.edit, form: null, day: f.day,
        list: s.list.map(b => b.id === f.edit ? { ...b, cust: cid, size: f.size, day: f.day, start: f.start, dur: f.dur, res: f.res, dep, source: f.source, notes: f.notes, log: this.logAdd(b, 'Edited by Aminul', 'neutral') } : b) });
      this.toast(f.edit + ' updated', 'success');
      return;
    }
    const id = 'BK-' + s.next;
    const needsDep = f.res === 'pr' && dep > 0;
    const b = { ...B(id, f.day, f.res, f.start, f.dur, cid, f.size, needsDep ? 'pending' : 'confirmed', dep, 0, f.source, f.notes), log: [['Created by Aminul' + (needsDep ? ', waiting on deposit' : ''), this.dayLabel(TODAY) + ' \u00b7 ' + hm(NOW), 'neutral']] };
    this.setState({ cust, list: s.list.concat([b]), next: s.next + 1, modal: 'detail', sel: id, form: null, day: f.day });
    this.toast(id + ' \u00b7 ' + this.res(f.res).name + ' at ' + hm(f.start) + (needsDep ? ' \u00b7 pending deposit' : ' \u00b7 confirmation SMS sent'), 'success');
  }

  renderVals() {
    const s = this.state;
    const today = s.day === TODAY;
    const dayList = s.list.filter(b => b.day === s.day).sort((a, b) => a.start - b.start);
    const live = dayList.filter(b => this.active(b));
    const covers = live.reduce((q, b) => q + b.size, 0);
    const busyNow = today ? RES.filter(r => live.some(b => b.res === r.id && b.status !== 'completed' && b.start <= NOW && NOW < b.start + b.dur)).length : 0;
    const depHeld = s.list.filter(b => b.depPaid && this.active(b) && b.status !== 'completed').reduce((q, b) => q + b.dep, 0);
    const pend = dayList.filter(b => b.status === 'pending').length;
    const span = CLOSE - OPEN;
    const f = s.form;
    const fe = f ? this.fErrs() : [];
    const sel = s.sel ? s.list.find(b => b.id === s.sel) : null;
    const A = (label, fn, kind) => ({ label, onClick: fn, style: 'height:36px;padding:0 14px;border-radius:8px;display:flex;align-items:center;font-size:12.5px;font-weight:600;cursor:pointer;' + (kind === 'p' ? 'background:#1668c9;color:#ffffff;' : kind === 'g' ? 'background:#2f9e63;color:#ffffff;' : kind === 'd' ? 'border:1px solid #f3cfcf;background:#fdf4f4;color:#b02020;' : 'border:1px solid #dfe6ee;background:#ffffff;color:#4a5866;') });
    let actions = [];
    if (sel) {
      const st = sel.status;
      const up = (stt, msg, tone) => () => this.update(sel.id, { status: stt }, msg, tone);
      if (st === 'pending') actions = [A('Cancel booking', up('cancelled', sel.id + ' cancelled', 'neutral'), 'd'), A('Edit', () => this.editForm(sel)), A('Confirm', () => { if (sel.res === 'pr' && sel.dep && !sel.depPaid) { this.toast('Take the ' + this.money(sel.dep) + ' deposit first', 'warn'); return; } this.update(sel.id, { status: 'confirmed' }, sel.id + ' confirmed \u00b7 SMS sent'); }, 'p')];
      else if (st === 'confirmed') actions = [A('No-show', up('noshow', sel.id + ' marked no-show', 'warn'), 'd'), A('Edit', () => this.editForm(sel)), A('Send reminder', () => this.toast('Reminder SMS sent to ' + this.cust(sel.cust).phone, 'neutral'))].concat(sel.day === TODAY ? [A('Seat guests', up('seated', this.cust(sel.cust).name + ' seated at ' + this.res(sel.res).name), 'p')] : []);
      else if (st === 'seated') actions = [A('Open order in POS', () => this.toast('Order opened for ' + this.res(sel.res).name + (sel.depPaid ? ' \u00b7 ' + this.money(sel.dep) + ' deposit applied' : ''), 'info')), A('Complete', up('completed', sel.id + ' completed \u00b7 ' + this.res(sel.res).name + ' is free'), 'g')];
      else if (st === 'completed') actions = [A('View bill', () => this.toast('Opens the sale in Sell', 'neutral'))];
      else actions = [A('Rebook', () => { this.editForm(sel); this.setF({ edit: null, day: TODAY === sel.day ? sel.day : sel.day, start: Math.max(sel.start, Math.ceil((NOW + 20) / 15) * 15) }); })];
    }
    const dPaid = sel && sel.depPaid;

    return {
      nav: NAV.map(n => {
        const on = n[0] === 'Bookings';
        return {
          name: n[0], key: n[1],
          style: 'display:flex;align-items:center;gap:10px;padding:8px 10px;border-radius:8px;cursor:pointer;font-size:13px;font-weight:500;margin-bottom:1px;' + (on ? 'background:rgba(255,255,255,.13);color:#ffffff;box-shadow:inset 2px 0 0 #4da3e8;' : 'color:#c4dced;'),
          iconStyle: 'width:20px;height:20px;border-radius:5px;display:grid;place-items:center;' + MONO + 'font-size:9px;font-weight:600;flex:none;' + (on ? 'background:#4da3e8;color:#06283a;' : 'background:rgba(255,255,255,.1);color:#9dbdd2;'),
          caretStyle: 'color:#7fa3bb;font-size:11px;flex:none;' + (n[2] ? '' : 'visibility:hidden;')
        };
      }),
      crumb: s.view === 'timeline' ? 'Table plan' : 'List',
      dayTitle: this.dayLabel(s.day, true), daySub: today ? 'Today \u00b7 open 11:00\u201322:00' : 'Open 11:00\u201322:00',
      prevDay: () => { const i = this.dayIdx(s.day); if (i > 0) this.setState({ day: DAYS[i - 1] }); },
      nextDay: () => { const i = this.dayIdx(s.day); if (i < DAYS.length - 1) this.setState({ day: DAYS[i + 1] }); },
      goToday: () => this.setState({ day: TODAY }),
      todayStyle: 'height:32px;padding:0 12px;border-radius:8px;border:1px solid #dfe6ee;display:flex;align-items:center;font-size:12.5px;font-weight:600;cursor:pointer;' + (today ? 'color:#9aa7b6;' : 'color:#1668c9;'),
      views: [['timeline','Table plan'],['list','List']].map(v => ({ name: v[1], onClick: () => this.setState({ view: v[0] }), style: this.seg(s.view === v[0]) })),
      newBooking: () => this.newForm(null),
      week: DAYS.map((d, i) => {
        const on = d === s.day;
        const n = s.list.filter(b => b.day === d && this.active(b)).length;
        return {
          dow: DOW[i], num: parseInt(d.split('/')[0], 10), count: n ? n + ' booked' : 'none',
          onClick: () => this.setState({ day: d }),
          style: 'flex:1;min-width:86px;border-radius:10px;padding:7px 10px;cursor:pointer;display:grid;gap:1px;text-align:center;' + (on ? 'background:#0f1c2b;color:#ffffff;' : d === TODAY ? 'background:#eaf1fb;color:#14549e;' : 'background:#f6f9fc;color:#0f1c2b;'),
          countStyle: 'font-size:10.5px;' + (on ? 'color:#b9c7d6;' : 'color:#8b98a8;')
        };
      }),
      kpis: [
        { k: 'Bookings', v: String(live.length), n: pend ? pend + ' waiting to confirm' : 'All confirmed' },
        { k: 'Covers', v: String(covers), n: 'guests expected' },
        { k: today ? 'Tables in use now' : 'Busiest table', v: today ? busyNow + ' / ' + RES.length : (live.length ? this.res(live.slice().sort((a, b) => b.dur - a.dur)[0].res).name : '\u2014'), n: today ? 'at ' + hm(NOW) : 'by booked time' },
        { k: 'Deposits held', v: this.money(depHeld), n: 'for upcoming bookings' }
      ],
      isTimeline: s.view === 'timeline', isList: s.view === 'list',
      legend: ['pending','confirmed','seated','completed'].map(k => ({ name: ST[k][0], sw: 'width:10px;height:10px;border-radius:3px;background:' + TONE[ST[k][1]][2] })),
      hours: Array.from({ length: 11 }, (_, i) => ({ label: hm(OPEN + i * 60) })),
      res: RES.map(r => ({
        name: r.name, meta: r.seats + ' seats \u00b7 ' + r.zone,
        slots: Array.from({ length: span / 30 }, (_, i) => {
          const st = OPEN + i * 30;
          const past = today && st + 30 <= NOW;
          return {
            tip: past ? '' : 'Book ' + r.name + ' at ' + hm(st),
            onClick: () => { if (past) return; this.newForm({ res: r.id, start: st }); },
            style: 'flex:1;height:100%;' + (i % 2 === 0 ? 'border-left:1px solid #eaeff5;' : 'border-left:1px dashed #f2f5f8;') + (past ? 'background:#fafbfc;' : 'cursor:pointer;')
          };
        }),
        blocks: dayList.filter(b => b.res === r.id && b.status !== 'cancelled').map(b => {
          const tn = TONE[ST[b.status][1]];
          const c = this.cust(b.cust);
          return {
            name: c.name + ' \u00b7 ' + b.size,
            meta: hm(b.start) + '\u2013' + hm(b.start + b.dur) + (b.notes ? ' \u00b7 ' + b.notes : ''),
            onClick: () => this.setState({ modal: 'detail', sel: b.id }),
            style: 'position:absolute;top:5px;bottom:5px;left:calc(' + ((b.start - OPEN) / span * 100) + '% + 2px);width:calc(' + (Math.min(b.dur, CLOSE - b.start) / span * 100) + '% - 4px);border-radius:7px;padding:5px 8px;cursor:pointer;overflow:hidden;z-index:2;'
              + 'background:' + tn[0] + ';color:' + tn[1] + ';box-shadow:inset 3px 0 0 ' + tn[2] + ';' + (b.status === 'noshow' ? 'opacity:.55;text-decoration:line-through;' : '') + (s.sel === b.id && s.modal === 'detail' ? 'outline:2px solid ' + tn[2] + ';' : '')
          };
        })
      })),
      showNow: today && NOW > OPEN && NOW < CLOSE,
      nowLabel: hm(NOW),
      nowStyle: 'position:absolute;top:30px;bottom:0;width:2px;background:#d93a3a;z-index:3;pointer-events:none;left:' + ((NOW - OPEN) / span * 100) + '%',
      statusTabs: ['All','Pending','Confirmed','Seated','Completed','No-show','Cancelled'].map(k => {
        const n = k === 'All' ? dayList.length : dayList.filter(b => ST[b.status][0] === k).length;
        return { name: k, count: n, onClick: () => this.setState({ st: k }), style: this.pageTab(s.st === k),
          countStyle: MONO + 'font-size:10.5px;border-radius:4px;padding:1px 5px;margin-left:6px;' + (s.st === k ? 'background:#eaf1fb;color:#14549e;' : 'background:#eef2f6;color:#7d8b9c;') };
      }),
      list: dayList.filter(b => s.st === 'All' || ST[b.status][0] === s.st).map(b => {
        const c = this.cust(b.cust);
        return {
          time: hm(b.start) + '\u2013' + hm(b.start + b.dur), name: c.name, sub: c.phone + (b.notes ? ' \u00b7 ' + b.notes : ''),
          size: b.size, res: this.res(b.res).name, status: ST[b.status][0], badge: this.badge(TONE[ST[b.status][1]]),
          dep: b.dep ? this.money(b.dep) : '\u2014', depStyle: MONO + 'font-size:12.5px;text-align:right;' + (b.dep && !b.depPaid ? 'color:#8a5300;' : b.dep ? 'color:#0f6b43;' : 'color:#9aa7b6;'),
          source: b.source, onClick: () => this.setState({ modal: 'detail', sel: b.id })
        };
      }),
      noList: !dayList.filter(b => s.st === 'All' || ST[b.status][0] === s.st).length,

      detailOpen: s.modal === 'detail' && !!sel,
      d: sel ? {
        id: sel.id, name: this.cust(sel.cust).name, status: ST[sel.status][0], badge: this.badge(TONE[ST[sel.status][1]]),
        when: this.dayLabel(sel.day, true) + ' \u00b7 ' + hm(sel.start) + '\u2013' + hm(sel.start + sel.dur),
        facts: [{ k: 'Table', v: this.res(sel.res).name + ' \u00b7 ' + this.res(sel.res).seats + ' seats' }, { k: 'Party', v: sel.size + (sel.size === 1 ? ' guest' : ' guests') }, { k: 'Mobile', v: this.cust(sel.cust).phone }, { k: 'Source', v: sel.source }, { k: 'Duration', v: sel.dur + ' min' }, { k: 'Zone', v: this.res(sel.res).zone }],
        depTitle: sel.dep ? (dPaid ? 'Deposit paid \u00b7 ' + this.money(sel.dep) : 'Deposit due \u00b7 ' + this.money(sel.dep)) : 'No deposit',
        depSub: sel.dep ? (dPaid ? 'Taken by card. Applied to the bill when the order closes' : 'Booking stays pending until it\u2019s paid') : 'Party room bookings need a 20 per head deposit',
        depBox: 'display:flex;align-items:center;gap:12px;border-radius:10px;padding:11px 13px;' + (sel.dep ? (dPaid ? 'background:#e7f6ee;color:#0f6b43;' : 'background:#fdf1dd;color:#8a5300;') : 'background:#f6f9fc;color:#5d6b7d;'),
        canTakeDep: !!sel.dep && !dPaid && this.active(sel),
        hasNotes: !!sel.notes, notes: sel.notes,
        log: (sel.log.length ? sel.log : [['Created by Aminul via ' + sel.source.toLowerCase(), this.dayLabel(sel.day) + ' \u00b7 earlier', 'neutral']]).map(a => ({ what: a[0], when: a[1], dot: 'width:8px;height:8px;border-radius:50%;margin-top:5px;flex:none;background:' + TONE[a[2]][2] })),
        actions
      } : { id:'', name:'', status:'', badge:'', when:'', facts:[], depTitle:'', depSub:'', depBox:'', canTakeDep:false, hasNotes:false, notes:'', log:[], actions:[] },
      takeDeposit: () => { this.setState(ss => ({ list: ss.list.map(b => b.id === sel.id ? { ...b, depPaid: true, status: b.status === 'pending' ? 'confirmed' : b.status, log: this.logAdd(b, 'Deposit ' + this.money(b.dep) + ' paid by card' + (b.status === 'pending' ? ', booking confirmed' : ''), 'success') } : b) })); this.toast('Deposit ' + this.money(sel.dep) + ' taken \u00b7 ' + sel.id + ' confirmed', 'success'); },

      formOpen: s.modal === 'form' && !!f,
      fTitle: f && f.edit ? 'Edit ' + f.edit : 'New booking',
      fCust: f ? f.cust : '', custOpts: s.cust.map(c => ({ id: c[0], name: c[1] + ' \u00b7 ' + c[2] })),
      onCust: e => this.setF({ cust: e.target.value }),
      fIsNew: !!f && f.cust === 'new', fName: f ? f.name : '', fPhone: f ? f.phone : '',
      onName: e => this.setF({ name: e.target.value }), onPhone: e => this.setF({ phone: e.target.value }),
      fSize: f ? f.size : 2,
      sizeDown: () => this.setF({ size: Math.max(1, f.size - 1) }),
      sizeUp: () => this.setF({ size: Math.min(20, f.size + 1), dep: f.res === 'pr' ? String((f.size + 1) * 20) : f.dep }),
      fDay: f ? f.day : '', dayOpts: DAYS.filter(d => this.dayIdx(d) >= this.dayIdx(TODAY)).map(d => ({ id: d, name: this.dayLabel(d) + (d === TODAY ? ' (today)' : '') })),
      onDay: e => this.setF({ day: e.target.value }),
      fStart: f ? String(f.start) : '', timeOpts: Array.from({ length: (CLOSE - 60 - OPEN) / 15 + 1 }, (_, i) => ({ id: String(OPEN + i * 15), name: hm(OPEN + i * 15) })),
      onStart: e => this.setF({ start: parseInt(e.target.value, 10) }),
      durs: [60, 90, 120, 180].map(d => ({ name: d < 120 ? d + 'm' : d / 60 + 'h', onClick: () => this.setF({ dur: d }), style: this.seg(f && f.dur === d) })),
      bestFit: () => { const r = this.fit(f); if (r) { this.setF({ res: r.id, dep: r.id === 'pr' ? String(f.size * 20) : f.dep }); this.toast(r.name + ' fits ' + f.size + ' and is free', 'neutral'); } else this.toast('Nothing free for ' + f.size + ' at ' + hm(f.start) + '. Try another time', 'warn'); },
      resOpts: f ? RES.map(r => {
        const on = f.res === r.id;
        const c = this.clash(f.day, r.id, f.start, f.dur, f.edit);
        const small = r.seats < f.size;
        return {
          name: r.name,
          note: c ? 'Booked ' + hm(c.start) : small ? r.seats + ' seats \u00b7 too small' : r.seats + ' seats \u00b7 free',
          noteStyle: 'font-size:10.5px;margin-top:1px;' + (c ? 'color:#b02020;' : small ? 'color:#9aa7b6;' : 'color:#0f6b43;'),
          onClick: () => this.setF({ res: r.id, dep: r.id === 'pr' ? String(f.size * 20) : (f.res === 'pr' ? '' : f.dep) }),
          style: 'border-radius:9px;padding:8px 10px;cursor:pointer;' + (on ? 'background:#f4f9ff;box-shadow:inset 0 0 0 1.5px #1668c9;' : 'border:1px solid #dfe6ee;') + ((c || small) && !on ? 'opacity:.6;' : '')
        };
      }) : [],
      fDep: f ? f.dep : '', onDep: e => this.setF({ dep: e.target.value.replace(/[^0-9.]/g, '') }),
      depHint: f && f.res === 'pr' ? '20 per head for the party room' : 'Optional for tables',
      fSource: f ? f.source : 'Phone', onSource: e => this.setF({ source: e.target.value }),
      fNotes: f ? f.notes : '', onNotes: e => this.setF({ notes: e.target.value }),
      fHasErr: !!f && fe.length > 0 && !!(f.cust || f.res), fErrs: fe.map(m => ({ msg: m })),
      fSummary: f && f.res ? this.res(f.res).name + ' \u00b7 ' + this.dayLabel(f.day) + ' \u00b7 ' + hm(f.start) + '\u2013' + hm(f.start + f.dur) : '',
      saveLabel: f && f.edit ? 'Save changes' : 'Create booking',
      saveStyle: 'height:34px;padding:0 15px;border-radius:8px;display:flex;align-items:center;font-size:12.5px;font-weight:600;color:#ffffff;cursor:pointer;background:' + (fe.length ? '#9fbde0' : '#1668c9'),
      save: () => this.save(),
      closeAll: () => this.setState({ modal: null, form: null }),

      toasts: s.toasts.map(x => ({ msg: x.msg, dot: 'width:8px;height:8px;border-radius:50%;flex:none;background:' + TONE[x.tone][2] }))
    };
  }
}
return Component;
})();
});
