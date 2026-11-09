<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** HRM: a holiday. branch_id null = every branch. recurring = the same day every year. */
class Holiday extends Model
{
    protected $table = 'holidays';

    protected $hidden = ['created_at', 'updated_at'];

    protected $fillable = ['branch_id', 'name', 'date', 'type', 'recurring'];

    protected function casts(): array
    {
        return ['branch_id' => 'integer', 'date' => 'date:Y-m-d', 'recurring' => 'boolean'];
    }

    private static array $cache = [];

    /** The holiday on that day at that branch, or null. */
    public static function onDay(?int $branchId, string $date): ?Holiday
    {
        $key = $branchId.'|'.$date;
        if (! array_key_exists($key, self::$cache)) {
            self::$cache[$key] = self::between($branchId, $date, $date)[$date] ?? null;
        }

        return self::$cache[$key];
    }

    /**
     * The holidays in a date range (recurring ones moved to that year), as [date => holiday].
     * With $everyBranch the holidays of all branches are listed, keyed "date|id" (two branches can share a day).
     */
    public static function between(?int $branchId, string $from, string $to, bool $everyBranch = false): array
    {
        $out = [];
        $rows = $everyBranch ? static::all() : static::where(fn ($q) => $q->whereNull('branch_id')->orWhere('branch_id', $branchId))->get();
        foreach ($rows as $holiday) {
            if (! $holiday->recurring) {
                $date = $holiday->date->toDateString();
                if ($date >= $from && $date <= $to) {
                    $out[$everyBranch ? $date.'|'.$holiday->id : $date] = $holiday;
                }

                continue;
            }
            for ($year = (int) substr($from, 0, 4); $year <= (int) substr($to, 0, 4); $year++) {
                $date = $year.'-'.$holiday->date->format('m-d');
                if ($date >= $from && $date <= $to && checkdate((int) substr($date, 5, 2), (int) substr($date, 8, 2), $year)) {
                    $out[$everyBranch ? $date.'|'.$holiday->id : $date] = $holiday;
                }
            }
        }
        ksort($out);

        return $out;
    }

    public static function forgetCache(): void
    {
        self::$cache = [];
    }
}
