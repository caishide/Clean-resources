<?php

namespace App\Console\Commands;

use App\Models\Transaction;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class NormalizeTransactionDetails extends Command
{
    protected $signature = 'transactions:normalize-details {--chunk=500} {--dry-run}';

    protected $description = 'Normalize transactions.details to JSON objects (text fallback)';

    public function handle(): int
    {
        $chunk = max(1, (int) $this->option('chunk'));
        $dryRun = (bool) $this->option('dry-run');

        $total = Transaction::query()->whereNotNull('details')->count();
        $updated = 0;
        $skipped = 0;

        $this->info("待处理记录数: {$total}");

        Transaction::query()
            ->select('id', 'details')
            ->whereNotNull('details')
            ->orderBy('id')
            ->chunkById($chunk, function ($rows) use (&$updated, &$skipped, $dryRun) {
                foreach ($rows as $row) {
                    $normalized = $this->normalizeDetails($row->details);

                    if ($normalized === null || $normalized === $row->details) {
                        $skipped++;
                        continue;
                    }

                    if (!$dryRun) {
                        DB::table('transactions')
                            ->where('id', $row->id)
                            ->update(['details' => $normalized]);
                    }
                    $updated++;
                }
            });

        $suffix = $dryRun ? ' (dry-run)' : '';
        $this->info("更新: {$updated}，跳过: {$skipped}{$suffix}");

        return self::SUCCESS;
    }

    private function normalizeDetails($value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_array($value) || is_object($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE);
        }

        if (!is_string($value)) {
            return $this->wrapText((string) $value);
        }

        $trimmed = trim($value);
        if ($trimmed === '') {
            return $this->wrapText('');
        }

        $decoded = json_decode($trimmed, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return $this->wrapText($value);
        }

        if (is_string($decoded)) {
            return $this->wrapText($decoded);
        }

        if (is_array($decoded)) {
            return $trimmed;
        }

        if (is_scalar($decoded) || $decoded === null) {
            return $this->wrapText((string) $decoded);
        }

        return $trimmed;
    }

    private function wrapText(string $text): string
    {
        return json_encode(['text' => $text], JSON_UNESCAPED_UNICODE);
    }
}
