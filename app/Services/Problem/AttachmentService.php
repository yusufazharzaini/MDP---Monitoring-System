<?php

declare(strict_types=1);

namespace App\Services\Problem;

use App\Enums\ProblemStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\DeliveryProblem;
use App\Models\ProblemAttachment;
use App\Models\User;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Evidence files for delivery problems (requirements 8 and 35).
 *
 * The bytes go to the private disk, which has no public URL, under a name this
 * service generates - the uploader's filename is kept for display only and
 * never becomes part of a path, so a name like `../../.env` cannot escape the
 * directory. Downloads are streamed back through an authorised controller.
 */
class AttachmentService
{
    /**
     * The MIME types the private disk will accept.
     *
     * The form request validates the extension; this list validates what the
     * file actually is, as reported by the finfo probe on the temporary upload.
     * Both have to agree, because either one alone is bypassable.
     *
     * @var array<int, string>
     */
    private const ALLOWED_MIME_TYPES = [
        'application/pdf',
        'image/jpeg',
        'image/png',
        'image/webp',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ];

    public function store(DeliveryProblem $problem, UploadedFile $file, ?User $actor = null): ProblemAttachment
    {
        $this->guardProblemAcceptsAttachments($problem);
        $this->guardSize($file);

        $mimeType = $this->guardedMimeType($file);
        $directory = $this->directoryFor($problem);
        $storedName = $this->storedName($file);

        $path = $file->storeAs($directory, $storedName, ['disk' => $this->diskName()]);

        if ($path === false) {
            throw new BusinessRuleException('File gagal disimpan. Silakan coba lagi.');
        }

        return DB::transaction(function () use ($problem, $file, $path, $mimeType, $actor): ProblemAttachment {
            $attachment = new ProblemAttachment;

            $attachment->fill([
                'delivery_problem_id' => $problem->getKey(),
                // Kept for display and for the download filename; sanitised
                // because it is echoed back into a Content-Disposition header.
                'file_name' => $this->displayName($file),
                'file_path' => $path,
                'mime_type' => $mimeType,
                'file_size' => $file->getSize(),
            ]);

            $attachment->forceFill(['uploaded_by' => $actor?->getKey()])->save();

            return $attachment;
        });
    }

    /**
     * Remove an attachment and its bytes.
     *
     * The row goes first and the file only once that commit has landed: a
     * rolled-back delete that had already erased the file would leave a record
     * pointing at nothing, which is the worse of the two failures.
     */
    public function delete(ProblemAttachment $attachment): void
    {
        $path = $attachment->file_path;

        DB::transaction(static function () use ($attachment): void {
            $attachment->delete();
        });

        $this->disk()->delete($path);
    }

    /**
     * Stream the file back to an authorised user.
     *
     * The path never reaches the browser and the disk has no public URL, so
     * this controller-mediated stream is the only way to the bytes.
     */
    public function stream(ProblemAttachment $attachment): StreamedResponse
    {
        $disk = $this->disk();

        if (! $disk->exists($attachment->file_path)) {
            throw new BusinessRuleException(
                "File {$attachment->file_name} tidak ditemukan pada penyimpanan."
            );
        }

        return $disk->download($attachment->file_path, $attachment->file_name, [
            'Content-Type' => $attachment->mime_type,
            // Belt and braces against a stored file being interpreted as
            // something executable by a browser that sniffs content.
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * A cancelled problem is a withdrawn report; nothing further attaches to it.
     */
    private function guardProblemAcceptsAttachments(DeliveryProblem $problem): void
    {
        if ($problem->status === ProblemStatus::CANCELLED) {
            throw new BusinessRuleException(
                "Problem {$problem->problem_number} sudah dibatalkan, lampiran tidak dapat ditambahkan."
            );
        }
    }

    private function guardSize(UploadedFile $file): void
    {
        $size = $file->getSize();
        $maxBytes = $this->maxKilobytes() * 1024;

        // Zero-byte uploads would also break the chk_attachment_size_positive
        // constraint, which is the same rule expressed in the schema.
        if ($size === false || $size <= 0) {
            throw new BusinessRuleException('File kosong tidak dapat diunggah.');
        }

        if ($size > $maxBytes) {
            throw new BusinessRuleException(
                'Ukuran file melebihi batas '.number_format($this->maxKilobytes() / 1024, 1).' MB.'
            );
        }
    }

    /**
     * The type the file really is, not the type the client claimed.
     */
    private function guardedMimeType(UploadedFile $file): string
    {
        $mimeType = $file->getMimeType() ?? 'application/octet-stream';

        if (! in_array($mimeType, self::ALLOWED_MIME_TYPES, true)) {
            throw new BusinessRuleException("Tipe file {$mimeType} tidak diizinkan.");
        }

        return $mimeType;
    }

    /**
     * One directory per problem, addressed by ULID rather than by the numeric
     * primary key, so the storage layout exposes no row counts.
     */
    private function directoryFor(DeliveryProblem $problem): string
    {
        return trim((string) config('mdp.attachments.directory'), '/').'/'.$problem->ulid;
    }

    /**
     * A generated name with the validated extension. Nothing the uploader
     * controls reaches the filesystem path.
     */
    private function storedName(UploadedFile $file): string
    {
        $extension = strtolower($file->extension() ?: $file->getClientOriginalExtension());
        $extension = preg_replace('/[^a-z0-9]/', '', $extension) ?: 'bin';

        return Str::ulid()->toString().'.'.$extension;
    }

    /**
     * The original filename with any directory part and control characters
     * stripped, truncated to the column width.
     */
    private function displayName(UploadedFile $file): string
    {
        $name = basename($file->getClientOriginalName());
        $name = preg_replace('/[\x00-\x1F\x7F"\\\\\/]+/', '', $name) ?? '';
        $name = trim($name);

        return $name === '' ? 'lampiran' : Str::limit($name, 250, '');
    }

    private function maxKilobytes(): int
    {
        return (int) config('mdp.attachments.max_kilobytes', 5120);
    }

    private function diskName(): string
    {
        return (string) config('mdp.attachments.disk', 'private');
    }

    private function disk(): FilesystemAdapter
    {
        return Storage::disk($this->diskName());
    }
}
