<?php

namespace App\Services\Persons;

use RuntimeException;

class PersonWarningException extends RuntimeException
{
    /**
     * @param  list<string>  $warnings
     */
    public function __construct(public readonly array $warnings)
    {
        parent::__construct('Person update needs confirmation.');
    }
}
