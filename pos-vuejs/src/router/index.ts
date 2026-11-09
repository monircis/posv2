import { createRouter, createWebHashHistory, type RouteRecordRaw } from "vue-router";
import AdminLayout from "../layouts/AdminLayout.vue";
import { VIEW_PATHS } from "../erp/runtime/nav.js";
import { can, homeFor, isSignedIn, loadUser, logout } from "../api/auth.js";
import { apiError } from "../api/http.js";

const TITLE = "Madinah Butchery ERP";

// Pages with the sidebar + header shell.
const shellPages: RouteRecordRaw[] = [
  { path: "", name: "home", component: () => import("../views/HomeView.vue"), meta: { title: "Home" } },

  {
    path: "user-management",
    component: () => import("../views/users/UsersPage.vue"),
    children: [
      { path: "", name: "users", component: () => import("../views/users/UserList.vue"), meta: { title: "Users" } },
      { path: "details", name: "users-details", component: () => import("../views/users/UserDetails.vue"), meta: { title: "User details" } },
      { path: "roles", name: "users-roles", component: () => import("../views/users/UserRoles.vue"), meta: { title: "Roles" } },
      { path: "permissions", name: "users-permissions", component: () => import("../views/users/UserPermissions.vue"), meta: { title: "Permissions" } },
    ],
  },

  {
    path: "contacts",
    component: () => import("../views/contacts/ContactsPage.vue"),
    children: [
      { path: "", name: "contacts", component: () => import("../views/contacts/ContactList.vue"), meta: { title: "Contacts" } },
      { path: "details", name: "contacts-details", component: () => import("../views/contacts/ContactDetails.vue"), meta: { title: "Contact details" } },
      { path: "groups", name: "contacts-groups", component: () => import("../views/contacts/CustomerGroups.vue"), meta: { title: "Customer groups" } },
    ],
  },

  {
    path: "products",
    component: () => import("../views/products/ProductsPage.vue"),
    children: [
      { path: "", name: "products", component: () => import("../views/products/ProductList.vue"), meta: { title: "Products" } },
      { path: "details", name: "products-details", component: () => import("../views/products/ProductDetails.vue"), meta: { title: "Product details" } },
      { path: "categories", name: "products-categories", component: () => import("../views/products/ProductCategories.vue"), meta: { title: "Categories & units" } },
      { path: "labels", name: "products-labels", component: () => import("../views/products/ProductLabels.vue"), meta: { title: "Print labels" } },
    ],
  },

  {
    path: "manufacturing",
    component: () => import("../views/manufacturing/ManufacturingPage.vue"),
    children: [
      { path: "", name: "mfg", component: () => import("../views/manufacturing/ProductionOrders.vue"), meta: { title: "Production orders" } },
      { path: "recipes", name: "mfg-recipes", component: () => import("../views/manufacturing/RecipesScreen.vue"), meta: { title: "Recipes" } },
      { path: "materials", name: "mfg-materials", component: () => import("../views/manufacturing/MaterialsScreen.vue"), meta: { title: "Materials" } },
    ],
  },

  {
    path: "purchases",
    component: () => import("../views/purchases/PurchasesPage.vue"),
    children: [
      { path: "", name: "purchases", component: () => import("../views/purchases/PurchaseList.vue"), meta: { title: "Purchases" } },
      { path: "new", name: "purchases-new", component: () => import("../views/purchases/PurchaseForm.vue"), meta: { title: "New purchase" } },
      { path: "details", name: "purchases-details", component: () => import("../views/purchases/PurchaseDetails.vue"), meta: { title: "Purchase details" } },
      { path: "returns", name: "purchases-returns", component: () => import("../views/purchases/PurchaseReturns.vue"), meta: { title: "Purchase returns" } },
      { path: "returns/new", name: "purchases-return-new", component: () => import("../views/purchases/PurchaseReturnForm.vue"), meta: { title: "New purchase return" } },
    ],
  },

  {
    path: "sell",
    component: () => import("../views/sell/SellPage.vue"),
    children: [
      { path: "", name: "sell", component: () => import("../views/sell/SalesList.vue"), meta: { title: "Sales" } },
      { path: "details", name: "sell-details", component: () => import("../views/sell/SaleDetails.vue"), meta: { title: "Sale details" } },
      { path: "return", name: "sell-return", component: () => import("../views/sell/SellReturn.vue"), meta: { title: "Sell return" } },
      { path: "registers", name: "sell-registers", component: () => import("../views/sell/Registers.vue"), meta: { title: "Cash registers" } },
    ],
  },

  {
    path: "stock-transfers",
    component: () => import("../views/stock-transfers/StockTransfersPage.vue"),
    children: [
      { path: "", name: "transfers", component: () => import("../views/stock-transfers/TransferList.vue"), meta: { title: "Stock transfers" } },
      { path: "new", name: "transfers-new", component: () => import("../views/stock-transfers/TransferForm.vue"), meta: { title: "New stock transfer" } },
      { path: "details", name: "transfers-details", component: () => import("../views/stock-transfers/TransferDetails.vue"), meta: { title: "Stock transfer details" } },
    ],
  },

  {
    path: "stock-adjustment",
    component: () => import("../views/stock-adjustment/StockAdjustmentPage.vue"),
    children: [
      { path: "", name: "adjust", component: () => import("../views/stock-adjustment/AdjustmentList.vue"), meta: { title: "Stock adjustments" } },
      { path: "new", name: "adjust-new", component: () => import("../views/stock-adjustment/AdjustmentForm.vue"), meta: { title: "New stock adjustment" } },
      { path: "details", name: "adjust-details", component: () => import("../views/stock-adjustment/AdjustmentDetails.vue"), meta: { title: "Stock adjustment details" } },
    ],
  },

  {
    path: "expenses",
    component: () => import("../views/expenses/ExpensesPage.vue"),
    children: [
      { path: "", name: "expenses", component: () => import("../views/expenses/ExpenseList.vue"), meta: { title: "Expenses" } },
      { path: "new", name: "expenses-new", component: () => import("../views/expenses/ExpenseForm.vue"), meta: { title: "New expense" } },
      { path: "details", name: "expenses-details", component: () => import("../views/expenses/ExpenseDetails.vue"), meta: { title: "Expense details" } },
      { path: "categories", name: "expenses-categories", component: () => import("../views/expenses/ExpenseCategories.vue"), meta: { title: "Expense categories" } },
    ],
  },

  {
    path: "payment-accounts",
    component: () => import("../views/payment-accounts/PaymentAccountsPage.vue"),
    children: [
      { path: "", name: "accounts", component: () => import("../views/payment-accounts/AccountList.vue"), meta: { title: "Payment accounts" } },
      { path: "details", name: "accounts-details", component: () => import("../views/payment-accounts/AccountDetails.vue"), meta: { title: "Account details" } },
    ],
  },

  {
    path: "accounting",
    component: () => import("../views/accounting/AccountingPage.vue"),
    children: [
      { path: "", name: "accounting", component: () => import("../views/accounting/ChartOfAccounts.vue"), meta: { title: "Chart of accounts" } },
      { path: "journals", name: "accounting-journals", component: () => import("../views/accounting/Journals.vue"), meta: { title: "Journals" } },
      { path: "journals/new", name: "accounting-journal-new", component: () => import("../views/accounting/JournalForm.vue"), meta: { title: "New journal" } },
      { path: "statements", name: "accounting-statements", component: () => import("../views/accounting/Statements.vue"), meta: { title: "Statements" } },
      { path: "close", name: "accounting-close", component: () => import("../views/accounting/PeriodClose.vue"), meta: { title: "Period close" } },
    ],
  },

  { path: "reports", name: "reports", component: () => import("../views/ReportsView.vue"), meta: { title: "Reports" } },
  { path: "bookings", name: "bookings", component: () => import("../views/BookingsView.vue"), meta: { title: "Bookings" } },
  { path: "orders", name: "orders", component: () => import("../views/OrdersView.vue"), meta: { title: "Orders" } },
  { path: "settings", name: "settings", component: () => import("../views/SettingsView.vue"), meta: { title: "Settings" } },
  // My HR: every signed-in user's own HR records (no HRM permission needed).
  { path: "my-hr", name: "my-hr", component: () => import("../views/MyHrView.vue"), meta: { title: "My HR" } },
  { path: "settings/price-rules", name: "settings-price-rules", component: () => import("../views/PriceRulesView.vue"), meta: { title: "Price rules" } },

  {
    path: "hrm",
    component: () => import("../views/hrm/HrmPage.vue"),
    children: [
      { path: "", name: "hrm", component: () => import("../views/hrm/EmployeeList.vue"), meta: { title: "Employees" } },
      { path: "employee", name: "hrm-employee", component: () => import("../views/hrm/EmployeeProfile.vue"), meta: { title: "Employee" } },
      { path: "attendance", name: "hrm-attendance", component: () => import("../views/hrm/Attendance.vue"), meta: { title: "Attendance" } },
      { path: "leave", name: "hrm-leave", component: () => import("../views/hrm/Leave.vue"), meta: { title: "Leave" } },
      { path: "payroll", name: "hrm-payroll", component: () => import("../views/hrm/Payroll.vue"), meta: { title: "Payroll" } },
      { path: "tips", name: "hrm-tips", component: () => import("../views/hrm/Tips.vue"), meta: { title: "Tips" } },
      // The new HRM screens (plain Vue, one file each, on the HRM API)
      { path: "dashboard", name: "hrm-dashboard", component: () => import("../views/hrm/HrmDashboard.vue"), meta: { title: "HRM dashboard" } },
      { path: "daily", name: "hrm-daily", component: () => import("../views/hrm/DailyBoard.vue"), meta: { title: "Daily attendance" } },
      { path: "approvals", name: "hrm-approvals", component: () => import("../views/hrm/Approvals.vue"), meta: { title: "Approvals" } },
      { path: "files", name: "hrm-files", component: () => import("../views/hrm/StaffFiles.vue"), meta: { title: "Staff files" } },
      { path: "files/:id", name: "hrm-file", component: () => import("../views/hrm/StaffFile.vue"), meta: { title: "Staff file", parent: "hrm-files" } },
      { path: "leave-balances", name: "hrm-leave-balances", component: () => import("../views/hrm/LeaveBalances.vue"), meta: { title: "Leave balances" } },
      { path: "overtime", name: "hrm-overtime", component: () => import("../views/hrm/Overtime.vue"), meta: { title: "Overtime" } },
      { path: "reports", name: "hrm-reports", component: () => import("../views/hrm/HrmReports.vue"), meta: { title: "Attendance reports" } },
      { path: "salary", name: "hrm-salary", component: () => import("../views/hrm/Salary.vue"), meta: { title: "Salary" } },
      { path: "deductions", name: "hrm-deductions", component: () => import("../views/hrm/Deductions.vue"), meta: { title: "Deductions" } },
      { path: "monthly-payroll", name: "hrm-monthly-payroll", component: () => import("../views/hrm/MonthlyPayroll.vue"), meta: { title: "Monthly payroll" } },
      { path: "shifts", name: "hrm-shifts", component: () => import("../views/hrm/Shifts.vue"), meta: { title: "Shifts" } },
      { path: "holidays", name: "hrm-holidays", component: () => import("../views/hrm/Holidays.vue"), meta: { title: "Holidays" } },
      { path: "policies", name: "hrm-policies", component: () => import("../views/hrm/Policies.vue"), meta: { title: "HRM policies" } },
      { path: "setup", name: "hrm-setup", component: () => import("../views/hrm/HrmSetup.vue"), meta: { title: "HRM setup" } },
    ],
  },
];

