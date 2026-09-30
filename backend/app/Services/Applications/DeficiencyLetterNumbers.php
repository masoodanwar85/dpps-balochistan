<?php

namespace App\Services\Applications;

use App\Models\DeficiencyLetter;

class DeficiencyLetterNumbers
{
    /**
     * @return list<string>
     */
    public static function existing(string $prefix): array
    {
        return DeficiencyLetter::query()
            ->where('letter_no', 'like', $prefix.'%')
            ->pluck('letter_no')
            ->all();
    }
}
