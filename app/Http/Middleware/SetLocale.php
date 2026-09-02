<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * Chooses the language every response is rendered in.
 *
 * Precedence is deliberate: a signed-in person's saved choice wins, because it
 * should follow them to whatever machine they sit down at. The session covers
 * the login screen itself, where there is no account to read from yet. Anything
 * unrecognised falls through to the application default rather than being
 * trusted - the value reaches us from a cookie the client controls.
 */
final class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        App::setLocale($this->resolve($request));

        return $next($request);
    }

    private function resolve(Request $request): string
    {
        $supported = array_keys((array) config('locales.supported'));

        $candidates = [
            $request->user()?->locale,
            $request->session()->get('locale'),
        ];

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && in_array($candidate, $supported, true)) {
                return $candidate;
            }
        }

        return (string) config('app.locale');
    }
}
