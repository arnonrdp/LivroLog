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
        // Add interaction counts to activities table
        Schema::table('activities', function (Blueprint $table) {
            $table->unsignedInteger('likes_count')->default(0)->after('metadata');
            $table->unsignedInteger('comments_count')->default(0)->after('likes_count');
        });

        // Create activity_likes table
        Schema::create('activity_likes', function (Blueprint $table) {
            $table->string('id', 16)->primary();
            $table->string('user_id', 16);
            $table->string('activity_id', 16);
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('activity_id')->references('id')->on('activities')->onDelete('cascade');

            $table->unique(['user_id', 'activity_id']);
            $table->index(['activity_id', 'created_at']);
            $table->index('user_id');
        });

        // Create comments table
        Schema::create('comments', function (Blueprint $table) {
            $table->string('id', 16)->primary();
            $table->string('user_id', 16);
            $table->string('activity_id', 16);
            $table->text('content');
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('activity_id')->references('id')->on('activities')->onDelete('cascade');

            $table->index(['activity_id', 'created_at']);
            $table->index(['user_id', 'created_at']);
        });

        // Create notifications table
        Schema::create('notifications', function (Blueprint $table) {
            $table->string('id', 16)->primary();
            $table->string('user_id', 16);
            $table->string('actor_id', 16);
            $table->enum('type', [
                'activity_liked',
                'activity_commented',
                'follow_accepted',
            ]);
            $table->string('notifiable_type', 50);
            $table->string('notifiable_id', 16);
            $table->json('data')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('actor_id')->references('id')->on('users')->onDelete('cascade');

            $table->index(['user_id', 'read_at', 'created_at']);
            $table->index(['user_id', 'type']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('comments');
        Schema::dropIfExists('activity_likes');

        Schema::table('activities', function (Blueprint $table) {
            $table->dropColumn(['likes_count', 'comments_count']);
        });
    }
};
