@extends($activeTemplate . 'layouts.master')

@section('content')
    <div class="row">
        <div class="col-lg-12 mb-4">
            <div class="card custom--card p-0">
                <div class="card-header bg--primary-gradient p-3">
                    <h4 class="card-title font-weight-normal text-white">@lang('Referrer Link')</h4>
                </div>
                <div class="card-body mb-3 p-4">
                    <h4 class="card-title font-weight-normal">@lang('Join left')</h4>
                    <form id="copyBoard" class="mb-4">
                        <div class="row align-items-center">
                            <div class="col-md-10 my-1">
                                <input class="form-control form--control from-control-lg" id="ref" type="url"
                                    value="{{ route('user.register') }}/?ref={{ auth()->user()->username }}&position=left" readonly>
                            </div>
                            <div class="col-md-2 my-1">
                                <button class="cmn--btn btn-block active" id="copybtn" type="button" onclick="myFunction('ref')"> <span><i
                                            class="fa fa-copy"></i> @lang('Copy')</span></button>
                            </div>
                        </div>
                    </form>

                    <h4 class="card-title font-weight-normal">@lang('Join right')</h4>
                    <form id="copyBoard2">
                        <div class="row align-items-center">
                            <div class="col-md-10 my-1">
                                <input class="form-control form--control from-control-lg" id="ref2" type="url"
                                    value="{{ route('user.register') }}?ref={{ auth()->user()->username }}&position=right" readonly>
                            </div>
                            <div class="col-md-2 my-1">
                                <button class="cmn--btn btn-block btn-sm active" id="copybtn2" type="button" onclick="myFunction('ref2')"> <span><i
                                            class="fa fa-copy"></i> @lang('Copy')</span></button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-lg-12">
            <div class="card custom--card">
                <div class="card-body p-0">
                    <div class="table-responsive--sm">
                        <table class="custom--table table">
                            <thead>
                                <tr>
                                    <th>@lang('Username')</th>
                                    <th>@lang('Name')</th>
                                    <th>@lang('Email')</th>
                                    <th>@lang('Join Date')</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($logs as $data)
                                    <tr>
                                        <td>{{ $data->username }}</td>
                                        <td>{{ $data->fullname }}</td>
                                        <td>{{ $data->email }}</td>
                                        <td>
                                            {{ showDateTime($data->created_at) }}
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
        </div>
        @if ($logs->hasPages())
            <div class="mt-4">
                {{ paginateLinks($logs) }}
            </div>
        @endif
    </div>
@endsection

@push('script')
    <script>
        'use strict';

        function myFunction(id) {
            var copyText = document.getElementById(id);
            copyText.select();
            copyText.setSelectionRange(0, 99999)
            document.execCommand("copy");
            notify('success', 'Url copied successfully ' + copyText.value);
        }
    </script>
@endpush
