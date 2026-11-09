<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /** php artisan migrate --seed (users and roles are seeded in the auth service) */
    public function run(): void
    {
        $this->call([
            BranchSeeder::class,
            SettingsSeeder::class,
            ProductListsSeeder::class,
            DemoDataSeeder::class,
            PurchaseSeeder::class,
            StockTransferSeeder::class,
            StockAdjustmentSeeder::class,
            ExpenseSeeder::class,
            SaleSeeder::class,
            ManufacturingSeeder::class,
            HrmSeeder::class,
            BookingSeeder::class,
        ]);
    }
}
