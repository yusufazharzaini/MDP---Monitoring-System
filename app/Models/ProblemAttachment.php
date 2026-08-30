<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Metadata only - the bytes live on the private disk and are streamed through
 * an authorised controller, never served directly.
 */
class ProblemAttachment extends Model
{
    use HasFactory;
    use HasUlid;

    protected $fillable = [
        'ulid', 'delivery_problem_id', 'file_name', 'file_path',
        'mime_type', 'file_size', 'uploaded_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['file_size' => 'integer'];
    }

    public function problem(): BelongsTo
    {
        return $this->belongsTo(DeliveryProblem::class, 'delivery_problem_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function humanFileSize(): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $size = (float) $this->file_size;
        $unit = 0;

        while ($size >= 1024 && $unit < count($units) - 1) {
            $size /= 1024;
            $unit++;
        }

        return round($size, 1).' '.$units[$unit];
    }
}
