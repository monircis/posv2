<?php

namespace Database\Seeders;

use App\Models;
use Illuminate\Database\Seeder;

/**
 * Demo expenses. Run after DemoDataSeeder (needs branches and payment accounts).
 * The account balances in payment_accounts.json already include them, so no balance changes here.
 * The JSON's file names had no real files behind them, so they are left out.
 */
class ExpenseSeeder extends Seeder
{
    /** The JSON's category code => the category name (the migration adds the categories). */
    private const CATEGORIES = [
        'rent' => 'Rent', 'util' => 'Utilities', 'wage' => 'Casual wages', 'pack' => 'Packaging',
        'fuel' => 'Fuel & delivery', 'rep' => 'Repairs', 'mkt' => 'Marketing', 'clean' => 'Cleaning',
        'fees' => 'Bank fees', 'other' => 'Other',
    ];

    public function run(): void
    {
        if (Models\Expense::withTrashed()->exists()) {
            return;
        }

        $rows = json_decode(file_get_contents(__DIR__.'/data/expenses.json'), true);
        usort($rows, fn ($a, $b) => strcmp($a['ref'], $b['ref']));

        foreach ($rows as $old) {
            $expense = Models\Expense::create([
                'ref' => $old['ref'],
                'date' => $old['date'],
                'branch_id' => Models\Branch::where('code', $old['loc'])->value('id'),
                'expense_category_id' => Models\ExpenseCategory::where('name', self::CATEGORIES[$old['cat']])->value('id'),
                'payee' => $old['payee'],
                'amount' => $old['amount'],
                'has_gst' => $old['gst'],
                'gst_amount' => $old['gst'] ? round($old['amount'] / 11, 2) : 0,
                'payment_account_id' => $old['acct'] ?: null,
                'paid_date' => $old['acct'] ? $old['date'] : null,
                'due_date' => $old['acct'] ? null : ($old['due'] ?: null),
                'invoice_no' => $old['inv'] ?: null,
                'notes' => $old['notes'] ?: null,
                'created_by' => $old['by'],
            ]);

            $log = $expense->logs()->create(['what' => 'Added by '.$old['by'], 'kind' => 'neutral']);
            $log->created_at = $old['date'].' 09:00:00';
            $log->save();
        }
    }
}
