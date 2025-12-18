@if (!request()->routeIs(['home']))
    @php
        $breadcrumbContent = getContent('breadcrumb.content', true);
    @endphp

    <div class="inner-banner bg_img"
        style="background: url({{ frontendImage('breadcrumb', @$breadcrumbContent->data_values->background_image) }}) center;">
        <div class="container">
            <div class="inner-banner-wrapper">
                <h2 class="title">{{ __($pageTitle) }}</h2>
            </div>
        </div>
    </div>
@endif
