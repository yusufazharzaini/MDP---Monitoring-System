<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Delivery;
use App\Models\PurchaseOrder;
use App\Models\User;
use App\Services\Delivery\DeliveryStatusCalculator;
use App\Services\Setting\SystemSettingService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SystemSettingService::class);

        // The calculator is pure but its over-delivery tolerance is configurable,
        // so the container resolves it from system settings once per request.
        $this->app->singleton(DeliveryStatusCalculator::class, static function ($app): DeliveryStatusCalculator {
            $tolerance = $app->make(SystemSettingService::class)
                ->float(SystemSettingService::DELIVERY_OVER_TOLERANCE_PERCENT, 0.0);

            return new DeliveryStatusCalculator($tolerance);
        });
    }

    public function boot(): void
    {
        // Fail loudly on accidental mass assignment and on lazy loading that
        // would otherwise become an N+1 in production (requirement 33).
        Model::shouldBeStrict(! $this->app->isProduction());

        DB::prohibitDestructiveCommands($this->app->isProduction());

        Password::defaults(static fn (): Password => Password::min(8)->letters()->numbers());

        /*
         * The super administrator passes every gate.
         *
         * The seeder also grants the role each permission explicitly, so this is
         * belt and braces - but it is the half that survives a new permission
         * being added without a re-seed, which is exactly when an admin would
         * otherwise find themselves locked out of a screen they own.
         */
        Gate::before(static function (User $user, string $ability, array $arguments = []): ?bool {
            /*
             * Deleting a purchase order or a delivery is not a permission
             * anyone can be granted - those records are cancelled, never
             * removed - so the bypass defers to the policy that says no.
             */
            $subject = $arguments[0] ?? null;

            if ($ability === 'delete' && ($subject instanceof PurchaseOrder || $subject instanceof Delivery)) {
                return null;
            }

            return $user->hasRole(RolesAndPermissionsSeeder::SUPER_ADMIN) ? true : null;
        });
    }
}
