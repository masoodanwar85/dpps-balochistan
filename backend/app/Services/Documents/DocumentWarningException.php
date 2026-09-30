<?php

namespace App\Services\Documents;

use RuntimeException;

class DocumentWarningException extends RuntimeException
{
    /**
     * @param  list<string>  $warnings
     */
    public function __construct(public readonly array $warnings)
    {
        parent::__construct('Document warnings require confirmation.');
    }
}
