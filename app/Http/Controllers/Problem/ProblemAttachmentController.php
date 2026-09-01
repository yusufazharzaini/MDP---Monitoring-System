<?php

declare(strict_types=1);

namespace App\Http\Controllers\Problem;

use App\Http\Controllers\Controller;
use App\Http\Requests\Problem\ProblemAttachmentRequest;
use App\Models\DeliveryProblem;
use App\Models\ProblemAttachment;
use App\Services\Problem\AttachmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Evidence files.
 *
 * The private disk has no public URL, so this controller is the only route to
 * the bytes and every action runs the policy before the service touches them.
 */
class ProblemAttachmentController extends Controller
{
    public function __construct(
        private readonly AttachmentService $attachments,
    ) {}

    public function store(ProblemAttachmentRequest $request, DeliveryProblem $problem): RedirectResponse
    {
        $this->authorize('create', [ProblemAttachment::class, $problem]);

        /** @var UploadedFile $file */
        $file = $request->file('file');

        $attachment = $this->attachments->store($problem, $file, $request->user());

        return back()->with('success', "Lampiran {$attachment->file_name} berhasil diunggah.");
    }

    /**
     * Stream a file back. Authorisation happens here, not at the filesystem:
     * the path is hidden on the model and never leaves the server.
     */
    public function download(DeliveryProblem $problem, ProblemAttachment $attachment): StreamedResponse
    {
        $this->authorize('view', $this->belongingTo($problem, $attachment));

        return $this->attachments->stream($attachment);
    }

    public function destroy(DeliveryProblem $problem, ProblemAttachment $attachment): RedirectResponse
    {
        $this->authorize('delete', $this->belongingTo($problem, $attachment));

        $name = $attachment->file_name;
        $this->attachments->delete($attachment);

        return back()->with('success', "Lampiran {$name} dihapus.");
    }

    /**
     * Nested bindings are resolved independently, so an attachment belonging to
     * another problem must not be reachable through this problem's URL.
     */
    private function belongingTo(DeliveryProblem $problem, ProblemAttachment $attachment): ProblemAttachment
    {
        abort_unless($attachment->delivery_problem_id === $problem->getKey(), 404);

        return $attachment;
    }
}
