<?php

namespace Database\Factories;

use App\Models\UserExtra;
use Illuminate\Database\Eloquent\Factories\Factory;

class UserExtraFactory extends Factory
{
    protected $model = UserExtra::class;

    public function definition(): array
    {
        return [
            'bv_left' => 0,
            'bv_right' => 0,
            'points' => 0,
        ];
    }
}
