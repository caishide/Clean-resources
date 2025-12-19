<?php

namespace App\Http\Controllers\Admin;

use Carbon\Carbon;
use App\Models\User;
use App\Models\BvLog;
use App\Models\Deposit;
use App\Lib\CurlRequest;
use App\Constants\Status;
use App\Models\UserExtra;
use App\Models\UserLogin;
use App\Models\Withdrawal;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use App\Rules\FileTypeValidate;
use App\Models\AdminNotification;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

/**
 * AdminController - 管理员控制器
 *
 * 处理管理员仪表盘、个人资料、密码管理和通知功能
 */
class AdminController extends Controller
{
    /** @var int 报表分组阈值(天数) */
    private const REPORT_GROUP_THRESHOLD_DAYS = 30;

    /** @var int 用户登录数据天数 */
    private const USER_LOGIN_DAYS = 30;

    /** @var int 国家统计显示数量 */
    private const COUNTRY_DISPLAY_COUNT = 5;

    /** @var int 最近投资天数 */
    private const RECENT_INVEST_DAYS = 6;

    /** @var string BV减少类型 */
    private const BV_TRX_TYPE_MINUS = '-';

    /** @var string 交易增加类型 */
    private const TRX_TYPE_PLUS = '+';

    /** @var string 交易减少类型 */
    private const TRX_TYPE_MINUS = '-';

    /**
     * 显示管理员仪表盘
     *
     * @return View
     */
    public function dashboard(): View
    {
        $pageTitle = 'Dashboard';

        // Optimize user statistics with a single aggregated query
        $userStats = User::selectRaw('
            COUNT(*) as total_users,
            SUM(CASE WHEN status = ? AND ev = ? AND sv = ? THEN 1 ELSE 0 END) as verified_users,
            SUM(CASE WHEN ev = ? THEN 1 ELSE 0 END) as email_unverified_users,
            SUM(CASE WHEN sv = ? THEN 1 ELSE 0 END) as mobile_unverified_users
        ', [
            Status::USER_ACTIVE, Status::VERIFIED, Status::VERIFIED,
            Status::UNVERIFIED, Status::UNVERIFIED
        ])->first();

        $widget['total_users'] = $userStats->total_users;
        $widget['verified_users'] = $userStats->verified_users;
        $widget['email_unverified_users'] = $userStats->email_unverified_users;
        $widget['mobile_unverified_users'] = $userStats->mobile_unverified_users;

        // user Browsing, Country, Operating Log - optimized to use single query
        $userLoginData = UserLogin::where('created_at', '>=', Carbon::now()->subDays(self::USER_LOGIN_DAYS))->get(['browser', 'os', 'country']);

        $chart['user_browser_counter'] = $userLoginData->groupBy('browser')->map(function ($item, $key) {
            return collect($item)->count();
        });
        $chart['user_os_counter'] = $userLoginData->groupBy('os')->map(function ($item, $key) {
            return collect($item)->count();
        });
        $chart['user_country_counter'] = $userLoginData->groupBy('country')->map(function ($item, $key) {
            return collect($item)->count();
        })->sort()->reverse()->take(self::COUNTRY_DISPLAY_COUNT);

