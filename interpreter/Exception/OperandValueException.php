<?php

/**
 * @file OperandValueException.php
 * @author Veranika Saltanava(xsalta01)
 */

namespace IPP\Student\Exception;

use IPP\Core\Exception\IPPException;
use IPP\Core\ReturnCode;
use Throwable;

class OperandValueException extends IPPException
{
    public function __construct(string $message = "Operand Value Error", ?Throwable $previous = null)
    {
        parent::__construct($message, ReturnCode::OPERAND_VALUE_ERROR, $previous, false);
    }
}