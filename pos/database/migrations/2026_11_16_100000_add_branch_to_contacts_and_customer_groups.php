<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Contacts and customer groups belong to a branch (branch_id), or to all branches (null).
 * A group's name only has to be different within its branch, so the old unique index on name goes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->foreignId('branch_id')->nullable()->after('type')->constrained('branches')->nullOnDelete();
        });

        Schema::table('customer_groups', function (Blueprint $table) {
            $table->dropUnique('customer_groups_name_unique');
            $table->foreignId('branch_id')->nullable()->after('name')->constrained('branches')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('customer_groups', function (Blueprint $table) {
            $table->dropConstrainedForeignId('branch_id');
            $table->unique('name');
        });

        Schema::table('contacts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('branch_id');
        });
    }
};
