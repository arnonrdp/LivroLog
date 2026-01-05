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
        Schema::create('activities', function (Blueprint $table) {
            $table->string('id', 16)->primary();
            $table->string('user_id', 16);
            $table->enum('type', [
                'book_added',
                'book_started',
                'book_read',
                'review_written',
                'user_followed',
            ]);
            $table->string('subject_type', 50);
            $table->string('subject_id', 16);
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

            // Indexes for feed queries
            $table->index(['user_id', 'created_at']);
            $table->index(['user_id', 'type', 'created_at']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activities');
    }
};
