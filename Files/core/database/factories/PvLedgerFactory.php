<?php

namespace Database\Factories;

use App\Models\PvLedger;
use Illuminate\Database\Eloquent\Factories\Factory;

class PvLedgerFactory extends Factory
{
    protected $model = PvLedger::class;

    public function definition(): array
    {
        return [
            'user_id' => \App\Models\User::factory(),
            'from_user_id' => \App\Models\User::factory(),
            'position' => $this->faker->randomElement([1, 2]),
            'level' => $this->faker->numberBetween(1, 10),
            'amount' => $this->faker->numberBetween(1000, 30000),
            'trx_type' => '+',
            'source_type' => 'order',
            'source_id' => 'ORDER-' . uniqid(),
        ];
    }

    public function positive(): static
    {
        return $this->state(fn (array $attributes) => [
            'trx_type' => '+',
        ]);
    }

    public function negative(): static
    {
        return $this->state(fn (array $attributes) => [
            'trx_type' => '-',
        ]);
    }

    public function fromOrder(string $orderTrx): static
    {
        return $this->state(fn (array $attributes) => [
            'source_type' => 'order',
            'source_id' => $orderTrx,
        ]);
    }

    public function leftPosition(): static
    {
        return $this->state(fn (array $attributes) => [
            'position' => 1,
        ]);
    }

    public function rightPosition(): static
    {
        return $this->state(fn (array $attributes) => [
            'position' => 2,
        ]);
    }
}
