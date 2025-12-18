@extends($activeTemplate . 'layouts.frontend')
@section('content')
    <section class="blog-details padding-top padding-bottom">
        <div class="container">
            <div class="row gy-5">
                <div class="col-lg-8">
                    <div class="blog-details-wrapper">
                        <div class="details-thumb"><img src="{{ frontendImage('blog',@$blog->data_values->image, '820x540') }}" alt="blog"></div>
                        <h3 class="title">{{ __($blog->data_values->title) }}</h3>
                        <div>
                            @php echo $blog->data_values->description @endphp
                        </div>
                        <div class="mt-5">
                            <div class="blog-details__share d-flex align-items-center mt-4 flex-wrap">
                                <ul class="social-list">
                                    <li class="social-list__item">
                                        <b>@lang('Share Now :')</b>
                                    </li>
                                    <li class="social-list__item">
                                        <a class="social-list__link flex-center facebook"
                                            href="https://www.facebook.com/sharer/sharer.php?u={{ urlencode(url()->current()) }}" target="_blank">
                                            <i class="fab fa-facebook-f"></i>
                                        </a>
                                    </li>
                                    <li class="social-list__item">
                                        <a class="social-list__link flex-center twitter"
                                            href="https://x.com/intent/tweet?text={{ __($blog->data_values->title) }}&amp;url={{ urlencode(url()->current()) }}"
                                            target="_blank">
                                            <i class="fab fa-twitter"></i>
                                        </a>
                                    </li>
                                    <li class="social-list__item">
                                        <a class="social-list__link flex-center linkedin"
                                            href="http://www.linkedin.com/shareArticle?mini=true&amp;url={{ urlencode(url()->current()) }}&amp;title={{ __($blog->data_values->title) }}&amp;summary=@php echo strLimit(strip_tags($blog->data_values->description),100) @endphp"
                                            target="_blank">
                                            <i class="fab fa-linkedin-in"></i>
                                        </a>
                                    </li>
                                    <li class="social-list__item">
                                        <a class="social-list__link flex-center instagram"
                                            href="https://www.instagram.com/sharer.php?u={{ urlencode(url()->current()) }}" target="_blank">
                                            <i class="fab fa-instagram"></i>
                                        </a>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <div class="comments-area">
                        <div class="fb-comments" data-width="100%" data-href="{{ url()->current() }}" data-numposts="5"></div>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="blog-sidebar">
                        <div class="sidebar-item">
                            <h5 class="title">@lang('Recent Post')</h5>
                            <div class="recent-post-wrapper">
                                @foreach ($latestBlogs as $blog)
                                    <div class="recent-post-item">
                                        <div class="thumb"><img src="{{ frontendImage('blog', 'thumb_' . @$blog->data_values->image, '820x540') }}"
                                                alt="blog"></div>
                                        <div class="content">
                                            <h6 class="title hover-line"><a
                                                    href="{{ route('blog.details', $blog->slug) }}">{{ __($blog->data_values->title) }}</a>
                                            </h6>
                                            <span class="date"><i
                                                    class="las la-calendar-check"></i>{{ showDateTime($blog->created_at, 'd M, Y') }}</span>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection

@push('fbComment')
    @php echo loadExtension('fb-comment') @endphp
@endpush
