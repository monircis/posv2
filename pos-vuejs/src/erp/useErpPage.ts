// Connects a page component to the ERP screen logic.
// The logic objects are plain classes (ported from the HTML prototype); their
// renderVals() output is what the templates bind to through `vm`.
import { computed, inject, onMounted, onUnmounted, provide, watch, watchEffect, type ComputedRef } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { ErpApp } from './logic/app.js';
import { PosLogic } from './logic/pos.js';
import { sidebarItems } from './runtime/nav.js';
import { SUB_PAGES } from './subPages.js';

type Vals = Record<string, any>;

// The page that last filled the sidebar. On navigation the new page mounts
// before the old one unmounts, so only the current owner may clear it.
let sidebarOwner: symbol | null = null;

// The logic created by the page being set up; read by useScreen() right after.
let setupLogic: any = null;

export function useErpPage(view: string, navKey?: string): ComputedRef<Vals> {
  const app: any = new ErpApp({ view });
  setupLogic = app;
  const vm = computed<Vals>(() => {
    app.__ver.value;
    return app.renderVals();
  });
  if (navKey) {
    const owner = Symbol(view);
    const stop = watchEffect(() => { sidebarOwner = owner; sidebarItems.value = vm.value[navKey] || []; });
    onUnmounted(() => {
      stop();
      if (sidebarOwner === owner) { sidebarOwner = null; sidebarItems.value = []; }
    });
  }
  onUnmounted(() => app.dispose());
  // Sub-screens (list, details, ...) are child routes; they read vm with usePageVm().
  provide('vm', vm);
  const sub = (SUB_PAGES as Record<string, any>)[view];
  if (sub) syncSubPage(app, vm, sub);
  return vm;
}

// For the sub-screen components of a page.
export function usePageVm(): ComputedRef<Vals> {
  return inject('vm') as ComputedRef<Vals>;
}

// Keeps the URL and the sub-screen shown by the logic in step.
function syncSubPage(app: any, vm: ComputedRef<Vals>, cfg: any) {
  const route = useRoute();
  const router = useRouter();

  // The logic object whose state holds the sub-screen.
  const target = () => { vm.value; return cfg.mod ? app._mods[cfg.mod] : app; };
  const current = () => { const s = target().state; return cfg.get ? cfg.get(s) : s[cfg.key]; };

  // URL -> screen: opening /sell/details shows the sale details.
  watch(() => route.name, (name) => {
    const sub = Object.keys(cfg.routes).find(k => cfg.routes[k] === name);
    if (!sub || sub === current()) return;
    const t = target();
    if (cfg.open && cfg.open[sub]) cfg.open[sub](t, vm.value);
    else t.setState(cfg.set ? cfg.set(sub, t.state) : { [cfg.key]: sub });
  }, { immediate: true });

  // Screen -> URL: buttons and sidebar links in the page change the logic state.
  watch(current, (sub) => {
    const name = cfg.routes[sub];
    if (name && name !== route.name) router.push({ name });
  });
}

/**
 * The page's logic state, for the API section at the bottom of a page.
 * Call it right after useErpPage() / usePosPage(). Pass the module key
 * (e.g. 'ex' for Expenses) when the screen's records live in a module.
 *   screen.state      current state (reactive, so it can be watched)
 *   screen.setState() change it
 *   screen.refresh()  re-render after changing a shared list (DATA in logic/app.js)
 */
export function useScreen(moduleKey?: string) {
  const logic = setupLogic;
  // Modules are created on the first render, before the API section loads (onMounted).
  const target = () => (moduleKey ? logic._mods[moduleKey] : logic);
  return {
    get state(): Vals {
      logic.__ver.value;
      return target().state;
    },
    setState: (patch: Vals | ((s: Vals) => Vals)) => target().setState(patch),
    refresh: () => logic.forceUpdate()
  };
}

export function usePosPage(): ComputedRef<Vals> {
  const pos: any = new PosLogic({});
  setupLogic = pos;
  const vm = computed<Vals>(() => {
    pos.__ver.value;
    return pos.renderVals();
  });
  onMounted(() => pos.componentDidMount());
  onUnmounted(() => pos.componentWillUnmount());
  return vm;
}
