<?php

namespace App\Enum\Exceptions;

use Exception;

class InvalidTypeException extends Exception
{
    public function __construct($message = "Invalid type")
    {
        parent::__construct($message);
    }
}
