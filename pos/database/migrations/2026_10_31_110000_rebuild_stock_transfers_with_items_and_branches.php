<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Stock transfers as related tables:
 * - stock_transfers: id key, linked to the two branches by id.
 * - stock_transfer_items: the lines, linked to the real products.
 * - stock_transfer_logs: the activity list.
 * The old rows are copied. Their lines used product numbers from the old screen,
 * so those are matched to the real products by SKU (see PRODUCTS below).
 * Copying does not change any stock.
 */
return new class extends Migration
{
    /** Old product number => [SKU, name, unit]. */
    private const PRODUCTS = [
        1 => ['6603', 'Beef Mince', 'kg'], 2 => ['6600', 'Beef Rump Steak', 'kg'], 3 => ['6595', 'Beef Scotch Fillet', 'kg'],
        4 => ['6598', 'Beef T-Bone', 'kg'], 5 => ['6622', 'Lamb Chops', 'kg'], 6 => ['6621', 'Lamb Shoulder', 'kg'],
        7 => ['6624', 'Lamb Leg', 'kg'], 8 => ['6642', 'Chicken Breast', 'kg'], 9 => ['6641', 'Chicken Thigh', 'kg'],
        10 => ['6631', 'Goat Curry Cut', 'kg'], 11 => ['7102', 'Basmati Rice 5kg', 'ea'], 12 => ['7120', 'Spice Mix 200g', 'ea'],
        13 => ['7131', 'Pita Bread 6 pack', 'ea'],
    ];

    public function up(): void
    {
        Schema::rename('stock_transfers', 'stock_transfers_old');

        Schema::create('stock_transfers', function (Blueprint $table) {
            $table->id();
            $table->string('ref', 40)->unique();              // TR-0418
            $table->date('date');
            $table->foreignId('from_branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignId('to_branch_id')->constrained('branches')->restrictOnDelete();
            $table->string('status', 20)->default('draft');   // draft, transit, received, variance, cancelled
            $table->decimal('stock_value', 12, 2)->default(0); // quantity x cost of all the lines
            $table->decimal('shipping_cost', 12, 2)->default(0);
            $table->decimal('loss_value', 12, 2)->default(0);  // sent but not received, at cost
            $table->text('notes')->nullable();
            $table->string('created_by')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamps();
        });

        Schema::create('stock_transfer_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_transfer_id')->constrained('stock_transfers')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->string('name', 190);                      // kept even if the product is deleted later
            $table->string('sku', 40)->nullable();
            $table->string('unit', 20)->nullable();
            $table->decimal('qty', 12, 3);                    // sent
            $table->decimal('received_qty', 12, 3)->nullable(); // null until received
            $table->decimal('cost', 12, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('stock_transfer_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_transfer_id')->constrained('stock_transfers')->cascadeOnDelete();
            $table->string('message');                        // Sent by Aminul
            $table->string('tone', 20)->default('neutral');   // neutral, info, success, warn, error
            $table->timestamp('logged_at')->nullable();
        });

        foreach (DB::table('stock_transfers_old')->orderBy('date')->get() as $old) {
            $this->copyTransfer($old);
        }

        Schema::drop('stock_transfers_old');
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_transfer_logs');
        Schema::dropIfExists('stock_transfer_items');
        Schema::dropIfExists('stock_transfers');

        // The old shape, empty. Restore rows from a backup if they are needed.
        Schema::create('stock_transfers', function (Blueprint $table) {
            $table->string('ref')->primary();
            $table->date('date');
            $table->string('from');
            $table->string('to');
            $table->string('status')->default('draft');
            $table->decimal('ship', 12, 2)->default(0);
            $table->string('by')->nullable();
            $table->text('notes')->nullable();
            $table->json('lines');
            $table->json('log');
            $table->timestamps();
        });
    }

    private function copyTransfer(object $old): void
    {
        $year = substr($old->date, 0, 4);
        $firstBranch = DB::table('branches')->orderBy('id')->value('id');

        $transferId = DB::table('stock_transfers')->insertGetId([
            'ref' => $old->ref,
            'date' => $old->date,
            'from_branch_id' => DB::table('branches')->where('code', $old->from)->value('id') ?? $firstBranch,
            'to_branch_id' => DB::table('branches')->where('code', $old->to)->value('id') ?? $firstBranch,
            'status' => $old->status,
            'shipping_cost' => $old->ship,
            'notes' => $old->notes,
            'created_by' => $old->by,
            'created_at' => $old->created_at,
            'updated_at' => $old->updated_at,
        ]);

        $stockValue = 0;
        $lossValue = 0;

        foreach (json_decode($old->lines, true) as $line) {
            [$sku, $name, $unit] = self::PRODUCTS[$line['pid']];
            $product = DB::table('products')->where('sku', $sku)->first();
            $cost = $product ? (float) $product->cost : 0;
            $received = $line['recv'] ?? null;

            DB::table('stock_transfer_items')->insert([
                'stock_transfer_id' => $transferId,
                'product_id' => $product ? $product->id : null,
                'name' => $product ? $product->name : $name,
                'sku' => $sku,
                'unit' => $unit,
                'qty' => $line['qty'],
                'received_qty' => $received,
                'cost' => $cost,
                'created_at' => $old->created_at,
                'updated_at' => $old->updated_at,
            ]);

            $stockValue += $line['qty'] * $cost;
            if ($received !== null) {
                $lossValue += ($line['qty'] - $received) * $cost;
            }
        }

        // Old log rows are [text, "29/09 · 10:24", tone], newest first.
        $sentAt = null;
        $receivedAt = null;
        foreach (array_reverse(json_decode($old->log, true)) as $row) {
            $when = $this->logTime($row[1], $year);

            DB::table('stock_transfer_logs')->insert([
                'stock_transfer_id' => $transferId,
                'message' => $row[0],
                'tone' => $row[2],
                'logged_at' => $when,
            ]);

            if (str_starts_with($row[0], 'Sent')) {
                $sentAt = $when;
            }
            if (str_starts_with($row[0], 'Received')) {
                $receivedAt = $when;
            }
        }

        DB::table('stock_transfers')->where('id', $transferId)->update([
            'stock_value' => round($stockValue, 2),
            'loss_value' => round($lossValue, 2),
            'sent_at' => $sentAt,
            'received_at' => $receivedAt,
        ]);
    }

    /** "29/09 · 10:24" and 2026 => "2026-09-29 10:24:00". */
    private function logTime(string $text, string $year): ?string
    {
        if (preg_match('/(\d{2})\/(\d{2})\D+(\d{2}):(\d{2})/', $text, $m)) {
            return "$year-$m[2]-$m[1] $m[3]:$m[4]:00";
        }

        return null;
    }
};
