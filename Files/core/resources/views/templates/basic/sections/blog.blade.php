@php
    $blogSectionContent = getContent('blog.content', true);
    $blogs = getContent('blog.element', false, 3);
@endphp

<section class="blog-section padding-bottom pos-rel">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8 col-md-10">
                <div class="section-header text-center">
                    <span class="subtitle">{{ __(@$blogSectionContent->data_values->heading) }}</span>
                    <h2 class="title">{{ __(@$blogSectionContent->data_values->sub_heading) }}</h2>
                </div>
            </div>
        </div>
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
                            <a href="{{ route('blog.details', $blog->slug) }}" class="read-more">@lang('Read More')</a>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
    <div class="shape shape1">
        <img src="{{ asset($activeTemplateTrue . 'images/shape/blob1.png') }}" alt="shap">
    </div>
</section>
