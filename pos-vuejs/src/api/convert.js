// Frontend records <-> API rows.
// The database columns are the frontend field names in snake_case
// (depPaid -> dep_paid), and full dates are stored as dates
// ('28/09/2026' <-> '2026-09-28'). Nested values (lines, logs) pass through as they are.

const DMY = /^(\d{2})\/(\d{2})\/(\d{4})$/;
const ISO = /^(\d{4})-(\d{2})-(\d{2})$/;

const snake = k => k.replace(/[A-Z]/g, c => '_' + c.toLowerCase());
const camel = k => k.replace(/_([a-z0-9])/g, (_, c) => c.toUpperCase());

export function toApi(record) {
  const out = {};
  for (const [k, v] of Object.entries(record)) {
    const m = typeof v === 'string' && v.match(DMY);
    out[snake(k)] = m ? `${m[3]}-${m[2]}-${m[1]}` : v;
  }
  return out;
}

export function fromApi(row) {
  const out = {};
  for (const [k, v] of Object.entries(row)) {
    const m = typeof v === 'string' && v.match(ISO);
    out[camel(k)] = m ? `${m[3]}/${m[2]}/${m[1]}` : v;
  }
  return out;
}
