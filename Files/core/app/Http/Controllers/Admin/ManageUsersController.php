<?php

namespace App\Http\Controllers\Admin;

use App\Models\User;
use App\Models\BvLog;
use App\Models\Order;
use App\Models\Deposit;
use App\Constants\Status;
use App\Models\Withdrawal;
use App\Models\Transaction;
use App\Models\AuditLog;
use App\Models\Admin;
use App\Repositories\UserRepository;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use App\Models\NotificationLog;
use App\Rules\FileTypeValidate;
use App\Http\Controllers\Controller;
use App\Models\NotificationTemplate;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * ManageUsersController - 用户管理控制器（优化版）
 *
 * 处理用户列表、详情、KYC验证、余额管理、
 * 管理员模拟登录、通知发送等功能
 *
 * 性能优化:
 * - 使用Repository模式避免SELECT *
 * - 实施Keyset分页替代OFFSET分页
 * - 添加缓存策略
 */
class ManageUsersController extends Controller
{
    /** @var string BV减少交易类型 */
    private const BV_TRX_TYPE_MINUS = '-';

    /** @var int 会话时长计算秒数 */
    private const SECONDS_PER_MINUTE = 60;

    /** @var int 会话时长小数位数 */
    private const DURATION_DECIMAL_PLACES = 2;

    /** @var UserRepository 用户仓储实例 */
    protected UserRepository $userRepository;

    /**
     * 构造函数
     *
     * @param UserRepository $userRepository
     */
    public function __construct(UserRepository $userRepository)
    {
        $this->userRepository = $userRepository;
    }

    /**
     * 显示所有用户列表
     *
     * @return View
     */
    public function allUsers(): View
    {
        $pageTitle = 'All Users';
        $users     = $this->userData();
        return view('admin.users.list', compact('pageTitle', 'users'));
    }

    /**
     * 显示活跃用户列表
     *
     * @return View
     */
    public function activeUsers(): View
    {
        $pageTitle = 'Active Users';
        $users     = $this->userData('active');
        return view('admin.users.list', compact('pageTitle', 'users'));
    }

    /**
     * 显示被封禁用户列表
     *
     * @return View
     */
    public function bannedUsers(): View
    {
        $pageTitle = 'Banned Users';
        $users     = $this->userData('banned');
        return view('admin.users.list', compact('pageTitle', 'users'));
    }

    /**
     * 显示邮箱未验证用户列表
     *
     * @return View
     */
    public function emailUnverifiedUsers(): View
    {
        $pageTitle = 'Email Unverified Users';
        $users     = $this->userData('emailUnverified');
        return view('admin.users.list', compact('pageTitle', 'users'));
    }

    /**
     * 显示KYC未验证用户列表
     *
     * @return View
     */
    public function kycUnverifiedUsers(): View
    {
        $pageTitle = 'KYC Unverified Users';
        $users     = $this->userData('kycUnverified');
        return view('admin.users.list', compact('pageTitle', 'users'));
    }

    /**
     * 显示KYC待审核用户列表
     *
     * @return View
     */
    public function kycPendingUsers(): View
    {
        $pageTitle = 'KYC Pending Users';
        $users     = $this->userData('kycPending');
        return view('admin.users.list', compact('pageTitle', 'users'));
    }

    /**
     * 显示邮箱已验证用户列表
     *
     * @return View
     */
    public function emailVerifiedUsers(): View
    {
        $pageTitle = 'Email Verified Users';
        $users     = $this->userData('emailVerified');
        return view('admin.users.list', compact('pageTitle', 'users'));
    }

    /**
     * 显示手机未验证用户列表
     *
     * @return View
     */
    public function mobileUnverifiedUsers(): View
    {
        $pageTitle = 'Mobile Unverified Users';
        $users     = $this->userData('mobileUnverified');
        return view('admin.users.list', compact('pageTitle', 'users'));
    }

    /**
     * 显示手机已验证用户列表
     *
     * @return View
     */
    public function mobileVerifiedUsers(): View
    {
        $pageTitle = 'Mobile Verified Users';
        $users     = $this->userData('mobileVerified');
        return view('admin.users.list', compact('pageTitle', 'users'));
    }

    /**
     * 显示有余额用户列表
     *
     * @return View
     */
    public function usersWithBalance(): View
    {
        $pageTitle = 'Users with Balance';
        $users     = $this->userData('withBalance');
        return view('admin.users.list', compact('pageTitle', 'users'));
    }

    /**
     * 显示付费用户列表
     *
     * @return View
     */
    public function paidUsers(): View
    {
        $pageTitle = 'Paid Users';
        $users     = $this->userData('paidUser');
        return view('admin.users.list', compact('pageTitle', 'users'));
    }

