// ExpensesLogic: only the sidebar menu. The Expenses screens are plain
// components that use the API (views/expenses, pos/routes/api.php).
import { ModBase } from "../../runtime/logic.js";
import { goRoute, routeNow } from "../../runtime/nav.js";

export const ExpensesLogic = (() => {

const NAV = [
  ['Home','Hm',0],['User Management','Us',1],['Contacts','Ct',1],['Products','Pr',1],
  ['Manufacturing','Mf',0],['Purchases','Pu',1],['Sell','Sl',1],['Stock Transfers','St',0],
  ['Stock Adjustment','Sa',0],['Expenses','Ex',1],['Payment Accounts','Pa',1],['Accounting','Ac',1],
  ['Reports','Rp',1],['Bookings','Bk',0],['Kitchen','Kt',0],['Orders','Or',0],
  ['Settings','Se',1],['HRM','Hr',1]
];
const MONO = "font-family:'IBM Plex Mono',monospace;";

class Component extends ModBase {
  initialState = {};

  renderVals() {
    const route = routeNow();
    const onList = route && route.name === 'expenses';
    const unpaid = onList && route.query.tab === 'unpaid';
    const onCategories = route && route.name === 'expenses-categories';
    const links = [
      ['All expenses', 'expenses', {}, onList && !unpaid],
      ['Unpaid bills', 'expenses', { tab: 'unpaid' }, unpaid],
      ['Categories', 'expenses-categories', {}, onCategories]
    ];

    return {
      nav: NAV.map(n => {
        const on = n[0] === 'Expenses';
        return {
          name: n[0], key: n[1], open: on,
          children: on ? links.map(l => ({
            name: l[0],
            onClick: () => goRoute(l[1], l[2]),
            style: 'padding:6px 8px;border-radius:6px;font-size:12.5px;cursor:pointer;' + (l[3] ? 'color:#ffffff;font-weight:600;background:rgba(255,255,255,.08);' : 'color:#9dbdd2;')
          })) : [],
          style: 'display:flex;align-items:center;gap:10px;padding:8px 10px;border-radius:8px;cursor:pointer;font-size:13px;font-weight:500;margin-bottom:1px;'
            + (on ? 'background:rgba(255,255,255,.13);color:#ffffff;box-shadow:inset 2px 0 0 var(--c-accent);' : 'color:#c4dced;'),
          iconStyle: 'width:20px;height:20px;border-radius:5px;display:grid;place-items:center;' + MONO + 'font-size:9px;font-weight:600;flex:none;'
            + (on ? 'background:var(--c-accent);color:#06283a;' : 'background:rgba(255,255,255,.1);color:#9dbdd2;'),
          caretStyle: 'color:#7fa3bb;font-size:11px;flex:none;' + (n[2] ? '' : 'visibility:hidden;') + (on ? 'transform:rotate(90deg);' : '')
        };
      })
    };
  }
}

return Component;
})();
