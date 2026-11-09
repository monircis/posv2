<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Manufacturing as related tables:
 * - materials, with stock per branch (material_stocks) and a history (material_movements).
 * - recipes, linked to the product they make, with their materials in recipe_items.
 * - production_orders, linked to the recipe and the branch, with what they used and their activity.
 * The old rows are copied. No stock changes: the old orders never took materials or added products.
 */
return new class extends Migration
{
    /** Old recipe id => SKU of the product it makes. The other recipes have no product yet. */
    private const PRODUCTS = ['saus' => '6602'];

    public function up(): void
    {
        Schema::rename('materials', 'materials_old');
        Schema::rename('recipes', 'recipes_old');
        Schema::rename('production_orders', 'production_orders_old');

        Schema::create('materials', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120)->unique();
            $table->string('unit', 20);
            $table->decimal('cost', 12, 2)->default(0);  // per unit
            $table->timestamps();
        });

        Schema::create('material_stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('material_id')->constrained('materials')->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->decimal('qty', 12, 3)->default(0);
            $table->timestamps();
            $table->unique(['material_id', 'branch_id']);
        });

        Schema::create('material_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('material_id')->constrained('materials')->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->decimal('change', 12, 3);            // + in, - out
            $table->decimal('balance', 12, 3);           // quantity after this change
            $table->string('kind', 40);                  // Opening stock, Stock in, Stock out, Production
            $table->string('ref', 40)->nullable();       // MO-0118
            $table->string('note')->nullable();
            $table->string('user_name', 120)->nullable();
            $table->timestamps();
        });

        Schema::create('recipes', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120)->unique();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete(); // what Finish adds to stock
            $table->unsignedBigInteger('variant_id')->nullable();
            $table->string('unit', 20);
            $table->decimal('output_qty', 12, 3)->default(1); // made by one batch
            $table->decimal('price', 12, 2)->default(0);      // selling price when no product is linked
            $table->timestamps();
        });

        Schema::create('recipe_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recipe_id')->constrained('recipes')->cascadeOnDelete();
            $table->foreignId('material_id')->constrained('materials')->restrictOnDelete();
            $table->decimal('qty', 12, 3);                    // per batch
            $table->timestamps();
        });

        Schema::create('production_orders', function (Blueprint $table) {
            $table->id();
            $table->string('ref', 40)->unique();              // MO-0118
            $table->foreignId('recipe_id')->constrained('recipes')->restrictOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete(); // materials and output
            $table->string('recipe_name', 120);               // copied for the list
            $table->string('unit', 20);
            $table->unsignedInteger('batches')->default(1);
            $table->date('date');
            $table->string('status', 20)->default('planned'); // planned, progress, done, cancelled
            $table->decimal('expected_qty', 12, 3)->default(0);
            $table->decimal('actual_qty', 12, 3)->nullable(); // weighed when finished
            $table->decimal('material_cost', 12, 2)->default(0);
            $table->boolean('materials_taken')->default(true); // old in-progress orders never took any
            $table->string('created_by')->nullable();
            $table->dateTime('finished_at')->nullable();
            $table->timestamps();
        });

        Schema::create('production_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('production_order_id')->constrained('production_orders')->cascadeOnDelete();
            $table->foreignId('material_id')->constrained('materials')->restrictOnDelete();
            $table->string('name', 120);                      // copied, so old orders stay right
            $table->string('unit', 20);
            $table->decimal('qty', 12, 3);                    // for all batches
            $table->decimal('cost', 12, 2);                   // per unit, on the day
            $table->timestamps();
        });

        Schema::create('production_order_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('production_order_id')->constrained('production_orders')->cascadeOnDelete();
            $table->string('what');
            $table->string('kind', 20)->default('neutral');   // neutral, info, success, error: the dot colour
            $table->timestamps();
        });

        $this->copyOldRows();

        Schema::drop('production_orders_old');
        Schema::drop('recipes_old');
        Schema::drop('materials_old');
    }

    private function copyOldRows(): void
    {
        $branch = DB::table('branches')->where('code', 'cold')->value('id') ?? DB::table('stock_settings')->value('default_branch_id') ?? DB::table('branches')->orderBy('id')->value('id');
        $materialIds = [];
        $now = now();

        foreach (DB::table('materials_old')->orderBy('created_at')->get() as $old) {
            $id = DB::table('materials')->insertGetId([
                'name' => $old->name, 'unit' => $old->unit, 'cost' => $old->cost, 'created_at' => $old->created_at, 'updated_at' => $old->updated_at,
            ]);
            $materialIds[$old->code] = ['id' => $id, 'name' => $old->name, 'unit' => $old->unit, 'cost' => $old->cost];

            // The old stock had no branch. It all starts at the cold store.
            if ($old->stock > 0) {
                DB::table('material_stocks')->insert(['material_id' => $id, 'branch_id' => $branch, 'qty' => $old->stock, 'created_at' => $now, 'updated_at' => $now]);
                DB::table('material_movements')->insert(['material_id' => $id, 'branch_id' => $branch, 'change' => $old->stock, 'balance' => $old->stock, 'kind' => 'Opening stock', 'created_at' => $now, 'updated_at' => $now]);
            }
        }

        $recipes = [];
        foreach (DB::table('recipes_old')->orderBy('created_at')->get() as $old) {
            $sku = self::PRODUCTS[$old->id] ?? null;
            $id = DB::table('recipes')->insertGetId([
                'name' => $old->name,
                'product_id' => $sku ? DB::table('products')->where('sku', $sku)->value('id') : null,
                'unit' => $old->unit,
                'output_qty' => $old->out,
                'price' => $old->price,
                'created_at' => $old->created_at,
                'updated_at' => $old->updated_at,
            ]);
            $lines = json_decode($old->lines, true);
            foreach ($lines as $line) {
                DB::table('recipe_items')->insert(['recipe_id' => $id, 'material_id' => $materialIds[$line[0]]['id'], 'qty' => $line[1], 'created_at' => $now, 'updated_at' => $now]);
            }
            $recipes[$old->id] = ['id' => $id, 'name' => $old->name, 'unit' => $old->unit, 'out' => $old->out, 'lines' => $lines];
        }

        foreach (DB::table('production_orders_old')->orderBy('ref')->get() as $old) {
            $recipe = $recipes[$old->rec];
            $cost = 0;
            foreach ($recipe['lines'] as $line) {
                $cost += $materialIds[$line[0]]['cost'] * $line[1] * $old->b;
            }
            $date = '2026-'.implode('-', array_reverse(explode('/', $old->date)));

            $id = DB::table('production_orders')->insertGetId([
                'ref' => $old->ref,
                'recipe_id' => $recipe['id'],
                'branch_id' => DB::table('branches')->where('name', $old->loc)->value('id') ?? $branch,
                'recipe_name' => $recipe['name'],
                'unit' => $recipe['unit'],
                'batches' => $old->b,
                'date' => $date,
                'status' => $old->status,
                'expected_qty' => $recipe['out'] * $old->b,
                'actual_qty' => $old->actual,
                'material_cost' => round($cost, 2),
                'materials_taken' => false,
                'finished_at' => $old->status === 'done' ? $date.' 17:00:00' : null,
                'created_at' => $old->created_at,
                'updated_at' => $old->updated_at,
            ]);

            if (in_array($old->status, ['progress', 'done'], true)) {
                foreach ($recipe['lines'] as $line) {
                    $material = $materialIds[$line[0]];
                    DB::table('production_order_items')->insert(['production_order_id' => $id, 'material_id' => $material['id'], 'name' => $material['name'], 'unit' => $material['unit'], 'qty' => $line[1] * $old->b, 'cost' => $material['cost'], 'created_at' => $now, 'updated_at' => $now]);
                }
            }

            // Old log rows: ["Started by Karim", "22/09 07:45", "info"], newest first.
            foreach (array_reverse(json_decode($old->log, true) ?: []) as $log) {
                $parts = preg_split('/[\/ ]/', $log[1]);
                $when = count($parts) === 3 ? '2026-'.$parts[1].'-'.$parts[0].' '.$parts[2].':00' : $now;
                DB::table('production_order_logs')->insert(['production_order_id' => $id, 'what' => $log[0], 'kind' => $log[2], 'created_at' => $when, 'updated_at' => $when]);
            }
        }
    }

    public function down(): void
    {
        Schema::create('materials_old', function (Blueprint $table) {
            $table->string('code')->primary();
            $table->string('name');
            $table->string('unit');
            $table->decimal('cost', 12, 2)->default(0);
            $table->decimal('stock', 12, 3)->default(0);
            $table->timestamps();
        });
        Schema::create('recipes_old', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('name');
            $table->string('unit');
            $table->decimal('out', 12, 3)->default(0);
            $table->decimal('price', 12, 2)->default(0);
            $table->json('lines');
            $table->timestamps();
        });
        Schema::create('production_orders_old', function (Blueprint $table) {
            $table->string('ref')->primary();
            $table->string('rec');
            $table->integer('b');
            $table->string('loc')->nullable();
            $table->string('status')->default('planned');
            $table->string('date');
            $table->decimal('actual', 12, 3)->nullable();
            $table->json('log');
            $table->timestamps();
        });

        foreach (DB::table('materials')->get() as $new) {
            DB::table('materials_old')->insert(['code' => 'm'.$new->id, 'name' => $new->name, 'unit' => $new->unit, 'cost' => $new->cost, 'stock' => DB::table('material_stocks')->where('material_id', $new->id)->sum('qty'), 'created_at' => $new->created_at, 'updated_at' => $new->updated_at]);
        }
        foreach (DB::table('recipes')->get() as $new) {
            $lines = DB::table('recipe_items')->where('recipe_id', $new->id)->get()->map(fn ($i) => ['m'.$i->material_id, (float) $i->qty])->all();
            DB::table('recipes_old')->insert(['id' => 'r'.$new->id, 'name' => $new->name, 'unit' => $new->unit, 'out' => $new->output_qty, 'price' => $new->price, 'lines' => json_encode($lines), 'created_at' => $new->created_at, 'updated_at' => $new->updated_at]);
        }
        foreach (DB::table('production_orders')->get() as $new) {
            DB::table('production_orders_old')->insert(['ref' => $new->ref, 'rec' => 'r'.$new->recipe_id, 'b' => $new->batches, 'loc' => DB::table('branches')->where('id', $new->branch_id)->value('name'), 'status' => $new->status, 'date' => date('d/m', strtotime($new->date)), 'actual' => $new->actual_qty, 'log' => '[]', 'created_at' => $new->created_at, 'updated_at' => $new->updated_at]);
        }

        Schema::drop('production_order_logs');
        Schema::drop('production_order_items');
        Schema::drop('production_orders');
        Schema::drop('recipe_items');
        Schema::drop('recipes');
        Schema::drop('material_movements');
        Schema::drop('material_stocks');
        Schema::drop('materials');

        Schema::rename('materials_old', 'materials');
        Schema::rename('recipes_old', 'recipes');
        Schema::rename('production_orders_old', 'production_orders');
    }
};