const routes: RouteRecordRaw[] = [
  { path: "/", component: AdminLayout, children: shellPages },
  // Full-screen pages without the shell.
  { path: "/pos", name: "pos", component: () => import("../views/PosView.vue"), meta: { title: "POS Terminal" } },
  { path: "/customer-display", name: "customer-display", component: () => import("../views/CustomerDisplay.vue"), meta: { title: "Customer display" } },
  { path: "/kitchen", name: "kitchen", component: () => import("../views/KitchenView.vue"), meta: { title: "Kitchen" } },
  { path: "/login", name: "login", component: () => import("../views/LoginView.vue"), meta: { title: "Sign in", public: true } },
  { path: "/register", name: "register", component: () => import("../views/RegisterView.vue"), meta: { title: "Create account", public: true } },
  { path: "/:pathMatch(.*)*", redirect: "/" },
];

const router = createRouter({
  history: createWebHashHistory(),
  routes,
});

// The screen a URL belongs to: /user-management/roles -> "users". The POS needs the Sell permission.
function screenOf(path: string) {
  const first = "/" + path.split("/")[1];
  const screen = Object.keys(VIEW_PATHS).find((k) => (VIEW_PATHS as Record<string, string>)[k] === first);
  return screen === "pos" ? "sell" : screen;
}

// Sign-in required; pages the user's role cannot view go to the first page it can.
// A signed-in user does not see the sign-in or create-account page: they go to their home page.
router.beforeEach(async (to) => {
  if (to.meta.public) {
    if (!isSignedIn()) return true;
    try {
      const user = await loadUser();
      return homeFor(user.role, to.query.next);
    } catch (e) {
      await logout(); // the saved sign-in no longer works: show the page
      return true;
    }
  }
  if (!isSignedIn()) return { name: "login", query: { next: to.fullPath } };

  try {
    await loadUser();
  } catch (e) {
    await logout();
    return { name: "login", query: { next: to.fullPath } };
  }

  const screen = screenOf(to.path);
  if (!screen || can(screen + ".view")) return true;

  const allowed = Object.keys(VIEW_PATHS).find((k) => can((k === "pos" ? "sell" : k) + ".view"));
  return allowed ? (VIEW_PATHS as Record<string, string>)[allowed] : { name: "login" };
});

router.afterEach((to) => {
  apiError.value = ""; // an error belongs to the page it happened on
  document.title = to.meta.title ? `${to.meta.title} · ${TITLE}` : TITLE;
});

export default router;
