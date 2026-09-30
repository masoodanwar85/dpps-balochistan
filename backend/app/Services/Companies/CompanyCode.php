<?php

namespace App\Services\Companies;

use App\Models\Company;

class CompanyCode
{
    public function next(): string
    {
        $max = 0;

        foreach (Company::withTrashed()->pluck('company_code') as $code) {
            if (preg_match('/^C-(\d+)$/', (string) $code, $match) === 1) {
                $max = max($max, (int) $match[1]);
            }
        }

        return 'C-'.str_pad((string) ($max + 1), 4, '0', STR_PAD_LEFT);
    }
}
