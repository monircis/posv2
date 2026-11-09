<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Users, roles and permissions moved to the auth service (auth/, database pos_v2_auth).
 * This removes the old copies from the POS database. On a new install the tables
 * were never made, so nothing happens.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();

        Schema::dropIfExists('role_has_permissions');
        Schema::dropIfExists('model_has_roles');
        Schema::dropIfExists('model_has_permissions');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('users');

        Schema::enableForeignKeyConstraints();
    }

    public function down(): void
    {
        // Not undone: the users and roles are kept in the auth service.
    }
};
