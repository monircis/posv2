<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Branches are now records you can add and edit (Settings > Branches & locations).
 * Users are in the auth service; each user has a branch_id that points here (null = all branches).
 * Existing branches are carried over.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();      // Main branch
            $table->string('code', 20)->unique();  // main (short code used by stock screens)
            $table->string('address')->nullable();
            $table->string('invoice_prefix', 10)->nullable(); // MB-
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        // Carry over the branches that already exist.
        foreach (DB::table('locations')->orderBy('created_at')->get() as $loc) {
            DB::table('branches')->insert([
                'name' => $loc->name, 'code' => $loc->code, 'address' => $loc->address,
                'invoice_prefix' => $loc->invoice_prefix, 'active' => $loc->active,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        Schema::dropIfExists('locations');
    }

    public function down(): void
    {
        Schema::create('locations', function (Blueprint $table) {
            $table->string('code')->primary();
            $table->string('name');
            $table->string('address')->nullable();
            $table->string('invoice_prefix')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
        foreach (DB::table('branches')->get() as $branch) {
            DB::table('locations')->insert(['code' => $branch->code, 'name' => $branch->name, 'address' => $branch->address, 'invoice_prefix' => $branch->invoice_prefix, 'active' => $branch->active]);
        }
        Schema::dropIfExists('branches');
    }
};
