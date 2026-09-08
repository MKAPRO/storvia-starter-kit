<?php

namespace Database\Factories;

use App\Models\FileSpace;
use App\Models\Node;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Node>
 */
class NodeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'file_space_id' => FileSpace::factory(),
            'parent_id' => null,
            'owner_id' => User::factory(),
            'type' => Node::TYPE_FOLDER,
            'name' => fake()->unique()->words(2, true),
        ];
    }

    public function file(): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => Node::TYPE_FILE,
            'storage_disk' => 'local',
            'storage_key' => 'objects/'.fake()->uuid(),
            'mime_type' => 'application/octet-stream',
            'extension' => 'bin',
            'size' => fake()->numberBetween(1, 10_000_000),
            'checksum' => hash('sha256', fake()->uuid()),
        ]);
    }
}
