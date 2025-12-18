@extends('admin.layouts.app')

@section('panel')
    <div class="card">
        <form action="{{ route('admin.setting.notice.update') }}" method="POST" enctype="multipart/form-data">
            @csrf
            <div class="card-body">
                <div class="row">
                    <div class="col-md-12">
                        <div class="form-group">
                            <label>@lang('All user notice')</label>
                            <textarea class="form-control nicEdit" name="notice" rows="10">{{ __(gs('notice')) }}</textarea>
                        </div>
                        <div class="form-group">
                            <label>@lang('Free user notice')</label>
                            <textarea class="form-control nicEdit" name="free_user_notice" rows="10">{{ __(gs('free_user_notice')) }}</textarea>
                        </div>
                        <div>
                            <button class="btn w-100 h-45 btn-primary mr-2" type="submit">@lang('Update')</button>
                        </div>
                    </div>

                </div>
            </div>
        </form>
    </div>
@endsection
