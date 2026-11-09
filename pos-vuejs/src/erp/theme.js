// App theme colours. Pages use these through CSS variables, e.g. background:var(--c-primary).
// Pick one in Settings > Appearance; the choice is remembered in this browser.
export const THEMES = {
  Blue:   { primary: '#1668c9', hover: '#1257ab', soft: '#eaf1fb', text: '#14549e', tint: '#f6faff', chart: '#9cc6ea', sidebar: '#0a3a52', accent: '#4da3e8' },
  Green:  { primary: '#1f8a55', hover: '#187046', soft: '#e5f4ec', text: '#11633d', tint: '#f3fbf7', chart: '#a3d8bc', sidebar: '#0d3b2a', accent: '#4cc38a' },
  Maroon: { primary: '#8e2a3b', hover: '#74212f', soft: '#f8e9ec', text: '#74212f', tint: '#fdf5f6', chart: '#dcaab3', sidebar: '#3b1219', accent: '#e07a8b' },
  Purple: { primary: '#6b45c9', hover: '#5734a8', soft: '#efeafc', text: '#5734a8', tint: '#f8f5fe', chart: '#c5b4ee', sidebar: '#2a1a52', accent: '#a98bf0' },
  Teal:   { primary: '#0f8b8d', hover: '#0b6f71', soft: '#e2f4f4', text: '#0b6f71', tint: '#f2fbfb', chart: '#9fd6d7', sidebar: '#083b3c', accent: '#4fc6c8' },
  Orange: { primary: '#d35400', hover: '#b34700', soft: '#fdeee3', text: '#a04000', tint: '#fff8f3', chart: '#f3bf96', sidebar: '#3d1d07', accent: '#f59a4e' },
  Yellow: { primary: '#b38600', hover: '#946f00', soft: '#fdf6dc', text: '#7a5c00', tint: '#fffcf0', chart: '#ecd383', sidebar: '#3a2e05', accent: '#f2c94c' }
};

export const THEME_NAMES = Object.keys(THEMES);

export function savedTheme() {
  try {
    const name = localStorage.getItem('erp.theme');
    return THEMES[name] ? name : 'Blue';
  } catch (e) {
    return 'Blue';
  }
}

export function applyTheme(name) {
  const t = THEMES[name] || THEMES.Blue;
  const root = document.documentElement.style;
  root.setProperty('--c-primary', t.primary);
  root.setProperty('--c-primary-hover', t.hover);
  root.setProperty('--c-primary-soft', t.soft);
  root.setProperty('--c-primary-text', t.text);
  root.setProperty('--c-primary-tint', t.tint);
  root.setProperty('--c-chart', t.chart);
  root.setProperty('--c-sidebar', t.sidebar);
  root.setProperty('--c-accent', t.accent);
  try { localStorage.setItem('erp.theme', name); } catch (e) {}
}
