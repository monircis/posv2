// Small helpers shared by the Manufacturing screens (views/manufacturing).
export { money } from './products.js';
export { dmy, today, userBranchId } from './purchases.js';

/** @type {Record<string, string>} */
export const STATUS_LABELS = { planned: 'Planned', progress: 'In progress', done: 'Done', cancelled: 'Cancelled' };

const TONES = {
  planned: ['#eef2f6', '#5d6b7d'], progress: ['var(--c-primary-soft)', 'var(--c-primary-text)'],
  done: ['#e7f6ee', '#0f6b43'], cancelled: ['#fdeaea', '#b02020']
};

export function statusBadge(status) {
  const tone = TONES[status] || TONES.planned;
  return 'background:' + tone[0] + ';color:' + tone[1] + ';border-radius:5px;padding:3px 8px;font-size:11.5px;font-weight:600;white-space:nowrap';
}

// 115.2, 'kg' -> '115.2 kg'
export function qty(n, unit) {
  return (Math.round((n || 0) * 1000) / 1000).toLocaleString('en-AU') + (unit ? ' ' + unit : '');
}

export const inputStyle = "width:100%;height:36px;border:1px solid #dfe6ee;border-radius:8px;padding:0 11px;font-family:'Public Sans',sans-serif;font-size:13px;outline:none;background:#ffffff";
export const labelStyle = 'display:block;font-size:12px;font-weight:600;margin-bottom:5px';
export const ghostButton = 'height:34px;padding:0 13px;border-radius:8px;border:1px solid #dfe6ee;display:flex;align-items:center;font-size:12.5px;font-weight:500;color:#4a5866;cursor:pointer;background:#ffffff';
export const primaryButton = 'height:34px;padding:0 15px;border-radius:8px;background:var(--c-primary);color:#ffffff;display:flex;align-items:center;font-size:12.5px;font-weight:600;cursor:pointer;text-decoration:none';
export const errorBox = 'background:#fdeaea;color:#b02020;border-radius:9px;padding:9px 12px;font-size:12.5px;font-weight:600';
