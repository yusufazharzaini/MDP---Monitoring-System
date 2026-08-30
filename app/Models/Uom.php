<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\RecordStatus;
use App\Enums\UomType;
use App\Models\Concerns\HasRecordStatus;
use App\Models\Concerns\HasSearch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Uom extends Model
{
    use HasFactory;
    use HasRecordStatus;
    use HasSearch;
    use SoftDeletes;

    protected $table = 'uoms';

    protected $fillable = ['code', 'name', 'type', 'status'];

    /**
     * @var array<int, string>
     */
    protected array $searchable = ['code', 'name'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => UomType::class,
            'status' => RecordStatus::class,
        ];
    }

    public function materials(): HasMany
    {
        return $this->hasMany(Material::class);
    }
}
