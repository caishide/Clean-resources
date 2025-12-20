<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\Category;
use App\Constants\Status;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        $name = $this->faker->words(3, true);
        return [
            'category_id' => Category::factory(),
            'name' => $name,
            'slug' => \Illuminate\Support\Str::slug($name),
            'price' => $this->faker->randomFloat(2, 10, 1000),
            'quantity' => $this->faker->numberBetween(1, 100),
            'description' => $this->faker->sentence(),
            'meta_title' => $this->faker->sentence(),
            'meta_description' => $this->faker->paragraph(),
            'meta_keyword' => [$this->faker->word, $this->faker->word, $this->faker->word],
            'thumbnail' => $this->faker->imageUrl(),
            'specifications' => [
                'weight' => $this->faker->randomFloat(2, 0.1, 10) . ' kg',
                'dimensions' => $this->faker->randomElement(['Small', 'Medium', 'Large']),
                'color' => $this->faker->colorName(),
            ],
            'bv' => $this->faker->randomFloat(2, 0, 100),
            'is_featured' => $this->faker->boolean(20),
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
            'is_featured' => Status::ENABLE,
        ]);
    }

    public function withCategory(Category $category): static
    {
        return $this->state(fn (array $attributes) => [
            'category_id' => $category->id,
        ]);
    }
}
