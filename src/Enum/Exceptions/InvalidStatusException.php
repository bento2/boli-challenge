<?php

namespace App\Enum\Exceptions;

use Exception;

class InvalidStatusException extends Exception
{
    public function __construct(string $message = "Invalid status")
    {
        parent::__construct($message);
    }
}
