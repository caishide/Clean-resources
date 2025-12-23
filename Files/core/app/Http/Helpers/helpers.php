<?php

use Carbon\Carbon;
use App\Lib\Captcha;
use App\Models\Plan;
use App\Models\User;
use App\Models\BvLog;
use App\Notify\Notify;
use App\Lib\ClientInfo;
use App\Lib\CurlRequest;
use App\Lib\FileManager;
use App\Models\Frontend;
use App\Constants\Status;
use App\Models\Extension;
use App\Models\UserExtra;
use Illuminate\Support\Str;
use App\Models\GeneralSetting;
use Laramin\Utility\VugiChugi;
use App\Lib\GoogleAuthenticator;
use App\Models\Transaction;
use Illuminate\Support\Facades\Cache;

function systemDetails()
{
    $system['name']          = 'binaryecom';
    $system['version']       = '2.0';
    $system['build_version'] = '5.0.9';
    return $system;
}

function slug($string)
{
    return Str::slug($string);
}

function verificationCode($length)
{
    if ($length == 0) return 0;
    $min = pow(10, $length - 1);
    $max = (int) ($min - 1) . '9';
    return random_int($min, $max);
}

function getNumber($length = 8)
{
    $characters       = '1234567890';
    $charactersLength = strlen($characters);
    $randomString     = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, $charactersLength - 1)];
    }
    return $randomString;
}


function activeTemplate($asset = false)
{
    $template = session('template') ?? gs('active_template');
    if ($asset) return 'assets/templates/' . $template . '/';
    return 'templates.' . $template . '.';
}

function activeTemplateName()
{
    $template = session('template') ?? gs('active_template');
    return $template;
}

function siteLogo($type = null)
{
    $name = $type ? "/logo_$type.png" : '/logo.png';
    return getImage(getFilePath('logoIcon') . $name);
}

function siteFavicon()
{
    return getImage(getFilePath('logoIcon') . '/favicon.png');
}

function loadReCaptcha()
{
    return Captcha::reCaptcha();
}

function loadCustomCaptcha($width = '100%', $height = 46, $bgColor = '#003')
{
    return Captcha::customCaptcha($width, $height, $bgColor);
}

function verifyCaptcha()
{
    return Captcha::verify();
}

function loadExtension($key)
{
    $extension = Extension::where('act', $key)->where('status', Status::ENABLE)->first();
    return $extension ? $extension->generateScript(): '';
}

function getTrx($length = 12)
{
    $characters       = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ123456789';
    $charactersLength = strlen($characters);
    $randomString     = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, $charactersLength - 1)];
    }
    return $randomString;
}

function getAmount($amount, $length = 2)
{
    $amount = round($amount ?? 0, $length);
    return $amount + 0;
}

function showAmount($amount, $decimal = 2, $separate = true, $exceptZeros = false, $currencyFormat = true)
{
    $separator = '';
    if ($separate) {
        $separator = ',';
    }
    $printAmount = number_format($amount, $decimal, '.', $separator);
    if ($exceptZeros) {
        $exp = explode('.', $printAmount);
        if ($exp[1] * 1 == 0) {
            $printAmount = $exp[0];
        } else {
            $printAmount = rtrim($printAmount, '0');
        }
    }
    if ($currencyFormat) {
        if (gs('currency_format') == Status::CUR_BOTH) {
            return gs('cur_sym') . $printAmount . ' ' . __(gs('cur_text'));
        } elseif (gs('currency_format') == Status::CUR_TEXT) {
            return $printAmount . ' ' . __(gs('cur_text'));
        } else {
            return gs('cur_sym') . $printAmount;
        }
    }
    return $printAmount;
}


function removeElement($array, $value)
{
    return array_diff($array, (is_array($value) ? $value : array($value)));
}

function cryptoQR($wallet)
{
    return "https://api.qrserver.com/v1/create-qr-code/?data=$wallet&size=300x300&ecc=m";
}

function keyToTitle($text)
{
    return ucfirst(preg_replace("/[^A-Za-z0-9 ]/", ' ', $text));
}


function titleToKey($text)
{
    return strtolower(str_replace(' ', '_', $text));
}


function strLimit($title = null, $length = 10)
{
    return Str::limit($title, $length);
}


function getIpInfo()
{
    $ipInfo = ClientInfo::ipInfo();
    return $ipInfo;
}


function osBrowser()
{
    $osBrowser = ClientInfo::osBrowser();
    return $osBrowser;
}


