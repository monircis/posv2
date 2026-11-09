// Small helpers shared by the Expenses screens (views/expenses).
export { money } from './products.js';
export { dmy, today, userBranchId } from './purchases.js';

export function categoryDot(color) {
  return 'width:8px;height:8px;border-radius:50%;flex:none;background:' + color;
}

// '2026-09-29' + 30 -> '2026-10-29'
export function addDays(iso, days) {
  const d = new Date(iso + 'T00:00:00');
  d.setDate(d.getDate() + days);
  return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
}

// Days from today to a date: negative when it has passed.
function daysFromToday(iso) {
  const now = new Date();
  const start = new Date(now.getFullYear(), now.getMonth(), now.getDate());
  return Math.round((new Date(iso + 'T00:00:00').getTime() - start.getTime()) / 864e5);
}

// 'Paid', 'Overdue 3d', 'Due today' or 'Due in 5d'.
export function statusText(expense) {
  if (expense.payment_account_id) return 'Paid';
  if (!expense.due_date) return 'Unpaid';
  const days = daysFromToday(expense.due_date);
  if (days < 0) return 'Overdue ' + -days + 'd';
  return days === 0 ? 'Due today' : 'Due in ' + days + 'd';
}

export function statusBadge(expense) {
  const text = statusText(expense);
  const tone = text === 'Paid' ? ['#e7f6ee', '#0f6b43'] : text.startsWith('Overdue') ? ['#fdeaea', '#b02020'] : ['#fdf1dd', '#8a5300'];
  return 'background:' + tone[0] + ';color:' + tone[1] + ';border-radius:5px;padding:3px 8px;font-size:11.5px;font-weight:600;white-space:nowrap';
}

// −3,120.80 for an account that is below zero.
export function balance(n, money) {
  return (n < 0 ? '−' : '') + money(Math.abs(n));
}

// The small line under an account's name, e.g. 'Cash · Main branch'.
export function accountType(a) {
  if (a.kind === 'cash') return 'Cash' + (a.branch_name ? ' · ' + a.branch_name : '');
  if (a.kind === 'bank') return 'Bank' + (a.num ? ' · ' + String(a.num).slice(-4) : '');
  if (a.kind === 'card') return 'Credit card' + (a.limit ? ' · limit ' + Number(a.limit).toLocaleString('en-AU') : '');
  return 'Wallet';
}

// Two accounts can both be called "Cash drawer", so the filter also shows the branch.
export function accountLabel(a) {
  return a.name + (a.branch_name ? ' · ' + a.branch_name : '');
}

// The account marked "Expenses" in Payment Accounts: the branch's own first, then a whole-business one, else the first account.
export function defaultAccountId(accounts, branchId) {
  const marked = accounts.filter(a => (a.defs || []).includes('expenses'));
  const pick = (branchId && marked.find(a => a.branch_id === branchId)) || marked.find(a => !a.branch_id) || marked[0];
  if (pick) return pick.id;
  return accounts.length ? accounts[0].id : '';
}

export function accountCardStyle(on) {
  return 'display:flex;align-items:center;gap:11px;border-radius:10px;padding:10px 12px;cursor:pointer;'
    + (on ? 'background:#f4f9ff;box-shadow:inset 0 0 0 1.5px var(--c-primary);' : 'border:1px solid #dfe6ee;');
}

export function accountRadioStyle(on) {
  return 'width:16px;height:16px;border-radius:50%;flex:none;'
    + (on ? 'box-shadow:inset 0 0 0 5px var(--c-primary);' : 'box-shadow:inset 0 0 0 1.5px #c2cedb;');
}