        // Optimize deposit statistics with aggregated queries
        $depositStats = Deposit::selectRaw('
            SUM(CASE WHEN status = ? THEN amount ELSE 0 END) as total_deposit_amount,
            COUNT(CASE WHEN status = ? THEN 1 END) as total_deposit_pending,
            COUNT(CASE WHEN status = ? THEN 1 END) as total_deposit_rejected,
            SUM(CASE WHEN status = ? THEN charge ELSE 0 END) as total_deposit_charge
        ', [
            Status::PAYMENT_SUCCESS, Status::PAYMENT_PENDING, Status::PAYMENT_REJECT, Status::PAYMENT_SUCCESS
        ])->first();

        $deposit['total_deposit_amount'] = $depositStats->total_deposit_amount;
        $deposit['total_deposit_pending'] = $depositStats->total_deposit_pending;
        $deposit['total_deposit_rejected'] = $depositStats->total_deposit_rejected;
        $deposit['total_deposit_charge'] = $depositStats->total_deposit_charge;

        // Optimize withdrawal statistics with aggregated queries
        $withdrawStats = Withdrawal::selectRaw('
            SUM(CASE WHEN status = ? THEN amount ELSE 0 END) as total_withdraw_amount,
            COUNT(CASE WHEN status = ? THEN 1 END) as total_withdraw_pending,
            COUNT(CASE WHEN status = ? THEN 1 END) as total_withdraw_rejected,
            SUM(CASE WHEN status = ? THEN charge ELSE 0 END) as total_withdraw_charge
        ', [
            Status::PAYMENT_SUCCESS, Status::PAYMENT_PENDING, Status::PAYMENT_REJECT, Status::PAYMENT_SUCCESS
        ])->first();

        $withdrawals['total_withdraw_amount'] = $withdrawStats->total_withdraw_amount;
        $withdrawals['total_withdraw_pending'] = $withdrawStats->total_withdraw_pending;
        $withdrawals['total_withdraw_rejected'] = $withdrawStats->total_withdraw_rejected;
        $withdrawals['total_withdraw_charge'] = $withdrawStats->total_withdraw_charge;

        // BV statistics - these remain separate as they aggregate different fields
        $bv['bvLeft'] = UserExtra::sum('bv_left');
        $bv['bvRight'] = UserExtra::sum('bv_right');
        $bv['totalBvCut'] = BvLog::where('trx_type', self::BV_TRX_TYPE_MINUS)->sum('amount');

        // Investment and commission statistics
        $widget['users_invest'] = User::sum('total_invest');
        $widget['last7days_invest'] = Transaction::whereDate('created_at', '>=', Carbon::now()->subDays(self::RECENT_INVEST_DAYS))->where('remark', 'purchased_plan')->sum('amount');
        $widget['total_ref_com'] = Transaction::where('remark', 'referral_commission')->sum('amount');
        $widget['total_binary_com'] = Transaction::where('remark', 'binary_commission')->sum('amount');

        return view('admin.dashboard', compact('pageTitle', 'widget', 'chart','deposit','withdrawals','bv'));
    }

    public function depositAndWithdrawReport(Request $request) {

        $diffInDays = Carbon::parse($request->start_date)->diffInDays(Carbon::parse($request->end_date));

        $groupBy = $diffInDays > 30 ? 'months' : 'days';
        $format = $diffInDays > 30 ? '%M-%Y'  : '%d-%M-%Y';

        if ($groupBy == 'days') {
            $dates = $this->getAllDates($request->start_date, $request->end_date);
        } else {
            $dates = $this->getAllMonths($request->start_date, $request->end_date);
        }
        $deposits = Deposit::successful()
            ->whereDate('created_at', '>=', $request->start_date)
            ->whereDate('created_at', '<=', $request->end_date)
            ->selectRaw('SUM(amount) AS amount')
            ->selectRaw("DATE_FORMAT(created_at, '{$format}') as created_on")
            ->latest()
            ->groupBy('created_on')
            ->get();


        $withdrawals = Withdrawal::approved()
            ->whereDate('created_at', '>=', $request->start_date)
            ->whereDate('created_at', '<=', $request->end_date)
            ->selectRaw('SUM(amount) AS amount')
            ->selectRaw("DATE_FORMAT(created_at, '{$format}') as created_on")
            ->latest()
            ->groupBy('created_on')
            ->get();

        $invests = Transaction::where('remark','purchased_plan')
            ->whereDate('created_at', '>=', $request->start_date)
            ->whereDate('created_at', '<=', $request->end_date)
            ->selectRaw('SUM(amount) AS amount')
            ->selectRaw("DATE_FORMAT(created_at, '{$format}') as created_on")
            ->latest()
            ->groupBy('created_on')
            ->get();
        $data = [];

        foreach ($dates as $date) {
            $data[] = [
                'created_on' => $date,
                'deposits' => getAmount($deposits->where('created_on', $date)->first()?->amount ?? 0),
                'withdrawals' => getAmount($withdrawals->where('created_on', $date)->first()?->amount ?? 0),
                'invests' => getAmount($invests->where('created_on', $date)->first()?->amount ?? 0)
            ];
        }

        $data = collect($data);

        // Monthly Deposit & Withdraw Report Graph
        $report['created_on']   = $data->pluck('created_on');
        $report['data']     = [
            [
                'name' => 'Deposited',
                'data' => $data->pluck('deposits')
            ],
            [
                'name' => 'Withdrawn',
                'data' => $data->pluck('withdrawals')
            ],
            [
                'name' => 'Invest',
                'data' => $data->pluck('invests')
            ]
        ];

        return response()->json($report);
    }

    public function transactionReport(Request $request) {

        $diffInDays = Carbon::parse($request->start_date)->diffInDays(Carbon::parse($request->end_date));

        $groupBy = $diffInDays > 30 ? 'months' : 'days';
        $format = $diffInDays > 30 ? '%M-%Y'  : '%d-%M-%Y';

        if ($groupBy == 'days') {
            $dates = $this->getAllDates($request->start_date, $request->end_date);
        } else {
            $dates = $this->getAllMonths($request->start_date, $request->end_date);
        }

        $plusTransactions   = Transaction::where('trx_type','+')
            ->whereDate('created_at', '>=', $request->start_date)
            ->whereDate('created_at', '<=', $request->end_date)
            ->selectRaw('SUM(amount) AS amount')
            ->selectRaw("DATE_FORMAT(created_at, '{$format}') as created_on")
            ->latest()
            ->groupBy('created_on')
            ->get();

        $minusTransactions  = Transaction::where('trx_type','-')
            ->whereDate('created_at', '>=', $request->start_date)
            ->whereDate('created_at', '<=', $request->end_date)
            ->selectRaw('SUM(amount) AS amount')
            ->selectRaw("DATE_FORMAT(created_at, '{$format}') as created_on")
            ->latest()
            ->groupBy('created_on')
            ->get();


        $data = [];

        foreach ($dates as $date) {
            $data[] = [
                'created_on' => $date,
                'credits' => getAmount($plusTransactions->where('created_on', $date)->first()?->amount ?? 0),
                'debits' => getAmount($minusTransactions->where('created_on', $date)->first()?->amount ?? 0)
            ];
        }

        $data = collect($data);

        // Monthly Deposit & Withdraw Report Graph
        $report['created_on']   = $data->pluck('created_on');
        $report['data']     = [
            [
                'name' => 'Plus Transactions',
                'data' => $data->pluck('credits')
            ],
            [
                'name' => 'Minus Transactions',
                'data' => $data->pluck('debits')
            ]
        ];

        return response()->json($report);
    }


    private function getAllDates($startDate, $endDate) {
        $dates = [];
        $currentDate = new \DateTime($startDate);
        $endDate = new \DateTime($endDate);

        while ($currentDate <= $endDate) {
            $dates[] = $currentDate->format('d-F-Y');
            $currentDate->modify('+1 day');
        }

        return $dates;
    }

    private function  getAllMonths($startDate, $endDate) {
        if ($endDate > now()) {
            $endDate = now()->format('Y-m-d');
        }

        $startDate = new \DateTime($startDate);
        $endDate = new \DateTime($endDate);

        $months = [];

        while ($startDate <= $endDate) {
            $months[] = $startDate->format('F-Y');
            $startDate->modify('+1 month');
        }

        return $months;
    }


    public function profile()
    {
        $pageTitle = 'Profile';
        $admin = auth('admin')->user();
        return view('admin.profile', compact('pageTitle', 'admin'));
    }

    public function profileUpdate(Request $request)
    {
        $request->validate([
            'name' => 'required',
            'email' => 'required|email',
            'image' => ['nullable','image',new FileTypeValidate(['jpg','jpeg','png'])]
        ]);
        $user = auth('admin')->user();

        if ($request->hasFile('image')) {
            try {
                $old = $user->image;
                $user->image = fileUploader($request->image, getFilePath('adminProfile'), getFileSize('adminProfile'), $old);
            } catch (\Exception $exp) {
                $notify[] = ['error', 'Couldn\'t upload your image'];
                return back()->withNotify($notify);
            }
        }

        $user->name = $request->name;
        $user->email = $request->email;
        $user->save();
        $notify[] = ['success', 'Profile updated successfully'];
        return to_route('admin.profile')->withNotify($notify);
    }

    public function password()
    {
        $pageTitle = 'Password Setting';
        $admin = auth('admin')->user();
        return view('admin.password', compact('pageTitle', 'admin'));
    }

    public function passwordUpdate(Request $request)
    {
        // Strong password requirement for admins - minimum 10 characters with complexity
        $request->validate([
            'old_password' => 'required',
            'password' => [
                'required',
                'confirmed',
                'min:10',
                'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[!@#$%^&*])[A-Za-z\d!@#$%^&*]+$/'
            ],
        ], [
            'password.min' => 'Admin password must be at least 10 characters',
            'password.regex' => 'Admin password must contain at least one uppercase letter, one lowercase letter, one number, and one special character (!@#$%^&*)'
        ]);

        $user = auth('admin')->user();
        if (!Hash::check($request->old_password, $user->password)) {
            $notify[] = ['error', 'Password doesn\'t match!!'];
            return back()->withNotify($notify);
        }
        $user->password = Hash::make($request->password);
        $user->save();
        $notify[] = ['success', 'Password changed successfully.'];
        return to_route('admin.password')->withNotify($notify);
    }

    public function notifications(){
        $notifications = AdminNotification::orderBy('id','desc')->with('user')->paginate(getPaginate());
        $hasUnread = AdminNotification::where('is_read',Status::NO)->exists();
        $hasNotification = AdminNotification::exists();
        $pageTitle = 'Notifications';
        return view('admin.notifications',compact('pageTitle','notifications','hasUnread','hasNotification'));
    }


    public function notificationRead($id){
        $notification = AdminNotification::findOrFail($id);
        $notification->is_read = Status::YES;
        $notification->save();
        $url = $notification->click_url;
        if ($url == '#') {
            $url = url()->previous();
        }
        return redirect($url);
    }

    public function requestReport()
    {
        $pageTitle = 'Your Listed Report & Request';
        $arr['app_name'] = systemDetails()['name'];
        $arr['app_url'] = env('APP_URL');
        $arr['purchase_code'] = env('PURCHASECODE');
        $url = "https://license.viserlab.com/issue/get?".http_build_query($arr);
        $response = CurlRequest::curlContent($url);
        $response = json_decode($response);
        if (!$response || !@$response->status || !@$response->message) {
            return to_route('admin.dashboard')->withErrors(__('admin.error.something_wrong'));
        }
        if ($response->status == 'error') {
            return to_route('admin.dashboard')->withErrors($response->message);
        }
        $reports = $response->message[0];
        return view('admin.reports',compact('reports','pageTitle'));
    }

    public function reportSubmit(Request $request)
    {
        $request->validate([
            'type'=>'required|in:bug,feature',
            'message'=>'required',
        ]);
        $url = 'https://license.viserlab.com/issue/add';

        $arr['app_name'] = systemDetails()['name'];
        $arr['app_url'] = env('APP_URL');
        $arr['purchase_code'] = env('PURCHASECODE');
        $arr['req_type'] = $request->type;
        $arr['message'] = $request->message;
        $response = CurlRequest::curlPostContent($url,$arr);
        $response = json_decode($response);
        if (!$response || !@$response->status || !@$response->message) {
            return to_route('admin.dashboard')->withErrors(__('admin.error.something_wrong'));
        }
        if ($response->status == 'error') {
            return back()->withErrors($response->message);
        }
        $notify[] = ['success',$response->message];
        return back()->withNotify($notify);
    }

    public function readAllNotification(){
        AdminNotification::where('is_read',Status::NO)->update([
            'is_read'=>Status::YES
        ]);
        $notify[] = ['success','Notifications read successfully'];
        return back()->withNotify($notify);
    }

    public function deleteAllNotification(){
        AdminNotification::truncate();
        $notify[] = ['success','Notifications deleted successfully'];
        return back()->withNotify($notify);
    }

    public function deleteSingleNotification($id){
        AdminNotification::where('id',$id)->delete();
        $notify[] = ['success','Notification deleted successfully'];
        return back()->withNotify($notify);
    }

    public function downloadAttachment($fileHash)
    {
        try {
            $filePath = decrypt($fileHash);

            // Resolve the real path to prevent path traversal
            $realPath = realpath($filePath);
            $allowedPath = realpath(storage_path('app/attachments'));

            // Validate that the path is within the allowed directory
            if (!$realPath || !$allowedPath || !str_starts_with($realPath, $allowedPath)) {
                Log::channel('security')->warning('Path traversal attempt in downloadAttachment', [
                    'attempted_path' => $filePath,
                    'real_path' => $realPath,
                    'user_id' => auth()->id(),
                    'ip' => request()->ip()
                ]);
                $notify[] = ['error','Invalid file path'];
                return back()->withNotify($notify);
            }

            // Verify file exists
            if (!file_exists($realPath)) {
                Log::channel('security')->warning('File not found in downloadAttachment', [
                    'file_path' => $realPath,
                    'user_id' => auth()->id()
                ]);
                $notify[] = ['error','File does not exist'];
                return back()->withNotify($notify);
            }

            $extension = pathinfo($realPath, PATHINFO_EXTENSION);
            $title = slug(gs('site_name')).'- attachments.'.$extension;

            $mimetype = mime_content_type($realPath);

            Log::channel('security')->info('File downloaded successfully', [
                'file_path' => $realPath,
                'user_id' => auth()->id(),
                'ip' => request()->ip()
            ]);

            return response()->download($realPath, $title, ['Content-Type' => $mimetype]);

        } catch (\Exception $e) {
            Log::channel('security')->error('Error in downloadAttachment', [
                'error' => $e->getMessage(),
                'file_hash' => $fileHash,
                'user_id' => auth()->id()
            ]);
            $notify[] = ['error','File does not exist'];
            return back()->withNotify($notify);
        }
    }


}
