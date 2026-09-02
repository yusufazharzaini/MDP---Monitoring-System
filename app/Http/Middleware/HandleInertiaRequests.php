<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\Setting\KpiSettingService;
use Illuminate\Http\Request;
use Inertia\Middleware;

/**
 * Shared Inertia props.
 *
 * The KPI thresholds are shared globally so charts and badges can render target
 * lines and grade bands without any number being hard-coded in Vue.
 */
class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        /** @var User|null $user */
        $user = $request->user();

        return [
            ...parent::share($request),

            'auth' => [
                'user' => $user === null ? null : [
                    'id' => $user->ulid,
                    'name' => $user->name,
                    'email' => $user->email,
                    'position' => $user->position,
                    'roles' => $user->getRoleNames()->all(),
                    'permissions' => $user->getAllPermissions()->pluck('name')->all(),
                ],
            ],

            // Resolved lazily: unauthenticated pages never touch the database for this.
            'kpi' => fn (): array => $user === null ? [] : app(KpiSettingService::class)->forFrontend(),

            /*
             * The bell's count, lazily too. One indexed count against
             * notifications_unread_index, on every page - which is why it is a
             * count and not the notifications themselves.
             */
            'unreadNotifications' => fn (): int => $user?->unreadNotifications()->count() ?? 0,

            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'warning' => fn () => $request->session()->get('warning'),
            ],

            'app' => [
                'name' => config('app.name'),
            ],

            /*
             * Language. `translations` carries only the active locale's strings
             * - one language over the wire, not four - and is resolved lazily so
             * an Inertia partial reload does not re-send the whole dictionary.
             *
             * Enum labels are not here: the server renders those through
             * HasEnumMetadata::label() before they ever reach a prop, so a badge
             * arrives already in the reader's language.
             */
            'locale' => [
                'current' => app()->getLocale(),
                'supported' => collect((array) config('locales.supported'))
                    ->map(static fn (array $meta, string $code): array => [
                        'code' => $code,
                        'native' => $meta['native'],
                    ])
                    ->values()
                    ->all(),
            ],

            'translations' => fn (): array => (array) trans('ui'),
        ];
    }
}
