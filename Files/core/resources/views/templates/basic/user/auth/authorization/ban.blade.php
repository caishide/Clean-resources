@extends($activeTemplate . 'layouts.app')

@php
    $bannedContent = getContent('banned.content', true);
@endphp

@section('panel')
    <div class="padding-bottom padding-top">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-6">
                    <div class="banned-image">
                        <img src="{{ frontendImage('banned', $bannedContent->data_values->image) }}" alt="image">
                    </div>
                    <div class="text-center mt-5">
                        <h3 class="text--danger pb-2">@lang('You are banned')</h3>
                        <p class="fw-bold mb-1">@lang('Reason'):</p>
                        <p>{{ $user->ban_reason }}</p>
                        <br>
                        <a href="{{ route('home') }}" class="btn btn--base btn--sm">@lang('Home')</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('style')
    <style>
        .banned-image {
            max-width: 400px;
            text-align: center;
            margin: 0 auto;
        }

        .banned-image img {
            width: 100%;
        }

        .banned-image
    </style>
@endpush
