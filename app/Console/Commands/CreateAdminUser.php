<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\RecordStatus;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Spatie\Permission\Models\Role;

/**
 * Creates the first administrator of a real deployment.
 *
 * ProductionSeeder deliberately seeds no accounts, because the demo set shares
 * one password across seven users. This is how a deployment gets its first way
 * in: a password typed by the person installing it, never one that exists in
 * the repository.
 *
 * The password is read from a hidden prompt, or from MDP_ADMIN_PASSWORD for an
 * unattended install. It is never accepted as a command-line argument, where it
 * would land in the shell history and the process list.
 */
final class CreateAdminUser extends Command
{
    protected $signature = 'mdp:create-admin
                            {--name= : Display name}
                            {--email= : Sign-in address}
                            {--role=SUPER_ADMIN : Role to grant}';

    protected $description = 'Create an administrator account for a fresh deployment';

    private const MIN_PASSWORD_LENGTH = 12;

    public function handle(): int
    {
        $name = (string) ($this->option('name') ?: $this->ask('Full name'));
        $email = mb_strtolower(trim((string) ($this->option('email') ?: $this->ask('Email address'))));
        $role = (string) $this->option('role');

        $password = (string) env('MDP_ADMIN_PASSWORD', '');
        $fromEnv = $password !== '';

        if (! $fromEnv) {
            $password = (string) $this->secret('Password');

            if ($password !== (string) $this->secret('Confirm password')) {
                $this->error('The passwords do not match.');

                return self::FAILURE;
            }
        }

        $validator = Validator::make(
            ['name' => $name, 'email' => $email, 'password' => $password, 'role' => $role],
            [
                'name' => ['required', 'string', 'max:255'],
                // withTrashed: a retired account still owns the address.
                'email' => ['required', 'email', 'max:255'],
                'password' => ['required', 'string', 'min:'.self::MIN_PASSWORD_LENGTH],
                'role' => ['required', 'string'],
            ],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        if (User::query()->withTrashed()->where('email', $email)->exists()) {
            $this->error("An account already uses {$email}.");

            return self::FAILURE;
        }

        if (! Role::query()->where('name', $role)->exists()) {
            $this->error("Role {$role} does not exist. Run `php artisan db:seed --class=ProductionSeeder` first.");

            return self::FAILURE;
        }

        $user = new User;
        $user->fill([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'status' => RecordStatus::ACTIVE,
        ]);
        // Not fillable by design, so it is set rather than mass assigned: an
        // administrator created here is verified by the act of creating it.
        $user->email_verified_at = now();
        $user->save();

        $user->syncRoles([$role]);

        $this->info("Created {$email} with role {$role}.");

        if ($fromEnv) {
            $this->warn('Password taken from MDP_ADMIN_PASSWORD - remove it from the environment now.');
        }

        return self::SUCCESS;
    }
}
