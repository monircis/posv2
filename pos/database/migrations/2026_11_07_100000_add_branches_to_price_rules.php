<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Price rules per branch: a rule is for all branches, or only for the branches listed.
 * Rules made before this stay for all branches.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('price_rules', function (Blueprint $table) {
            $table->boolean('all_branches')->default(true)->after('all_products'); // false: only the branches below
        });

        Schema::create('price_rule_branches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('price_rule_id')->constrained('price_rules')->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->unique(['price_rule_id', 'branch_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('price_rule_branches');
        Schema::table('price_rules', function (Blueprint $table) {
            $table->dropColumn('all_branches');
        });
    }
};
