<?php

namespace Database\Seeders;

use App\Models;
use Illuminate\Database\Seeder;

/**
 * Demo staff, today's attendance, leave and the first pay run. Run after DemoDataSeeder (needs branches and app_settings).
 * The JSON has branch names and text dates, so they are turned into ids and dates here.
 */
class HrmSeeder extends Seeder
{
    public function run(): void
    {
        if (Models\Employee::query()->exists()) {
            return;
        }

        foreach ($this->rows('employees') as $old) {
            $employee = new Models\Employee;
            $employee->id = $old['id'];
            $employee->fill([
                'first' => $old['first'], 'last' => $old['last'], 'role' => $old['role'], 'dept' => $old['dept'],
                'branch_id' => Models\Branch::where('name', $old['loc'])->value('id'),
                'type' => $old['type'], 'basis' => $old['basis'], 'rate' => $old['rate'], 'hours' => $old['hours'],
                'phone' => $old['phone'], 'email' => $old['email'], 'start' => $old['start'] ?: null,
                'status' => $old['status'] === 'On leave' ? 'Active' : $old['status'],
                'al' => $old['al'], 'pl' => $old['pl'], 'shift' => $old['shift'], 'pin' => $old['pin'] ?: null,
                'dob' => $old['dob'], 'emerg' => $old['emerg'], 'tfn' => $old['tfn'], 'super_fund' => $old['super_fund'],
            ]);
            $employee->save();
        }

        foreach ($this->rows('attendances') as $old) {
            Models\Attendance::create(['employee_id' => $old['employee_id'], 'date' => today()->toDateString(), 'in' => $old['in'], 'out' => $old['out']]);
        }

        foreach ($this->rows('leave_requests') as $old) {
            Models\LeaveRequest::create([
                'ref' => $old['id'], 'employee_id' => $old['emp'], 'type' => $old['type'],
                'date_from' => '2026-'.implode('-', array_reverse(explode('/', $old['from']))),
                'date_to' => '2026-'.implode('-', array_reverse(explode('/', $old['to']))),
                'days' => $old['days'], 'status' => $old['status'], 'note' => $old['note'] ?: null,
            ]);
        }

        $payroll = Models\AppSetting::where('key', 'payroll')->first();
        if ($payroll) {
            $data = is_array($payroll->value) ? $payroll->value : json_decode($payroll->value, true);
            $run = Models\PayRun::create(['period_from' => '2026-09-15', 'period_to' => '2026-09-28', 'pay_date' => '2026-09-30', 'status' => $data['payStatus'] ?? 'draft']);
            foreach ($data['pay'] ?? [] as $employeeId => $hours) {
                $run->items()->create(['employee_id' => $employeeId, 'ord_hours' => (float) $hours['ord'], 'ot_hours' => (float) $hours['ot']]);
            }
            $payroll->delete();
        }
    }

    private function rows(string $file): array
    {
        return json_decode(file_get_contents(__DIR__."/data/$file.json"), true);
    }
}
