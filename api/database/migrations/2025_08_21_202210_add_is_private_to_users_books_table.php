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
            $table->boolean('is_private')->default(false)->after('read_at');
            $table->enum('reading_status', ['want_to_read', 'reading', 'read', 'abandoned', 'on_hold', 're_reading'])
                ->default('read')
                ->after('is_private');
        });

        Schema::table('follows', function (Blueprint $table) {
            $table->enum('status', ['pending', 'accepted'])->default('accepted')->after('followed_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users_books', function (Blueprint $table) {
            $table->dropColumn(['is_private', 'reading_status']);
        });

        Schema::table('follows', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
