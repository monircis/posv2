<?php

namespace Database\Seeders;

use App\Models;
use Illuminate\Database\Seeder;

/**
 * Demo tables and bookings for Main branch, from the prototype's JSON (bookings.json, booking_customers.json).
 * The JSON has "29/09" days, "t3" tables and "yusuf" customers; they are turned into dates, table ids and names.
 * Run after BranchSeeder.
 */
class BookingSeeder extends Seeder
{
    // The prototype's tables: its id => [name, seats, zone, deposit per guest]
    private const TABLES = [
        't1' => ['Table 1', 2, 'Window', 0], 't2' => ['Table 2', 2, 'Window', 0],
        't3' => ['Table 3', 4, 'Main floor', 0], 't4' => ['Table 4', 4, 'Main floor', 0],
        't5' => ['Table 5', 4, 'Main floor', 0], 't6' => ['Table 6', 6, 'Main floor', 0],
        't7' => ['Table 7', 6, 'Back', 0], 't8' => ['Table 8', 8, 'Back', 0],
        'pr' => ['Party room', 20, 'Upstairs', 20],
    ];

    public function run(): void
    {
        $branch = Models\Branch::where('code', 'main')->first() ?? Models\Branch::orderBy('id')->first();
        if (! $branch || Models\BookingTable::where('branch_id', $branch->id)->exists()) {
            return;
        }

        $tableIds = [];
        $sort = 0;
        foreach (self::TABLES as $key => $t) {
            $table = Models\BookingTable::create([
                'branch_id' => $branch->id, 'name' => $t[0], 'seats' => $t[1], 'zone' => $t[2],
                'deposit_per_head' => $t[3], 'active' => true, 'sort' => ++$sort,
            ]);
            $tableIds[$key] = $table->id;
        }

        $customers = [];
        foreach (json_decode(file_get_contents(__DIR__.'/data/booking_customers.json'), true) as $c) {
            $customers[$c['id']] = $c;
        }

        foreach (json_decode(file_get_contents(__DIR__.'/data/bookings.json'), true) as $old) {
            [$day, $month] = explode('/', $old['day']);
            $date = '2026-'.$month.'-'.$day;
            $customer = $customers[$old['cust']] ?? ['name' => $old['cust'], 'phone' => null];

            $booking = Models\Booking::create([
                'ref' => $old['id'],
                'branch_id' => $branch->id,
                'booking_table_id' => $tableIds[$old['res']],
                'customer_name' => $customer['name'],
                'customer_phone' => $customer['phone'],
                'date' => $date,
                'start_time' => Models\Booking::time($old['start']),
                'duration' => $old['dur'],
                'size' => $old['size'],
                'status' => $old['status'],
                'deposit' => $old['dep'],
                'deposit_paid_at' => $old['dep_paid'] ? $date.' 10:00:00' : null,
                'deposit_method' => $old['dep_paid'] ? 'card' : null,
                'source' => $old['source'],
                'notes' => $old['notes'] ?: null,
                'created_by' => 'Aminul',
            ]);
            $booking->log('Created by Aminul ('.strtolower($old['source']).')', 'neutral', 'Aminul');
        }
    }
}
