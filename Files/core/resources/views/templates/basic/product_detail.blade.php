@extends($activeTemplate . 'layouts.frontend')
@section('content')

    <!-- Product Details Section Starts Here -->
    <section class="product-details padding-top padding-bottom pos-rel">
        <div class="container">
            <div class="row gy-4 gy-sm-5">
                <div class="col-lg-5">
                    <div class="product-thumb-wrapper">
                        <div class="sync1 owl-carousel owl-theme">
                            <div class="thumbs">
                                <img class="zoom_img" src="{{ getImage(getFilePath('products') . '/' . $product->thumbnail, getFilePath('products')) }}"
                                    alt="products-details">
                            </div>
                            @if ($product->images != null)
                                @foreach ($product->images as $image)
                                    <div class="thumbs"> <img class="zoom_img"
                                            src="{{ getImage(getFilePath('products') . '/' . $image->name, getFileSize('products')) }}"
                                            alt="products-details"></div>
                                @endforeach
                            @endif
                        </div>
                        <div class="sync2 owl-carousel owl-theme mt-2">
                            <div class="thumbs">
                                <img class="zoom_img" src="{{ getImage(getFilePath('products') . '/' . $product->thumbnail, getFileSize('products')) }}"
                                    alt="products-details">
                            </div>
                            @if ($product->images != null)
                                @foreach ($product->images as $image)
                                    <div class="thumbs">
                                        <img src="{{ getImage(getFilePath('products') . '/' . $image->name, getFileSize('products')) }}"
                                            alt="products-details">
                                    </div>
                                @endforeach
                            @endif
                        </div>
                    </div>
                </div>
                <div class="col-lg-7">
                    <div class="product-info-wrapper">
                        <h3 class="title">{{ __(@$product->name) }}</h3>
                        <div class="product-price">
                            <span class="current-price">{{ showAmount($product->price) }}</span>
                        </div>
                        @if ($product->quantity > 0)
                            <span class="custom--badge bg--success mt-3">@lang('In Stock')</span>
                        @else
                            <span class="custom--badge bg--danger mt-3">@lang('Out of Stock')</span>
                        @endif
                        <div class="add-cart-wrapper">
                            <div class="cart-plus-minus">
                                <div class="cart-decrease qtybutton dec"><i class="las la-minus"></i></div>
                                <input class="cart-count" type="text" value="1" readonly>
                                <div class="cart-increase qtybutton inc active"><i class="las la-plus"></i></div>
                            </div>
                            @if ($product->quantity > 0)
                                <a class="cart--btn cmn--btn-2 bg--primary purchaseBtn" data-id="{{ $product->id }}" data-name="{{ $product->name }}"
                                    href="javascript:void(0)">@lang('Purchase Now')</a>
                            @endif
                        </div>
                        <ul class="product-meta">
                            <li class="meta-item">
                                <h6 class="title">@lang('Category') :</h6>
                                <a href="{{ route('products', $product->category_id) }}">{{ __($product->category->name) }}</a>
                            </li>
                            <li class="meta-item">
                                <h6 class="title">@lang('Tags') :</h6>
                                <div>
                                    @foreach ($product->meta_keyword as $keyword)
                                        <a href="#0">{{ $keyword }}</a>
                                    @endforeach
                                </div>
                            </li>
                        </ul>
                        @if ($product->specifications)
                            <div class="specifications mt-3">
                                <h5 class="title">@lang('Product Full Specifications')</h5>
                                <table class="specification-table">
                                    <tbody>
                                        @foreach ($product->specifications as $specification)
                                            <tr>
                                                <th>{{ $specification['name'] }}</th>
                                                <td>{{ $specification['value'] }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
        <div class="shape shape1"><img src="{{ asset($activeTemplateTrue . 'images/shape/blob1.png') }}" alt="shape"></div>
    </section>

    <div class="product-details section-bg pb-80">
        <div class="container">
            <div class="product-details-wrapper">
                <div class="description">
                    <h3 class="mb-3">@lang('Description')</h3>
                    @php echo @$product->description @endphp
                </div>

                <div class="mt-5">
                    <div class="blog-details__share d-flex align-items-center flex-wrap">
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
                                    href="https://x.com/intent/tweet?text={{ __($product->name) }}&amp;url={{ urlencode(url()->current()) }}"
                                    target="_blank">
                                    <i class="fab fa-twitter"></i>
                                </a>
                            </li>
                            <li class="social-list__item">
                                <a class="social-list__link flex-center linkedin"
                                    href="http://www.linkedin.com/shareArticle?mini=true&amp;url={{ urlencode(url()->current()) }}&amp;title={{ __($product->name) }}&amp;summary=@php echo strLimit(strip_tags($product->meta_description),100) @endphp"
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
        </div>
    </div>

    <!-- Products Section Starts Here -->
    <section class="product-section padding-top padding-bottom">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-8 col-md-10">
                    <div class="section-header text-center">
                        <h2 class="title">@lang('Related Products')</h2>
                    </div>
                </div>
            </div>
            <div class="product-slider owl-carousel owl-theme owl-loaded owl-drag">
                @foreach ($relates as $product)
                    <div class="owl-item">
                        <div class="product-item h-100">
                            <div class="product-thumb">
                                <img src="{{ getImage(getFilePath('products') . '/' . $product->thumbnail, getFileSize('products')) }}" alt="products">
                                <ul class="product-options">
                                    <li><a class="image"
                                            href="{{ getImage(getFilePath('products') . '/' . $product->thumbnail, getFileSize('products')) }}"><i
                                                class="las la-expand"></i></a></li>
                                </ul>
                            </div>
                            <div class="product-content">
                                <h6 class="product-title"><a
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
            @if (blank($relates))
                <span class="w-100 text-center">@lang('Related product not found')</span>
            @endif
        </div>
    </section>
    <!-- Products Section Ends Here -->
@endsection

@push('modal')
    <div class="modal fade" id="purchaseModal" aria-labelledby="exampleModalLabel" aria-hidden="true" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="exampleModalLabel">@lang('Purchase Confirmation')</h5>
                    <button class="btn-close" data-bs-dismiss="modal" type="button" aria-label="Close"></button>
                </div>
                <form action="{{ route('user.purchase') }}" method="post">
                    @csrf
                    <div class="modal-body">
                        <input name="quantity" type="hidden">
                        <input name="product_id" type="hidden">
                        <h6>@lang('Are you sure to buy') <span class="quantity"></span> @lang('pieces') "<span class="p_name"></span>"</h6>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn--dark" data-bs-dismiss="modal" type="button">@lang('Close')</button>
                        <button class="btn btn--base" type="submit">@lang('Purchase')</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endpush

@push('script')
    <script>
        (function($) {
            "use strict";

            $('.purchaseBtn').on('click', function() {
                var counter = $('.cart-count').val();
                var modal = $('#purchaseModal');
                modal.find('input[name=quantity]').val(counter);
                modal.find('input[name=product_id]').val($(this).data('id'));
                modal.find('.p_name').text($(this).data('name'));
                modal.find('.quantity').text(counter);
                modal.modal('show');
            });
        })(jQuery);
    </script>
@endpush

@push('style')
    <style>
        .variants-wrapper {
            margin-left: 15px;
        }
    </style>
@endpush
