<?php

namespace App\Http\Controllers\Api\Hrm;

use App\Models\Shift;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * HRM > Shifts. Times are "HH:MM". An end_time not after start_time ends the next day (night shift).
 * weekly_off: days off, 0 = Sunday ... 6 = Saturday. flexible: only required_minutes counts.
 * Deleting a shift keeps it for old attendance (soft delete).
 */
#[Group('HRM setup', weight: 19)]
class ShiftController extends HrmSetupController
{
    protected string $model = Shift::class;

    protected function rules(Request $request, ?Model $record): array
    {
        $time = ['required', 'regex:/^([01][0-9]|2[0-3]):[0-5][0-9]$/'];

        return [
            'branch_id' => 'nullable|exists:branches,id',
            'name' => 'required|string|max:80',
            'code' => ['required', 'string', 'max:20', Rule::unique('shifts')->ignore($record?->id)],
            'start_time' => $time,
            'end_time' => $time,
            'break_minutes' => 'integer|min:0|max:600',
            'late_grace_minutes' => 'integer|min:0|max:600',
            'early_grace_minutes' => 'integer|min:0|max:600',
            'half_day_minutes' => 'integer|min:0|max:1440',
            'flexible' => 'boolean',
            'required_minutes' => 'required_if:flexible,true|integer|min:0|max:1440',
            'weekly_off' => 'array',
            'weekly_off.*' => 'integer|between:0,6',
            'active' => 'boolean',
        ];
    }

    protected function problem(array $data, ?Model $record): ?string
    {
        if ($data['start_time'] === $data['end_time'] && empty($data['flexible'])) {
            return 'Start and end cannot be the same time.';
        }

        return null;
    }
}
