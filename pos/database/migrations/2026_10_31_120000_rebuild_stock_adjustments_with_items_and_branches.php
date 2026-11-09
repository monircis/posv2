<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Stock adjustments as related tables:
 * - stock_adjustments: id key, linked to the branch by id.
 * - stock_adjustment_items: the lines, linked to the real products.
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
        Schema::rename('stock_adjustments', 'stock_adjustments_old');

        Schema::create('stock_adjustments', function (Blueprint $table) {
            $table->id();
            $table->string('ref', 40)->unique();                 // SA-0231
            $table->date('date');
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->string('type', 20);                          // dec, inc, count
            $table->decimal('increase_value', 12, 2)->default(0); // stock added, at cost
            $table->decimal('decrease_value', 12, 2)->default(0); // stock removed, at cost
            $table->text('notes')->nullable();
            $table->string('created_by')->nullable();
            $table->string('approved_by')->nullable();           // who approved a large write-off
            $table->foreignId('reverses_id')->nullable()->constrained('stock_adjustments')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('stock_adjustment_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_adjustment_id')->constrained('stock_adjustments')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->string('name', 190);                         // kept even if the product is deleted later
            $table->string('sku', 40)->nullable();
            $table->string('unit', 20)->nullable();
            $table->decimal('before_qty', 12, 3);                // on hand before this line
            $table->decimal('change_qty', 12, 3);                // + added, - removed
            $table->decimal('cost', 12, 2)->default(0);
            $table->string('reason', 120);
            $table->timestamps();
        });

        foreach (DB::table('stock_adjustments_old')->orderBy('ref')->get() as $old) {
            $this->copyAdjustment($old);
        }

        Schema::drop('stock_adjustments_old');
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_adjustment_items');
        Schema::dropIfExists('stock_adjustments');

        // The old shape, empty. Restore rows from a backup if they are needed.
        Schema::create('stock_adjustments', function (Blueprint $table) {
            $table->string('ref')->primary();
            $table->date('date');
            $table->string('loc');
            $table->string('type');
            $table->string('by')->nullable();
            $table->string('approved')->nullable();
            $table->text('notes')->nullable();
            $table->json('lines');
            $table->string('reverses')->nullable();
            $table->string('reversed_by')->nullable();
            $table->timestamps();
        });
    }

    private function copyAdjustment(object $old): void
    {
        $adjustmentId = DB::table('stock_adjustments')->insertGetId([
            'ref' => $old->ref,
            'date' => $old->date,
            'branch_id' => DB::table('branches')->where('code', $old->loc)->value('id') ?? DB::table('branches')->orderBy('id')->value('id'),
            'type' => $old->type,
            'notes' => $old->notes ?: null,
            'created_by' => $old->by,
            'approved_by' => $old->approved === '—' ? null : $old->approved, // the old screen saved a dash for nobody
            'reverses_id' => $old->reverses ? DB::table('stock_adjustments')->where('ref', $old->reverses)->value('id') : null,
            'created_at' => $old->created_at,
            'updated_at' => $old->updated_at,
        ]);

        $increase = 0;
        $decrease = 0;

        foreach (json_decode($old->lines, true) as $line) {
            [$sku, $name, $unit] = self::PRODUCTS[$line['pid']];
            $product = DB::table('products')->where('sku', $sku)->first();
            $cost = $product ? (float) $product->cost : 0;

            DB::table('stock_adjustment_items')->insert([
                'stock_adjustment_id' => $adjustmentId,
                'product_id' => $product ? $product->id : null,
                'name' => $product ? $product->name : $name,
                'sku' => $sku,
                'unit' => $unit,
                'before_qty' => $line['before'],
                'change_qty' => $line['delta'],
                'cost' => $cost,
                'reason' => $line['reason'],
                'created_at' => $old->created_at,
                'updated_at' => $old->updated_at,
            ]);

            if ($line['delta'] > 0) {
                $increase += $line['delta'] * $cost;
            } else {
                $decrease -= $line['delta'] * $cost;
            }
        }

        DB::table('stock_adjustments')->where('id', $adjustmentId)->update([
            'increase_value' => round($increase, 2),
            'decrease_value' => round($decrease, 2),
        ]);
    }
};
