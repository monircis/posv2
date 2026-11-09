<?php

use App\Http\Controllers\Api;
use Illuminate\Support\Facades\Route;

/*
| API for the Vue frontend (pos-vuejs). Every URL starts with /api.
| Sign-in, users and roles are in the auth service (auth/, POST /api/auth/login there).
| Send its token here as "Authorization: Bearer <token>"; the permissions are inside it.
| Permissions are "<screen>.<action>", actions: view, create, edit, delete.
*/

// Branches: the list is public (sign-in page); changes need the Settings permissions.
Route::get('branches', [Api\BranchController::class, 'index']);
Route::middleware('auth.token')->group(function () {
    Route::post('branches', [Api\BranchController::class, 'store'])->middleware('permission:settings.create');
    Route::put('branches/{branch}', [Api\BranchController::class, 'update'])->middleware('permission:settings.edit');
    Route::delete('branches/{branch}', [Api\BranchController::class, 'destroy'])->middleware('permission:settings.delete');
});

/**
 * List, create, show, update, delete for one table.
 * $view lets other screens read the list too (e.g. the POS reads products).
 */
$crud = function (string $uri, string $controller, string $screen, ?string $view = null): void {
    $id = '{id}';
    Route::get($uri, [$controller, 'index'])->middleware('permission:'.($view ?? $screen.'.view'));
    Route::get("$uri/$id", [$controller, 'show'])->middleware('permission:'.($view ?? $screen.'.view'));
    Route::post($uri, [$controller, 'store'])->middleware("permission:$screen.create");
    Route::put("$uri/$id", [$controller, 'update'])->middleware("permission:$screen.edit");
    Route::delete("$uri/$id", [$controller, 'destroy'])->middleware("permission:$screen.delete");
};

