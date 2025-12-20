<?php

namespace Database\Factories;

use App\Models\Category;
use App\Constants\Status;
use Illuminate\Database\Eloquent\Factories\Factory;

class CategoryFactory extends Factory
{
    protected $model = Category::class;

    public function definition(): array
    {
        $name = $this->faker->unique()->word();
        return [
            'name' => $name,
            'slug' => \Illuminate\Support\Str::slug($name),
            'status' => $this->faker->boolean(80) ? Status::ENABLE : Status::DISABLE,
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Status::ENABLE,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Status::DISABLE,
        ]);
    }

    public function featured(): static
    {
        return $this->state(fn (array $attributes) => [
            'featured' => Status::ENABLE,
        ]);
    }
}
