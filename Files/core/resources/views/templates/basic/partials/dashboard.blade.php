<section class="user-dashboard padding-top padding-bottom">
    <div class="container">
        <div class="row">
            <div class="col-lg-3">
                <div class="dashboard-sidebar">
                    <div class="close-dashboard d-lg-none">
                        <i class="las la-times"></i>
                    </div>
                    <div class="dashboard-user">
                        <div class="user-thumb">
                            <img  id="output" src="{{ getImage('assets/images/user/profile/'. auth()->user()->image, '350x300',true)}}" alt="dashboard">
                        </div>
                        <div class="user-content">
                            <span>@lang('user.welcome')</span>
                            <h5 class="name">{{ auth()->user()->fullname }}</h5>
                        </div>
                    </div>

                    @if(Session::has('is_impersonating') && Session::get('is_impersonating'))
                        <div class="alert alert-danger mt-3">
                            <div class="d-flex align-items-center">
                                <i class="las la-user-shield fs-4 me-2"></i>
                                <div>
                                    <strong>@lang('user.admin_impersonation_active')</strong>
                                    <p class="mb-0 small">
                                        @lang('user.you_are_being_impersonated')
                                    </p>
                                </div>
                            </div>
                            <a href="{{ route('admin.users.exit.impersonation') }}" class="btn btn--danger btn-sm mt-2 w-100">
                                        <i class="las la-sign-out-alt"></i> @lang('user.exit_impersonation')
                                    </a>
                        </div>
                    @endif
                    <ul class="user-dashboard-tab">
                        <li>
                            <a class="{{menuActive('user.home')}}" href="{{route('user.home')}}">@lang('user.dashboard')</a>
                        </li>
                        <li>
                            <a class="{{menuActive('user.plan.index')}}" href="{{route('user.plan.index')}}"> @lang('user.plan') </a>
                        </li>
                        <li>
                            <a class="{{menuActive('user.bv.log')}}" href="{{ route('user.bv.log') }}">@lang('user.bv_log') </a>
                        </li>
                        <li>
                            <a class="{{menuActive('user.my.ref')}}" href="{{ route('user.my.ref') }}"> @lang('user.my_referrals')</a>
                        </li>
                        <li>
                            <a class="{{menuActive('user.my.tree')}}" href="{{ route('user.my.tree') }}">@lang('user.my_tree')</a>
                        </li>
                        <li>
                            <a href="{{ route('user.binary.summery') }}" class="{{menuActive('user.binary.summery')}}">
                                @lang('user.binary_summary')
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('user.orders') }}" class="{{menuActive('user.orders')}}">
                                @lang('user.orders')
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('user.balance.transfer') }}" class="{{menuActive('user.balance.transfer')}}">
                                @lang('user.balance_transfer')
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('user.deposit.history') }}" class="{{menuActive(['user.deposit*'])}}">
                                @lang('user.deposit_history')
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('user.withdraw.history') }}" class="{{menuActive('user.withdraw*')}}">
                                @lang('user.withdraw_history')
                            </a>
                        </li>
                       
                        <li>
                            <a href="{{ route('user.transactions') }}" class="{{menuActive('user.transactions')}}">
                                @lang('user.transactions_history')
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('ticket.index') }}" class="{{menuActive('ticket*')}}">
                                @lang('user.support_ticket')
                            </a>
                        </li>
                        
                        <li>
                            <a class="{{menuActive('user.profile.setting')}}" href="{{route('user.profile.setting')}}" class="">@lang('user.profile_setting')</a>
                        </li>
                        <li>
                            <a href="{{ route('user.twofactor') }}" class="{{menuActive('user.twofactor')}}">
                                @lang('user.two_fa_security')
                            </a>
                        </li>
                        <li>
                            <a class="{{menuActive('user.change.password')}}" href="{{route('user.change.password')}}" class="">@lang('user.change_password')</a>
                        </li>
                        <li>
                            <a href="{{ route('user.logout') }}" class="">@lang('user.sign_out')</a>
                        </li>
                    </ul>
                </div>
            </div>
            <div class="col-lg-9">

                <div class="user-toggler-wrapper d-flex d-lg-none">
                    <h4 class="title">{{ __($pageTitle) }}</h4>
                    <div class="user-toggler">
                        <i class="las la-sliders-h"></i>
                    </div>
                </div>


                @yield('content')
            </div>
        </div>
    </div>
</section>