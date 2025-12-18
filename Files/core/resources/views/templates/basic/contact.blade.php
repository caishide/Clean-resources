@extends($activeTemplate . 'layouts.frontend')

@section('content')
    @php
        $contactContent = getContent('contact_us.content', true);
        $contactElement = getContent('contact_us.element');
    @endphp

    <section class="contact-section padding-top padding-bottom">
        <div class="container">
            <div class="row">
                <div class="col-lg-6 d-none d-lg-block">
                    <div class="contact-thumb rtl">
                        <img src="{{ frontendImage('contact_us', @$contactContent->data_values->image, '700x600') }}" alt="thumb">
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="contact-form-wrapper">
                        <h3 class="title">{{ __(@$contactContent->data_values->title) }}</h3>
                        <form class="contact-form verify-gcaptcha" method="post">
                            @csrf
                            <div class="form--group">
                                <label class="form--label">@lang('Name')</label>
                                <input class="form--control" name="name" type="text" value="{{ old('name', @$user->fullname) }}"
                                    placeholder="@lang('Enter Your Full Name')" @if ($user && $user->profile_complete) readonly @endif required>
                            </div>
                            <div class="form--group">
                                <label class="form--label">@lang('Email Address')</label>
                                <input class="form--control" name="email" type="email" value="{{ old('email', @$user->email) }}"
                                    placeholder="@lang('Enter Your Email Address')" @if ($user) readonly @endif required>
                            </div>
                            <div class="form--group">
                                <label class="form--label">@lang('Subject')</label>
                                <input class="form--control" name="subject" type="text" value="{{ old('subject') }}" placeholder="@lang('Enter Your Subject')"
                                    required>
                            </div>
                            <div class="form--group">
                                <label class="form--label" for="msg">@lang('Your Message')</label>
                                <textarea class="form--control" id="msg" name="message" placeholder="@lang('Enter Your Message')" required>{{ old('message') }}</textarea>
                            </div>
                            @php
                                $custom = true;
                            @endphp
                            <x-captcha :custom="$custom" />
                            <div class="form--group">
                                <button class="btn btn--base w-100" type="submit">@lang('Send Us Message')</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="contact-info padding-bottom">
        <div class="shape shape1"><img src="{{ asset($activeTemplateTrue . 'images/shape/all-shape.png') }}" alt="shape"></div>
        <div class="container">
            <div class="row gy-5 justify-content-center">
                <div class="col-lg-6 col-xl-5">
                    <div class="contact-info-wrapper row gy-4 justify-content-center">
                        @foreach ($contactElement as $information)
                            <div class="col-lg-12 col-md-6 col-sm-10">
                                <div class="contact-info-item">
                                    <div class="thumb"><img src="{{ frontendImage('contact_us', $information->data_values->image) }}" alt="icon">
                                    </div>
                                    <div class="content">
                                        <h5 class="title">{{ __(@$information->data_values->title) }} :</h5>
                                        <span>{{ __(@$information->data_values->content) }}</span>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
                <div class="col-lg-6 col-xl-7">
                    <div class="map-wrapper">
                        <iframe class="map" src="{{ __(@$contactContent->data_values->map_iframe_url) }}" style="border:0;" allowfullscreen=""
                            loading="lazy"></iframe>
                    </div>
                </div>
            </div>
        </div>
    </section>

    @if (@$sections->secs != null)
        @foreach (json_decode($sections->secs) as $sec)
            @include($activeTemplate . 'sections.' . $sec)
        @endforeach
    @endif
@endsection
