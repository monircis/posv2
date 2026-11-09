<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bookings, per branch. The old tables came from the HTML prototype (day "29/09", table "t3",
 * customer "yusuf") and only held the demo data, so they are replaced:
 *   booking_tables  the tables and rooms of each branch
 *   bookings        one booking: branch, table, customer, date and time, party size, status, deposit
 *   booking_logs    what happened to a booking (created, confirmed, deposit paid ...)
 * The demo bookings are added again by BookingSeeder.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('bookings');
        Schema::dropIfExists('booking_customers');

        Schema::create('booking_tables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->string('name', 60);
            $table->unsignedSmallInteger('seats');
            $table->string('zone', 60)->nullable();
            $table->decimal('deposit_per_head', 8, 2)->default(0); // e.g. the party room: 20 per guest
            $table->boolean('active')->default(true);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->string('ref', 20)->unique();                      // BK-2050
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignId('booking_table_id')->constrained('booking_tables')->restrictOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->string('customer_name', 120);
            $table->string('customer_phone', 40)->nullable();
            $table->date('date');
            $table->string('start_time', 5);                          // "19:30"
            $table->unsignedSmallInteger('duration');                 // minutes
            $table->unsignedSmallInteger('size');                     // guests
            $table->string('status', 20)->default('pending');         // pending, confirmed, seated, completed, noshow, cancelled
            $table->decimal('deposit', 10, 2)->default(0);
            $table->dateTime('deposit_paid_at')->nullable();
            $table->string('deposit_method', 10)->nullable();         // cash or card
            $table->string('payment_account_id')->nullable();         // where the deposit went
            $table->string('source', 20)->default('Phone');           // Phone, Online, Walk-in
            $table->text('notes')->nullable();
            $table->string('created_by')->nullable();
            $table->timestamps();
            $table->index(['branch_id', 'date']);
        });

        Schema::create('booking_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained('bookings')->cascadeOnDelete();
            $table->string('text');
            $table->string('tone', 20)->default('neutral');           // colour of the dot: neutral, info, success, warn, error
            $table->string('user_name')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_logs');
        Schema::dropIfExists('bookings');
        Schema::dropIfExists('booking_tables');

        // The old prototype tables, empty.
        Schema::create('booking_customers', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('name');
            $table->string('phone')->nullable();
            $table->timestamps();
        });
        Schema::create('bookings', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('day');
            $table->string('res');
            $table->integer('start');
            $table->integer('dur');
            $table->string('cust');
            $table->integer('size');
            $table->string('status')->default('pending');
            $table->decimal('dep', 12, 2)->default(0);
            $table->boolean('dep_paid')->default(false);
            $table->string('source')->default('Phone');
            $table->text('notes')->nullable();
            $table->longText('log');
            $table->timestamps();
        });
    }
};
