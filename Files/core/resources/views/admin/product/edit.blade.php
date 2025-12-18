@extends('admin.layouts.app')
@section('panel')
    <form action="{{ route('admin.product.update', @$product->id) }}" method="POST" enctype="multipart/form-data">
        @csrf
        <div class="row">

            <div class="col-lg-12">
                <div class="card">
                    <div class="card-header font-weight-bold bg--primary">@lang('Product Basic Information')</div>
                    <div class="card-body">
                        <div class="form-group row">
                            <div class="col-md-2">
                                <label>@lang('Product Name') </label>
                            </div>
                            <div class="col-md-10">
                                <input class="form-control" name="name" type="text" value="{{ @$product->name }}" placeholder="Name" required>
                            </div>
                        </div>

                        <div class="form-group row">
                            <div class="col-md-2">
                                <label>@lang('Categories')</label>
                            </div>
                            <div class="col-md-10">
                                <select class="form-control select2" name="category" required>
                                    <option disabled>@lang('Please Select One')</option>
                                    @foreach (@$categories as $category)
                                        <option value="{{ $category->id }}" @if ($category->id == $product->category_id) selected @endif>{{ $category->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="form-group row">
                            <div class="col-md-2">
                                <label>@lang('Price')</label>
                            </div>
                            <div class="col-md-10">
                                <input class="form-control" name="price" type="number" value="{{ getAmount($product->price) }}" step="any" placeholder="Price" required>
                            </div>
                        </div>

                        <div class="form-group row">
                            <div class="col-md-2">
                                <label>@lang('Quantity')</label>
                            </div>
                            <div class="col-md-10">
                                <input class="form-control" name="quantity" type="number" value="{{ @$product->quantity }}" placeholder="Quantity" required>
                            </div>
                        </div>

                        <div class="form-group row">
                            <div class="col-md-2">
                                <label>@lang('BV')</label>
                            </div>
                            <div class="col-md-10">
                                <input class="form-control" name="bv" type="number" value="{{ $product->bv }}" placeholder="@lang('BV')" required>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-12 mt-5">
                <div class="card">
                    <div class="card-header font-weight-bold bg--primary">@lang('Product Description')</div>
                    <div class="card-body">
                        <div class="form-group row">
                            <div class="col-md-2">
                                <label class="font-weight-bold">@lang('Product Discription') <strong class="text-danger">*</strong> </label>
                            </div>
                            <div class="col-md-10">
                                <textarea class="form-control nicEdit" id="my-textarea" name="description" rows="3">{{ @$product->description }}</textarea>
                            </div>
                        </div>

                        <div class="form-group row">
                            <div class="col-md-2">
                                <label for="">@lang('Product Specifications')</label>
                            </div>
                            <div class="col-10">
                                <div id="specification">
                                    @foreach (@$product->specifications ?? [] as $k => $specification)
                                        <div class="row specification align-items-center mb-2">
                                            <div class="col-lg-5 p-1">
                                                <input class="form-control" name="specification[{{ $k }}][name]" type="text" value="{{ @$specification['name'] }}" placeholder="@lang('Enter Specification Name')">
                                            </div>
                                            <div class="col-lg-5 p-1">
                                                <input class="form-control" name="specification[{{ $k }}][value]" type="text" value="{{ @$specification['value'] }}" placeholder="@lang('Enter Specification Value')">
                                            </div>
                                            <div class="col-lg-2 minus-specification p-1 text-right">
                                                <a class="btn btn-outline--danger"><i class="las la-trash-alt"></i></a>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                                <div class="row p-0">
                                    <div class="col-lg-10 p-1"><label class="{{ $product->specifications ? 'd-none' : '' }}" id="specifications-title">@lang('Add specifications as you want by clicking the (+) button on the right side')</label></div>
                                    <div class="col-lg-2 p-1"><a class="btn btn--success add-specification mb-2"><i class="la la-plus"></i></a></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-12 mt-5">
                <div class="card">
                    <div class="card-header font-weight-bold bg--primary">@lang('Product Image')</div>
                    <div class="card-body">
                        <div class="form-group row">
                            <div class="col-md-2">
                                <label>@lang('Thumbnail')</label>
                            </div>
                            <div class="col-md-10">
                                <div class="thumbnail-image-box">
                                    <x-image-uploader class="w-100" name="thumbnail" type="products" image="{{ $product->thumbnail }}" :required=false />
                                </div>
                            </div>
                        </div>
                        <div class="form-group row">
                            <div class="col-md-2">
                                <label class="font-weight-bold" for="">@lang('Gallery')</label>
                            </div>
                            <div class="col-md-10">
                                <div class="input-images"></div>
                                <div class="mt-1">
                                    <small class="text-muted">
                                        @lang('Supported Files:')
                                        <b>@lang('.png, .jpg, .jpeg')</b>
                                        @lang('& you can upload maximum ') <b>@lang('10')</b> @lang('images').
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-12 mt-5">
                <div class="card">
                    <div class="card-header font-weight-bold bg--primary">@lang('SEO Contents')</div>
                    <div class="card-body">
                        <div class="form-group row">
                            <div class="col-md-2">
                                <label>@lang('Meta Ttitle')</label>
                            </div>
                            <div class="col-md-10">
                                <input class="form-control" name="meta_title" type="text" value="{{ @$product->meta_title }}" placeholder="Meta Title">
                            </div>

                        </div>
                        <div class="form-group row">
                            <div class="col-md-2">
                                <label>@lang('Meta Keyword')</label>
                            </div>
                            <div class="col-md-10">
                                <select class="form-control select2-auto-tokenize" name="meta_keywords[]" data-placeholder="@lang('Separate multiple keywords by ,(comma) or enter key')" multiple>
                                    @if (@$product->meta_keyword && !empty(@$product->meta_keyword))
                                        @foreach (@$product->meta_keyword as $keyword)
                                            <option value="{{ $keyword }}" selected>{{ $keyword }}</option>
                                        @endforeach
                                    @endif

                                </select>
                            </div>

                        </div>
                        <div class="form-group row">
                            <div class="col-md-2">
                                <label>@lang('Meta Description')</label>
                            </div>
                            <div class="col-md-10">
                                <textarea class="form-control" name="meta_description" name="meta_description" placeholder="@lang('Meta Description')" cols="30" rows="10">{{ @$product->meta_description }}</textarea>
                            </div>

                        </div>

                    </div>
                </div>
            </div>

            <div class="col-lg-12 mt-3">
                <button class="btn btn--primary w-100 h-45" type="submit">@lang('Update')</button>
            </div>

        </div>

    </form>

@endsection

@push('breadcrumb-plugins')
    <x-back route="{{ route('admin.product.index') }}" />
@endpush


@push('script')
    <script>
        "use strict";
        (function($) {

            $(".add-specification").on('click', function(e) {
                let index = $(document).find(".specification").length;
                index = parseInt(index) + parseInt(1);

                let html = `
                    <div class="row align-items-center mb-2 specification">
                        <div class="col-lg-5 p-1">
                            <input type="text" class="form-control" name="specification[${index}][name]" placeholder="@lang('Enter Specification Name')">
                        </div>
                        <div class="col-lg-5 p-1">
                            <input type="text" class="form-control" name="specification[${index}][value]" placeholder="@lang('Enter Specification Value')">
                        </div>
                        <div class="col-lg-2 text-right minus-specification p-1">
                            <a class="btn btn-outline--danger "><i class="las la-trash-alt"></i></a>
                        </div>
                    </div>
                `;
                $("#specification").append(html)
                $("#specifications-title").hide()
            })


            $("body").on('click', '.minus-specification', function(e) {
                $(this).closest('.specification').remove();
                $(document).find(".specification").length <= 0 ? $("#specifications-title").show() : "";

            })

            $(document).on('click', '.removeBtn', function() {
                $(this).closest('.__gallery_image').remove();
            });

            $('select[name=featured]').val({{ $product->is_featured }});


            // image uploder
            @if (isset($images))
                let preloaded = @json($images);
            @else
                let preloaded = [];
            @endif

            $('.input-images').imageUploader({
                preloaded: preloaded,
                imagesInputName: 'gallery',
                preloadedInputName: 'old',
                maxSize: 3 * 1024 * 1024,
                maxFiles: 10,
            });

        })(jQuery);
    </script>
@endpush

@push('script-lib')
    <script src="{{ asset('assets/admin/js/image-uploader.min.js') }}"></script>
@endpush

@push('style-lib')
    <link href="{{ asset('assets/admin/css/image-uploader.min.css') }}" rel="stylesheet">
@endpush

@push('style')
    <style>
        .profilePicUpload {
            height: 0px;
            padding: 0px;
        }

        .__gallery_image .form-group {
            position: relative;
        }

        .removeBtn {
            position: absolute;
            z-index: 99;
            top: 3px;
            right: 3px;
            border-radius: 5px;
        }

        .thumbnail-image-box {
            max-width: 300px;
        }
    </style>
@endpush
