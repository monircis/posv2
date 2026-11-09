// Main ERP app: sidebar, shared screens (Home, Users, Contacts, Products, Purchases, Sell) and page routing
import { DCLogic } from "../runtime/logic.js";
import { goView, navCollapsed, VIEW_PATHS } from "../runtime/nav.js";
import { TransfersLogic } from "./modules/transfers.js";
import { AdjustLogic } from "./modules/adjust.js";
import { ExpensesLogic } from "./modules/expenses.js";
import { AccountsLogic } from "./modules/accounts.js";
import { AccountingLogic } from "./modules/accounting.js";
import { ReportsLogic } from "./modules/reports.js";
import { BookingsLogic } from "./modules/bookings.js";
import { KitchenLogic } from "./modules/kitchen.js";
import { OrdersLogic } from "./modules/orders.js";
import { SettingsLogic } from "./modules/settings.js";
import { HrmLogic } from "./modules/hrm.js";
import { MfgLogic } from "./modules/mfg.js";
const NAV = [
  ['Home','Hm',0],['User Management','Us',1],['Contacts','Ct',1],['Products','Pr',1],
  ['Manufacturing','Mf',0],['Purchases','Pu',1],['Sell','Sl',1],['Stock Transfers','St',0],
  ['Stock Adjustment','Sa',0],['Expenses','Ex',1],['Payment Accounts','Pa',1],['Accounting','Ac',1],
  ['Reports','Rp',1],['Bookings','Bk',0],['Kitchen','Kt',0],['Orders','Or',0],
  ['Settings','Se',1],['HRM','Hr',1]
];

const KPIS = [
  {label:'Gross sales today', value:'4,265.75', delta:'12.4% vs last Tuesday', up:true},
  {label:'Transactions', value:'38', delta:'6 more than average', up:true},
  {label:'Average sale', value:'112.26', delta:'3.1% below target', up:false},
  {label:'Outstanding due', value:'2,615.25', delta:'3 invoices on account', up:false}
];

const BARS = [8,14,10,22,18,12,31,26,19,24,44,38,20,15,28,52,36,30,41,68,47,33,58,72,49,55,80,62,74,96];

const ALERTS = [
  {count:'6', label:'Products low or out of stock', tone:'warn', go:'products'},
  {count:'3', label:'Sales invoices awaiting payment', tone:'error', go:'sell'},
  {count:'1', label:'Bill overdue · Coolaire Refrigeration', tone:'error', go:'expenses'},
  {count:'2', label:'Purchase orders not yet received', tone:'info', go:'purchases'},
  {count:'1', label:'Draft journal waiting to be posted', tone:'neutral', go:'accounting'}
];

const ACCOUNTS = [
  {name:'Cash drawer · Main branch', bal:'1,840.50'},
  {name:'Cash drawer · Bankstown', bal:'612.30'},
  {name:'CBA Business', bal:'28,412.35'},
  {name:'Tax saver', bal:'14,250.00'}
];

const SALES = [
  {inv:'2026-5014', time:'Today 10:12', cust:'Walk in', method:'Card', total:'86.40', st:'paid'},
  {inv:'2026-5013', time:'Today 09:58', cust:'Hassan Grocers', method:'On account', total:'412.80', st:'due'},
  {inv:'2026-5012', time:'Yesterday 14:31', cust:'Walk in', method:'Card', total:'219.10', st:'paid'},
  {inv:'2026-5011', time:'Yesterday 14:12', cust:'Hassan Grocers', method:'Bank transfer', total:'1,842.00', st:'due'},
  {inv:'2026-5009', time:'Yesterday 13:40', cust:'Al Amin Catering', method:'Multiple', total:'720.45', st:'partial'},
  {inv:'2026-5008', time:'Yesterday 13:22', cust:'Walk in', method:'Card', total:'100.08', st:'paid'}
];

const LOW = [
  {name:'Beef Croquettes', alert:'10 kg', qty:'0 kg', tone:'out'},
  {name:'Beef Scotch Fillet', alert:'8 kg', qty:'0 kg', tone:'out'},
  {name:'Beef Curry', alert:'6 kg', qty:'1 kg', tone:'low'},
  {name:'Lamb Shoulder', alert:'10 kg', qty:'3 kg', tone:'low'},
  {name:'Beef Liver', alert:'8 kg', qty:'6 kg', tone:'low'},
  {name:'Chicken Thigh', alert:'15 kg', qty:'9 kg', tone:'low'}
];

const HOME_NOTES = [
  {title:'Four numbers, not fourteen', body:'The reference dashboard led with four zeros and an empty chart. This opens with the figures a manager checks hourly, each carrying its own comparison so the number means something on its own.'},
  {title:'Alerts are a queue, not a widget', body:'Anything needing a decision collapses into one list with a count and a destination. Low stock, overdue invoices, unreceived POs and unposted journals all route from here.'},
  {title:'No empty tables', body:'Panels with no data show an empty state with the action that fills them, never a bare "No data available in table" row inside export buttons.'}
];

const POS_TOOLS = ['Recent','Drafts','Register','Expense','Returns'];

const CATS = ['All','Beef','Lamb','Goat','Chicken','Butcher','Grocery'];

const PRODUCTS = [
  {name:'Beef Mince', price:'11.00', stock:'54', cat:'Beef'},
  {name:'Beef Rump Steak', price:'24.90', stock:'41', cat:'Beef'},
  {name:'Beef Osso Buco', price:'21.30', stock:'23', cat:'Beef'},
  {name:'Beef Kabab', price:'5.03', stock:'18', cat:'Beef'},
  {name:'Beef Liver', price:'11.00', stock:'6', cat:'Beef'},
  {name:'Beef Curry', price:'11.00', stock:'1', cat:'Beef'},
  {name:'Beef Croquettes', price:'11.50', stock:'0', cat:'Beef'},
  {name:'Beef Scotch Fillet', price:'27.32', stock:'0', cat:'Beef'},
  {name:'Lamb Shoulder', price:'19.80', stock:'3', cat:'Lamb'},
  {name:'Lamb Chops', price:'28.40', stock:'27', cat:'Lamb'},
  {name:'Lamb Mince', price:'16.90', stock:'34', cat:'Lamb'},
  {name:'Goat Curry Cut', price:'22.60', stock:'12', cat:'Goat'},
  {name:'Chicken Thigh', price:'9.40', stock:'9', cat:'Chicken'},
  {name:'Chicken Breast', price:'13.20', stock:'46', cat:'Chicken'},
  {name:'Chicken Wings', price:'8.10', stock:'52', cat:'Chicken'},
  {name:'Marinated Skewers', price:'14.50', stock:'20', cat:'Butcher'},
  {name:'Beef Sausage', price:'12.00', stock:'31', cat:'Butcher'},
  {name:'Basmati Rice 5kg', price:'18.00', stock:'44', cat:'Grocery'},
  {name:'Olive Oil 1L', price:'15.60', stock:'26', cat:'Grocery'},
  {name:'Spice Mix 200g', price:'6.40', stock:'60', cat:'Grocery'}
];

const POS_NOTES = [
  {title:'Three zones, left to right', body:'Categories, products, order. The eye moves the way the sale does. Search sits above the grid and takes barcode scans: any digit typed anywhere jumps into it, and an exact SKU plus Enter adds the item.'},
  {title:'Weight is asked, not assumed', body:'Cuts sold by the kilo open a scale keypad with presets and a live price. Stock is checked on every add, plus-step and edit, and tiles show what is left after the current cart.'},
  {title:'Pricing follows the customer', body:'Picking a wholesale or charity customer applies group pricing at once. Order discounts over 10% ask for a manager PIN, matching the permission ceiling set in User Management.'},
  {title:'Payment is a real step', body:'Cash with 5c rounding and change, card via the terminal (approve or decline), split tenders, or charge to account with a credit-limit check. Hold, resume, tables and recent sales all work.'}
];

const COLUMNS = [
  {name:'New', tone:'info', tickets:[
    {no:'#5014', where:'Table 7 · Dine in', time:'0:42', late:false, action:'Start preparing', items:[
      {q:2, name:'Beef Kabab Plate', note:'No onion'},
      {q:1, name:'Lamb Chops', note:''}
    ]},
    {no:'#5013', where:'Collection · Hassan', time:'1:20', late:false, action:'Start preparing', items:[
      {q:3, name:'Marinated Skewers', note:'Extra spice'},
      {q:2, name:'Chicken Wings', note:''}
    ]}
  ]},
  {name:'Preparing', tone:'warn', tickets:[
    {no:'#5011', where:'Table 4 · Dine in', time:'8:05', late:false, action:'Mark ready', items:[
      {q:1, name:'Beef Osso Buco', note:'Well done'},
      {q:2, name:'Basmati Rice', note:''}
    ]},
    {no:'#5009', where:'Delivery · Al Amin', time:'14:32', late:true, action:'Mark ready', items:[
      {q:6, name:'Beef Kabab Plate', note:'Catering order'},
      {q:4, name:'Chicken Thigh Grill', note:''}
    ]},
    {no:'#5008', where:'Table 2 · Dine in', time:'11:10', late:false, action:'Mark ready', items:[
      {q:1, name:'Lamb Shoulder Roast', note:'Slice thin'}
    ]}
  ]},
  {name:'Ready', tone:'success', tickets:[
    {no:'#5007', where:'Table 9 · Dine in', time:'2:14', late:false, action:'Mark served', items:[
      {q:2, name:'Beef Rump Steak', note:'Medium rare'}
    ]},
    {no:'#5005', where:'Collection · Riverside', time:'6:48', late:true, action:'Mark served', items:[
      {q:4, name:'Beef Sausage', note:''},
      {q:1, name:'Spice Mix', note:''}
    ]}
  ]},
  {name:'Served', tone:'neutral', tickets:[
    {no:'#5004', where:'Table 1 · Dine in', time:'closed', late:false, action:'View order', items:[
      {q:2, name:'Chicken Breast Grill', note:''}
    ]},
    {no:'#5002', where:'Collection · Walk in', time:'closed', late:false, action:'View order', items:[
      {q:1, name:'Beef Mince', note:''}
    ]}
  ]}
];

const KITCHEN_NOTES = [
  {title:'Dark ground on purpose', body:'Kitchen displays sit under hot overhead lighting and are read from two metres. Dark ground, white cards, 14px minimum item text and 17px ticket numbers.'},
  {title:'One action per ticket', body:'Each card carries the single next status as a full-width button. No dropdowns, no multi-select, nothing that needs precision from a gloved hand.'},
  {title:'Elapsed time, not placed time', body:'Timers count up and turn red past the prep estimate. A clock time tells a cook nothing; two minutes over does.'},
  {title:'Fires from POS and Orders', body:'Tickets arrive when a POS sale is flagged as a kitchen order or an Order is confirmed. Status changes write straight back to the Orders timeline.'}
];

const USERS = [
  {name:'Aminul Haque', email:'aminul@madinah.com.au', role:'Owner', locations:'All', lastActive:'now', status:'active'},
  {name:'Sara Rahman', email:'sara.rahman@madinah.com.au', role:'Manager', locations:'Main branch', lastActive:'12 min ago', status:'active'},
  {name:'Yusuf Karim', email:'yusuf.k@madinah.com.au', role:'Cashier', locations:'Main branch', lastActive:'1 hr ago', status:'active'},
  {name:'Fatima Noor', email:'fatima.noor@madinah.com.au', role:'Cashier', locations:'Main branch', lastActive:'3 hr ago', status:'active'},
  {name:'Imran Sheikh', email:'imran@madinah.com.au', role:'Kitchen', locations:'Main branch', lastActive:'26 min ago', status:'active'},
  {name:'Nadia Islam', email:'nadia.islam@madinah.com.au', role:'Accountant', locations:'All', lastActive:'2 days ago', status:'active'},
  {name:'Omar Siddiq', email:'omar.siddiq@madinah.com.au', role:'Stock keeper', locations:'Warehouse', lastActive:'never', status:'invited'},
  {name:'Rashid Alam', email:'rashid.alam@madinah.com.au', role:'Cashier', locations:'Main branch', lastActive:'41 days ago', status:'off'}
];

const ROLES = [
  {name:'Owner', kind:'Built in', users:1, perms:'All 18 modules', desc:'Unrestricted. Includes period close, role editing and business settings. Cannot be deleted or reduced.'},
  {name:'Manager', kind:'Built in', users:1, perms:'14 modules', desc:'Runs the floor. Sells, purchases, adjusts stock, reads reports. No role editing, no period close, no payroll.'},
  {name:'Cashier', kind:'Built in', users:3, perms:'4 modules', desc:'POS, Orders, Bookings and their own sales list. Cannot see cost price, margin or any report.'},
  {name:'Accountant', kind:'Custom', users:1, perms:'7 modules', desc:'Accounting, Payment Accounts, Expenses, Reports. Read-only on Sell and Purchases so figures can be traced to source.'},
  {name:'Kitchen', kind:'Built in', users:1, perms:'2 modules', desc:'Kitchen board and Orders only. Signs in on the shared tablet with a PIN rather than a password.'},
  {name:'Stock keeper', kind:'Custom', users:1, perms:'5 modules', desc:'Receives purchases, runs transfers and adjustments, counts stock. No pricing, no customers, no payments.'}
];

const ACTIVITY = [
  {when:'29/09 14:22', what:'Voided sale 2026-5010 — 64.80', meta:'Reason: wrong customer · approved by Aminul', tone:'warn'},
  {when:'29/09 13:58', what:'Applied 15% discount on sale 2026-5009', meta:'Above the 10% ceiling for Manager · flagged', tone:'warn'},
  {when:'29/09 11:04', what:'Received purchase PO-2041 from Baraka Meats', meta:'18 lines · stock updated at Main branch', tone:'success'},
  {when:'29/09 09:12', what:'Opened register 1', meta:'Opening float 300.00 · Main branch', tone:'info'},
  {when:'28/09 18:40', what:'Closed register 1', meta:'Counted 2,140.50 · no variance', tone:'success'},
  {when:'28/09 15:30', what:'Edited product Beef Scotch Fillet', meta:'Selling price 24.90 → 27.32', tone:'info'},
  {when:'27/09 10:02', what:'Signed in from a new device', meta:'iPad · Main branch Wi-Fi', tone:'neutral'}
];

const EFFECTIVE = [
  {key:'Sl', name:'Sell', actions:'View, create, edit, void', src:'Role'},
  {key:'Or', name:'Orders', actions:'View, create, edit, status', src:'Role'},
  {key:'Pr', name:'Products', actions:'View, create, edit', src:'Role'},
  {key:'Pu', name:'Purchases', actions:'View, create, edit', src:'Role'},
  {key:'Ct', name:'Contacts', actions:'View, create, edit', src:'Role'},
  {key:'St', name:'Stock Transfers', actions:'View, create', src:'Role'},
  {key:'Sa', name:'Stock Adjustment', actions:'View, create', src:'Role'},
  {key:'Rp', name:'Reports', actions:'View', src:'Role'},
  {key:'Ex', name:'Expenses', actions:'View, create, edit, delete', src:'Override'},
  {key:'Hr', name:'HRM', actions:'View attendance only', src:'Override'}
];

const SESSIONS = [
  {device:'Register 1 — iPad', agent:'Safari 18 · iPadOS', where:'Main branch', when:'active now', state:'Current'},
  {device:'Back office — Windows', agent:'Chrome 140 · Windows 11', where:'Main branch', when:'2 hr ago', state:'Idle'},
  {device:'Personal — Android', agent:'Chrome 140 · Android 15', where:'Sydney, AU', when:'3 days ago', state:'Expired'}
];

const META = [
  {k:'Employee ID', v:'EMP-0042', mono:true},
  {k:'Role', v:'Manager'},
  {k:'Locations', v:'Main branch'},
  {k:'POS PIN', v:'set · last changed 14 Aug', mono:false},
  {k:'Two-factor', v:'Enabled'},
  {k:'Created', v:'16/07/2025 by Aminul', mono:true},
  {k:'Last sign-in', v:'29/09/2026 09:08', mono:true}
];

const USER_STATS = [
  {k:'Sales rung up', v:'218'},
  {k:'Value', v:'24,806.40'},
  {k:'Discounts given', v:'412.20', warn:true},
  {k:'Voids', v:'3', warn:true}
];

const PERM_MODULES = [
  ['Hm','Home','dashboard widgets'],
  ['Us','User Management','roles, permissions'],
  ['Ct','Contacts',''],
  ['Pr','Products','cost price, margin'],
  ['Mf','Manufacturing',''],
  ['Pu','Purchases','supplier prices'],
  ['Sl','Sell','discount ceiling, void'],
  ['St','Stock Transfers',''],
  ['Sa','Stock Adjustment',''],
  ['Ex','Expenses',''],
  ['Pa','Payment Accounts',''],
  ['Ac','Accounting','period close'],
  ['Rp','Reports','profit figures'],
  ['Bk','Bookings',''],
  ['Kt','Kitchen',''],
  ['Or','Orders',''],
  ['Se','Settings',''],
  ['Hr','HRM','payroll']
];

