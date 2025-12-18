@extends($activeTemplate . 'layouts.master')
@section('content')
    <div class="row justify-content-center">
        <div class="col-md-12">
            <div class="show-filter mb-3 text-end">
                <button class="btn btn--base showFilterBtn btn-sm" type="button"><i class="las la-filter"></i> @lang('Filter')</button>
            </div>
            <div class="card custom--card responsive-filter-card b-radius--10 mb-4">
                <div class="card-body">
                    <form>
                        <div class="d-flex flex-wrap gap-4">
                            <div class="flex-grow-1">
                                <label class="form--label form-label">@lang('Transaction Number')</label>
                                <input class="form-control form--control" name="search" type="search" value="{{ request()->search }}">
                            </div>
                            <div class="flex-grow-1 select2-parent">
                                <label class="form--label form-label d-block">@lang('Type')</label>
                                <select class="form-control form--control select2" name="trx_type" data-minimum-results-for-search="-1">
                                    <option value="">@lang('All')</option>
                                    <option value="+" @selected(request()->trx_type == '+')>@lang('Plus')</option>
                                    <option value="-" @selected(request()->trx_type == '-')>@lang('Minus')</option>
                                </select>
                            </div>
                            <div class="flex-grow-1 select2-parent">
                                <label class="form--label form-label d-block">@lang('Remark')</label>
                                <select class="form-control form--control select2" name="remark" data-minimum-results-for-search="-1">
                                    <option value="">@lang('All')</option>
                                    @foreach ($remarks as $remark)
                                        <option value="{{ $remark->remark }}" @selected(request()->remark == $remark->remark)>{{ __(keyToTitle($remark->remark)) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="flex-grow-1 align-self-end">
                                <button class="btn btn--base w-100 h-50"><i class="las la-filter"></i> @lang('Filter')</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            <div class="card custom--card p-0">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="custom--table table">
                            <thead>
                                <tr>
                                    <th>@lang('Trx')</th>
                                    <th>@lang('Transacted')</th>
                                    <th>@lang('Amount/Charge')</th>
                                    <th>@lang('Post Balance')</th>
                                    <th>@lang('Detail')</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($transactions as $trx)
                                    <tr>
                                        <td>
                                            <strong>{{ $trx->trx }}</strong>
                                        </td>

                                        <td>
                                            {{ showDateTime($trx->created_at) }}<br>{{ diffForHumans($trx->created_at) }}
                                        </td>

                                        <td>
                                            <span class="fw-bold @if ($trx->trx_type == '+') text--success @else text--danger @endif">
                                                {{ $trx->trx_type }} {{ showAmount($trx->amount) }}
                                            </span>
                                            <br>
                                            <span class="fw-bold text--warning ps-2">
                                                {{ showAmount($trx->charge) }}
                                            </span>
                                        </td>

                                        <td>
                                            {{ showAmount($trx->post_balance) }}
                                        </td>

                                        <td>{{ __($trx->details) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td class="text-muted text-center" colspan="100%">{{ __($emptyMessage) }}</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </div>
        @if ($transactions->hasPages())
            <div class="mt-4">
                {{ paginateLinks($transactions) }}
            </div>
        @endif
    </div>
@endsection

@push('script')
    <script>
        (function($) {
            "use strict";
            $('.showFilterBtn').on('click', function() {
                $('.responsive-filter-card').slideToggle();
            });

        })(jQuery);
    </script>
@endpush

@push('style')
    <style>
        .select2-container {
            width: 100% !important;
        }
    </style>
@endpush
