<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Metadata only - the bytes live on the private disk and are streamed through
 * an authorised controller, never served directly.
 *
 * @property-read string $human_file_size
 */
class ProblemAttachment extends Model
{
    use HasFactory;
    use HasUlid;

    /**
     * Written only by AttachmentService, from a validated UploadedFile.
     *
     * `ulid` and `uploaded_by` are absent: the first is generated, the second
     * comes from the authenticated user.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'delivery_problem_id',
        'file_name',
        'file_path',
        'mime_type',
        'file_size',
    ];

    /**
     * The storage path is an implementation detail and a traversal risk if it
     * ever reached a template; downloads go through the signed route instead.
     *
     * @var array<int, string>
     */
    protected $hidden = ['file_path'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['file_size' => 'integer'];
    }

    /**
     * A genuine accessor: every attachment list renders this, and computing it
     * in the template would put formatting logic in the view.
     */
    protected function humanFileSize(): Attribute
    {
        return Attribute::get(function (): string {
            $units = ['B', 'KB', 'MB', 'GB'];
            $size = (float) $this->file_size;
            $unit = 0;

            while ($size >= 1024 && $unit < count($units) - 1) {
                $size /= 1024;
                $unit++;
            }

            return round($size, 1).' '.$units[$unit];
        });
    }

    public function problem(): BelongsTo
    {
        return $this->belongsTo(DeliveryProblem::class, 'delivery_problem_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function isImage(): bool
    {
        return str_starts_with($this->mime_type, 'image/');
    }
}
