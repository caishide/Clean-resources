<?php

namespace App\Http\Controllers\User;

use App\Models\Form;
use App\Models\User;
use App\Models\BvLog;
use App\Models\Order;
use App\Models\Deposit;
use App\Models\Product;
use App\Constants\Status;
use App\Lib\FormProcessor;
use App\Models\PendingBonus;
use App\Models\WeeklySettlement;
use App\Models\WeeklySettlementUserSummary;
use App\Models\QuarterlySettlement;
use App\Models\DividendLog;
use App\Models\UserPointsLog;
use App\Models\UserAsset;
use App\Models\Withdrawal;
use App\Models\DeviceToken;
use App\Models\Transaction;
use App\Services\PointsService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Lib\GoogleAuthenticator;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
    public function home()
    {
        $pageTitle        = 'user.dashboard';
        $totalDeposit     = Deposit::where('user_id', auth()->id())->where('status', 1)->sum('amount');
        $totalWithdraw    = Withdrawal::where('user_id', auth()->id())->where('status', 1)->sum('amount');
        $completeWithdraw = Withdrawal::where('user_id', auth()->id())->where('status', 1)->count();
        $pendingWithdraw  = Withdrawal::where('user_id', auth()->id())->where('status', 2)->count();
        $totalRef         = User::where('ref_by', auth()->id())->count();
        $totalBvCut       = BvLog::where('user_id', auth()->id())->where('trx_type', '-')->sum('amount');
        return view('Template::user.dashboard', compact('pageTitle', 'totalDeposit', 'totalWithdraw', 'completeWithdraw', 'pendingWithdraw', 'totalRef', 'totalBvCut'));
    }

    public function depositHistory(Request $request)
    {
        $pageTitle = 'user.deposit_history';
        $deposits  = auth()->user()->deposits()->searchable(['trx'])->with(['gateway'])->orderBy('id', 'desc')->paginate(getPaginate());
        return view('Template::user.deposit_history', compact('pageTitle', 'deposits'));
    }

    public function show2faForm()
    {
        $ga        = new GoogleAuthenticator();
        $user      = auth()->user();
        $secret    = $ga->createSecret();
        $qrCodeUrl = $ga->getQRCodeGoogleUrl($user->username . '@' . gs('site_name'), $secret);
        $pageTitle = 'user.two_fa_security';
        return view('Template::user.twofactor', compact('pageTitle', 'secret', 'qrCodeUrl'));
    }

    public function create2fa(Request $request)
    {
        $user = auth()->user();
        $request->validate([
            'key'  => 'required',
            'code' => 'required',
        ]);
        $response = verifyG2fa($user, $request->code, $request->key);
        if ($response) {
            $user->tsc = $request->key;
            $user->ts  = Status::ENABLE;
            $user->save();
            $notify[] = ['success', 'Two factor authenticator activated successfully'];
            return back()->withNotify($notify);
        } else {
            $notify[] = ['error', 'Wrong verification code'];
            return back()->withNotify($notify);
        }
    }

    public function disable2fa(Request $request)
    {
        $request->validate([
            'code' => 'required',
        ]);

        $user     = auth()->user();
        $response = verifyG2fa($user, $request->code);
        if ($response) {
            $user->tsc = null;
            $user->ts  = Status::DISABLE;
            $user->save();
            $notify[] = ['success', 'Two factor authenticator deactivated successfully'];
        } else {
            $notify[] = ['error', 'Wrong verification code'];
        }
        return back()->withNotify($notify);
    }

    public function transactions()
    {
        $pageTitle    = 'Transactions';
        $remarks      = Transaction::where('user_id', auth()->id())->distinct('remark')->orderBy('remark')->whereNotNull('remark')->get('remark');
        $transactions = Transaction::where('user_id', auth()->id())->searchable(['trx'])->filter(['trx_type', 'remark'])->orderBy('id', 'desc')->paginate(getPaginate());
        return view('Template::user.transactions', compact('pageTitle', 'transactions', 'remarks'));
    }

    public function kycForm()
    {
        if (auth()->user()->kv == Status::KYC_PENDING) {
            $notify[] = ['error', 'Your KYC is under review'];
            return to_route('user.home')->withNotify($notify);
        }
        if (auth()->user()->kv == Status::KYC_VERIFIED) {
            $notify[] = ['error', 'You are already KYC verified'];
            return to_route('user.home')->withNotify($notify);
        }
        $pageTitle = 'user.kyc_form';
        $form      = Form::where('act', 'kyc')->first();
        return view('Template::user.kyc.form', compact('pageTitle', 'form'));
    }

    public function kycData()
    {
        $user      = auth()->user();
        $pageTitle = 'user.kyc_document';
        abort_if($user->kv == Status::VERIFIED, 403);
        return view('Template::user.kyc.info', compact('pageTitle', 'user'));
    }

    public function kycSubmit(Request $request)
    {
        $form           = Form::where('act', 'kyc')->firstOrFail();
        $formData       = $form->form_data;
        $formProcessor  = new FormProcessor();
        $validationRule = $formProcessor->valueValidation($formData);
        $request->validate($validationRule);
        $user = auth()->user();
        foreach (@$user->kyc_data ?? [] as $kycData) {
            if ($kycData->type == 'file') {
                fileManager()->removeFile(getFilePath('verify') . '/' . $kycData->value);
            }
        }
        $userData                   = $formProcessor->processFormData($request, $formData);
        $user->kyc_data             = $userData;
        $user->kyc_rejection_reason = null;
        $user->kv                   = Status::KYC_PENDING;
        $user->save();

        $notify[] = ['success', 'KYC data submitted successfully'];
        return to_route('user.home')->withNotify($notify);
    }

    public function userData()
    {
        $user = auth()->user();

        if ($user->profile_complete == Status::YES) {
            return to_route('user.home');
        }

        $pageTitle  = 'User Data';
        $info       = json_decode(json_encode(getIpInfo()), true);
        $mobileCode = @implode(',', $info['code']);
        $countries  = json_decode(file_get_contents(resource_path('views/partials/country.json')));

        return view('Template::user.user_data', compact('pageTitle', 'user', 'countries', 'mobileCode'));
    }

    public function userDataSubmit(Request $request)
    {

        $user = auth()->user();

        if ($user->profile_complete == Status::YES) {
            return to_route('user.home');
        }

        $countryData  = (array)json_decode(file_get_contents(resource_path('views/partials/country.json')));
        $countryCodes = implode(',', array_keys($countryData));
        $mobileCodes  = implode(',', array_column($countryData, 'dial_code'));
        $countries    = implode(',', array_column($countryData, 'country'));

        $request->validate([
            'country_code' => 'required|in:' . $countryCodes,
            'country'      => 'required|in:' . $countries,
            'mobile_code'  => 'required|in:' . $mobileCodes,
            'username'     => 'required|unique:users|min:6',
            'mobile'       => ['required', 'regex:/^([0-9]*)$/', Rule::unique('users')->where('dial_code', $request->mobile_code)],
        ]);


        if (preg_match("/[^a-z0-9_]/", trim($request->username))) {
            $notify[] = ['info', 'Username can contain only small letters, numbers and underscore.'];
            $notify[] = ['error', 'No special character, space or capital letters in username.'];
            return back()->withNotify($notify)->withInput($request->all());
        }

        $user->country_code = $request->country_code;
        $user->mobile       = $request->mobile;
        $user->username     = $request->username;


        $user->address      = $request->address;
        $user->city         = $request->city;
        $user->state        = $request->state;
        $user->zip          = $request->zip;
        $user->country_name = @$request->country;
        $user->dial_code    = $request->mobile_code;

        $user->profile_complete = Status::YES;
        $user->save();

        return to_route('user.home');
    }


    public function addDeviceToken(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'token' => 'required',
        ]);

        if ($validator->fails()) {
            return ['success' => false, 'errors' => $validator->errors()->all()];
        }

        $deviceToken = DeviceToken::where('token', $request->token)->first();

        if ($deviceToken) {
            return ['success' => true, 'message' => 'Already exists'];
        }

        $deviceToken          = new DeviceToken();
        $deviceToken->user_id = auth()->user()->id;
        $deviceToken->token   = $request->token;
        $deviceToken->is_app  = Status::NO;
        $deviceToken->save();

        return ['success' => true, 'message' => 'Token saved successfully'];
    }

    public function downloadAttachment($fileHash)
    {
        try {
            // 🔒 修复IDOR漏洞：检查用户是否有权限下载此文件
            // 获取当前用户
            $user = auth()->user();

            if (!$user) {
                $notify[] = ['error', 'Please login to download files'];
                return back()->withNotify($notify);
            }

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

            // 🔒 修复IDOR漏洞：验证文件属于当前用户或用户有权限访问
            // 检查文件路径中是否包含用户ID（根据实际存储策略调整）
            $filename = basename($realPath);
            $userIdFromPath = $this->extractUserIdFromFilePath($realPath);

            if ($userIdFromPath && $userIdFromPath != $user->id) {
                Log::channel('security')->warning('Unauthorized file download attempt', [
                    'file_path' => $realPath,
                    'user_id' => auth()->id(),
                    'attempted_user_id' => $userIdFromPath,
                    'ip' => request()->ip()
                ]);
                $notify[] = ['error', 'You do not have permission to download this file'];
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

    /**
     * 🔒 从文件路径中提取用户ID
     *
     * @param string $filePath 文件路径
     * @return int|null
     */
    private function extractUserIdFromFilePath(string $filePath): ?int
    {
        // 根据实际的文件存储策略来解析用户ID
        // 示例：如果文件存储在 user_attachments/{user_id}/ 目录下
        $pathParts = explode('/', dirname($filePath));
        $lastPart = end($pathParts);

        // 检查是否是数字（用户ID）
        if (is_numeric($lastPart)) {
            return (int) $lastPart;
        }

        // 其他解析策略...
        // 例如：从文件名中提取用户ID
        // $filename = basename($filePath);
        // if (preg_match('/user_(\d+)_/', $filename, $matches)) {
        //     return (int) $matches[1];
        // }

        return null;
    }

    public function purchase(Request $request)
    {
        $request->validate([
            'quantity'   => 'required|integer|gt:0',
            'product_id' => 'required|integer|gt:0'
        ]);

        $product = Product::hasCategory()->active()->find($request->product_id);

        if (!$product) {
            $notify[] = ['error', 'Product not found'];
            return back()->withNotify($notify);
        }

        if ($request->quantity > $product->quantity) {
            $notify[] = ['error', 'Requested quantity is not available in stock'];
            return back()->withNotify($notify);
        }
        $user       = auth()->user();
        $totalPrice = $product->price * $request->quantity;
        if ($user->balance < $totalPrice) {
            $notify[] = ['error', 'Balance is not sufficient'];
            return back()->withNotify($notify);
        }
        $user->balance -= $totalPrice;
        $user->save();

        $product->quantity -= $request->quantity;
        $product->save();

        $transaction               = new Transaction();
        $transaction->user_id      = $user->id;
        $transaction->amount       = $totalPrice;
        $transaction->post_balance = $user->balance;
        $transaction->charge       = 0;
        $transaction->trx_type     = '-';
        $transaction->details      = $product->name . ' item purchase';
        $transaction->trx          = getTrx();
        $transaction->save();

        $order              = new Order();
        $order->user_id     = $user->id;
        $order->product_id  = $product->id;
        $order->quantity    = $request->quantity;
        $order->price       = $product->price;
        $order->total_price = $totalPrice;
        $order->trx         = $transaction->trx;
        $order->status      = 0;
        $order->save();

        notify($user, 'ORDER_PLACED', [
            'product_name' => $product->name,
            'quantity'     => $request->quantity,
            'price'        => showAmount($product->price, currencyFormat: false),
            'total_price'  => showAmount($totalPrice, currencyFormat: false),
            'trx'          => $transaction->trx,
        ]);

        $notify[] = ['success', 'Order placed successfully'];
        return back()->withNotify($notify);
    }

    public function indexTransfer()
    {
        $pageTitle = 'user.balance_transfer';
        return view('Template::user.balanceTransfer', compact('pageTitle'));
    }

    public function searchUser(Request $request)
    {
        $transUser = User::where('username', $request->username)->orwhere('email', $request->username)->count();
        if ($transUser >= 1) {
            return response()->json(['success' => true]);
        } else {
            return response()->json(['success' => false]);
        }
    }

    public function balanceTransfer(Request $request)
    {
        $request->validate([
            'username' => 'required',
            'amount'   => 'required|numeric|min:0',
        ]);

        $general = gs();

        $user      = User::find(auth()->id());
        $transUser = User::where('username', $request->username)->orWhere('email', $request->username)->first();

        if ($transUser == '') {
            $notify[] = ['error', 'Username not found'];
            return back()->withNotify($notify);
        }
        if ($transUser->username == $user->username) {
            $notify[] = ['error', 'Balance transfer not possible in your own account'];
            return back()->withNotify($notify);
        }
        if ($transUser->email == $user->email) {
            $notify[] = ['error', 'Balance transfer not possible in your own account'];
            return back()->withNotify($notify);
        }

        if ($user->balance < 0) {
            $notify[] = ['error', '余额为负，暂不可转账'];
            return back()->withNotify($notify);
        }

        $charge = $general->bal_trans_fixed_charge + (($request->amount * $general->bal_trans_per_charge) / 100);
        $amount = $request->amount + $charge;

        if ($user->balance < $amount) {
            $notify[] = ['error', 'Insufficient Balance'];
            return back()->withNotify($notify);
        }

        $user->balance -= $amount;
        $user->save();

        $trx = getTrx();

        $transaction               = new Transaction();
        $transaction->trx          = $trx;
        $transaction->user_id      = $user->id;
        $transaction->trx_type     = '-';
        $transaction->remark       = 'balance_transfer';
        $transaction->details      = 'Balance transferred to ' . $transUser->username;
        $transaction->amount       = $request->amount;
        $transaction->post_balance = $user->balance;
        $transaction->charge       = $charge;
        $transaction->save();

        notify($user, 'BAL_SEND', [
            'amount'      => showAmount($request->amount, currencyFormat: false),
            'username'    => $transUser->username,
            'trx'         => $trx,
            'charge'      => showAmount($charge, currencyFormat: false),
            'balance_now' => showAmount($user->balance, currencyFormat: false),
        ]);

        $transUser->balance += $request->amount;
        $transUser->save();

        $transaction               = new Transaction();
        $transaction->trx          = $trx;
        $transaction->user_id      = $transUser->id;
        $transaction->remark       = 'balance_receive';
        $transaction->details      = 'Balance receive From ' . $user->username;
        $transaction->amount       = $request->amount;
        $transaction->post_balance = $transUser->balance;
        $transaction->charge       = 0;
        $transaction->trx_type     = '+';
        $transaction->save();

        notify($transUser, 'BAL_RECEIVE', [
            'amount'      => showAmount($request->amount, currencyFormat: false),
            'trx'         => $trx,
            'username'    => $user->username,
            'charge'      => 0,
            'balance_now' => showAmount($transUser->balance, currencyFormat: false),
        ]);

        $notify[] = ['success', 'Balance Transferred Successfully.'];
        return back()->withNotify($notify);
    }

    public function orders()
    {
        $pageTitle = 'user.orders';
        $orders    = Order::where('user_id', auth()->user()->id)->with('product')->orderBy('id', 'desc')->paginate(getPaginate());
        return view('Template::user.orders', compact('pageTitle', 'orders'));
    }

    public function binarySummery()
    {
        $pageTitle = 'user.binary_summary';
        $user      = auth()->user();
        return view('Template::user.binarySummery', compact('pageTitle', 'user'));
    }

    public function bonusCenter(Request $request)
    {
        $pageTitle = '奖金中心';
        $type = $request->input('type', 'direct');

        $remarkMap = [
            'direct' => ['direct_bonus'],
            'level_pair' => ['level_pair_bonus'],
            'pair' => ['pair_bonus'],
            'matching' => ['matching_bonus'],
            'dividend' => ['stockist_dividend', 'leader_dividend'],
        ];

        $transactions = collect();
        $pendingBonuses = collect();

        if ($type === 'pending') {
            $pendingBonuses = PendingBonus::where('recipient_id', auth()->id())
                ->orderByDesc('id')
                ->paginate(getPaginate());
        } else {
            $remarks = $remarkMap[$type] ?? $remarkMap['direct'];
            $transactions = Transaction::where('user_id', auth()->id())
                ->whereIn('remark', $remarks)
                ->orderByDesc('id')
                ->paginate(getPaginate());
        }

        return view('Template::user.bonus_center', compact('pageTitle', 'type', 'transactions', 'pendingBonuses'));
    }

    public function weeklySettlements()
    {
        $pageTitle = '周结算';
        $summaries = WeeklySettlementUserSummary::where('user_id', auth()->id())
            ->orderByDesc('week_key')
            ->paginate(getPaginate());

        $settlements = WeeklySettlement::whereIn('week_key', $summaries->pluck('week_key')->all())
            ->get()
            ->keyBy('week_key');

        return view('Template::user.weekly_settlements', compact('pageTitle', 'summaries', 'settlements'));
    }

    public function weeklySettlementShow(string $weekKey)
    {
        $pageTitle = '周结算详情';
        $summary = WeeklySettlementUserSummary::where('user_id', auth()->id())
            ->where('week_key', $weekKey)
            ->first();
        $settlement = WeeklySettlement::where('week_key', $weekKey)->first();

        return view('Template::user.weekly_settlement_show', compact('pageTitle', 'summary', 'settlement', 'weekKey'));
    }

    public function quarterlyDividends()
    {
        $pageTitle = '季度分红';
        $logs = DividendLog::where('user_id', auth()->id())
            ->orderByDesc('quarter_key')
            ->paginate(getPaginate());

        $settlements = QuarterlySettlement::whereIn('quarter_key', $logs->pluck('quarter_key')->all())
            ->get()
            ->keyBy('quarter_key');

        return view('Template::user.quarterly_dividends', compact('pageTitle', 'logs', 'settlements'));
    }

    public function pointsCenter()
    {
        $pageTitle = '莲子积分';
        $logs = UserPointsLog::where('user_id', auth()->id())
            ->orderByDesc('id')
            ->paginate(getPaginate());

        $asset = UserAsset::where('user_id', auth()->id())->first();
        $byType = UserPointsLog::where('user_id', auth()->id())
            ->selectRaw('source_type, SUM(points) as total_points')
            ->groupBy('source_type')
            ->get()
            ->keyBy('source_type');

        $todayKey = now()->toDateString();
        $checkedIn = UserPointsLog::where('user_id', auth()->id())
            ->where('source_type', 'DAILY')
            ->where('source_id', $todayKey)
            ->exists();

        return view('Template::user.points_center', compact('pageTitle', 'logs', 'asset', 'byType', 'checkedIn'));
    }

    public function dailyCheckIn(PointsService $pointsService)
    {
        $result = $pointsService->creditDailyCheckIn(auth()->id());

        if (($result['status'] ?? null) === 'success') {
            $notify[] = ['success', '签到成功'];
        } else {
            $notify[] = ['error', $result['message'] ?? '签到失败'];
        }

        return back()->withNotify($notify);
    }
}
