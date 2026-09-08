<?php

namespace Tests\Feature\Starter;

use App\Models\Department;
use App\Models\FileSpace;
use App\Models\InstallationSetting;
use App\Models\Node;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\StarterDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use LogicException;
use Tests\TestCase;

final class StarterDemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_database_seed_is_safe_and_creates_no_operational_user(): void
    {
        config()->set('storvia.starter_demo.enabled', false);

        $this->seed(DatabaseSeeder::class);

        $this->assertSame(0, User::query()->count());
        $this->assertSame(4, Role::query()->where('is_system', true)->count());
    }

    public function test_opt_in_demo_seed_creates_an_idempotent_non_secret_starter_dataset(): void
    {
        config()->set('storvia.starter_demo.enabled', true);
        config()->set('storvia.starter_demo.admin_password', 'LocalDemo!Password2026');

        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);

        $admin = User::query()->where('email', StarterDemoSeeder::ADMIN_EMAIL)->sole();

        $this->assertSame(StarterDemoSeeder::ADMIN_USERNAME, $admin->username);
        $this->assertTrue(Hash::check('LocalDemo!Password2026', $admin->password));
        $this->assertTrue($admin->is_active);
        $this->assertTrue($admin->personal_space_enabled);
        $this->assertSame([Role::SUPER_ADMIN], $admin->roleNames()->all());

        $this->assertSame(2, Department::query()->count());
        $this->assertSame(1, $admin->departments()->count());
        $this->assertSame(3, FileSpace::query()->count());
        $this->assertSame(4, Node::query()->where('type', Node::TYPE_FOLDER)->count());

        $settings = InstallationSetting::query()
            ->where('key', InstallationSetting::PRIMARY_KEY)
            ->sole();

        $this->assertSame('STORVIA Demo', $settings->company_name);
        $this->assertNotNull($settings->completed_at);
        $this->assertSame($admin->getKey(), $settings->completed_by);
    }

    public function test_demo_seed_requires_an_explicit_strong_password(): void
    {
        config()->set('storvia.starter_demo.admin_password', '');

        $this->expectException(LogicException::class);

        $this->seed(StarterDemoSeeder::class);
    }

    public function test_demo_seed_refuses_production_environment(): void
    {
        $this->app->detectEnvironment(fn (): string => 'production');
        config()->set('storvia.starter_demo.admin_password', 'LocalDemo!Password2026');

        $this->expectException(LogicException::class);

        $this->seed(StarterDemoSeeder::class);
    }
}
