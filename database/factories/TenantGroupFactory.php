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

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(6)),
            'database_name' => null,
            'active' => true,
        ];
    }
}
