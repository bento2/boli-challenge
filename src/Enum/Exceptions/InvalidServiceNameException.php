<?php

namespace App\Enum\Exceptions;

use Exception;

class InvalidServiceNameException extends Exception
{
    public function __construct(string $message = "Invalid service name")
    {
        parent::__construct($message);
    }
}
