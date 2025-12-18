@extends($activeTemplate . 'layouts.frontend')
@section('content')
    <section class="product-section padding-top padding-bottom mb-5">
        <div class="container">
            <ul class="mr-list justify-content-center">
                <li class="mr-list__item">
                    <a class="mr-list__btn @if (!$categoryId) active @endif" href="{{ route('products') }}">@lang('All Products')</a>
                </li>
                @foreach ($categories as $category)
                    <li class="mr-list__item">
                        <a class="mr-list__btn @if ($categoryId == $category->id) active @endif"
                            href="{{ route('products', $category->id) }}">{{ __($category->name) }}</a>
                    </li>
                @endforeach
            </ul>
            <div class="row g-3 justify-content-center">
                @foreach ($products as $product)
                    <div class="col-xl-3 col-lg-4 col-md-4 col-sm-6">
                        <div class="product-item h-100">
                            <div class="product-thumb">
                                <img src="{{ getImage(getFilePath('products') . '/' . $product->thumbnail, getFileSize('products')) }}" alt="products">
                                <ul class="product-options">
                                    <li>
                                        <a class="image"
                                            href="{{ getImage(getFilePath('products') . '/' . $product->thumbnail, getFileSize('products')) }}"><i
                                                class="las la-expand"></i>
                                        </a>
                                    </li>
                                </ul>
                            </div>
                            <div class="product-content">
                                <h6 class="product-title">
                                    <a
                                        href="{{ route('product.details', ['id' => $product->id, 'slug' => slug($product->name)]) }}">{{ __(shortDescription($product->name, 35)) }}</a>
                                </h6>

                                @if ($product->quantity >= 0)
                                    <span class="product-availablity text--success">@lang('in stock')</span>
                                @else
                                    <span class="product-availablity text--danger">@lang('out stock')</span>
                                @endif

                                <div class="product-price">
                                    <span class="current-price">{{ showAmount($product->price) }}</span>
                                </div>
                                <a class="add-to-cart cmn--btn-2"
                                    href="{{ route('product.details', ['id' => $product->id, 'slug' => slug($product->name)]) }}">@lang('Details')</a>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
            @if ($products->hasPages())
                <div class="pagination-wrapper">
                    {{ paginateLinks($products) }}
                </div>
            @endif
        </div>
    </section>
@endsection
