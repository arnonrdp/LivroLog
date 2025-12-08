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
            // Amazon customer review data
            $table->decimal('amazon_rating', 2, 1)->nullable()->after('amazon_asin')
                ->comment('Amazon average star rating (1.0 to 5.0)');
            $table->unsignedInteger('amazon_rating_count')->nullable()->after('amazon_rating')
                ->comment('Number of Amazon customer reviews');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->dropColumn(['amazon_rating', 'amazon_rating_count']);
        });
    }
};
