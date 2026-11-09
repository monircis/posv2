// Small helpers shared by the Sales screens (views/sell).
export { money } from './products.js';

/** @type {Record<string, string>} */
export const STATUS_LABELS = {
  paid: 'Paid', partial: 'Partial', due: 'Due', cancelled: 'Cancelled', draft: 'Draft', quote: 'Quotation'
};

const TONES = {
  paid: ['#e7f6ee', '#0f6b43'], partial: ['#fdf1dd', '#8a5300'], due: ['#fdeaea', '#b02020'],
  cancelled: ['#eef2f6', '#5d6b7d'], draft: ['#eef2f6', '#5d6b7d'], quote: ['var(--c-primary-soft)', 'var(--c-primary-text)']
};

export function statusBadge(status) {
  const tone = TONES[status] || TONES.draft;
  return 'background:' + tone[0] + ';color:' + tone[1] + ';border-radius:5px;padding:3px 8px;font-size:11.5px;font-weight:600;white-space:nowrap';
}

// '2026-09-29T10:12:00.000000Z' -> '29/09/2026 10:12' in the browser's time.
export function dateTime(iso) {
  if (!iso) return '';
  const d = new Date(iso);
  return String(d.getDate()).padStart(2, '0') + '/' + String(d.getMonth() + 1).padStart(2, '0') + '/' + d.getFullYear()
    + ' ' + String(d.getHours()).padStart(2, '0') + ':' + String(d.getMinutes()).padStart(2, '0');
}
