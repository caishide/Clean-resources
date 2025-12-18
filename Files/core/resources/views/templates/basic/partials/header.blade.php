<div class="overlay"></div>
<!-- Preloader -->
<div id="preloader">
    <div id="loader"></div>
</div>

<!-- Header Section Starts Here -->
<header class="header">
    <div class="header-bottom">
        <div class="container">
            <div class="header-bottom-area">
                <div class="logo">
                    <a href="{{ route('home') }}">
                        <img src="{{ siteLogo('dark') }}" alt="logo">
                    </a>
                </div>
                <div class="header-trigger-wrapper d-flex d-lg-none align-items-center">
                    <div class="header-trigger d-block d-lg-none">
                        <span></span>
                    </div>
                    <div class="account-cart-wrapper">
                        <a class="account" href="{{ route('user.login') }}"><i class="las la-user"></i></a>
                    </div>
                </div>

                <ul class="menu">
                    <li><a href="{{ route('home') }}">@lang('Home')</a></li>
                    <li><a href="{{ route('products') }}">@lang('Product')</a></li>

                    @foreach ($pages as $k => $data)
                        <li><a href="{{ route('pages', [$data->slug]) }}">{{ $data->name }}</a></li>
                    @endforeach
                    <li><a href="{{ route('blog') }}">@lang('Blog')</a></li>
                    <li><a href="{{ route('contact') }}">@lang('Contact')</a></li>

                    <li>

                        @if (gs('multi_language'))
                            @php
                                $language = App\Models\Language::all();
                                $selectLang = $language->where('code', config('app.locale'))->first();
                                $currentLang = session('lang')
                                    ? $language->where('code', session('lang'))->first()
                                    : $language->where('is_default', Status::YES)->first();
                            @endphp

                            <div class="custom--dropdown">
                                <div class="custom--dropdown__selected dropdown-list__item">
                                    <div class="thumb">
                                        <img src="{{ getImage(getFilePath('language') . '/' . $currentLang->image, getFileSize('language')) }}"
                                            alt="image">
                                    </div>
                                    <span class="text"> {{ __(@$selectLang->name) }} </span>
                                </div>
                                <ul class="dropdown-list">
                                    @foreach ($language as $item)
                                        <li class="dropdown-list__item" data-value="en">
                                            <a class="thumb" href="{{ route('lang', $item->code) }}"> <img
                                                    src="{{ getImage(getFilePath('language') . '/' . $item->image, getFileSize('language')) }}"
                                                    alt="image">
                                                <span class="text"> {{ __($item->name) }} </span>
                                            </a>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                    </li>

                    <li class="account-cart-wrapper d-none d-lg-block">
                        <a class="account" href="{{ route('user.login') }}"><i class="las la-user"></i></a>
                    </li>
                </ul> <!-- Menu End -->
            </div>
        </div>
    </div>
</header>
<!-- Header Section Ends Here -->
