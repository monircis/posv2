// Small React-style state container the ERP screen logic is written against.
// Logic classes keep `state`, `setState()` and `renderVals()`; every setState
// bumps a version ref so a Vue computed around renderVals() re-runs.
import { nextTick, shallowRef } from 'vue';

export class DCLogic {
  constructor(props) {
    this.props = props || {};
    this.state = {};
    this.__ver = shallowRef(0);
  }

  setState(update, cb) {
    const patch = typeof update === 'function' ? update(this.state, this.props) : update;
    if (patch == null) return;
    this.state = { ...this.state, ...patch };
    this.__ver.value++;
    if (cb) nextTick(cb);
  }

  forceUpdate() { this.__ver.value++; }
  componentDidMount() {}
  componentWillUnmount() {}
  renderVals() { return {}; }
}

// Base class for the screen modules (state stored on the main app)
export class ModBase {
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

// Stand-in for React.createRef(); templates fill `current` through a function ref.
export const createRef = () => ({ current: null });
