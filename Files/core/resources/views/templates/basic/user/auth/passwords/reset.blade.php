@extends($activeTemplate.'layouts.frontend')
@section('content')
<div class="container padding-bottom padding-top">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-7 col-xl-5">
            <div class="card custom--card">
                <div class="card-body">
                    <div class="mb-4">
                        <p>@lang('user.account_verified_successfully')</p>
                    </div>
                    <form method="POST" action="{{ route('user.password.update') }}">
                        @csrf
                        <input type="hidden" name="email" value="{{ $email }}">
                        <input type="hidden" name="token" value="{{ $token }}">
                        <div class="form--group">
                            <label class="form--label">@lang('user.password')</label>
                            <input type="password" class="form-control form--control @if(gs('secure_password')) secure-password @endif" name="password" required>
                        </div>
                        <div class="form--group">
                            <label class="form--label">@lang('user.confirm_password')</label>
                            <input type="password" class="form-control form--control" name="password_confirmation" required>
                        </div>
                      
                            <button type="submit" class="btn btn--base w-100"> @lang('user.submit')</button>
                       
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@if(gs('secure_password'))
    @push('script-lib')
        <script src="{{ asset('assets/global/js/secure_password.js') }}"></script>
    @endpush
@endif
