<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class BackupDatabase extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'backup:database {--max-backups=5 : Maximum number of backups to keep}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a backup of the database and manage backup rotation';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting database backup...');

        $timestamp = Carbon::now()->format('Y-m-d_H-i-s');
        $database = config('database.connections.' . config('database.default') . '.database');
        $backupFileName = "backup_{$database}_{$timestamp}.sql";
        $backupPath = storage_path("backups/{$backupFileName}");

        // Create backups directory if it doesn't exist
        if (!file_exists(storage_path('backups'))) {
            mkdir(storage_path('backups'), 0755, true);
        }

        // Build dump command (try mariadb-dump first, fallback to mysqldump)
        $connection = config('database.default');
        $host = config("database.connections.{$connection}.host");
        $port = config("database.connections.{$connection}.port");
        $username = config("database.connections.{$connection}.username");
        $password = config("database.connections.{$connection}.password");

        // Check if mariadb-dump is available, otherwise use mysqldump
        exec('which mariadb-dump', $output, $returnCode);
        $dumpCommand = ($returnCode === 0) ? 'mariadb-dump' : 'mysqldump';

        $command = sprintf(
            '%s --single-transaction --routines --triggers --host=%s --port=%s --user=%s --password=%s %s > %s 2>&1',
            $dumpCommand,
            escapeshellarg($host),
            escapeshellarg($port),
            escapeshellarg($username),
            escapeshellarg($password),
            escapeshellarg($database),
            escapeshellarg($backupPath)
        );

        // Execute backup
        exec($command, $output, $returnCode);

        if ($returnCode !== 0) {
            $this->error('Backup failed!');
            $this->error('Output: ' . implode("\n", $output));
            return 1;
        }

        // Compress backup
        $compressedPath = $backupPath . '.gz';
        exec("gzip {$backupPath}", $gzipOutput, $gzipReturn);

        if ($gzipReturn !== 0) {
            $this->warn('Backup created but compression failed');
            $finalPath = $backupPath;
        } else {
            $finalPath = $compressedPath;
        }

        $fileSize = $this->formatBytes(filesize($finalPath));
        $this->info("Backup created successfully: {$backupFileName}");
        $this->info("File size: {$fileSize}");

        // Clean up old backups
        $maxBackups = $this->option('max-backups');
        $this->cleanupOldBackups($maxBackups);

        return 0;
    }

    /**
     * Clean up old backup files, keeping only the specified number
     */
    private function cleanupOldBackups(int $maxBackups): void
    {
        $backupDir = storage_path('backups');
        $backups = glob($backupDir . '/backup_*.sql*');

        if (count($backups) <= $maxBackups) {
            return;
        }

        // Sort by modification time (newest first)
        usort($backups, function($a, $b) {
            return filemtime($b) - filemtime($a);
        });

        // Remove old backups
        $backupsToDelete = array_slice($backups, $maxBackups);
        foreach ($backupsToDelete as $backup) {
            unlink($backup);
        }

        $deletedCount = count($backupsToDelete);
        $this->info("Cleaned up {$deletedCount} old backup(s). Keeping last {$maxBackups} backups.");
    }

    /**
     * Format bytes to human readable format
     */
    private function formatBytes(int $size): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        
        for ($i = 0; $size >= 1024 && $i < count($units) - 1; $i++) {
            $size /= 1024;
        }
        
        return round($size, 2) . ' ' . $units[$i];
    }
}
