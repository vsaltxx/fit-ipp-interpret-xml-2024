<?php

/**
 * @file VariableAccessException.php
 * @author Veranika Saltanava(xsalta01)
 */

namespace IPP\Student\Exception;

use IPP\Core\Exception\IPPException;
use IPP\Core\ReturnCode;
use Throwable;

class VariableAccessException extends IPPException
{
    public function __construct(string $message = "Variable Access Exception", ?Throwable $previous = null)
    {
        parent::__construct($message, ReturnCode::VARIABLE_ACCESS_ERROR, $previous, false);
    }
}