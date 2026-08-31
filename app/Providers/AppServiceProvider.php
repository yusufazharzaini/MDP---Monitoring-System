<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\CorrectiveAction;
use App\Models\Delivery;
use App\Models\DeliveryProblem;
use App\Models\ProblemAttachment;
use App\Models\PurchaseOrder;
use App\Models\SupplierEvaluation;
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
    /**
     * Abilities the policy decides alone, even for a super administrator.
     *
     * These are the ones whose answer comes from the record's own state rather
     * than from what the user is permitted to do: a purchase order and a
     * delivery are cancelled rather than deleted, and a closed or cancelled
     * problem is shut to everybody. Letting the bypass overrule them would put
     * buttons on screens that the service layer then refuses.
     *
     * @var array<class-string, array<int, string>>
     */
    private const POLICY_ALONE = [
        PurchaseOrder::class => ['delete'],
        Delivery::class => ['delete'],
        DeliveryProblem::class => ['update', 'close', 'cancel', 'delete'],
        CorrectiveAction::class => ['create', 'complete', 'delete'],
        ProblemAttachment::class => ['create', 'delete'],
        SupplierEvaluation::class => ['regenerate', 'approve', 'reopen', 'delete'],
    ];

    /**
     * Whether this ability is one the bypass must defer on.
     *
     * The subject arrives as a model for an ability about one record and as a
     * class-string for an ability about the type (`create`), so both forms are
     * resolved to the same key.
     */
    private static function policyAlone(string $ability, mixed $subject): bool
    {
        $key = match (true) {
            is_object($subject) => $subject::class,
            is_string($subject) => $subject,
            default => null,
        };

        return $key !== null && in_array($ability, self::POLICY_ALONE[$key] ?? [], true);
    }

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
            if (self::policyAlone($ability, $arguments[0] ?? null)) {
                return null;
            }

            return $user->hasRole(RolesAndPermissionsSeeder::SUPER_ADMIN) ? true : null;
        });
    }
}
