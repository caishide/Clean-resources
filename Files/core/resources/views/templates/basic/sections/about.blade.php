@php
    $aboutContent = getContent('about.content', true);
@endphp
@if (!blank(@$aboutContent))
    <section class="about-section padding-top padding-bottom">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6 d-none d-lg-block">
                    <div class="about-thumb rtl">
                        <img src="{{ frontendImage('about', @$aboutContent->data_values->about_image, '700x700') }}" alt="thumb" class="w-100">
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="about-content">
                        <div class="section-header">
                            <span class="subtitle">{{ __(@$aboutContent->data_values->heading) }}</span>
                            <h2 class="title">{{ __(@$aboutContent->data_values->sub_heading) }}</h2>
                            <p>{{ __(@$aboutContent->data_values->description) }}</p>
                        </div>

                        <a href="{{ @$aboutContent->data_values->button_url }}"
                            class="cmn--btn active"><span>{{ __(@$aboutContent->data_values->button_text) }}</span></a>
                    </div>
                </div>
            </div>
        </div>
        <div class="shape shape1"><img src="{{ asset($activeTemplateTrue . 'images/shape/circle-triangle.png') }}" alt="shape"></div>
        <div class="shape shape2"><img src="{{ asset($activeTemplateTrue . 'images/shape/circle-big.png') }}" alt="shape"></div>
    </section>
@endif
