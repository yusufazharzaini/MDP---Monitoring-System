<?php

declare(strict_types=1);

namespace App\Http\Controllers\Problem;

use App\Http\Controllers\Controller;
use App\Http\Requests\Problem\CorrectiveActionRequest;
use App\Models\CorrectiveAction;
use App\Models\DeliveryProblem;
use App\Services\Problem\CorrectiveActionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Corrective actions, always addressed through their problem.
 *
 * There is no index or show: an action has no meaning away from the problem it
 * answers, and the problem's detail page renders the whole list.
 */
class CorrectiveActionController extends Controller
{
    public function __construct(
        private readonly CorrectiveActionService $actions,
    ) {}

    public function store(CorrectiveActionRequest $request, DeliveryProblem $problem): RedirectResponse
    {
        $this->authorize('create', [CorrectiveAction::class, $problem]);

        $this->actions->add($problem, $request->validated(), $request->user());

        return back()->with('success', 'Corrective action berhasil ditambahkan.');
    }

    public function update(
        CorrectiveActionRequest $request,
        DeliveryProblem $problem,
        CorrectiveAction $action,
    ): RedirectResponse {
        $this->authorize('update', $this->belongingTo($problem, $action));

        $this->actions->update($action, $request->validated(), $request->user());

        return back()->with('success', 'Corrective action berhasil diperbarui.');
    }

    public function start(Request $request, DeliveryProblem $problem, CorrectiveAction $action): RedirectResponse
    {
        $this->authorize('update', $this->belongingTo($problem, $action));

        $this->actions->markInProgress($action, $request->user());

        return back()->with('success', 'Corrective action sedang dikerjakan.');
    }

    public function complete(Request $request, DeliveryProblem $problem, CorrectiveAction $action): RedirectResponse
    {
        $this->authorize('complete', $this->belongingTo($problem, $action));

        $this->actions->complete($action, $request->user());

        return back()->with('success', 'Corrective action selesai. Problem sekarang dapat ditutup.');
    }

    public function destroy(DeliveryProblem $problem, CorrectiveAction $action): RedirectResponse
    {
        $this->authorize('delete', $this->belongingTo($problem, $action));

        $this->actions->remove($action);

        return back()->with('success', 'Corrective action dihapus.');
    }

    /**
     * Nested bindings are resolved independently, so an action id from one
     * problem would otherwise be reachable through another problem's URL.
     */
    private function belongingTo(DeliveryProblem $problem, CorrectiveAction $action): CorrectiveAction
    {
        abort_unless($action->delivery_problem_id === $problem->getKey(), 404);

        return $action;
    }
}
