<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Products as related tables:
 * - products: id key, linked to category / brand / unit / tax rate by id, photo, type single or variable.
 * - product_variants: rows of a variable product, each with its own SKU, price and stock.
 * - product_stocks: quantity per branch (and per variant).
 * - stock_movements: every stock change, with the reason and the balance after it.
 * The old rows are copied: same SKUs, category and unit by name, branch by code.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::rename('products', 'products_old');
        Schema::rename('product_stocks', 'product_stocks_old');

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name', 190);
            $table->string('sku', 40)->unique();
            $table->string('type', 10)->default('single'); // single, variable
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->foreignId('brand_id')->nullable()->constrained('brands')->nullOnDelete();
            $table->foreignId('unit_id')->nullable()->constrained('units')->nullOnDelete();
            $table->string('barcode', 60)->nullable();
            $table->string('barcode_type', 20)->default('EAN-13'); // EAN-13, Code 128, QR
            $table->string('image')->nullable();              // file in storage/app/public/products
            $table->string('description')->nullable();
            // Pricing (single products; a variable product prices each variant)
            $table->decimal('cost', 12, 2)->default(0);           // ex tax
            $table->decimal('price', 12, 2)->default(0);          // selling price
            $table->decimal('wholesale_price', 12, 2)->nullable(); // for the "Wholesale ex-GST" price group
            $table->foreignId('tax_rate_id')->nullable()->constrained('tax_rates')->nullOnDelete();
            $table->boolean('price_includes_tax')->default(true);
            // Stock
            $table->boolean('manage_stock')->default(true);
            $table->decimal('alert_qty', 12, 3)->default(0);      // warn at or below this
            $table->boolean('allow_negative_stock')->default(false);
            // POS and kitchen
            $table->boolean('available_at_pos')->default(true);
            $table->boolean('pos_favourite')->default(false);
            $table->boolean('send_to_kitchen')->default(false);
            $table->unsignedSmallInteger('prep_minutes')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('name', 120);                 // Whole (2-3 kg), Portioned 250g ...
            $table->string('sku', 40)->unique();
            $table->string('barcode', 60)->nullable();
            $table->decimal('cost', 12, 2)->default(0);
            $table->decimal('price', 12, 2)->default(0);
            $table->decimal('wholesale_price', 12, 2)->nullable();
            $table->boolean('active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('product_stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->cascadeOnDelete(); // null = single product
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->decimal('qty', 12, 3)->default(0);
            $table->timestamps();
            $table->index(['product_id', 'variant_id', 'branch_id']);
        });

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->decimal('change', 12, 3);         // + in, - out
            $table->decimal('balance', 12, 3);        // quantity after this change
            $table->string('kind', 40);               // Opening stock, Adjustment, Sale, Purchase received ...
            $table->string('ref', 40)->nullable();    // document number, e.g. PO-2038
            $table->string('note')->nullable();
            $table->string('user_name', 120)->nullable(); // from the auth service
            $table->timestamps();
        });

        // ---- copy the old products ----
        $gst = DB::table('tax_rates')->where('code', 'GST')->value('id');
        $mainBranch = DB::table('branches')->where('code', 'main')->value('id') ?? DB::table('branches')->value('id');

        foreach (DB::table('products_old')->orderBy('created_at')->get() as $old) {
            // The old "description" was sometimes a brand (Halal Prime, house made).
            $brandId = $old->description ? DB::table('brands')->whereRaw('lower(name) = ?', [strtolower($old->description)])->value('id') : null;

            $productId = DB::table('products')->insertGetId([
                'name' => $old->name,
                'sku' => $old->sku,
                'category_id' => DB::table('categories')->where('name', $old->category)->whereNull('parent_id')->value('id'),
                'brand_id' => $brandId,
                'unit_id' => DB::table('units')->where('short_name', $old->unit)->value('id'),
                'description' => $brandId ? null : $old->description,
                'cost' => $old->cost,
                'price' => $old->price,
                'tax_rate_id' => $gst,
                'manage_stock' => $old->stock !== null,  // null stock = made to order
                'alert_qty' => $old->alert ?? 0,
                'allow_negative_stock' => $old->stock === null,
                'pos_favourite' => $old->pos_favourite,
                'send_to_kitchen' => $old->kitchen,
                'created_at' => $old->created_at,
                'updated_at' => $old->updated_at,
            ]);

            // Stock per branch; a product without stock rows keeps its old total at the main branch.
            $stocks = DB::table('product_stocks_old')->where('sku', $old->sku)->get();
            if ($stocks->isEmpty() && $old->stock !== null && $mainBranch) {
                $stocks = collect([(object) ['location' => null, 'qty' => $old->stock]]);
            }
            foreach ($stocks as $stock) {
                $branchId = $stock->location ? DB::table('branches')->where('code', $stock->location)->value('id') : $mainBranch;
                if (! $branchId) {
                    continue;
                }
                DB::table('product_stocks')->insert(['product_id' => $productId, 'branch_id' => $branchId, 'qty' => $stock->qty, 'created_at' => now(), 'updated_at' => now()]);
                DB::table('stock_movements')->insert([
                    'product_id' => $productId, 'branch_id' => $branchId, 'change' => $stock->qty, 'balance' => $stock->qty,
                    'kind' => 'Opening stock', 'note' => 'Moved from the old products table', 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }

        Schema::drop('product_stocks_old');
        Schema::drop('products_old');
    }

    public function down(): void
    {
        Schema::create('products_old', function (Blueprint $table) {
            $table->string('sku')->primary();
            $table->string('name');
            $table->string('category')->nullable();
            $table->string('unit')->default('kg');
            $table->string('description')->nullable();
            $table->decimal('cost', 12, 2)->default(0);
            $table->decimal('price', 12, 2)->default(0);
            $table->decimal('stock', 12, 3)->nullable();
            $table->decimal('alert', 12, 3)->nullable();
            $table->boolean('pos_favourite')->default(false);
            $table->boolean('kitchen')->default(false);
            $table->timestamps();
        });
        Schema::create('product_stocks_old', function (Blueprint $table) {
            $table->id();
            $table->string('location');
            $table->string('sku');
            $table->decimal('qty', 12, 3)->default(0);
            $table->timestamps();
            $table->unique(['location', 'sku']);
        });

        foreach (DB::table('products')->get() as $p) {
            $total = DB::table('product_stocks')->where('product_id', $p->id)->sum('qty');
            DB::table('products_old')->insert([
                'sku' => $p->sku, 'name' => $p->name,
                'category' => DB::table('categories')->where('id', $p->category_id)->value('name'),
                'unit' => DB::table('units')->where('id', $p->unit_id)->value('short_name') ?? 'kg',
                'description' => $p->description ?? DB::table('brands')->where('id', $p->brand_id)->value('name'),
                'cost' => $p->cost, 'price' => $p->price, 'stock' => $p->manage_stock ? $total : null, 'alert' => $p->alert_qty,
                'pos_favourite' => $p->pos_favourite, 'kitchen' => $p->send_to_kitchen,
                'created_at' => $p->created_at, 'updated_at' => $p->updated_at,
            ]);
            foreach (DB::table('product_stocks')->where('product_id', $p->id)->whereNull('variant_id')->get() as $s) {
                DB::table('product_stocks_old')->insert([
                    'location' => DB::table('branches')->where('id', $s->branch_id)->value('code'), 'sku' => $p->sku, 'qty' => $s->qty,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }

        Schema::drop('stock_movements');
        Schema::drop('product_stocks');
        Schema::drop('product_variants');
        Schema::drop('products');
        Schema::rename('products_old', 'products');
        Schema::rename('product_stocks_old', 'product_stocks');
    }
};
