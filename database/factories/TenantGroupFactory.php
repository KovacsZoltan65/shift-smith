<?php

namespace Database\Factories;

use App\Models\TenantGroup;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TenantGroup>
 */
class TenantGroupFactory extends Factory
{
    protected $model = TenantGroup::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->company();
        $code = Str::upper(Str::slug($name, '_')).'_'.fake()->unique()->numberBetween(10, 999);

        return [
            'name' => $name,
            'code' => Str::limit($code, 50, ''),
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(6)),
            'database_name' => null,
            'status' => fake()->randomElement(['draft', 'active', 'archived']),
            'notes' => fake()->optional()->sentence(),
            'active' => true,
        ];
    }
}