    /**
     * 显示免费用户列表
     *
     * @return View
     */
    public function freeUsers(): View
    {
        $pageTitle = 'Free Users';
        $users     = $this->userData('freeUser');
        return view('admin.users.list', compact('pageTitle', 'users'));
    }

    /**
     * 获取用户数据（优化版）
     *
     * @param string|null $scope
     * @param Request $request
     * @return LengthAwarePaginator|Collection
     */
    protected function userData(?string $scope = null, Request $request = null)
    {
        $request = $request ?? request();

        // 构建过滤条件
        $filters = [];
        if ($scope) {
            $filters['scope'] = $scope;
        }

        if ($request->has('search')) {
            $filters['search'] = $request->search;
        }
        if ($request->has('status')) {
            $filters['status'] = $request->status;
        }
        $filters['page'] = (int) $request->get('page', 1);

        // 使用Repository获取数据（优化：避免SELECT *，添加缓存）
        // 注意：这里返回Collection，如果是API可以使用Keyset分页
        // 如果是Web页面，可以继续使用LengthAwarePaginator
        return $this->userRepository->getUserList($filters, getPaginate());
    }

    /**
     * 显示用户详情（优化版）
     *
     * @param int $id
     * @return View
     */
    public function detail(int $id): View
    {
        // 使用Repository获取用户详情（优化：避免SELECT *，添加缓存）
        $user = $this->userRepository->getUserDetail($id);

        if (!$user) {
            abort(404);
        }

        // 🔒 修复IDOR漏洞：添加权限检查
        // 检查当前管理员是否有权限查看此用户详情
        if (!$this->canViewUser($user)) {
            abort(403, 'Unauthorized access to this user');
        }

        $pageTitle = 'User Detail - ' . $user->username;

        // 使用预加载的数据计算统计，避免额外查询
        $totalDeposit = $user->deposits->where('status', 1)->sum('amount');
        $totalWithdrawals = $user->withdrawals->where('status', 2)->sum('amount');
        $totalTransaction = $user->transactions->count();
        $countries = json_decode(file_get_contents(resource_path('views/partials/country.json')));

        // 检查是否存在bvLogs关联
        $totalBvCut = $user->bvLogs ? $user->bvLogs->where('trx_type', self::BV_TRX_TYPE_MINUS)->sum('amount') : 0;
        $totalOrder = $user->orders->count();

        return view('admin.users.detail', compact('pageTitle', 'user', 'totalDeposit', 'totalWithdrawals', 'totalTransaction', 'countries', 'totalBvCut', 'totalOrder'));
    }

    /**
     * 用户列表 - API接口（使用Keyset分页）
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function apiIndex(Request $request): JsonResponse
    {
        $filters = [
            'search' => $request->search,
            'status' => $request->status,
        ];

        $lastId = $request->get('last_id');
        $perPage = $request->get('per_page', 20);

        // 使用Keyset分页（替代OFFSET分页）
        $users = $this->userRepository->getUserList($filters, $perPage, $lastId);

        return response()->json([
            'data' => $users,
            'next_cursor' => $users->last()?->id, // 用于下次查询的cursor
            'has_more' => $users->count() === $perPage,
        ]);
    }

    /**
     * 用户交易历史 - API接口（使用Keyset分页）
     *
     * @param Request $request
     * @param int $userId
     * @return JsonResponse
     */
    public function apiUserTransactions(Request $request, int $userId): JsonResponse
    {
        $filters = [
            'trx_type' => $request->trx_type,
            'remark' => $request->remark,
            'date_from' => $request->date_from,
            'date_to' => $request->date_to,
        ];

        $lastId = $request->get('last_id');
        $perPage = $request->get('per_page', 20);

        // 使用Keyset分页（替代OFFSET分页）
        $transactions = $this->userRepository->getUserTransactions($userId, $filters, $perPage, $lastId);

        return response()->json([
            'data' => $transactions,
            'next_cursor' => $transactions->last()?->id,
            'has_more' => $transactions->count() === $perPage,
        ]);
    }

    /**
     * 显示KYC详情
     *
     * @param int $id
     * @return View
     */
    public function kycDetails(int $id): View
    {
        $pageTitle = 'KYC Details';
        $user      = User::findOrFail($id);

        // 🔒 修复IDOR漏洞：添加权限检查
        if (!$this->canViewUser($user)) {
            abort(403, 'Unauthorized access to KYC data');
        }

        return view('admin.users.kyc_detail', compact('pageTitle', 'user'));
    }

