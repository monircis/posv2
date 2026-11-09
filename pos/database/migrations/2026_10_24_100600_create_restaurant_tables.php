<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Bookings, Orders and Kitchen screens.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_customers', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('name');
            $table->string('phone')->nullable();
            $table->timestamps();
        });

        Schema::create('bookings', function (Blueprint $table) {
            $table->string('id')->primary(); // BK-2031
            $table->string('day');           // "29/09"
            $table->string('res');           // table id: t1..t8, pr
            $table->integer('start');        // minutes after midnight
            $table->integer('dur');          // minutes
            $table->string('cust');          // booking_customers.id
            $table->integer('size');
            $table->string('status')->default('pending'); // pending, confirmed, seated, completed, cancelled, noshow
            $table->decimal('dep', 12, 2)->default(0);
            $table->boolean('dep_paid')->default(false);
            $table->string('source')->default('Phone');
            $table->text('notes')->nullable();
            $table->json('log');
            $table->timestamps();
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->string('no')->primary(); // #4520
            $table->string('ch');            // dine, take, deliv, online
            $table->string('cust');
            $table->string('where')->nullable();
            $table->integer('at');           // minutes after midnight
            $table->string('status')->default('new'); // new, prep, ready, out, done, cancelled
            $table->string('pay')->default('unpaid'); // unpaid, part, paid, refunded
            $table->decimal('paid', 12, 2)->nullable();
            $table->decimal('paid_amt', 12, 2)->nullable();
            $table->json('items');           // [{n, q, note, p}]
            $table->decimal('disc', 12, 2)->default(0);
            $table->decimal('fee', 12, 2)->default(0);
            $table->text('note')->nullable();
            $table->string('method')->nullable();
            $table->string('driver')->nullable();
            $table->json('times');
            $table->json('log');
            $table->timestamps();
        });

        Schema::create('kitchen_tickets', function (Blueprint $table) {
            $table->string('no')->primary(); // #4518
            $table->string('src');           // dine, take, deliv, online
            $table->string('where')->nullable();
            $table->integer('placed');       // seconds after midnight
            $table->string('status')->default('new'); // new, prep, ready, served
            $table->json('items');           // [{q, name, station, note, done}]
            $table->boolean('rush')->default(false);
            $table->text('note')->nullable();
            $table->integer('bumped')->nullable();
            $table->integer('started')->nullable();
            $table->integer('ready_at')->nullable();
            $table->integer('served_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kitchen_tickets');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('bookings');
        Schema::dropIfExists('booking_customers');
    }
};
