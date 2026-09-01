<?php

declare(strict_types=1);

namespace App\Services\Dashboard;

use App\DataTransferObjects\DashboardFilter;
use Illuminate\Support\Facades\Cache;

/**
 * Cache keys for the dashboard aggregates, and the one way to invalidate them.
 *
 * Every panel is cached per filter, so there is no single key to forget when a
 * receipt is booked - and the `database` and `file` stores this deployment can
 * use do not support tags. A version stamp folded into every key solves both:
 * bumping it once retires every cached payload at a stroke, on any store, in
 * constant time.
 *
 * Without this the dashboard was stale for up to the TTL after every write,
 * which for an operations screen means a clerk books goods and then watches
 * five minutes of numbers that do not include them.
 */
class DashboardCache
{
    private const VERSION_KEY = 'dashboard.version';

    /**
     * Cache an assembled payload, when a TTL is configured.
     *
     * @param  callable():array<string, mixed>  $callback
     * @return array<string, mixed>
     */
    public function remember(DashboardFilter $filter, string $prefix, callable $callback): array
    {
        $ttl = $this->ttl();

        if ($ttl <= 0) {
            return $callback();
        }

        return Cache::remember($this->key($filter, $prefix), $ttl, $callback);
    }

    /**
     * Retire every cached dashboard payload.
     *
     * Called by the services that move the numbers underneath it, so what the
     * screen shows is never older than the last write.
     */
    public function flush(): void
    {
        Cache::forever(self::VERSION_KEY, $this->version() + 1);
    }

    public function version(): int
    {
        return (int) Cache::get(self::VERSION_KEY, 1);
    }

    private function key(DashboardFilter $filter, string $prefix): string
    {
        return sprintf('%s:v%d:%s', $prefix, $this->version(), $filter->cacheKey($prefix));
    }

    private function ttl(): int
    {
        return (int) config('mdp.dashboard.cache_ttl', 0);
    }
}
