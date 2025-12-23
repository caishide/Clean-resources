<?php

namespace App\Providers;

use URL;
use App\Models\User;
use App\Lib\Searchable;
use App\Models\Deposit;
use App\Models\Frontend;
use App\Constants\Status;
use App\Models\Withdrawal;
use App\Models\SupportTicket;
use App\Models\AdminNotification;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        Builder::mixin(new Searchable);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // 测试环境强制使用 array cache，避免未安装 redis 扩展导致启动失败
        if (app()->environment('testing')) {
            config(['cache.default' => 'array']);
        }

        // 测试环境无 general_settings 表时跳过后续初始化
        if (app()->environment('testing') && !Schema::hasTable('general_settings')) {
            return;
        }

        if (!config('logging.channels.performance')) {
            config([
                'logging.channels.performance' => [
                    'driver' => 'daily',
                    'path' => storage_path('logs/performance.log'),
                    'level' => 'info',
                    'days' => 14,
                ],
            ]);
        }

        try {
            // 使用try-catch避免Redis未安装时崩溃
            if (!cache()->get('SystemInstalled')) {
                $envFilePath = base_path('.env');
                if (!file_exists($envFilePath)) {
                    header('Location: install');
                    exit;
                }
                $envContents = file_get_contents($envFilePath);
                if (empty($envContents)) {
                    header('Location: install');
                    exit;
                } else {
                    cache()->put('SystemInstalled', true);
                }
            }
        } catch (\Exception $e) {
            // 如果Redis不可用，记录错误但不阻止应用启动
            Log::warning('Cache system unavailable', [
                'error' => $e->getMessage(),
                'message' => 'Redis extension may not be installed. Using fallback.',
            ]);
        }


        $activeTemplate = activeTemplate();
        $viewShare['activeTemplate'] = $activeTemplate;
        $viewShare['activeTemplateTrue'] = activeTemplate(true);
        $viewShare['emptyMessage'] = 'Data not found';
        view()->share($viewShare);


        view()->composer('admin.partials.sidenav', function ($view) {
            $view->with([
                'bannedUsersCount'           => User::banned()->count(),
                'emailUnverifiedUsersCount' => User::emailUnverified()->count(),
                'mobileUnverifiedUsersCount'   => User::mobileUnverified()->count(),
                'kycUnverifiedUsersCount'   => User::kycUnverified()->count(),
                'kycPendingUsersCount'   => User::kycPending()->count(),
                'pendingTicketCount'         => SupportTicket::whereIN('status', [Status::TICKET_OPEN, Status::TICKET_REPLY])->count(),
                'pendingDepositsCount'    => Deposit::pending()->count(),
                'pendingWithdrawCount'    => Withdrawal::pending()->count(),
                'updateAvailable'    => version_compare(gs('available_version'), systemDetails()['version'], '>') ? 'v' . gs('available_version') : false,
            ]);
        });

        view()->composer('admin.partials.topnav', function ($view) {
            $view->with([
                'adminNotifications' => AdminNotification::where('is_read', Status::NO)->with('user')->orderBy('id', 'desc')->take(10)->get(),
                'adminNotificationCount' => AdminNotification::where('is_read', Status::NO)->count(),
            ]);
        });

        view()->composer('partials.seo', function ($view) {
            $seo = Frontend::where('data_keys', 'seo.data')->first();
            $view->with([
                'seo' => $seo ? $seo->data_values : $seo,
            ]);
        });

        if (gs('force_ssl')) {
            URL::forceScheme('https');
        }

        Paginator::useBootstrapFive();

        // 🔒 安全增强：添加HTTP安全头
        $this->addSecurityHeaders();
    }

    /**
     * 🔒 安全增强：添加HTTP安全头
     *
     * @return void
     */
    private function addSecurityHeaders(): void
    {
        if (app()->runningInConsole() || headers_sent()) {
            return;
        }

        // 防止点击劫持
        header('X-Frame-Options: DENY');

        // 防止MIME类型嗅探
        header('X-Content-Type-Options: nosniff');

        // XSS保护
        header('X-XSS-Protection: 1; mode=block');

        // 强制HTTPS（HSTS）
        if (request()->isSecure()) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
        }

        // 限制引用来源
        header('Referrer-Policy: strict-origin-when-cross-origin');

        // 内容安全策略（CSP）- 防止XSS攻击
        // 注意：此配置需要根据实际需求调整
        header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; style-src-elem 'self' 'unsafe-inline' https://fonts.googleapis.com; img-src 'self' data: https:; font-src 'self' data: https://fonts.gstatic.com; connect-src 'self' https:; frame-src 'self' https://www.google.com https://maps.google.com https://www.youtube.com https://youtube.com https://player.vimeo.com https://www.googleusercontent.com; frame-ancestors 'self' https://www.google.com https://maps.google.com;");
    }
}
