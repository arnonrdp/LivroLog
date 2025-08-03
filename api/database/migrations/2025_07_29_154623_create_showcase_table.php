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
        Schema::create('showcase', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('authors')->nullable();
            $table->string('isbn')->nullable();
            $table->text('description')->nullable();
            $table->string('thumbnail')->nullable();
            $table->string('link')->nullable(); // Link for purchase/more information
            $table->string('publisher')->nullable();
            $table->string('language', 10)->default('pt-BR');
            $table->string('edition')->nullable();
            $table->integer('order_index')->default(0); // To control display order
            $table->boolean('is_active')->default(true); // To enable/disable
            $table->text('notes')->nullable(); // Internal notes about why it's in the showcase
            $table->timestamps();

            // Indexes
            $table->index(['is_active', 'order_index']);
            $table->index('isbn');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('showcase');
    }
};
