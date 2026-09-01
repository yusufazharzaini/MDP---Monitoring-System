<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Switching the interface language.
 *
 * Open to guests on purpose: somebody who cannot read the login screen has to
 * be able to change it before signing in. It writes to the session for that
 * case, and additionally to the account when there is one, so the choice
 * survives a new browser.
 */
final class LocaleController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'locale' => ['required', 'string', Rule::in(array_keys((array) config('locales.supported')))],
        ]);

        $request->session()->put('locale', $validated['locale']);

        $user = $request->user();

        if ($user !== null) {
            $user->locale = $validated['locale'];
            $user->save();
        }

        return back();
    }
}
