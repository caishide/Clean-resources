@php
    $bannerContent = getContent('banner.content', true);
@endphp
@section('content')
    @if ($bannerContent != null)
        <section class="banner-section bg_img" style="background: url({{ frontendImage('banner', $bannerContent->data_values->image) }}) left center;">
            <span class="bg-shape"></span>
            <div class="container">
                <div class="banner-content">
                    <h1 class="title">{{ __(@$bannerContent->data_values->heading) }}</h1>
                    <p>{{ __(@$bannerContent->data_values->sub_heading) }}</p>
                    <div class="button--wrapper">
                        <a class="cmn--btn active"
                            href="{{ @$bannerContent->data_values->left_button_link }}"><span>{{ __(@$bannerContent->data_values->left_button) }}</span></a>
                        <a class="cmn--btn"
                            href="{{ @$bannerContent->data_values->right_button_link }}"><span>{{ __(@$bannerContent->data_values->right_button) }}</span></a>
                    </div>
                </div>
            </div>
            <div class="shapes d-none d-sm-block">
                <div class="shape shape1">
                    <img src="{{ asset($activeTemplateTrue . 'images/shape/circle-triangle.png') }}" alt="shape">
                </div>
                <div class="shape2 shape">
                    <img src="{{ asset($activeTemplateTrue . 'images/shape/shape-circle.png') }}" alt="shape">
                </div>
                <div class="shape3 shape">
                    <img src="{{ asset($activeTemplateTrue . 'images/shape/dots-colour.png') }}" alt="shape">
                </div>
                <div class="shape4 shape">
                    <img src="{{ asset($activeTemplateTrue . 'images/shape/plus-big.png') }}" alt="shape">
                </div>
                <div class="shape5 shape">
                    <img src="{{ asset($activeTemplateTrue . 'images/shape/waves.png') }}" alt="shape">
                </div>
            </div>
        </section>
    @endif
