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
        // Tags table - user-defined labels for organizing books
        Schema::create('tags', function (Blueprint $table) {
            $table->string('id', 16)->primary();
            $table->string('user_id', 16);
            $table->string('name', 50);
            $table->string('color', 7); // Hex color like #EF4444
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->unique(['user_id', 'name']); // Each user can have only one tag with a given name
            $table->index('user_id');
        });

        // Pivot table linking tags to user's books
        Schema::create('user_book_tags', function (Blueprint $table) {
            $table->id();
            $table->string('user_id', 16);
            $table->string('book_id', 16);
            $table->string('tag_id', 16);
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('book_id')->references('id')->on('books')->onDelete('cascade');
            $table->foreign('tag_id')->references('id')->on('tags')->onDelete('cascade');

            // Unique constraint: a tag can only be applied once to a user's book
            $table->unique(['user_id', 'book_id', 'tag_id']);

            // Indexes for common queries
            $table->index(['user_id', 'book_id']);
            $table->index(['user_id', 'tag_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_book_tags');
        Schema::dropIfExists('tags');
    }
};
