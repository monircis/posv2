<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Unit;
use Illuminate\Database\Seeder;

/** Demo categories, brands and units. Each list is only filled when it is empty. */
class ProductListsSeeder extends Seeder
{
    public function run(): void
    {
        if (Category::count() === 0) {
            // [name, description]
            $categories = [
                ['Beef', null], ['Lamb', null], ['Goat', null], ['Chicken', null],
                ['Butcher', 'House made'], ['Grocery', null], ['Grill', 'Restaurant meals'], ['Drinks', null],
            ];
            foreach ($categories as [$name, $description]) {
                Category::create(['name' => $name, 'description' => $description]);
            }

            // Sub-categories: [main category, name]
            $subCategories = [['Beef', 'Steaks'], ['Beef', 'Mince'], ['Lamb', 'Chops'], ['Lamb', 'Mince'], ['Chicken', 'Whole']];
            foreach ($subCategories as [$main, $name]) {
                Category::create(['name' => $name, 'parent_id' => Category::where('name', $main)->value('id')]);
            }
        }

        if (Brand::count() === 0) {
            foreach (['Halal Prime', 'Baraka', 'House made', 'Sunrise Foods', 'Unbranded'] as $name) {
                Brand::create(['name' => $name]);
            }
        }

        if (Unit::count() === 0) {
            // [name, short name, allow decimals] - short names match products.unit
            $units = [
                ['Kilogram', 'kg', true],
                ['Gram', 'g', true],
                ['Each', 'ea', false],
                ['Pack', 'pack', false],
                ['Litre', 'L', true],
            ];
            foreach ($units as [$name, $short, $decimals]) {
                Unit::create(['name' => $name, 'short_name' => $short, 'allow_decimals' => $decimals]);
            }
        }
    }
}
