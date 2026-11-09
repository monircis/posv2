<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * HRM step 2: shifts, who works which shift (and from when), and holidays.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->restrictOnDelete(); // null = all branches
            $table->string('name', 80);
            $table->string('code', 20)->unique();
            $table->string('start_time', 5);                         // "09:00"
            $table->string('end_time', 5);                           // "17:00", or "02:00" for a night shift (next day)
            $table->unsignedSmallInteger('break_minutes')->default(0);
            $table->unsignedSmallInteger('late_grace_minutes')->default(0);   // clock-in up to this late is not late
            $table->unsignedSmallInteger('early_grace_minutes')->default(0);  // clock-out up to this early is not early
            $table->unsignedSmallInteger('half_day_minutes')->default(0);     // worked less than this = half day (0 = off)
            $table->boolean('flexible')->default(false);             // flexible hours: only the total counts, not start/end
            $table->unsignedSmallInteger('required_minutes')->default(0);     // flexible: minutes to work per day
            $table->json('weekly_off')->nullable();                  // [0, 6] = Sunday and Saturday off
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        // Who works a shift, from date_from (to date_to). The most specific one wins: employee, then department, then branch.
        // With rotation_shift_ids the shift changes every rotation_days days, in that order, starting on date_from.
        Schema::create('shift_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shift_id')->constrained('shifts')->restrictOnDelete();
            $table->string('target_type', 12);                       // branch, department, employee
            $table->unsignedBigInteger('target_id');
            $table->date('date_from');
            $table->date('date_to')->nullable();
            $table->json('rotation_shift_ids')->nullable();
            $table->unsignedSmallInteger('rotation_days')->default(7);
            $table->string('note')->nullable();
            $table->string('created_by')->nullable();
            $table->timestamps();
            $table->index(['target_type', 'target_id', 'date_from']);
        });

        Schema::create('holidays', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->restrictOnDelete(); // null = all branches
            $table->string('name', 120);
            $table->date('date');
            $table->string('type', 20)->default('Public');           // Public, Company
            $table->boolean('recurring')->default(false);            // the same day every year
            $table->timestamps();
            $table->index('date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('holidays');
        Schema::dropIfExists('shift_assignments');
        Schema::dropIfExists('shifts');
    }
};
