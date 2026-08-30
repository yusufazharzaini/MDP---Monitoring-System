<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\RecordStatus;
use App\Models\Concerns\HasRecordStatus;
use App\Models\Concerns\HasSearch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProblemCategory extends Model
{
    use HasFactory;
    use HasRecordStatus;
    use HasSearch;
    use SoftDeletes;

    protected $fillable = ['code', 'name', 'description', 'status'];

    /**
     * @var array<int, string>
     */
    protected array $searchable = ['code', 'name'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['status' => RecordStatus::class];
    }

    public function problems(): HasMany
    {
        return $this->hasMany(DeliveryProblem::class);
    }
}
