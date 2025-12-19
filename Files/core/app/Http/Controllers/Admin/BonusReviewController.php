<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PendingBonus;
use App\Repositories\BonusRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Models\AuditLog;

class BonusReviewController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->input('status', 'pending');
        $releaseMode = $request->input('release_mode', 'manual');

        $bonuses = PendingBonus::with('recipient:id,username,email')
            ->when($status, fn($q) => $q->where('status', $status))
            ->when($releaseMode, fn($q) => $q->where('release_mode', $releaseMode))
            ->orderByDesc('id')
            ->paginate(20);

        return view('admin.bonus.pending', compact('bonuses', 'status', 'releaseMode'));
    }

    public function approve(Request $request, BonusRepository $repo)
    {
        $ids = (array) $request->input('bonus_ids', []);
        if (empty($ids)) {
            $notify[] = ['error', __('admin.bonus_review.select_pending_bonuses')];
            return back()->withNotify($notify);
        }

        $results = $repo->batchReleasePendingBonuses($ids);
        $success = collect($results)->where('status', 'success')->count();
        $failed = collect($results)->where('status', 'failed')->count();

        Log::info('Pending bonuses approved', ['ids' => $ids, 'results' => $results]);
        AuditLog::create([
            'admin_id' => auth('admin')->id(),
            'action_type' => 'pending_bonus_approve',
            'entity_type' => 'pending_bonus',
            'entity_id' => implode(',', $ids),
            'meta' => $results,
        ]);

        $notify[] = ['success', __('admin.bonus_review.released_success_failed', ['success' => $success, 'failed' => $failed])];
        return back()->withNotify($notify);
    }

    public function reject($id)
    {
        $bonus = PendingBonus::find($id);
        if (!$bonus || $bonus->status !== 'pending') {
            $notify[] = ['error', __('admin.bonus_review.record_not_exists_or_processed')];
            return back()->withNotify($notify);
        }
        $bonus->status = 'rejected';
        $bonus->save();

        Log::info('Pending bonus rejected', ['id' => $id]);
        AuditLog::create([
            'admin_id' => auth('admin')->id(),
            'action_type' => 'pending_bonus_reject',
            'entity_type' => 'pending_bonus',
            'entity_id' => $bonus->id,
            'meta' => ['bonus_type' => $bonus->bonus_type, 'amount' => $bonus->amount],
        ]);
        $notify[] = ['success', __('admin.bonus_review.rejected')];
        return back()->withNotify($notify);
    }
}
