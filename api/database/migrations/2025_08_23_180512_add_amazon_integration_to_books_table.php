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
            if (! Schema::hasColumn('books', 'amazon_asin')) {
                $table->string('amazon_asin', 20)->nullable()->after('isbn');
            }
            if (! Schema::hasColumn('books', 'asin_status')) {
                $table->enum('asin_status', ['pending', 'processing', 'completed', 'failed'])
                    ->default('pending')
                    ->after('amazon_asin');
            }
            if (! Schema::hasColumn('books', 'asin_processed_at')) {
                $table->timestamp('asin_processed_at')->nullable()->after('asin_status');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $columnsToDropSQL = collect(['amazon_asin', 'asin_status', 'asin_processed_at'])
                ->filter(fn ($column) => Schema::hasColumn('books', $column))
                ->toArray();

            if (! empty($columnsToDropSQL)) {
                $table->dropColumn($columnsToDropSQL);
            }
        });
    }
};