function getTemplates()
{
    $param['purchasecode'] = env("PURCHASECODE");
    $param['website']      = @$_SERVER['HTTP_HOST'] . @$_SERVER['REQUEST_URI'] . ' - ' . env("APP_URL");
    $url                   = VugiChugi::gttmp() . systemDetails()['name'];
    $response              = CurlRequest::curlPostContent($url, $param);
    if ($response) {
        return $response;
    } else {
        return null;
    }
}


function getPageSections($arr = false)
{
    $jsonUrl  = resource_path('views/') . str_replace('.', '/', activeTemplate()) . 'sections.json';
    $sections = json_decode(file_get_contents($jsonUrl));
    if ($arr) {
        $sections = json_decode(file_get_contents($jsonUrl), true);
        ksort($sections);
    }
    return $sections;
}


function getImage($image, $size = null, $defaultUser = false)
{
    $clean = '';
    if (file_exists($image) && is_file($image)) {
        return asset($image) . $clean;
    }
    if ($defaultUser) {
        return asset('assets/images/default-user.png');
    }
    if ($size) {
        return route('placeholder.image', $size);
    }
    return asset('assets/images/default.png');
}


function notify($user, $templateName, $shortCodes = null, $sendVia = null, $createLog = true, $pushImage = null)
{
    $globalShortCodes = [
        'site_name'       => gs('site_name'),
        'site_currency'   => gs('cur_text'),
        'currency_symbol' => gs('cur_sym'),
    ];

    if (gettype($user) == 'array') {
        $user = (object) $user;
    }

    $shortCodes = array_merge($shortCodes ?? [], $globalShortCodes);

    $notify               = new Notify($sendVia);
    $notify->templateName = $templateName;
    $notify->shortCodes   = $shortCodes;
    $notify->user         = $user;
    $notify->createLog    = $createLog;
    $notify->pushImage    = $pushImage;
    $notify->userColumn   = isset($user->id) ? $user->getForeignKey() : 'user_id';
    $notify->send();
}

function getPaginate($paginate = null)
{
    if (!$paginate) {
        $paginate = gs('paginate_number');
    }
    return $paginate;
}

function paginateLinks($data)
{
    return $data->appends(request()->all())->links();
}


function menuActive($routeName, $type = null, $param = null)
{
    if     ($type == 3) $class = 'side-menu--open';
    elseif ($type == 2) $class = 'sidebar-submenu__open';
    else   $class              = 'active';

    if (is_array($routeName)) {
        foreach ($routeName as $key => $value) {
            if (request()->routeIs($value)) return $class;
        }
    } elseif (request()->routeIs($routeName)) {
        if ($param) {
            $routeParam = array_values(@request()->route()->parameters ?? []);
            if (strtolower(@$routeParam[0]) == strtolower($param)) return $class;
            else return;
        }
        return $class;
    }
}


function fileUploader($file, $location, $size = null, $old = null, $thumb = null, $filename = null)
{
    $fileManager           = new FileManager($file);
    $fileManager->path     = $location;
    $fileManager->size     = $size;
    $fileManager->old      = $old;
    $fileManager->thumb    = $thumb;
    $fileManager->filename = $filename;
    $fileManager->upload();
    return $fileManager->filename;
}

function fileManager()
{
    return new FileManager();
}

function getFilePath($key)
{
    return fileManager()->$key()->path;
}

function getFileSize($key)
{
    return fileManager()->$key()->size;
}

function getThumbSize($key)
{
    return fileManager()->$key()->thumb;
}

function getFileExt($key)
{
    return fileManager()->$key()->extensions;
}

function diffForHumans($date)
{
    $lang = session()->get('lang');
    Carbon::setlocale($lang);
    return Carbon::parse($date)->diffForHumans();
}


function showDateTime($date, $format = 'Y-m-d h:i A')
{
    if (!$date) {
        return '-';
    }
    $lang = session()->get('lang');
    Carbon::setlocale($lang ?? 'en');

    return Carbon::parse($date)->translatedFormat($format);
}


function getContent($dataKeys, $singleQuery = false, $limit = null, $orderById = false)
{

    $templateName = activeTemplateName();
    if ($singleQuery) {
        $content = Frontend::where('tempname', $templateName)->where('data_keys', $dataKeys)->orderBy('id', 'desc')->first();
    } else {
        $article = Frontend::where('tempname', $templateName);
        $article->when($limit != null, function ($q) use ($limit) {
            return $q->limit($limit);
        });
        if ($orderById) {
            $content = $article->where('data_keys', $dataKeys)->orderBy('id')->get();
        } else {
            $content = $article->where('data_keys', $dataKeys)->orderBy('id', 'desc')->get();
        }
    }
    return $content;
}

