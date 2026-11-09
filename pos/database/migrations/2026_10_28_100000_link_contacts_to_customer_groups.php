<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Contacts and customer groups as real related tables:
 * - customer_groups gets a normal id (it used the name as primary key).
 *   Its "members" and "balance" columns are removed: they are counted from the contacts.
 * - contacts.customer_group_id points to the group (was the group name in contacts.group).
 * - Suppliers get pay_term and advance_balance (their "group" was really a pay term, e.g. "30-day terms").
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. customer_groups with an id; copy the old rows.
        Schema::rename('customer_groups', 'customer_groups_old');
        Schema::create('customer_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120)->unique();
            $table->string('description')->nullable();
            $table->string('price_group', 60)->nullable(); // which selling price: Default selling price, Wholesale ex-GST ...
            $table->decimal('discount', 5, 2)->default(0); // %
            $table->string('terms', 40)->nullable();       // 30 days, On sale ...
            $table->timestamps();
        });
        foreach (DB::table('customer_groups_old')->orderBy('created_at')->get() as $old) {
            DB::table('customer_groups')->insert([
                'name' => $old->name, 'description' => $old->description, 'price_group' => $old->price_group,
                'discount' => $old->discount, 'terms' => $old->terms,
                'created_at' => $old->created_at, 'updated_at' => $old->updated_at,
            ]);
        }
        Schema::drop('customer_groups_old');

        // 2. New contact columns.
        Schema::table('contacts', function (Blueprint $table) {
            $table->foreignId('customer_group_id')->nullable()->after('type')->constrained('customer_groups')->nullOnDelete();
            $table->string('pay_term', 20)->nullable()->after('opening_balance');          // suppliers: 7 days, 30 days ...
            $table->decimal('advance_balance', 12, 2)->default(0)->after('pay_term');      // suppliers: paid in advance
        });

        // 3. Move contacts.group into the new columns.
        foreach (DB::table('contacts')->get() as $contact) {
            $groupId = DB::table('customer_groups')->where('name', $contact->group)->value('id');
            $payTerm = null;
            if (! $groupId && $contact->group && preg_match('/(\d+)-day/', $contact->group, $m)) {
                $payTerm = $m[1].' days'; // "30-day terms" -> "30 days"
            }
            DB::table('contacts')->where('id', $contact->id)->update(['customer_group_id' => $groupId, 'pay_term' => $payTerm]);
        }

        Schema::table('contacts', function (Blueprint $table) {
            $table->dropColumn('group');
        });
    }

    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->string('group')->nullable();
        });
        foreach (DB::table('contacts')->get() as $contact) {
            $name = DB::table('customer_groups')->where('id', $contact->customer_group_id)->value('name');
            DB::table('contacts')->where('id', $contact->id)->update(['group' => $name ?? ($contact->pay_term ? str_replace(' days', '-day terms', $contact->pay_term) : null)]);
        }
        Schema::table('contacts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('customer_group_id');
            $table->dropColumn(['pay_term', 'advance_balance']);
        });

        Schema::rename('customer_groups', 'customer_groups_new');
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
        foreach (DB::table('customer_groups_new')->get() as $g) {
            DB::table('customer_groups')->insert([
                'name' => $g->name, 'description' => $g->description, 'price_group' => $g->price_group,
                'discount' => $g->discount, 'terms' => $g->terms, 'created_at' => $g->created_at, 'updated_at' => $g->updated_at,
            ]);
        }
        Schema::drop('customer_groups_new');
    }
};
