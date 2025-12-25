@extends($activeTemplate . 'layouts.master')
@section('content')
    @php
        $user = auth()->user();
        $pointsBalance = $asset->points ?? 0;
        $canCheckIn = !$checkedIn;
    @endphp
    <div class="container">
        <div class="row">
            <div class="col-md-12">
                <div class="notice"></div>
                @php
                    $kyc = getContent('kyc.content', true);
                @endphp
                @if (auth()->user()->kv == Status::KYC_UNVERIFIED && auth()->user()->kyc_rejection_reason)
                    <div class="alert alert--danger" role="alert">
                        <div class="alert__icon"><i class="fas fa-file-signature"></i></div>
                        <p class="alert__message">
                            <span class="fw-bold">@lang('user.kyc_documents_rejected')</span><br>
                            <small>
                                <i>
                                    {{ __(@$kyc->data_values->reject) }}
                                    <a class="link-color text--base" data-bs-toggle="modal" data-bs-target="#kycRejectionReason"
                                        href="javascript::void(0)">@lang('user.click_here')</a> @lang('user.to_show_the_reason').
                                    <a class="link-color text--base" href="{{ route('user.kyc.form') }}">@lang('user.click_here')</a>
                                    @lang('user.to_re_submit_documents'). <br>

                                    <a class="link-color text--base mt-2" href="{{ route('user.kyc.data') }}">@lang('user.see_kyc_data')</a>
                                </i>
                            </small>
                        </p>
                    </div>
                @elseif (auth()->user()->kv == Status::KYC_UNVERIFIED)
                    <div class="alert alert--info" role="alert">
                        <div class="alert__icon"><i class="fas fa-file-signature"></i></div>
                        <p class="alert__message">
                            <span class="fw-bold">@lang('user.kyc_verification_required')</span><br>
                            <small>
                                <i>
                                    {{ __(@$kyc->data_values->required) }}
                                    <a class="link-color text--base" href="{{ route('user.kyc.form') }}">@lang('user.click_here')</a>
                                    @lang('user.to_submit_kyc_information').
                                </i>
                            </small>
                        </p>
                    </div>
                @elseif(auth()->user()->kv == Status::KYC_PENDING)
                    <div class="alert alert--warning" role="alert">
                        <div class="alert__icon"><i class="fas fa-user-check"></i></div>
                        <p class="alert__message">
                            <span class="fw-bold">@lang('user.kyc_verification_pending')</span><br>
                            <small>
                                <i>
                                    {{ __(@$kyc->data_values->pending) }}
                                    <a class="link-color text--base" href="{{ route('user.kyc.data') }}">@lang('user.click_here')</a> @lang('user.to_see_your_submitted_information')
                                </i>
                            </small>
                        </p>
                    </div>
                @endif

                @if (gs('notice'))
                    <div class="col-lg-12 col-sm-6 mt-4">
                        <div class="card notice--card custom--card">
                            <div class="card-header">
                                <h5 class="pb-2">@lang('user.notice')</h5>
                            </div>
                            <div class="card-body">
                                @if (gs('notice'))
                                    <p class="notice-text-inner">@php echo gs('notice') @endphp</p>
                                @endif
                            </div>
                        </div>
                    </div>
                @endif

                @if (gs('free_user_notice'))
                    <div class="col-lg-12 col-sm-6 mt-4">
                        <div class="card notice--card custom--card">
                            <div class="card-header">
                                <h5 class="pb-1">@lang('user.free_user_notice')</h5>
                            </div>
                            <div class="card-body">
                                @if (gs('free_user_notice') != null)
                                    <p class="notice-text-inner"> @php echo gs('free_user_notice'); @endphp </p>
                                @endif
                            </div>
                        </div>
                    </div>
                @endif
            </div>

            <div class="row justify-content-center g-3">
                <div class="col-sm-6 col-md-6 col-lg-6 col-xl-4">
                    <div class="dashboard-item">
                        <div class="dashboard-item-header">
                            <div class="header-left">
                                <h6 class="title">@lang('user.current_balance')</h6>
                                <h3 class="ammount theme-two">{{ showAmount(auth()->user()->balance) }}</h3>
                            </div>
                            <div class="right-content">
                                <div class="icon"><i class="flaticon-wallet"></i></div>
                            </div>
                        </div>
                        <div class="dashboard-item-body">
                        </div>
                    </div>
                </div>
                <div class="col-sm-6 col-md-6 col-lg-6 col-xl-4">
                    <div class="dashboard-item">
                        <div class="dashboard-item-header">
                            <div class="header-left">
                                <h6 class="title">
                                    @lang('user.current_plan')
                                </h6>
                                <h3 class="ammount">
                                    @if (auth()->user()->plan)
                                        <span>{{ auth()->user()->plan->name }}</span>
                                    @else
                                        <span class="text--danger">@lang('user.na')</span>
                                    @endif
                                </h3>
                            </div>
                            <div class="right-content">
                                <div class="icon"><i class="las la-paper-plane"></i></div>
                            </div>
                        </div>
                        <div class="dashboard-item-body">
                        </div>
                    </div>
                </div>
                <div class="col-sm-6 col-md-6 col-lg-6 col-xl-4">
                    <div class="dashboard-item">
                        <div class="dashboard-item-header">
                            <div class="header-left">
                                <h6 class="title">@lang('user.total_deposit')</h6>
                                <h3 class="ammount text--base">{{ showAmount($totalDeposit) }}</h3>
                            </div>
                            <div class="icon"><i class="flaticon-save-money"></i></div>
                        </div>
                        <div class="dashboard-item-body">
                        </div>
                    </div>
                </div>
                <div class="col-sm-6 col-md-6 col-lg-6 col-xl-4">
                    <div class="dashboard-item">
                        <div class="dashboard-item-header">
                            <div class="header-left">
                                <h6 class="title">@lang('user.total_withdraw')</h6>
                                <h3 class="ammount theme-one">{{ showAmount($totalWithdraw) }}</h3>
                            </div>
                            <div class="icon"><i class="flaticon-withdraw"></i></div>
                        </div>
                        <div class="dashboard-item-body">
                        </div>
                    </div>
                </div>
                <div class="col-sm-6 col-md-6 col-lg-6 col-xl-4">
                    <div class="dashboard-item">
                        <div class="dashboard-item-header">
                            <div class="header-left">
                                <h6 class="title">@lang('user.complete_withdraw')</h6>
                                <h3 class="ammount theme-two">{{ getAmount($completeWithdraw) }}</h3>
                            </div>
                            <div class="right-content">
                                <div class="icon"><i class="flaticon-wallet"></i></div>
                            </div>
                        </div>
                        <div class="dashboard-item-body">
                        </div>
                    </div>
                </div>
                <div class="col-sm-6 col-md-6 col-lg-6 col-xl-4">
                    <div class="dashboard-item">
                        <div class="dashboard-item-header">
                            <div class="header-left">
                                <h6 class="title">@lang('user.pending_withdraw')</h6>
                                <h3 class="ammount text--base">{{ getAmount($pendingWithdraw) }}</h3>
                            </div>
                            <div class="icon"><i class="flaticon-withdrawal"></i></div>
                        </div>
                        <div class="dashboard-item-body">
                        </div>
                    </div>
                </div>
                <div class="col-sm-6 col-md-6 col-lg-6 col-xl-4">
                    <div class="dashboard-item">
                        <div class="dashboard-item-header">
                            <div class="header-left">
                                <h6 class="title">@lang('user.total_invest')</h6>
                                <h3 class="ammount theme-one">{{ showAmount(auth()->user()->total_invest) }}</h3>
                            </div>
                            <div class="icon"><i class="flaticon-tag-1"></i></div>
                        </div>
                        <div class="dashboard-item-body">
                        </div>
                    </div>
                </div>
                <div class="col-sm-6 col-md-6 col-lg-6 col-xl-4">
                    <div class="dashboard-item">
                        <div class="dashboard-item-header">
                            <div class="header-left">
                                <h6 class="title">@lang('user.total_referral_commission')</h6>
                                <h3 class="ammount theme-one">{{ showAmount(auth()->user()->total_ref_com) }}</h3>
                            </div>
                            <div class="icon"><i class="flaticon-clipboards"></i></div>
                        </div>
                        <div class="dashboard-item-body">
                        </div>
                    </div>
                </div>
                <div class="col-sm-6 col-md-6 col-lg-6 col-xl-4">
                    <div class="dashboard-item">
                        <div class="dashboard-item-header">
                            <div class="header-left">
                                <h6 class="title">@lang('user.total_binary_commission')</h6>
                                <h3 class="ammount theme-one">{{ showAmount(auth()->user()->total_binary_com) }}</h3>
                            </div>
                            <div class="icon"><i class="flaticon-money-bag"></i></div>
                        </div>
                        <div class="dashboard-item-body">
                        </div>
                    </div>
                </div>

                {{-- 莲子积分卡片 --}}
                <div class="col-sm-6 col-md-6 col-lg-6 col-xl-4">
                    <div class="dashboard-item" style="border-left: 4px solid #9b59b6;">
                        <div class="dashboard-item-header">
                            <div class="header-left">
                                <h6 class="title">莲子积分</h6>
                                <h3 class="ammount" style="color: #9b59b6;">{{ showAmount($pointsBalance) }}</h3>
                            </div>
                            <div class="icon" style="background: rgba(155, 89, 182, 0.1); color: #9b59b6;">
                                <i class="fas fa-seedling"></i>
                            </div>
                        </div>
                        <div class="dashboard-item-body">
                            <div class="d-flex justify-content-between align-items-center mt-2">
                                <span class="text-muted small">每日签到 +10</span>
                                <form action="{{ route('user.points.checkin') }}" method="POST" style="display: inline;">
                                    @csrf
                                    <button type="submit" class="btn btn--sm btn--base" @disabled(!$canCheckIn)>
                                        @if($canCheckIn)
                                            <i class="fas fa-calendar-check"></i> 签到
                                        @else
                                            <i class="fas fa-check"></i> 已签到
                                        @endif
                                    </button>
                                </form>
                            </div>
                            <div class="mt-2">
                                <a href="{{ route('user.points.center') }}" class="text--base small">
                                    <i class="fas fa-arrow-right"></i> 查看积分中心
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- 七宝进阶快捷入口 --}}
                @php
                    $rankCode = $user->leader_rank_code ?? null;
                    $rankNames = [
                        'liuli_xingzhe' => '琉璃行者',
                        'huangjin_daoshi' => '黄金导师',
                        'manao_hufa' => '玛瑙护法',
                        'moni_dade' => '摩尼大德',
                        'jingang_zunzhe' => '金刚尊者'
                    ];
                    $currentRank = $rankCode ? ($rankNames[$rankCode] ?? '未设定') : '未设定';
                @endphp
                <div class="col-sm-6 col-md-6 col-lg-6 col-xl-4">
                    <div class="dashboard-item" style="border-left: 4px solid #f39c12;">
                        <div class="dashboard-item-header">
                            <div class="header-left">
                                <h6 class="title">七宝进阶</h6>
                                <h3 class="ammount" style="color: #f39c12; font-size: 1.2rem;">{{ $currentRank }}</h3>
                            </div>
                            <div class="icon" style="background: rgba(243, 156, 18, 0.1); color: #f39c12;">
                                <i class="fas fa-star"></i>
                            </div>
                        </div>
                        <div class="dashboard-item-body">
                            <div class="mt-2">
                                <a href="{{ route('user.seven.treasures.index') }}" class="text--base small">
                                    <i class="fas fa-chart-line"></i> 查看职级进度与分红
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endsection

        @if (auth()->user()->kv == Status::KYC_UNVERIFIED && auth()->user()->kyc_rejection_reason)
            <div class="modal fade" id="kycRejectionReason">
                <div class="modal-dialog" role="document">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">@lang('user.kyc_document_rejection_reason')</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="@lang('user.close')"></button>
                        </div>
                        <div class="modal-body">
                            <p>{{ auth()->user()->kyc_rejection_reason }}</p>
                        </div>
                    </div>
                </div>
            </div>
        @endif
