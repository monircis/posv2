// Small helpers shared by the Bookings screens (views/BookingsView.vue and views/bookings).
export { money } from './products.js';

// Status: label and colours [background, text, strong colour].
/** @type {Record<string, { name: string, c: string[] }>} */
export const STATUS = {
  pending: { name: 'Pending', c: ['#fdf1dd', '#8a5300', '#d98c1a'] },
  confirmed: { name: 'Confirmed', c: ['var(--c-primary-soft)', 'var(--c-primary-text)', 'var(--c-primary)'] },
  seated: { name: 'Seated', c: ['#f3ecfd', '#5b34a8', '#7a4fc9'] },
  completed: { name: 'Completed', c: ['#e7f6ee', '#0f6b43', '#2f9e63'] },
  noshow: { name: 'No-show', c: ['#fdeaea', '#b02020', '#d93a3a'] },
  cancelled: { name: 'Cancelled', c: ['#eef2f6', '#5d6b7d', '#8fa3b6'] },
};

// Colour of a history dot.
/** @type {Record<string, string>} */
export const TONE_DOT = { neutral: '#8fa3b6', info: 'var(--c-primary)', success: '#2f9e63', warn: '#d98c1a', error: '#d93a3a' };

// Statuses that still hold the table.
export const HOLDS_TABLE = ['pending', 'confirmed', 'seated'];

export function badge(status) {
  const c = STATUS[status].c;
  return 'background:' + c[0] + ';color:' + c[1] + ';border-radius:5px;padding:3px 8px;font-size:11.5px;font-weight:600;white-space:nowrap';
}

// "19:30" -> 1170
export function minutes(time) {
  const p = String(time).split(':');
  return Number(p[0]) * 60 + Number(p[1]);
}

// 1170 -> "19:30"
export function hm(m) {
  return String(Math.floor(m / 60)).padStart(2, '0') + ':' + String(m % 60).padStart(2, '0');
}

// "2026-11-14" -> "Saturday 14 November" (or "Sat 14 Nov" when short)
export function dayLabel(date, short) {
  const d = new Date(date + 'T12:00:00');
  return d.toLocaleDateString('en-AU', short ? { weekday: 'short', day: 'numeric', month: 'short' } : { weekday: 'long', day: 'numeric', month: 'long' });
}

// "2026-11-14" plus n days
export function addDays(date, n) {
  const d = new Date(date + 'T12:00:00');
  d.setDate(d.getDate() + n);
  return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
}
