<?php

namespace App\Exceptions;

use Exception;

class SlotUnavailableException extends Exception
{
    public function __construct(string $message = 'That time is no longer available. Please choose another time.')
    {
        parent::__construct($message);
    }
}
