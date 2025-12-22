<?php

namespace App\Services;

use App\Models\User;
use App\Models\Order;
use App\Models\Product;
use App\Models\Transaction;
use Exception;

class OrderService extends BaseService
{
    protected UserService $userService;
    protected TransactionService $transactionService;

    public function __construct(UserService $userService, TransactionService $transactionService)
    {
        $this->userService = $userService;
        $this->transactionService = $transactionService;
    }

    /**
     * Create a new order (purchase)
     */
    public function createOrder(User $user, Product $product, int $quantity): array
    {
        $this->logInfo('Creating order', [
            'user_id' => $user->id,
            'product_id' => $product->id,
            'quantity' => $quantity,
        ]);

        return $this->transaction(function () use ($user, $product, $quantity) {
            // Validate stock
            if ($quantity > $product->quantity) {
                throw new Exception('Requested quantity is not available in stock');
            }

            $totalPrice = $product->price * $quantity;

            // Validate balance
            if ($user->balance < $totalPrice) {
                throw new Exception('Insufficient balance');
            }

            // Deduct balance
            $user->balance -= $totalPrice;
            $user->save();

            // Update stock
            $product->quantity -= $quantity;
            $product->save();

            // Create transaction
            $trx = getTrx();
            $transaction = $this->transactionService->createTransaction([
                'user_id' => $user->id,
                'amount' => $totalPrice,
                'post_balance' => $user->balance,
                'charge' => 0,
                'trx_type' => '-',
                'details' => "{$product->name} item purchase",
                'trx' => $trx,
            ]);

            // Create order
            $order = new Order();
            $order->user_id = $user->id;
            $order->product_id = $product->id;
            $order->quantity = $quantity;
            $order->price = $product->price;
            $order->total_price = $totalPrice;
            $order->trx = $trx;
            $order->status = 0;
            $order->save();

            // Clear caches
            $this->userService->clearDashboardCache($user->id);

            $this->logInfo('Order created successfully', [
                'order_id' => $order->id,
                'trx' => $trx,
            ]);

            return [
                'success' => true,
                'order' => $order,
                'transaction' => $transaction,
                'trx' => $trx,
            ];
        });
    }

    /**
     * Get user orders with pagination
     */
    public function getUserOrders(int $userId, int $perPage = 15): \Illuminate\Pagination\LengthAwarePaginator
    {
        return Order::where('user_id', $userId)
            ->with('product')
            ->orderBy('id', 'desc')
            ->paginate($perPage);
    }

    /**
     * Find order by ID
     */
    public function findById(int $orderId): ?Order
    {
        return Order::with(['user', 'product'])->find($orderId);
    }
}
