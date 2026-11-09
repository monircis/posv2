// v-pseudo:hover="'css'" (also :focus, :active) — the replacement for the
// style-hover / style-focus / style-active attributes of the original pages.
// Each distinct rule becomes one generated class; declarations are marked
// !important so they win over the element's inline style, as before.
const cache = new Map();
let sheet = null;
let n = 0;

function importantify(css) {
  return css.split(';').map(d => d.trim()).filter(Boolean)
    .map(d => (/!important$/.test(d) ? d : d + ' !important')).join(';');
}

function classFor(pseudo, css) {
  const key = pseudo + '|' + css;
  let cls = cache.get(key);
  if (cls) return cls;
  if (!sheet) {
    const el = document.createElement('style');
    el.setAttribute('data-erp-pseudo', '');
    document.head.appendChild(el);
    sheet = el.sheet;
  }
  cls = 'ps' + (n++).toString(36);
  sheet.insertRule('.' + cls + ':' + pseudo + '{' + importantify(css) + '}', sheet.cssRules.length);
  cache.set(key, cls);
  return cls;
}

function apply(el, { arg, value, oldValue }) {
  if (oldValue && oldValue !== value) el.classList.remove(classFor(arg, oldValue));
  if (value) el.classList.add(classFor(arg, value));
}

/** @type {import('vue').Directive<HTMLElement, string>} */
export const pseudo = { mounted: apply, updated: apply };
