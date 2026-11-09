<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Settings > Notifications. One row. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_settings', function (Blueprint $table) {
            $table->id();
            $table->string('alert_email', 190)->nullable();
            $table->boolean('low_stock')->default(true);
            $table->boolean('daily_summary')->default(true);
            $table->boolean('bills_due')->default(true);
            $table->boolean('booking_reminder')->default(true);
            $table->string('reminder_lead', 20)->default('2 hours before');
            $table->boolean('order_ready')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_settings');
    }
};