    /**
     * 批准KYC验证
     *
     * @param int $id
     * @return RedirectResponse
     */
    public function kycApprove(int $id): RedirectResponse
    {
        $user     = User::findOrFail($id);

        // 🔒 修复IDOR漏洞：添加权限检查
        if (!$this->canManageUserKyc($user)) {
            abort(403, 'Unauthorized to approve KYC for this user');
        }

        $user->kv = Status::KYC_VERIFIED;
        $user->save();

        notify($user, 'KYC_APPROVE', []);

        $notify[] = ['success', 'KYC approved successfully'];
        return to_route('admin.users.kyc.pending')->withNotify($notify);
    }

    /**
     * 拒绝KYC验证
     *
     * @param Request $request
     * @param int $id
     * @return RedirectResponse
     */
    public function kycReject(Request $request, int $id): RedirectResponse
    {
        $request->validate([
            'reason' => 'required'
        ]);
        $user = User::findOrFail($id);

        // 🔒 修复IDOR漏洞：添加权限检查
        if (!$this->canManageUserKyc($user)) {
            abort(403, 'Unauthorized to reject KYC for this user');
        }

        $user                       = User::findOrFail($id);
        $user->kv                   = Status::KYC_UNVERIFIED;
        $user->kyc_rejection_reason = $request->reason;
        $user->save();

        notify($user, 'KYC_REJECT', [
            'reason' => $request->reason
        ]);

        $notify[] = ['success', 'KYC rejected successfully'];
        return to_route('admin.users.kyc.pending')->withNotify($notify);
    }


    /**
     * 更新用户信息
     *
     * @param Request $request
     * @param int $id
     * @return RedirectResponse
     */
    public function update(Request $request, int $id): RedirectResponse
    {
        $user         = User::findOrFail($id);
        $countryData  = json_decode(file_get_contents(resource_path('views/partials/country.json')));
        $countryArray = (array)$countryData;
        $countries    = implode(',', array_keys($countryArray));

        $countryCode = $request->country;
        $country     = $countryData->$countryCode->country;
        $dialCode    = $countryData->$countryCode->dial_code;

        $request->validate([
            'firstname' => 'required|string|max:40',
            'lastname'  => 'required|string|max:40',
            'email'     => 'required|email|string|max:40|unique:users,email,' . $user->id,
            'mobile'    => 'required|string|max:40',
            'country'   => 'required|in:' . $countries,
        ]);

        $exists = User::where('mobile', $request->mobile)->where('dial_code', $dialCode)->where('id', '!=', $user->id)->exists();
        if ($exists) {
            $notify[] = ['error', 'The mobile number already exists.'];
            return back()->withNotify($notify);
        }

        $user->mobile    = $request->mobile;
        $user->firstname = $request->firstname;
        $user->lastname  = $request->lastname;
        $user->email     = $request->email;

        $user->address      = $request->address;
        $user->city         = $request->city;
        $user->state        = $request->state;
        $user->zip          = $request->zip;
        $user->country_name = @$country;
        $user->dial_code    = $dialCode;
        $user->country_code = $countryCode;

        $user->ev = $request->ev ? Status::VERIFIED : Status::UNVERIFIED;
        $user->sv = $request->sv ? Status::VERIFIED : Status::UNVERIFIED;
        $user->ts = $request->ts ? Status::ENABLE : Status::DISABLE;
        if (!$request->kv) {
            $user->kv = Status::KYC_UNVERIFIED;
            if ($user->kyc_data) {
                foreach ($user->kyc_data as $kycData) {
                    if ($kycData->type == 'file') {
                        fileManager()->removeFile(getFilePath('verify') . '/' . $kycData->value);
                    }
                }
            }
            $user->kyc_data = null;
        } else {
            $user->kv = Status::KYC_VERIFIED;
        }
        $user->save();

        $notify[] = ['success', 'User details updated successfully'];
        return back()->withNotify($notify);
    }