const PERM_COLS = ['View','Create','Edit','Delete','All loc.'];

const PRESETS = {
  'Owner':       [1,1,1,1,1],
  'Manager':     [1,1,1,0,0],
  'Cashier':     [1,1,0,0,0],
  'Accountant':  [1,1,1,0,1],
  'Kitchen':     [1,0,1,0,0]
};

const PRESET_MODULES = {
  'Owner': null,
  'Manager': ['Hm','Ct','Pr','Mf','Pu','Sl','St','Sa','Ex','Pa','Rp','Bk','Kt','Or'],
  'Cashier': ['Hm','Sl','Bk','Or'],
  'Accountant': ['Hm','Ct','Pu','Sl','Ex','Pa','Ac','Rp'],
  'Kitchen': ['Kt','Or']
};

const ROLE_CHOICES = [
  {name:'Manager', desc:'Runs the floor. Sells, purchases, reads reports.'},
  {name:'Cashier', desc:'POS and Orders only. No cost price, no reports.'},
  {name:'Accountant', desc:'Accounting and Reports. Read-only on transactions.'},
  {name:'Kitchen', desc:'Kitchen board only. PIN sign-in on a shared tablet.'}
];

const USER_TOGGLES = [
  {name:'Require two-factor', desc:'Authenticator app at every sign-in', on:true},
  {name:'Allow POS PIN sign-in', desc:'Faster on shared terminals; no password prompt', on:true},
  {name:'Can give discounts above 10%', desc:'Otherwise larger discounts need a manager PIN', on:false}
];

const USER_NOTES = [
  {title:'One permission grid, not one screen per module', body:'Eighteen modules by five actions in a single toggleable grid. Module-specific rules — cost price visibility, discount ceiling, period close — hang off their row as a sensitive-field tag instead of becoming their own page.'},
  {title:'Roles are templates, not cages', body:'Pick a preset, adjust, and the role becomes custom. The grid shows exactly what changed, so nobody has to guess what "Manager" means in this business.'},
  {title:'Permissions are hidden, not greyed out', body:'A cashier sees four modules in the sidebar. Showing seventeen disabled links teaches staff to ignore the nav and generates support calls.'},
  {title:'Activity is the audit trail', body:'Voids, over-ceiling discounts, price edits and register opens are logged on the user, not buried in a separate report. Anything unusual is tinted.'}
];

const CONTACTS = [
  {name:'Al Amin Catering', sub:'Ibrahim Khalil · +61 413 220 887', type:'Customer', group:'Wholesale', business:'48,204.10', bal:1842.45, last:'28/09'},
  {name:'Baraka Meats', sub:'Supplier · +61 2 9740 1188', type:'Supplier', group:'30-day terms', business:'112,840.00', bal:-6420.00, last:'29/09'},
  {name:'Riverside Cafe', sub:'Elena Petrov · +61 411 907 552', type:'Customer', group:'Wholesale', business:'21,660.80', bal:0, last:'29/09'},
  {name:'Hassan Grocers', sub:'Hassan Ali · +61 412 118 340', type:'Both', group:'Wholesale', business:'67,412.20', bal:1842.00, last:'29/09'},
  {name:'Walk in', sub:'Default counter customer', type:'Customer', group:'Retail', business:'184,920.55', bal:0, last:'29/09'},
  {name:'Sydney Halal Supply', sub:'Supplier · +61 2 9600 4421', type:'Supplier', group:'14-day terms', business:'38,110.00', bal:-1280.00, last:'26/09'},
  {name:'Noor Restaurant', sub:'Bilal Noor · +61 414 552 001', type:'Customer', group:'Wholesale', business:'33,880.40', bal:620.80, last:'25/09'},
  {name:'Fresh Produce Co', sub:'Supplier · +61 2 9812 6600', type:'Supplier', group:'7-day terms', business:'14,290.00', bal:0, last:'23/09'},
  {name:'Mariam Chowdhury', sub:'+61 402 771 118', type:'Customer', group:'Retail', business:'2,140.60', bal:0, last:'21/09'}
];

const CONTACT_KPIS = [
  {label:'Receivable', value:'4,305.25', sub:'from 3 customers', tone:'error'},
  {label:'Payable', value:'7,700.00', sub:'to 2 suppliers', tone:'warn'},
  {label:'Overdue receivable', value:'1,842.45', sub:'oldest 34 days', tone:'error'},
  {label:'Active this month', value:'31', sub:'of 9 contacts shown', tone:'plain'}
];

const C_TX = [
  {ref:'2026-5009', date:'28/09/2026', kind:'Sale', total:'720.45', due:'720.45', status:'partial'},
  {ref:'2026-4988', date:'26/09/2026', kind:'Sale', total:'1,240.00', due:'0.00', status:'paid'},
  {ref:'PAY-0912', date:'26/09/2026', kind:'Payment received', total:'1,240.00', due:'0.00', status:'paid'},
  {ref:'2026-4901', date:'19/09/2026', kind:'Sale', total:'1,122.00', due:'1,122.00', status:'due'},
  {ref:'CN-0034', date:'15/09/2026', kind:'Credit note', total:'184.20', due:'0.00', status:'paid'},
  {ref:'2026-4820', date:'09/09/2026', kind:'Sale', total:'2,406.40', due:'0.00', status:'paid'},
  {ref:'PAY-0874', date:'09/09/2026', kind:'Payment received', total:'2,406.40', due:'0.00', status:'paid'}
];

const C_LEDGER = [
  {date:'09/09/2026', detail:'Invoice 2026-4820', debit:'2,406.40', credit:'—', bal:'2,406.40'},
  {date:'09/09/2026', detail:'Payment PAY-0874 · bank transfer', debit:'—', credit:'2,406.40', bal:'0.00'},
  {date:'15/09/2026', detail:'Credit note CN-0034 · short delivery', debit:'—', credit:'184.20', bal:'-184.20'},
  {date:'19/09/2026', detail:'Invoice 2026-4901', debit:'1,122.00', credit:'—', bal:'937.80'},
  {date:'26/09/2026', detail:'Invoice 2026-4988', debit:'1,240.00', credit:'—', bal:'2,177.80'},
  {date:'26/09/2026', detail:'Payment PAY-0912 · bank transfer', debit:'—', credit:'1,240.00', bal:'937.80'},
  {date:'28/09/2026', detail:'Invoice 2026-5009', debit:'720.45', credit:'—', bal:'1,658.25'}
];

const C_ADDRESSES = [
  {kind:'Billing', tag:'Default', lines:'14 Haldon Street, Lakemba NSW 2195, Australia'},
  {kind:'Shipping', tag:'Delivery', lines:'Unit 3, 88 Canterbury Road, Bankstown NSW 2200, Australia'}
];

const C_DOCS = [
  {ext:'PDF', name:'signed-credit-application.pdf', meta:'412 KB · uploaded 16/07/2025'},
  {ext:'PDF', name:'abn-registration.pdf', meta:'188 KB · uploaded 16/07/2025'},
  {ext:'JPG', name:'delivery-dock-photo.jpg', meta:'1.2 MB · uploaded 04/03/2026'}
];

const C_META = [
  {k:'Contact ID', v:'CT-0118', mono:true},
  {k:'Customer group', v:'Wholesale'},
  {k:'Price group', v:'Wholesale ex-GST'},
  {k:'Payment terms', v:'30 days'},
  {k:'Credit limit', v:'10,000.00', mono:true},
  {k:'ABN', v:'62 884 220 117', mono:true},
  {k:'Customer since', v:'16/07/2025', mono:true}
];

const C_STATS = [
  {k:'Total business', v:'48,204.10'},
  {k:'Invoices', v:'64'},
  {k:'Average invoice', v:'753.19'},
  {k:'Average days to pay', v:'22'}
];

const GROUPS = [
  {name:'Retail', desc:'Walk-in counter customers', price:'Default selling price', discount:'0%', terms:'On sale', count:412, bal:0},
  {name:'Wholesale', desc:'Restaurants, caterers, cafes', price:'Wholesale ex-GST', discount:'8%', terms:'30 days', count:38, bal:4305.25},
  {name:'Staff', desc:'Employee purchases', price:'Cost + 10%', discount:'0%', terms:'Payroll deduct', count:8, bal:184.40},
  {name:'Charity', desc:'Registered community organisations', price:'Cost price', discount:'15%', terms:'14 days', count:4, bal:0},
  {name:'Online', desc:'Catalogue QR and phone orders', price:'Default selling price', discount:'0%', terms:'Prepaid', count:126, bal:0}
];

const CONTACT_NOTES = [
  {title:'One form, conditional sections', body:'Customer, Supplier and Both are a segmented choice at the top of the form. Picking one reveals only the terms that apply — the reference app kept both blocks visible and marked half of them irrelevant.'},
  {title:'Balance is the headline', body:'A contact record exists mostly to answer "do they owe us?". Balance due leads the detail sidebar with ageing split, and the list colours receivable against payable rather than showing an unsigned number.'},
  {title:'Ledger separate from transactions', body:'Transactions list documents; the ledger shows running balance with debits, credits and a closing figure an accountant can tie to the trial balance.'},
  {title:'Groups drive pricing', body:'Assigning a group sets price group, default discount and payment terms together, so POS pricing needs no per-contact price list.'}
];

const CTYPES = [
  {name:'Customer', hint:'Sells to. Reveals customer group, credit limit and opening balance.', footer:'Will appear in POS customer search'},
  {name:'Supplier', hint:'Buys from. Reveals pay term and advance balance.', footer:'Will appear in purchase supplier search'},
  {name:'Both', hint:'Trades both ways — a restaurant that also supplies you. Reveals both sets of terms and keeps one ledger.', footer:'One ledger, two transaction streams'}
];

const P_ROWS = [
  {name:'Beef Mince', meta:'Kilogram · Halal Prime', cat:'Beef', sku:'6603', cost:8.20, price:11.00, stock:54, alert:20},
  {name:'Beef Rump Steak', meta:'Kilogram · Halal Prime', cat:'Beef', sku:'6600', cost:16.80, price:24.90, stock:41, alert:10},
  {name:'Beef Osso Buco', meta:'Kilogram', cat:'Beef', sku:'6605', cost:14.20, price:21.30, stock:23, alert:8},
  {name:'Beef Kabab', meta:'Kilogram · house made', cat:'Butcher', sku:'6607', cost:3.90, price:5.03, stock:18, alert:12},
  {name:'Beef Liver', meta:'Kilogram', cat:'Beef', sku:'6604', cost:7.40, price:11.00, stock:6, alert:8},
  {name:'Beef Curry', meta:'Kilogram · diced', cat:'Beef', sku:'6592', cost:8.10, price:11.00, stock:1, alert:6},
  {name:'Beef Croquettes', meta:'Kilogram · frozen', cat:'Butcher', sku:'6609', cost:6.80, price:11.50, stock:0, alert:10},
  {name:'Beef Scotch Fillet', meta:'Kilogram · Halal Prime', cat:'Beef', sku:'6595', cost:18.40, price:27.32, stock:0, alert:8},
  {name:'Lamb Shoulder', meta:'Kilogram', cat:'Lamb', sku:'6621', cost:13.60, price:19.80, stock:3, alert:10},
  {name:'Lamb Chops', meta:'Kilogram', cat:'Lamb', sku:'6622', cost:19.40, price:28.40, stock:27, alert:10},
  {name:'Lamb Mince', meta:'Kilogram', cat:'Lamb', sku:'6623', cost:11.80, price:16.90, stock:34, alert:12},
  {name:'Goat Curry Cut', meta:'Kilogram · bone in', cat:'Goat', sku:'6631', cost:15.90, price:22.60, stock:12, alert:8},
  {name:'Chicken Thigh', meta:'Kilogram · skin on', cat:'Chicken', sku:'6641', cost:6.20, price:9.40, stock:9, alert:15},
  {name:'Chicken Breast', meta:'Kilogram', cat:'Chicken', sku:'6642', cost:9.10, price:13.20, stock:46, alert:15},
  {name:'Chicken Wings', meta:'Kilogram', cat:'Chicken', sku:'6640', cost:5.40, price:8.10, stock:52, alert:15},
  {name:'Beef Sausage', meta:'Kilogram · house made', cat:'Butcher', sku:'6602', cost:8.00, price:12.00, stock:31, alert:12},
  {name:'Basmati Rice 5kg', meta:'Each', cat:'Grocery', sku:'7102', cost:13.20, price:18.00, stock:44, alert:10},
  {name:'Olive Oil 1L', meta:'Each', cat:'Grocery', sku:'7108', cost:11.40, price:15.60, stock:26, alert:8},
  {name:'Spice Mix 200g', meta:'Each · house blend', cat:'Grocery', sku:'7120', cost:3.80, price:6.40, stock:60, alert:20}
];

const P_CATS = ['All','Beef','Lamb','Goat','Chicken','Butcher','Grocery'];

const P_STOCK = [
  {loc:'Main branch', onHand:'0 kg', alert:'8 kg', committed:'12 kg', value:'0.00', tone:'out'},
  {loc:'Warehouse', onHand:'0 kg', alert:'20 kg', committed:'0 kg', value:'0.00', tone:'out'},
  {loc:'All locations', onHand:'0 kg', alert:'—', committed:'12 kg', value:'0.00', tone:'out'}
];

const P_MOVES = [
  {date:'29/09', kind:'Sale', ref:'2026-5001', delta:-4, bal:'0'},
  {date:'28/09', kind:'Sale', ref:'2026-4994', delta:-6, bal:'4'},
  {date:'27/09', kind:'Stock adjustment', ref:'ADJ-0212', delta:-2, bal:'10'},
  {date:'25/09', kind:'Purchase received', ref:'PO-2038', delta:12, bal:'12'},
  {date:'23/09', kind:'Transfer out', ref:'TR-0088', delta:-8, bal:'0'},
  {date:'21/09', kind:'Sale', ref:'2026-4820', delta:-5, bal:'8'},
  {date:'18/09', kind:'Purchase received', ref:'PO-2031', delta:13, bal:'13'}
];

const P_PURCHASES = [
  {date:'25/09/2026', supplier:'Baraka Meats', qty:'12 kg', cost:18.40, total:'220.80', rise:true},
  {date:'18/09/2026', supplier:'Baraka Meats', qty:'13 kg', cost:17.90, total:'232.70', rise:true},
  {date:'04/09/2026', supplier:'Sydney Halal Supply', qty:'20 kg', cost:16.40, total:'328.00', rise:false},
  {date:'12/03/2026', supplier:'Baraka Meats', qty:'18 kg', cost:15.60, total:'280.80', rise:false}
];

const P_SALES_BARS = [3,5,2,7,4,6,9,5,8,4,11,7,6,3,8,12,9,7,10,14,8,6,11,15,9,12,17,13,15,18];

const P_VARIANTS = [
  {name:'Whole (2–3 kg)', sku:'6595-W', cost:'18.40', price:'27.32', stock:'0 kg', tone:'out'},
  {name:'Portioned 250g', sku:'6595-P', cost:'19.80', price:'29.90', stock:'14 pk', tone:'ok'},
  {name:'Marinated', sku:'6595-M', cost:'21.20', price:'32.40', stock:'6 pk', tone:'low'}
];

const P_PRICING = [
  {k:'Cost ex. tax', v:'18.40'},
  {k:'Margin', v:'35.0%'},
  {k:'Price ex. tax', v:'24.84'},
  {k:'GST 10%', v:'2.48'},
  {k:'Price inc. tax', v:'27.32', strong:true},
  {k:'Wholesale ex-GST', v:'22.90'}
];

const P_ATTRS = [
  {k:'SKU', v:'6595', mono:true},
  {k:'Barcode', v:'Code 128 (C128)'},
  {k:'Category / brand', v:'Beef · Halal Prime'},
  {k:'Unit', v:'Kilogram (kg)'},
  {k:'Prep time', v:'—'},
  {k:'Last edited', v:'28/09/2026 by Sara', mono:true}
];

