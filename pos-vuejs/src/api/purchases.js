// Small helpers shared by the Purchases screens (views/purchases).
import { computed } from 'vue';
import { currentUser } from './auth.js';

export { money } from './products.js';

// The account marked "Supplier payments" in Payment Accounts: the branch's own first, then a whole-business one, else the first account.
export function defaultAccountId(accounts, branchId) {
  const marked = accounts.filter(a => (a.defs || []).includes('purchases'));
  const pick = (branchId && marked.find(a => a.branch_id === branchId)) || marked.find(a => !a.branch_id) || marked[0];
  if (pick) return pick.id;
  return accounts.length ? accounts[0].id : '';
}

// A user with a branch only works on that branch; null = all branches.
export const userBranchId = computed(() => (currentUser.value ? currentUser.value.branch_id : null));

/** @type {Record<string, string>} */
export const STATUS_LABELS = { ordered: 'Ordered', pending: 'Pending', received: 'Received' };
/** @type {Record<string, string>} */
export const PAY_LABELS = { paid: 'Paid', partial: 'Partial', due: 'Due' };
export const RETURN_REASONS = ['Damaged', 'Temperature breach', 'Short dated', 'Wrong item', 'Over-delivered', 'Quality'];
export const SETTLEMENTS = [
  { name: 'Reduced owed', title: 'Reduce amount owed', desc: 'Take the credit off what is still due on this purchase.' },
  { name: 'Refund', title: 'Refund to account', desc: 'The supplier pays the money back.' },
  { name: 'Supplier credit', title: 'Hold as supplier credit', desc: 'Keep the credit on the supplier for a later purchase.' }
];

const TONES = {
  ordered: ['var(--c-primary-soft)', 'var(--c-primary-text)'], pending: ['#eef2f6', '#5d6b7d'], received: ['#e7f6ee', '#0f6b43'],
  paid: ['#e7f6ee', '#0f6b43'], partial: ['#fdf1dd', '#8a5300'], due: ['#fdeaea', '#b02020'],
  Credited: ['#e7f6ee', '#0f6b43'], 'Awaiting refund': ['#fdf1dd', '#8a5300']
};

export function badge(value) {
  const tone = TONES[value] || TONES.pending;
  return 'background:' + tone[0] + ';color:' + tone[1] + ';border-radius:5px;padding:3px 8px;font-size:11.5px;font-weight:600';
}

// '2026-09-29' -> '29/09/2026'
export function dmy(iso) {
  if (!iso) return '';
  const part = String(iso).slice(0, 10).split('-');
  return part[2] + '/' + part[1] + '/' + part[0];
}

// Today as '2026-09-29', for <input type="date">.
export function today() {
  const d = new Date();
  return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
}

// 12.5 -> '12.5', 12 -> '12'
export function qtyText(qty) {
  const n = Number(qty) || 0;
  return Number.isInteger(n) ? String(n) : String(Math.round(n * 1000) / 1000);
}
