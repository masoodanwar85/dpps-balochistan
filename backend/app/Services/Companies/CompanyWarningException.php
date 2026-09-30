<?php

namespace App\Services\Companies;

use RuntimeException;

class CompanyWarningException extends RuntimeException
{
    /**
     * @param  list<string>  $warnings
     */
    public function __construct(public readonly array $warnings)
    {
        parent::__construct('Company save needs confirmation.');
    }
}
