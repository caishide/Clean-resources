@php
    $referContent = getContent('refer.content', true);
@endphp

@if (!blank($referContent))
    <section class="referral-section"
        style="background: url({{ frontendImage('refer', @$referContent->data_values->background_image, '1900x1200') }}) center;">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6">
                    <div class="refer-content">
                        <h2 class="title">{{ __(@$referContent->data_values->heading) }}</h2>
                        <p>{{ __(@$referContent->data_values->description) }}</p>
                        <a class="cmn--btn active" href="#0"><span>@lang('Get Started')</span></a>
                        <div class="shape shape1"><img src="{{ asset($activeTemplateTrue . 'images/icon/gft.png') }}" alt="icon"></div>
                    </div>
                </div>
                <div class="col-lg-6 d-none d-lg-block">
                    <div class="refer-thumb">
                        <img src="{{ frontendImage('refer', @$referContent->data_values->refer_image, '650x580') }}" alt="thumb">
                    </div>
                </div>
            </div>
        </div>
    </section>
@endif
