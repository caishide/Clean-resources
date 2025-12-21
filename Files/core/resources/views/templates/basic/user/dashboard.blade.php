@extends($activeTemplate . 'layouts.master')
@section('content')
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
