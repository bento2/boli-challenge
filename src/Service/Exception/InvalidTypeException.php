<?php

namespace App\Service\Exception;

class InvalidTypeException extends \InvalidArgumentException
{
    public function __construct($message = "Invalid type")
    {
        parent::__construct($message);
    }
}
