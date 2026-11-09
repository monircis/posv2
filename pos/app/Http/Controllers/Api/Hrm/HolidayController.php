<?php

namespace App\Http\Controllers\Api\Hrm;

use App\Models\Holiday;
use Carbon\Carbon;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/** HRM > Holidays. branch_id null = every branch. type: Public or Company. recurring = every year on that day. */
#[Group('HRM setup', weight: 19)]
class HolidayController extends HrmSetupController
{
    protected string $model = Holiday::class;

    protected string $orderBy = 'date';

    protected function rules(Request $request, ?Model $record): array
    {
        return [
            'branch_id' => 'nullable|exists:branches,id',
            'name' => 'required|string|max:120',
            'date' => 'required|date',
            'type' => 'required|in:Public,Company',
            'recurring' => 'boolean',
        ];
    }

    /**
     * Holiday calendar
     *
     * The holidays of a year (or one month), recurring ones on that year's date. Needs `hrm.view`.
     */
    #[QueryParameter('year', 'e.g. 2026. Default: this year.', type: 'integer')]
    #[QueryParameter('month', '1-12, optional.', type: 'integer')]
    public function calendar(Request $request)
    {
        return self::calendarFor($this->branchFor($request), (int) $request->input('year', now()->year), $request->input('month'));
    }

    public static function calendarFor(?int $branchId, int $year, $month): array
    {
        $from = $month ? Carbon::create($year, (int) $month, 1) : Carbon::create($year, 1, 1);
        $to = $month ? $from->copy()->endOfMonth() : $from->copy()->endOfYear();

        $out = [];
        // No branch (a user who sees every branch): list every branch's holidays.
        foreach (Holiday::between($branchId, $from->toDateString(), $to->toDateString(), $branchId === null) as $key => $holiday) {
            $out[] = ['date' => explode('|', $key)[0], 'name' => $holiday->name, 'type' => $holiday->type, 'recurring' => $holiday->recurring, 'branch_id' => $holiday->branch_id, 'id' => $holiday->id];
        }

        return $out;
    }
}
