@extends($activeTemplate.'layouts.frontend')
@section('content')
<div class="container padding-top padding-bottom">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-7 col-xl-5">
            <div class="d-flex justify-content-center">
                <div class="verification-code-wrapper">
                    <div class="verification-area">
                        <h5 class="pb-3 text-center border-bottom">@lang('user.verify_email_address')</h5>
                        <form action="{{ route('user.password.verify.code') }}" method="POST" class="submit-form">
                            @csrf
                            <p class="verification-text">@lang('user.6_digit_verification_code_email') :  {{ showEmailAddress($email) }}</p>
                            <input type="hidden" name="email" value="{{ $email }}">
                            @include($activeTemplate.'partials.verification_code')
                            <div class="form-group">
                                <button type="submit" class="btn btn--base w-100">@lang('user.submit')</button>
                            </div>
                            <div class="form-group">
                                @lang('user.please_check_spam_folder')
                                <a href="{{ route('user.password.request') }}">@lang('user.try_to_send_again')</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