function verifyG2fa($user, $code, $secret = null)
{
    $authenticator = new GoogleAuthenticator();
    if (!$secret) {
        $secret = $user->tsc;
    }
    $oneCode  = $authenticator->getCode($secret);
    $userCode = $code;
    if ($oneCode == $userCode) {
        $user->tv = Status::YES;
        $user->save();
        return true;
    } else {
        return false;
    }
}


function urlPath($routeName, $routeParam = null)
{
    if ($routeParam == null) {
        $url = route($routeName);
    } else {
        $url = route($routeName, $routeParam);
    }
    $basePath = route('home');
    $path     = str_replace($basePath, '', $url);
    return $path;
}


function showMobileNumber($number)
{
    $length = strlen($number);
    return substr_replace($number, '***', 2, $length - 4);
}

function showEmailAddress($email)
{
    $endPosition = strpos($email, '@') - 1;
    return substr_replace($email, '***', 1, $endPosition);
}


function getRealIP()
{
    $ip = $_SERVER["REMOTE_ADDR"];
      //Deep detect ip
    if (filter_var(@$_SERVER['HTTP_FORWARDED'], FILTER_VALIDATE_IP)) {
        $ip = $_SERVER['HTTP_FORWARDED'];
    }
    if (filter_var(@$_SERVER['HTTP_FORWARDED_FOR'], FILTER_VALIDATE_IP)) {
        $ip = $_SERVER['HTTP_FORWARDED_FOR'];
    }
    if (filter_var(@$_SERVER['HTTP_X_FORWARDED_FOR'], FILTER_VALIDATE_IP)) {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
    }
    if (filter_var(@$_SERVER['HTTP_CLIENT_IP'], FILTER_VALIDATE_IP)) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    }
    if (filter_var(@$_SERVER['HTTP_X_REAL_IP'], FILTER_VALIDATE_IP)) {
        $ip = $_SERVER['HTTP_X_REAL_IP'];
    }
    if (filter_var(@$_SERVER['HTTP_CF_CONNECTING_IP'], FILTER_VALIDATE_IP)) {
        $ip = $_SERVER['HTTP_CF_CONNECTING_IP'];
    }
    if ($ip == '::1') {
        $ip = '127.0.0.1';
    }

    return $ip;
}


function appendQuery($key, $value)
{
    return request()->fullUrlWithQuery([$key => $value]);
}

function dateSort($a, $b)
{
    return strtotime($a) - strtotime($b);
}

function dateSorting($arr)
{
    usort($arr, "dateSort");
    return $arr;
}

function gs($key = null)
{
    try {
        $general = Cache::get('GeneralSetting');
        if (!$general) {
            $general = GeneralSetting::first();
            Cache::put('GeneralSetting', $general);
        }
        if ($key) return @$general->$key;
        return $general;
    } catch (\Exception $e) {
        // 如果缓存系统不可用，直接查询数据库
        $general = GeneralSetting::first();
        if ($key) return @$general->$key;
        return $general;
    }
}
function isImage($string)
{
    $allowedExtensions = array('jpg', 'jpeg', 'png', 'gif');
    $fileExtension     = pathinfo($string, PATHINFO_EXTENSION);
    if (in_array($fileExtension, $allowedExtensions)) {
        return true;
    } else {
        return false;
    }
}

function isHtml($string)
{
    if (preg_match('/<.*?>/', $string)) {
        return true;
    } else {
        return false;
    }
}


function convertToReadableSize($size)
{
    preg_match('/^(\d+)([KMG])$/', $size, $matches);
    $size = (int)$matches[1];
    $unit = $matches[2];

    if ($unit == 'G') {
        return $size . 'GB';
    }

    if ($unit == 'M') {
        return $size . 'MB';
    }

    if ($unit == 'K') {
        return $size . 'KB';
    }

    return $size . $unit;
}


function frontendImage($sectionName, $image, $size = null, $seo = false)
{
    if ($seo) {
        return getImage('assets/images/frontend/' . $sectionName . '/seo/' . $image, $size);
    }
    return getImage('assets/images/frontend/' . $sectionName . '/' . $image, $size);
}


function shortDescription($string, $length = 120)
{
    return Illuminate\Support\Str::limit($string, $length);
}

