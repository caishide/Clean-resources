@extends($activeTemplate . 'layouts.frontend')
@section('content')
    <!-- Blog Section Starts Here -->
    <section class="blog-section padding-bottom padding-top">
        <div class="container">
            <div class="row justify-content-center gy-4">
                @foreach ($blogs as $blog)
                    <div class="col-lg-4 col-md-6 col-sm-10">
                        <div class="post-item h-100">
                            <div class="post-thumb"><img src="{{ frontendImage('blog', 'thumb_' . @$blog->data_values->image) }}" alt="blog">
                                <div class="meta-date">
                                    <span class="date">{{ showDateTime($blog->created_at, 'd M') }}</span>
                                    <span>{{ showDateTime($blog->created_at, 'Y') }}</span>
                                </div>
                            </div>
                            <div class="post-content">
                                <h4 class="title"><a href="{{ route('blog.details', $blog->slug) }}">{{ __($blog->data_values->title) }}</a></h4>
                                <p>@php echo shortDescription(strip_tags($blog->data_values->description),120) @endphp</p>
                                <a class="read-more" href="{{ route('blog.details', $blog->slug) }}">@lang('Read More')</a>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
            @if ($blogs->hasPages())
                <div class="pagination-wrapper">
                    {{ paginateLinks($blogs) }}
                </div>
            @endif
        </div>
        <div class="shape shape1">
            <img src="{{ asset($activeTemplateTrue . 'images/shape/blob1.png') }}" alt="shap">
        </div>
    </section>
    <!-- Blog Section Ends Here -->

    @if ($sections->secs != null)
        @foreach (json_decode($sections->secs) as $sec)
            @include($activeTemplate . 'sections.' . $sec)
        @endforeach
    @endif
@endsection
