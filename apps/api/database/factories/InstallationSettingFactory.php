<?php

namespace Database\Factories;

use App\Models\InstallationSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InstallationSetting>
 */
class InstallationSettingFactory extends Factory
{
    protected $model = InstallationSetting::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'key' => InstallationSetting::PRIMARY_KEY,
            'company_name' => fake()->company(),
            'default_locale' => 'en',
            'storage_disk' => 'local',
            'completed_at' => null,
            'completed_by' => null,
        ];
    }
}