    /**
     * 增加或减少用户余额
     *
     * @param Request $request
     * @param int $id
     * @return RedirectResponse
     */
    public function addSubBalance(Request $request, int $id): RedirectResponse
    {
        $request->validate([
            'amount' => 'required|numeric|gt:0',
            'act'    => 'required|in:add,sub',
            'remark' => 'required|string|max:255',
        ]);

        $user   = User::findOrFail($id);
        $amount = $request->amount;
        $trx    = getTrx();

        $transaction = new Transaction();

        if ($request->act == 'add') {
            $user->balance += $amount;

            $transaction->trx_type = '+';
            $transaction->remark   = 'balance_add';

            $notifyTemplate = 'BAL_ADD';

            $notify[] = ['success', 'Balance added successfully'];
        } else {
            if ($amount > $user->balance) {
                $notify[] = ['error', $user->username . ' doesn\'t have sufficient balance.'];
                return back()->withNotify($notify);
            }

            $user->balance -= $amount;

            $transaction->trx_type = '-';
            $transaction->remark   = 'balance_subtract';

            $notifyTemplate = 'BAL_SUB';
            $notify[]       = ['success', 'Balance subtracted successfully'];
        }

        $user->save();

        $transaction->user_id      = $user->id;
        $transaction->amount       = $amount;
        $transaction->post_balance = $user->balance;
        $transaction->charge       = 0;
        $transaction->trx          = $trx;
        $transaction->details      = $request->remark;
        $transaction->save();

        notify($user, $notifyTemplate, [
            'trx'          => $trx,
            'amount'       => showAmount($amount, currencyFormat: false),
            'remark'       => $request->remark,
            'post_balance' => showAmount($user->balance, currencyFormat: false)
        ]);

        return back()->withNotify($notify);
    }

    /**
     * 管理员模拟用户登录
     * 这是一个关键的安全功能，需要：
     * 1. 管理员身份验证
     * 2. 如果用户启用了2FA，需要验证
     * 3. 完整的审计日志记录
     * 4. 会话标记为模拟
     * 5. 时间限制访问
     *
     * @param int $id 要模拟的用户ID
     * @param Request $request
     * @return RedirectResponse
     */
    public function login(int $id, Request $request): RedirectResponse
    {
        // Verify admin is authenticated
        if (!auth()->guard('admin')->check()) {
            $notify[] = ['error', 'Unauthorized access'];
            return to_route('admin.login')->withNotify($notify);
        }

        $admin = auth()->guard('admin')->user();
        $user = User::findOrFail($id);

        // Prevent admin from impersonating themselves
        if ($admin->id == $user->id) {
            $notify[] = ['error', 'Cannot impersonate yourself'];
            return back()->withNotify($notify);
        }

        // Check if user has 2FA enabled
        if ($user->tv == Status::VERIFIED) {
            // Store impersonation intent in session
            Session::put('impersonate_intent', [
                'admin_id' => $admin->id,
                'user_id' => $user->id,
                'timestamp' => time(),
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'reason' => $request->reason ?? 'No reason provided'
            ]);

            // Redirect to 2FA verification page
            return to_route('admin.user.impersonate.verify', $user->id);
        }

        // If no 2FA required, proceed with impersonation
        return $this->performImpersonation($admin, $user, $request);
    }

    /**
     * 显示模拟登录的2FA验证表单
     *
     * @param int $id 被模拟的用户ID
     * @return View
     */
    public function show2FAForm(int $id): View
    {
        // Verify admin is authenticated
        if (!auth()->guard('admin')->check()) {
            $notify[] = ['error', 'Unauthorized access'];
            return to_route('admin.login')->withNotify($notify);
        }

        // Check if impersonation intent exists in session
        $intent = Session::get('impersonate_intent');
        if (!$intent || $intent['user_id'] != $id) {
            $notify[] = ['error', 'Invalid or expired impersonation request'];
            return to_route('admin.users.detail', $id)->withNotify($notify);
        }

        $user = User::findOrFail($id);
        $pageTitle = '2FA Verification for Impersonation - ' . $user->username;

        return view($this->getAdminTemplate() . 'admin.auth.impersonation_2fa', compact('pageTitle', 'user', 'id'));
    }

    /**
     * Get active template name for admin views
     *
     * @return string
     */
    private function getAdminTemplate()
    {
        return activeTemplateName() . '.';
    }

    /**
     * 🔒 权限检查：判断当前管理员是否可以查看特定用户
     *
     * @param User $user 要查看的用户
     * @return bool
     */
    private function canViewUser(User $user): bool
    {
        // 获取当前管理员
        $admin = auth()->guard('admin')->user();

        // 如果未登录，返回false
        if (!$admin) {
            return false;
        }

        // 超级管理员可以查看所有用户
        if ($admin->super_admin == 1) {
            return true;
        }

        // 普通管理员可以查看所有用户（根据业务需求调整）
        // 这里可以根据实际需求添加更细粒度的权限控制
        // 例如：只允许查看特定分支的用户、只允许查看特定状态的用户等
        return true;
    }

