# HRM module

Plain MVC: migrations → models (the rules live in the models) → controllers (validate, check branch, call the model) → `routes/api.php`.
All URLs start with `/api`, need `Authorization: Bearer <token>` and answer JSON. The full generated docs are at `/docs/api` (Scramble, groups "HRM ...").

The old HRM endpoints (`/employees`, `/attendances`, `/attendances/clock`, `/leave-requests`, `/pay-runs`, `/tips`) still work as before.

## Who sees what

| Rule | How |
|---|---|
| Branch | A user with a branch only sees that branch's staff and records, unless they have `hrm.all-locations`. Setup rows with `branch_id = null` (departments, shifts, holidays, policies) are for every branch. |
| Permission per route | `hrm.view` read, `hrm.create` add, `hrm.edit` change, `hrm.delete` delete |
| `hrm.approve` | the **HR manager** level in approvals |
| `hrm.payroll` | salary structures, deductions, bonuses / advances / loans, monthly payroll, payslips, leave encashment |
| `hrm.admin` | the **administrator** level (can decide any step), approval workflows, HRM settings |
| Self-service `/me/hrm/*` | any signed-in user whose sign-in is linked to an employee (`employees.user_id`); only their own data |
| Approvals `/approvals/*` | any signed-in user; who may decide is checked per step |

Roles: Owner has all three extras. Branch Manager has `hrm.approve` + `hrm.payroll`. New role **HR Manager** = Home + HRM (all actions) + approve + payroll.

## Tables (migrations `2026_11_19_*`)

| Migration | Tables |
|---|---|
| `100000_hrm_employee_details` | `departments`, `designations`, more `employees` columns (code, user_id, department/designation, reporting manager, probation, exit, bank, photo, soft delete), `employee_documents`, `employee_educations`, `employee_experiences`, `employee_status_histories` |
| `110000_hrm_shifts_and_holidays` | `shifts`, `shift_assignments`, `holidays` |
| `120000_hrm_approvals` | `approval_workflows` (seeded for leave, overtime, correction, deduction, payroll), `approvals` |
| `130000_hrm_attendance_punches` | more `attendances` columns (status, totals), `attendance_punches`, `attendance_breaks`, `attendance_corrections` |
| `140000_hrm_leave_policies` | `leave_policies` (Annual, Personal, Unpaid made from the old types), `leave_balances`, more `leave_requests` columns |
| `150000_hrm_overtime_deductions_payroll` | `overtime_policies`, `overtime_requests`, `deduction_policies`, `deduction_exceptions`, `salary_deductions`, `salary_structures`, `salary_adjustments`, `employee_loans`, more `pay_runs` / `pay_run_items` columns |
| auth `2026_11_19_100000_add_hrm_permissions` | `hrm.approve`, `hrm.payroll`, `hrm.admin`, role HR Manager |

Settings (`config/hrm.php`, changed with `PUT /hrm/settings`): `duplicate_punch_minutes` (2), `open_punch_hours` (20), `weekly_off` ([0] = Sunday, for staff with no shift), `hours_per_month` (164.67), `hours_per_day` (7.6), `salary_days_per_month` (30).

## How it works

**Employees.** The code `EMP-0001` is made when none is sent. With `probation_months` the status starts as `Probation` until `start + months`; `POST /employees/{id}/confirm` makes them Active. `POST /employees/{id}/exit` records Resigned / Terminated; they still count for attendance and pay up to `exit_date`. Every status change is a line in the status history. Delete is a soft delete.

**Shifts.** `end_time` not after `start_time` = the shift ends the next day (night shift). Which shift someone works on a day: an employee assignment beats a department one, which beats a branch one; then the newest `date_from`. A rotation (`rotation_shift_ids`, `rotation_days`) changes the shift every N days. Old assignments are the shift history.

