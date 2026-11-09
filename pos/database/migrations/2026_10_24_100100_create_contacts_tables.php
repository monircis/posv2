<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Contacts screen: customers, suppliers and both, plus customer groups.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('contact_name')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('abn')->nullable();
            $table->string('type')->default('Customer'); // Customer, Supplier, Both
            $table->string('group')->nullable();
            $table->decimal('credit_limit', 12, 2)->default(0);
            $table->decimal('opening_balance', 12, 2)->default(0);
            $table->string('street')->nullable();
            $table->string('suburb')->nullable();
            $table->string('state')->nullable();
            $table->string('postcode')->nullable();
            $table->decimal('business', 12, 2)->default(0); // total business to date
            $table->decimal('balance', 12, 2)->default(0);  // + they owe us, - we owe them
            $table->string('last_activity')->nullable();
            $table->string('note')->nullable();
            $table->timestamps();
        });

        Schema::create('customer_groups', function (Blueprint $table) {
            $table->string('name')->primary();
            $table->string('description')->nullable();
            $table->string('price_group')->nullable();
            $table->decimal('discount', 5, 2)->default(0);
            $table->string('terms')->nullable();
            $table->integer('members')->default(0);
            $table->decimal('balance', 12, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_groups');
        Schema::dropIfExists('contacts');
    }
};