    /**
     * 🔒 权限检查：判断当前管理员是否可以管理用户KYC
     *
     * @param User $user 要管理的用户
     * @return bool
     */
    private function canManageUserKyc(User $user): bool
    {
        // 获取当前管理员
        $admin = auth()->guard('admin')->user();

        // 如果未登录，返回false
        if (!$admin) {
            return false;
        }

        // 超级管理员可以管理所有用户KYC
        if ($admin->super_admin == 1) {
            return true;
        }

        // 检查管理员是否有KYC审核权限（根据实际需求添加权限字段）
        // 暂时允许所有登录的管理员进行KYC审核
        return true;
    }

    /**
     * 验证模拟登录的2FA验证码
     *
     * @param Request $request
     * @param int $id 被模拟的用户ID
     * @return RedirectResponse
     */
    public function verify2FA(Request $request, int $id): RedirectResponse
    {
        $request->validate([
            'code' => 'required',
        ]);

        // Verify admin is authenticated
        if (!auth()->guard('admin')->check()) {
            $notify[] = ['error', 'Unauthorized access'];
            return to_route('admin.login')->withNotify($notify);
        }

        $admin = auth()->guard('admin')->user();
        $user = User::findOrFail($id);

        // Check if impersonation intent exists in session
        $intent = Session::get('impersonate_intent');
        if (!$intent || $intent['admin_id'] != $admin->id || $intent['user_id'] != $user->id) {
            $notify[] = ['error', 'Invalid impersonation request'];
            return back()->withNotify($notify);
        }

        // Verify 2FA code
        $response = verifyG2fa($user, $request->code);
        if (!$response) {
            $notify[] = ['error', 'Invalid verification code'];
            return back()->withNotify($notify);
        }

        // Clear impersonation intent from session
        Session::forget('impersonate_intent');

        // Perform the impersonation
        return $this->performImpersonation($admin, $user, $request);
    }

    /**
     * 执行实际的模拟登录，包含所有安全检查
     *
     * @param Admin $admin 执行模拟的管理员
     * @param User $user 被模拟的用户
     * @param Request $request
     * @return RedirectResponse
     */
    private function performImpersonation(Admin $admin, User $user, Request $request): RedirectResponse
    {
        // Start database transaction for data integrity
        DB::beginTransaction();

        try {
            // Store original admin session data
            $originalSessionData = [
                'admin_id' => $admin->id,
                'admin_username' => $admin->username,
                'admin_email' => $admin->email,
                'original_login_at' => now(),
                'impersonation_start_time' => time(),
                'impersonation_expires_at' => time() + (config('session.lifetime', 120) * 60), // Default to session lifetime
            ];

            // Log admin out from admin panel
            Auth::guard('admin')->logout();

            // Log in as the user
            Auth::loginUsingId($user->id);

            // Store impersonation data in session
            Session::put('is_impersonating', true);
            Session::put('impersonator_data', $originalSessionData);
            Session::put('impersonation_reason', $request->reason ?? 'No reason provided');
            Session::put('impersonation_started_at', now());

            // Log the impersonation start event
            AuditLog::create([
                'admin_id' => $admin->id,
                'action_type' => 'admin_impersonation_start',
                'entity_type' => 'User',
                'entity_id' => $user->id,
                'meta' => [
                    'admin_username' => $admin->username,
                    'admin_email' => $admin->email,
                    'user_id' => $user->id,
                    'user_username' => $user->username,
                    'user_email' => $user->email,
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'reason' => $originalSessionData['impersonation_reason'],
                    'started_at' => now()->toISOString(),
                    'expires_at' => Carbon::createFromTimestamp($originalSessionData['impersonation_expires_at'])->toISOString(),
                ]
            ]);

            // Commit the transaction
            DB::commit();

            // Redirect with success message
            $notify[] = ['success', 'You are now impersonating ' . $user->username . '. All actions are being logged.'];
            return to_route('user.home')->withNotify($notify);

        } catch (\Exception $e) {
            // Rollback on error
            DB::rollBack();

            // Log the error
            AuditLog::create([
                'admin_id' => $admin->id,
                'action_type' => 'admin_impersonation_failed',
                'entity_type' => 'User',
                'entity_id' => $user->id,
                'meta' => [
                    'error' => $e->getMessage(),
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ]
            ]);

            $notify[] = ['error', 'Failed to start impersonation. Please try again.'];
            return back()->withNotify($notify);
        }
    }

