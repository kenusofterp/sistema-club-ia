<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum PostStatus: string
{
    use EnumHelpers;

    case Draft = 'draft';
    case Published = 'published';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Borrador',
            self::Published => 'Publicada',
        };
    }

    public function color(): string
    {
        return $this === self::Published ? 'green' : 'gray';
    }
}
