<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

/** POS PINs were plain text. They are hashed now, like passwords. This cannot be undone. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('pin')->nullable()->change();
        });

        foreach (DB::table('users')->whereNotNull('pin')->where('pin', '!=', '')->get(['id', 'pin']) as $user) {
            DB::table('users')->where('id', $user->id)->update(['pin' => Hash::make($user->pin)]);
        }
    }

    public function down(): void
    {
        // A hashed PIN cannot be turned back. Restore the backup if the plain PINs are needed.
    }
};
