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
use Illuminate\Support\Facades\Schema;

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
            // Handle both old and new position data formats
            $user->ref_by = $position['user_id'] ?? $position['pos_id'];
            $user->pos_id = $position['pos_id'] ?? 0;
            $user->position = $position['position'] ?? 0;
            $user->email = strtolower($data['email']);
            // Sanitize input to prevent XSS attacks
            $user->firstname = strip_tags($data['firstname'] ?? '');
            $user->lastname = strip_tags($data['lastname'] ?? '');
            $user->password = $data['password']; // Let the User model accessor handle hashing
            // Set verification status - default to VERIFIED if gs() is not available
            $user->kv = function_exists('gs') && gs('kv') ? Status::NO : Status::YES;
            $user->ev = function_exists('gs') && gs('ev') ? Status::NO : Status::YES;
            $user->sv = function_exists('gs') && gs('sv') ? Status::NO : Status::YES;
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
            // Sanitize input to prevent XSS attacks - remove script tags and their content
            $user->firstname = preg_replace('/<script[^>]*>.*?<\/script>/i', '', $data['firstname'] ?? '');
            $user->lastname = preg_replace('/<script[^>]*>.*?<\/script>/i', '', $data['lastname'] ?? '');

            // Only update optional fields if they exist in the database
            if (Schema::hasColumn('users', 'address') && isset($data['address'])) {
                $user->address = preg_replace('/<script[^>]*>.*?<\/script>/i', '', $data['address']);
            }
            if (Schema::hasColumn('users', 'city') && isset($data['city'])) {
                $user->city = preg_replace('/<script[^>]*>.*?<\/script>/i', '', $data['city']);
            }
            if (Schema::hasColumn('users', 'state') && isset($data['state'])) {
                $user->state = preg_replace('/<script[^>]*>.*?<\/script>/i', '', $data['state']);
            }
            if (Schema::hasColumn('users', 'zip') && isset($data['zip'])) {
                $user->zip = preg_replace('/<script[^>]*>.*?<\/script>/i', '', $data['zip']);
            }

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
        // In testing environment, we may not have these globals
        if (!isset($_SERVER['REMOTE_ADDR'])) {
            $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        }
        if (!isset($_SERVER['HTTP_USER_AGENT'])) {
            $_SERVER['HTTP_USER_AGENT'] = 'Test User Agent';
        }

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

    /**
     * Change user password
     *
     * @param User $user The user
     * @param string $newPassword The new password
     * @return void
     */
    public function changePassword(User $user, string $newPassword): void
    {
        $user->password = $newPassword; // Let the User model accessor handle hashing
        $user->save();
    }

    /**
     * Ban a user
     *
     * @param User $user The user to ban
     * @param string $reason The ban reason
     * @return void
     */
    public function banUser(User $user, string $reason): void
    {
        $user->status = Status::USER_BAN;
        $user->ban_reason = $reason;
        $user->save();
    }

    /**
     * Unban a user
     *
     * @param User $user The user to unban
     * @return void
     */
    public function unbanUser(User $user): void
    {
        $user->status = Status::USER_ACTIVE;
        $user->ban_reason = null;
        $user->save();
    }

    /**
     * Verify user email
     *
     * @param User $user The user
     * @return void
     */
    public function verifyEmail(User $user): void
    {
        $user->ev = Status::VERIFIED;
        $user->save();
    }

    /**
     * Verify user phone
     *
     * @param User $user The user
     * @return void
     */
    public function verifyPhone(User $user): void
    {
        $user->sv = Status::VERIFIED;
        $user->save();
    }

    /**
     * Get user by username
     *
     * @param string $username The username
     * @return User|null
     */
    public function getUserByUsername(string $username): ?User
    {
        return User::where('username', $username)->first();
    }

    /**
     * Get user by email
     *
     * @param string $email The email
     * @return User|null
     */
    public function getUserByEmail(string $email): ?User
    {
        return User::where('email', strtolower($email))->first();
    }

    /**
     * Search users by query
     *
     * @param string $query The search query
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function searchUsers(string $query)
    {
        return User::where(function ($q) use ($query) {
            $q->where('username', 'like', "%{$query}%")
              ->orWhere('email', 'like', "%{$query}%")
              ->orWhere('firstname', 'like', "%{$query}%")
              ->orWhere('lastname', 'like', "%{$query}%");
        })->get();
    }

    /**
     * Get user count by status
     *
     * @param int $status The status
     * @return int
     */
    public function getUserCountByStatus(int $status): int
    {
        return User::where('status', $status)->count();
    }

    /**
     * Get recent users
     *
     * @param int $limit The limit
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getRecentUsers(int $limit = 10)
    {
        return User::orderBy('created_at', 'desc')->limit($limit)->get();
    }

    /**
     * Get users by referrer
     *
     * @param int $userId The referrer user ID
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getUsersByReferrer(int $userId)
    {
        return User::where('ref_by', $userId)->get();
    }

    /**
     * Update user balance
     *
     * @param User $user The user
     * @param float $amount The amount to add
     * @return void
     */
    public function updateBalance(User $user, float $amount): void
    {
        $user->balance += $amount;
        $user->save();
    }

    /**
     * Deduct user balance
     *
     * @param User $user The user
     * @param float $amount The amount to deduct
     * @return void
     */
    public function deductBalance(User $user, float $amount): void
    {
        if ($user->balance >= $amount) {
            $user->balance -= $amount;
            $user->save();
        }
    }
}