const TAXONOMIES = [
  {name:'Categories', rows:[
    {name:'Beef', sub:'12 sub-categories', count:'24'},
    {name:'Lamb', sub:'3 sub-categories', count:'11'},
    {name:'Goat', sub:'', count:'6'},
    {name:'Chicken', sub:'2 sub-categories', count:'9'},
    {name:'Butcher', sub:'house made', count:'7'},
    {name:'Grocery', sub:'', count:'4'}
  ]},
  {name:'Brands', rows:[
    {name:'Halal Prime', sub:'', count:'18'},
    {name:'Baraka', sub:'', count:'9'},
    {name:'House made', sub:'', count:'7'},
    {name:'Sunrise Foods', sub:'', count:'4'},
    {name:'Unbranded', sub:'', count:'23'}
  ]},
  {name:'Units', rows:[
    {name:'Kilogram (kg)', sub:'allows decimals', count:'44'},
    {name:'Gram (g)', sub:'0.001 kg', count:'3'},
    {name:'Each', sub:'whole numbers only', count:'12'},
    {name:'Pack', sub:'', count:'2'},
    {name:'Litre (L)', sub:'allows decimals', count:'0'}
  ]}
];

const P_TYPES = [
  {name:'Single', hint:'One SKU, one price, one stock figure. Covers most of the catalogue.'},
  {name:'Variable', hint:'Multiple variations under one product — cut, weight, marinade. Each row gets its own SKU, price and stock, shown in the grid below.'}
];

const P_TOGGLES = [
  {name:'Manage stock', desc:'Track quantity per location and warn below alert', on:true},
  {name:'Available at POS', desc:'Appears in the terminal product grid', on:true},
  {name:'Allow negative stock', desc:'Sell past zero; use for made-to-order items', on:false}
];

const PRODUCT_NOTES = [
  {title:'Margin is a column, not a mystery', body:'The list shows cost, price and margin together and tints margins under 20%. The reference app scattered cost, price, tax and margin across a wide horizontal scroll with no derived figure.'},
  {title:'Variations reveal, not relocate', body:'Switching type to Variable opens a variation grid inside the same form. The reference app had a separate Variations page in the sidebar plus Selling Price Group, Update Price and Print Labels as four more.'},
  {title:'Stock has a paper trail', body:'Every quantity change lists its cause and running balance, so a negative figure can be traced rather than guessed at. Reference stock figures went negative with no explanation available.'},
  {title:'Six taxonomies, one form', body:'Categories, brands, units, expense categories, departments and designations are the same name-and-parent record. One modal serves all six instead of six sidebar entries.'}
];

const PU_ROWS = [
  {ref:'PO-2043', date:'29/09/2026', supplier:'Baraka Meats', lines:'6 lines', status:'ordered', pay:'due', total:1980.00, due:1980.00, loc:'Main branch'},
  {ref:'PO-2041', date:'29/09/2026', supplier:'Baraka Meats', lines:'5 lines', status:'received', pay:'partial', total:4444.00, due:2420.00, loc:'Main branch'},
  {ref:'PO-2040', date:'27/09/2026', supplier:'Fresh Produce Co', lines:'12 lines', status:'received', pay:'paid', total:612.40, due:0, loc:'Main branch'},
  {ref:'PO-2039', date:'26/09/2026', supplier:'Sydney Halal Supply', lines:'4 lines', status:'received', pay:'partial', total:2280.00, due:1280.00, loc:'Warehouse'},
  {ref:'PO-2038', date:'25/09/2026', supplier:'Baraka Meats', lines:'8 lines', status:'received', pay:'paid', total:3106.80, due:0, loc:'Main branch'},
  {ref:'PO-2037', date:'22/09/2026', supplier:'Sunrise Foods', lines:'3 lines', status:'pending', pay:'due', total:420.00, due:0, loc:'Main branch'},
  {ref:'PO-2036', date:'18/09/2026', supplier:'Baraka Meats', lines:'7 lines', status:'received', pay:'paid', total:2840.20, due:0, loc:'Main branch'},
  {ref:'PO-2035', date:'11/09/2026', supplier:'Fresh Produce Co', lines:'9 lines', status:'received', pay:'paid', total:488.60, due:0, loc:'Main branch'}
];

const PU_STATUS = {ordered:['Ordered','info'], received:['Received','success'], pending:['Pending','neutral']};
const PU_PAY = {paid:['Paid','success'], partial:['Partial','warn'], due:['Due','error']};

const PU_SUGGEST = [
  {name:'Beef Scotch Fillet', note:'0 kg · alert 8', cost:18.40},
  {name:'Lamb Shoulder', note:'3 kg · alert 10', cost:13.60},
  {name:'Chicken Thigh', note:'9 kg · alert 15', cost:6.20}
];

const PU_DETAIL_LINES = [
  {name:'Beef Rump Steak', qty:'60 kg', cost:'16.80', after:'101 kg', total:'1,008.00'},
  {name:'Beef Mince', qty:'80 kg', cost:'8.20', after:'134 kg', total:'656.00'},
  {name:'Lamb Chops', qty:'40 kg', cost:'19.40', after:'67 kg', total:'776.00'},
  {name:'Beef Osso Buco', qty:'40 kg', cost:'14.20', after:'63 kg', total:'568.00'},
  {name:'Goat Curry Cut', qty:'53.6 kg', cost:'15.90', after:'65.6 kg', total:'852.00'}
];

const PU_JOURNAL = [
  {code:'1310', name:'Inventory — meat', dr:'3,860.00', cr:''},
  {code:'5120', name:'Freight inwards', dr:'180.00', cr:''},
  {code:'1820', name:'GST paid', dr:'404.00', cr:''},
  {code:'2110', name:'Accounts payable — Baraka Meats', dr:'', cr:'4,444.00'}
];

const PU_PAYMENTS = [
  {method:'Bank transfer', meta:'29/09 · CBA Business · PAY-S-0441', amt:'1,500.00'},
  {method:'Cash', meta:'29/09 · on delivery · PAY-S-0442', amt:'524.00'}
];

const PU_STEPS = [
  {name:'Ordered', when:'25/09 09:40', state:'done'},
  {name:'Received', when:'29/09 11:04', state:'done'},
  {name:'Posted', when:'29/09 11:06', state:'done'},
  {name:'Paid', when:'2,420.00 due', state:'current'}
];

const RET_LINES = [
  {name:'Beef Rump Steak', received:60, cost:16.80, onHand:101},
  {name:'Beef Mince', received:80, cost:8.20, onHand:134},
  {name:'Lamb Chops', received:40, cost:19.40, onHand:67},
  {name:'Beef Osso Buco', received:40, cost:14.20, onHand:63},
  {name:'Goat Curry Cut', received:53.6, cost:15.90, onHand:65.6}
];

const RET_REASONS = ['Damaged','Temperature breach','Short dated','Wrong item','Over-delivered','Quality'];

const RET_SETTLE = [
  {name:'Reduce amount owed', desc:'Take the credit off the 2,420.00 still due on PO-2041.'},
  {name:'Refund to account', desc:'The supplier pays the money back into CBA Business.'},
  {name:'Hold as supplier credit', desc:'Keep the credit on Baraka Meats and use it on a later purchase.'}
];

const PR_ROWS = [
  {ref:'PR-0018', date:'27/09/2026', po:'PO-2038', supplier:'Baraka Meats', reason:'Temperature breach', settle:'Reduced owed', credit:'462.00', status:'Credited'},
  {ref:'PR-0017', date:'23/09/2026', po:'PO-2036', supplier:'Baraka Meats', reason:'Damaged', settle:'Supplier credit', credit:'218.40', status:'Credited'},
  {ref:'PR-0016', date:'19/09/2026', po:'PO-2035', supplier:'Fresh Produce Co', reason:'Short dated', settle:'Refund', credit:'96.90', status:'Awaiting refund'},
  {ref:'PR-0015', date:'12/09/2026', po:'PO-2034', supplier:'Sydney Halal Supply', reason:'Wrong item', settle:'Refund', credit:'309.00', status:'Credited'}
];

const S_ROWS = [
  {inv:'2026-5014', time:'29/09 10:12', cust:'Walk in', items:'3 items', channel:'POS', method:'Card', total:86.40, due:0, st:'paid', by:'Yusuf'},
  {inv:'2026-5013', time:'29/09 09:58', cust:'Hassan Grocers', items:'5 items', channel:'Order', method:'On account', total:412.80, due:412.80, st:'due', by:'Sara'},
  {inv:'2026-5012', time:'28/09 14:31', cust:'Walk in', items:'4 items', channel:'POS', method:'Card', total:219.10, due:0, st:'paid', by:'Fatima'},
  {inv:'2026-5011', time:'28/09 14:12', cust:'Hassan Grocers', items:'9 items', channel:'Order', method:'Bank transfer', total:1842.00, due:1842.00, st:'due', by:'Sara'},
  {inv:'2026-5010', time:'28/09 13:58', cust:'Walk in', items:'2 items', channel:'POS', method:'Cash', total:64.80, due:0, st:'void', by:'Sara'},
  {inv:'2026-5009', time:'28/09 13:40', cust:'Al Amin Catering', items:'5 items', channel:'Order', method:'Multiple', total:720.45, due:360.45, st:'partial', by:'Sara'},
  {inv:'2026-5008', time:'28/09 13:22', cust:'Walk in', items:'2 items', channel:'POS', method:'Card', total:100.08, due:0, st:'paid', by:'Yusuf'},
  {inv:'2026-5007', time:'28/09 12:55', cust:'Riverside Cafe', items:'6 items', channel:'Online', method:'Card', total:386.20, due:0, st:'paid', by:'Online'},
  {inv:'D-0042', time:'28/09 11:30', cust:'Noor Restaurant', items:'8 items', channel:'POS', method:'—', total:640.00, due:0, st:'draft', by:'Sara'},
  {inv:'Q-0118', time:'27/09 16:05', cust:'Al Amin Catering', items:'14 items', channel:'Quote', method:'—', total:3280.00, due:0, st:'quote', by:'Aminul'}
];

const S_STATUS = {
  paid:['Paid','success'], partial:['Partial','warn'], due:['Due','error'],
  void:['Voided','neutral'], draft:['Draft','neutral'], quote:['Quotation','info']
};

const S_INV_LINES = [
  {name:'Beef Kabab Plate — catering tray', qty:'6', price:'54.00', amt:'324.00'},
  {name:'Chicken Thigh Grill — catering tray', qty:'4', price:'48.00', amt:'192.00'},
  {name:'Basmati Rice 5kg', qty:'4', price:'18.00', amt:'72.00'},
  {name:'Spice Mix 200g', qty:'10', price:'6.40', amt:'64.00'},
  {name:'Olive Oil 1L', qty:'3', price:'16.67', amt:'50.00'}
];

const S_JOURNAL = [
  {code:'1210', name:'Accounts receivable — Al Amin', dr:'360.45', cr:''},
  {code:'1110', name:'Cash drawer — Register 1', dr:'360.00', cr:''},
  {code:'4110', name:'Sales — wholesale', dr:'', cr:'645.84'},
  {code:'4190', name:'Delivery income', dr:'', cr:'15.00'},
  {code:'2310', name:'GST collected', dr:'', cr:'59.61'}
];

const S_PAYMENTS = [
  {method:'Cash', meta:'28/09 13:40 · Register 1 · Sara', amt:'360.00'}
];

const S_FULFIL = [
  {name:'Order placed', meta:'28/09 13:40 · POS', done:true},
  {name:'Kitchen — preparing', meta:'13:42 · Imran', done:true},
  {name:'Ready', meta:'14:18 · 36 min prep', done:true},
  {name:'Out for delivery', meta:'14:26 · driver Karim', done:false}
];

const SR_LINES = [
  {name:'Beef Kabab Plate — catering tray', sold:6, price:54.00, chilled:true},
  {name:'Chicken Thigh Grill — catering tray', sold:4, price:48.00, chilled:true},
  {name:'Basmati Rice 5kg', sold:4, price:18.00, chilled:false},
  {name:'Spice Mix 200g', sold:10, price:6.40, chilled:false},
  {name:'Olive Oil 1L', sold:3, price:16.6667, chilled:false}
];

const SR_REASONS = ['Wrong item','Damaged','Quality','Customer changed mind','Over-supplied'];

const SR_SETTLE = [
  {name:'Reduce balance due', desc:'Take it off the 360.45 still owed on this invoice.'},
  {name:'Refund to original method', desc:'Cash back from Register 1, or reversal on the card.'},
  {name:'Store credit', desc:'Held on Al Amin Catering for the next order.'}
];

const SELL_NOTES = [
  {title:'One sales list for every channel', body:'POS, online and order sales sit together with a channel filter. Drafts and quotations are status tabs rather than separate sidebar entries. The reference app had 16 items under Sell.'},
  {title:'Payment is a step, not a panel', body:'Tender choice, cash quick-amounts, change due and the card terminal state appear in one overlay once Pay is pressed. Try it from the POS tab.'},
  {title:'The invoice is the record', body:'Sale details shows the tax invoice as the customer receives it, with journal, payments, fulfilment timeline and customer balance alongside it.'},
  {title:'Returns know about fresh stock', body:'The sell return reuses the purchase return form but adds a restock toggle per line. It defaults off for chilled goods, so returned meat is written off rather than put back on sale.'}
];

const PURCHASE_NOTES = [
  {title:'Built for keyboard entry', body:'One scan field adds lines; quantity steps with −/+; totals recalc on every change. The reference form was a long page with a separate product grid, a tax table, a shipping block and a payment block below the fold.'},
  {title:'Reorder is one click away', body:'Products below alert quantity appear as suggested lines under the search. Tapping one adds it at the last cost paid. Try it.'},
  {title:'Consequences are stated', body:'The sidebar spells out what receiving will do: units into which location, how many costs update, and which journal gets drafted. Nobody should have to guess what Save changes.'},
  {title:'One form, six workflows', body:'Sales, stock transfers, adjustments, production orders and both kinds of return use this exact shell. Only the header fields and the summary rules change.'},
  {title:'Returns start from the purchase', body:'A return always opens from the original PO. Its lines, costs and supplier are locked, quantities are capped at what was received, and every returned line needs a reason, so the credit always matches something the supplier actually billed.'}
];

// The record lists the screens read. Pages fill them from the API
// (see the API section at the bottom of each page) and then re-render.
export const DATA = {
  homeAccounts: ACCOUNTS, homeSales: SALES, lowStock: LOW,
  users: USERS, roles: ROLES,
  contacts: CONTACTS, groups: GROUPS,
  products: P_ROWS, taxonomies: TAXONOMIES,
  purchases: PU_ROWS, purchaseReturns: PR_ROWS,
  sales: S_ROWS
};

// Replaces a list's contents in place, so every screen using it sees the new rows.
export function replaceRows(list, rows) {
  list.splice(0, list.length, ...rows);
}

const TONE = {
  info:['var(--c-primary-soft)','var(--c-primary-text)','var(--c-primary)'],
  warn:['#fdf1dd','#8a5300','#d98c1a'],
  error:['#fdeaea','#b02020','#d93a3a'],
  success:['#e7f6ee','#0f6b43','#2f9e63'],
  neutral:['#eef2f6','#5d6b7d','#8fa3b6']
};

export class ErpApp extends DCLogic {
  state = {
    view: this.props.view || 'home',
    sub: 'list',
    csub: 'list',
    pusub: 'list',
    pustatus: 'All',
    ssub: 'list',
    sstatus: 'All',
    schannel: 'All',
    srQty: [1, 0, 0, 0, 0],
    srReason: [2, 0, 0, 0, 0],
    srRestock: SR_LINES.map(l => !l.chilled),
    srSettle: 0,
    payOpen: false,
    tender: 'Cash',
    tendered: 0,
    saleDone: false,
    retQty: [12, 0, 0, 0, 0],
    retReason: [1, 0, 0, 0, 0],
    retSettle: 0,
    puPay: 'Part paid',
    puLines: [
      {id:1, name:'Beef Rump Steak', cost:16.80, qty:40, disc:0, last:16.80},
      {id:2, name:'Beef Mince', cost:8.20, qty:60, disc:0, last:8.20},
      {id:3, name:'Lamb Chops', cost:19.40, qty:20, disc:5, last:19.10},
      {id:4, name:'Chicken Breast', cost:9.10, qty:30, disc:0, last:9.10}
    ],
    psub: 'list',
    pdtab: 'stock',
    pcat: 'All',
    pstock: 'All',
    pview: 'table',
    ptype: 'Single',
    pFormOpen: false,
    pToggles: P_TOGGLES.map(t => t.on),
    cdtab: 'tx',
    ctype: 'Customer',
    ctypeFilter: 'All',
    dueOnly: false,
    cFormOpen: false,
    dtab: 'activity',
    statusFilter: 'All',
    preset: 'Manager',
    grants: null,
    formOpen: false,
    formRole: 'Manager',
    toggles: USER_TOGGLES.map(t => t.on),
    cat: 'All',
    cart: [
      {id:1, name:'Beef Rump Steak', sku:'6600', price:24.90, qty:2},
      {id:2, name:'Lamb Chops', sku:'6621', price:28.40, qty:1},
      {id:3, name:'Chicken Wings', sku:'6640', price:8.10, qty:3},
      {id:4, name:'Basmati Rice 5kg', sku:'7102', price:18.00, qty:1}
    ]
  };

