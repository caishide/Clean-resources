<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdjustmentBatch;
use App\Models\AdjustmentEntry;
use App\Services\AdjustmentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Models\AuditLog;

class AdjustmentBatchController extends Controller
{
    public function index(Request $request)
    {
        $pageTitle = 'Adjustment Batches';
        $status = $request->input('status');
        $query = AdjustmentBatch::query()->orderByDesc('id');
        if ($status === 'pending') {
            $query->whereNull('finalized_at');
        } elseif ($status === 'finalized') {
            $query->whereNotNull('finalized_at');
        }
        $batches = $query->paginate(20);
        return view('admin.adjustment.batches', compact('pageTitle', 'batches', 'status'));
    }

    public function show($id)
    {
        $pageTitle = 'Adjustment Batch Details';
        $batch = AdjustmentBatch::findOrFail($id);
        $entries = AdjustmentEntry::where('batch_id', $id)->orderByDesc('id')->paginate(50);
        return view('admin.adjustment.show', compact('pageTitle', 'batch', 'entries'));
    }

    public function finalize($id, AdjustmentService $service)
    {
        $batch = AdjustmentBatch::findOrFail($id);
        if ($batch->finalized_at) {
            $notify[] = ['error', __('admin.adjustment.batch_already_finalized')];
            return back()->withNotify($notify);
        }
        try {
            $service->finalizeAdjustmentBatch($id);
            $batch->finalized_by = auth('admin')->id();
            $batch->save();
            Log::info('Adjustment batch finalized by admin', ['batch_id' => $id, 'admin_id' => auth('admin')->id()]);
            AuditLog::create([
                'admin_id' => auth('admin')->id(),
                'action_type' => 'adjustment_finalize',
                'entity_type' => 'adjustment_batch',
                'entity_id' => $id,
                'meta' => ['batch_key' => $batch->batch_key],
            ]);
            $notify[] = ['success', __('admin.adjustment.batch_finalized')];
            return back()->withNotify($notify);
        } catch (\Exception $e) {
            Log::error('Finalize batch failed', ['batch_id' => $id, 'error' => $e->getMessage()]);
            $notify[] = ['error', $e->getMessage()];
            return back()->withNotify($notify);
        }
    }
}
