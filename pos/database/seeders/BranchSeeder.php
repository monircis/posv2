<?php

namespace Database\Seeders;

use App\Models\Branch;
use Illuminate\Database\Seeder;

/** Demo branches. Add or change branches later in Settings > Branches & locations. */
class BranchSeeder extends Seeder
{
    public function run(): void
    {
        $branches = [
            ['Main branch', 'main', '118 Haldon St, Lakemba NSW 2195', 'MB-'],
            ['Bankstown shop', 'bank', '22 Chapel Rd, Bankstown NSW 2200', 'BK-'],
            ['Cold store', 'cold', '7 Muir Rd, Chullora NSW 2190', 'CS-'],
        ];

        foreach ($branches as [$name, $code, $address, $prefix]) {
            Branch::updateOrCreate(['code' => $code], ['name' => $name, 'address' => $address, 'invoice_prefix' => $prefix, 'active' => true]);
        }
    }
}
