@extends($activeTemplate.'layouts.master')

@section('content')
<div class="card custom--card p-0">
    <div class="card-body p-0">
        <div class="table-responsive--sm">
            <table class="table custom--table">
                <thead>
                <tr>
                    <th>@lang('Paid left')</th>
                    <th>@lang('Paid right')</th>
                    <th>@lang('Free left')</th>
                    <th>@lang('Free right')</th>
                    <th>@lang('Bv left')</th>
                    <th>@lang('Bv right')</th>
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
