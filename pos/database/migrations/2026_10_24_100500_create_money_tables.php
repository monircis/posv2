<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Expenses, Payment Accounts and Accounting screens.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->string('ref')->primary(); // EX-1092
            $table->date('date');
            $table->string('cat');
            $table->string('payee');
            $table->decimal('amount', 12, 2);
            $table->string('acct')->nullable(); // payment account it was paid from; null = unpaid
            $table->string('loc')->nullable();
            $table->boolean('gst')->default(true);
            $table->string('file')->nullable();
            $table->text('notes')->nullable();
            $table->date('due')->nullable();
            $table->string('by')->nullable();
            $table->string('inv')->nullable(); // supplier invoice number
            $table->timestamps();
        });

        Schema::create('payment_accounts', function (Blueprint $table) {
            $table->string('id')->primary(); // cash, cba, amex ...
            $table->string('name');
            $table->string('kind'); // cash, bank, card, wallet
            $table->string('loc')->nullable();
            $table->string('num')->nullable();
            $table->string('bsb')->nullable();
            $table->decimal('limit', 12, 2)->default(0);
            $table->decimal('bal', 12, 2)->default(0);
            $table->boolean('active')->default(true);
            $table->json('defs');  // default uses: posCash, posCard, expenses, purchases
            $table->json('tx');    // [{date, type, desc, party, ref, amt}]
            $table->timestamps();
        });

        Schema::create('ledger_accounts', function (Blueprint $table) {
            $table->string('code')->primary(); // 1-1100
            $table->string('name');
            $table->string('type'); // asset, liability, equity, income, expense
            $table->decimal('open', 12, 2)->default(0);
            $table->string('tax')->nullable();
            $table->string('tag')->nullable();
            $table->timestamps();
        });

        Schema::create('journals', function (Blueprint $table) {
            $table->string('no')->primary(); // JN-0421
            $table->string('date'); // as shown: "30/09"
            $table->string('src');  // sale, purchase, expense, stock, transfer, manual
            $table->string('desc');
            $table->string('ref')->nullable();
            $table->string('status')->default('draft'); // draft, posted
            $table->json('lines'); // [{code, dr, cr}]
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journals');
        Schema::dropIfExists('ledger_accounts');
        Schema::dropIfExists('payment_accounts');
        Schema::dropIfExists('expenses');
    }
};
