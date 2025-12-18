@extends($activeTemplate . 'layouts.master')
@section('content')
    <div class="card custom--card p-0 p-0">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="custom--table table">
                    <thead>
                        <tr>
                            <th>@lang('Product')</th>
                            <th>@lang('Quantity')</th>
                            <th>@lang('Price')</th>
                            <th>@lang('Total Price')</th>
                            <th>@lang('Status')</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($orders as $order)
                            <tr>
                                <td>
                                    @if (@$order->product)
                                        <a href="{{ route('product.details', ['id' => @$order->product->id, 'slug' => slug($order->product->name)]) }}">
                                            {{ __(strLimit($order->product->name, '30')) }}</a>
                                    @endif
                                </td>
                                <td>{{ $order->quantity }}</td>
                                <td>{{ showAmount($order->price) }}</td>
                                <td>{{ showAmount($order->total_price) }}</td>
                                <td>
                                    @php echo $order->statusOrderBadge @endphp
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td class="text-center" colspan="100%">@lang('No order found')</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    @if ($orders->hasPages())
        <div class="mt-4">
            {{ paginateLinks($orders) }}
        </div>
    @endif
@endsection
