<?php

namespace Database\Factories;

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class TransactionFactory extends Factory
{
    protected $model = Transaction::class;

    public function definition(): array
    {
        $user = User::factory()->create();
        $trxTypes = ['+', '-'];
        $amount = $this->faker->randomFloat(2, 0.01, 100);
        $trxType = $this->faker->randomElement($trxTypes);

        return [
            'user_id' => $user->id,
            'amount' => $amount,
            'post_balance' => $this->faker->randomFloat(2, 0, 1000),
            'charge' => $this->faker->randomFloat(2, 0, 5),
            'trx_type' => $trxType,
            'details' => $this->faker->sentence(),
            'trx' => Str::random(16),
            'remark' => $this->faker->randomElement([
                'Balance deposit',
                'Balance withdrawal',
                'Referral commission',
                'Binary commission',
                'Purchase',
                'Adjustment'
            ]),
            'source_type' => $this->faker->randomElement([
                'deposit',
                'withdrawal',
                'order',
                'commission',
                'adjustment',
                'refund'
            ]),
            'source_id' => Str::random(10),
        ];
    }

    public function credit(): static
    {
        return $this->state(fn (array $attributes) => [
            'trx_type' => '+',
            'remark' => 'Balance deposit',
        ]);
    }

    public function debit(): static
    {
        return $this->state(fn (array $attributes) => [
            'trx_type' => '-',
            'remark' => 'Balance withdrawal',
        ]);
    }

    public function commission(): static
    {
        return $this->state(fn (array $attributes) => [
            'source_type' => 'commission',
            'remark' => 'Referral commission',
        ]);
    }
}
