@php
    $sideBarLinks = json_decode($sidenav);
    if (json_last_error() !== JSON_ERROR_NONE) {
        $sideBarLinks = new stdClass();
        error_log('Sidenav JSON decode error: ' . json_last_error_msg());
    }

    // 翻译菜单标题的辅助函数
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

    // 增强的防御性路由生成函数 - 多层防护
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
            error_log("SafeRoute: Exception for route $routeName: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
            return 'javascript:void(0)';
        }
    }
@endphp

<div class="sidebar bg--dark">
    <button class="res-sidebar-close-btn"><i class="las la-times"></i></button>
    <div class="sidebar__inner">
        <div class="sidebar__logo">
            <a href="{{route('admin.dashboard')}}" class="sidebar__main-logo"><img src="{{siteLogo('dark')}}" alt="image"></a>
        </div>
        <div class="sidebar__menu-wrapper">
            <ul class="sidebar__menu">
                @foreach($sideBarLinks as $key => $data)
                    @if (@$data->header)
                        <li class="sidebar__menu-header">{{ translateMenuTitle($data->header, $key) }}</li>
                    @endif
                    @if(@$data->submenu)
                        <li class="sidebar-menu-item sidebar-dropdown">
                            <a href="javascript:void(0)" class="{{ menuActive(@$data->menu_active, 3) }}">
                                <i class="menu-icon {{ @$data->icon }}"></i>
                                <span class="menu-title">{{ translateMenuTitle(@$data->title, $key) }}</span>
                                @foreach(@$data->counters ?? [] as $counter)
                                    @if($$counter > 0)
                                        <span class="menu-badge menu-badge-level-one bg--warning ms-auto">
                                            <i class="fas fa-exclamation"></i>
                                        </span>
                                        @break
                                    @endif
                                @endforeach
                            </a>
                            <div class="sidebar-submenu {{ menuActive(@$data->menu_active, 2) }} ">
                                <ul>
                                    @foreach($data->submenu as $menu)
                                    @php
                                        $submenuParams = null;
                                        if (@$menu->params) {
                                            foreach ($menu->params as $submenuParamVal) {
                                                $submenuParams[] = array_values((array)$submenuParamVal)[0];
                                            }
                                        }
                                    @endphp
                                        <li class="sidebar-menu-item {{ menuActive(@$menu->menu_active) }} ">
                                            @if(isset($menu->route_name) && !empty($menu->route_name))
                                                <a href="{{ safeRoute($menu->route_name, $submenuParams) }}" class="nav-link">
                                            @else
                                                <a href="javascript:void(0)" class="nav-link">
                                            @endif
                                                <i class="menu-icon las la-dot-circle"></i>
                                                <span class="menu-title">{{ translateMenuTitle($menu->title, $key) }}</span>
                                                @php $counter = @$menu->counter; @endphp
                                                @if(@$$counter)
                                                    <span class="menu-badge bg--info ms-auto">{{ @$$counter }}</span>
                                                @endif
                                            </a>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        </li>
                    @else
                        @php
                            $mainParams = null;
                            if (@$data->params) {
                                foreach ($data->params as $paramVal) {
                                    $mainParams[] = array_values((array)$paramVal)[0];
                                }
                            }
                        @endphp
                        <li class="sidebar-menu-item {{ menuActive(@$data->menu_active) }}">
                            @if(isset($data->route_name) && !empty($data->route_name))
                                <a href="{{ safeRoute($data->route_name, $mainParams) }}" class="nav-link ">
                            @else
                                <a href="javascript:void(0)" class="nav-link ">
                            @endif
                                <i class="menu-icon {{ $data->icon }}"></i>
                                <span class="menu-title">{{ translateMenuTitle(@$data->title, $key) }}</span>
                                @php $counter = @$data->counter; @endphp
                                @if (@$$counter)
                                    <span class="menu-badge bg--info ms-auto">{{ @$$counter }}</span>
                                @endif
                            </a>
                        </li>
                    @endif
                @endforeach
            </ul>
        </div>
        <div class="version-info text-center text-uppercase">
            <span class="text--primary">{{__(systemDetails()['name'])}}</span>
            <span class="text--success">@lang('V'){{systemDetails()['version']}} </span>
        </div>
    </div>
</div>
<!-- sidebar end -->

@push('script')
    <script>
        if($('li').hasClass('active')){
            $('.sidebar__menu-wrapper').animate({
                scrollTop: eval($(".active").offset().top - 320)
            },500);
        }
    </script>
@endpush
