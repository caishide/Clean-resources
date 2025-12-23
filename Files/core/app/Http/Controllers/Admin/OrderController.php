<?php

namespace App\Http\Controllers\Admin;

use App\Models\Order;
use App\Constants\Status;
use App\Models\Transaction;
use App\Services\OrderShipmentService;
use App\Repositories\UserRepository;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;

class OrderController extends Controller
{
    /** @var UserRepository 用户仓储实例 */
    protected UserRepository $userRepository;

    /**
     * 构造函数
     *
     * @param UserRepository $userRepository
     */
    public function __construct(UserRepository $userRepository)
    {
        $this->userRepository = $userRepository;
    }

    /**
     * 订单列表（优化版）
     *
     * @param Request $request
     * @param int|null $userId
     * @return View
     */
    public function index(Request $request, $userId = null)
    {
        $pageTitle = 'Orders';

        // 构建过滤条件
        $filters = [
            'status' => $request->status,
            'user_id' => $userId,
            'search' => $request->search,
        ];

        // 使用Repository获取订单列表（优化：避免SELECT *，添加缓存）
        $orders = $this->userRepository->getOrderList($filters, getPaginate());

        $emptyMessage = 'Order not found';
        return view('admin.orders', compact('pageTitle', 'orders', 'emptyMessage'));
    }

    /**
     * 订单列表 - API接口（使用Keyset分页）
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function apiIndex(Request $request): JsonResponse
    {
        $filters = [
            'status' => $request->status,
            'user_id' => $request->user_id,
            'search' => $request->search,
        ];

        $lastId = $request->get('last_id');
        $perPage = $request->get('per_page', 20);

        // 使用Keyset分页（替代OFFSET分页）
        $orders = $this->userRepository->getOrderList($filters, $perPage, $lastId);

        return response()->json([
            'data' => $orders,
            'next_cursor' => $orders->last()?->id,
            'has_more' => $orders->count() === $perPage,
        ]);
    }

    public function status(Request $request, $id, OrderShipmentService $shipmentService)
    {
        $request->validate([
            'status' => 'required|in:1,2',
            'refund_reason' => 'nullable|string|max:255',
        ]);

        $order   = Order::with('product', 'user')->findOrFail($id);
        $product = $order->product;
        $user    = $order->user;

        if ($request->status == Status::ORDER_SHIPPED) {
            $result = $shipmentService->ship($order);
            if (($result['status'] ?? null) === 'error') {
                $notify[] = ['error', $result['message'] ?? 'Failed to ship order'];
                return back()->withNotify($notify);
            }
            $template = 'ORDER_SHIPPED';
        } else {
            if ($order->status == Status::ORDER_SHIPPED) {
                $result = $shipmentService->refund($order, (string) $request->input('refund_reason', 'refund'));
                if (($result['status'] ?? null) === 'error') {
                    $notify[] = ['error', $result['message'] ?? 'Failed to refund order'];
                    return back()->withNotify($notify);
                }
                $template = 'ORDER_CANCELED';
            } else {
                if ($order->status != Status::ORDER_PENDING) {
                    $notify[] = ['error', 'Only pending orders can be cancelled'];
                    return back()->withNotify($notify);
                }

                $order->status  = Status::ORDER_CANCELED;
                $user->balance += $order->total_price;
                $user->save();

                $transaction               = new Transaction();
                $transaction->user_id      = $order->user_id;
                $transaction->amount       = $order->total_price;
                $transaction->post_balance = $user->balance;
                $transaction->charge       = 0;
                $transaction->trx_type     = '+';
                $transaction->details      = $product->name . ' Order cancel';
                $transaction->trx          = $order->trx;
                $transaction->save();

                $product->quantity += $order->quantity;
                $product->save();

                $template = 'ORDER_CANCELED';
                $order->save();
            }
        }

        $order->refresh();

        notify($user, $template, [
            'product_name' => $product->name,
            'quantity'     => $order->quantity,
            'price'        => showAmount($product->price, currencyFormat: false),
            'total_price'  => showAmount($order->total_price, currencyFormat: false),
            'trx'          => $order->trx,
        ]);

        $notify[] = ['success', 'Product status updated successfully'];
        return back()->withNotify($notify);
    }
}
