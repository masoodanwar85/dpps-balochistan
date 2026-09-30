<?php

namespace App\Services\Dealers;

use App\Models\Dealer;
use App\Models\District;

class DealerCode
{
    public function next(District $district): string
    {
        $prefix = 'D-'.$district->code.'-';
        $max = 0;

        foreach (Dealer::withTrashed()->where('dealer_code', 'like', $prefix.'%')->pluck('dealer_code') as $code) {
            if (preg_match('/^'.preg_quote($prefix, '/').'(\d+)$/', (string) $code, $match) === 1) {
                $max = max($max, (int) $match[1]);
            }
        }

        return $prefix.str_pad((string) ($max + 1), 4, '0', STR_PAD_LEFT);
    }
}
