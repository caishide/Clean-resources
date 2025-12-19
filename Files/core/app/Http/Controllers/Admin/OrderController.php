<?php

namespace App\Http\Controllers\Admin;

use App\Models\Order;
use App\Constants\Status;
use App\Models\Transaction;
use App\Services\OrderShipmentService;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class OrderController extends Controller
{
    public function index($userId = null)
    {
        $pageTitle = 'Orders';
        $orders    = Order::searchable(['trx', 'user:username', 'product:name']);
        if ($userId) {
            $orders = $orders->where('user_id', $userId);
        }
        $orders = $orders->with('product', 'user')->orderBy('id', 'desc')->paginate(getPaginate());

        $emptyMessage = 'Order not found';
        return view('admin.orders', compact('pageTitle', 'orders', 'emptyMessage'));
    }

    public function status(Request $request, $id, OrderShipmentService $shipmentService)
    {
        $request->validate([
            'status' => 'required|in:1,2'
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
