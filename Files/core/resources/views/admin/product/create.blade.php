@extends('admin.layouts.app')

@section('panel')
    <form action="{{ route('admin.product.store') }}" method="POST" enctype="multipart/form-data">
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
                                <input class="form-control" name="name" type="text" value="{{ old('name') }}" placeholder="@lang('Name')"
                                    required>
                            </div>
                        </div>
                        <div class="form-group row">
                            <div class="col-md-2">
                                <label>@lang('Categories') </label>
                            </div>
                            <div class="col-md-10">
                                <select class="form-control select2" name="category" required>
                                    <option value="">@lang('Select One')</option>
                                    @foreach ($categories as $category)
                                        <option value="{{ $category->id }}" @selected(old('category') == $category->id)>{{ __($category->name) }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="form-group row">
                            <div class="col-md-2">
                                <label>@lang('Price') </label>
                            </div>
                            <div class="col-md-10">
                                <input class="form-control" name="price" type="number" value="{{ old('price') }}" step="any"  placeholder="@lang('Price')" required>
                            </div>
                        </div>

                        <div class="form-group row">
                            <div class="col-md-2">
                                <label>@lang('Quantity')</label>
                            </div>
                            <div class="col-md-10">
                                <input class="form-control" name="quantity" type="number" value="{{ old('quantity') }}" placeholder="@lang('Quantity')"
                                    required>
                            </div>
                        </div>
                        <div class="form-group row">
                            <div class="col-md-2">
                                <label>@lang('BV')</label>
                            </div>
                            <div class="col-md-10">
                                <input class="form-control" name="bv" type="number" value="{{ old('bv') }}" placeholder="@lang('BV')"
                                    required>
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
                                <label>@lang('Product Discription') </label>
                            </div>
                            <div class="col-md-10">
                                <textarea class="form-control nicEdit" id="my-textarea" name="description" rows="3" required> {{ old('description') }} </textarea>
                            </div>
                        </div>
                        <div class="form-group row">
                            <div class="col-md-2">
                                <label for="">@lang('Product Specifications')</label>
                            </div>
                            <div class="col-md-10">
                                <div id="specification"></div>
                                <div class="row">
                                    <div class="col-lg-10 p-1"><label id="specifications-title">@lang('Add specifications as you want by clicking the (+) button on the right side')</label></div>
                                    <div class="col-lg-2 p-1"><a class="btn btn-outline--success add-specification mb-2"><i
                                                class="la la-plus me-0"></i></a></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-12 mt-5">
                <div class="card">
                    <div class="card-header font-weight-bold bg--primary">@lang('Product Images')</div>
                    <div class="card-body">
                        <div class="form-group row">
                            <div class="col-md-2">
                                <label>@lang('Thumbnail')</label>
                            </div>
                            <div class="col-md-10">
                                <div class="thumbnail-image-box">
                                    <x-image-uploader class="w-100" name="thumbnail" type="products" :required=true />
                                </div>
                            </div>
                        </div>
                        <div class="form-group row">
                            <div class="col-md-2">
                                <label>@lang('Gallery')</label>
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
                                <input class="form-control" name="meta_title" type="text" value="{{ old('meta_title') }}"
                                    placeholder="@lang('Meta Title')">
                            </div>

                        </div>
                        <div class="form-group row">
                            <div class="col-md-2">
                                <label>@lang('Meta Keyword')</label>
                            </div>
                            <div class="col-md-10">
                                <select class="form-control select2-auto-tokenize" name="meta_keywords[]" data-placeholder="@lang('Separate multiple keywords by ,(comma) or enter key')"
                                    multiple></select>
                            </div>
                        </div>
                        <div class="form-group row">
                            <div class="col-md-2">
                                <label>@lang('Meta Description') </label>
                            </div>
                            <div class="col-md-10">
                                <textarea class="form-control" id="" name="meta_description" placeholder="@lang('Meta Description')" cols="30" rows="10">{{ old('meta_description') }}</textarea>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-12 mt-3">
                <button class="btn btn--primary h-45 w-100" type="submit">@lang('Submit')</button>
            </div>
        </div>
    </form>
@endsection

@push('breadcrumb-plugins')
    <a class="btn btn-sm btn-outline--dark" href="{{ route('admin.product.index') }}"><i class="las la-undo"></i>@lang('Back')</a>
@endpush

@push('script-lib')
    <script src="{{ asset('assets/admin/js/image-uploader.min.js') }}"></script>
@endpush

@push('style-lib')
    <link href="{{ asset('assets/admin/css/image-uploader.min.css') }}" rel="stylesheet">
@endpush


@push('script')
    <script>
        "use strict";
        (function($) {

            $(".add-specification").on('click', function(e) {
                let index = $(document).find(".specification").length;
                index = parseInt(index) + parseInt(1);

                let html = `
           <div class="row mb-2 align-items-center specification">
            <div class="col-lg-5 p-1">
                <input type="text" class="form-control" name="specification[${index}][name]" placeholder="@lang('Enter Specification Name')">
            </div>
            <div class="col-lg-5 px-1">
                <input type="text" class="form-control" name="specification[${index}][value]" placeholder="@lang('Enter Specification Value')">
            </div>
            <div class="col-lg-2 p-1 text-right minus-specification">
                <a class="btn btn-outline--danger "><i class="las la-trash-alt me-0"></i></a>
            </div>
        </div>
           `;
                $("#specification").append(html)
                $("#specifications-title").hide()
            })


            $("body").on('click', '.minus-specification', function(e) {
                $(this).closest('.specification').remove()
                $(document).find(".specification").length <= 0 ? $("#specifications-title").show() : "";

            })

            let preloaded = [];

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

@push('style')
    <style>
        .thumbnail-image-box {
            max-width: 300px;
        }
    </style>
@endpush
