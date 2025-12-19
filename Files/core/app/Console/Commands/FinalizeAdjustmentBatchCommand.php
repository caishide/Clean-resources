<?php

namespace App\Console\Commands;

use App\Services\AdjustmentService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class FinalizeAdjustmentBatchCommand extends Command
{
    protected $signature = 'adjustment:finalize {batch_id}';
    protected $description = 'Finalize a refund/adjustment batch and generate reversal entries';

    public function handle(AdjustmentService $service): int
    {
        $batchId = (int) $this->argument('batch_id');
        try {
            $service->finalizeAdjustmentBatch($batchId);
            $this->info("Batch {$batchId} finalized successfully.");
            return Command::SUCCESS;
        } catch (\Exception $e) {
            Log::error('FinalizeAdjustmentBatch failed', ['batch_id' => $batchId, 'error' => $e->getMessage()]);
            $this->error('Failed: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
