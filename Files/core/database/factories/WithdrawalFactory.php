<?php

namespace Database\Factories;

use App\Models\Withdrawal;
use App\Models\User;
use App\Models\WithdrawMethod;
use App\Constants\Status;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class WithdrawalFactory extends Factory
{
    protected $model = Withdrawal::class;

    public function definition(): array
    {
        $user = User::factory()->create();
        $method = WithdrawMethod::factory()->create();

        return [
            'user_id' => $user->id,
            'method_id' => $method->id,
            'amount' => $this->faker->randomFloat(2, 10, 500),
            'charge' => $this->faker->randomFloat(2, 0, 5),
            'final_amount' => function (array $attributes) {
                return $attributes['amount'] - $attributes['charge'];
            },
            'rate' => $method->rate,
            'trx' => Str::random(16),
            'status' => $this->faker->randomElement([
                Status::PAYMENT_PENDING,
                Status::PAYMENT_SUCCESS,
                Status::PAYMENT_REJECT
            ]),
            'withdraw_information' => [
                'account' => $this->faker->bankAccountNumber(),
                'account_name' => $this->faker->name(),
            ],
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
