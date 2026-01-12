<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // For MySQL: modify enum to include new types
        // For SQLite: recreate the table with new enum values
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE notifications MODIFY COLUMN type ENUM(
                'activity_liked',
                'activity_commented',
                'follow_accepted',
                'new_follower',
                'follow_request'
            ) NOT NULL");
        } else {
            // SQLite: Create new table with updated enum, migrate data, drop old table
            Schema::create('notifications_new', function (Blueprint $table) {
                $table->string('id', 16)->primary();
                $table->string('user_id', 16);
                $table->string('actor_id', 16);
                $table->enum('type', [
                    'activity_liked',
                    'activity_commented',
                    'follow_accepted',
                    'new_follower',
                    'follow_request',
                ]);
                $table->string('notifiable_type', 50);
                $table->string('notifiable_id', 16);
                $table->json('data')->nullable();
                $table->timestamp('read_at')->nullable();
                $table->timestamps();
            });

            // Copy data from old table
            DB::statement('INSERT INTO notifications_new SELECT * FROM notifications');

            // Drop old table
            Schema::drop('notifications');

            // Rename new table
            Schema::rename('notifications_new', 'notifications');

            // Re-add indexes
            Schema::table('notifications', function (Blueprint $table) {
                $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
                $table->foreign('actor_id')->references('id')->on('users')->onDelete('cascade');
                $table->index(['user_id', 'read_at', 'created_at']);
                $table->index(['user_id', 'type']);
                $table->index('created_at');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // For MySQL: revert enum to original types
        // For SQLite: would need similar recreation logic
        if (DB::connection()->getDriverName() === 'mysql') {
            // First delete any notifications with new types
            DB::table('notifications')->whereIn('type', ['new_follower', 'follow_request'])->delete();

            DB::statement("ALTER TABLE notifications MODIFY COLUMN type ENUM(
                'activity_liked',
                'activity_commented',
                'follow_accepted'
            ) NOT NULL");
        }
        // For SQLite tests, we typically use fresh migrations so no rollback needed
    }
};
