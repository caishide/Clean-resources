<!-- ============Footer Section Starts Here============ -->
@php
    $socials     = getContent('social_icon.element');
    $footer      = getContent('footer_section.content', true);
    $policyPages = getContent('policy_pages.element', false, orderById: true);

@endphp


<!-- Footer Section Starts Here -->
<footer class="footer-section">
    <div class="footer-top">
        <div class="container">
            <div class="row justify-content-between gy-5">
                <div class="col-lg-4 col-xl-3 col-sm-6">
                    <div class="footer-widget p-0">
                        <div class="logo"><a href="{{ route('home') }}"><img src="{{ siteLogo('dark') }}" alt="logo"></a></div>
                        <p>{{ __($footer->data_values->description) }}</p>
                    </div>
                </div>
                <div class="col-lg-2 col-xl-3 col-sm-6">
                    <div class="footer-widget">
                        <h4 class="widget-title">@lang('Quick Links')</h4>
                        <ul class="footer-links">
                            <li><a href="{{ route('home') }}"><i class="las la-angle-double-right"></i>@lang('Home')</a></li>
                            <li><a href="{{ route('products') }}"><i class="las la-angle-double-right"></i>@lang('Products')</a></li>
                            <li><a href="{{ route('blog') }}"><i class="las la-angle-double-right"></i>@lang('Blog')</a></li>
                            <li><a href="{{ route('contact') }}"><i class="las la-angle-double-right"></i>@lang('Contact')</a></li>
                        </ul>
                    </div>
                </div>
                <div class="col-lg-2 col-xl-3 col-sm-6">
                    <div class="footer-widget">
                        <h4 class="widget-title">@lang('Policy Links')</h4>
                        <ul class="footer-links">
                            @foreach ($policyPages as $policy)
                                <li><a href="{{ route('policy.pages', $policy->slug) }}"><i
                                            class="las la-angle-double-right"></i>{{ __($policy->data_values->title) }}</a></li>
                            @endforeach
                        </ul>
                    </div>
                </div>
                <div class="col-lg-3 col-xl-3 col-sm-6">
                    <div class="footer-widget">
                        <h4 class="widget-title">@lang('Contact')</h4>
                        <ul class="footer-info">
                            <li>
                                <p><i class="las la-map-marker"></i>{{ @$footer->data_values->website_footer_address }}</p>
                            </li>
                            <li><a href="tel:{{ @$footer->data_values->website_footer_phone_number }}"><i
                                        class="las la-phone-volume"></i>{{ @$footer->data_values->website_footer_phone_number }}</a></li>
                            <li><a href="mailto:{{ @$footer->data_values->website_footer_email }}"><i
                                        class="las la-envelope"></i>{{ @$footer->data_values->website_footer_email }}</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="footer-bottom">
        <div class="container">
            <div class="footer-bottom-wrapper">
                <p class="copy-text">&copy; @lang('All Right Reserved By') <a href="{{ route('home') }}">{{ __(gs('site_name')) }}</a></p>
                <ul class="social-icons">
                    @foreach ($socials as $social)
                        <li>
                            <a href="{{ @$social->data_values->url }}" title="{{ @$social->data_values->title }}" target="_blank">
                                @php echo @$social->data_values->social_icon; @endphp
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    </div>
</footer>
