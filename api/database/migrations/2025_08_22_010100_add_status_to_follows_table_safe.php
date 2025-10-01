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
        // Check if status column already exists
        if (! Schema::hasColumn('follows', 'status')) {
            Schema::table('follows', function (Blueprint $table) {
                $table->enum('status', ['pending', 'accepted'])->default('accepted')->after('followed_id');
            });
        }

        // Add missing columns to users_books if they don't exist
        if (! Schema::hasColumn('users_books', 'is_private')) {
            Schema::table('users_books', function (Blueprint $table) {
                $table->boolean('is_private')->default(false)->after('read_at');
            });
        }

        if (! Schema::hasColumn('users_books', 'reading_status')) {
            Schema::table('users_books', function (Blueprint $table) {
                $table->enum('reading_status', ['want_to_read', 'reading', 'read', 'abandoned', 'on_hold', 're_reading'])
                    ->default('read')
                    ->after('is_private');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('follows', 'status')) {
            Schema::table('follows', function (Blueprint $table) {
                $table->dropColumn('status');
            });
        }

        if (Schema::hasColumn('users_books', 'reading_status')) {
            Schema::table('users_books', function (Blueprint $table) {
                $table->dropColumn('reading_status');
            });
        }

        if (Schema::hasColumn('users_books', 'is_private')) {
            Schema::table('users_books', function (Blueprint $table) {
                $table->dropColumn('is_private');
            });
        }
    }
};
