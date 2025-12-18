@extends('admin.layouts.app')

@section('panel')
    <div class="row">
        <div class="col-lg-12">
            <div class="card">
                <div class="card-body p-0">
                    <div class="table-responsive--md table-responsive">
                        <table class="table--light style--two table">
                            <thead>
                                <tr>
                                    <th>@lang('Name')</th>
                                    <th>@lang('Price')</th>
                                    <th>@lang('Quantity')</th>
                                    <th>@lang('Feature')</th>
                                    <th>@lang('Status')</th>
                                    <th>@lang('Action')</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($products as $product)
                                    <tr>
                                        <td>
                                            <div class="user">
                                                <div class="thumb justify-content-end justify-content-md-start">
                                                    <img src="{{ getImage(getFilePath('products') . '/' . $product->thumbnail, getFileSize('products')) }}"
                                                        alt="Image">
                                                    <span>{{ __(strLimit($product->name, '20')) }}</span>
                                                </div>
                                            </div>
                                        </td>

                                        <td>{{ __(showAmount($product->price)) }}</td>
                                        <td>{{ __($product->quantity) }}</td>
                                        <td> @php echo $product->statusFeature @endphp </td>
                                        <td> @php echo $product->statusBadge @endphp </td>
                                        <td>
                                            <div class="button--group">
                                                <a class="btn btn-outline--primary btn-sm" data-toggle="tooltip" data-original-title="Edit"
                                                    href="{{ route('admin.product.edit', $product->id) }}">
                                                    <i class="la la-pencil"></i>@lang('Edit')
                                                </a>
                                                @if ($product->is_featured == Status::ENABLE)
                                                    <button class="btn btn-outline--danger btn-sm confirmationBtn" data-question="@lang('Are you sure to feature this product?')"
                                                        data-action="{{ route('admin.product.feature', $product->id) }}">
                                                        <i class="las la-eye-slash"></i>@lang('Unfeatured')
                                                    </button>
                                                @else
                                                    <button class="btn btn-outline--warning confirmationBtn btn-sm" data-question="@lang('Are you sure to unfeatured this product?')"
                                                        data-action="{{ route('admin.product.feature', $product->id) }}">
                                                        <i class="las la-eye"></i>@lang('Featured')
                                                    </button>
                                                @endif

                                                @if ($product->status == Status::ENABLE)
                                                    <button class="btn btn-outline--danger btn-sm confirmationBtn" data-question="@lang('Are you sure to disable this product?')"
                                                        data-action="{{ route('admin.product.status', $product->id) }}">
                                                        <i class="las la-eye-slash"></i>@lang('Disable')
                                                    </button>
                                                @else
                                                    <button class="btn btn-outline--success confirmationBtn btn-sm" data-question="@lang('Are you sure to enable this product?')"
                                                        data-action="{{ route('admin.product.status', $product->id) }}">
                                                        <i class="las la-eye"></i>@lang('Enable')
                                                    </button>
                                                @endif
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
                @if ($products->hasPages())
                    <div class="card-footer py-4">
                        {{ paginateLinks($products) }}
                    </div>
                @endif
            </div>
        </div>
    </div>

    <x-confirmation-modal />
@endsection

@push('breadcrumb-plugins')
    <x-search-form />
    <a class="btn btn-outline--primary h-45" href="{{ route('admin.product.create') }}"><i class="las la-plus"></i>@lang('Add New')</a>
@endpush
