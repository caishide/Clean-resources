<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * CleanupExpiredSessions - Command to clean up expired sessions
 *
 * Removes expired sessions and inactive user sessions
 * to improve application performance and security.
 */
class CleanupExpiredSessions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sessions:cleanup {--days=7 : Number of days after which to consider sessions expired}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up expired and inactive sessions';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Starting session cleanup...');

        $days = $this->option('days');
        $expiredDate = now()->subDays($days);

        // Clean up database sessions
        $dbDeleted = DB::table('sessions')
            ->where('last_activity', '<', $expiredDate->timestamp)
            ->delete();

        $this->info("Deleted {$dbDeleted} expired database sessions.");

        // Clean up file-based sessions
        $filesDeleted = $this->cleanupFileSessions($expiredDate);
        $this->info("Deleted {$filesDeleted} expired file-based sessions.");

        // Log cleanup activity
        Log::channel('application')->info('Session cleanup completed', [
            'db_sessions_deleted' => $dbDeleted,
            'file_sessions_deleted' => $filesDeleted,
            'expired_before' => $expiredDate->toISOString(),
        ]);

        $this->info('Session cleanup completed successfully.');
        return self::SUCCESS;
    }

    /**
     * Clean up file-based sessions
     */
    private function cleanupFileSessions($expiredDate): int
    {
        $sessionPath = storage_path('framework/sessions');
        $deleted = 0;

        if (!is_dir($sessionPath)) {
            return 0;
        }

        $files = glob($sessionPath . '/*');
        foreach ($files as $file) {
            if (is_file($file)) {
                $modifiedTime = filemtime($file);
                if ($modifiedTime < $expiredDate->timestamp) {
                    unlink($file);
                    $deleted++;
                }
            }
        }

        return $deleted;
    }
}
