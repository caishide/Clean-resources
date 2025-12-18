@extends('admin.layouts.app')

@section('panel')
    <div class="row">
        <div class="col-lg-12">
            <div class="card">
                <div class="card-body p-0">
                    <div class="table-responsive--lg table-responsive">
                        <table class="table--light style--two table">
                            <thead>
                                <tr>
                                    <th>@lang('Username')</th>
                                    <th>@lang('BV')</th>
                                    <th>@lang('Position')</th>
                                    <th>@lang('Date')</th>
                                    <th>@lang('Detail')</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($logs as $data)
                                    <tr>
                                        <td>
                                            <span class="fw-bold">{{ $data->user->fullname }}</span>
                                            <br>
                                            <span class="small"> <a href="{{ appendQuery('search', $data->user->username) }}"><span>@</span>{{ $data->user->username }}</a> </span>
                                        </td>
                                        <td class="budget">
                                            <strong @if ($data->trx_type == '+') class="text-success"
                                                @else class="text-danger" @endif> {{ $data->trx_type == '+' ? '+' : '-' }} {{ getAmount($data->amount) }}</strong>
                                        </td>

                                        <td>
                                            @php echo $data->positionBadge @endphp
                                        </td>
                                        <td>
                                            {{ showDateTime($data->created_at) }}
                                        </td>
                                        <td>{{ $data->details }}</td>
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
                @if ($logs->hasPages())
                    <div class="card-footer py-4">
                        {{ paginateLinks($logs) }}
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection

@push('breadcrumb-plugins')
    <x-search-form />
@endpush