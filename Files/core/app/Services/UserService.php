<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserExtra;
use App\Models\UserLogin;
use App\Models\AdminNotification;
use App\Constants\Status;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

/**
 * UserService - User management business logic
 *
 * Encapsulates user-related operations to improve code organization and reusability.
 * All database operations are wrapped in transactions for data consistency.
 */
class UserService
{
    /**
     * Create a new user with all associated records
     *
     * @param array $data User registration data
     * @param array $position Position data (referrer info)
     * @return User
     * @throws \Exception
     */
    public function createUser(array $data, array $position): User
    {
        return DB::transaction(function () use ($data, $position) {
            // Create user
            $user = new User();
            $user->ref_by = $position['user_id'];
            $user->pos_id = $position['pos_id'];
            $user->position = $position['position'];
            $user->email = strtolower($data['email']);
            // Sanitize input to prevent XSS attacks
            $user->firstname = strip_tags($data['firstname']);
            $user->lastname = strip_tags($data['lastname']);
            $user->password = Hash::make($data['password']);
            $user->kv = gs('kv') ? Status::NO : Status::YES;
            $user->ev = gs('ev') ? Status::NO : Status::YES;
            $user->sv = gs('sv') ? Status::NO : Status::YES;
            $user->ts = Status::DISABLE;
            $user->tv = Status::ENABLE;
            $user->save();

            // Create admin notification
            $adminNotification = new AdminNotification();
            $adminNotification->user_id = $user->id;
            $adminNotification->title = 'New member registered';
            $adminNotification->click_url = urlPath('admin.users.detail', $user->id);
            $adminNotification->save();

            // Create login log
            $this->createLoginLog($user);

            return $user;
        });
    }

    /**
     * Update user profile
     *
     * @param User $user The user to update
     * @param array $data Profile data
     * @return User
     * @throws \Exception
     */
    public function updateProfile(User $user, array $data): User
    {
        return DB::transaction(function () use ($user, $data) {
            // Sanitize input to prevent XSS attacks
            $user->firstname = strip_tags($data['firstname']);
            $user->lastname = strip_tags($data['lastname']);
            $user->address = strip_tags($data['address'] ?? '');
            $user->city = strip_tags($data['city'] ?? '');
            $user->state = strip_tags($data['state'] ?? '');
            $user->zip = strip_tags($data['zip'] ?? '');

            $user->save();

            return $user;
        });
    }

    /**
     * Create user login log
     *
     * @param User $user The user
     * @return UserLogin
     */
    private function createLoginLog(User $user): UserLogin
    {
        $ip = getRealIP();
        $exist = UserLogin::where('user_ip', $ip)->first();
        $userLogin = new UserLogin();

        if ($exist) {
            $userLogin->longitude = $exist->longitude;
            $userLogin->latitude = $exist->latitude;
            $userLogin->city = $exist->city;
            $userLogin->country_code = $exist->country_code;
            $userLogin->country = $exist->country;
        } else {
            $info = json_decode(json_encode(getIpInfo()), true);
            $userLogin->longitude = @implode(',', $info['long']);
            $userLogin->latitude = @implode(',', $info['lat']);
            $userLogin->city = @implode(',', $info['city']);
            $userLogin->country_code = @implode(',', $info['code']);
            $userLogin->country = @implode(',', $info['country']);
        }

        $userAgent = osBrowser();
        $userLogin->user_id = $user->id;
        $userLogin->user_ip = $ip;
        $userLogin->browser = @$userAgent['browser'];
        $userLogin->os = @$userAgent['os_platform'];
        $userLogin->save();

        return $userLogin;
    }

    /**
     * Get user statistics with optimized aggregation
     *
     * @return object
     */
    public function getUserStatistics(): object
    {
        return User::selectRaw('
            COUNT(*) as total_users,
            SUM(CASE WHEN status = ? AND ev = ? AND sv = ? THEN 1 ELSE 0 END) as verified_users,
            SUM(CASE WHEN ev = ? THEN 1 ELSE 0 END) as email_unverified_users,
            SUM(CASE WHEN sv = ? THEN 1 ELSE 0 END) as mobile_unverified_users
        ', [
            Status::USER_ACTIVE, Status::VERIFIED, Status::VERIFIED,
            Status::UNVERIFIED, Status::UNVERIFIED
        ])->first();
    }
}
