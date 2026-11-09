// Screen keys used by the logic (state.view) mapped to router paths, plus the
// sidebar state shared by AppSidebar, AppHeader and the page logic.
import { ref, shallowRef } from 'vue';

// Set once in main.ts (kept out of the imports so router -> views -> logic -> nav has no cycle).
let router = null;
export function bindRouter(r) { router = r; }

export const VIEW_PATHS = {
  home: '/',
  users: '/user-management',
  contacts: '/contacts',
  products: '/products',
  mfg: '/manufacturing',
  purchases: '/purchases',
  sell: '/sell',
  pos: '/pos',
  transfers: '/stock-transfers',
  adjust: '/stock-adjustment',
  expenses: '/expenses',
  accounts: '/payment-accounts',
  accounting: '/accounting',
  reports: '/reports',
  bookings: '/bookings',
  kitchen: '/kitchen',
  orders: '/orders',
  settings: '/settings',
  hrm: '/hrm'
};

// Sidebar item name -> screen key (also the permission prefix: "contacts.view").
export const SCREEN_OF_NAME = {
  'Home': 'home', 'User Management': 'users', 'Contacts': 'contacts', 'Products': 'products',
  'Manufacturing': 'mfg', 'Purchases': 'purchases', 'Sell': 'sell', 'Stock Transfers': 'transfers',
  'Stock Adjustment': 'adjust', 'Expenses': 'expenses', 'Payment Accounts': 'accounts', 'Accounting': 'accounting',
  'Reports': 'reports', 'Bookings': 'bookings', 'Kitchen': 'kitchen', 'Orders': 'orders', 'Settings': 'settings', 'HRM': 'hrm'
};

export function goView(view) {
  const path = VIEW_PATHS[view];
  if (path && router) router.push(path);
}

// For sidebar links that open a page with a filter, e.g. Unpaid bills.
export function goRoute(name, query) {
  if (router) router.push({ name, query });
}

export function routeNow() {
  return router ? router.currentRoute.value : null;
}

function readCollapsed() {
  try { return localStorage.getItem('erp.navCollapsed') === '1'; } catch (e) { return false; }
}

export const navCollapsed = ref(readCollapsed());

export function toggleNav() {
  navCollapsed.value = !navCollapsed.value;
  try { localStorage.setItem('erp.navCollapsed', navCollapsed.value ? '1' : '0'); } catch (e) {}
}

// Sidebar items for the page currently on screen (set by useErpPage).
export const sidebarItems = shallowRef([]);
