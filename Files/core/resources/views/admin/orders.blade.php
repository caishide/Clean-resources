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
                                    <th>@lang('User')</th>
                                    <th>@lang('Trx')</th>
                                    <th>@lang('Price')</th>
                                    <th>@lang('Total Price')</th>
                                    <th>@lang('Quantity')</th>
                                    <th>@lang('Status')</th>
                                    <th>@lang('Action')</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($orders as $order)
                                    <tr>

                                        <td>
                                            <span class="fw-bold">{{ $order->user->fullname }}</span>
                                            <br>
                                            <span class="small">
                                                <a href="{{ route('admin.users.detail', $order->user_id) }}"><span>@</span>{{ $order->user->username }}</a>
                                            </span>
                                        </td>
                                        <td>{{ '#' . $order->trx }}</td>

                                        <td>{{ showAmount($order->price) }} </td>
                                        <td>{{ showAmount($order->total_price) }}</td>
                                        <td>{{ $order->quantity }}</td>
                                        <td>
                                            @php echo $order->statusOrderBadge @endphp
                                        </td>
                                        <td>
                                            <div class="button--group">
                                                <button class="btn btn-outline--primary btn-sm orderBtn"
                                                    data-action="{{ route('admin.order.status', $order->id) }}"
                                                    @if ($order->status != 0) disabled @endif>@lang('Order Status')</button>
                                                <button class="btn btn-sm btn-outline--success orderDetailsBtn" data-order='@json($order)'
                                                    data-date="{{ showDateTime($order->created_at) }}" data-status="{{ $order->statusOrderBadge }}"><i
                                                        class="las la-desktop"></i>@lang('Details')</button>
                                            </div>
                                        </td>
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
                @if ($orders->hasPages())
                    <div class="card-footer py-4">
                        {{ paginateLinks($orders) }}
                    </div>
                @endif
            </div>
        </div>
    </div>

    <div class="modal fade" id="orderStatusModal">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">@lang('Update Order Status')</h5>
                    <button class="close" data-bs-dismiss="modal" type="button" aria-label="Close">
                        <i class="las la-times"></i>
                    </button>
                </div>
                <form method="post">
                    @csrf
                    <div class="modal-body">
                        <div class="form-group">
                            <label>@lang('Order Status')</label>
                            <select class="form-control select2" name="status" data-minimum-results-for-search="-1">
                                <option value="1">@lang('Shipped')</option>
                                <option value="2">@lang('Cancel')</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn--dark" data-bs-dismiss="modal" type="button">@lang('Cancel')</button>
                        <button class="btn btn--primary" type="submit">@lang('Submit')</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="orderDetailsModal">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">@lang('Order Details')</h5>
                    <button class="close" data-bs-dismiss="modal" type="button" aria-label="Close">
                        <i class="las la-times"></i>
                    </button>
                </div>

                <div class="modal-body">
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item d-flex justify-content-between">
                            <b>@lang('Product')</b> <a class="product" href=""></a>
                        </li>
                        <li class="list-group-item d-flex justify-content-between">
                            <b>@lang('Quantity') </b> <span class="quantity"></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between">
                            <b>@lang('Price') </b> <span class="price"></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between">
                            <b>@lang('Total Price') </b> <span class="total-price"></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between">
                            <b>@lang('Username')</b> <span class="username"></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between">
                            <b>@lang('Transition No')</b> <span class="trx"></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between">
                            <b>@lang('Order Date') </b> <span class="date"></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between">
                            <b>@lang('Status') </b> <span class="status"></span>
                        </li>

                    </ul>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('breadcrumb-plugins')
    <x-search-form />
@endpush

@push('script')
    <script>
        (function($) {
            "use strict";

            $('.orderBtn').on('click', function() {
                var modal = $('#orderStatusModal');
                modal.find('form').attr('action', $(this).data('action'));
                modal.modal('show');
            });

            $('.orderDetailsBtn').on('click', function() {
                var modal = $('#orderDetailsModal');
                var order = $(this).data('order');
                var date = $(this).data('date');
                var status = $(this).data('status');
                var curSym = `{{ gs('cur_sym') }}`;
                var price = curSym + parseFloat(order.price).toFixed(2);
                var totalPrice = curSym + parseFloat(order.total_price).toFixed(2);
                var url = (`{{ route('admin.product.edit', ':id') }}`).replace(":id", order.product_id);
                modal.find('.username').text(order.user.username);
                modal.find('.trx').text(order.trx);
                modal.find('.product').text(order.product.name);
                modal.find('.product').attr('href', url);
                modal.find('.quantity').text(order.quantity);
                modal.find('.quantity').text(order.quantity);
                modal.find('.price').text(price);
                modal.find('.total-price').text(totalPrice);
                modal.find('.status').html(status);
                modal.find('.date').html(date);
                modal.modal('show');
            });
        })(jQuery);
    </script>
@endpush
