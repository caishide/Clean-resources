@extends($activeTemplate . 'layouts.master')
@section('content')
    <div class="d-flex align-items-center justify-content-end mb-3 flex-wrap gap-3">
        <form>
            <div class="input-group">
                <input class="form-control form--control" name="search" type="search" value="{{ request()->search }}" placeholder="@lang('Search by transactions')">
                <button class="input-group-text">
                    <i class="las la-search"></i>
                </button>
            </div>
        </form>
        <a class="btn btn--base" href="{{ route('user.withdraw') }}">
            <i class="las la-plus"></i> @lang('Withdraw Now')
        </a>
    </div>
    <div class="card custom--card p-0">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="custom--table table">
                    <thead>
                        <tr>
                            <th>@lang('Gateway | Transaction')</th>
                            <th class="text-md-center text-end">@lang('Initiated')</th>
                            <th class="text-md-center text-end">@lang('Amount')</th>
                            <th class="text-md-center text-end">@lang('Conversion')</th>
                            <th class="text-md-center text-end">@lang('Status')</th>
                            <th>@lang('Action')</th>
                        </tr>
                    </thead>
                    <tbody>

                        @forelse($withdraws as $withdraw)
                            @php
                                $details = [];
                                foreach ($withdraw->withdraw_information as $key => $info) {
                                    $details[] = $info;
                                    if ($info->type == 'file' && @$info->value) {
                                        $details[$key]->value = route(
                                            'user.download.attachment',
                                            encrypt(getFilePath('verify') . '/' . $info->value),
                                        );
                                    }
                                }
                            @endphp
                            <tr>
                                <td>
                                    <span class="fw-bold"><span class="text-primary"> {{ __(@$withdraw->method->name) }}</span></span>
                                    <br>
                                    <small>{{ $withdraw->trx }}</small>
                                </td>
                                <td class="text-md-center text-end">
                                    {{ showDateTime($withdraw->created_at) }} <br> {{ diffForHumans($withdraw->created_at) }}
                                </td>
                                <td class="text-md-center text-end">
                                    {{ showAmount($withdraw->amount) }} - <span class="text--danger" data-bs-toggle="tooltip"
                                        title="@lang('Processing Charge')">{{ showAmount($withdraw->charge) }} </span>
                                    <br>
                                    <strong data-bs-toggle="tooltip" title="@lang('Amount after charge')">
                                        {{ showAmount($withdraw->amount - $withdraw->charge) }}
                                    </strong>

                                </td>
                                <td class="text-md-center text-end">
                                    {{ showAmount(1) }} = {{ showAmount($withdraw->rate, currencyFormat: false) }} {{ __($withdraw->currency) }}
                                    <br>
                                    <strong>{{ showAmount($withdraw->final_amount, currencyFormat: false) }} {{ __($withdraw->currency) }}</strong>
                                </td>
                                <td class="text-md-center text-end">
                                    @php echo $withdraw->statusBadge @endphp
                                </td>
                                <td>
                                    <button class="btn btn--sm btn--base detailBtn" data-user_data="{{ json_encode($details) }}"
                                        @if ($withdraw->status == Status::PAYMENT_REJECT) data-admin_feedback="{{ $withdraw->admin_feedback }}" @endif>
                                        <i class="la la-desktop"></i>
                                    </button>
                                </td>
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

    @if ($withdraws->hasPages())
        <div class="mt-4">
            {{ paginateLinks($withdraws) }}
        </div>
    @endif
@endsection

@push('modal')
    {{-- APPROVE MODAL --}}
    <div class="modal fade" id="detailModal" role="dialog" tabindex="-1">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">@lang('Details')</h5>
                    <span class="close" data-bs-dismiss="modal" type="button" aria-label="Close">
                        <i class="las la-times"></i>
                    </span>
                </div>
                <div class="modal-body">
                    <ul class="list-group userData">

                    </ul>
                    <div class="feedback"></div>
                </div>
              
            </div>
        </div>
    </div>
@endpush

@push('script')
    <script>
        (function($) {
            "use strict";
            $('.detailBtn').on('click', function() {
                var modal = $('#detailModal');
                var userData = $(this).data('user_data');
                var html = ``;
                userData.forEach(element => {
                    if (element.type != 'file') {
                        html += `
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span>${element.name}</span>
                            <span">${element.value}</span>
                        </li>`;
                    } else {
                        html += `
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span>${element.name}</span>
                            <span"><a href="${element.value}"><i class="fa-regular fa-file"></i> @lang('Attachment')</a></span>
                        </li>`;
                    }
                });
                modal.find('.userData').html(html);

                if ($(this).data('admin_feedback') != undefined) {
                    var adminFeedback = `
                        <div class="my-3">
                            <strong>@lang('Admin Feedback')</strong>
                            <p>${$(this).data('admin_feedback')}</p>
                        </div>
                    `;
                } else {
                    var adminFeedback = '';
                }

                modal.find('.feedback').html(adminFeedback);

                modal.modal('show');
            });

            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[title], [data-title], [data-bs-title]'))
            tooltipTriggerList.map(function(tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl)
            });
        })(jQuery);
    </script>
@endpush
