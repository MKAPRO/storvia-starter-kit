<?php

namespace Database\Factories;

use App\Models\Department;
use App\Models\FileSpace;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FileSpace>
 */
class FileSpaceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => FileSpace::TYPE_PERSONAL,
            'owner_user_id' => User::factory(),
            'department_id' => null,
        ];
    }

    public function department(): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => FileSpace::TYPE_DEPARTMENT,
            'owner_user_id' => null,
            'department_id' => Department::factory(),
        ]);
    }
}