function updateBV($id, $bv, $details)
{
    while ($id != "" || $id != "0") {
        if (isUserExists($id)) {
            $posId = getPositionId($id);
            if ($posId == "0") {
                break;
            }
            $posUser = User::find($posId);
            if ($posUser->plan_id) {
                $position       = getPositionLocation($id);
                $extra          = UserExtra::where('user_id', $posId)->first();
                $bvLog          = new BvLog();
                $bvLog->user_id = $posId;

                if ($position == 1) {
                    $extra->bv_left  += $bv;
                    $bvLog->position  = '1';
                } else {
                    $extra->bv_right += $bv;
                    $bvLog->position  = '2';
                }
                $extra->save();
                $bvLog->amount   = $bv;
                $bvLog->trx_type = '+';
                $bvLog->details  = $details;
                $bvLog->save();
            }
            $id = $posId;
        } else {
            break;
        }
    }
}

function isUserExists($id)
{
    $user = User::find($id);
    if ($user) {
        return true;
    } else {
        return false;
    }
}

function getPositionId($id)
{
    $user = User::find($id);

    if ($user) {
        return $user->pos_id;
    } else {
        return 0;
    }
}

function getPositionLocation($id)
{
    $user = User::find($id);
    if ($user) {
        return $user->position;
    } else {
        return 0;
    }
}

function getPosition($parentid, $position)
{
    $childid = getTreeChildId($parentid, $position);

    if ($childid != "-1") {
        $id = $childid;
    } else {
        $id = $parentid;
    }
    while ($id != "" || $id != "0") {
        if (isUserExists($id)) {
            $nextchildid = getTreeChildId($id, $position);
            if ($nextchildid == "-1") {
                break;
            } else {
                $id = $nextchildid;
            }
        } else break;
    }

    $res['pos_id']   = $id;
    $res['position'] = $position;
    return $res;
}

function getTreeChildId($parentid, $position)
{
    $cou = User::where('pos_id', $parentid)->where('position', $position)->count();
    $cid = User::where('pos_id', $parentid)->where('position', $position)->first();
    if ($cou == 1) {
        return $cid->id;
    } else {
        return -1;
    }
}

function mlmPositions()
{
    return array(
        '1' => 'Left',
        '2' => 'Right',
    );
}

function updateFreeCount($id)
{
    while ($id != "" || $id != "0") {
        if (isUserExists($id)) {
            $posid = getPositionId($id);
            if ($posid == "0") {
                break;
            }
            $position = getPositionLocation($id);

            $extra = UserExtra::where('user_id', $posid)->first();

            if ($position == 1) {
                $extra->free_left += 1;
            } else {
                $extra->free_right += 1;
            }
            $extra->save();

            $id = $posid;
        } else {
            break;
        }
    }
}

function showTreePage($id)
{
    $res      = array_fill_keys(array('b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j', 'k', 'l', 'm', 'n', 'o'), null);
    $res['a'] = User::find($id);

    $res['b'] = getPositionUser($id, 1);
    if ($res['b']) {
        $res['d'] = getPositionUser($res['b']->id, 1);
        $res['e'] = getPositionUser($res['b']->id, 2);
    }
    if ($res['d']) {
        $res['h'] = getPositionUser($res['d']->id, 1);
        $res['i'] = getPositionUser($res['d']->id, 2);
    }
    if ($res['e']) {
        $res['j'] = getPositionUser($res['e']->id, 1);
        $res['k'] = getPositionUser($res['e']->id, 2);
    }
    $res['c'] = getPositionUser($id, 2);
    if ($res['c']) {
        $res['f'] = getPositionUser($res['c']->id, 1);
        $res['g'] = getPositionUser($res['c']->id, 2);
    }
    if ($res['f']) {
        $res['l'] = getPositionUser($res['f']->id, 1);
        $res['m'] = getPositionUser($res['f']->id, 2);
    }
    if ($res['g']) {
        $res['n'] = getPositionUser($res['g']->id, 1);
        $res['o'] = getPositionUser($res['g']->id, 2);
    }
    return $res;
}

function getPositionUser($id, $position)
{
    return User::where('pos_id', $id)->where('position', $position)->first();
}

