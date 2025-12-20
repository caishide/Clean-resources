<?php

namespace Database\Factories;

use App\Models\Deposit;
use App\Models\User;
use App\Constants\Status;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class DepositFactory extends Factory
{
    protected $model = Deposit::class;

    public function definition(): array
    {
        $user = User::factory()->create();
        $methodCode = $this->faker->numberBetween(100, 999);
        $currencies = ['USD', 'EUR', 'GBP', 'BTC', 'ETH'];

        return [
            'user_id' => $user->id,
            'amount' => $this->faker->randomFloat(2, 10, 1000),
            'method_code' => $methodCode,
            'method_currency' => $this->faker->randomElement($currencies),
            'charge' => $this->faker->randomFloat(2, 0, 10),
            'rate' => $this->faker->randomFloat(4, 0.5, 2),
            'final_amount' => function (array $attributes) {
                return $attributes['amount'] + $attributes['charge'];
            },
            'trx' => Str::random(16),
            'status' => $this->faker->randomElement([
                Status::PAYMENT_INITIATE,
                Status::PAYMENT_SUCCESS,
                Status::PAYMENT_PENDING,
                Status::PAYMENT_REJECT
            ]),
            'detail' => $this->faker->sentence(),
        ];
    }

    public function successful(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Status::PAYMENT_SUCCESS,
        ]);
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Status::PAYMENT_PENDING,
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Status::PAYMENT_REJECT,
        ]);
    }
}
