<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users_books', function (Blueprint $table) {
            // Custom shelf order set by dragging; null until the user drags a book
            $table->unsignedInteger('position')->nullable()->after('reading_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users_books', function (Blueprint $table) {
            $table->dropColumn('position');
        });
    }
};
