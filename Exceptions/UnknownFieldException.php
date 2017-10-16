<?php

namespace LucianoJr\LaravelApiQueryHandler\Exceptions;

use Exception;

class UnknownFieldException extends Exception
{

    /**
     * UnknownFieldException constructor.
     * @param string $string
     */
    public function __construct($string = 'Unknown field')
    {

    }
}
