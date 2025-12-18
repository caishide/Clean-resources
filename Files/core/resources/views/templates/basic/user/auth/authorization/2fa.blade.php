@extends($activeTemplate . 'layouts.frontend')
@section('content')
    <div class="padding-top padding-bottom">
        <div class="container">
            <div class="d-flex justify-content-center">
                <div class="verification-code-wrapper">
                    <div class="verification-area">
                        <h5 class="border-bottom pb-3 text-center">@lang('2FA Verification')</h5>
                        <form class="submit-form mt-4" action="{{ route('user.2fa.verify') }}" method="POST">
                            @csrf

                            @include($activeTemplate . 'partials.verification_code')

                            <button class="btn btn--base w-100" type="submit">@lang('Submit')</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
