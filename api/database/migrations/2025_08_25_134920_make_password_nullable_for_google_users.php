<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // First, make the password column nullable
        Schema::table('users', function (Blueprint $table) {
            $table->string('password')->nullable()->change();
        });

        // Then, update all Google users to have NULL passwords
        // This identifies users who signed up via Google and never set a real password
        DB::table('users')
            ->whereNotNull('google_id')
            ->update(['password' => null]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // First, generate random passwords for users with NULL passwords
        // (to satisfy the NOT NULL constraint we're reverting to)
        $usersWithNullPasswords = DB::table('users')
            ->whereNull('password')
            ->pluck('id');

        foreach ($usersWithNullPasswords as $userId) {
            DB::table('users')
                ->where('id', $userId)
                ->update(['password' => bcrypt(\Illuminate\Support\Str::random(32))]);
        }

        // Then, make the password column non-nullable again
        Schema::table('users', function (Blueprint $table) {
            $table->string('password')->nullable(false)->change();
        });
    }
};
