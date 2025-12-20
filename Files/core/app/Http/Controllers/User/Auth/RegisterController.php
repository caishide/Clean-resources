<?php

namespace App\Http\Controllers\User\Auth;

use App\Models\User;
use App\Lib\Intended;
use App\Constants\Status;
use App\Models\UserExtra;
use App\Models\UserLogin;
use Illuminate\Http\Request;
use App\Models\AdminNotification;
use App\Http\Controllers\Controller;
use App\Http\Requests\User\UserRegistrationRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Illuminate\Foundation\Auth\RegistersUsers;

class RegisterController extends Controller
{

    use RegistersUsers;

    public function __construct()
    {
        parent::__construct();
    }

    public function showRegistrationForm(Request $request): \Illuminate\View\View
    {
        $pageTitle = "Register";
        if ($request->ref && $request->position) {

            $refUser = $this->resolveReferrerUser((string) $request->ref);
            if ($refUser == null) {
                $notify[] = ['error', 'Invalid Referral link.'];
                return redirect()->route('home')->withNotify($notify);
            }

            $position = $request->position == 'left' ? 1 : 2;
            $pos = getPosition($refUser->id, $position);

            $referrer = User::find($pos['pos_id']);

            if ($pos['position'] == 1)
                $getPosition = 'Left';
            else {
                $getPosition = 'Right';
            }

            $joining = "<span class='help-block2'><strong class='text--success'>You are joining under $referrer->username at $getPosition  </strong></span>";
        } else {
            $refUser = null;
            $joining = null;
            $position = null;
            $pos = null;
            $referrer = null;
            $getPosition = null;
        }

        Intended::identifyRoute();
        return view('Template::user.auth.register', compact('pageTitle', 'position', 'pos', 'refUser', 'referrer', 'getPosition', 'joining'));
    }

    public function register(UserRegistrationRequest $request): \Illuminate\Http\RedirectResponse
    {
        if (!gs('registration')) {
            return back();
        }

        $request->session()->regenerateToken();

        if (!verifyCaptcha()) {
            $notify[] = ['error', 'Invalid captcha provided'];
            return back()->withNotify($notify);
        }

        $validated = $request->validated();
        $placementId = (int) ($validated['placement_id'] ?? 0);
        if ($placementId > 0) {
            $refUser = $this->resolveReferrerUser((string) $validated['referBy']);
            if (!$refUser) {
                $notify[] = ['error', '推荐人不存在'];
                return back()->withNotify($notify)->withInput();
            }
            if (!$this->isPlacementInReferrerTree($refUser->id, $placementId)) {
                $notify[] = ['error', '安置ID不在推荐人团队内'];
                return back()->withNotify($notify)->withInput();
            }
            $position = (int) ($validated['position'] ?? 0);
            $occupied = User::where('pos_id', $placementId)->where('position', $position)->exists();
            if ($occupied) {
                $notify[] = ['error', '该安置位置不可用'];
                return back()->withNotify($notify)->withInput();
            }
        }

        event(new Registered($user = $this->create($validated)));

        $this->guard()->login($user);

        return $this->registered($request, $user) ?: redirect($this->redirectPath());
    }



    protected function create(array $data)
    {
        return DB::transaction(function () use ($data) {
            $userCheck = $this->resolveReferrerUser((string) ($data['referBy'] ?? ''));
            if (!$userCheck) {
                throw new \RuntimeException('推荐人不存在');
            }
            $placementId = (int) ($data['placement_id'] ?? 0);
            if ($placementId > 0) {
                $pos = [
                    'pos_id' => $placementId,
                    'position' => (int) $data['position'],
                ];
            } else {
                $pos = getPosition($userCheck->id, $data['position']);
            }

            //User Create
            $user            = new User();
            $user->ref_by       = $userCheck->id;
            $user->pos_id       = $pos['pos_id'];
            $user->position     = $pos['position'];
            $user->email     = strtolower($data['email']);
            // Sanitize input to prevent XSS attacks
            $user->firstname = strip_tags($data['firstname']);
            $user->lastname  = strip_tags($data['lastname']);
            $user->password  = Hash::make($data['password']);
            $user->kv = gs('kv') ? Status::NO : Status::YES;
            $user->ev = gs('ev') ? Status::NO : Status::YES;
            $user->sv = gs('sv') ? Status::NO : Status::YES;
            $user->ts = Status::DISABLE;
            $user->tv = Status::ENABLE;
            $user->save();

            $adminNotification            = new AdminNotification();
            $adminNotification->user_id   = $user->id;
            $adminNotification->title     = 'New member registered';
            $adminNotification->click_url = urlPath('admin.users.detail', $user->id);
            $adminNotification->save();


            //Login Log Create
            $ip        = getRealIP();
            $exist     = UserLogin::where('user_ip', $ip)->first();
            $userLogin = new UserLogin();

            if ($exist) {
                $userLogin->longitude    = $exist->longitude;
                $userLogin->latitude     = $exist->latitude;
                $userLogin->city         = $exist->city;
                $userLogin->country_code = $exist->country_code;
                $userLogin->country      = $exist->country;
            } else {
                $info                    = json_decode(json_encode(getIpInfo()), true);
            $userLogin->longitude    = @implode(',', $info['long']);
            $userLogin->latitude     = @implode(',', $info['lat']);
                $userLogin->city         = @implode(',', $info['city']);
                $userLogin->country_code = @implode(',', $info['code']);
                $userLogin->country      = @implode(',', $info['country']);
            }

            $userAgent          = osBrowser();
            $userLogin->user_id = $user->id;
            $userLogin->user_ip = $ip;

            $userLogin->browser = @$userAgent['browser'];
            $userLogin->os      = @$userAgent['os_platform'];
            $userLogin->save();

            return $user;
        });
    }

    private function resolveReferrerUser(string $input): ?User
    {
        $value = trim($input);
        if ($value === '') {
            return null;
        }
        $value = preg_replace('/\s+/', ' ', $value);

        if (ctype_digit($value)) {
            return User::find((int) $value);
        }

        return User::where('username', $value)
            ->orWhere('email', $value)
            ->orWhereRaw("concat(firstname, ' ', lastname) = ?", [$value])
            ->orWhereRaw("concat(lastname, ' ', firstname) = ?", [$value])
            ->first();
    }

    public function checkUser(Request $request): \Illuminate\Http\JsonResponse
    {
        $exist['data'] = false;
        $exist['type'] = null;
        if ($request->email) {
            $exist['data'] = User::where('email', $request->email)->exists();
            $exist['type'] = 'email';
            $exist['field'] = 'Email';
        }
        if ($request->mobile) {
            $exist['data'] = User::where('mobile', $request->mobile)->where('dial_code', $request->mobile_code)->exists();
            $exist['type'] = 'mobile';
            $exist['field'] = 'Mobile';
        }
        if ($request->username) {
            $exist['data'] = User::where('username', $request->username)->exists();
            $exist['type'] = 'username';
            $exist['field'] = 'Username';
        }
        return response($exist);
    }

    public function registered(Request $request, $user): \Illuminate\Http\RedirectResponse
    {
        DB::transaction(function () use ($user) {
            $user_extras = new UserExtra();
            $user_extras->user_id = $user->id;
            $user_extras->save();
            updateFreeCount($user->id);
        });
        return to_route('user.home');
    }

    private function isPlacementInReferrerTree(int $referrerId, int $placementId): bool
    {
        $current = $placementId;
        while ($current > 0) {
            if ($current === $referrerId) {
                return true;
            }
            $current = (int) getPositionId($current);
        }
        return false;
    }
}