**Attendance.** Every clock-in / out / break is a punch (`POST /attendances/punch`). The day row is then worked out again (`Attendance::recalculate()`):
- worked = in→out time minus breaks; late = clock-in after start + grace; early = clock-out before end − grace; extra = worked − (shift length − break)
- status: `present`, `late`, `half_day` (worked < shift's `half_day_minutes`), `missing_punch` (no clock-out after the day), and for days with no punch: `leave`, `holiday`, `weekend` (day off), `absent`
- a punch inside `duplicate_punch_minutes` of the last one is refused; punches out of order are refused
- an open clock-in belongs to the same day for up to `open_punch_hours`: a night shift's clock-out the next morning counts for the day it started
- on a holiday or day off all worked time is extra

**Corrections.** An employee (or HR) asks for a fix; when the last approval step approves, the day's punches are replaced. HR can also fix a day directly: `POST /attendances/manual`.

**Approvals.** Each request gets one step per level of its workflow, in order. A level with nobody (no reporting manager) is left out. Nobody decides their own request. Rejecting stops the rest. Before the last approval the request is checked again (enough leave left, overtime month limit).

**Leave.** A policy is a leave type with its rules (paid, balance, accrual yearly / monthly, carry forward with a limit, max balance, half-day / hourly, weekends and holidays left out, not on probation, attachment after N days, only for a gender / employment types / branch / department). Balances are made when first needed: yearly days allocated, last year carried forward, monthly accrual up to this month. Asking checks the balance minus the requests still waiting. Approving takes the days off and marks the days `leave` in attendance; cancelling an approved request gives them back.

**Overtime.** From the attendance: extra minutes ≥ `min_minutes` become an "auto" request (capped at `max_daily_minutes`). Amount = minutes / 60 × hourly rate × the rate for the day (regular, weekend, holiday). Approving stops at `max_monthly_minutes`. The policy used: the employee's own, else their branch's, else the all-branch one.

**Deductions.** Nothing is taken automatically. `POST /salary-deductions/calculate` goes through the month's finished days (before today, without a pending correction) and the active policies; it skips excused employees (exceptions), the first `free_times`, anything over `monthly_limit`, and days already deducted (a unique key: one per policy per day), so running it again adds nothing new. Deductions go through approval unless the policy says `needs_approval: false`. Only approved ones reach payroll.

**Payroll (monthly).** `POST /payroll` makes the month's run (kind `monthly` in `pay_runs`) from the salary structures:

    net = basic + allowances + overtime + bonus/incentive/encashment − (fixed + approved deductions) − advance − loan instalment

Draft → `submit` (payroll approval workflow) → approved → `pay`. Paying marks the overtime, deductions and adjustments as paid and takes the loan instalment. The fortnight pay run (`/pay-runs`, HRM > Payroll screen) is unchanged.

## Endpoints

Setup (`hrm.view` to read; create / edit / delete need those actions):

| | |
|---|---|
| `GET POST /departments`, `GET PUT DELETE /departments/{id}` | `name, branch_id?, manager_id?, active` |
| `GET POST /designations`, `PUT DELETE /designations/{id}` | `name, department_id?` |
| `GET POST /shifts`, `GET PUT DELETE /shifts/{id}` | `name, code, start_time, end_time, break_minutes, late_grace_minutes, early_grace_minutes, half_day_minutes, flexible, required_minutes, weekly_off[]` |
| `GET POST /shift-assignments`, `PUT DELETE /shift-assignments/{id}` | `shift_id, target_type (branch/department/employee), target_id, date_from, date_to?, rotation_shift_ids?, rotation_days?` |
| `GET POST /holidays`, `GET PUT DELETE /holidays/{id}`, `GET /holidays/calendar?year&month` | `name, date, type (Public/Company), recurring, branch_id?` |
| `GET POST /leave-policies` (+ `/{id}`) | see the leave rules above |
| `GET POST /overtime-policies` (+ `/{id}`) | `name, min_minutes, max_daily_minutes, max_monthly_minutes, regular_rate, weekend_rate, holiday_rate, auto_calculate` |
| `GET POST /deduction-policies` (+ `/{id}`) | `name, type, method, value, grace_minutes, free_times, monthly_limit, needs_approval` |
| `GET /approval-workflows`, `PUT /approval-workflows/{module}` (hrm.admin) | `levels[]`, `active` |
| `GET PUT /hrm/settings` (PUT: hrm.admin) | the settings above |

Update calls (`PUT`) send the whole record.

Employees:

| | |
|---|---|
| `GET /employees` | filters `branch_id, department_id, designation_id, status (or working), q, per_page` |
| `POST /employees`, `GET PUT DELETE /employees/{id}` | GET = the full profile (salary only with hrm.payroll) |
| `POST /employees/{id}/status` · `/confirm` · `/exit` · `/photo` | |
| `GET POST /employees/{id}/documents`, `GET /employee-documents/{id}/download`, `DELETE /employee-documents/{id}` | multipart `file, title, type, expires_on` |
| `GET POST /employees/{id}/educations` · `/experiences`, `PUT DELETE /employee-educations/{id}` · `/employee-experiences/{id}` | |
| `GET /employees/{id}/schedule?from&to` · `/attendance?from&to` | |
| `GET POST /employees/{id}/salary`, `DELETE /salary-structures/{id}` (hrm.payroll) | |

Attendance, leave, overtime:

| | |
|---|---|
| `POST /attendances/punch` | `employee_id, type (in/out/break_start/break_end), at?, remote?` |
| `POST /attendances/manual` | `employee_id, date, in_at, out_at?, break_minutes?, note?` |
| `GET /attendances?date` · `/attendances/daily?date&day_status` · `/attendances/summary?month` · `/attendances/{id}` | |
| `GET POST /attendance-corrections`, `GET /attendance-corrections/{id}`, `POST .../{id}/cancel` | `employee_id, date, in_at, out_at?, break_minutes?, reason` |
| `GET POST /leave-requests`, `GET /leave-requests/{id}`, `POST .../{id}/decide` · `/cancel`, `GET .../{id}/attachment` | `employee_id, leave_policy_id (or type), date_from, date_to, duration (full/half/hours), hours?, note?, attachment?` |
| `GET /leave-calendar?month` | |
| `GET /leave-balances?year`, `POST /leave-balances/allocate`, `POST /leave-balances/{employee}/{policy}/adjust` · `/encash` | |
| `GET POST /overtime-requests`, `GET /overtime-requests/summary?month`, `GET /overtime-requests/{id}`, `POST .../{id}/cancel` | `employee_id, date, minutes, reason` |

Payroll (`hrm.payroll`):

| | |
|---|---|
| `GET POST /salary-deductions`, `POST /salary-deductions/calculate`, `GET DELETE /salary-deductions/{id}` | calculate: `month, branch_id?` |
| `GET POST /deduction-exceptions`, `DELETE /deduction-exceptions/{id}` | |
| `GET POST /salary-adjustments`, `DELETE /salary-adjustments/{id}` | `employee_id, month, type (bonus/incentive/advance), amount` |
| `GET POST /employee-loans`, `POST /employee-loans/{id}/close` | `employee_id, amount, installment, start_month` |
| `GET POST /payroll`, `GET /payroll/report?year&month`, `GET DELETE /payroll/{id}`, `POST /payroll/{id}/recalculate` · `/submit` · `/pay` | `month, branch_id?` |
| `GET /payslips?pay_run_id`, `GET /payslips/{id}`, `GET /payslips/{id}/print` | print = HTML page |

Reports, dashboard, approvals, self-service:

| | |
|---|---|
| `GET /attendance-reports` (list), `GET /attendance-reports/{report}` | reports: daily, monthly, employee, branch, department, late, early, absent, present, half_day, overtime, missing_punch, leave, working_hours, breaks, corrections. Filters `from, to (≤ 93 days), employee_id, branch_id, department_id, designation_id, shift_id, day_status`. `format=csv / excel / pdf` |
| `GET /hrm-dashboard?branch_id` | |
| `GET /approvals/pending?module`, `POST /approvals/{module}/{id}`, `GET /approvals/{module}/{id}/history` | decide: `approve, remarks (required when rejecting)` |
| `/me/hrm/`: `profile`, `attendance`, `attendance/summary`, `shifts`, `holidays`, `leave` (GET/POST), `leave/{id}/cancel`, `leave-balances`, `team-leave`, `corrections` (GET/POST), `corrections/{id}/cancel`, `overtime` (GET/POST), `salary`, `payslips`, `payslips/{id}` | |

Exports: `csv` is a real CSV; `excel` is an HTML table saved as `.xls` (Excel opens it); `pdf` and payslip print are print-ready HTML pages, saved as PDF from the browser's print dialog. No PDF / Excel library is installed.

## Examples

Punch in:

    POST /api/attendances/punch
    {"employee_id": 12, "type": "in", "at": "2026-11-03 09:20"}

    200 {"id": 41, "employee_id": 12, "date": "2026-11-03", "day_type": "workday", "status": "late",
         "first_in_at": "2026-11-03 09:20", "late_minutes": 20, "worked_minutes": 0, "punches": [...], "breaks": []}

    422 {"message": "A punch was already recorded at 09:20. Wait 2 minutes between punches."}

Apply for leave (self-service):

    POST /api/me/hrm/leave
    {"leave_policy_id": 1, "date_from": "2026-11-16", "date_to": "2026-11-22", "note": "Holiday"}

    201 {"id": 7, "ref": "LV-7", "type": "Annual", "days": 5, "status": "Pending", ...}
    422 {"message": "Emp only has 3.5 days of annual leave left (including requests that are waiting)."}

Approve a step:

    POST /api/approvals/leave/7
    {"approve": true, "remarks": "Enjoy"}

    200 {"result": "next", "record": {...}, "approvals": [{"step": 1, "level": "reporting_manager", "status": "approved",
         "approver_name": "Team Lead", "acted_at": "..."}, {"step": 2, "level": "hr_manager", "status": "pending"}]}
    403 {"message": "This is waiting for the reporting manager, not you."}

Make the month's payroll:

    POST /api/payroll
    {"month": "2026-11", "branch_id": 3}

    201 {"id": 1, "kind": "monthly", "month": "2026-11", "status": "draft", "staff": 2, "gross": ..., "net": ...,
         "items": [{"employee_id": 12, "basic": 4000, "allowances": 650, "overtime": 294.1, "bonus": 200,
                    "deductions": 160.83, "advance": 100, "loan": 200, "net": 4483.27,
                    "lines": [{"name": "Basic", "amount": 4000}, {"name": "House rent", "amount": 500}, ...]}],
         "approvals": []}

## Frontend

The old tabs (Employees, Attendance, Leave, Payroll, Tips) are unchanged. The new screens are plain Vue, one file each, linked in a second row on the HRM page; shared helpers are in `src/api/hrm.js`.

| Route | File | Uses |
|---|---|---|
| `/hrm/dashboard` | `views/hrm/HrmDashboard.vue` | `/hrm-dashboard` |
| `/hrm/daily` | `views/hrm/DailyBoard.vue` | `/attendances/daily`, `/attendances/punch`, `/attendances/{id}`, `/attendances/manual`, `/attendance-corrections` |
| `/hrm/approvals` | `views/hrm/Approvals.vue` | `/approvals/pending`, `/approvals/{module}/{id}`, `.../history` |
| `/hrm/files`, `/hrm/files/:id` | `views/hrm/StaffFiles.vue`, `StaffFile.vue` | `/employees`, documents, educations, experiences, confirm, exit, photo, schedule, attendance |
| `/hrm/leave-balances` | `views/hrm/LeaveBalances.vue` | `/leave-balances`, adjust, encash, allocate, `/leave-calendar` |
| `/hrm/overtime` | `views/hrm/Overtime.vue` | `/overtime-requests`, summary, cancel |
| `/hrm/reports` | `views/hrm/HrmReports.vue` | `/attendance-reports/{report}` + csv / excel / pdf |
| `/hrm/salary` | `views/hrm/Salary.vue` | `/employees/{id}/salary`, `/salary-adjustments`, `/employee-loans` |
| `/hrm/deductions` | `views/hrm/Deductions.vue` | `/salary-deductions`, calculate, `/deduction-exceptions` |
| `/hrm/monthly-payroll` | `views/hrm/MonthlyPayroll.vue` | `/payroll`, recalculate / submit / pay, `/payslips/{id}/print`, `/payroll/report` |
| `/hrm/shifts` | `views/hrm/Shifts.vue` | `/shifts`, `/shift-assignments` |
| `/hrm/holidays` | `views/hrm/Holidays.vue` | `/holidays`, `/holidays/calendar` |
| `/hrm/policies` | `views/hrm/Policies.vue` | `/leave-policies`, `/overtime-policies`, `/deduction-policies` |
| `/hrm/setup` | `views/hrm/HrmSetup.vue` | `/departments`, `/designations`, `/approval-workflows`, `/hrm/settings` |
| `/my-hr` (every signed-in user, "My HR" in the header) | `views/MyHrView.vue` | `/me/hrm/*`, and the Approvals screen as "To approve" |

Links and buttons follow the permissions: Pay screens need `hrm.payroll`; add / edit / delete buttons need `hrm.create` / `hrm.edit` / `hrm.delete`; workflows and settings can only be changed with `hrm.admin`.