  // Switching to another screen goes to that screen's route.
  setState(p, cb) {
    if (p && typeof p === 'object' && typeof p.view === 'string' && p.view !== this.state.view && VIEW_PATHS[p.view]) {
      goView(p.view);
      return;
    }
    return super.setState(p, cb);
  }

  // Called when the page unmounts: stop module timers so nothing runs off screen.
  dispose() {
    Object.values(this._mods).forEach(m => {
      if (m.componentWillUnmount) m.componentWillUnmount();
      (m._t || []).forEach(clearTimeout);
    });
  }

  tab(id) {
    const on = this.state.view === id;
    return 'padding:7px 14px;border-radius:7px;font-size:12.5px;font-weight:600;cursor:pointer;white-space:nowrap;'
      + (on ? 'background:#ffffff;color:#0f1c2b;box-shadow:0 1px 2px rgba(15,28,43,.12);' : 'color:#5d6b7d;');
  }

  money(n) { return n.toLocaleString('en-AU', {minimumFractionDigits:2, maximumFractionDigits:2}); }

  bump(id, d) {
    this.setState(st => ({
      cart: st.cart.map(c => c.id === id ? { ...c, qty: Math.max(1, c.qty + d) } : c)
    }));
  }

  add(name, price) {
    this.setState(st => {
      const hit = st.cart.find(c => c.name === name);
      if (hit) return { cart: st.cart.map(c => c.name === name ? { ...c, qty: c.qty + 1 } : c) };
      return { cart: st.cart.concat([{ id: Date.now(), name, sku: '—', price, qty: 1 }]) };
    });
  }

  presetGrants(name) {
    const role = ROLES.find(r => r.name === name);
    if (role && role.grants) return JSON.parse(JSON.stringify(role.grants)); // from the API
    const row = PRESETS[name] || PRESETS.Manager;
    const allowed = PRESET_MODULES[name];
    const out = {};
    PERM_MODULES.forEach(m => {
      const on = allowed === null || allowed.indexOf(m[0]) > -1;
      out[m[0]] = on ? row.slice() : [0, 0, 0, 0, 0];
    });
    return out;
  }

  grants() { return this.state.grants || this.presetGrants(this.state.preset); }

  setGrants(g) { this.setState({ grants: g }); }

  toggleCell(mk, ci) {
    const g = this.grants();
    const next = {};
    Object.keys(g).forEach(k => { next[k] = g[k].slice(); });
    next[mk][ci] = next[mk][ci] ? 0 : 1;
    this.setGrants(next);
  }

  toggleCol(ci) {
    const g = this.grants();
    const anyOff = PERM_MODULES.some(m => !g[m[0]][ci]);
    const next = {};
    Object.keys(g).forEach(k => { next[k] = g[k].slice(); next[k][ci] = anyOff ? 1 : 0; });
    this.setGrants(next);
  }

  fillAll(v) {
    const next = {};
    PERM_MODULES.forEach(m => { next[m[0]] = [v, v, v, v, v]; });
    this.setGrants(next);
  }

  subTab(id) {
    const on = this.state.sub === id;
    return 'padding:6px 11px;border-radius:7px;font-size:12px;font-weight:600;cursor:pointer;white-space:nowrap;'
      + (on ? 'background:#0f1c2b;color:#ffffff;' : 'color:#5d6b7d;border:1px solid #dfe6ee;');
  }

  pageTab(on) {
    return 'padding:9px 14px;font-size:13px;cursor:pointer;border-bottom:2px solid '
      + (on ? 'var(--c-primary);color:var(--c-primary);font-weight:600;' : 'transparent;color:#5d6b7d;font-weight:500;');
  }

  navFor(mod, items, cur, setKey) {
    const S = this.state;
    return NAV.map(n => {
      const on = n[0] === mod;
      return {
        name: n[0], key: n[1], open: on,
        children: on ? items.map(c => ({
          name: c.name,
          onClick: () => this.setState({ [setKey]: c.k }),
          style: 'padding:6px 8px;border-radius:6px;font-size:12.5px;cursor:pointer;'
            + (c.matches.indexOf(cur) > -1 ? 'color:#ffffff;font-weight:600;background:rgba(255,255,255,.08);' : 'color:#9dbdd2;')
        })) : [],
        style: 'display:flex;align-items:center;gap:10px;padding:8px 10px;border-radius:8px;cursor:pointer;font-size:13px;font-weight:500;margin-bottom:1px;'
          + (on ? 'background:rgba(255,255,255,.13);color:#ffffff;box-shadow:inset 2px 0 0 var(--c-accent);' : 'color:#c4dced;'),
        iconStyle: 'width:20px;height:20px;border-radius:5px;display:grid;place-items:center;font-family:\'IBM Plex Mono\',monospace;font-size:9px;font-weight:600;flex:none;'
          + (on ? 'background:var(--c-accent);color:#06283a;' : 'background:rgba(255,255,255,.1);color:#9dbdd2;'),
        caretStyle: 'color:#7fa3bb;font-size:11px;flex:none;' + (n[2] ? '' : 'visibility:hidden;')
      };
    });
  }

  subTabsFor(defs, cur, key) {
    return defs.map(s => ({
      name: s[1],
      onClick: () => this.setState({ [key]: s[0] }),
      style: 'padding:6px 11px;border-radius:7px;font-size:12px;font-weight:600;cursor:pointer;white-space:nowrap;'
        + (cur === s[0] ? 'background:#0f1c2b;color:#ffffff;' : 'color:#5d6b7d;border:1px solid #dfe6ee;')
    }));
  }

  badge(t) {
    return 'background:' + t[0] + ';color:' + t[1] + ';border-radius:5px;padding:3px 8px;font-size:11.5px;font-weight:600';
  }

  bumpLine(id, d) {
    this.setState(st => ({ puLines: st.puLines.map(l => l.id === id ? { ...l, qty: Math.max(1, l.qty + d) } : l) }));
  }

  setRet(i, v) {
    this.setState(st => {
      const q = st.retQty.slice();
      q[i] = Math.max(0, Math.min(RET_LINES[i].received, Math.round(v * 10) / 10));
      return { retQty: q };
    });
  }

  returnVals() {
    const S = this.state;
    const sub = RET_LINES.reduce((a, l, i) => a + l.cost * S.retQty[i], 0);
    const tax = sub * 0.10;
    const total = sub + tax;
    const qty = S.retQty.reduce((a, q) => a + q, 0);
    const lines = S.retQty.filter(q => q > 0).length;
    const has = qty > 0;
    const owed = 2420;
    const afterOwed = Math.max(0, owed - total);
    const overflow = Math.max(0, total - owed);
    const settle = S.retSettle;
    const afterLabel = settle === 0 ? 'Owed on PO-2041 after' : settle === 1 ? 'Refund expected' : 'Supplier credit held';
    const afterVal = settle === 0 ? afterOwed : total;
    const afterHint = settle === 0
      ? (overflow > 0 ? this.money(overflow) + ' more than is owed \u2014 the rest is held as supplier credit' : 'Down from ' + this.money(owed))
      : settle === 1 ? 'Shows as a receivable from Baraka Meats until it lands'
      : 'Offered automatically on the next Baraka Meats purchase';
    const journal = settle === 1 ? 'Supplier refund receivable' : 'Accounts payable';
    const n = RET_LINES.length;
    return {
      retLines: RET_LINES.map((l, i) => {
        const q = S.retQty[i];
        const on = q > 0;
        const atMax = q >= l.received;
        return {
          name: l.name,
          onHand: on ? 'on hand ' + l.onHand + ' \u2192 ' + (Math.round((l.onHand - q) * 10) / 10) + ' kg' : 'on hand ' + l.onHand + ' kg',
          received: l.received + ' kg',
          qty: q,
          cost: this.money(l.cost),
          credit: on ? this.money(l.cost * q) : '\u2014',
          reason: on ? RET_REASONS[S.retReason[i]] : 'Not returning',
          inc: () => this.setRet(i, q + 1),
          dec: () => this.setRet(i, q - 1),
          cycle: () => { if (!on) return; this.setState(st => { const r = st.retReason.slice(); r[i] = (r[i] + 1) % RET_REASONS.length; return { retReason: r }; }); },
          rowStyle: 'display:grid;grid-template-columns:1.7fr 0.8fr 1.15fr 1.3fr 0.8fr 0.9fr;gap:10px;padding:9px 16px;align-items:center;'
            + (i < n - 1 ? 'border-bottom:1px solid #f0f4f8;' : '')
            + (on ? 'background:#fff8f3;box-shadow:inset 3px 0 0 #d98c1a;' : ''),
          qtyStyle: 'flex:1;text-align:center;font-family:\'IBM Plex Mono\',monospace;font-size:12.5px;font-weight:600;border-left:1px solid #eaeff5;border-right:1px solid #eaeff5;height:100%;display:grid;place-items:center;'
            + (on ? 'color:#8a5300;' : 'color:#9aa7b6;'),
          incStyle: 'width:28px;height:100%;display:grid;place-items:center;font-size:14px;flex:none;'
            + (atMax ? 'color:#d3dbe4;cursor:not-allowed;' : 'color:#5d6b7d;cursor:pointer;'),
          reasonStyle: 'height:32px;border-radius:7px;padding:0 9px;display:flex;align-items:center;justify-content:space-between;gap:6px;font-size:12px;white-space:nowrap;overflow:hidden;'
            + (on ? 'border:1px solid #f0d9ac;background:#ffffff;color:#8a5300;font-weight:600;cursor:pointer;' : 'border:1px solid #eef2f6;color:#b3c0cf;cursor:default;'),
          creditStyle: 'font-family:\'IBM Plex Mono\',monospace;font-size:13px;text-align:right;font-weight:600;' + (on ? '' : 'color:#c2cedb;')
        };
      }),
      retAll: () => this.setState({ retQty: RET_LINES.map(l => l.received) }),
      retClear: () => this.setState({ retQty: RET_LINES.map(() => 0) }),
      retLineCount: lines,
      retQty: Math.round(qty * 10) / 10,
      retSub: this.money(sub),
      retTax: this.money(tax),
      retTotal: this.money(total),
      retSettle: RET_SETTLE.map((s, i) => {
        const on = settle === i;
        return {
          name: s.name, desc: s.desc,
          onClick: () => this.setState({ retSettle: i }),
          style: 'display:flex;align-items:flex-start;gap:11px;padding:10px 12px;border-radius:9px;cursor:pointer;'
            + (on ? 'border:1px solid var(--c-primary);background:var(--c-primary-tint);' : 'border:1px solid #dfe6ee;'),
          radio: 'width:17px;height:17px;border-radius:50%;display:grid;place-items:center;flex:none;margin-top:1px;border:1.5px solid ' + (on ? 'var(--c-primary);' : '#c2cedb;'),
          inner: 'width:8px;height:8px;border-radius:50%;background:' + (on ? 'var(--c-primary)' : 'transparent')
        };
      }),
      retAfterLabel: afterLabel,
      retAfter: this.money(afterVal),
      retAfterHint: afterHint,
      retImpact: has
        ? '\u2212' + (Math.round(qty * 10) / 10) + ' kg out of Main branch across ' + lines + (lines === 1 ? ' product' : ' products') + '. A journal is drafted debiting ' + journal + ' ' + this.money(total) + ', crediting Inventory ' + this.money(sub) + ' and GST paid ' + this.money(tax) + '. Return note PR-0019 goes to Baraka Meats.'
        : 'Nothing selected yet. Raise a quantity on at least one line.',
      retImpactBox: 'border-radius:12px;padding:14px 16px;'
        + (has ? 'background:#fdf1dd;border:1px solid #f0d9ac;color:#8a5300;' : 'background:#f6f9fc;border:1px solid #dfe6ee;color:#7d8b9c;'),
      retSubmitLabel: has ? 'Submit return \u00b7 ' + this.money(total) : 'Submit return',
      retSubmitStyle: 'height:34px;padding:0 15px;border-radius:8px;display:flex;align-items:center;font-size:12.5px;font-weight:600;'
        + (has ? 'background:var(--c-primary);color:#ffffff;cursor:pointer;' : 'background:#b7c6d6;color:#ffffff;cursor:not-allowed;pointer-events:none;'),
      prRows: PR_ROWS.map(r => ({
        ...r,
        badge: this.badge(r.status === 'Credited' ? TONE.success : TONE.warn)
      }))
    };
  }

  posPayVals(total) {
    const S = this.state;
    const t = S.tender;
    const tendered = S.tendered;
    const short = t === 'Cash' && tendered < total - 0.005;
    const change = tendered - total;
    const empty = S.cart.length === 0 || total <= 0;
    const ready = !empty && (t === 'Card' || t === 'Split' || (t === 'Cash' && !short));
    const round = n => Math.ceil(n / 5) * 5;
    const quick = [total, round(total), round(total) + 20, 100 * Math.ceil(total / 100), 100 * Math.ceil(total / 100) + 50]
      .filter((v, i, a) => a.findIndex(x => Math.abs(x - v) < 0.01) === i).slice(0, 5);
    const splitCard = Math.round(total * 0.6 * 100) / 100;
    return {
      openPay: () => { if (!empty) this.setState({ payOpen: true, tender: 'Cash', tendered: 0 }); },
      payBtnLabel: empty ? 'Pay' : 'Pay \u00b7 ' + this.money(total),
      payBtnStyle: 'height:46px;flex:2;border-radius:10px;display:grid;place-items:center;font-size:14px;font-weight:700;color:#ffffff;'
        + (empty ? 'background:#b7c6d6;cursor:not-allowed;pointer-events:none;' : 'background:var(--c-primary);cursor:pointer;'),
      closePay: () => this.setState({ payOpen: false }),
      payOpen: S.payOpen,
      tenders: [['Cash','Drawer 1'],['Card','Tyro 01'],['Split','2+ tenders'],['Account','Charge later']].map(x => {
        const on = t === x[0];
        return {
          name: x[0], sub: x[1],
          onClick: () => this.setState({ tender: x[0] }),
          style: 'border-radius:11px;padding:12px 13px;cursor:pointer;min-height:60px;'
            + (on ? 'background:var(--c-primary);color:#ffffff;' : 'border:1px solid #dfe6ee;color:#0f1c2b;')
        };
      }),
      tenderCash: t === 'Cash', tenderCard: t === 'Card', tenderSplit: t === 'Split', tenderAccount: t === 'Account',
      tenderedLabel: tendered ? this.money(tendered) : '0.00',
      quickCash: quick.map((v, i) => ({
        label: i === 0 ? 'Exact' : this.money(v).replace('.00', ''),
        onClick: () => this.setState({ tendered: v }),
        style: 'height:44px;border-radius:9px;display:grid;place-items:center;font-family:\'IBM Plex Mono\',monospace;font-size:13px;font-weight:600;cursor:pointer;'
          + (Math.abs(tendered - v) < 0.01 ? 'background:var(--c-primary-soft);border:1px solid var(--c-primary);color:var(--c-primary-text);' : 'border:1px solid #dfe6ee;color:#0f1c2b;')
      })),
      splitRows: [
        {method:'Card — Tyro 01', amt:this.money(splitCard), state:'Approved', tone:'success'},
        {method:'Cash', amt:this.money(total - splitCard), state:'Pending', tone:'warn'}
      ].map(r => ({ ...r, badge: this.badge(TONE[r.tone]) })),
      changeLabel: t === 'Cash' ? (short ? 'Still to collect' : 'Change to give') : t === 'Card' ? 'Waiting for terminal' : t === 'Split' ? 'Remaining on cash' : 'Cannot charge',
      changeValue: t === 'Cash' ? this.money(Math.abs(change)) : t === 'Card' ? this.money(total) : t === 'Split' ? this.money(total - splitCard) : '\u2014',
      changeBox: 'border-radius:11px;padding:12px 14px;'
        + (t === 'Cash' && !short ? 'background:#2f9e63;color:#ffffff;' : t === 'Account' ? 'background:rgba(217,58,58,.2);color:#ffb3b3;' : 'background:rgba(217,140,26,.2);color:#ffd08a;'),
      completeLabel: ready ? 'Complete sale' : t === 'Account' ? 'Pick a customer first' : 'Enter cash tendered',
      completeStyle: 'height:48px;border-radius:11px;display:grid;place-items:center;font-size:14.5px;font-weight:700;'
        + (ready ? 'background:var(--c-accent);color:#06283a;cursor:pointer;' : 'background:rgba(255,255,255,.1);color:#6f8599;cursor:not-allowed;pointer-events:none;'),
      completeSale: () => this.setState({ payOpen: false, saleDone: true }),
      saleDone: S.saleDone,
      doneLine: t === 'Cash' ? 'Cash ' + this.money(tendered) + ' \u00b7 change ' + this.money(Math.max(0, change)) : t + ' \u00b7 ' + this.money(total),
      newSale: () => this.setState({ saleDone: false, cart: [] })
    };
  }

