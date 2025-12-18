@php

    $worksContent = getContent('how_it_works.content', true);
    $worksElements = getContent('how_it_works.element');
@endphp

@if (!blank($worksContent))
    <section class="work-section padding-bottom pos-rel">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-8 col-md-10">
                    <div class="section-header text-center">
                        <span class="subtitle">{{ __(@$worksContent->data_values->heading) }}</span>
                        <h2 class="title">{{ __(@$worksContent->data_values->sub_heading) }}</h2>
                    </div>
                </div>
            </div>
            <div class="row gy-5">

                @if (@$worksElements && !empty($worksElements->toArray()))
                    @foreach (@$worksElements as $worksSectionElement)
                        <div class="col-lg-4 col-md-6 col-sm-6">
                            <div class="work-item">
                                <div class="work-icon">@php echo @$worksSectionElement->data_values->icon @endphp</div>
                                <div class="work-content">
                                    <h4 class="title">{{ __(@$worksSectionElement->data_values->title) }}</h4>
                                    <p>{{ __(@$worksSectionElement->data_values->description) }}</p>
                                </div>
                            </div>
                        </div>
                    @endforeach
                @endif

            </div>
        </div>
        <div class="shape shape1"><img src="{{ asset($activeTemplateTrue . 'images/shape/circle-big.png') }}" alt="shape"></div>
    </section>

@endif
