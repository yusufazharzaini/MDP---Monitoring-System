<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SettingType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Typed key/value configuration that operations staff can change at runtime -
 * service-rate formula, over-delivery tolerance, critical-material rules.
 */
class SystemSetting extends Model
{
    use HasFactory;

    protected $fillable = ['setting_key', 'setting_value', 'type', 'group', 'description'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['type' => SettingType::class];
    }

    /**
     * The stored string cast back into its declared PHP type.
     */
    public function typedValue(): mixed
    {
        return $this->type->cast($this->setting_value);
    }
}
