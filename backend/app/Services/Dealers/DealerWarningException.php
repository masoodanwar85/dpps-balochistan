<?php

namespace App\Services\Dealers;

use RuntimeException;

class DealerWarningException extends RuntimeException
{
    /**
     * @param  list<string>  $warnings
     */
    public function __construct(public readonly array $warnings)
    {
        parent::__construct('Dealer warnings require confirmation.');
    }
}
