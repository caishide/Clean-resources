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
use Illuminate\Http\Request;
use App\Models\NotificationLog;
use App\Rules\FileTypeValidate;
use App\Http\Controllers\Controller;
use App\Models\NotificationTemplate;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ManageUsersController extends Controller
{

    public function allUsers()
    {
        $pageTitle = 'All Users';
        $users     = $this->userData();
        return view('admin.users.list', compact('pageTitle', 'users'));
    }

    public function activeUsers()
    {
        $pageTitle = 'Active Users';
        $users     = $this->userData('active');
        return view('admin.users.list', compact('pageTitle', 'users'));
    }

    public function bannedUsers()
    {
        $pageTitle = 'Banned Users';
        $users     = $this->userData('banned');
        return view('admin.users.list', compact('pageTitle', 'users'));
    }

    public function emailUnverifiedUsers()
    {
        $pageTitle = 'Email Unverified Users';
        $users     = $this->userData('emailUnverified');
        return view('admin.users.list', compact('pageTitle', 'users'));
    }

    public function kycUnverifiedUsers()
    {
        $pageTitle = 'KYC Unverified Users';
        $users     = $this->userData('kycUnverified');
        return view('admin.users.list', compact('pageTitle', 'users'));
    }

    public function kycPendingUsers()
    {
        $pageTitle = 'KYC Pending Users';
        $users     = $this->userData('kycPending');
        return view('admin.users.list', compact('pageTitle', 'users'));
    }

    public function emailVerifiedUsers()
    {
        $pageTitle = 'Email Verified Users';
        $users     = $this->userData('emailVerified');
        return view('admin.users.list', compact('pageTitle', 'users'));
    }


    public function mobileUnverifiedUsers()
    {
        $pageTitle = 'Mobile Unverified Users';
        $users     = $this->userData('mobileUnverified');
        return view('admin.users.list', compact('pageTitle', 'users'));
    }


    public function mobileVerifiedUsers()
    {
        $pageTitle = 'Mobile Verified Users';
        $users     = $this->userData('mobileVerified');
        return view('admin.users.list', compact('pageTitle', 'users'));
    }


    public function usersWithBalance()
    {
        $pageTitle = 'Users with Balance';
        $users     = $this->userData('withBalance');
        return view('admin.users.list', compact('pageTitle', 'users'));
    }

    public function paidUsers()
    {
        $pageTitle = 'Paid Users';
        $users     = $this->userData('paidUser');
        return view('admin.users.list', compact('pageTitle', 'users'));
    }

    public function freeUsers()
    {
        $pageTitle = 'Free Users';
        $users     = $this->userData('freeUser');
        return view('admin.users.list', compact('pageTitle', 'users'));
    }

    protected function userData($scope = null)
    {
        if ($scope) {
            $users = User::$scope();
        } else {
            $users = User::query();
        }
        return $users->searchable(['username', 'email'])->orderBy('id', 'desc')->paginate(getPaginate());
    }


    public function detail($id)
    {
        $user      = User::findOrFail($id);
        $pageTitle = 'User Detail - ' . $user->username;

        $totalDeposit     = Deposit::where('user_id', $user->id)->successful()->sum('amount');
        $totalWithdrawals = Withdrawal::where('user_id', $user->id)->approved()->sum('amount');
        $totalTransaction = Transaction::where('user_id', $user->id)->count();
        $countries        = json_decode(file_get_contents(resource_path('views/partials/country.json')));
        $totalBvCut       = BvLog::where('user_id', $user->id)->where('trx_type', '-')->sum('amount');
        $totalOrder       = Order::where('user_id', $user->id)->count();
        return view('admin.users.detail', compact('pageTitle', 'user', 'totalDeposit', 'totalWithdrawals', 'totalTransaction', 'countries', 'totalBvCut', 'totalOrder'));
    }


    public function kycDetails($id)
    {
        $pageTitle = 'KYC Details';
        $user      = User::findOrFail($id);
        return view('admin.users.kyc_detail', compact('pageTitle', 'user'));
    }

    public function kycApprove($id)
    {
        $user     = User::findOrFail($id);
        $user->kv = Status::KYC_VERIFIED;
        $user->save();

        notify($user, 'KYC_APPROVE', []);

        $notify[] = ['success', 'KYC approved successfully'];
        return to_route('admin.users.kyc.pending')->withNotify($notify);
    }

    public function kycReject(Request $request, $id)
    {
        $request->validate([
            'reason' => 'required'
        ]);
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


    public function update(Request $request, $id)
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

    public function addSubBalance(Request $request, $id)
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
     * Start admin impersonation of a user
     * This is a critical security function that requires:
     * 1. Admin authentication
     * 2. 2FA verification if user has it enabled
     * 3. Comprehensive audit logging
     * 4. Session flagging for impersonation
     * 5. Time-limited access
     *
     * @param int $id User ID to impersonate
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function login($id, Request $request)
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
     * Show 2FA verification form for impersonation
     *
     * @param int $id User ID being impersonated
     * @return \Illuminate\Http\Response
     */
    public function show2FAForm($id)
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
     * Verify 2FA code for impersonation
     *
     * @param Request $request
     * @param int $id User ID being impersonated
     * @return \Illuminate\Http\RedirectResponse
     */
    public function verify2FA(Request $request, $id)
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
     * Perform the actual impersonation with all security checks
     *
     * @param Admin $admin Admin performing impersonation
     * @param User $user User being impersonated
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    private function performImpersonation(Admin $admin, User $user, Request $request)
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
     * Exit impersonation mode and return to admin panel
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function exitImpersonation(Request $request)
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
     * Check if current session is an impersonation
     *
     * @return bool
     */
    public function isImpersonating()
    {
        return Session::has('is_impersonating') && Session::get('is_impersonating');
    }

    /**
     * Calculate session duration in minutes
     *
     * @param array $impersonatorData
     * @return float
     */
    private function calculateSessionDuration(array $impersonatorData)
    {
        $startTime = $impersonatorData['impersonation_start_time'] ?? time();
        $endTime = time();
        return round(($endTime - $startTime) / 60, 2);
    }

    public function status(Request $request, $id)
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


    public function showNotificationSingleForm($id)
    {
        $user = User::findOrFail($id);
        if (!gs('en') && !gs('sn') && !gs('pn')) {
            $notify[] = ['warning', 'Notification options are disabled currently'];
            return to_route('admin.users.detail', $user->id)->withNotify($notify);
        }
        $pageTitle = 'Send Notification to ' . $user->username;
        return view('admin.users.notification_single', compact('pageTitle', 'user'));
    }

    public function sendNotificationSingle(Request $request, $id)
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

    public function showNotificationAllForm()
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

    public function sendNotificationAll(Request $request)
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


    private function sessionForNotification($totalUserCount, $request)
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
    }

    public function countBySegment($methodName)
    {
        return User::active()->$methodName()->count();
    }

    public function list()
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
    }

    public function notificationLog($id)
    {
        $user      = User::findOrFail($id);
        $pageTitle = 'Notifications Sent to ' . $user->username;
        $logs      = NotificationLog::where('user_id', $id)->with('user')->orderBy('id', 'desc')->paginate(getPaginate());
        return view('admin.reports.notification_history', compact('pageTitle', 'logs', 'user'));
    }

    public function tree($username)
    {

        $user = User::where('username', $username)->first();

        if ($user) {
            $tree      = showTreePage($user->id);
            $pageTitle = "Tree of " . $user->fullname;
            return view('admin.users.tree', compact('tree', 'pageTitle'));
        }

        $notify[] = ['error', 'Tree Not Found!!'];
        return redirect()->route('admin.dashboard')->withNotify($notify);
    }

    public function otherTree(Request $request, $username = null)
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
    }

    public function userRef($id)
    {
        $user      = User::findOrFail($id);
        $pageTitle = 'Referred By ' . $user->username;
        $users     = User::searchable(['username', 'email'])->where('ref_by', $id)->latest()->paginate(getPaginate());
        return view('admin.users.list', compact('pageTitle', 'users'));
    }


    public function matchingUpdate(Request $request)
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
