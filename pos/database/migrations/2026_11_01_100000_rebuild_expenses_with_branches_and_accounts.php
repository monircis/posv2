<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Expenses as related tables:
 * - expenses: id key, linked to the branch and the payment account.
 * - expense_logs: the Activity list on the details page.
 * The old rows are copied. Their account balances already include them, so no balance changes.
 * The old "file" column only held a demo file name with no real file, so it is not copied.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::rename('expenses', 'expenses_old');

        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->string('ref', 40)->unique();              // EX-1092
            $table->date('date');
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->string('category', 30);                   // rent, util ... see Expense::CATEGORIES
            $table->string('payee');
            $table->decimal('amount', 12, 2);                 // includes GST
            $table->boolean('has_gst')->default(true);
            $table->decimal('gst_amount', 12, 2)->default(0);
            $table->string('payment_account_id')->nullable(); // the account it was paid from; null = unpaid bill
            $table->foreign('payment_account_id')->references('id')->on('payment_accounts')->restrictOnDelete();
            $table->date('paid_date')->nullable();
            $table->date('due_date')->nullable();
            $table->string('invoice_no')->nullable();
            $table->string('receipt_file')->nullable();       // path on the public disk
            $table->string('receipt_name')->nullable();       // the name of the file that was uploaded
            $table->text('notes')->nullable();
            $table->string('created_by')->nullable();
            $table->softDeletes();                            // a deleted expense stays in the database, hidden
            $table->timestamps();
        });

        Schema::create('expense_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expense_id')->constrained('expenses')->cascadeOnDelete();
            $table->string('what');
            $table->string('kind', 20)->default('neutral');   // neutral, info, success: the dot colour
            $table->timestamps();
        });

        foreach (DB::table('expenses_old')->orderBy('ref')->get() as $old) {
            $id = DB::table('expenses')->insertGetId([
                'ref' => $old->ref,
                'date' => $old->date,
                'branch_id' => DB::table('branches')->where('code', $old->loc)->value('id') ?? DB::table('branches')->orderBy('id')->value('id'),
                'category' => $old->cat,
                'payee' => $old->payee,
                'amount' => $old->amount,
                'has_gst' => $old->gst,
                'gst_amount' => $old->gst ? round($old->amount / 11, 2) : 0,
                'payment_account_id' => $old->acct ?: null,
                'paid_date' => $old->acct ? $old->date : null,
                'due_date' => $old->acct ? null : $old->due,
                'invoice_no' => $old->inv ?: null,
                'notes' => $old->notes ?: null,
                'created_by' => $old->by,
                'created_at' => $old->created_at,
                'updated_at' => $old->updated_at,
            ]);

            DB::table('expense_logs')->insert([
                'expense_id' => $id,
                'what' => 'Added by '.$old->by,
                'kind' => 'neutral',
                'created_at' => $old->date.' 09:00:00',
                'updated_at' => $old->date.' 09:00:00',
            ]);
        }

        Schema::drop('expenses_old');
    }

    public function down(): void
    {
        Schema::rename('expenses', 'expenses_new');

        Schema::create('expenses', function (Blueprint $table) {
            $table->string('ref')->primary();
            $table->date('date');
            $table->string('cat');
            $table->string('payee');
            $table->decimal('amount', 12, 2);
            $table->string('acct')->nullable();
            $table->string('loc')->nullable();
            $table->boolean('gst')->default(true);
            $table->string('file')->nullable();
            $table->text('notes')->nullable();
            $table->date('due')->nullable();
            $table->string('by')->nullable();
            $table->string('inv')->nullable();
            $table->timestamps();
        });

        foreach (DB::table('expenses_new')->whereNull('deleted_at')->get() as $new) {
            DB::table('expenses')->insert([
                'ref' => $new->ref,
                'date' => $new->date,
                'cat' => $new->category,
                'payee' => $new->payee,
                'amount' => $new->amount,
                'acct' => $new->payment_account_id,
                'loc' => DB::table('branches')->where('id', $new->branch_id)->value('code'),
                'gst' => $new->has_gst,
                'file' => $new->receipt_name,
                'notes' => $new->notes,
                'due' => $new->due_date,
                'by' => $new->created_by,
                'inv' => $new->invoice_no,
                'created_at' => $new->created_at,
                'updated_at' => $new->updated_at,
            ]);
        }

        Schema::drop('expense_logs');
        Schema::drop('expenses_new');
    }
};
