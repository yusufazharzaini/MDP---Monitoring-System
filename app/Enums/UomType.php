<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasEnumMetadata;

enum UomType: string
{
    use HasEnumMetadata;

    case QTY = 'QTY';
    case WEIGHT = 'WEIGHT';
    case VOLUME = 'VOLUME';
    case LENGTH = 'LENGTH';
    case AREA = 'AREA';
    case TIME = 'TIME';
}
