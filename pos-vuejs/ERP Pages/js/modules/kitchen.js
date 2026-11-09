// KitchenLogic
DC.add(function () {
const KitchenLogic = (() => {
const MONO = "font-family:'IBM Plex Mono',monospace;";
const STN = { grill:['Grill','#c2410c'], cold:['Cold','#0f8b8d'], fry:['Fryer','#8a5300'] };
const SRC = { dine:['Dine-in','#eaf1fb','#14549e'], take:['Takeaway','#fdf1dd','#8a5300'], deliv:['Delivery','#f3ecfd','#5b34a8'], online:['Online','#e7f6ee','#0f6b43'] };
const MENU = [['Mixed grill platter','grill',14],['Lamb chops plate','grill',12],['Chicken shish wrap','grill',8],['Beef burger','grill',9],['Kofta plate','grill',10],['Hummus','cold',3],['Tabbouleh','cold',4],['Fattoush','cold',4],['Garlic sauce tub','cold',2],['Chips','fry',6],['Falafel \u00d76','fry',6],['Chicken wings','fry',9]];
const BASE = 14 * 3600 + 38 * 60;
const I = (q, n, note, done) => { const m = MENU.find(x => x[0] === n); return { q, name: n, station: m[1], note: note || '', done: !!done }; };
const T = (no, src, where, ago, status, items, extra) => ({ no, src, where, placed: BASE - ago, status, items, rush: false, note: '', bumped: null, ...(extra || {}) });
const SEED = [
  T('#4518','dine','Table 6 \u00b7 5 guests',60,'new',[I(2,'Mixed grill platter'),I(1,'Fattoush'),I(2,'Chips')]),
  T('#4519','online','Pickup \u00b7 Hamza',25,'new',[I(3,'Chicken shish wrap','No onion'),I(1,'Garlic sauce tub')]),
  T('#4520','deliv','Uber Eats \u00b7 driver 6 min',10,'new',[I(1,'Beef burger','Well done'),I(1,'Chips'),I(1,'Chicken wings')]),
  T('#4514','dine','Table 4 \u00b7 4 guests',780,'prep',[I(2,'Lamb chops plate','Medium',1),I(1,'Hummus','',1),I(1,'Tabbouleh'),I(2,'Chips')],{ started: BASE - 620 }),
  T('#4515','take','Counter \u00b7 Yusuf',1080,'prep',[I(2,'Kofta plate'),I(1,'Falafel \u00d76','',1)],{ started: BASE - 900, rush: true, note:'Customer waiting at counter' }),
  T('#4516','dine','Table 8 \u00b7 8 guests',540,'prep',[I(3,'Mixed grill platter'),I(2,'Fattoush','',1),I(4,'Chips','',1),I(1,'Chicken wings')],{ started: BASE - 420, note:'Pre-ordered with booking BK-2035' }),
  T('#4517','online','Pickup \u00b7 Layla',300,'prep',[I(1,'Chicken shish wrap'),I(1,'Hummus','',1)],{ started: BASE - 200 }),
  T('#4512','dine','Table 3 \u00b7 2 guests',960,'ready',[I(2,'Kofta plate','',1),I(1,'Hummus','',1)],{ started: BASE - 900, readyAt: BASE - 120 }),
  T('#4513','deliv','DoorDash \u00b7 driver here',720,'ready',[I(2,'Beef burger','',1),I(2,'Chips','',1)],{ started: BASE - 650, readyAt: BASE - 40 }),
  T('#4510','take','Counter \u00b7 Omar',1700,'served',[I(1,'Lamb chops plate','',1)],{ started: BASE - 1600, readyAt: BASE - 900, servedAt: BASE - 700 }),
  T('#4511','dine','Table 1 \u00b7 2 guests',1500,'served',[I(1,'Mixed grill platter','',1),I(1,'Tabbouleh','',1)],{ started: BASE - 1400, readyAt: BASE - 600, servedAt: BASE - 480 })
];
const WHERE = [['dine','Table 2 \u00b7 2 guests'],['dine','Table 5 \u00b7 4 guests'],['take','Counter \u00b7 walk-in'],['online','Pickup \u00b7 Fatima'],['deliv','Uber Eats \u00b7 driver 9 min'],['deliv','DoorDash \u00b7 driver 12 min'],['dine','Table 7 \u00b7 6 guests']];
const fmt = s => { s = Math.max(0, Math.floor(s)); return String(Math.floor(s / 60)).padStart(2, '0') + ':' + String(s % 60).padStart(2, '0'); };

class Component extends ModBase {
  initialState = { tickets: SEED, station: 'all', allDay: true, sound: true, next: 4521, undo: null, flash: null, t0: 0, now: BASE, lastArr: BASE };
  ensureClock() {
    if (this._tick) return;
    const t0 = Date.now();
    this._tick = setInterval(() => {
      if (this._host.state.view !== 'kitchen') return;
      const now = BASE + Math.floor((Date.now() - t0) / 1000);
      const s = this.state;
      const p = { now };
      if (now - s.lastArr >= 75) { Object.assign(p, this.makeTicket(now)); p.lastArr = now; }
      this.setState(p);
    }, 1000);
  }
  makeTicket(now) {
    const s = this.state;
    const n = s.next;
    const w = WHERE[n % WHERE.length];
    const picks = [MENU[n % 5], MENU[5 + (n % 4)], MENU[9 + (n % 3)]].slice(0, 2 + (n % 2));
    const t = T('#' + n, w[0], w[1], 0, 'new', picks.map((m, i) => I(1 + ((n + i) % 2), m[0])));
    t.placed = now;
    this.flashMsg('New ticket ' + t.no + ' \u00b7 ' + t.where);
    return { tickets: s.tickets.concat([t]), next: n + 1 };
  }
  flashMsg(m) {
    clearTimeout(this._fl);
    this.setState({ flash: m });
    this._fl = setTimeout(() => this.setState({ flash: null }), 2600);
  }
  target(t) { return Math.max(...t.items.map(i => MENU.find(m => m[0] === i.name)[2])) * 60 + (t.items.length > 3 ? 180 : 0); }
  bump(t, to) {
    const s = this.state;
    const prev = { ...t, items: t.items.map(i => ({ ...i })) };
    const patch = { status: to };
    if (to === 'prep' && !t.started) patch.started = s.now;
    if (to === 'ready') { patch.readyAt = s.now; patch.items = t.items.map(i => ({ ...i, done: true })); }
    if (to === 'served') patch.servedAt = s.now;
    const label = { prep: 'Preparing', ready: 'Ready', served: t.src === 'dine' ? 'Served' : 'Picked up', new: 'New' }[to];
    clearTimeout(this._ud);
    this.setState({ tickets: s.tickets.map(x => x.no === t.no ? { ...x, ...patch } : x), undo: { prev, text: t.no + ' moved to ' + label } });
    this._ud = setTimeout(() => this.setState({ undo: null }), 5000);
  }
  toggleItem(t, idx) {
    const s = this.state;
    const items = t.items.map((i, j) => j === idx ? { ...i, done: !i.done } : i);
    const patch = { items };
    if (t.status === 'new') { patch.status = 'prep'; patch.started = s.now; }
    this.setState({ tickets: s.tickets.map(x => x.no === t.no ? { ...x, ...patch } : x) });
    if (items.every(i => i.done) && t.status !== 'ready') this.flashMsg(t.no + ' all items done \u00b7 tap Ready to call it');
  }

  renderVals() {
    this.ensureClock();
    const s = this.state;
    const now = s.now;
    const inSt = i => s.station === 'all' || i.station === s.station;
    const vis = s.tickets.filter(t => s.station === 'all' || t.items.some(inSt));
    const open = s.tickets.filter(t => t.status === 'new' || t.status === 'prep');
    const overdue = open.filter(t => now - t.placed > this.target(t));
    const served = s.tickets.filter(t => t.servedAt);
    const avg = served.length ? served.reduce((q, t) => q + (t.readyAt - t.placed), 0) / served.length : 0;
    const COLS = [['new','New','#4da3e8','No new tickets'],['prep','Preparing','#d98c1a','Nothing cooking'],['ready','Ready','#2f9e63','Nothing waiting'],['served','Served','#8fa3b6','Nothing served yet']];
    const all = {};
    open.forEach(t => t.items.filter(i => !i.done && inSt(i)).forEach(i => { const k = i.name; all[k] = all[k] || { q: 0, n: 0, st: i.station }; all[k].q += i.q; all[k].n += 1; }));
    const adRows = Object.keys(all).sort((a, b) => all[b].q - all[a].q).map(k => ({ name: k, q: all[k].q, station: STN[all[k].st][0], tickets: all[k].n + (all[k].n === 1 ? ' ticket' : ' tickets') }));
    const pill = on => 'height:34px;padding:0 12px;border-radius:8px;display:flex;align-items:center;font-size:12.5px;font-weight:600;cursor:pointer;' + (on ? 'background:rgba(77,163,232,.2);color:#9cd0f5;border:1px solid rgba(77,163,232,.45);' : 'border:1px solid rgba(255,255,255,.14);color:#8fa3b6;');

    return {
      clock: String(Math.floor(now / 3600)).padStart(2, '0') + ':' + String(Math.floor(now % 3600 / 60)).padStart(2, '0'),
      activeLabel: open.length + ' open \u00b7 ' + s.tickets.filter(t => t.status === 'ready').length + ' ready',
      hasOverdue: overdue.length > 0, overdueLabel: overdue.length + ' over target',
      avgLabel: avg ? 'Avg ticket ' + fmt(avg) : '',
      stations: [['all','All stations']].concat(Object.keys(STN).map(k => [k, STN[k][0]])).map(x => {
        const on = s.station === x[0];
        const n = open.filter(t => x[0] === 'all' || t.items.some(i => i.station === x[0] && !i.done)).length;
        return { name: x[1], count: n, onClick: () => this.setState({ station: x[0] }),
          style: 'padding:7px 12px;border-radius:6px;font-size:12.5px;cursor:pointer;display:flex;align-items:center;gap:6px;' + (on ? 'background:#1668c9;color:#ffffff;font-weight:600;' : 'color:#8fa3b6;font-weight:500;'),
          countStyle: MONO + 'font-size:10.5px;border-radius:4px;padding:1px 5px;' + (on ? 'background:rgba(255,255,255,.2);' : 'background:rgba(255,255,255,.08);') };
      }),
      toggleAllDay: () => this.setState({ allDay: !s.allDay }), allDayBtn: pill(s.allDay),
      toggleSound: () => this.setState({ sound: !s.sound }), soundBtn: pill(s.sound), soundLabel: s.sound ? 'Chime on' : 'Chime off',
      simulate: () => this.setState(this.makeTicket(now)),
      columns: COLS.map(c => {
        let list = vis.filter(t => t.status === c[0]);
        if (c[0] === 'served') list = list.sort((a, b) => b.servedAt - a.servedAt).slice(0, 5);
        else list = list.sort((a, b) => (b.rush - a.rush) || (a.placed - b.placed));
        return {
          name: c[1], n: vis.filter(t => t.status === c[0]).length, empty: !list.length, emptyText: c[3],
          wrap: 'flex:' + (c[0] === 'served' ? '0.75' : '1') + ';min-width:0;background:#0f1c2b;display:flex;flex-direction:column;min-height:0',
          dot: 'width:10px;height:10px;border-radius:50%;background:' + c[2],
          tickets: list.map(t => {
            const tg = this.target(t);
            const end = t.status === 'ready' || t.status === 'served' ? t.readyAt : now;
            const el = end - t.placed;
            const pct = Math.min(100, el / tg * 100);
            const over = el > tg, warn = el > tg * 0.7;
            const done = t.status === 'served';
            const tone = done ? '#8fa3b6' : t.status === 'ready' ? '#2f9e63' : over ? '#d93a3a' : warn ? '#d98c1a' : '#2f9e63';
            const src = SRC[t.src];
            const sec = [];
            if (t.status === 'new' || t.status === 'prep') sec.push({ label: t.rush ? 'Un-rush' : 'Rush', onClick: () => this.setState(ss => ({ tickets: ss.tickets.map(x => x.no === t.no ? { ...x, rush: !x.rush } : x) })) });
            if (t.status === 'ready') sec.push({ label: 'Recall', onClick: () => this.bump(t, 'prep') });
            if (t.status === 'served') sec.push({ label: 'Recall', onClick: () => this.bump(t, 'ready') });
            const act = { new: ['Start', 'prep', '#1668c9'], prep: ['Ready', 'ready', '#2f9e63'], ready: [t.src === 'dine' ? 'Served' : 'Picked up', 'served', '#0f1c2b'] }[t.status];
            return {
              no: t.no, where: t.where, src: src[0],
              srcStyle: 'background:' + src[1] + ';color:' + src[2] + ';border-radius:5px;padding:2px 7px;font-size:10.5px;font-weight:700',
              rushStyle: t.rush && !done ? 'background:#d93a3a;color:#ffffff;border-radius:5px;padding:2px 7px;font-size:10.5px;font-weight:800;letter-spacing:.05em' : 'display:none',
              time: fmt(el), target: done ? 'served ' + fmt(now - t.servedAt) + ' ago' : t.status === 'ready' ? 'waiting ' + fmt(now - t.readyAt) : 'target ' + Math.round(tg / 60) + ' min',
              timeStyle: MONO + 'font-size:20px;font-weight:700;letter-spacing:-0.02em;color:' + tone,
              bar: 'height:100%;border-radius:99px;width:' + pct + '%;background:' + tone,
              card: 'background:#ffffff;border-radius:13px;padding:13px 14px;color:#0f1c2b;' + (done ? 'opacity:.6;' : '') + (over && !done && t.status !== 'ready' ? 'box-shadow:0 0 0 3px #d93a3a;' : t.rush && !done ? 'box-shadow:0 0 0 3px #f59e0b;' : ''),
              items: t.items.map((i, idx) => {
                const mine = inSt(i);
                const canTick = t.status === 'new' || t.status === 'prep';
                return {
                  q: i.q, name: i.name, note: i.note, station: STN[i.station][0],
                  onClick: () => { if (canTick && mine) this.toggleItem(t, idx); },
                  row: 'display:flex;align-items:flex-start;gap:9px;padding:6px 6px;border-radius:8px;' + (canTick && mine ? 'cursor:pointer;' : '') + (mine ? '' : 'opacity:.35;'),
                  box: 'width:22px;height:22px;border-radius:6px;flex:none;display:grid;place-items:center;font-size:13px;font-weight:800;margin-top:1px;' + (i.done ? 'background:#2f9e63;color:#ffffff;' : 'box-shadow:inset 0 0 0 2px #c2cedb;color:transparent;'),
                  mark: '\u2713',
                  nameStyle: 'font-size:15px;font-weight:600;line-height:1.3;overflow-wrap:anywhere;' + (i.done ? 'text-decoration:line-through;color:#8b98a8;' : ''),
                  noteStyle: i.note ? 'font-size:12.5px;font-weight:700;color:#b02020;margin-top:1px' : 'display:none',
                  stStyle: 'font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:' + STN[i.station][1] + ';margin-top:4px;flex:none'
                };
              }),
              hasNote: !!t.note && !done, orderNote: t.note,
              secondary: sec,
              action: act ? act[0] : 'Done', onAction: () => { if (act) this.bump(t, act[1]); },
              btn: act ? 'flex:1;height:44px;border-radius:9px;display:grid;place-items:center;font-size:15px;font-weight:700;color:#ffffff;cursor:pointer;background:' + act[2] : 'display:none'
            };
          })
        };
      }),
      allDay: s.allDay, allDayRows: adRows, allDayEmpty: !adRows.length,
      hasUndo: !!s.undo, undoText: s.undo ? s.undo.text : '',
      undo: () => { const u = s.undo; if (!u) return; clearTimeout(this._ud); this.setState({ tickets: s.tickets.map(x => x.no === u.prev.no ? u.prev : x), undo: null }); },
      hasFlash: !!s.flash, flash: s.flash || ''
    };
  }
}
return Component;
})();
});
