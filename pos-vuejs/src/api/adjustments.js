// Small helpers shared by the Stock Adjustment screens (views/stock-adjustment).
import { money } from './products.js';

export { money, qtyText as qtyUnit } from './products.js';
export { dmy, today, userBranchId } from './purchases.js';

/** @type {Record<string, string>} */
export const TYPE_LABELS = { dec: 'Decrease', inc: 'Increase', count: 'Stock count' };

const TONES = {
  dec: ['#fdeaea', '#b02020'], inc: ['#e7f6ee', '#0f6b43'], count: ['var(--c-primary-soft)', 'var(--c-primary-text)']
};

export function badge(type) {
  const tone = TONES[type] || TONES.count;
  return 'background:' + tone[0] + ';color:' + tone[1] + ';border-radius:5px;padding:3px 8px;font-size:11.5px;font-weight:600;white-space:nowrap';
}

// 12.5 -> '+12.50', -12.5 -> '−12.50'
export function signed(n) {
  return (n > 0 ? '+' : n < 0 ? '−' : '') + money(Math.abs(n));
}

// Red for less stock, green for more.
export function valueColor(n) {
  return n < 0 ? 'color:#b02020;' : n > 0 ? 'color:#0f6b43;' : 'color:#9aa7b6;';
}
