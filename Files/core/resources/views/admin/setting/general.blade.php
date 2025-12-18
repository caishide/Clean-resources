@extends('admin.layouts.app')
@section('panel')
    <div class="row mb-none-30">
        <div class="col-lg-12 col-md-12 mb-30">
            <div class="card">
                <div class="card-body">
                    <form method="POST">
                        @csrf
                        <div class="row">
                            <div class="col-sm-6">
                                <div class="form-group">
                                    <label> @lang('Site Title')</label>
                                    <input class="form-control" name="site_name" type="text" value="{{ gs('site_name') }}" required>
                                </div>
                            </div>
                            <div class="form-group col-sm-6">
                                <label class="required"> @lang('Timezone')</label>
                                <select class="select2 form-control" name="timezone">
                                    @foreach ($timezones as $key => $timezone)
                                        <option value="{{ @$key }}" @selected(@$key == $currentTimezone)>{{ __($timezone) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-sm-6">
                                <div class="form-group">
                                    <label>@lang('Currency')</label>
                                    <input class="form-control" name="cur_text" type="text" value="{{ gs('cur_text') }}" required>
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <div class="form-group">
                                    <label>@lang('Currency Symbol')</label>
                                    <input class="form-control" name="cur_sym" type="text" value="{{ gs('cur_sym') }}" required>
                                </div>
                            </div>

                            <div class="form-group col-sm-6">
                                <label class="required"> @lang('Site Base Color')</label>
                                <div class="input-group">
                                    <span class="input-group-text border-0 p-0">
                                        <input class="form-control colorPicker" type='text' value="{{ gs('base_color') }}">
                                    </span>
                                    <input class="form-control colorCode" name="base_color" type="text" value="{{ gs('base_color') }}">
                                </div>
                            </div>
                            <div class="form-group col-sm-6">
                                <label class="required"> @lang('Site Secondary Color')</label>
                                <div class="input-group">
                                    <span class="input-group-text border-0 p-0">
                                        <input class="form-control colorPicker" type='text' value="{{ gs('secondary_color') }}">
                                    </span>
                                    <input class="form-control colorCode" name="secondary_color" type="text" value="{{ gs('secondary_color') }}">
                                </div>
                            </div>
                            <div class="form-group col-sm-6">
                                <label> @lang('Record to Display Per page')</label>
                                <select class="select2 form-control" name="paginate_number" data-minimum-results-for-search="-1">
                                    <option value="20" @selected(gs('paginate_number') == 20)>@lang('20 items per page')</option>
                                    <option value="50" @selected(gs('paginate_number') == 50)>@lang('50 items per page')</option>
                                    <option value="100" @selected(gs('paginate_number') == 100)>@lang('100 items per page')</option>
                                </select>
                            </div>

                            <div class="form-group col-sm-6">
                                <label class="required"> @lang('Currency Showing Format')</label>
                                <select class="select2 form-control" name="currency_format" data-minimum-results-for-search="-1">
                                    <option value="1" @selected(gs('currency_format') == Status::CUR_BOTH)>@lang('Show Currency Text and Symbol Both')</option>
                                    <option value="2" @selected(gs('currency_format') == Status::CUR_TEXT)>@lang('Show Currency Text Only')</option>
                                    <option value="3" @selected(gs('currency_format') == Status::CUR_SYM)>@lang('Show Currency Symbol Only')</option>
                                </select>
                            </div>

                            <div class="form-group col-sm-6">
                                <label> @lang('Balance Transfer Fixed Charge')</label>
                                <div class="input-group">
                                    <input class="form-control" name="bal_trans_fixed_charge" type="number"
                                        value="{{ getAmount(gs('bal_trans_fixed_charge')) }}" step="any">
                                    <span class="input-group-text">{{ gs('cur_text') }}</span>
                                </div>
                            </div>
                            <div class="form-group col-sm-6">
                                <label> @lang('Balance Transfer Perchance Charge ')</label>
                                <div class="input-group">
                                    <input class="form-control" type="number" step="any" name="bal_trans_per_charge"
                                        value="{{ getAmount(gs('bal_trans_per_charge')) }}">
                                    <span class="input-group-text">%</span>
                                </div>
                            </div>

                        </div>

                        <button class="btn btn--primary w-100 h-45" type="submit">@lang('Submit')</button>

                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="row mt-4">
        <div class="col-lg-12 mb-30">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title m-0">@lang('Matching Bonus')</h4>
                </div>
                <form action="{{ route('admin.users.matching-bonus.update') }}" method="post">
                    <div class="card-body">
                        @csrf
                        <div class="row justify-content-between">
                            <div class="col-md-4 form-group">
                                <div class="input-group">
                                    <input class="form-control" id="#" name="total_bv" type="number" value="{{ getAmount(gs('total_bv')) }}"
                                        aria-describedby="#" required="">
                                    <span class="input-group-text" id="#">@lang('BV')</span>
                                </div>
                            </div>
                            <div class="col-md-1">
                                <h2 class="text-center">=</h2>
                            </div>
                            <div class="col-md-3 mb-4">
                                <div class="input-group">
                                    <input class="form-control" name="bv_price" type="number" value="{{ getAmount(gs('bv_price')) }}"
                                        aria-describedby="#" step="any" required="">
                                    <span class="input-group-text" id="#">{{ gs('cur_text') }}</span>
                                </div>
                            </div>

                            <div class="col-lg-4">
                                <div class="input-group">
                                    <span class="input-group-text">@lang('MAX')</span>
                                    <input class="form-control" name="max_bv" type="number" value="{{ getAmount(gs('max_bv')) }}"
                                        aria-label="Amount (to the nearest dollar)">
                                    <span class="input-group-text">@lang('BV')</span>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="form-group col-lg-4">
                                <label>@lang('Carry / Flush')</label>
                                <select class="form-control select2" name="cary_flash" data-minimum-results-for-search="-1" required>
                                    <option value="0" @selected(gs('cary_flash') == 0)>@lang('Carry (Cut Only Paid BV)')</option>
                                    <option value="1" @selected(gs('cary_flash') == 1)>@lang('Flush (Cut Weak Leg Value)')</option>
                                    <option value="2" @selected(gs('cary_flash') == 2)>@lang('Flush (Cut All BV and reset to 0)')</option>
                                </select>
                            </div>
                            <div class="form-group col-md-4">
                                <label> @lang('Matching Bonus Time') </label>
                                <select class="form-control select2" name="matching_bonus_time" data-minimum-results-for-search="-1">
                                    <option value="daily">@lang('Daily')</option>
                                    <option value="weekly">@lang('Weekly')</option>
                                    <option value="monthly">@lang('Monthly')</option>
                                </select>
                            </div>
                            <div class="form-group col-md-4" id="daily_time" style="display:none;">
                                <label>@lang('Daily Time')</label>
                                <select class="form-control select2" name="daily_time" data-minimum-results-for-search="-1">
                                    <option value="1">@lang('01:00')</option>
                                    <option value="2">@lang('02:00')</option>
                                    <option value="3">@lang('03:00')</option>
                                    <option value="4">@lang('04:00')</option>
                                    <option value="5">@lang('05:00')</option>
                                    <option value="6">@lang('06:00')</option>
                                    <option value="7">@lang('07:00')</option>
                                    <option value="8">@lang('08:00')</option>
                                    <option value="9">@lang('09:00')</option>
                                    <option value="10">@lang('10:00')</option>
                                    <option value="11">@lang('11:00')</option>
                                    <option value="12">@lang('12:00')</option>
                                    <option value="13">@lang('13:00')</option>
                                    <option value="14">@lang('14:00')</option>
                                    <option value="15">@lang('15:00')</option>
                                    <option value="16">@lang('16:00')</option>
                                    <option value="17">@lang('17:00')</option>
                                    <option value="18">@lang('18:00')</option>
                                    <option value="19">@lang('19:00')</option>
                                    <option value="20">@lang('20:00')</option>
                                    <option value="21">@lang('21:00')</option>
                                    <option value="22">@lang('22:00')</option>
                                    <option value="23">@lang('23:00')</option>
                                    <option value="24">@lang('24:00')</option>
                                </select>
                            </div>
                            <div class="form-group col-md-4" id="weekly_time" style="display:none;">
                                <label>@lang('Weekly Time')</label>
                                <select class="form-control select2" name="weekly_time" data-minimum-results-for-search="-1">
                                    <option value="sat">@lang('Saturday')</option>
                                    <option value="sun">@lang('Sunday')</option>
                                    <option value="mon">@lang('Monday')</option>
                                    <option value="tue">@lang('Tuesday')</option>
                                    <option value="wed">@lang('Wednesday')</option>
                                    <option value="thu">@lang('Thursday')</option>
                                    <option value="fri">@lang('Friday')</option>
                                </select>
                            </div>
                            <div class="form-group col-md-4" id="monthly_time" style="display:none;">
                                <label>@lang('Monthly Time')</label>
                                <select class="form-control select2" name="monthly_time" data-minimum-results-for-search="-1">
                                    <option value="1">@lang('1st day Month')</option>
                                    <option value="15">@lang('15th day of Month')</option>
                                </select>
                            </div>
                        </div>

                        <button class="btn btn--primary h-45 w-100 btn-block btn-lg" type="submit">@lang('Update')</button>
                    </div>

                </form>
            </div>
        </div>
    </div>
@endsection

@push('script-lib')
    <script src="{{ asset('assets/admin/js/spectrum.js') }}"></script>
@endpush

@push('style-lib')
    <link href="{{ asset('assets/admin/css/spectrum.css') }}" rel="stylesheet">
@endpush

@push('script')
    <script>
        (function($) {
            "use strict";


            $('.colorPicker').spectrum({
                color: $(this).data('color'),
                change: function(color) {
                    $(this).parent().siblings('.colorCode').val(color.toHexString().replace(/^#?/, ''));
                }
            });

            $('.colorCode').on('input', function() {
                var clr = $(this).val();
                $(this).parents('.input-group').find('.colorPicker').spectrum({
                    color: clr,
                });
            });



            // matching bonus
            $("select[name=cary_flash]").val("{{ gs('cary_flash') }}");
            $("select[name=matching_bonus_time]").val("{{ gs('matching_bonus_time') }}");
            $("select[name=weekly_time]").val("{{ gs('matching_when') }}");
            $("select[name=monthly_time]").val("{{ gs('matching_when') }}");
            $("select[name=daily_time]").val("{{ gs('matching_when') }}");

            $('select[name=matching_bonus_time]').on('change', function() {
                matchingBonus($(this).val());
            });

            matchingBonus($('select[name=matching_bonus_time]').val());

            function matchingBonus(matching_bonus_time) {
                if (matching_bonus_time == 'daily') {
                    document.getElementById('weekly_time').style.display = 'none';
                    document.getElementById('monthly_time').style.display = 'none'
                    document.getElementById('daily_time').style.display = 'block'

                } else if (matching_bonus_time == 'weekly') {
                    document.getElementById('weekly_time').style.display = 'block';
                    document.getElementById('monthly_time').style.display = 'none'
                    document.getElementById('daily_time').style.display = 'none'
                } else if (matching_bonus_time == 'monthly') {
                    document.getElementById('weekly_time').style.display = 'none';
                    document.getElementById('monthly_time').style.display = 'block'
                    document.getElementById('daily_time').style.display = 'none'
                }
            }





        })(jQuery);
    </script>
@endpush