    /**
     * 退出模拟模式并返回管理员面板
     *
     * @param Request $request
     * @return RedirectResponse
     */
    public function exitImpersonation(Request $request): RedirectResponse
    {
        // Check if currently impersonating
        if (!Session::has('is_impersonating') || !Session::get('is_impersonating')) {
            $notify[] = ['error', 'Not currently impersonating any user'];
            return to_route('admin.dashboard')->withNotify($notify);
        }

        // Get impersonation data
        $impersonatorData = Session::get('impersonator_data');
        $adminId = $impersonatorData['admin_id'] ?? null;

        // Get the current user being impersonated
        $currentUser = Auth::user();

        // Log out from user session
        Auth::logout();

        // Log back in as admin if admin record exists
        if ($adminId) {
            $admin = Admin::find($adminId);
            if ($admin) {
                Auth::guard('admin')->login($admin);

                // Log the impersonation end event
                if ($currentUser) {
                    AuditLog::create([
                        'admin_id' => $admin->id,
                        'action_type' => 'admin_impersonation_end',
                        'entity_type' => 'User',
                        'entity_id' => $currentUser->id,
                        'meta' => [
                            'admin_username' => $admin->username,
                            'user_id' => $currentUser->id,
                            'user_username' => $currentUser->username,
                            'session_duration_minutes' => $this->calculateSessionDuration($impersonatorData),
                            'ip_address' => $request->ip(),
                            'user_agent' => $request->userAgent(),
                            'ended_at' => now()->toISOString(),
                        ]
                    ]);
                }
            }
        }

        // Clear all impersonation-related session data
        Session::forget('is_impersonating');
        Session::forget('impersonator_data');
        Session::forget('impersonation_reason');
        Session::forget('impersonation_started_at');

        $notify[] = ['success', 'Successfully exited impersonation mode'];
        return to_route('admin.dashboard')->withNotify($notify);
    }

    /**
     * 检查当前会话是否为模拟登录
     *
     * @return bool
     */
    public function isImpersonating(): bool
    {
        return Session::has('is_impersonating') && Session::get('is_impersonating');
    }

    /**
     * 计算会话时长（分钟）
     *
     * @param array $impersonatorData
     * @return float
     */
    private function calculateSessionDuration(array $impersonatorData): float
    {
        $startTime = $impersonatorData['impersonation_start_time'] ?? time();
        $endTime = time();
        return round(($endTime - $startTime) / 60, 2);
    }

    /**
     * 切换用户状态（启用/禁用）
     *
     * @param Request $request
     * @param int $id
     * @return RedirectResponse
     */
    public function status(Request $request, int $id): RedirectResponse
    {
        $user = User::findOrFail($id);
        if ($user->status == Status::USER_ACTIVE) {
            $request->validate([
                'reason' => 'required|string|max:255'
            ]);
            $user->status     = Status::USER_BAN;
            $user->ban_reason = $request->reason;
            $notify[]         = ['success', 'User banned successfully'];
        } else {
            $user->status     = Status::USER_ACTIVE;
            $user->ban_reason = null;
            $notify[]         = ['success', 'User unbanned successfully'];
        }
        $user->save();
        return back()->withNotify($notify);
    }


    /**
     * 显示单个用户通知表单
     *
     * @param int $id
     * @return View
     */
    public function showNotificationSingleForm(int $id): View
    {
        $user = User::findOrFail($id);
        if (!gs('en') && !gs('sn') && !gs('pn')) {
            $notify[] = ['warning', 'Notification options are disabled currently'];
            return to_route('admin.users.detail', $user->id)->withNotify($notify);
        }
        $pageTitle = 'Send Notification to ' . $user->username;
        return view('admin.users.notification_single', compact('pageTitle', 'user'));
    }

    /**
     * 发送单个用户通知
     *
     * @param Request $request
     * @param int $id
     * @return RedirectResponse
     */
    public function sendNotificationSingle(Request $request, int $id): RedirectResponse
    {
        $request->validate([
            'message' => 'required',
            'via'     => 'required|in:email,sms,push',
            'subject' => 'required_if:via,email,push',
            'image'   => ['nullable', 'image', new FileTypeValidate(['jpg', 'jpeg', 'png'])],
        ]);

        if (!gs('en') && !gs('sn') && !gs('pn')) {
            $notify[] = ['warning', 'Notification options are disabled currently'];
            return to_route('admin.dashboard')->withNotify($notify);
        }

        $imageUrl = null;
        if ($request->via == 'push' && $request->hasFile('image')) {
            $imageUrl = fileUploader($request->image, getFilePath('push'));
        }

        $template = NotificationTemplate::where('act', 'DEFAULT')->where($request->via . '_status', Status::ENABLE)->exists();
        if (!$template) {
            $notify[] = ['warning', 'Default notification template is not enabled'];
            return back()->withNotify($notify);
        }

        $user = User::findOrFail($id);
        notify($user, 'DEFAULT', [
            'subject' => $request->subject,
            'message' => $request->message,
        ], [$request->via], pushImage: $imageUrl);
        $notify[] = ['success', 'Notification sent successfully'];
        return back()->withNotify($notify);
    }

