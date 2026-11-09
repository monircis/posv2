// Load a screen's records from the API, then save whatever the screen changes.
// Used in the "API" section at the bottom of each page.
import { onMounted, onUnmounted, watch } from 'vue';
import { api } from './http.js';
import { fromApi, toApi } from './convert.js';

/**
 * A list of records with a key (ref, no, id ...).
 *   path   API path, e.g. '/expenses'
 *   key    field that identifies a record
 *   read   () => the records on screen
 *   write  rows => put loaded records on screen
 * After loading, a new record is POSTed, a changed one PUT, a removed one DELETEd.
 */
export function syncList({ path, key, read, write, toRow = toApi, fromRow = fromApi }) {
  let saved = new Map(); // key -> record as last saved (JSON)
  const snapshot = list => new Map(list.map(r => [String(r[key]), JSON.stringify(r)]));
  const url = k => path + '/' + encodeURIComponent(k);
  let stop = null;

  onMounted(async () => {
    try {
      const rows = await api.get(path);
      write(rows.map(fromRow));
    } catch (e) {
      return; // the error bar shows why; the screen keeps what it had
    }
    saved = snapshot(read());

    stop = watch(read, async list => {
      const before = saved;
      saved = snapshot(list);
      for (const [k, json] of saved) {
        if (!before.has(k)) await api.post(path, toRow(JSON.parse(json))).catch(() => {});
        else if (before.get(k) !== json) await api.put(url(k), toRow(JSON.parse(json))).catch(() => {});
      }
      for (const k of before.keys()) {
        if (!saved.has(k)) await api.del(url(k)).catch(() => {});
      }
    });
  });
  onUnmounted(() => stop && stop());
}

/**
 * One value (a stock map, a settings document ...).
 *   load   async () => value from the API
 *   save   async value => send it to the API
 *   read / write   the value on screen
 */
export function syncValue({ load, save, read, write }) {
  let stop = null;

  onMounted(async () => {
    try {
      write(await load());
    } catch (e) {
      return;
    }
    let last = JSON.stringify(read());

    stop = watch(read, value => {
      const json = JSON.stringify(value);
      if (json === last) return;
      last = json;
      save(value).catch(() => {});
    });
  });
  onUnmounted(() => stop && stop());
}

/** Next number for a screen's counter: 'EX-1092' -> 1093. */
export function nextNumber(rows, key, fallback = 1) {
  const nums = rows.map(r => parseInt(String(r[key]).replace(/\D/g, ''), 10)).filter(n => !isNaN(n));
  return nums.length ? Math.max(...nums) + 1 : fallback;
}
