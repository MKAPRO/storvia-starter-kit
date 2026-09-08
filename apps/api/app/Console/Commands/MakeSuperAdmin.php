<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\User;
use App\Services\AccessControl\AccessControlProvisioner;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class MakeSuperAdmin extends Command
{
    /**
     * Password is intentionally never accepted as a command-line option so it
     * cannot leak into shell history or process listings.
     *
     * @var string
     */
    protected $signature = 'storvia:make-super-admin
        {--email= : Existing or new user email}
        {--username= : Username to use when creating a new user}
        {--name= : Name to use when creating a new user}';

    protected $description = 'Securely create or promote a STORVIA Super Admin';

    public function handle(AccessControlProvisioner $provisioner): int
    {
        $provisioner->syncCatalog();

        $email = Str::lower(trim((string) ($this->option('email') ?: $this->ask('Email'))));

        $emailValidator = Validator::make(
            ['email' => $email],
            ['email' => ['required', 'email:rfc', 'max:255']],
        );

        if ($emailValidator->fails()) {
            $this->components->error($emailValidator->errors()->first('email'));

            return self::FAILURE;
        }

        $user = User::query()->where('email', $email)->first();

        if (! $user instanceof User) {
            $name = trim((string) ($this->option('name') ?: $this->ask('Name')));
            $username = $this->resolveUsername($email, (string) $this->option('username'));
            $password = (string) $this->secret('Password');
            $passwordConfirmation = (string) $this->secret('Confirm password');

            $validator = Validator::make([
                'name' => $name,
                'username' => $username,
                'email' => $email,
                'password' => $password,
                'password_confirmation' => $passwordConfirmation,
            ], [
                'name' => ['required', 'string', 'min:2', 'max:120'],
                'username' => [
                    'required',
                    'string',
                    'min:3',
                    'max:64',
                    'regex:/^[a-z0-9][a-z0-9._-]*$/',
                    'unique:users,username',
                ],
                'email' => ['required', 'email:rfc', 'max:255', 'unique:users,email'],
                'password' => [
                    'required',
                    'confirmed',
                    Password::min(12)->mixedCase()->numbers()->symbols(),
                ],
            ]);

            if ($validator->fails()) {
                foreach ($validator->errors()->all() as $message) {
                    $this->components->error($message);
                }

                return self::FAILURE;
            }

            $user = User::query()->create([
                'name' => $name,
                'username' => $username,
                'email' => $email,
                'password' => $password,
            ]);
        } elseif (blank($user->username)) {
            $user->forceFill([
                'username' => $this->resolveUsername($email, (string) $this->option('username')),
            ])->save();
        }

        $superAdminRole = Role::query()
            ->where('name', Role::SUPER_ADMIN)
            ->firstOrFail();

        $user->roles()->sync([$superAdminRole->getKey()]);

        if (! $user->is_active) {
            $user->forceFill(['is_active' => true])->save();
        }

        $this->components->info("Super Admin ready: {$user->email} ({$user->username})");

        return self::SUCCESS;
    }

    private function resolveUsername(string $email, string $requested): string
    {
        $requested = Str::lower(trim($requested));

        if ($requested !== '') {
            return $requested;
        }

        $localPart = Str::lower(Str::before($email, '@'));
        $base = preg_replace('/[^a-z0-9._-]+/', '-', $localPart) ?: 'admin';
        $base = trim($base, '.-_');
        $base = Str::limit($base !== '' ? $base : 'admin', 48, '');
        if (strlen($base) < 3) {
            $base = 'user-'.$base;
        }

        $candidate = $base;
        $suffix = 1;

        while (User::query()->where('username', $candidate)->exists()) {
            $candidate = Str::limit($base, 54, '').'-'.$suffix;
            $suffix++;
        }

        return $candidate;
    }
}