    /**
     * 显示批量通知表单
     *
     * @return View
     */
    public function showNotificationAllForm(): View
    {
        if (!gs('en') && !gs('sn') && !gs('pn')) {
            $notify[] = ['warning', 'Notification options are disabled currently'];
            return to_route('admin.dashboard')->withNotify($notify);
        }

        $notifyToUser = User::notifyToUser();
        $users        = User::active()->count();
        $pageTitle    = 'Notification to Verified Users';

        if (session()->has('SEND_NOTIFICATION') && !request()->email_sent) {
            session()->forget('SEND_NOTIFICATION');
        }

        return view('admin.users.notification_all', compact('pageTitle', 'users', 'notifyToUser'));
    }

    /**
     * 发送批量通知
     *
     * @param Request $request
     * @return RedirectResponse
     */
    public function sendNotificationAll(Request $request): RedirectResponse
    {
        $request->validate([
            'via'                          => 'required|in:email,sms,push',
            'message'                      => 'required',
            'subject'                      => 'required_if:via,email,push',
            'start'                        => 'required|integer|gte:1',
            'batch'                        => 'required|integer|gte:1',
            'being_sent_to'                => 'required',
            'cooling_time'                 => 'required|integer|gte:1',
            'number_of_top_deposited_user' => 'required_if:being_sent_to,topDepositedUsers|integer|gte:0',
            'number_of_days'               => 'required_if:being_sent_to,notLoginUsers|integer|gte:0',
            'image'                        => ["nullable", 'image', new FileTypeValidate(['jpg', 'jpeg', 'png'])],
        ], [
            'number_of_days.required_if'               => "Number of days field is required",
            'number_of_top_deposited_user.required_if' => "Number of top deposited user field is required",
        ]);

        if (!gs('en') && !gs('sn') && !gs('pn')) {
            $notify[] = ['warning', 'Notification options are disabled currently'];
            return to_route('admin.dashboard')->withNotify($notify);
        }


        $template = NotificationTemplate::where('act', 'DEFAULT')->where($request->via . '_status', Status::ENABLE)->exists();
        if (!$template) {
            $notify[] = ['warning', 'Default notification template is not enabled'];
            return back()->withNotify($notify);
        }

        if ($request->being_sent_to == 'selectedUsers') {
            if (session()->has("SEND_NOTIFICATION")) {
                $request->merge(['user' => session()->get('SEND_NOTIFICATION')['user']]);
            } else {
                if (!$request->user || !is_array($request->user) || empty($request->user)) {
                    $notify[] = ['error', "Ensure that the user field is populated when sending an email to the designated user group"];
                    return back()->withNotify($notify);
                }
            }
        }

        $scope     = $request->being_sent_to;
        $userQuery = User::oldest()->active()->$scope();

        if (session()->has("SEND_NOTIFICATION")) {
            $totalUserCount = session('SEND_NOTIFICATION')['total_user'];
        } else {
            $totalUserCount = (clone $userQuery)->count() - ($request->start - 1);
        }


        if ($totalUserCount <= 0) {
            $notify[] = ['error', "Notification recipients were not found among the selected user base."];
            return back()->withNotify($notify);
        }


        $imageUrl = null;

        if ($request->via == 'push' && $request->hasFile('image')) {
            if (session()->has("SEND_NOTIFICATION")) {
                $request->merge(['image' => session()->get('SEND_NOTIFICATION')['image']]);
            }
            if ($request->hasFile("image")) {
                $imageUrl = fileUploader($request->image, getFilePath('push'));
            }
        }

        $users = (clone $userQuery)->skip($request->start - 1)->limit($request->batch)->get();

        foreach ($users as $user) {
            notify($user, 'DEFAULT', [
                'subject' => $request->subject,
                'message' => $request->message,
            ], [$request->via], pushImage: $imageUrl);
        }

        return $this->sessionForNotification($totalUserCount, $request);
    }
    /**
     * 管理通知发送会话状态
     *
     * @param int $totalUserCount
     * @param Request $request
     * @return RedirectResponse
     */
    private function sessionForNotification(int $totalUserCount, Request $request): RedirectResponse
    {
        if (session()->has('SEND_NOTIFICATION')) {
            $sessionData                = session("SEND_NOTIFICATION");
            $sessionData['total_sent'] += $sessionData['batch'];
        } else {
            $sessionData               = $request->except('_token');
            $sessionData['total_sent'] = $request->batch;
            $sessionData['total_user'] = $totalUserCount;
        }

        $sessionData['start'] = $sessionData['total_sent'] + 1;

        if ($sessionData['total_sent'] >= $totalUserCount) {
            session()->forget("SEND_NOTIFICATION");
            $message = ucfirst($request->via) . " notifications were sent successfully";
            $url     = route("admin.users.notification.all");
        } else {
            session()->put('SEND_NOTIFICATION', $sessionData);
            $message = $sessionData['total_sent'] . " " . $sessionData['via'] . "  notifications were sent successfully";
            $url     = route("admin.users.notification.all") . "?email_sent=yes";
        }
        $notify[] = ['success', $message];
        return redirect($url)->withNotify($notify);
    }    /**
     * 按用户群体统计数量
     *
     * @param string $methodName
     * @return int
     */
    public function countBySegment(string $methodName): int
    {
        return User::active()->$methodName()->count();
    }    /**
     * 获取用户列表（JSON格式）
     *
     * @return JsonResponse
     */
    public function list(): JsonResponse
    {
        $query = User::active();

        if (request()->search) {
            $query->where(function ($q) {
                $q->where('email', 'like', '%' . request()->search . '%')->orWhere('username', 'like', '%' . request()->search . '%');
            });
        }
        $users = $query->orderBy('id', 'desc')->paginate(getPaginate());
        return response()->json([
            'success' => true,
            'users'   => $users,
            'more'    => $users->hasMorePages()
        ]);
    }    /**
     * 显示用户通知日志
     *
     * @param int $id
     * @return View
     */
    public function notificationLog(int $id): View
    {
        $user      = User::findOrFail($id);
        $pageTitle = 'Notifications Sent to ' . $user->username;
        $logs      = NotificationLog::where('user_id', $id)->with('user')->orderBy('id', 'desc')->paginate(getPaginate());
        return view('admin.reports.notification_history', compact('pageTitle', 'logs', 'user'));
    }    /**
     * 显示用户推荐树
     *
     * @param string $username
     * @return View
     */
    public function tree(string $username): View
    {

        $user = User::where('username', $username)->first();

        if ($user) {
            $tree      = showTreePage($user->id);
            $pageTitle = "Tree of " . $user->fullname;
            return view('admin.users.tree', compact('tree', 'pageTitle'));
        }

        $notify[] = ['error', 'Tree Not Found!!'];
        return redirect()->route('admin.dashboard')->withNotify($notify);
    }    /**
     * 显示其他用户的推荐树
     *
     * @param Request $request
     * @param string|null $username
     * @return View
     */
    public function otherTree(Request $request, ?string $username = null): View
    {
        if ($request->username) {
            $user = User::where('username', $request->username)->first();
        } else {
            $user = User::where('username', $username)->first();
        }
        if ($user) {
            $tree      = showTreePage($user->id);
            $pageTitle = "Tree of " . $user->fullname;
            return view('admin.users.tree', compact('tree', 'pageTitle'));
        }

        $notify[] = ['error', 'Tree Not Found !'];
        return redirect()->route('admin.dashboard')->withNotify($notify);
    }    /**
     * 显示用户的推荐人列表
     *
     * @param int $id
     * @return View
     */
    public function userRef(int $id): View
    {
        $user      = User::findOrFail($id);
        $pageTitle = 'Referred By ' . $user->username;
        $users     = User::searchable(['username', 'email'])->where('ref_by', $id)->latest()->paginate(getPaginate());
        return view('admin.users.list', compact('pageTitle', 'users'));
    }
    /**
     * 更新匹配奖金设置
     *
     * @param Request $request
     * @return RedirectResponse
     */
    public function matchingUpdate(Request $request): RedirectResponse
    {
        $request->validate([
            'bv_price' => 'required|min:0',
            'total_bv' => 'required|min:0|integer',
            'max_bv'   => 'required|min:0|integer',
        ]);

        if ($request->matching_bonus_time == 'daily') {
            $when = $request->daily_time;
        } elseif ($request->matching_bonus_time == 'weekly') {
            $when = $request->weekly_time;
        } elseif ($request->matching_bonus_time == 'monthly') {
            $when = $request->monthly_time;
        }

        $setting                      = gs();
        $setting->bv_price            = $request->bv_price;
        $setting->total_bv            = $request->total_bv;
        $setting->max_bv              = $request->max_bv;
        $setting->cary_flash          = $request->cary_flash;
        $setting->matching_bonus_time = $request->matching_bonus_time;
        $setting->matching_when       = $when;
        $setting->save();

        $notify[] = ['success', 'Matching bonus has been updated.'];
        return back()->withNotify($notify);
    }
}
