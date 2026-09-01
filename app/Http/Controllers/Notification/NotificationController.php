<?php

declare(strict_types=1);

namespace App\Http\Controllers\Notification;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The reader's own notifications.
 *
 * Every action here is scoped to the signed-in user by construction: the
 * queries start from their relation rather than from the table, so there is no
 * id a reader could pass to reach somebody else's mail.
 */
class NotificationController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('Notifications/Index', [
            'records' => $request->user()
                ->appNotifications()
                ->latest()
                ->paginate(20)
                ->through(fn (Notification $notification): array => $this->summarise($notification)),
            'unread' => $request->user()->unreadNotifications()->count(),
        ]);
    }

    public function read(Request $request, string $id): RedirectResponse
    {
        // findOrFail on the user's own relation, so another reader's id is a
        // 404 rather than an authorisation question.
        $notification = $request->user()->appNotifications()->findOrFail($id);
        $notification->markAsRead();

        return back();
    }

    public function readAll(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return back()->with('success', 'Semua notifikasi ditandai sudah dibaca.');
    }

    /**
     * @return array<string, mixed>
     */
    private function summarise(Notification $notification): array
    {
        // The model already reads the payload; repeating those keys here would
        // give the same field two definitions that could drift.
        return [
            'id' => $notification->id,
            'title' => $notification->title,
            'message' => $notification->message,
            'severity' => $notification->severity,
            'url' => $notification->url,
            'read' => $notification->read_at !== null,
            'created_at' => $notification->created_at?->diffForHumans(),
        ];
    }
}