  setSr(i, v) {
    this.setState(st => {
      const q = st.srQty.slice();
      q[i] = Math.max(0, Math.min(SR_LINES[i].sold, v));
      return { srQty: q };
    });
  }

  sellVals() {
    const S = this.state;
    const rows = S_ROWS.filter(r => {
      const stOk = S.sstatus === 'All'
        || (S.sstatus === 'Paid' && r.st === 'paid')
        || (S.sstatus === 'Unpaid' && (r.st === 'due' || r.st === 'partial'))
        || (S.sstatus === 'Drafts' && r.st === 'draft')
        || (S.sstatus === 'Quotations' && r.st === 'quote')
        || (S.sstatus === 'Voided' && r.st === 'void');
      const chOk = S.schannel === 'All' || r.channel === S.schannel;
      return stOk && chOk;
    });
    const counted = r => r.st !== 'draft' && r.st !== 'quote' && r.st !== 'void';
    const counts = {
      All: S_ROWS.length,
      Paid: S_ROWS.filter(r => r.st === 'paid').length,
      Unpaid: S_ROWS.filter(r => r.st === 'due' || r.st === 'partial').length,
      Drafts: S_ROWS.filter(r => r.st === 'draft').length,
      Quotations: S_ROWS.filter(r => r.st === 'quote').length,
      Voided: S_ROWS.filter(r => r.st === 'void').length
    };
    const disc = 0.92;
    const srSub = SR_LINES.reduce((a, l, i) => a + l.price * S.srQty[i] * disc, 0);
    const srTax = srSub * 0.10;
    const srTotal = srSub + srTax;
    const srCount = S.srQty.filter(q => q > 0).length;
    const srUnits = S.srQty.reduce((a, q) => a + q, 0);
    const restocked = S.srQty.reduce((a, q, i) => a + (q > 0 && S.srRestock[i] ? q : 0), 0);
    const written = srUnits - restocked;
    const has = srUnits > 0;
    const n = SR_LINES.length;
    return {
      isSell: S.view === 'sell',
      goSell: () => this.setState({ view:'sell' }),
      tabSell: this.tab('sell'),
      sSubTabs: [
        { name:'POS terminal', onClick: () => this.setState({ view:'pos' }), style:'padding:6px 11px;border-radius:7px;font-size:12px;font-weight:600;cursor:pointer;white-space:nowrap;color:#5d6b7d;border:1px solid #dfe6ee;' }
      ].concat(this.subTabsFor([['list','Sales list'],['details','Sale details'],['return','Sell return']], S.ssub, 'ssub')),
      sList: S.ssub === 'list', sDetails: S.ssub === 'details', sReturn: S.ssub === 'return',
      sGoList: () => this.setState({ ssub:'list' }),
      sGoDetails: () => this.setState({ ssub:'details' }),
      sGoReturn: () => this.setState({ ssub:'return' }),
      sellNav: this.navFor('Sell', [
        {name:'POS', k:'__pos', matches:[]},
        {name:'Sales', k:'list', matches:['list','details']},
        {name:'Drafts & quotations', k:'list', matches:[]},
        {name:'Sell returns', k:'return', matches:['return']}
      ], S.ssub, 'ssub').map(nv => nv.open ? { ...nv, children: nv.children.map((c, i) => i === 0 ? { ...c, onClick: () => this.setState({ view:'pos' }) } : i === 2 ? { ...c, onClick: () => this.setState({ ssub:'list', sstatus:'Drafts' }) } : c) } : nv),

      sStatusTabs: ['All','Paid','Unpaid','Drafts','Quotations','Voided'].map(t => {
        const on = S.sstatus === t;
        return {
          name: t, count: counts[t],
          onClick: () => this.setState({ sstatus: t }),
          style: this.pageTab(on),
          countStyle: 'font-family:\'IBM Plex Mono\',monospace;font-size:10.5px;border-radius:4px;padding:1px 5px;margin-left:6px;'
            + (on ? 'background:var(--c-primary-soft);color:var(--c-primary-text);' : 'background:#eef2f6;color:#7d8b9c;')
        };
      }),
      sKpis: [
        {label:'Sales, last 2 days', value:this.money(S_ROWS.filter(counted).reduce((a, r) => a + r.total, 0)), sub:'7 invoices · excludes drafts, quotes, voids', tone:''},
        {label:'Collected', value:this.money(S_ROWS.filter(counted).reduce((a, r) => a + r.total - r.due, 0)), sub:'cash, card and transfer', tone:'ok'},
        {label:'Still due', value:this.money(S_ROWS.reduce((a, r) => a + r.due, 0)), sub:'3 invoices on account', tone:'error'},
        {label:'Open quotations', value:'3,280.00', sub:'1 quote · expires 04/10', tone:'info'}
      ].map(k => ({
        ...k,
        valueStyle: 'font-family:\'IBM Plex Mono\',monospace;font-size:22px;font-weight:600;letter-spacing:-0.025em;line-height:1;'
          + (k.tone === 'error' ? 'color:#b02020;' : k.tone === 'ok' ? 'color:#0f6b43;' : k.tone === 'info' ? 'color:var(--c-primary-text);' : '')
      })),
      sChannels: ['All','POS','Online','Order'].map(c => ({
        name: c === 'All' ? 'All channels' : c,
        onClick: () => this.setState({ schannel: c }),
        style: 'height:34px;padding:0 12px;border-radius:8px;display:flex;align-items:center;font-size:12.5px;cursor:pointer;white-space:nowrap;'
          + (S.schannel === c ? 'background:var(--c-primary);color:#ffffff;font-weight:600;' : 'border:1px solid #dfe6ee;background:#ffffff;color:#4a5866;font-weight:500;')
      })),
      sRows: rows.map(r => {
        const s = S_STATUS[r.st];
        const muted = r.st === 'void';
        return {
          inv: r.inv, time: r.time, cust: r.cust, items: r.items, channel: r.channel, method: r.method, by: r.by,
          total: muted ? '(' + this.money(r.total) + ')' : this.money(r.total),
          due: r.due ? this.money(r.due) : '\u2014',
          dueStyle: 'font-family:\'IBM Plex Mono\',monospace;font-size:12.5px;text-align:right;' + (r.due ? 'color:#b02020;font-weight:600;' : 'color:#9aa7b6;'),
          status: s[0], badge: this.badge(TONE[s[1]])
        };
      }),
      sCount: rows.length + ' of ' + S_ROWS.length,
      sTotal: this.money(rows.filter(counted).reduce((a, r) => a + r.total, 0)),
      sDue: this.money(rows.reduce((a, r) => a + r.due, 0)),

      sInvLines: S_INV_LINES,
      sJournal: S_JOURNAL,
      sPayments: S_PAYMENTS,
      sFulfil: S_FULFIL.map((f, i) => ({
        name: f.name, meta: f.meta,
        dot: 'width:10px;height:10px;border-radius:50%;margin-top:4px;' + (f.done ? 'background:#2f9e63;' : 'background:#ffffff;box-shadow:inset 0 0 0 2px var(--c-primary);'),
        line: 'width:2px;flex:1;min-height:14px;' + (i === S_FULFIL.length - 1 ? 'background:transparent;' : f.done ? 'background:#bfe5d1;' : 'background:#e4eaf1;')
      })),

      srLines: SR_LINES.map((l, i) => {
        const q = S.srQty[i];
        const on = q > 0;
        const rs = S.srRestock[i];
        return {
          name: l.name, sold: l.sold, qty: q,
          price: this.money(l.price),
          refund: on ? this.money(l.price * q * disc) : '\u2014',
          reason: on ? SR_REASONS[S.srReason[i]] : 'Not returning',
          inc: () => this.setSr(i, q + 1),
          dec: () => this.setSr(i, q - 1),
          cycle: () => { if (!on) return; this.setState(st => { const r = st.srReason.slice(); r[i] = (r[i] + 1) % SR_REASONS.length; return { srReason: r }; }); },
          toggleRestock: () => this.setState(st => { const r = st.srRestock.slice(); r[i] = !r[i]; return { srRestock: r }; }),
          rowStyle: 'display:grid;grid-template-columns:1.7fr 0.75fr 1.15fr 1.1fr 0.85fr 0.9fr;gap:10px;padding:9px 16px;align-items:center;'
            + (i < n - 1 ? 'border-bottom:1px solid #f0f4f8;' : '')
            + (on ? 'background:#fff8f3;box-shadow:inset 3px 0 0 #d98c1a;' : ''),
          qtyStyle: 'flex:1;text-align:center;font-family:\'IBM Plex Mono\',monospace;font-size:12.5px;font-weight:600;border-left:1px solid #eaeff5;border-right:1px solid #eaeff5;height:100%;display:grid;place-items:center;'
            + (on ? 'color:#8a5300;' : 'color:#9aa7b6;'),
          incStyle: 'width:28px;height:100%;display:grid;place-items:center;font-size:14px;flex:none;'
            + (q >= l.sold ? 'color:#d3dbe4;cursor:not-allowed;' : 'color:#5d6b7d;cursor:pointer;'),
          reasonStyle: 'height:32px;border-radius:7px;padding:0 9px;display:flex;align-items:center;justify-content:space-between;gap:6px;font-size:12px;white-space:nowrap;overflow:hidden;'
            + (on ? 'border:1px solid #f0d9ac;background:#ffffff;color:#8a5300;font-weight:600;cursor:pointer;' : 'border:1px solid #eef2f6;color:#b3c0cf;cursor:default;'),
          restockTrack: 'width:34px;height:19px;border-radius:99px;position:relative;flex:none;background:' + (rs ? 'var(--c-primary)' : '#c2cedb') + (on ? '' : ';opacity:.45'),
          restockKnob: 'position:absolute;top:2px;width:15px;height:15px;border-radius:50%;background:#ffffff;transition:left .14s ease;left:' + (rs ? '17px' : '2px'),
          refundStyle: 'font-family:\'IBM Plex Mono\',monospace;font-size:13px;text-align:right;font-weight:600;' + (on ? '' : 'color:#c2cedb;')
        };
      }),
      srSub: this.money(srSub),
      srTax: this.money(srTax),
      srTotal: this.money(srTotal),
      srSettle: SR_SETTLE.map((s, i) => {
        const on = S.srSettle === i;
        return {
          name: s.name, desc: s.desc,
          onClick: () => this.setState({ srSettle: i }),
          style: 'display:flex;align-items:flex-start;gap:11px;padding:10px 12px;border-radius:9px;cursor:pointer;'
            + (on ? 'border:1px solid var(--c-primary);background:var(--c-primary-tint);' : 'border:1px solid #dfe6ee;'),
          radio: 'width:17px;height:17px;border-radius:50%;display:grid;place-items:center;flex:none;margin-top:1px;border:1.5px solid ' + (on ? 'var(--c-primary);' : '#c2cedb;'),
          inner: 'width:8px;height:8px;border-radius:50%;background:' + (on ? 'var(--c-primary)' : 'transparent')
        };
      }),
      srHint: S.srSettle === 0 ? 'Balance due on 2026-5009 falls to ' + this.money(Math.max(0, 360.45 - srTotal)) + '.'
        : S.srSettle === 1 ? 'Original payment was cash, so the refund comes out of Register 1.'
        : 'Al Amin Catering will see ' + this.money(srTotal) + ' credit at their next checkout.',
      srImpact: has
        ? srUnits + ' units across ' + srCount + (srCount === 1 ? ' line' : ' lines') + '. ' + restocked + ' back into Main branch stock, ' + written + ' written off as waste. A credit note CN-0036 is issued and its journal drafted for review.'
        : 'Nothing selected yet. Raise a quantity on at least one line.',
      srImpactBox: 'border-radius:12px;padding:14px 16px;'
        + (has ? 'background:#fdf1dd;border:1px solid #f0d9ac;color:#8a5300;' : 'background:#f6f9fc;border:1px solid #dfe6ee;color:#7d8b9c;'),
      srSubmitLabel: has ? 'Issue credit note \u00b7 ' + this.money(srTotal) : 'Issue credit note',
      srSubmitStyle: 'height:34px;padding:0 15px;border-radius:8px;display:flex;align-items:center;font-size:12.5px;font-weight:600;'
        + (has ? 'background:var(--c-primary);color:#ffffff;cursor:pointer;' : 'background:#b7c6d6;color:#ffffff;cursor:not-allowed;pointer-events:none;'),
      sellNotes: SELL_NOTES
    };
  }

  purchaseVals() {
    const S = this.state;
    const rows = PU_ROWS.filter(r => S.pustatus === 'All'
      || (S.pustatus === 'Ordered' && r.status === 'ordered')
      || (S.pustatus === 'Received' && r.status === 'received')
      || (S.pustatus === 'Unpaid' && r.due > 0));
    const lineTot = l => l.cost * l.qty * (1 - l.disc / 100);
    const sub = S.puLines.reduce((a, l) => a + lineTot(l), 0);
    const orderDisc = 0;
    const freight = 120;
    const tax = (sub - orderDisc + freight) * 0.10;
    const grand = sub - orderDisc + freight + tax;
    const paid = S.puPay === 'Paid in full' ? grand : S.puPay === 'Part paid' ? Math.round(grand * 0.5) : 0;
    const bal = grand - paid;
    const counts = {
      All: PU_ROWS.length,
      Ordered: PU_ROWS.filter(r => r.status === 'ordered').length,
      Received: PU_ROWS.filter(r => r.status === 'received').length,
      Unpaid: PU_ROWS.filter(r => r.due > 0).length
    };
    const doneIdx = PU_STEPS.length;
    return {
      isPurchases: S.view === 'purchases',
      goPurchases: () => this.setState({ view:'purchases' }),
      tabPurchases: this.tab('purchases'),
      puSubTabs: this.subTabsFor([['list','Purchase list'],['form','New purchase'],['details','Purchase details'],['returns','Returns'],['return','Return form']], S.pusub, 'pusub'),
      puList: S.pusub === 'list', puForm: S.pusub === 'form', puDetails: S.pusub === 'details',
      puReturns: S.pusub === 'returns', puReturn: S.pusub === 'return',
      puGoList: () => this.setState({ pusub:'list' }),
      puGoForm: () => this.setState({ pusub:'form' }),
      puGoDetails: () => this.setState({ pusub:'details' }),
      puGoReturns: () => this.setState({ pusub:'returns' }),
      puGoReturn: () => this.setState({ pusub:'return' }),
      purchaseNav: this.navFor('Purchases', [
        {name:'Purchases', k:'list', matches:['list','form','details']},
        {name:'Purchase returns', k:'returns', matches:['returns','return']}
      ], S.pusub, 'pusub'),
      ...this.returnVals(),

      puStatusTabs: ['All','Ordered','Received','Unpaid'].map(t => {
        const on = S.pustatus === t;
        return {
          name: t, count: counts[t],
          onClick: () => this.setState({ pustatus: t }),
          style: this.pageTab(on),
          countStyle: 'font-family:\'IBM Plex Mono\',monospace;font-size:10.5px;border-radius:4px;padding:1px 5px;margin-left:6px;'
            + (on ? 'background:var(--c-primary-soft);color:var(--c-primary-text);' : 'background:#eef2f6;color:#7d8b9c;')
        };
      }),
      puRows: rows.map(r => ({
        ref: r.ref, date: r.date, supplier: r.supplier, lines: r.lines, loc: r.loc,
        status: PU_STATUS[r.status][0], statusBadge: this.badge(TONE[PU_STATUS[r.status][1]]),
        pay: PU_PAY[r.pay][0], payBadge: this.badge(TONE[PU_PAY[r.pay][1]]),
        total: this.money(r.total),
        due: r.due ? this.money(r.due) : '—',
        dueStyle: 'font-family:\'IBM Plex Mono\',monospace;font-size:12.5px;text-align:right;' + (r.due ? 'color:#b02020;font-weight:600;' : 'color:#9aa7b6;')
      })),
      puCount: rows.length + ' of ' + PU_ROWS.length,
      puTotal: this.money(rows.reduce((a, r) => a + r.total, 0)),
      puDue: this.money(rows.reduce((a, r) => a + r.due, 0)),

      puSuggest: PU_SUGGEST.filter(s => !S.puLines.some(l => l.name === s.name)).map(s => ({
        name: s.name, note: s.note, cost: this.money(s.cost),
        onClick: () => this.setState(st => ({
          puLines: st.puLines.concat([{ id: Date.now(), name: s.name, cost: s.cost, qty: 10, disc: 0, last: s.cost, suggested: true }])
        }))
      })),
      puLines: S.puLines.map((l, i) => ({
        n: i + 1, name: l.name, qty: l.qty,
        cost: this.money(l.cost),
        disc: l.disc ? l.disc + '%' : '0',
        total: this.money(lineTot(l)),
        hint: l.suggested ? 'added from reorder suggestions' : l.cost !== l.last ? 'last paid ' + this.money(l.last) : 'same as last cost',
        hintStyle: 'font-size:10.5px;' + (l.cost !== l.last ? 'color:#b45309;' : 'color:#9aa7b6;'),
        inc: () => this.bumpLine(l.id, 5),
        dec: () => this.bumpLine(l.id, -5),
        rm: () => this.setState(st => ({ puLines: st.puLines.filter(x => x.id !== l.id) }))
      })),
      puLineCount: S.puLines.length,
      puQty: S.puLines.reduce((a, l) => a + l.qty, 0),
      puSub: this.money(sub),
      puOrderDisc: this.money(orderDisc),
      puFreight: this.money(freight),
      puTax: this.money(tax),
      puGrand: this.money(grand),
      puPayChoices: ['Unpaid','Part paid','Paid in full'].map(p => ({
        name: p,
        onClick: () => this.setState({ puPay: p }),
        style: 'flex:1;height:32px;border-radius:8px;display:grid;place-items:center;font-size:12px;cursor:pointer;white-space:nowrap;'
          + (S.puPay === p ? 'background:var(--c-primary);color:#ffffff;font-weight:600;' : 'border:1px solid #dfe6ee;color:#4a5866;font-weight:500;')
      })),
      puShowPayFields: S.puPay !== 'Unpaid',
      puPaid: this.money(paid),
      puBalance: this.money(bal),
      puBalanceStyle: 'font-family:\'IBM Plex Mono\',monospace;font-size:16px;font-weight:700;' + (bal > 0.005 ? 'color:#b02020;' : 'color:#0f6b43;'),
      puDueHint: bal > 0.005 ? 'Due 29/10/2026 on Baraka Meats\u2019 30-day terms' : 'Nothing owing after save',

      puSteps: PU_STEPS.map((s, i) => ({
        name: s.name, when: s.when,
        mark: s.state === 'done' ? '\u2713' : String(i + 1),
        dot: 'width:24px;height:24px;border-radius:50%;display:grid;place-items:center;font-size:11px;font-weight:700;flex:none;'
          + (s.state === 'done' ? 'background:#2f9e63;color:#ffffff;' : 'background:#ffffff;color:#b45309;box-shadow:inset 0 0 0 2px #d98c1a;'),
        bar: 'flex:1;height:2px;margin:0 12px;border-radius:2px;min-width:16px;'
          + (i === doneIdx - 1 ? 'display:none;' : (PU_STEPS[i + 1] && PU_STEPS[i + 1].state === 'done' ? 'background:#2f9e63;' : 'background:#e4eaf1;'))
      })),
      puDetailLines: PU_DETAIL_LINES,
      puJournal: PU_JOURNAL,
      puPayments: PU_PAYMENTS,
      purchaseNotes: PURCHASE_NOTES
    };
  }

