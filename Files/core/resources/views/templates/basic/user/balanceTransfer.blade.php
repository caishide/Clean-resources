@extends($activeTemplate . 'layouts.master')

@section('content')
    <div class="card custom--card">
        <form class="contact-form" method="POST" action="{{ route('user.balance.transfer') }}">
            @csrf
            <div class="card-body">
                <div class="text-center">
                    <div class="alert block-none alert-danger p-2" role="alert">
                        <strong>@lang('Balance Transfer Charge') {{ getAmount(gs('bal_trans_fixed_charge')) }} {{ __(gs('cur_text')) }} @lang('Fixed and')
                            {{ getAmount(gs('bal_trans_per_charge')) }}
                            % @lang('of your total amount to transfer balance.')</strong> <br/>
                        <p id="after-balance" class="d-block"></p>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label form--label">@lang('Username / Email To Send Amount') </label>
                    <input class="form-control form--control" id="username" name="username" type="text" placeholder="@lang('username / email')" required
                        autocomplete="off">
                    <span id="position-test"></span>
                </div>
                <div class="form-group">
                    <label class="form-label form--label" for="InputMail">@lang('Transfer Amount')</label>
                    <input class="form-control form--control" type="number" step="any" id="amount" name="amount"
                        onkeyup="this.value = this.value.replace (/^\.|[^\d\.]/g, '')" autocomplete="off"
                        placeholder="@lang('Amount') {{ __(gs('cur_text')) }}" required>
                    <span id="balance-message"></span>
                </div>
                <button class="btn btn--base w-100" type="submit">@lang('Transfer Balance')</button>
            </div>

        </form>
    </div>
@endsection
@push('script')
    <script>
        'use strict';
        (function($) {
            $(document).on('focusout', '#username', function() {
                var username = $('#username').val();
                var token = "{{ csrf_token() }}";
                if (username) {
                    $.ajax({
                        type: "POST",
                        url: "{{ route('user.search.user') }}",
                        data: {
                            'username': username,
                            '_token': token
                        },
                        success: function(data) {
                            if (data.success) {
                                $('#position-test').html('<div class="text--success mt-1">@lang('User found')</div>');
                            } else {
                                $('#position-test').html('<div class="text--danger mt-2">@lang('User not found')</div>');
                            }
                        }
                    });
                } else {
                    $('#position-test').html('');
                }
            });
            $(document).on('keyup', '#amount', function() {
                var amount = parseFloat($('#amount').val());
                var balance = parseFloat("{{ Auth::user()->balance + 0 }}");
                var fixed_charge = parseFloat("{{ gs('bal_trans_fixed_charge') + 0 }}");
                var percent_charge = parseFloat("{{ gs('bal_trans_per_charge') + 0 }}");
                var percent = (amount * percent_charge) / 100;
                var with_charge = amount + fixed_charge + percent;
                if (with_charge > balance) {
                    $('#after-balance').html('<p  class="text--danger">' + with_charge + ' {{ gs('cur_text') }} ' +
                        ' {{ __('will be subtracted from your balance') }}' + '</p>');
                    $('#balance-message').html('<small class="text--danger">Insufficient Balance!</small>');
                } else if (with_charge <= balance) {
                    $('#after-balance').html('<p class="text--danger">' + with_charge + ' {{ gs('cur_text') }} ' +
                        ' {{ __('will be subtracted from your balance') }}' + '</p>');
                    $('#balance-message').html('');
                }
            });
        })(jQuery)
    </script>
@endpush
