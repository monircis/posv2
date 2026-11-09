// Small helpers shared by the Payment Accounts screens (views/payment-accounts).
export { money } from './products.js';
import { money } from './products.js';

// Account kinds: name, icon letter, colours [background, text].
/** @type {Record<string, { name: string, icon: string, c: string[] }>} */
export const KIND = {
  cash: { name: 'Cash', icon: '$', c: ['#e7f6ee', '#0f6b43'] },
  bank: { name: 'Bank', icon: 'B', c: ['var(--c-primary-soft)', 'var(--c-primary-text)'] },
  card: { name: 'Card', icon: 'C', c: ['#f3ecfd', '#5b34a8'] },
  wallet: { name: 'Wallet', icon: 'W', c: ['#fdf1dd', '#8a5300'] },
};

// What an account can be the default for. A default is per branch.
export const DEFAULTS = [
  ['posCash', 'POS cash sales', 'Cash takings from the register land here'],
  ['posCard', 'Card settlements', 'POS card sales land here'],
  ['expenses', 'Expenses', 'Pre-selected on the expense form'],
  ['purchases', 'Supplier payments', 'Pre-selected when paying purchases'],
];

// Transaction types written by the server, with a badge tone.
export const TX_TYPES = {
  sale: ['Sale', 'success'], deposit: ['Deposit', 'success'], purchase: ['Purchase', 'error'], expense: ['Expense', 'error'],
  xin: ['Transfer in', 'info'], xout: ['Transfer out', 'info'], payment: ['Card payment', 'info'], fee: ['Fee', 'neutral'],
  refund: ['Refund', 'warn'], tip: ['Tip', 'success'], 'tip payout': ['Tip payout', 'error'],
  'cash in': ['Cash in', 'success'], 'cash out': ['Cash out', 'error'], adjustment: ['Adjustment', 'neutral'],
};

/** @type {Record<string, string[]>} */
export const TONE = {
  info: ['var(--c-primary-soft)', 'var(--c-primary-text)'], warn: ['#fdf1dd', '#8a5300'], error: ['#fdeaea', '#b02020'],
  success: ['#e7f6ee', '#0f6b43'], neutral: ['#eef2f6', '#5d6b7d'],
};

export const isTransfer = t => t.type === 'xin' || t.type === 'xout' || t.type === 'payment';

// −12.50 for a negative amount.
export function signed(n) {
  return (n < 0 ? '−' : '') + money(Math.abs(n));
}

// Where the account belongs: a branch, or the whole business.
export function branchText(a) {
  return a.branch_name || 'All branches';
}

// The small line under the name, e.g. "BSB 062-118 · 1044 7781".
export function accountSub(a) {
  if (a.kind === 'bank') return (a.bsb ? 'BSB ' + a.bsb + ' · ' : '') + (a.num || 'Bank account');
  if (a.kind === 'card') return 'Card' + (a.num ? ' •• ' + a.num : '') + (a.limit ? ' · limit ' + money(a.limit) : '');
  if (a.kind === 'cash') return 'Cash drawer';
  return 'Digital wallet';
}

export function iconStyle(kind, big) {
  const k = KIND[kind];
  const z = big ? 44 : 36;
  return 'width:' + z + 'px;height:' + z + 'px;border-radius:10px;display:grid;place-items:center;font-family:\'IBM Plex Mono\',monospace;font-size:'
    + (big ? 17 : 14) + 'px;font-weight:700;flex:none;background:' + k.c[0] + ';color:' + k.c[1];
}

export function badge(tone) {
  return 'background:' + tone[0] + ';color:' + tone[1] + ';border-radius:5px;padding:3px 8px;font-size:11.5px;font-weight:600;white-space:nowrap';
}
