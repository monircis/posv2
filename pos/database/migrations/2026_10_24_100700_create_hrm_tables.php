<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// HRM screen: employees, today's attendance and leave requests.
// The current pay run is kept in app_settings under the key "payroll".
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->unsignedInteger('id')->primary();
            $table->string('first');
            $table->string('last');
            $table->string('role')->nullable();
            $table->string('dept')->nullable();
            $table->string('loc')->nullable();
            $table->string('type')->nullable();  // Full time, Part time, Casual
            $table->string('basis')->nullable(); // Hourly, Salary
            $table->decimal('rate', 12, 2)->default(0);
            $table->decimal('hours', 6, 2)->default(0);
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('start')->nullable();
            $table->string('status')->default('Active'); // Active, On leave, Inactive
            $table->decimal('al', 6, 2)->default(0); // annual leave balance (days)
            $table->decimal('pl', 6, 2)->default(0); // personal leave balance (days)
            $table->json('shift')->nullable();
            $table->string('pin')->nullable();
            $table->string('dob')->nullable();
            $table->string('emerg')->nullable();
            $table->string('tfn')->nullable();
            $table->string('super_fund')->nullable();
            $table->timestamps();
        });

        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('employee_id');
            $table->date('date');
            $table->integer('in')->nullable();  // clock-in, minutes after midnight
            $table->integer('out')->nullable(); // clock-out
            $table->timestamps();
            $table->unique(['employee_id', 'date']);
        });

        Schema::create('leave_requests', function (Blueprint $table) {
            $table->string('id')->primary(); // LV-118
            $table->unsignedInteger('emp');
            $table->string('type'); // Annual, Personal, Unpaid
            $table->string('from');
            $table->string('to');
            $table->decimal('days', 6, 2)->default(1);
            $table->string('status')->default('Pending'); // Pending, Approved, Declined
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_requests');
        Schema::dropIfExists('attendances');
        Schema::dropIfExists('employees');
    }
};
