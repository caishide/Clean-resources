<?php

namespace Database\Factories;

use App\Models\BvLog;
use App\Models\User;
use App\Constants\Status;
use Illuminate\Database\Eloquent\Factories\Factory;

class BvLogFactory extends Factory
{
    protected $model = BvLog::class;

    public function definition(): array
    {
        $user = User::factory()->create();

        return [
            'user_id' => $user->id,
            'position' => $this->faker->randomElement([
                Status::LEFT,
                Status::RIGHT
            ]),
            'trx_type' => $this->faker->randomElement([
                BvLog::TRX_TYPE_PLUS,
                BvLog::TRX_TYPE_MINUS
            ]),
            'amount' => $this->faker->randomFloat(2, 0.01, 100),
        ];
    }

    public function left(): static
    {
        return $this->state(fn (array $attributes) => [
            'position' => Status::LEFT,
        ]);
    }

    public function right(): static
    {
        return $this->state(fn (array $attributes) => [
            'position' => Status::RIGHT,
        ]);
    }

    public function plus(): static
    {
        return $this->state(fn (array $attributes) => [
            'trx_type' => BvLog::TRX_TYPE_PLUS,
        ]);
    }

    public function minus(): static
    {
        return $this->state(fn (array $attributes) => [
            'trx_type' => BvLog::TRX_TYPE_MINUS,
        ]);
    }

    public function withUser(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $user->id,
        ]);
    }
}
