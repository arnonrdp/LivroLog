<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // First, convert existing timestamp values to date only
        DB::statement("UPDATE users_books SET read_at = DATE(read_at) WHERE read_at IS NOT NULL");
        
        // Then change the column type from timestamp to date
        Schema::table('users_books', function (Blueprint $table) {
            $table->date('read_at')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users_books', function (Blueprint $table) {
            $table->timestamp('read_at')->nullable()->change();
        });
    }
};