function showSingleUserinTree($user)
{
    $res = '';
    if ($user) {
        if ($user->plan_id == 0) {
            $userType = "free-user";
            $stShow   = "Free";
            $planName = '';
        } else {
            $userType = "paid-user";
            $stShow   = "Paid";
            $planName = $user->plan->name;
        }
        $img   = getImage('assets/images/user/profile/' . $user->image, '120x120', true);
        $refby = getUserById($user->ref_id)->fullname ?? '';
        if (auth()->guard('admin')->user()) {
            $hisTree = route('admin.users.other.tree', $user->username);
        } else {
            $hisTree = route('user.other.tree', $user->username);
        }

        $extraData  = " data-name=\"$user->fullname\"";
        $extraData .= " data-treeurl=\"$hisTree\"";
        $extraData .= " data-status=\"$stShow\"";
        $extraData .= " data-plan=\"$planName\"";
        $extraData .= " data-image=\"$img\"";
        $extraData .= " data-refby=\"$refby\"";
        $extraData .= " data-lpaid=\"" . @$user->userExtra->paid_left . "\"";
        $extraData .= " data-rpaid=\"" . @$user->userExtra->paid_right . "\"";
        $extraData .= " data-lfree=\"" . @$user->userExtra->free_left . "\"";
        $extraData .= " data-rfree=\"" . @$user->userExtra->free_right . "\"";
        $extraData .= " data-lbv=\"" . getAmount(@$user->userExtra->bv_left) . "\"";
        $extraData .= " data-rbv=\"" . getAmount(@$user->userExtra->bv_right) . "\"";
        $res       .= "<div class=\"user showDetails\" type=\"button\" $extraData>";
        $res       .= "<img src=\"$img\" alt=\"*\"  class=\"$userType\">";
        $res       .= "<p class=\"user-name\">$user->username</p>";
    } else {
        $img = getImage('assets/images/user/profile/', '120x120', true);

        $res .= "<div class=\"user\" type=\"button\">";
        $res .= "<img src=\"$img\" alt=\"*\"  class=\"no-user\">";
        $res .= "<p class=\"user-name\">No user</p>";
    }

    $res .= " </div>";
    $res .= " <span class=\"line\"></span>";

    return $res;
}

function getUserById($id)
{
    return User::find($id);
}

function updatePaidCount($id)
{
    while ($id != "" || $id != "0") {
        if (isUserExists($id)) {
            $posid = getPositionId($id);
            if ($posid == "0") {
                break;
            }
            $position = getPositionLocation($id);
            $extra    = UserExtra::where('user_id', $posid)->first();

            if ($position == 1) {
                $extra->free_left -= 1;
                $extra->paid_left += 1;
            } else {
                $extra->free_right -= 1;
                $extra->paid_right += 1;
            }
            $extra->save();
            $id = $posid;
        } else {
            break;
        }
    }
}

function treeComission($id, $amount, $details)
{
    while ($id != "" || $id != "0") {
        if (isUserExists($id)) {
            $posid = getPositionId($id);
            if ($posid == "0") {
                break;
            }

            $posUser = User::find($posid);
            if ($posUser->plan_id != 0) {

                $posUser->balance          += $amount;
                $posUser->total_binary_com += $amount;
                $posUser->save();

                $transaction               = new Transaction();
                $transaction->amount       = $posUser->id;
                $transaction->user_id      = $amount;
                $transaction->charge       = 0;
                $transaction->trx_type     = '+';
                $transaction->details      = $details;
                $transaction->remark       = 'binary_commission';
                $transaction->trx          = getTrx();
                $transaction->post_balance = $posUser->balance;
                $transaction->save();
            }
            $id = $posid;
        } else {
            break;
        }
    }
}


function referralComission($user_id, $details)
{
    $user  = User::find($user_id);
    $refer = User::find($user->ref_by);
    if ($refer) {
        $plan = Plan::find($refer->plan_id);
        if ($plan) {
            $amount                = $plan->ref_com;
            $refer->balance       += $amount;
            $refer->total_ref_com += $amount;
            $refer->save();

            $transaction               = new Transaction();
            $transaction->user_id      = $refer->id;
            $transaction->amount       = $amount;
            $transaction->charge       = 0;
            $transaction->trx_type     = '+';
            $transaction->details      = $details;
            $transaction->remark       = 'referral_commission';
            $transaction->trx          = getTrx();
            $transaction->post_balance = $refer->balance;
            $transaction->save();

            notify($refer, 'REFERRAL_COMMISSION', [
                'trx'          => $transaction->trx,
                'amount'       => showAmount($amount, currencyFormat: false),
                'username'     => $user->username,
                'post_balance' => showAmount($refer->balance, currencyFormat: false),
            ]);
        }
    }
}

function createBVLog($user_id, $lr, $amount, $details)
{
    $bvlog           = new BvLog();
    $bvlog->user_id  = $user_id;
    $bvlog->position = $lr;
    $bvlog->amount   = $amount;
    $bvlog->trx_type = '-';
    $bvlog->details  = $details;
    $bvlog->save();
}

