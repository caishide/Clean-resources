@extends($activeTemplate . 'layouts.master')
@section('content')
    <div class="card custom--card">
        <div class="card-body">
            <form class="register" method="post" enctype="multipart/form-data">
                @csrf

                <div class="row">
                    <div class="form--group col-12">
                        <label class="form--label">@lang('Profile Photo')</label>
                        <input class="form-control" name="image" type="file" accept=".png, .jpg, .jpeg" onchange="loadFile(event)">

                        <small class="text-muted mt-3"> @lang('Supported Files:')
                            <b>@lang('.png, .jpg, .jpeg ')</b> @lang('Image will be resized into') <b>@lang('350x300')</b>@lang('px')
                        </small>
                    </div>

                    <div class="form--group col-sm-6">
                        <label class="form--label">@lang('First Name')</label>
                        <input class="form-control form--control" name="firstname" type="text" value="{{ $user->firstname }}" required>
                    </div>
                    <div class="form--group col-sm-6">
                        <label class="form--label">@lang('Last Name')</label>
                        <input class="form-control form--control" name="lastname" type="text" value="{{ $user->lastname }}" required>
                    </div>

                    <div class="form--group col-sm-6">
                        <label class="form--label">@lang('E-mail Address')</label>
                        <input class="form-control form--control" value="{{ $user->email }}" readonly>
                    </div>
                    <div class="form--group col-sm-6">
                        <label class="form--label">@lang('Mobile Number')</label>
                        <input class="form-control form--control" value="{{ $user->mobile }}" readonly>
                    </div>

                    <div class="form--group col-sm-6">
                        <label class="form--label">@lang('Address')</label>
                        <input class="form-control form--control" name="address" type="text" value="{{ @$user->address }}">
                    </div>
                    <div class="form--group col-sm-6">
                        <label class="form--label">@lang('State')</label>
                        <input class="form-control form--control" name="state" type="text" value="{{ @$user->state }}">
                    </div>

                    <div class="form--group col-sm-4">
                        <label class="form--label">@lang('Zip Code')</label>
                        <input class="form-control form--control" name="zip" type="text" value="{{ @$user->zip }}">
                    </div>

                    <div class="form--group col-sm-4">
                        <label class="form--label">@lang('City')</label>
                        <input class="form-control form--control" name="city" type="text" value="{{ @$user->city }}">
                    </div>

                    <div class="form--group col-sm-4">
                        <label class="form--label">@lang('Country')</label>
                        <input class="form-control form--control" value="{{ @$user->country_name }}" disabled>
                    </div>

                    <div class="col-12">
                        <button class="btn btn--base w-100" type="submit">@lang('Submit')</button>
                    </div>

                </div>

            </form>
        </div>
    </div>
@endsection

@push('script')
    <script>
        'use strict';

        var loadFile = function(event) {
            var output = document.getElementById('output');
            output.src = URL.createObjectURL(event.target.files[0]);
            output.onload = function() {
                URL.revokeObjectURL(output.src)
            }
        };
    </script>
@endpush

@push('style')
    <style>
        .profile-image {
            flex-shrink: 0;
        }

        .profile-image img {
            height: 100px;
            width: 100px;
            border-radius: 50%;
            border: 6px solid #00000010;
        }
    </style>
@endpush
