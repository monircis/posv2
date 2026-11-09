<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kitchen tickets, per branch. The old table came from the HTML prototype (demo tickets with times
 * as seconds since midnight) and is replaced. A ticket is made when a POS sale has items marked
 * "Send to kitchen"; its lines are in kitchen_ticket_items.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('kitchen_tickets');

        Schema::create('kitchen_tickets', function (Blueprint $table) {
            $table->id();
            $table->string('ref', 30);                                  // the sale ref, e.g. MB-3321
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignId('sale_id')->nullable()->constrained('sales')->nullOnDelete();
            $table->string('source', 10);                               // dine, take, deliv, online
            $table->string('where_text')->nullable();                   // "Table 4 · Fatima"
            $table->string('status', 10)->default('new');               // new, prep, ready, served, cancelled
            $table->string('previous_status', 10)->nullable();          // for Undo
            $table->boolean('rush')->default(false);
            $table->string('note')->nullable();
            $table->unsignedSmallInteger('target_minutes')->default(12);
            $table->dateTime('placed_at');
            $table->dateTime('started_at')->nullable();
            $table->dateTime('ready_at')->nullable();
            $table->dateTime('served_at')->nullable();
            $table->timestamps();
            $table->index(['branch_id', 'status']);
        });

        Schema::create('kitchen_ticket_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kitchen_ticket_id')->constrained('kitchen_tickets')->cascadeOnDelete();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->string('name');
            $table->decimal('qty', 10, 3);
            $table->string('unit', 10)->nullable();
            $table->string('station', 60)->nullable();                  // the product's top category, e.g. Grill
            $table->string('note')->nullable();
            $table->boolean('done')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kitchen_ticket_items');
        Schema::dropIfExists('kitchen_tickets');

        Schema::create('kitchen_tickets', function (Blueprint $table) {
            $table->string('no')->primary();
            $table->string('src');
            $table->string('where')->nullable();
            $table->integer('placed');
            $table->string('status')->default('new');
            $table->longText('items');
            $table->boolean('rush')->default(false);
            $table->text('note')->nullable();
            $table->integer('bumped')->nullable();
            $table->integer('started')->nullable();
            $table->integer('ready_at')->nullable();
            $table->integer('served_at')->nullable();
            $table->timestamps();
        });
    }
};
