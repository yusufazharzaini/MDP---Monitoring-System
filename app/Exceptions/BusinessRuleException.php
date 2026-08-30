<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use RuntimeException;

/**
 * Raised when a request is well-formed but violates a domain rule - receiving
 * against a cancelled order, closing a problem with no completed corrective
 * action, and so on.
 *
 * Rendered as a flashed error for Inertia requests and as a 422 for API clients.
 */
class BusinessRuleException extends RuntimeException
{
    public function render(Request $request): RedirectResponse|Response
    {
        if ($request->expectsJson()) {
            return response([
                'message' => $this->getMessage(),
                'errors' => ['business_rule' => [$this->getMessage()]],
            ], 422);
        }

        return back()->with('error', $this->getMessage())->withInput();
    }
}
