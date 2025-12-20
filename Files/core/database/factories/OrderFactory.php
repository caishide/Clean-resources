<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\User;
use App\Models\Product;
use App\Constants\Status;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();
        $quantity = $this->faker->numberBetween(1, 10);
        $price = $this->faker->randomFloat(2, 10, 100);

        return [
            'user_id' => $user->id,
            'product_id' => $product->id,
            'quantity' => $quantity,
            'price' => $price,
            'total_price' => $quantity * $price,
            'amount' => $this->faker->randomFloat(2, 10, 1000),
            'commission' => $this->faker->randomFloat(2, 0, 50),
            'trx' => Str::random(16),
            'status' => $this->faker->randomElement([
                Status::ORDER_PENDING,
                Status::ORDER_SHIPPED,
                Status::ORDER_CANCELED
            ]),
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Status::ORDER_PENDING,
        ]);
    }

    public function shipped(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Status::ORDER_SHIPPED,
        ]);
    }

    public function canceled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Status::ORDER_CANCELED,
        ]);
    }

    public function withUser(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $user->id,
        ]);
    }

    public function withProduct(Product $product): static
    {
        return $this->state(fn (array $attributes) => [
            'product_id' => $product->id,
        ]);
    }
}
