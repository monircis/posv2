<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Expense categories become a table, so they can be added and edited on screen.
 * The ten fixed categories are added here and the expenses are linked to them.
 */
return new class extends Migration
{
    /** Old code => [name, colour]. They get ids 1 to 10 in this order. */
    private const CATEGORIES = [
        'rent' => ['Rent', 'var(--c-primary)'], 'util' => ['Utilities', '#0f8b8d'], 'wage' => ['Casual wages', '#7a4fc9'],
        'pack' => ['Packaging', '#d98c1a'], 'fuel' => ['Fuel & delivery', '#c2410c'], 'rep' => ['Repairs', '#b02020'],
        'mkt' => ['Marketing', '#c43d86'], 'clean' => ['Cleaning', '#2f9e63'], 'fees' => ['Bank fees', '#5d6b7d'],
        'other' => ['Other', '#8fa3b6'],
    ];

    public function up(): void
    {
        Schema::create('expense_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80)->unique();
            $table->string('color', 30);
            $table->boolean('active')->default(true); // switched off = not offered on the expense form
            $table->timestamps();
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->foreignId('expense_category_id')->nullable()->after('branch_id');
        });

        $otherId = null;
        foreach (self::CATEGORIES as $code => $category) {
            $id = DB::table('expense_categories')->insertGetId([
                'name' => $category[0],
                'color' => $category[1],
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('expenses')->where('category', $code)->update(['expense_category_id' => $id]);
            $otherId = $id;
        }
        // Anything else goes under Other, which is last in the list.
        DB::table('expenses')->whereNull('expense_category_id')->update(['expense_category_id' => $otherId]);

        Schema::table('expenses', function (Blueprint $table) {
            $table->foreignId('expense_category_id')->nullable(false)->change();
            $table->foreign('expense_category_id')->references('id')->on('expense_categories')->restrictOnDelete();
            $table->dropColumn('category');
        });
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->string('category', 30)->default('other')->after('branch_id');
        });

        $id = 0;
        foreach (array_keys(self::CATEGORIES) as $code) {
            $id++;
            DB::table('expenses')->where('expense_category_id', $id)->update(['category' => $code]);
        }

        Schema::table('expenses', function (Blueprint $table) {
            $table->dropForeign(['expense_category_id']);
            $table->dropColumn('expense_category_id');
        });

        Schema::drop('expense_categories');
    }
};
