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
        Schema::create('users', function (Blueprint $table) {
            $table->string('id', 16)->primary();
            $table->string('google_id')->nullable()->unique();
            $table->string('display_name');
            $table->string('email')->unique();
            $table->string('username', 100)->unique();
            $table->string('avatar')->nullable();
            $table->string('shelf_name')->nullable();
            $table->string('locale', 10)->nullable();
            $table->enum('role', ['admin', 'user'])->default('user');
            $table->timestamp('modified_at')->useCurrent();
            $table->timestamp('email_verified_at')->nullable();
            $table->boolean('email_verified')->default(false);
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('books', function (Blueprint $table) {
            $table->string('id', 16)->primary();
            $table->string('isbn', 64)->unique()->nullable();
            $table->string('google_id')->nullable()->unique();
            $table->json('industry_identifiers')->nullable(); // all ISBNs
            $table->string('title');
            $table->string('subtitle')->nullable();
            $table->string('authors')->nullable();
            $table->text('description')->nullable();
            $table->string('thumbnail', 512)->nullable();
            $table->string('language', 10)->default('pt-BR');
            $table->string('publisher')->nullable();
            $table->date('published_date')->nullable();
            $table->integer('page_count')->nullable();
            $table->string('format')->nullable(); // hardcover, paperback, ebook, audiobook
            $table->string('print_type')->nullable(); // BOOK, MAGAZINE
            $table->decimal('height', 8, 2)->nullable();
            $table->decimal('width', 8, 2)->nullable();
            $table->decimal('thickness', 8, 2)->nullable();
            $table->string('maturity_rating')->nullable(); // NOT_MATURE, MATURE
            $table->json('categories')->nullable();
            $table->enum('info_quality', ['basic', 'enhanced', 'complete'])->default('basic');
            $table->timestamp('enriched_at')->nullable();
            $table->string('edition', 50)->nullable();
            $table->timestamps();

            // Índices para busca
            $table->index('published_date');
            $table->index('page_count');
            $table->index('format');
            $table->index('google_id');
        });

        Schema::create('users_books', function (Blueprint $table) {
            $table->id();
            $table->string('user_id', 16);
            $table->string('book_id', 16);
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('book_id')->references('id')->on('books')->onDelete('cascade');
            $table->timestamp('added_at')->useCurrent();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });

        Schema::create('related_books', function (Blueprint $table) {
            $table->id();
            $table->string('book_id', 16);
            $table->string('related_book_id', 16);
            $table->foreign('book_id')->references('id')->on('books')->onDelete('cascade');
            $table->foreign('related_book_id')->references('id')->on('books')->onDelete('cascade');
            $table->timestamps();
        });

        Schema::create('authors', function (Blueprint $table) {
            $table->string('id', 16)->primary();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('author_book', function (Blueprint $table) {
            $table->id();
            $table->string('book_id', 16);
            $table->string('author_id', 16);
            $table->foreign('book_id')->references('id')->on('books')->onDelete('cascade');
            $table->foreign('author_id')->references('id')->on('authors')->onDelete('cascade');
            $table->timestamps();
        });

        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('tokenable_id', 16);
            $table->string('tokenable_type');
            $table->index(['tokenable_id', 'tokenable_type']);
            $table->text('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('personal_access_tokens');
        Schema::dropIfExists('author_book');
        Schema::dropIfExists('authors');
        Schema::dropIfExists('related_books');
        Schema::dropIfExists('users_books');
        Schema::dropIfExists('books');
        Schema::dropIfExists('users');
    }
};
