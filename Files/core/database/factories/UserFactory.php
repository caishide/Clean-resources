<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use App\Constants\Status;

class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        return [
            'username' => 'user_' . Str::random(6),
            'name' => 'user_' . Str::random(6),
            'firstname' => 'Test',
            'lastname' => 'User',
            'email' => $this->faker->unique()->safeEmail(),
            'password' => bcrypt('password'),
            'plan_id' => 0,
            'pos_id' => 0,
            'position' => 0,
            'ref_by' => 0,
            'status' => Status::USER_ACTIVE,
            'ev' => Status::VERIFIED,
            'sv' => Status::VERIFIED,
            'balance' => 0,
        ];
    }
}
