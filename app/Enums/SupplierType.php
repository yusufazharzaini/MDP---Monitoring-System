<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasEnumMetadata;

enum SupplierType: string
{
    use HasEnumMetadata;

    case LOCAL = 'LOCAL';
    case IMPORT = 'IMPORT';
    case TOLLING = 'TOLLING';
    case SERVICE = 'SERVICE';
}
