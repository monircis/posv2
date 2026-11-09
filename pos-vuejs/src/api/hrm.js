// Small helpers shared by the HRM screens (views/hrm/*.vue) and My HR (views/MyHrView.vue).
// API: see pos/docs/HRM.md
import { api, API_URL, getToken } from './http.js';

export { api };

// ---- styles (the same look as the other screens) ----
export const ui = {
  card: 'background:#ffffff;border:1px solid #dfe6ee;border-radius:12px;overflow:hidden',
  pad: 'padding:14px 16px',
  head: 'padding:10px 16px;background:#f6f9fc;border-bottom:1px solid #dfe6ee;font-family:\'IBM Plex Mono\',monospace;font-size:9.5px;letter-spacing:0.07em;text-transform:uppercase;color:#5d6b7d',
  row: 'padding:9px 16px;border-bottom:1px solid #f0f4f8;font-size:12.5px;align-items:center',
  title: 'font-size:14px;font-weight:700',
  muted: 'font-size:12px;color:#7d8b9c',
  mono: "font-family:'IBM Plex Mono',monospace",
  btn: 'height:32px;padding:0 12px;border-radius:8px;border:1px solid #dfe6ee;background:#ffffff;display:inline-flex;align-items:center;font-size:12.5px;font-weight:600;color:#4a5866;cursor:pointer;white-space:nowrap',
  btnMain: 'height:32px;padding:0 14px;border-radius:8px;background:var(--c-primary);color:#ffffff;display:inline-flex;align-items:center;font-size:12.5px;font-weight:600;cursor:pointer;white-space:nowrap;border:none',
  btnSmall: 'height:26px;padding:0 9px;border-radius:7px;border:1px solid #dfe6ee;background:#ffffff;display:inline-flex;align-items:center;font-size:11.5px;font-weight:600;color:#4a5866;cursor:pointer;white-space:nowrap',
  btnGood: 'height:26px;padding:0 9px;border-radius:7px;background:#e7f6ee;color:#0f6b43;display:inline-flex;align-items:center;font-size:11.5px;font-weight:600;cursor:pointer;border:none',
  btnBad: 'height:26px;padding:0 9px;border-radius:7px;background:#fdeaea;color:#b02020;display:inline-flex;align-items:center;font-size:11.5px;font-weight:600;cursor:pointer;border:none',
  input: 'height:32px;border:1px solid #dfe6ee;border-radius:8px;padding:0 9px;font-family:inherit;font-size:12.5px;background:#ffffff;min-width:0',
  label: 'display:grid;gap:4px;font-size:11.5px;color:#5d6b7d;font-weight:600',
  ok: 'background:#e7f6ee;color:#0f6b43;border-radius:10px;padding:10px 14px;font-size:12.5px',
  err: 'background:#fdeaea;color:#b02020;border-radius:10px;padding:10px 14px;font-size:12.5px',
  kpi: 'background:#ffffff;border:1px solid #dfe6ee;border-radius:12px;padding:13px 16px',
  kpiValue: "font-family:'IBM Plex Mono',monospace;font-size:21px;font-weight:600;letter-spacing:-0.02em;margin-top:2px",
  seg: on => 'padding:6px 12px;border-radius:7px;font-size:12.5px;font-weight:600;cursor:pointer;' + (on ? 'background:#ffffff;color:#0f1c2b;box-shadow:0 1px 2px rgba(0,0,0,.08)' : 'color:#5d6b7d'),
  segWrap: 'display:inline-flex;gap:3px;background:#eef2f6;padding:3px;border-radius:9px;flex-wrap:wrap',
  overlay: 'position:fixed;inset:0;background:rgba(9,22,35,.42);z-index:40;display:flex;align-items:flex-start;justify-content:center;padding:60px 16px;overflow:auto',
  dialog: 'background:#ffffff;border-radius:14px;width:560px;max-width:100%;padding:20px 22px;display:grid;gap:12px;box-shadow:0 20px 50px -20px rgba(9,22,35,.6)',
};

// ---- status colours ----
const COLORS = {
  green: ['#e7f6ee', '#0f6b43'], amber: ['#fdf1dd', '#8a5300'], red: ['#fdeaea', '#b02020'],
  blue: ['var(--c-primary-soft)', 'var(--c-primary-text)'], grey: ['#eef2f6', '#5d6b7d'], purple: ['#f3ecfd', '#5b34a8'],
};
const TONE = {
  present: 'green', approved: 'green', Approved: 'green', paid: 'green', Active: 'green', active: 'green',
  late: 'amber', half_day: 'amber', pending: 'amber', Pending: 'amber', draft: 'grey', Probation: 'amber', missing_punch: 'red', not_in_yet: 'grey',
  absent: 'red', rejected: 'red', Declined: 'red', Terminated: 'red', Resigned: 'grey', Inactive: 'grey', cancelled: 'grey', Cancelled: 'grey', closed: 'grey', skipped: 'grey', waiting: 'grey',
  leave: 'blue', weekend: 'grey', holiday: 'purple', none: 'grey',
};
export function badge(status) {
  const c = COLORS[TONE[status] || 'grey'];
  return 'display:inline-block;background:' + c[0] + ';color:' + c[1] + ';border-radius:5px;padding:2px 8px;font-size:11.5px;font-weight:600;white-space:nowrap';
}
export const label = s => String(s || '').replace(/_/g, ' ').replace(/^./, c => c.toUpperCase()).replace(/^Hr /, 'HR ');

// ---- numbers and dates ----
export const money = n => '$' + Number(n || 0).toLocaleString('en-AU', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
export const hours = min => { const m = Math.round(Number(min) || 0); return Math.floor(m / 60) + 'h ' + String(m % 60).padStart(2, '0') + 'm'; };
export const dmy = iso => (iso ? String(iso).slice(8, 10) + '/' + String(iso).slice(5, 7) + '/' + String(iso).slice(0, 4) : '');
export const time = dt => (dt ? String(dt).slice(11, 16) : '—');
const pad = n => String(n).padStart(2, '0');
export const today = () => { const d = new Date(); return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()); };
export const thisMonth = () => today().slice(0, 7);
export const monthName = m => new Date(m + '-01T12:00:00').toLocaleDateString('en-AU', { month: 'long', year: 'numeric' });
export const DAYS = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

// ---- shared lists ----
/** Working staff the user may see: [{ id, code, name, branch_id, ... }] */
export async function loadStaff() {
  return api.get('/employees?status=working');
}

// ---- files ----
// Printable pages (payslips, report PDF) need the sign-in token, so they are fetched and opened in a new tab.
export async function openPrintable(path) {
  const win = window.open('', '_blank');
  const res = await fetch(API_URL + path, { headers: { Authorization: 'Bearer ' + getToken(), Accept: 'text/html' } });
  const html = await res.text();
  if (win) { win.document.open(); win.document.write(html); win.document.close(); }
}

// CSV / Excel exports and documents: download the file.
export async function download(path, fileName) {
  const res = await fetch(API_URL + path, { headers: { Authorization: 'Bearer ' + getToken() } });
  if (!res.ok) throw new Error('Download failed (' + res.status + ')');
  const url = URL.createObjectURL(await res.blob());
  const a = document.createElement('a');
  a.href = url;
  a.download = fileName;
  a.click();
  URL.revokeObjectURL(url);
}
