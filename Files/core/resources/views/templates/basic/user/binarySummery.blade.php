@extends($activeTemplate.'layouts.master')

@section('content')
<div class="card custom--card p-0">
    <div class="card-body p-0">
        <div class="table-responsive--sm">
            <table class="table custom--table">
                <thead>
                <tr>
                    <th>@lang('user.paid_left')</th>
                    <th>@lang('user.paid_right')</th>
                    <th>@lang('user.free_left')</th>
                    <th>@lang('user.free_right')</th>
                    <th>@lang('user.bv_left')</th>
                    <th>@lang('user.bv_right')</th>
                </tr>
                </thead>
                <tbody>
                <tr>
                    <td>{{$logs->paid_left}}</td>
                    <td>{{$logs->paid_right}}</td>
                    <td>{{$logs->free_left}}</td>
                    <td>{{$logs->free_right}}</td>
                    <td>{{getAmount($logs->bv_left)}}</td>
                    <td>{{getAmount($logs->bv_right)}}</td>
                </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
