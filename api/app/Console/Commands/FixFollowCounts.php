<?php

namespace App\Console\Commands;

use App\Services\FollowService;
use Illuminate\Console\Command;

class FixFollowCounts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'users:fix-follow-counts
                            {--user-id= : Fix counts for a specific user ID}
                            {--dry-run : Show what would be updated without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Recalculate and fix user follow counts';

    protected FollowService $followService;

    public function __construct(FollowService $followService)
    {
        parent::__construct();
        $this->followService = $followService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting follow counts reconciliation...');

        $userId = $this->option('user-id');
        $isDryRun = $this->option('dry-run');

        if ($isDryRun) {
            $this->warn('DRY RUN MODE - No changes will be made');
        }

        try {
            if ($userId) {
                $user = \App\Models\User::find($userId);
                if (! $user) {
                    $this->error("User with ID '{$userId}' not found.");

                    return Command::FAILURE;
                }

                $this->info("Fixing counts for user: {$user->display_name} ({$user->username})");

                if ($isDryRun) {
                    $this->showUserCountDiff($user);
                } else {
                    $result = $this->followService->recalculateFollowCounts($user);
                    $this->displayResult($result);
                }
            } else {
                $this->info('Fixing counts for all users...');

                if ($isDryRun) {
                    $this->showAllUsersCountDiff();
                } else {
                    $result = $this->followService->recalculateFollowCounts();
                    $this->displayResult($result);
                }
            }

            $this->newLine();
            $this->info('Follow counts reconciliation completed!');

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('Error during reconciliation: '.$e->getMessage());

            return Command::FAILURE;
        }
    }

    /**
     * Show count differences for a specific user without making changes.
     */
    private function showUserCountDiff(\App\Models\User $user): void
    {
        $actualFollowersCount = $user->followers()->count();
        $actualFollowingCount = $user->following()->count();

        $this->table(['Field', 'Current', 'Actual', 'Difference'], [
            [
                'Followers',
                $user->followers_count,
                $actualFollowersCount,
                $actualFollowersCount - $user->followers_count,
            ],
            [
                'Following',
                $user->following_count,
                $actualFollowingCount,
                $actualFollowingCount - $user->following_count,
            ],
        ]);

        if ($user->followers_count !== $actualFollowersCount || $user->following_count !== $actualFollowingCount) {
            $this->warn('This user needs count updates.');
        } else {
            $this->info('This user counts are already correct.');
        }
    }

    /**
     * Show count differences for all users without making changes.
     */
    private function showAllUsersCountDiff(): void
    {
        $this->info('Scanning all users for count inconsistencies...');

        $inconsistentUsers = [];
        $totalUsers = 0;

        \App\Models\User::chunk(100, function ($users) use (&$inconsistentUsers, &$totalUsers) {
            foreach ($users as $user) {
                $totalUsers++;

                $actualFollowersCount = $user->followers()->count();
                $actualFollowingCount = $user->following()->count();

                if ($user->followers_count !== $actualFollowersCount || $user->following_count !== $actualFollowingCount) {
                    $inconsistentUsers[] = [
                        'User' => "{$user->display_name} ({$user->username})",
                        'Followers (Current/Actual)' => "{$user->followers_count}/{$actualFollowersCount}",
                        'Following (Current/Actual)' => "{$user->following_count}/{$actualFollowingCount}",
                    ];
                }
            }
        });

        $this->info("Scanned {$totalUsers} users.");

        if (! empty($inconsistentUsers)) {
            $this->warn(count($inconsistentUsers).' users have inconsistent counts:');
            $this->table(['User', 'Followers (Current/Actual)', 'Following (Current/Actual)'], $inconsistentUsers);
        } else {
            $this->info('All user counts are consistent!');
        }
    }

    /**
     * Display the result of the reconciliation.
     */
    private function displayResult(array $result): void
    {
        if ($result['success']) {
            if ($result['updated_count'] > 0) {
                $this->info($result['message']);
            } else {
                $this->info('All follow counts were already correct.');
            }
        } else {
            $this->error('Reconciliation failed: '.($result['message'] ?? 'Unknown error'));
        }
    }
}
