@php
	$customCaptcha = loadCustomCaptcha();
    $googleCaptcha = loadReCaptcha()
@endphp
@if($googleCaptcha)
    <div class="mb-3">
        @php echo $googleCaptcha @endphp
    </div>
@endif
@if($customCaptcha)
    <div class="form-group {{ $custom ? 'form--group' : 'form-group' }}">
        <div class="mb-2">
            @php echo $customCaptcha @endphp
        </div>
        <label class="{{ $custom ? 'form--label' : 'form-label' }}">@lang('Captcha')</label>
        <input type="text" name="captcha" class="form-control form--control" placeholder="{{ $custom ? 'Enter Captcha Code' : '' }}" required>
    </div>
@endif
@if($googleCaptcha)
@push('script')
    <script>
        (function($){
            "use strict"
            $('.verify-gcaptcha').on('submit',function(){
                var response = grecaptcha.getResponse();
                if (response.length == 0) {
                    document.getElementById('g-recaptcha-error').innerHTML = '<span class="text--danger">@lang("Captcha field is required.")</span>';
                    return false;
                }
                return true;
            });

            window.verifyCaptcha = () => {
                document.getElementById('g-recaptcha-error').innerHTML = '';
            }
        })(jQuery);
    </script>
@endpush
@endif