Route::middleware('auth.token')->group(function () use ($crud) {
    // Contacts (the POS picks customers, Purchases picks suppliers, so they can read the list too)
    Route::get('contacts', [Api\ContactController::class, 'index'])->middleware('permission:contacts.view|sell.view|purchases.view|bookings.view');
    Route::get('contacts/{contact}', [Api\ContactController::class, 'show'])->middleware('permission:contacts.view');
    Route::get('contacts/{contact}/transactions', [Api\ContactController::class, 'transactions'])->middleware('permission:contacts.view');
    Route::get('contacts/{contact}/coins', [Api\CoinController::class, 'index'])->middleware('permission:contacts.view');
    Route::post('contacts/{contact}/coins', [Api\CoinController::class, 'adjust'])->middleware('permission:contacts.edit');
    Route::post('contacts', [Api\ContactController::class, 'store'])->middleware('permission:contacts.create');
    Route::put('contacts/{contact}', [Api\ContactController::class, 'update'])->middleware('permission:contacts.edit');
    Route::delete('contacts/{contact}', [Api\ContactController::class, 'destroy'])->middleware('permission:contacts.delete');

    // Customer groups (the POS reads them for pricing)
    Route::get('customer-groups', [Api\CustomerGroupController::class, 'index'])->middleware('permission:contacts.view|sell.view');
    Route::get('customer-groups/{customerGroup}', [Api\CustomerGroupController::class, 'show'])->middleware('permission:contacts.view');
    Route::post('customer-groups', [Api\CustomerGroupController::class, 'store'])->middleware('permission:contacts.create');
    Route::put('customer-groups/{customerGroup}', [Api\CustomerGroupController::class, 'update'])->middleware('permission:contacts.edit');
    Route::delete('customer-groups/{customerGroup}', [Api\CustomerGroupController::class, 'destroy'])->middleware('permission:contacts.delete');

    // POS terminal
    Route::get('pos/catalog', [Api\PosController::class, 'catalog'])->middleware('permission:sell.view');
    Route::post('pos/customers', [Api\PosController::class, 'addCustomer'])->middleware('permission:sell.create');

    // Products (the POS reads products too). form-data must come before products/{product}.
    Route::get('products/form-data', [Api\ProductController::class, 'formData'])->middleware('permission:products.view');
    Route::get('products/labels', [Api\ProductLabelController::class, 'index'])->middleware('permission:products.view');
    Route::get('products', [Api\ProductController::class, 'index'])->middleware('permission:products.view|sell.view');
    Route::get('products/{product}', [Api\ProductController::class, 'show'])->middleware('permission:products.view|sell.view');
    Route::post('products', [Api\ProductController::class, 'store'])->middleware('permission:products.create');
    Route::put('products/{product}', [Api\ProductController::class, 'update'])->middleware('permission:products.edit');
    Route::delete('products/{product}', [Api\ProductController::class, 'destroy'])->middleware('permission:products.delete');
    // Photo
    Route::post('products/{product}/image', [Api\ProductImageController::class, 'store'])->middleware('permission:products.create|products.edit');
    Route::delete('products/{product}/image', [Api\ProductImageController::class, 'destroy'])->middleware('permission:products.edit');
    // Stock per branch
    Route::get('products/{product}/movements', [Api\ProductStockController::class, 'movements'])->middleware('permission:products.view');
    Route::post('products/{product}/adjust-stock', [Api\ProductStockController::class, 'adjust'])->middleware('permission:products.edit|adjust.create');

    // Categories, brands, units (the POS reads them too)
    Route::get('categories', [Api\CategoryController::class, 'index'])->middleware('permission:products.view|sell.view');
    Route::get('categories/{category}', [Api\CategoryController::class, 'show'])->middleware('permission:products.view|sell.view');
    Route::post('categories', [Api\CategoryController::class, 'store'])->middleware('permission:products.create');
    Route::put('categories/{category}', [Api\CategoryController::class, 'update'])->middleware('permission:products.edit');
    Route::delete('categories/{category}', [Api\CategoryController::class, 'destroy'])->middleware('permission:products.delete');

    Route::get('brands', [Api\BrandController::class, 'index'])->middleware('permission:products.view|sell.view');
    Route::get('brands/{brand}', [Api\BrandController::class, 'show'])->middleware('permission:products.view|sell.view');
    Route::post('brands', [Api\BrandController::class, 'store'])->middleware('permission:products.create');
    Route::put('brands/{brand}', [Api\BrandController::class, 'update'])->middleware('permission:products.edit');
    Route::delete('brands/{brand}', [Api\BrandController::class, 'destroy'])->middleware('permission:products.delete');

    Route::get('units', [Api\UnitController::class, 'index'])->middleware('permission:products.view|sell.view');
    Route::get('units/{unit}', [Api\UnitController::class, 'show'])->middleware('permission:products.view|sell.view');
    Route::post('units', [Api\UnitController::class, 'store'])->middleware('permission:products.create');
    Route::put('units/{unit}', [Api\UnitController::class, 'update'])->middleware('permission:products.edit');
    Route::delete('units/{unit}', [Api\UnitController::class, 'destroy'])->middleware('permission:products.delete');

    // Purchases, per branch. form-data and products must come before purchases/{purchase}.
    Route::get('purchases/form-data', [Api\PurchaseController::class, 'formData'])->middleware('permission:purchases.create|purchases.edit');
    Route::get('purchases/products', [Api\PurchaseController::class, 'products'])->middleware('permission:purchases.create|purchases.edit');
    Route::get('purchases', [Api\PurchaseController::class, 'index'])->middleware('permission:purchases.view');
    Route::get('purchases/{purchase}', [Api\PurchaseController::class, 'show'])->middleware('permission:purchases.view');
    Route::post('purchases', [Api\PurchaseController::class, 'store'])->middleware('permission:purchases.create');
    Route::put('purchases/{purchase}', [Api\PurchaseController::class, 'update'])->middleware('permission:purchases.edit');
    Route::delete('purchases/{purchase}', [Api\PurchaseController::class, 'destroy'])->middleware('permission:purchases.delete');
    Route::post('purchases/{purchase}/receive', [Api\PurchaseController::class, 'receive'])->middleware('permission:purchases.edit');
    Route::post('purchases/{purchase}/invoice', [Api\PurchaseController::class, 'uploadInvoice'])->middleware('permission:purchases.create|purchases.edit');
    Route::post('purchases/{purchase}/payments', [Api\PurchasePaymentController::class, 'store'])->middleware('permission:purchases.edit');
    Route::get('purchase-returns', [Api\PurchaseReturnController::class, 'index'])->middleware('permission:purchases.view');
    Route::post('purchase-returns', [Api\PurchaseReturnController::class, 'store'])->middleware('permission:purchases.create');

    // Sell (POS sales are sales)
    Route::get('sales', [Api\SaleController::class, 'index'])->middleware('permission:sell.view');
    Route::get('sales/{sale}', [Api\SaleController::class, 'show'])->middleware('permission:sell.view');
    Route::post('sales', [Api\SaleController::class, 'store'])->middleware('permission:sell.create');
    Route::post('sales/{sale}/receive', [Api\SaleController::class, 'receive'])->middleware('permission:sell.create|sell.edit');
    Route::post('sales/{sale}/cancel', [Api\SaleController::class, 'cancel'])->middleware('permission:sell.edit');

    // Cash register shifts on the POS
    Route::get('cash-register', [Api\CashRegisterController::class, 'current'])->middleware('permission:sell.view');
    Route::get('cash-register/mine', [Api\CashRegisterController::class, 'mine'])->middleware('permission:sell.view');
    Route::post('cash-register/open', [Api\CashRegisterController::class, 'open'])->middleware('permission:sell.create');
    Route::post('cash-register/{register}/move', [Api\CashRegisterController::class, 'move'])->middleware('permission:sell.create');
    Route::post('cash-register/{register}/close', [Api\CashRegisterController::class, 'close'])->middleware('permission:sell.create');
    Route::get('cash-registers', [Api\CashRegisterController::class, 'index'])->middleware('permission:sell.view');
    Route::get('cash-registers/{register}', [Api\CashRegisterController::class, 'show'])->middleware('permission:sell.view');

    // Stock
    // Stock transfers. form-data and products must come before stock-transfers/{stockTransfer}.
    Route::get('stock-transfers/form-data', [Api\StockTransferController::class, 'formData'])->middleware('permission:transfers.create|transfers.edit');
    Route::get('stock-transfers/products', [Api\StockTransferController::class, 'products'])->middleware('permission:transfers.create|transfers.edit');
    Route::get('stock-transfers', [Api\StockTransferController::class, 'index'])->middleware('permission:transfers.view');
    Route::get('stock-transfers/{stockTransfer}', [Api\StockTransferController::class, 'show'])->middleware('permission:transfers.view');
    Route::post('stock-transfers', [Api\StockTransferController::class, 'store'])->middleware('permission:transfers.create');
    Route::put('stock-transfers/{stockTransfer}', [Api\StockTransferController::class, 'update'])->middleware('permission:transfers.edit');
    Route::post('stock-transfers/{stockTransfer}/send', [Api\StockTransferController::class, 'send'])->middleware('permission:transfers.edit');
    Route::post('stock-transfers/{stockTransfer}/receive', [Api\StockTransferController::class, 'receive'])->middleware('permission:transfers.edit');
    Route::post('stock-transfers/{stockTransfer}/cancel', [Api\StockTransferController::class, 'cancel'])->middleware('permission:transfers.edit');
    // Stock adjustments. form-data and products must come before stock-adjustments/{stockAdjustment}.
    Route::get('stock-adjustments/form-data', [Api\StockAdjustmentController::class, 'formData'])->middleware('permission:adjust.create');
    Route::get('stock-adjustments/products', [Api\StockAdjustmentController::class, 'products'])->middleware('permission:adjust.create');
    Route::get('stock-adjustments', [Api\StockAdjustmentController::class, 'index'])->middleware('permission:adjust.view');
    Route::get('stock-adjustments/{stockAdjustment}', [Api\StockAdjustmentController::class, 'show'])->middleware('permission:adjust.view');
    Route::post('stock-adjustments', [Api\StockAdjustmentController::class, 'store'])->middleware('permission:adjust.create');
    Route::post('stock-adjustments/{stockAdjustment}/reverse', [Api\StockAdjustmentController::class, 'reverse'])->middleware('permission:adjust.edit');

    // Money
    Route::get('expense-categories', [Api\ExpenseCategoryController::class, 'index'])->middleware('permission:expenses.view');
    Route::post('expense-categories', [Api\ExpenseCategoryController::class, 'store'])->middleware('permission:expenses.create');
    Route::put('expense-categories/{expenseCategory}', [Api\ExpenseCategoryController::class, 'update'])->middleware('permission:expenses.edit');
    // Expenses. form-data must come before expenses/{expense}.
    Route::get('expenses/form-data', [Api\ExpenseController::class, 'formData'])->middleware('permission:expenses.create|expenses.edit');
    Route::get('expenses', [Api\ExpenseController::class, 'index'])->middleware('permission:expenses.view');
    Route::get('expenses/{expense}', [Api\ExpenseController::class, 'show'])->middleware('permission:expenses.view');
    Route::post('expenses', [Api\ExpenseController::class, 'store'])->middleware('permission:expenses.create');
    Route::put('expenses/{expense}', [Api\ExpenseController::class, 'update'])->middleware('permission:expenses.edit');
    Route::post('expenses/{expense}/pay', [Api\ExpenseController::class, 'pay'])->middleware('permission:expenses.edit');
    Route::delete('expenses/{expense}', [Api\ExpenseController::class, 'destroy'])->middleware('permission:expenses.delete');
    Route::post('expenses/{expense}/receipt', [Api\ExpenseController::class, 'uploadReceipt'])->middleware('permission:expenses.create|expenses.edit');
    Route::delete('expenses/{expense}/receipt', [Api\ExpenseController::class, 'deleteReceipt'])->middleware('permission:expenses.create|expenses.edit');
    // Payment accounts, per branch (Expenses reads the list too)
    Route::get('payment-accounts', [Api\PaymentAccountController::class, 'index'])->middleware('permission:accounts.view|expenses.view');
    Route::post('payment-accounts/transfer', [Api\PaymentAccountController::class, 'transfer'])->middleware('permission:accounts.edit');
    Route::get('payment-accounts/{id}', [Api\PaymentAccountController::class, 'show'])->middleware('permission:accounts.view|expenses.view');
    Route::post('payment-accounts', [Api\PaymentAccountController::class, 'store'])->middleware('permission:accounts.create');
    Route::put('payment-accounts/{id}', [Api\PaymentAccountController::class, 'update'])->middleware('permission:accounts.edit');
    Route::delete('payment-accounts/{id}', [Api\PaymentAccountController::class, 'destroy'])->middleware('permission:accounts.delete');
    $crud('ledger-accounts', Api\LedgerAccountController::class, 'accounting');
    $crud('journals', Api\JournalController::class, 'accounting');

    // Restaurant
    // Dashboard (Home): per branch and per salesperson
    Route::get('dashboard', [Api\DashboardController::class, 'index'])->middleware('permission:home.view');

    // Reports, per branch (a user with a branch sees only theirs, unless they have reports.all-locations)
    Route::middleware('permission:reports.view')->prefix('reports')->group(function () {
        Route::get('/', [Api\Reports\ReportController::class, 'index']);
        Route::get('sales', [Api\Reports\SalesReportController::class, 'sales']);
        Route::get('customers', [Api\Reports\SalesReportController::class, 'customers']);
        Route::get('products', [Api\Reports\SalesReportController::class, 'products']);
        Route::get('payments', [Api\Reports\SalesReportController::class, 'payments']);
        Route::get('purchases', [Api\Reports\PurchaseReportController::class, 'purchases']);
        Route::get('suppliers', [Api\Reports\PurchaseReportController::class, 'suppliers']);
        Route::get('stock', [Api\Reports\StockReportController::class, 'stock']);
        Route::get('manufacturing', [Api\Reports\StockReportController::class, 'manufacturing']);
        Route::get('profit-loss', [Api\Reports\FinanceReportController::class, 'profitLoss']);
        Route::get('expenses', [Api\Reports\FinanceReportController::class, 'expenses']);
        Route::get('gst', [Api\Reports\FinanceReportController::class, 'gst']);
    });

    // Bookings, per branch
    Route::get('bookings', [Api\BookingController::class, 'index'])->middleware('permission:bookings.view');
    Route::get('bookings/{booking}', [Api\BookingController::class, 'show'])->middleware('permission:bookings.view');
    Route::post('bookings', [Api\BookingController::class, 'store'])->middleware('permission:bookings.create');
    Route::put('bookings/{booking}', [Api\BookingController::class, 'update'])->middleware('permission:bookings.edit');
    Route::post('bookings/{booking}/status', [Api\BookingController::class, 'status'])->middleware('permission:bookings.edit');
    Route::post('bookings/{booking}/deposit', [Api\BookingController::class, 'deposit'])->middleware('permission:bookings.edit');
    Route::get('booking-tables', [Api\BookingTableController::class, 'index'])->middleware('permission:bookings.view');
    Route::post('booking-tables', [Api\BookingTableController::class, 'store'])->middleware('permission:bookings.edit');
    Route::put('booking-tables/{bookingTable}', [Api\BookingTableController::class, 'update'])->middleware('permission:bookings.edit');
    Route::delete('booking-tables/{bookingTable}', [Api\BookingTableController::class, 'destroy'])->middleware('permission:bookings.delete');
    $crud('orders', Api\OrderController::class, 'orders');
    // Kitchen board, per branch (tickets are made by POS sales)
    Route::get('kitchen', [Api\KitchenController::class, 'index'])->middleware('permission:kitchen.view');
    Route::post('kitchen/{ticket}/status', [Api\KitchenController::class, 'status'])->middleware('permission:kitchen.edit');
    Route::post('kitchen/{ticket}/undo', [Api\KitchenController::class, 'undo'])->middleware('permission:kitchen.edit');
    Route::post('kitchen/{ticket}/rush', [Api\KitchenController::class, 'rush'])->middleware('permission:kitchen.edit');
    Route::post('kitchen/{ticket}/items/{item}', [Api\KitchenController::class, 'toggleItem'])->middleware('permission:kitchen.edit');

    // ------------------------------------------------------------------
    // HRM (docs/HRM.md). hrm.view / create / edit / delete as usual, plus:
    //   hrm.approve  = HR manager level in approvals    hrm.payroll = salary, deductions, payroll, payslips
    //   hrm.admin    = administrator level, workflows and HRM settings
    //   hrm.all-locations = every branch's staff
    // ------------------------------------------------------------------

    // Employees
    Route::get('employees', [Api\EmployeeController::class, 'index'])->middleware('permission:hrm.view');
    Route::post('employees', [Api\EmployeeController::class, 'store'])->middleware('permission:hrm.create');
    Route::get('employees/{employee}', [Api\EmployeeController::class, 'show'])->middleware('permission:hrm.view');
    Route::put('employees/{employee}', [Api\EmployeeController::class, 'update'])->middleware('permission:hrm.edit');
    Route::delete('employees/{employee}', [Api\EmployeeController::class, 'destroy'])->middleware('permission:hrm.delete');
    Route::post('employees/{employee}/status', [Api\EmployeeController::class, 'status'])->middleware('permission:hrm.edit');
    Route::post('employees/{employee}/confirm', [Api\EmployeeController::class, 'confirm'])->middleware('permission:hrm.edit');
    Route::post('employees/{employee}/exit', [Api\EmployeeController::class, 'exit'])->middleware('permission:hrm.edit');
    Route::post('employees/{employee}/photo', [Api\EmployeeController::class, 'photo'])->middleware('permission:hrm.edit');
    Route::get('employees/{employee}/documents', [Api\Hrm\EmployeeRecordController::class, 'documents'])->middleware('permission:hrm.view');
    Route::post('employees/{employee}/documents', [Api\Hrm\EmployeeRecordController::class, 'storeDocument'])->middleware('permission:hrm.edit');
    Route::get('employee-documents/{document}/download', [Api\Hrm\EmployeeRecordController::class, 'downloadDocument'])->middleware('permission:hrm.view');
    Route::delete('employee-documents/{document}', [Api\Hrm\EmployeeRecordController::class, 'destroyDocument'])->middleware('permission:hrm.edit');
    Route::get('employees/{employee}/{kind}', [Api\Hrm\EmployeeRecordController::class, 'index'])->whereIn('kind', ['educations', 'experiences'])->middleware('permission:hrm.view');
    Route::post('employees/{employee}/{kind}', [Api\Hrm\EmployeeRecordController::class, 'store'])->whereIn('kind', ['educations', 'experiences'])->middleware('permission:hrm.edit');
    Route::put('employee-{kind}/{id}', [Api\Hrm\EmployeeRecordController::class, 'update'])->whereIn('kind', ['educations', 'experiences'])->middleware('permission:hrm.edit');
    Route::delete('employee-{kind}/{id}', [Api\Hrm\EmployeeRecordController::class, 'destroy'])->whereIn('kind', ['educations', 'experiences'])->middleware('permission:hrm.edit');
    Route::get('employees/{employee}/schedule', [Api\Hrm\ShiftAssignmentController::class, 'schedule'])->middleware('permission:hrm.view');
    Route::get('employees/{employee}/attendance', [Api\AttendanceController::class, 'history'])->middleware('permission:hrm.view');
    Route::get('employees/{employee}/salary', [Api\Hrm\SalaryController::class, 'structures'])->middleware('permission:hrm.payroll');
    Route::post('employees/{employee}/salary', [Api\Hrm\SalaryController::class, 'storeStructure'])->middleware('permission:hrm.payroll');
    Route::delete('salary-structures/{salaryStructure}', [Api\Hrm\SalaryController::class, 'destroyStructure'])->middleware('permission:hrm.payroll');

    // Setup: departments, designations, shifts, holidays, policies (view: hrm.view, changes: hrm.create / hrm.edit / hrm.delete)
    $hrmSetup = function (string $uri, string $controller) {
        Route::get($uri, [$controller, 'index'])->middleware('permission:hrm.view');
        Route::get("$uri/{id}", [$controller, 'show'])->middleware('permission:hrm.view');
        Route::post($uri, [$controller, 'store'])->middleware('permission:hrm.create');
        Route::put("$uri/{id}", [$controller, 'update'])->middleware('permission:hrm.edit');
        Route::delete("$uri/{id}", [$controller, 'destroy'])->middleware('permission:hrm.delete');
    };
    Route::get('holidays/calendar', [Api\Hrm\HolidayController::class, 'calendar'])->middleware('permission:hrm.view');
    $hrmSetup('departments', Api\Hrm\DepartmentController::class);
    $hrmSetup('shifts', Api\Hrm\ShiftController::class);
    $hrmSetup('holidays', Api\Hrm\HolidayController::class);
    $hrmSetup('leave-policies', Api\Hrm\LeavePolicyController::class);
    $hrmSetup('overtime-policies', Api\Hrm\OvertimePolicyController::class);
    $hrmSetup('deduction-policies', Api\Hrm\DeductionPolicyController::class);
    Route::get('designations', [Api\Hrm\DesignationController::class, 'index'])->middleware('permission:hrm.view');
    Route::post('designations', [Api\Hrm\DesignationController::class, 'store'])->middleware('permission:hrm.create');
    Route::put('designations/{designation}', [Api\Hrm\DesignationController::class, 'update'])->middleware('permission:hrm.edit');
    Route::delete('designations/{designation}', [Api\Hrm\DesignationController::class, 'destroy'])->middleware('permission:hrm.delete');
    Route::get('shift-assignments', [Api\Hrm\ShiftAssignmentController::class, 'index'])->middleware('permission:hrm.view');
    Route::post('shift-assignments', [Api\Hrm\ShiftAssignmentController::class, 'store'])->middleware('permission:hrm.edit');
    Route::put('shift-assignments/{shiftAssignment}', [Api\Hrm\ShiftAssignmentController::class, 'update'])->middleware('permission:hrm.edit');
    Route::delete('shift-assignments/{shiftAssignment}', [Api\Hrm\ShiftAssignmentController::class, 'destroy'])->middleware('permission:hrm.edit');
    Route::get('hrm/settings', [Api\Hrm\HrmSettingController::class, 'show'])->middleware('permission:hrm.view');
    Route::put('hrm/settings', [Api\Hrm\HrmSettingController::class, 'update'])->middleware('permission:hrm.admin');

    // Attendance
    Route::get('attendances', [Api\AttendanceController::class, 'index'])->middleware('permission:hrm.view');
    Route::get('attendances/daily', [Api\AttendanceController::class, 'daily'])->middleware('permission:hrm.view');
    Route::get('attendances/summary', [Api\AttendanceController::class, 'summary'])->middleware('permission:hrm.view');
    Route::post('attendances/clock', [Api\AttendanceController::class, 'clock'])->middleware('permission:hrm.edit');
    Route::post('attendances/punch', [Api\AttendanceController::class, 'punch'])->middleware('permission:hrm.edit');
    Route::post('attendances/manual', [Api\AttendanceController::class, 'manual'])->middleware('permission:hrm.edit');
    Route::get('attendances/{attendance}', [Api\AttendanceController::class, 'show'])->whereNumber('attendance')->middleware('permission:hrm.view');
    Route::get('attendance-corrections', [Api\Hrm\AttendanceCorrectionController::class, 'index'])->middleware('permission:hrm.view');
    Route::post('attendance-corrections', [Api\Hrm\AttendanceCorrectionController::class, 'store'])->middleware('permission:hrm.create');
    Route::get('attendance-corrections/{attendanceCorrection}', [Api\Hrm\AttendanceCorrectionController::class, 'show'])->middleware('permission:hrm.view');
    Route::post('attendance-corrections/{attendanceCorrection}/cancel', [Api\Hrm\AttendanceCorrectionController::class, 'cancel'])->middleware('permission:hrm.edit');
    Route::get('attendance-reports', [Api\Hrm\HrmReportController::class, 'index'])->middleware('permission:hrm.view');
    Route::get('attendance-reports/{report}', [Api\Hrm\HrmReportController::class, 'show'])->middleware('permission:hrm.view');

    // Leave
    Route::get('leave-requests', [Api\LeaveRequestController::class, 'index'])->middleware('permission:hrm.view');
    Route::post('leave-requests', [Api\LeaveRequestController::class, 'store'])->middleware('permission:hrm.create');
    Route::get('leave-requests/{leaveRequest}', [Api\LeaveRequestController::class, 'show'])->middleware('permission:hrm.view');
    Route::post('leave-requests/{leaveRequest}/decide', [Api\LeaveRequestController::class, 'decide'])->middleware('permission:hrm.edit');
    Route::post('leave-requests/{leaveRequest}/cancel', [Api\LeaveRequestController::class, 'cancel'])->middleware('permission:hrm.edit');
    Route::get('leave-requests/{leaveRequest}/attachment', [Api\LeaveRequestController::class, 'attachment'])->middleware('permission:hrm.view');
    Route::get('leave-calendar', [Api\LeaveRequestController::class, 'calendar'])->middleware('permission:hrm.view');
    Route::get('leave-balances', [Api\Hrm\LeaveBalanceController::class, 'index'])->middleware('permission:hrm.view');
    Route::post('leave-balances/allocate', [Api\Hrm\LeaveBalanceController::class, 'allocate'])->middleware('permission:hrm.edit');
    Route::post('leave-balances/{employee}/{leavePolicy}/adjust', [Api\Hrm\LeaveBalanceController::class, 'adjust'])->middleware('permission:hrm.edit');
    Route::post('leave-balances/{employee}/{leavePolicy}/encash', [Api\Hrm\LeaveBalanceController::class, 'encash'])->middleware('permission:hrm.payroll');

    // Overtime
    Route::get('overtime-requests', [Api\Hrm\OvertimeRequestController::class, 'index'])->middleware('permission:hrm.view');
    Route::get('overtime-requests/summary', [Api\Hrm\OvertimeRequestController::class, 'summary'])->middleware('permission:hrm.view');
    Route::post('overtime-requests', [Api\Hrm\OvertimeRequestController::class, 'store'])->middleware('permission:hrm.create');
    Route::get('overtime-requests/{overtimeRequest}', [Api\Hrm\OvertimeRequestController::class, 'show'])->middleware('permission:hrm.view');
    Route::post('overtime-requests/{overtimeRequest}/cancel', [Api\Hrm\OvertimeRequestController::class, 'cancel'])->middleware('permission:hrm.edit');

    // Deductions (hrm.payroll)
    Route::get('salary-deductions', [Api\Hrm\SalaryDeductionController::class, 'index'])->middleware('permission:hrm.payroll');
    Route::post('salary-deductions/calculate', [Api\Hrm\SalaryDeductionController::class, 'calculate'])->middleware('permission:hrm.payroll');
    Route::post('salary-deductions', [Api\Hrm\SalaryDeductionController::class, 'store'])->middleware('permission:hrm.payroll');
    Route::get('salary-deductions/{salaryDeduction}', [Api\Hrm\SalaryDeductionController::class, 'show'])->middleware('permission:hrm.payroll');
    Route::delete('salary-deductions/{salaryDeduction}', [Api\Hrm\SalaryDeductionController::class, 'destroy'])->middleware('permission:hrm.payroll');
    Route::get('deduction-exceptions', [Api\Hrm\SalaryDeductionController::class, 'exceptions'])->middleware('permission:hrm.payroll');
    Route::post('deduction-exceptions', [Api\Hrm\SalaryDeductionController::class, 'storeException'])->middleware('permission:hrm.payroll');
    Route::delete('deduction-exceptions/{deductionException}', [Api\Hrm\SalaryDeductionController::class, 'destroyException'])->middleware('permission:hrm.payroll');

    // Salary adjustments, loans, monthly payroll and payslips (hrm.payroll)
    Route::get('salary-adjustments', [Api\Hrm\SalaryController::class, 'adjustments'])->middleware('permission:hrm.payroll');
    Route::post('salary-adjustments', [Api\Hrm\SalaryController::class, 'storeAdjustment'])->middleware('permission:hrm.payroll');
    Route::delete('salary-adjustments/{salaryAdjustment}', [Api\Hrm\SalaryController::class, 'destroyAdjustment'])->middleware('permission:hrm.payroll');
    Route::get('employee-loans', [Api\Hrm\SalaryController::class, 'loans'])->middleware('permission:hrm.payroll');
    Route::post('employee-loans', [Api\Hrm\SalaryController::class, 'storeLoan'])->middleware('permission:hrm.payroll');
    Route::post('employee-loans/{employeeLoan}/close', [Api\Hrm\SalaryController::class, 'closeLoan'])->middleware('permission:hrm.payroll');
    Route::get('payroll', [Api\Hrm\PayrollController::class, 'index'])->middleware('permission:hrm.payroll');
    Route::get('payroll/report', [Api\Hrm\PayrollController::class, 'report'])->middleware('permission:hrm.payroll');
    Route::post('payroll', [Api\Hrm\PayrollController::class, 'store'])->middleware('permission:hrm.payroll');
    Route::get('payroll/{payRun}', [Api\Hrm\PayrollController::class, 'show'])->middleware('permission:hrm.payroll');
    Route::post('payroll/{payRun}/recalculate', [Api\Hrm\PayrollController::class, 'recalculate'])->middleware('permission:hrm.payroll');
    Route::post('payroll/{payRun}/submit', [Api\Hrm\PayrollController::class, 'submit'])->middleware('permission:hrm.payroll');
    Route::post('payroll/{payRun}/pay', [Api\Hrm\PayrollController::class, 'pay'])->middleware('permission:hrm.payroll');
    Route::delete('payroll/{payRun}', [Api\Hrm\PayrollController::class, 'destroy'])->middleware('permission:hrm.payroll');
    Route::get('payslips', [Api\Hrm\PayrollController::class, 'payslips'])->middleware('permission:hrm.payroll');
    Route::get('payslips/{payRunItem}', [Api\Hrm\PayrollController::class, 'payslip'])->middleware('permission:hrm.payroll');
    Route::get('payslips/{payRunItem}/print', [Api\Hrm\PayrollController::class, 'printPayslip'])->middleware('permission:hrm.payroll');

    // Fortnight pay run (HRM > Payroll screen) and tips, as before
    Route::get('pay-runs/current', [Api\PayRunController::class, 'current'])->middleware('permission:hrm.view');
    Route::post('pay-runs', [Api\PayRunController::class, 'store'])->middleware('permission:hrm.create');
    Route::put('pay-runs/{payRun}/hours', [Api\PayRunController::class, 'saveHours'])->middleware('permission:hrm.edit');
    Route::post('pay-runs/{payRun}/approve', [Api\PayRunController::class, 'approve'])->middleware('permission:hrm.edit');
    Route::post('pay-runs/{payRun}/reopen', [Api\PayRunController::class, 'reopen'])->middleware('permission:hrm.edit');
    Route::post('pay-runs/{payRun}/pay', [Api\PayRunController::class, 'pay'])->middleware('permission:hrm.edit');
    Route::get('tips', [Api\TipController::class, 'index'])->middleware('permission:hrm.view');
    Route::post('tips/pay', [Api\TipController::class, 'pay'])->middleware('permission:hrm.edit');

    // Dashboard and approvals
    Route::get('hrm-dashboard', [Api\Hrm\HrmDashboardController::class, 'index'])->middleware('permission:hrm.view');
    Route::get('approval-workflows', [Api\Hrm\ApprovalController::class, 'workflows'])->middleware('permission:hrm.view');
    Route::put('approval-workflows/{module}', [Api\Hrm\ApprovalController::class, 'updateWorkflow'])->middleware('permission:hrm.admin');
    Route::get('approvals/{module}/{id}/history', [Api\Hrm\ApprovalController::class, 'history'])->whereNumber('id')->middleware('permission:hrm.view');
    // Any signed-in user: reporting and department managers decide without HRM permissions (checked per step).
    Route::get('approvals/pending', [Api\Hrm\ApprovalController::class, 'pending']);
    Route::post('approvals/{module}/{id}', [Api\Hrm\ApprovalController::class, 'decide'])->whereNumber('id');

    // Self-service: the signed-in user's own records (needs a linked employee, no HRM permission)
    Route::prefix('me/hrm')->group(function () {
        Route::get('profile', [Api\Hrm\MyHrmController::class, 'profile']);
        Route::get('attendance', [Api\Hrm\MyHrmController::class, 'attendance']);
        Route::get('attendance/summary', [Api\Hrm\MyHrmController::class, 'summary']);
        Route::get('shifts', [Api\Hrm\MyHrmController::class, 'shifts']);
        Route::get('holidays', [Api\Hrm\MyHrmController::class, 'holidays']);
        Route::get('leave', [Api\Hrm\MyHrmController::class, 'leave']);
        Route::post('leave', [Api\Hrm\MyHrmController::class, 'applyLeave']);
        Route::post('leave/{leaveRequest}/cancel', [Api\Hrm\MyHrmController::class, 'cancelLeave']);
        Route::get('leave-balances', [Api\Hrm\MyHrmController::class, 'balances']);
        Route::get('leave-types', [Api\Hrm\MyHrmController::class, 'leaveTypes']);
        Route::get('team-leave', [Api\Hrm\MyHrmController::class, 'teamLeave']);
        Route::get('corrections', [Api\Hrm\MyHrmController::class, 'corrections']);
        Route::post('corrections', [Api\Hrm\MyHrmController::class, 'askCorrection']);
        Route::post('corrections/{attendanceCorrection}/cancel', [Api\Hrm\MyHrmController::class, 'cancelCorrection']);
        Route::get('overtime', [Api\Hrm\MyHrmController::class, 'overtime']);
        Route::post('overtime', [Api\Hrm\MyHrmController::class, 'askOvertime']);
        Route::get('salary', [Api\Hrm\MyHrmController::class, 'salary']);
        Route::get('payslips', [Api\Hrm\MyHrmController::class, 'payslips']);
        Route::get('payslips/{payRunItem}', [Api\Hrm\MyHrmController::class, 'payslip']);
    });

    // Manufacturing
    Route::get('materials', [Api\MaterialController::class, 'index'])->middleware('permission:mfg.view');
    Route::post('materials', [Api\MaterialController::class, 'store'])->middleware('permission:mfg.create');
    Route::put('materials/{material}', [Api\MaterialController::class, 'update'])->middleware('permission:mfg.edit');
    Route::delete('materials/{material}', [Api\MaterialController::class, 'destroy'])->middleware('permission:mfg.delete');
    Route::post('materials/{material}/adjust', [Api\MaterialController::class, 'adjust'])->middleware('permission:mfg.edit');
    // form-data must come before recipes/{recipe} and production-orders/{productionOrder}.
    Route::get('recipes/form-data', [Api\RecipeController::class, 'formData'])->middleware('permission:mfg.view');
    Route::get('recipes', [Api\RecipeController::class, 'index'])->middleware('permission:mfg.view');
    Route::post('recipes', [Api\RecipeController::class, 'store'])->middleware('permission:mfg.create');
    Route::put('recipes/{recipe}', [Api\RecipeController::class, 'update'])->middleware('permission:mfg.edit');
    Route::delete('recipes/{recipe}', [Api\RecipeController::class, 'destroy'])->middleware('permission:mfg.delete');
    Route::get('production-orders/form-data', [Api\ProductionOrderController::class, 'formData'])->middleware('permission:mfg.create');
    Route::get('production-orders', [Api\ProductionOrderController::class, 'index'])->middleware('permission:mfg.view');
    Route::get('production-orders/{productionOrder}', [Api\ProductionOrderController::class, 'show'])->middleware('permission:mfg.view');
    Route::post('production-orders', [Api\ProductionOrderController::class, 'store'])->middleware('permission:mfg.create');
    Route::post('production-orders/{productionOrder}/start', [Api\ProductionOrderController::class, 'start'])->middleware('permission:mfg.edit');
    Route::post('production-orders/{productionOrder}/finish', [Api\ProductionOrderController::class, 'finish'])->middleware('permission:mfg.edit');
    Route::post('production-orders/{productionOrder}/cancel', [Api\ProductionOrderController::class, 'cancel'])->middleware('permission:mfg.edit');

    // Settings pages saved in one row: GET /api/settings/business, PUT /api/settings/business ...
    // (these must come before settings/{key} below)
    $settingPages = [
        'business' => Api\Settings\BusinessSettingController::class,
        'stock' => Api\Settings\StockSettingController::class,
        'tax' => Api\Settings\TaxSettingController::class,
        'formats' => Api\Settings\FormatSettingController::class,
        'payments' => Api\Settings\PaymentSettingController::class,
        'invoice' => Api\Settings\InvoiceSettingController::class,
        'products' => Api\Settings\ProductSettingController::class,
        'pos' => Api\Settings\PosSettingController::class,
        'coins' => Api\Settings\CoinSettingController::class,
        'purchases' => Api\Settings\PurchaseSettingController::class,
        'notifications' => Api\Settings\NotificationSettingController::class,
        'security' => Api\Settings\SecuritySettingController::class,
        'backup' => Api\Settings\BackupSettingController::class,
    ];
    foreach ($settingPages as $page => $controller) {
        Route::get("settings/$page", [$controller, 'show'])->middleware('permission:settings.view');
        Route::put("settings/$page", [$controller, 'update'])->middleware('permission:settings.edit');
    }

    // Settings > Business > Logo (a file, uploaded on its own)
    Route::post('settings/business/logo', [Api\Settings\BusinessSettingController::class, 'uploadLogo'])->middleware('permission:settings.edit');
    Route::delete('settings/business/logo', [Api\Settings\BusinessSettingController::class, 'deleteLogo'])->middleware('permission:settings.edit');

    // Settings lists
    $crud('settings/tax-rates', Api\Settings\TaxRateController::class, 'settings');
    $crud('settings/payment-methods', Api\Settings\PaymentMethodController::class, 'settings');
    $crud('settings/price-rules', Api\Settings\PriceRuleController::class, 'settings');
    Route::get('settings/backups', [Api\Settings\BackupController::class, 'index'])->middleware('permission:settings.view');
    Route::post('settings/backups', [Api\Settings\BackupController::class, 'store'])->middleware('permission:settings.edit');

    // Settings, HRM pay run, Accounting periods (permission checked per key)
    Route::get('settings/{key}', [Api\SettingController::class, 'show']);
    Route::put('settings/{key}', [Api\SettingController::class, 'update']);
});
