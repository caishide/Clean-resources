@extends($activeTemplate . 'layouts.admin')

@section('content')
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title border-bottom pb-3 text-center">@lang('2FA Verification Required for Impersonation')</h5>
                    <p class="text-center mt-3">
                        <strong>@lang('Admin Impersonation Security Check')</strong>
                    </p>
                    <p class="text-muted text-center small">
                        @lang('You are attempting to impersonate a user who has 2FA enabled. Please enter the 2FA verification code from their authenticator app to proceed.')
                    </p>

                    @if(session('impersonate_intent'))
                        <div class="alert alert-warning">
                            <h6><i class="las la-info-circle"></i> @lang('Impersonation Details')</h6>
                            <hr>
                            <ul class="list-unstyled">
                                <li><strong>@lang('User ID:')</strong> {{ session('impersonate_intent.user_id') }}</li>
                                <li><strong>@lang('Admin ID:')</strong> {{ session('impersonate_intent.admin_id') }}</li>
                                <li><strong>@lang('IP Address:')</strong> {{ session('impersonate_intent.ip') }}</li>
                                <li><strong>@lang('Reason:')</strong> {{ session('impersonate_intent.reason') }}</li>
                            </ul>
                        </div>
                    @endif

                    <form class="submit-form mt-4" action="{{ route('admin.users.impersonate.verify', $userId ?? 0) }}" method="POST">
                        @csrf

                        <div class="form-group">
                            <label class="form-label">@lang('Verification Code')</label>
                            <div class="verification-code-wrapper">
                                <div class="verification-code">
                                    <input type="text" name="code" class="form-control" placeholder="@lang('Enter 6-digit code')" required>
                                    @error('code')
                                        <span class="text-danger">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>
                            <small class="text-muted d-block mt-2">
                                @lang('Enter the 6-digit verification code from the user\'s authenticator app (e.g., Google Authenticator, Authy).')
                            </small>
                        </div>

                        <div class="form-group">
                            <button class="btn btn--base w-100" type="submit">
                                <i class="las la-shield-alt"></i> @lang('Verify and Start Impersonation')
                            </button>
                        </div>

                        <div class="form-group text-center">
                            <a href="{{ route('admin.dashboard') }}" class="btn btn-outline-secondary">
                                <i class="las la-arrow-left"></i> @lang('Cancel')
                            </a>
                        </div>
                    </form>

                    <div class="alert alert-info mt-4">
                        <h6><i class="las la-lock"></i> @lang('Security Notice')</h6>
                        <p class="mb-0 small">
                            @lang('All impersonation activities are logged and monitored. Your actions will be recorded with timestamps, IP address, and session details. The impersonation session will automatically expire based on session configuration.')
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
