// Small helpers shared by the Stock Transfers screens (views/stock-transfers).
export { money, qtyText as qtyUnit } from './products.js';
export { dmy, today, userBranchId } from './purchases.js';

/** @type {Record<string, string>} */
export const STATUS_LABELS = {
  draft: 'Draft', transit: 'In transit', received: 'Received', variance: 'Received with variance', cancelled: 'Cancelled'
};

const TONES = {
  draft: ['#eef2f6', '#5d6b7d'], transit: ['var(--c-primary-soft)', 'var(--c-primary-text)'],
  received: ['#e7f6ee', '#0f6b43'], variance: ['#fdf1dd', '#8a5300'], cancelled: ['#fdeaea', '#b02020']
};

export function badge(status) {
  const tone = TONES[status] || TONES.draft;
  return 'background:' + tone[0] + ';color:' + tone[1] + ';border-radius:5px;padding:3px 8px;font-size:11.5px;font-weight:600;white-space:nowrap';
}

/** @type {Record<string, string>} */
const LOG_DOTS = { neutral: '#8fa3b6', info: 'var(--c-primary)', success: '#2f9e63', warn: '#d98c1a', error: '#d93a3a' };

export function logDot(tone) {
  return 'width:8px;height:8px;border-radius:50%;margin-top:5px;flex:none;background:' + (LOG_DOTS[tone] || LOG_DOTS.neutral);
}

// A date and time from the API -> '29/09 · 10:24' in the user's own time.
export function dayTime(iso) {
  if (!iso) return '';
  const d = new Date(iso);
  const two = n => String(n).padStart(2, '0');
  return two(d.getDate()) + '/' + two(d.getMonth() + 1) + ' · ' + two(d.getHours()) + ':' + two(d.getMinutes());
}