/**
 * 翻译菜单标题的辅助函数
 */
function translateMenuTitle($title, $key) {
    if (!is_string($title)) {
        return '';
    }
    // 映射菜单键到翻译键
    $translations = [
        'Dashboard' => 'admin.dashboard',
        'Manage Plan' => 'admin.manage_plan',
        'Manage Category' => 'admin.manage_category',
        'Manage Product' => 'admin.manage_product',
        '结算模拟' => 'admin.simulation',
        '待处理奖金审核' => 'admin.bonus_review',
        '调整批次' => 'admin.adjustment',
        '报表导出' => 'admin.exports',
        '奖金参数' => 'admin.bonus_config',
        'Manage Order' => 'admin.manage_order',
        'Manage Users' => 'admin.manage_users',
        'Active Users' => 'admin.active_users',
        'Banned Users' => 'admin.banned_users',
        'Email Unverified' => 'admin.email_unverified',
        'Mobile Unverified' => 'admin.mobile_unverified',
        'KYC Unverified' => 'admin.kyc_unverified',
        'KYC Pending' => 'admin.kyc_pending',
        'With Balance' => 'admin.with_balance',
        'Paid users' => 'admin.paid_users',
        'Free users' => 'admin.free_users',
        'All Users' => 'admin.all_users',
        'Send Notification' => 'admin.send_notification',
        'Deposits' => 'admin.deposits',
        'Pending Deposits' => 'admin.pending_deposits',
        'Approved Deposits' => 'admin.approved_deposits',
        'Successful Deposits' => 'admin.successful_deposits',
        'Rejected Deposits' => 'admin.rejected_deposits',
        'Initiated Deposits' => 'admin.initiated_deposits',
        'All Deposits' => 'admin.all_deposits',
        'Withdrawals' => 'admin.withdrawals',
        'Pending Withdrawals' => 'admin.pending_withdrawals',
        'Approved Withdrawals' => 'admin.approved_withdrawals',
        'Rejected Withdrawals' => 'admin.rejected_withdrawals',
        'All Withdrawals' => 'admin.all_withdrawals',
        'Support Ticket' => 'admin.support_ticket',
        'Pending Ticket' => 'admin.pending_ticket',
        'Closed Ticket' => 'admin.closed_ticket',
        'Answered Ticket' => 'admin.answered_ticket',
        'All Ticket' => 'admin.all_ticket',
        'Report' => 'admin.report',
        'Transaction History' => 'admin.transaction_history',
        'Invest History' => 'admin.invest_history',
        'Bv History' => 'admin.bv_history',
        'Referral Commission' => 'admin.referral_commission',
        'Binary Commission' => 'admin.binary_commission',
        'Login History' => 'admin.login_history',
        'Notification History' => 'admin.notification_history',
        'Notice' => 'admin.notice',
        'System Setting' => 'admin.system_setting',
        'Extra' => 'admin.extra',
        'Application' => 'admin.application',
        'Server' => 'admin.server',
        'Cache' => 'admin.cache',
        'Update' => 'admin.update',
        'Report & Request' => 'admin.report_and_request',
    ];

    // 如果有对应的翻译键，使用翻译
    if (isset($translations[$title])) {
        $translated = __($translations[$title]);
    } else {
        // 否则直接返回原标题
        $translated = __($title);
    }

    return is_string($translated) ? $translated : $title;
}

/**
 * 增强的防御性路由生成函数 - 多层防护
 */
function safeRoute($routeName, $params = null) {
    // 防御层1: 验证输入参数
    if (empty($routeName) || !is_string($routeName)) {
        error_log("SafeRoute: Invalid route name provided: " . var_export($routeName, true));
        return 'javascript:void(0)';
    }

    try {
        // 防御层2: 检查路由是否存在
        if (!Route::has($routeName)) {
            error_log("SafeRoute: Route not found: $routeName");
            return 'javascript:void(0)';
        }

        // 防御层3: 尝试生成URL并捕获异常
        $url = route($routeName, $params);

        // 防御层4: 验证生成的URL
        if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
            error_log("SafeRoute: Invalid URL generated for route: $routeName");
            return 'javascript:void(0)';
        }

        return $url;
    } catch (Exception $e) {
        // 记录详细错误信息用于调试
        error_log("SafeRoute Exception for route [$routeName]: " . $e->getMessage());
        return 'javascript:void(0)';
    }
}
