<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\Delivery\DeliveryStatusCalculator;
use App\Services\Setting\SystemSettingService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
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
    }
}