  _mods = {};
  mod(key, Cls) { return this._mods[key] || (this._mods[key] = new Cls(this, key)); }
  pfx(p, o) { const r = {}; for (const k in o) r[p + '_' + k] = o[k]; return r; }

  renderVals() {
    const R = this._rv();
    // Sidebar frame styles live in AppSidebar; the collapsed flag still shapes the nav items below.
    const col = navCollapsed.value;
    const VIEW = { 'Home':'home', 'User Management':'users', 'Contacts':'contacts', 'Products':'products', 'Manufacturing':'mfg', 'Purchases':'purchases', 'Sell':'sell', 'Stock Transfers':'transfers', 'Stock Adjustment':'adjust', 'Expenses':'expenses', 'Payment Accounts':'accounts', 'Accounting':'accounting', 'Reports':'reports', 'Bookings':'bookings', 'Kitchen':'kitchen', 'Orders':'orders', 'Settings':'settings', 'HRM':'hrm' };
    const SUBS = { sell: R.sSubTabs, purchases: R.puSubTabs, products: R.pSubTabs, users: R.subTabs, contacts: R.cSubTabs, accounting: R.ac_subTabs, hrm: R.hr_tabs };
    const HAS_SUB = { users:1, contacts:1, products:1, purchases:1, sell:1, expenses:1, accounting:1, hrm:1 };
    const v = this.state.view;
    const childStyle = on => 'padding:6px 8px;border-radius:6px;font-size:12.5px;cursor:pointer;' + (on ? 'color:#ffffff;font-weight:600;background:rgba(255,255,255,.08);' : 'color:#9dbdd2;');
    Object.keys(R).forEach(k => {
      const a = R[k];
      if (!Array.isArray(a) || !a.length || !a[0] || a[0].caretStyle === undefined || a[0].iconStyle === undefined) return;
      R[k] = a.map(n => {
        const target = VIEW[n.name];
        const active = target === v || (target === 'sell' && v === 'pos');
        const out = { ...n, onClick: () => { if (target) this.setState({ view: target }); } };
        if (active && (!n.children || !n.children.length) && SUBS[target] && SUBS[target].length) {
          out.open = true;
          out.children = SUBS[target].map(c => ({ name: c.name, onClick: c.onClick, style: childStyle(/background:#0f1c2b|background:var\(--c-primary\)|solid var\(--c-primary\)/.test(c.style || '')) }));
        }
        if (out.open === undefined) out.open = false;
        out.nameStyle = 'flex:1;white-space:nowrap;overflow:hidden;text-overflow:ellipsis';
        out.caretStyle = 'color:#7fa3bb;font-size:11px;flex:none;transition:transform .15s ease;' + (HAS_SUB[target] ? '' : 'visibility:hidden;') + (out.open && HAS_SUB[target] ? 'transform:rotate(90deg);' : '');
        if (col) { out.open = false; out.nameStyle = 'display:none'; out.caretStyle = 'display:none'; out.style = (n.style || '') + 'justify-content:center;padding:8px 0;'; }
        if (!out.children) out.children = [];
        return out;
      });
    });
    return R;
  }

  _rv() {
    const S = this.state;
    const sub = S.cart.reduce((a, c) => a + c.price * c.qty, 0);
    const disc = sub * 0.05;
    const tax = (sub - disc) * 0.10;

    const blurb = S.view === 'home'
      ? 'Standard shell, dashboard content. Every other list and form module reuses this frame.'
      : S.view === 'pos'
      ? 'Redesigned terminal. Everything works: weigh, hold, tables, discounts, customers and all four tenders.'
      : S.view === 'kitchen'
      ? 'Live kitchen display: running timers vs target, tap items to tick off, station filter, rush, recall and undo, all-day counts. New test orders arrive every 75 seconds.'
      : S.view === 'users'
      ? 'Five screens on the standard shell. The permission grid is live — toggle cells or column headers.'
      : S.view === 'contacts'
      ? 'Four screens, one conditional form. Customer / Supplier / Both changes which terms appear.'
      : S.view === 'products'
      ? 'Three screens plus the form. Category chips, stock tabs and table/card view all filter live.'
      : S.view === 'purchases'
      ? 'List, the shared line-item form, details and returns. Lines, payment and reorder suggestions are live.'
      : S.view === 'hrm'
      ? 'Employees with one add/edit form and profile page, attendance from POS clock-ins, leave approvals with balances, and a fortnightly pay run.'
      : S.view === 'settings'
      ? '12 grouped settings pages on one field system: search across all, inline tables, validation, unsaved-changes bar, live invoice preview.'
      : S.view === 'orders'
      ? 'One queue for dine-in, takeaway, delivery and online. Status flow linked to Kitchen, new-order form shared with POS, payment and refunds.'
      : S.view === 'bookings'
      ? 'Day table plan and list, one new/edit drawer with live table availability, booking details with status flow and deposits.'
      : S.view === 'reports'
      ? 'One report layout for 11 reports: date range, location and contact filters, period comparison, chart, sortable table with totals.'
      : S.view === 'accounting'
      ? 'Chart of accounts with ledgers, journals (auto from modules plus manual), live P&L, balance sheet and trial balance, and period close.'
      : S.view === 'accounts'
      ? 'Account cards, a ledger per account with running balance, one add/edit drawer, and a transfer dialog. Balances update live.'
      : S.view === 'expenses'
      ? 'List with category breakdown, one add/edit form, and details with receipt, journal and mark-as-paid. Account balances update live.'
      : S.view === 'adjust'
      ? 'List, one form with three modes (decrease, increase, stock count) and details with reversal. Red is always a decrease, green an increase.'
      : S.view === 'transfers'
      ? 'List, the shared line-item form and details with receiving. Send deducts stock, receive adds it, shortfalls are written off.'
      : 'Sales list, invoice details and sell return. The POS Pay button now opens the payment step.';

    const cShown = CONTACTS.filter(c => {
      const typeOk = S.ctypeFilter === 'All'
        || (S.ctypeFilter === 'Both' && c.type === 'Both')
        || c.type === S.ctypeFilter.slice(0, -1);
      return typeOk && (!S.dueOnly || c.bal !== 0);
    });

    const pShown = P_ROWS.filter(p => {
      const catOk = S.pcat === 'All' || p.cat === S.pcat;
      const stockOk = S.pstock === 'All'
        || (S.pstock === 'In stock' && p.stock > p.alert)
        || (S.pstock === 'Low stock' && p.stock > 0 && p.stock <= p.alert)
        || (S.pstock === 'Out of stock' && p.stock === 0);
      return catOk && stockOk;
    });

    const g = this.grants();
    const granted = PERM_MODULES.reduce((a, m) => a + g[m[0]].reduce((x, y) => x + y, 0), 0);
    const modulesTouched = PERM_MODULES.filter(m => g[m[0]].some(v => v)).length;

    const filtered = USERS.filter(u =>
      S.statusFilter === 'All'
        || (S.statusFilter === 'Active' && u.status === 'active')
        || (S.statusFilter === 'Invited' && u.status === 'invited')
        || (S.statusFilter === 'Deactivated' && u.status === 'off'));

    const statusCounts = {
      All: USERS.length,
      Active: USERS.filter(u => u.status === 'active').length,
      Invited: USERS.filter(u => u.status === 'invited').length,
      Deactivated: USERS.filter(u => u.status === 'off').length
    };

    return {
      ...(S.view === 'transfers' ? this.pfx('tr', this.mod('tr', TransfersLogic).renderVals()) : {}),
      ...(S.view === 'adjust' ? this.pfx('sa', this.mod('sa', AdjustLogic).renderVals()) : {}),
      ...(S.view === 'expenses' ? this.pfx('ex', this.mod('ex', ExpensesLogic).renderVals()) : {}),
      ...(S.view === 'accounts' ? this.pfx('pa', this.mod('pa', AccountsLogic).renderVals()) : {}),
      ...(S.view === 'accounting' ? this.pfx('ac', this.mod('ac', AccountingLogic).renderVals()) : {}),
      ...(S.view === 'reports' ? this.pfx('rp', this.mod('rp', ReportsLogic).renderVals()) : {}),
      ...(S.view === 'bookings' ? this.pfx('bk', this.mod('bk', BookingsLogic).renderVals()) : {}),
      ...(S.view === 'kitchen' ? this.pfx('kt', this.mod('kt', KitchenLogic).renderVals()) : {}),
      ...(S.view === 'orders' ? this.pfx('od', this.mod('od', OrdersLogic).renderVals()) : {}),
      ...(S.view === 'settings' ? this.pfx('se', this.mod('se', SettingsLogic).renderVals()) : {}),
      ...(S.view === 'hrm' ? this.pfx('hr', this.mod('hr', HrmLogic).renderVals()) : {}),
      ...(S.view === 'mfg' ? this.pfx('mf', this.mod('mf', MfgLogic).renderVals()) : {}),
      isMfg: S.view === 'mfg',
      isHrm: S.view === 'hrm', goHrm: () => this.setState({ view:'hrm' }), tabHrm: this.tab('hrm'),
      isSettings: S.view === 'settings', goSettings: () => this.setState({ view:'settings' }), tabSettings: this.tab('settings'),
      isOrders: S.view === 'orders', goOrders: () => this.setState({ view:'orders' }), tabOrders: this.tab('orders'),
      isBookings: S.view === 'bookings', goBookings: () => this.setState({ view:'bookings' }), tabBookings: this.tab('bookings'),
      isReports: S.view === 'reports', goReports: () => this.setState({ view:'reports' }), tabReports: this.tab('reports'),
      isHome: S.view === 'home', isPos: S.view === 'pos', isKitchen: S.view === 'kitchen', isTransfers: S.view === 'transfers', isAdjust: S.view === 'adjust', isExpenses: S.view === 'expenses', isAccounts: S.view === 'accounts', isAccounting: S.view === 'accounting',
      goAccounting: () => this.setState({ view:'accounting' }), tabAccounting: this.tab('accounting'),
      goAccounts: () => this.setState({ view:'accounts' }), tabAccounts: this.tab('accounts'),
      goExpenses: () => this.setState({ view:'expenses' }), tabExpenses: this.tab('expenses'),
      goAdjust: () => this.setState({ view:'adjust' }), tabAdjust: this.tab('adjust'),
      goTransfers: () => this.setState({ view:'transfers' }), tabTransfers: this.tab('transfers'),
      isUsers: S.view === 'users',
      goHome: () => this.setState({ view:'home' }),
      goPos: () => this.setState({ view:'pos' }),
      goKitchen: () => this.setState({ view:'kitchen' }),
      goUsers: () => this.setState({ view:'users' }),
      tabHome: this.tab('home'), tabPos: this.tab('pos'), tabKitchen: this.tab('kitchen'),
      tabUsers: this.tab('users'),
      blurb,

      subTabs: [
        ['list','User list'], ['details','User details'], ['roles','Roles'], ['matrix','Permissions']
      ].map(s => ({
        name: s[1],
        onClick: () => this.setState({ sub: s[0] }),
        style: this.subTab(s[0])
      })),

      uList: S.sub === 'list', uDetails: S.sub === 'details',
      uRoles: S.sub === 'roles', uMatrix: S.sub === 'matrix',
      uGoList: () => this.setState({ sub:'list' }),
      uGoDetails: () => this.setState({ sub:'details' }),
      uGoRoles: () => this.setState({ sub:'roles' }),
      uGoMatrix: () => this.setState({ sub:'matrix' }),

      userNav: NAV.map(n => {
        const on = n[0] === 'User Management';
        return {
          name: n[0], key: n[1],
          open: on,
          children: on ? [
            {name:'Users', k:'list'}, {name:'Roles & permissions', k:'roles'}
          ].map(c => ({
            name: c.name,
            onClick: () => this.setState({ sub: c.k }),
            style: 'padding:6px 8px;border-radius:6px;font-size:12.5px;cursor:pointer;'
              + ((c.k === 'list' ? (S.sub === 'list' || S.sub === 'details') : (S.sub === 'roles' || S.sub === 'matrix'))
                ? 'color:#ffffff;font-weight:600;background:rgba(255,255,255,.08);'
                : 'color:#9dbdd2;')
          })) : [],
          style: 'display:flex;align-items:center;gap:10px;padding:8px 10px;border-radius:8px;cursor:pointer;font-size:13px;font-weight:500;margin-bottom:1px;'
            + (on ? 'background:rgba(255,255,255,.13);color:#ffffff;box-shadow:inset 2px 0 0 var(--c-accent);' : 'color:#c4dced;'),
          iconStyle: 'width:20px;height:20px;border-radius:5px;display:grid;place-items:center;font-family:\'IBM Plex Mono\',monospace;font-size:9px;font-weight:600;flex:none;'
            + (on ? 'background:var(--c-accent);color:#06283a;' : 'background:rgba(255,255,255,.1);color:#9dbdd2;'),
          caretStyle: 'color:#7fa3bb;font-size:11px;flex:none;' + (n[2] ? '' : 'visibility:hidden;')
        };
      }),

      statusTabs: ['All','Active','Invited','Deactivated'].map(s => ({
        name: s,
        count: statusCounts[s],
        onClick: () => this.setState({ statusFilter: s }),
        style: this.pageTab(S.statusFilter === s),
        countStyle: 'font-family:\'IBM Plex Mono\',monospace;font-size:10.5px;border-radius:4px;padding:1px 5px;margin-left:6px;'
          + (S.statusFilter === s ? 'background:var(--c-primary-soft);color:var(--c-primary-text);' : 'background:#eef2f6;color:#7d8b9c;')
      })),

      users: filtered.map(u => {
        const t = u.status === 'active' ? TONE.success : u.status === 'invited' ? TONE.warn : TONE.neutral;
        const owner = u.role === 'Owner';
        return {
          name: u.name, email: u.email, role: u.role, locations: u.locations, lastActive: u.lastActive,
          initials: u.name.split(' ').map(x => x[0]).join(''),
          status: u.status === 'active' ? 'Active' : u.status === 'invited' ? 'Invited' : 'Deactivated',
          avatar: 'width:32px;height:32px;border-radius:8px;display:grid;place-items:center;font-size:11.5px;font-weight:600;flex:none;'
            + (u.status === 'off' ? 'background:#eef2f6;color:#9aa7b6;' : 'background:var(--c-sidebar);color:#ffffff;'),
          roleBadge: 'border-radius:5px;padding:3px 8px;font-size:11.5px;font-weight:600;'
            + (owner ? 'background:#0f1c2b;color:#ffffff;' : 'background:var(--c-primary-soft);color:var(--c-primary-text);'),
          statusBadge: 'background:' + t[0] + ';color:' + t[1] + ';border-radius:5px;padding:3px 8px;font-size:11.5px;font-weight:600'
        };
      }),
      userCount: filtered.length,

      detailTabs: [['activity','Activity'],['perms','Permissions'],['sessions','Sessions']].map(t => ({
        name: t[1],
        onClick: () => this.setState({ dtab: t[0] }),
        style: this.pageTab(S.dtab === t[0])
      })),
      dtActivity: S.dtab === 'activity', dtPerms: S.dtab === 'perms', dtSessions: S.dtab === 'sessions',

      activity: ACTIVITY.map(a => ({
        when: a.when, what: a.what, meta: a.meta,
        dot: 'width:8px;height:8px;border-radius:50%;flex:none;margin-top:5px;background:' + TONE[a.tone][2]
      })),

      effective: EFFECTIVE.map(e => ({
        key: e.key, name: e.name, actions: e.actions, src: e.src,
        srcStyle: 'border-radius:5px;padding:2px 8px;font-size:11px;font-weight:600;'
          + (e.src === 'Override' ? 'background:#fdf1dd;color:#8a5300;' : 'background:#eef2f6;color:#5d6b7d;')
      })),

      sessions: SESSIONS.map(s => {
        const t = s.state === 'Current' ? TONE.success : s.state === 'Idle' ? TONE.info : TONE.neutral;
        return {
          device: s.device, agent: s.agent, where: s.where, when: s.when, state: s.state,
          badge: 'background:' + t[0] + ';color:' + t[1] + ';border-radius:5px;padding:2px 8px;font-size:11px;font-weight:600'
        };
      }),

      meta: META.map(m => ({
        k: m.k, v: m.v,
        vStyle: 'font-size:12.5px;font-weight:500;' + (m.mono ? 'font-family:\'IBM Plex Mono\',monospace;font-size:12px;' : '')
      })),

      userStats: USER_STATS.map(s => ({
        k: s.k, v: s.v,
        vStyle: 'font-family:\'IBM Plex Mono\',monospace;font-size:14px;font-weight:600;' + (s.warn ? 'color:#b45309;' : '')
      })),

      roles: ROLES.map(r => ({
        name: r.name, desc: r.desc, kind: r.kind,
        userLabel: r.users === 1 ? '1 user' : r.users + ' users',
        permLabel: r.perms,
        kindStyle: 'border-radius:5px;padding:2px 7px;font-size:10.5px;font-weight:600;'
          + (r.kind === 'Custom' ? 'background:#fdf1dd;color:#8a5300;' : 'background:#eef2f6;color:#5d6b7d;')
      })),

      presetName: S.grants ? S.preset + ' (modified)' : S.preset,
      grantSummary: granted + ' of 90 grants across ' + modulesTouched + ' modules',
      // Roles loaded from the API carry their grants; until then the built-in presets show.
      presets: (ROLES.some(r => r.grants) ? ROLES.map(r => r.name) : Object.keys(PRESETS)).map(p => ({
        name: p,
        onClick: () => this.setState({ preset: p, grants: null }),
        style: 'height:30px;padding:0 12px;border-radius:7px;display:flex;align-items:center;font-size:12px;font-weight:500;cursor:pointer;'
          + (S.preset === p && !S.grants
            ? 'background:var(--c-primary);color:#ffffff;font-weight:600;'
            : 'border:1px solid #dfe6ee;color:#4a5866;')
      })),
      cols: PERM_COLS.map((c, i) => ({ name: c, onClick: () => this.toggleCol(i) })),
      grid: PERM_MODULES.map(m => ({
        key: m[0], name: m[1],
        extra: m[2],
        extraStyle: 'font-family:\'IBM Plex Mono\',monospace;font-size:9.5px;color:#b45309;background:#fdf1dd;border-radius:4px;padding:2px 6px;flex:none;white-space:nowrap;'
          + (m[2] ? '' : 'display:none;'),
        cells: g[m[0]].map((v, i) => ({
          mark: v ? '\u2713' : '',
          onClick: () => this.toggleCell(m[0], i),
          style: 'width:20px;height:20px;border-radius:5px;display:grid;place-items:center;font-size:11px;font-weight:700;'
            + (v ? 'background:var(--c-primary);color:#ffffff;' : 'border:1.5px solid #dfe6ee;color:transparent;')
        }))
      })),
      grantAll: () => this.fillAll(1),
      revokeAll: () => this.fillAll(0),

      userFormOpen: S.formOpen,
      openUserForm: () => this.setState({ formOpen: true }),
      closeUserForm: () => this.setState({ formOpen: false }),
      roleChoices: ROLE_CHOICES.map(r => {
        const on = S.formRole === r.name;
        return {
          name: r.name, desc: r.desc,
          onClick: () => this.setState({ formRole: r.name }),
          style: 'display:flex;align-items:flex-start;gap:11px;padding:11px 13px;border-radius:9px;cursor:pointer;'
            + (on ? 'border:1px solid var(--c-primary);background:var(--c-primary-tint);' : 'border:1px solid #dfe6ee;'),
          radio: 'width:17px;height:17px;border-radius:50%;display:grid;place-items:center;flex:none;margin-top:1px;border:1.5px solid '
            + (on ? 'var(--c-primary);' : '#c2cedb;'),
          inner: 'width:8px;height:8px;border-radius:50%;background:' + (on ? 'var(--c-primary)' : 'transparent')
        };
      }),
      toggles: USER_TOGGLES.map((t, i) => {
        const on = S.toggles[i];
        return {
          name: t.name, desc: t.desc,
          onClick: () => this.setState(st => {
            const next = st.toggles.slice();
            next[i] = !next[i];
            return { toggles: next };
          }),
          track: 'width:36px;height:20px;border-radius:99px;position:relative;flex:none;background:' + (on ? 'var(--c-primary)' : '#c2cedb'),
          knob: 'position:absolute;top:2px;width:16px;height:16px;border-radius:50%;background:#ffffff;transition:left .14s ease;left:' + (on ? '18px' : '2px')
        };
      }),
      userNotes: USER_NOTES,

      isContacts: S.view === 'contacts',
      goContacts: () => this.setState({ view:'contacts' }),
      tabContacts: this.tab('contacts'),

      cSubTabs: [['list','Contact list'],['details','Contact details'],['groups','Customer groups']].map(s => ({
        name: s[1],
        onClick: () => this.setState({ csub: s[0] }),
        style: 'padding:6px 11px;border-radius:7px;font-size:12px;font-weight:600;cursor:pointer;white-space:nowrap;'
          + (S.csub === s[0] ? 'background:#0f1c2b;color:#ffffff;' : 'color:#5d6b7d;border:1px solid #dfe6ee;')
      })),
      cList: S.csub === 'list', cDetails: S.csub === 'details', cGroups: S.csub === 'groups',
      cGoList: () => this.setState({ csub:'list' }),
      cGoDetails: () => this.setState({ csub:'details' }),

      contactNav: NAV.map(n => {
        const on = n[0] === 'Contacts';
        return {
          name: n[0], key: n[1],
          open: on,
          children: on ? [
            {name:'All contacts', k:'list'}, {name:'Customer groups', k:'groups'}
          ].map(c => ({
            name: c.name,
            onClick: () => this.setState({ csub: c.k }),
            style: 'padding:6px 8px;border-radius:6px;font-size:12.5px;cursor:pointer;'
              + ((c.k === 'list' ? (S.csub === 'list' || S.csub === 'details') : S.csub === 'groups')
                ? 'color:#ffffff;font-weight:600;background:rgba(255,255,255,.08);'
                : 'color:#9dbdd2;')
          })) : [],
          style: 'display:flex;align-items:center;gap:10px;padding:8px 10px;border-radius:8px;cursor:pointer;font-size:13px;font-weight:500;margin-bottom:1px;'
            + (on ? 'background:rgba(255,255,255,.13);color:#ffffff;box-shadow:inset 2px 0 0 var(--c-accent);' : 'color:#c4dced;'),
          iconStyle: 'width:20px;height:20px;border-radius:5px;display:grid;place-items:center;font-family:\'IBM Plex Mono\',monospace;font-size:9px;font-weight:600;flex:none;'
            + (on ? 'background:var(--c-accent);color:#06283a;' : 'background:rgba(255,255,255,.1);color:#9dbdd2;'),
          caretStyle: 'color:#7fa3bb;font-size:11px;flex:none;' + (n[2] ? '' : 'visibility:hidden;')
        };
      }),

      typeTabs: ['All','Customers','Suppliers','Both'].map(t => {
        const n = t === 'All' ? CONTACTS.length
          : t === 'Both' ? CONTACTS.filter(c => c.type === 'Both').length
          : CONTACTS.filter(c => c.type === t.slice(0, -1)).length;
        const on = S.ctypeFilter === t;
        return {
          name: t, count: n,
          onClick: () => this.setState({ ctypeFilter: t }),
          style: this.pageTab(on),
          countStyle: 'font-family:\'IBM Plex Mono\',monospace;font-size:10.5px;border-radius:4px;padding:1px 5px;margin-left:6px;'
            + (on ? 'background:var(--c-primary-soft);color:var(--c-primary-text);' : 'background:#eef2f6;color:#7d8b9c;')
        };
      }),

      contactKpis: CONTACT_KPIS.map(k => ({
        label: k.label, value: k.value, sub: k.sub,
        valueStyle: 'font-family:\'IBM Plex Mono\',monospace;font-size:22px;font-weight:600;letter-spacing:-0.025em;line-height:1;'
          + (k.tone === 'error' ? 'color:#b02020;' : k.tone === 'warn' ? 'color:#8a5300;' : '')
      })),

      contacts: cShown.map(c => {
        const t = c.type === 'Supplier' ? TONE.warn : c.type === 'Both' ? TONE.info : TONE.success;
        const owed = c.bal > 0;
        const advance = c.bal < 0;
        return {
          name: c.name, sub: c.sub, type: c.type, group: c.group, business: c.business, last: c.last,
          initials: c.name.split(' ').map(x => x[0]).join('').slice(0, 2).toUpperCase(),
          avatar: 'width:32px;height:32px;border-radius:8px;display:grid;place-items:center;font-size:11.5px;font-weight:600;flex:none;background:var(--c-sidebar);color:#ffffff;',
          typeBadge: 'background:' + t[0] + ';color:' + t[1] + ';border-radius:5px;padding:3px 8px;font-size:11.5px;font-weight:600',
          balance: c.bal === 0 ? '—' : this.money(Math.abs(c.bal)),
          balStyle: 'font-family:\'IBM Plex Mono\',monospace;font-size:12.5px;font-weight:600;border-radius:5px;padding:3px 7px;'
            + (owed ? 'background:#fdeaea;color:#b02020;'
              : advance ? 'background:#fdf1dd;color:#8a5300;'
              : 'color:#9aa7b6;')
        };
      }),
      contactCount: cShown.length,
      contactSubtitle: '9 contacts · 5 customers, 3 suppliers, 1 both',

      dueOnly: S.dueOnly,
      toggleDueOnly: () => this.setState(st => ({ dueOnly: !st.dueOnly })),
      dueOnlyStyle: 'height:34px;padding:0 12px;border-radius:8px;display:flex;align-items:center;gap:8px;font-size:12.5px;font-weight:500;cursor:pointer;'
        + (S.dueOnly ? 'border:1px solid var(--c-primary);background:var(--c-primary-soft);color:var(--c-primary-text);font-weight:600;' : 'border:1px solid #dfe6ee;background:#ffffff;color:#4a5866;'),

      cDetailTabs: [['tx','Transactions'],['ledger','Ledger'],['docs','Documents']].map(t => ({
        name: t[1],
        onClick: () => this.setState({ cdtab: t[0] }),
        style: this.pageTab(S.cdtab === t[0])
      })),
      cdTx: S.cdtab === 'tx', cdLedger: S.cdtab === 'ledger', cdDocs: S.cdtab === 'docs',

      cTx: C_TX.map(t => {
        const tn = t.status === 'paid' ? TONE.success : t.status === 'due' ? TONE.error : TONE.warn;
        return {
          ref: t.ref, date: t.date, kind: t.kind, total: t.total, due: t.due,
          status: t.status === 'paid' ? 'Paid' : t.status === 'due' ? 'Due' : 'Partial',
          dueStyle: 'font-family:\'IBM Plex Mono\',monospace;font-size:12.5px;text-align:right;'
            + (t.due === '0.00' ? 'color:#9aa7b6;' : 'color:#b02020;font-weight:600;'),
          badge: 'background:' + tn[0] + ';color:' + tn[1] + ';border-radius:5px;padding:2px 8px;font-size:11px;font-weight:600'
        };
      }),
      cLedger: C_LEDGER,
      cAddresses: C_ADDRESSES.map(a => ({
        kind: a.kind, lines: a.lines, tag: a.tag,
        tagStyle: 'font-family:\'IBM Plex Mono\',monospace;font-size:9.5px;letter-spacing:0.06em;text-transform:uppercase;background:#eef2f6;color:#7d8b9c;border-radius:4px;padding:2px 6px'
      })),
      cDocs: C_DOCS,
      cMeta: C_META.map(m => ({
        k: m.k, v: m.v,
        vStyle: 'font-size:12.5px;font-weight:500;' + (m.mono ? 'font-family:\'IBM Plex Mono\',monospace;font-size:12px;' : '')
      })),
      cStats: C_STATS,

      groups: GROUPS.map(g => ({
        name: g.name, desc: g.desc, price: g.price, discount: g.discount, terms: g.terms,
        count: g.count,
        bal: g.bal === 0 ? '—' : this.money(g.bal),
        balStyle: 'font-family:\'IBM Plex Mono\',monospace;font-size:12.5px;font-weight:600;border-radius:5px;padding:3px 7px;'
          + (g.bal > 0 ? 'background:#fdeaea;color:#b02020;' : 'color:#9aa7b6;')
      })),

      contactFormOpen: S.cFormOpen,
      openContactForm: () => this.setState({ cFormOpen: true }),
      closeContactForm: () => this.setState({ cFormOpen: false }),
      ctypeChoices: CTYPES.map(c => ({
        name: c.name,
        onClick: () => this.setState({ ctype: c.name }),
        style: 'flex:1;height:38px;border-radius:9px;display:grid;place-items:center;font-size:13px;cursor:pointer;'
          + (S.ctype === c.name
            ? 'background:var(--c-primary);color:#ffffff;font-weight:700;'
            : 'border:1px solid #dfe6ee;color:#4a5866;font-weight:500;')
      })),
      ctypeHint: (CTYPES.find(c => c.name === S.ctype) || CTYPES[0]).hint,
      ctypeFooter: (CTYPES.find(c => c.name === S.ctype) || CTYPES[0]).footer,
      showCustomerFields: S.ctype === 'Customer' || S.ctype === 'Both',
      showSupplierFields: S.ctype === 'Supplier' || S.ctype === 'Both',

      contactNotes: CONTACT_NOTES,

      isProducts: S.view === 'products',
      goProducts: () => this.setState({ view:'products' }),
      tabProducts: this.tab('products'),

      pSubTabs: [['list','Product list'],['details','Product details'],['taxonomy','Categories & units']].map(s => ({
        name: s[1],
        onClick: () => this.setState({ psub: s[0] }),
        style: 'padding:6px 11px;border-radius:7px;font-size:12px;font-weight:600;cursor:pointer;white-space:nowrap;'
          + (S.psub === s[0] ? 'background:#0f1c2b;color:#ffffff;' : 'color:#5d6b7d;border:1px solid #dfe6ee;')
      })),
      pList: S.psub === 'list', pDetails: S.psub === 'details', pTaxonomy: S.psub === 'taxonomy', pLabels: S.psub === 'labels',
      pGoList: () => this.setState({ psub:'list' }),
      pGoDetails: () => this.setState({ psub:'details' }),

      productNav: NAV.map(n => {
        const on = n[0] === 'Products';
        return {
          name: n[0], key: n[1],
          open: on,
          children: on ? [
            {name:'All products', k:'list', matches:['list','details']},
            {name:'Categories & units', k:'taxonomy', matches:['taxonomy']},
            {name:'Print labels', k:'labels', matches:['labels']}
          ].map(c => ({
            name: c.name,
            onClick: () => this.setState({ psub: c.k }),
            style: 'padding:6px 8px;border-radius:6px;font-size:12.5px;cursor:pointer;'
              + (c.matches.indexOf(S.psub) > -1
                ? 'color:#ffffff;font-weight:600;background:rgba(255,255,255,.08);'
                : 'color:#9dbdd2;')
          })) : [],
          style: 'display:flex;align-items:center;gap:10px;padding:8px 10px;border-radius:8px;cursor:pointer;font-size:13px;font-weight:500;margin-bottom:1px;'
            + (on ? 'background:rgba(255,255,255,.13);color:#ffffff;box-shadow:inset 2px 0 0 var(--c-accent);' : 'color:#c4dced;'),
          iconStyle: 'width:20px;height:20px;border-radius:5px;display:grid;place-items:center;font-family:\'IBM Plex Mono\',monospace;font-size:9px;font-weight:600;flex:none;'
            + (on ? 'background:var(--c-accent);color:#06283a;' : 'background:rgba(255,255,255,.1);color:#9dbdd2;'),
          caretStyle: 'color:#7fa3bb;font-size:11px;flex:none;' + (n[2] ? '' : 'visibility:hidden;')
        };
      }),

      stockTabs: ['All','In stock','Low stock','Out of stock'].map(t => {
        const n = t === 'All' ? P_ROWS.length
          : t === 'In stock' ? P_ROWS.filter(p => p.stock > p.alert).length
          : t === 'Low stock' ? P_ROWS.filter(p => p.stock > 0 && p.stock <= p.alert).length
          : P_ROWS.filter(p => p.stock === 0).length;
        const on = S.pstock === t;
        return {
          name: t, count: n,
          onClick: () => this.setState({ pstock: t }),
          style: this.pageTab(on),
          countStyle: 'font-family:\'IBM Plex Mono\',monospace;font-size:10.5px;border-radius:4px;padding:1px 5px;margin-left:6px;'
            + (on ? 'background:var(--c-primary-soft);color:var(--c-primary-text);' : 'background:#eef2f6;color:#7d8b9c;')
        };
      }),

      pCatChips: P_CATS.map(c => ({
        name: c,
        onClick: () => this.setState({ pcat: c }),
        style: 'height:34px;padding:0 12px;border-radius:8px;display:flex;align-items:center;font-size:12.5px;cursor:pointer;white-space:nowrap;'
          + (S.pcat === c
            ? 'background:var(--c-primary);color:#ffffff;font-weight:600;'
            : 'border:1px solid #dfe6ee;background:#ffffff;color:#4a5866;font-weight:500;')
      })),

      pTableView: S.pview === 'table', pCardView: S.pview === 'cards',
      setTable: () => this.setState({ pview:'table' }),
      setCards: () => this.setState({ pview:'cards' }),
      tableViewStyle: 'padding:5px 11px;border-radius:6px;font-size:11.5px;cursor:pointer;'
        + (S.pview === 'table' ? 'background:#ffffff;font-weight:600;box-shadow:0 1px 2px rgba(15,28,43,.09);' : 'color:#5d6b7d;'),
      cardViewStyle: 'padding:5px 11px;border-radius:6px;font-size:11.5px;cursor:pointer;'
        + (S.pview === 'cards' ? 'background:#ffffff;font-weight:600;box-shadow:0 1px 2px rgba(15,28,43,.09);' : 'color:#5d6b7d;'),

      pRows: pShown.map(p => {
        const out = p.stock === 0;
        const low = !out && p.stock <= p.alert;
        const t = out ? TONE.error : low ? TONE.warn : TONE.success;
        const margin = p.price > 0 ? ((p.price - p.cost) / p.price) * 100 : 0;
        const thin = margin < 20;
        const unit = p.meta.indexOf('Each') === 0 ? '' : ' kg';
        return {
          name: p.name, meta: p.meta, cat: p.cat, sku: p.sku,
          cost: this.money(p.cost), price: this.money(p.price),
          margin: margin.toFixed(1) + '%',
          marginStyle: 'font-family:\'IBM Plex Mono\',monospace;font-size:12.5px;text-align:right;font-weight:500;'
            + (thin ? 'color:#b02020;' : 'color:#0f6b43;'),
          stock: out ? 'Out' : p.stock + unit,
          stockStyle: 'font-family:\'IBM Plex Mono\',monospace;font-size:12px;font-weight:600;border-radius:5px;padding:3px 7px;background:' + t[0] + ';color:' + t[1]
        };
      }),
      pCount: pShown.length,

      pDetailTabs: [['stock','Stock & movements'],['sales','Sales & cost'],['variants','Variations']].map(t => ({
        name: t[1],
        onClick: () => this.setState({ pdtab: t[0] }),
        style: this.pageTab(S.pdtab === t[0])
      })),
      pdStock: S.pdtab === 'stock', pdSales: S.pdtab === 'sales', pdVariants: S.pdtab === 'variants',

      pStock: P_STOCK.map(s => ({
        loc: s.loc, alert: s.alert, committed: s.committed, value: s.value, onHand: s.onHand,
        onHandStyle: 'font-family:\'IBM Plex Mono\',monospace;font-size:12.5px;font-weight:600;border-radius:5px;padding:3px 7px;'
          + (s.tone === 'out' ? 'background:#fdeaea;color:#b02020;' : 'background:#e7f6ee;color:#0f6b43;')
      })),

      pMoves: P_MOVES.map(m => ({
        date: m.date, kind: m.kind, ref: m.ref, bal: m.bal,
        delta: (m.delta > 0 ? '+' : '') + m.delta,
        deltaStyle: 'font-family:\'IBM Plex Mono\',monospace;font-size:12px;font-weight:600;border-radius:5px;padding:3px 7px;'
          + (m.delta > 0 ? 'background:#e7f6ee;color:#0f6b43;' : 'background:#fdeaea;color:#b02020;')
      })),

      pSalesBars: P_SALES_BARS.map((h, i) => ({
        style: 'flex:1;border-radius:3px 3px 0 0;height:' + (h / 18 * 100) + '%;background:' + (i === P_SALES_BARS.length - 1 ? 'var(--c-primary)' : 'var(--c-chart)')
      })),

      pPurchases: P_PURCHASES.map(ph => ({
        date: ph.date, supplier: ph.supplier, qty: ph.qty, total: ph.total,
        cost: this.money(ph.cost),
        costStyle: 'font-family:\'IBM Plex Mono\',monospace;font-size:12.5px;text-align:right;font-weight:500;'
          + (ph.rise ? 'color:#b02020;' : 'color:#4a5866;')
      })),

      pVariants: P_VARIANTS.map(v => ({
        name: v.name, sku: v.sku, cost: v.cost, price: v.price, stock: v.stock,
        stockStyle: 'font-family:\'IBM Plex Mono\',monospace;font-size:12px;font-weight:600;border-radius:5px;padding:3px 7px;'
          + (v.tone === 'out' ? 'background:#fdeaea;color:#b02020;'
            : v.tone === 'low' ? 'background:#fdf1dd;color:#8a5300;'
            : 'background:#e7f6ee;color:#0f6b43;')
      })),

      pPricing: P_PRICING.map(pr => ({
        k: pr.k, v: pr.v,
        vStyle: 'font-family:\'IBM Plex Mono\',monospace;font-weight:600;' + (pr.strong ? 'font-size:15px;' : 'font-size:13px;')
      })),
      pAttrs: P_ATTRS.map(a => ({
        k: a.k, v: a.v,
        vStyle: 'font-size:12.5px;font-weight:500;' + (a.mono ? 'font-family:\'IBM Plex Mono\',monospace;font-size:12px;' : '')
      })),
      taxonomies: TAXONOMIES.map(t => ({
        name: t.name,
        n: t.rows.length,
        rows: t.rows.map(r => ({
          name: r.name, count: r.count, sub: r.sub,
          subStyle: 'font-size:11px;color:#8b98a8;' + (r.sub ? '' : 'display:none;')
        }))
      })),

      productFormOpen: S.pFormOpen,
      openProductForm: () => this.setState({ pFormOpen: true }),
      closeProductForm: () => this.setState({ pFormOpen: false }),
      ptypeChoices: P_TYPES.map(t => ({
        name: t.name,
        onClick: () => this.setState({ ptype: t.name }),
        style: 'flex:1;height:38px;border-radius:9px;display:grid;place-items:center;font-size:13px;cursor:pointer;'
          + (S.ptype === t.name
            ? 'background:var(--c-primary);color:#ffffff;font-weight:700;'
            : 'border:1px solid #dfe6ee;color:#4a5866;font-weight:500;')
      })),
      ptypeHint: (P_TYPES.find(t => t.name === S.ptype) || P_TYPES[0]).hint,
      showVariantBuilder: S.ptype === 'Variable',
      showStockFields: S.pToggles[0],
      pToggles: P_TOGGLES.map((t, i) => {
        const on = S.pToggles[i];
        return {
          name: t.name, desc: t.desc,
          onClick: () => this.setState(st => {
            const next = st.pToggles.slice();
            next[i] = !next[i];
            return { pToggles: next };
          }),
          track: 'width:36px;height:20px;border-radius:99px;position:relative;flex:none;background:' + (on ? 'var(--c-primary)' : '#c2cedb'),
          knob: 'position:absolute;top:2px;width:16px;height:16px;border-radius:50%;background:#ffffff;transition:left .14s ease;left:' + (on ? '18px' : '2px')
        };
      }),

      productNotes: PRODUCT_NOTES,
      ...this.purchaseVals(),
      ...this.sellVals(),
      ...this.posPayVals(sub - disc + tax),

      nav: NAV.map(n => {
        const on = n[0] === 'Home';
        return {
          name: n[0], key: n[1],
          style: 'display:flex;align-items:center;gap:10px;padding:8px 10px;border-radius:8px;cursor:pointer;font-size:13px;font-weight:500;margin-bottom:1px;'
            + (on ? 'background:rgba(255,255,255,.13);color:#ffffff;box-shadow:inset 2px 0 0 var(--c-accent);' : 'color:#c4dced;'),
          iconStyle: 'width:20px;height:20px;border-radius:5px;display:grid;place-items:center;font-family:\'IBM Plex Mono\',monospace;font-size:9px;font-weight:600;flex:none;'
            + (on ? 'background:var(--c-accent);color:#06283a;' : 'background:rgba(255,255,255,.1);color:#9dbdd2;'),
          caretStyle: 'color:#7fa3bb;font-size:11px;flex:none;' + (n[2] ? '' : 'visibility:hidden;')
        };
      }),

      kpis: KPIS.map(k => ({
        ...k,
        deltaStyle: 'font-size:11.5px;margin-top:6px;color:' + (k.up ? '#0f7b4a' : '#b02020')
      })),

      bars: BARS.map((h, i) => ({
        style: 'flex:1;border-radius:3px 3px 0 0;height:' + h + '%;background:' + (i === BARS.length - 1 ? 'var(--c-primary)' : 'var(--c-chart)')
      })),

      homeGo: { sell: () => this.setState({ view:'sell' }), purchases: () => this.setState({ view:'purchases' }), accounts: () => this.setState({ view:'accounts' }), expenses: () => this.setState({ view:'expenses' }) },
      alerts: ALERTS.map(a => {
        const t = TONE[a.tone];
        return {
          count: a.count, label: a.label, onClick: () => this.setState({ view: a.go }),
          style: 'display:flex;align-items:center;gap:11px;padding:10px 12px;border-radius:9px;cursor:pointer;background:' + t[0] + ';color:' + t[1]
        };
      }),

      accounts: ACCOUNTS,

      sales: SALES.map(s => {
        const t = s.st === 'paid' ? TONE.success : s.st === 'due' ? TONE.error : TONE.warn;
        return {
          ...s,
          status: s.st === 'paid' ? 'Paid' : s.st === 'due' ? 'Due' : 'Partial',
          badge: 'background:' + t[0] + ';color:' + t[1] + ';border-radius:5px;padding:2px 8px;font-size:11px;font-weight:600'
        };
      }),

      lowStock: LOW.map(l => {
        const t = l.tone === 'out' ? TONE.error : TONE.warn;
        return {
          ...l,
          badge: 'font-family:\'IBM Plex Mono\',monospace;font-size:11.5px;font-weight:600;border-radius:5px;padding:3px 7px;flex:none;background:' + t[0] + ';color:' + t[1]
        };
      }),

      homeNotes: HOME_NOTES, posNotes: POS_NOTES, kitchenNotes: KITCHEN_NOTES,
      posTools: POS_TOOLS,

      cart: S.cart.map(c => ({
        name: c.name,
        meta: c.sku + ' · ' + this.money(c.price) + ' / kg',
        qty: c.qty,
        price: this.money(c.price),
        sub: this.money(c.price * c.qty),
        inc: () => this.bump(c.id, 1),
        dec: () => this.bump(c.id, -1),
        rm: () => this.setState(st => ({ cart: st.cart.filter(x => x.id !== c.id) }))
      })),
      cartEmpty: S.cart.length === 0,
      itemCount: S.cart.reduce((a, c) => a + c.qty, 0),
      subtotal: this.money(sub),
      discount: this.money(disc),
      tax: this.money(tax),
      total: this.money(sub - disc + tax),

      cats: CATS.map(c => ({
        name: c,
        onClick: () => this.setState({ cat: c }),
        style: 'height:32px;padding:0 13px;border-radius:8px;display:flex;align-items:center;font-size:12.5px;font-weight:500;cursor:pointer;white-space:nowrap;'
          + (S.cat === c
            ? 'background:var(--c-primary);color:#ffffff;font-weight:600;'
            : 'border:1px solid #dfe6ee;color:#4a5866;')
      })),

      products: PRODUCTS.filter(p => S.cat === 'All' || p.cat === S.cat).map(p => {
        const out = p.stock === '0';
        const low = !out && Number(p.stock) < 10;
        const t = out ? TONE.error : low ? TONE.warn : TONE.success;
        return {
          name: p.name,
          price: p.price,
          stock: out ? 'Out' : p.stock + ' kg',
          onClick: () => this.add(p.name, Number(p.price)),
          style: 'background:#ffffff;border:1px solid #dfe6ee;border-radius:11px;padding:10px;cursor:pointer;transition:border-color .12s ease,box-shadow .12s ease;'
            + (out ? 'opacity:.55;' : ''),
          stockStyle: 'font-family:\'IBM Plex Mono\',monospace;font-size:10.5px;font-weight:600;border-radius:4px;padding:2px 6px;flex:none;background:' + t[0] + ';color:' + t[1]
        };
      }),

      columns: COLUMNS.map(col => {
        const t = TONE[col.tone];
        return {
          name: col.name,
          n: col.tickets.length,
          empty: col.tickets.length === 0,
          dot: 'width:9px;height:9px;border-radius:50%;flex:none;background:' + t[2],
          tickets: col.tickets.map(tk => ({
            no: tk.no,
            where: tk.where,
            time: tk.time,
            action: tk.action,
            card: 'background:#ffffff;border-radius:12px;padding:14px;'
              + (tk.late ? 'box-shadow:0 0 0 2px #d93a3a;' : '')
              + (col.tone === 'neutral' ? 'opacity:.62;' : ''),
            timeStyle: 'font-family:\'IBM Plex Mono\',monospace;font-size:13px;font-weight:600;border-radius:6px;padding:4px 8px;flex:none;'
              + (tk.late ? 'background:#fdeaea;color:#b02020;' : 'background:#eef2f6;color:#5d6b7d;'),
            items: tk.items.map(it => ({
              q: it.q,
              name: it.name,
              note: it.note,
              noteStyle: 'font-size:12px;color:#b45309;margin-top:2px;' + (it.note ? '' : 'display:none;')
            })),
            btn: 'height:40px;border-radius:9px;display:grid;place-items:center;font-size:13px;font-weight:600;cursor:pointer;'
              + (col.tone === 'neutral'
                ? 'border:1px solid #dfe6ee;color:#5d6b7d;'
                : 'background:var(--c-primary);color:#ffffff;')
          }))
        };
      })
    };
  }
}
