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
        Schema::table('books', function (Blueprint $table) {
            // Amazon integration fields (ordered logically)
            $table->string('amazon_asin', 20)->nullable()->after('isbn');
            $table->enum('asin_status', ['pending', 'processing', 'completed', 'failed'])
                  ->default('pending')
                  ->after('amazon_asin');
            $table->timestamp('asin_processed_at')->nullable()->after('asin_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->dropColumn(['amazon_asin', 'asin_status', 'asin_processed_at']);
        });
    }
};
