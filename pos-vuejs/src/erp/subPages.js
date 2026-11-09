// Pages that are split into sub-screens, each with its own route.
//   key:    the state value that picks the sub-screen
//   mod:    the screen module holding that state (pages built on a module)
//   routes: sub-screen value -> route name (see router/index.ts)
//   open:   how to open a sub-screen that needs setup first (an empty form)
export const SUB_PAGES = {
  users: {
    key: 'sub',
    routes: { list: 'users', details: 'users-details', roles: 'users-roles', matrix: 'users-permissions' }
  },
  contacts: {
    key: 'csub',
    routes: { list: 'contacts', details: 'contacts-details', groups: 'contacts-groups' }
  },
  products: {
    key: 'psub',
    routes: { list: 'products', details: 'products-details', taxonomy: 'products-categories', labels: 'products-labels' }
  },
  purchases: {
    key: 'pusub',
    routes: { list: 'purchases', form: 'purchases-new', details: 'purchases-details', returns: 'purchases-returns', return: 'purchases-return-new' }
  },
  sell: {
    key: 'ssub',
    routes: { list: 'sell', details: 'sell-details', return: 'sell-return' }
  },
  accounting: {
    mod: 'ac', key: 'sub',
    routes: { coa: 'accounting', journals: 'accounting-journals', form: 'accounting-journal-new', statements: 'accounting-statements', close: 'accounting-close' },
    open: { form: (m, vm) => vm.ac_newJournal() }
  },
  // HRM uses two values (tab + view), so it reads and sets them itself.
  hrm: {
    mod: 'hr',
    routes: { list: 'hrm', emp: 'hrm-employee', att: 'hrm-attendance', leave: 'hrm-leave', pay: 'hrm-payroll', tips: 'hrm-tips' },
    get: s => (s.tab === 'emp' ? (s.view === 'emp' ? 'emp' : 'list') : s.tab),
    set: (sub, s) => (sub === 'emp' ? { tab: 'emp', view: 'emp', sel: s.sel || 1 }
      : sub === 'list' ? { tab: 'emp', view: 'list' }
      : { tab: sub, view: 'list' })
  }
};
