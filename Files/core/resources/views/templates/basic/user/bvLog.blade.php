@extends($activeTemplate . 'layouts.master')

@section('content')
    <div class="row">
        <div class="col-lg-12">
            <div class="card custom--card p-0">
                <div class="card-body p-0">
                    <div class="table-responsive--sm">
                        <table class="custom--table table">
                            <thead>
                                <tr>
                                    <th>@lang('Sl')</th>
                                    <th>@lang('BV')</th>
                                    <th>@lang('Position')</th>
                                    <th>@lang('Detail')</th>
                                    <th>@lang('Date')</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($logs as $key=>$data)
                                    <tr>
                                        <td>{{ $logs->firstItem() + $key }}</td>
                                        <td class="budget">
                                            <strong @if ($data->trx_type == '+') class="text--success" @else class="text--danger" @endif>
                                                {{ $data->trx_type == '+' ? '+' : '-' }} {{ getAmount($data->amount) }}</strong>
                                        </td>
                                        <td>
                                            @if ($data->position == 1)
                                                <span class="badge badge--success">@lang('Left')</span>
                                            @else
                                                <span class="badge badge--primary">@lang('Right')</span>
                                            @endif
                                        </td>
                                        <td>{{ $data->details }}</td>
                                        <td>{{ $data->created_at != '' ? date('d/m/y  g:i A', strtotime($data->created_at)) : __('Not Assign') }}</td>
                                    </tr>

                                @empty
                                    <tr>
                                        <td class="text-muted text-center" colspan="100%">{{ __($emptyMessage) }}</td>
                                    </tr>
                                @endforelse

                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            @if ($logs->hasPages())
                <div class="mt-4">
                    {{ paginateLinks($logs) }}
                </div>
            @endif
        </div>
    </div>
@endsection
