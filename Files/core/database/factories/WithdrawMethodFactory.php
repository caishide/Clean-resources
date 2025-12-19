<?php

namespace Database\Factories;

use App\Models\WithdrawMethod;
use Illuminate\Database\Eloquent\Factories\Factory;

class WithdrawMethodFactory extends Factory
{
    protected $model = WithdrawMethod::class;

    public function definition(): array
    {
        return [
            'name' => 'Test Method',
            'currency' => 'USD',
            'min_limit' => 1,
            'max_limit' => 1000,
            'fixed_charge' => 0,
            'percent_charge' => 0,
            'rate' => 1,
            'status' => 1,
        ];
    }
}
