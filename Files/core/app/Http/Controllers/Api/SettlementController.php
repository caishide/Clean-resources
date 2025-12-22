<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\SettlementService;
use App\Services\PVLedgerService;
use App\Services\PointsService;
use App\Repositories\BonusRepository;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

/**
 * 结算管理API控制器
 * 提供结算相关的查询和管理接口
 */
class SettlementController extends Controller
{
    protected SettlementService $settlementService;
    protected PVLedgerService $pvLedgerService;
    protected PointsService $pointsService;
    protected BonusRepository $bonusRepository;

    public function __construct(
        SettlementService $settlementService,
        PVLedgerService $pvLedgerService,
        PointsService $pointsService,
        BonusRepository $bonusRepository
    ) {
        $this->settlementService = $settlementService;
        $this->pvLedgerService = $pvLedgerService;
        $this->pointsService = $pointsService;
        $this->bonusRepository = $bonusRepository;
    }

    /**
     * 获取用户PV概览
     * GET /api/user/pv-summary
     */
    public function getUserPVSummary(Request $request): JsonResponse
    {
        $user = Auth::user();

        $summary = $this->pvLedgerService->getUserPVSummary($user->id);

        return response()->json([
            'status' => 'success',
            'data' => [
                'left_pv' => $summary['left_pv'] ?? 0,
                'right_pv' => $summary['right_pv'] ?? 0,
                'weak_pv' => min($summary['left_pv'] ?? 0, $summary['right_pv'] ?? 0),
                'this_week_left' => $summary['this_week_left'] ?? 0,
                'this_week_right' => $summary['this_week_right'] ?? 0,
            ]
        ]);
    }

    /**
     * 获取用户积分概览
     * GET /api/user/points-summary
     */
    public function getUserPointsSummary(Request $request): JsonResponse
    {
        $user = Auth::user();

        $summary = $this->pointsService->getUserPointsSummary($user->id);

        return response()->json([
            'status' => 'success',
            'data' => [
                'total_points' => $summary['total'] ?? 0,
                'a_class' => $summary['a_class'] ?? 0,
                'b_class' => $summary['b_class'] ?? 0,
                'c_class' => $summary['c_class'] ?? 0,
                'd_class' => $summary['d_class'] ?? 0,
            ]
        ]);
    }

    /**
     * 获取用户奖金历史
     * GET /api/user/bonus-history
     */
    public function getUserBonusHistory(Request $request): JsonResponse
    {
        $user = Auth::user();
        $startDate = $request->input('start_date', now()->subMonth()->toDateString());
        $endDate = $request->input('end_date', now()->toDateString());

        $directStats = $this->bonusRepository->getUserDirectBonusStats($user->id, $startDate, $endDate);
        $levelPairStats = $this->bonusRepository->getUserLevelPairBonusStats($user->id, $startDate, $endDate);

        return response()->json([
            'status' => 'success',
            'data' => [
                'period' => ['start' => $startDate, 'end' => $endDate],
                'direct_bonus' => $directStats,
                'level_pair_bonus' => $levelPairStats,
            ]
        ]);
    }

    /**
     * 获取待处理奖金列表
     * GET /api/user/pending-bonuses
     */
    public function getPendingBonuses(Request $request): JsonResponse
    {
        $user = Auth::user();

        $bonuses = $this->bonusRepository->getPendingBonuses([
            'recipient_id' => $user->id,
            'status' => 'pending'
        ]);

        return response()->json([
            'status' => 'success',
            'data' => $bonuses
        ]);
    }

    // ========== 管理员接口 ==========
    // 注意: 访问控制由 api.admin 中间件处理

    /**
     * 获取周结算列表
     * GET /api/admin/settlements
     */
    public function getSettlements(Request $request): JsonResponse
    {
        $settlements = $this->settlementService->getSettlementHistory(
            $request->input('page', 1),
            $request->input('per_page', 20)
        );

        return response()->json([
            'status' => 'success',
            'data' => $settlements
        ]);
    }

    /**
     * 执行周结算预演
     * POST /api/admin/settlements/dry-run
     */
    public function dryRunSettlement(Request $request): JsonResponse
    {
        $week = $request->input('week', now()->subWeek()->format('o-\WW'));

        $result = $this->settlementService->executeWeeklySettlement($week, true);

        return response()->json([
            'status' => 'success',
            'message' => '预演完成',
            'data' => $result
        ]);
    }

    /**
     * 执行周结算
     * POST /api/admin/settlements/execute
     */
    public function executeSettlement(Request $request): JsonResponse
    {
        $week = $request->input('week', now()->subWeek()->format('o-\WW'));

        // 先执行预演
        $preview = $this->settlementService->executeWeeklySettlement($week, true);

        // 需要确认
        if (!$request->input('confirmed', false)) {
            return response()->json([
                'status' => 'pending_confirmation',
                'message' => '请确认结算数据',
                'data' => $preview
            ]);
        }

        // 执行正式结算
        $result = $this->settlementService->executeWeeklySettlement($week, false);

        return response()->json([
            'status' => 'success',
            'message' => '结算完成',
            'data' => $result
        ]);
    }

    /**
     * 获取K值计算详情
     * GET /api/admin/settlements/{week}/k-factor
     */
    public function getKFactorDetails(Request $request, string $week): JsonResponse
    {
        $details = $this->settlementService->getKFactorDetails($week);

        return response()->json([
            'status' => 'success',
            'data' => $details
        ]);
    }

    /**
     * 批量释放待处理奖金
     * POST /api/admin/bonuses/release
     */
    public function releasePendingBonuses(Request $request): JsonResponse
    {
        $bonusIds = $request->input('bonus_ids', []);

        if (empty($bonusIds)) {
            return response()->json([
                'status' => 'error',
                'message' => '请选择要释放的奖金'
            ], 400);
        }

        $results = $this->bonusRepository->batchReleasePendingBonuses($bonusIds);

        return response()->json([
            'status' => 'success',
            'message' => '释放完成',
            'data' => $results
        ]);
    }
}
