// Base class for the screen modules (state stored on the main app)
DC.add(function () {
class ModBase {
  constructor(host, key) { this._host = host; this._key = key; }
  get state() { return this._host.state[this._key] || this.initialState; }
  setState(p) {
    this._host.setState(s => {
      const cur = s[this._key] || this.initialState;
      const patch = typeof p === 'function' ? p(cur) : p;
      if (!patch) return null;
      return { [this._key]: { ...cur, ...patch } };
    });
  }
}
});
