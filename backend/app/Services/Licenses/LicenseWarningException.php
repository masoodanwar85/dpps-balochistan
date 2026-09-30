<?php

namespace App\Services\Licenses;

use RuntimeException;

class LicenseWarningException extends RuntimeException
{
    /**
     * @param  list<string>  $warnings
     */
    public function __construct(public array $warnings)
    {
        parent::__construct('Warnings need confirmation.');
    }
}
