<?php

/**
 * @file OperandTypeException.php
 * @author Veranika Saltanava(xsalta01)
 */

namespace IPP\Student\Exception;

use IPP\Core\Exception\IPPException;
use IPP\Core\ReturnCode;
use Throwable;

class OperandTypeException extends IPPException
{
    public function __construct(string $message = "Operand Type Error", ?Throwable $previous = null)
    {
        parent::__construct($message, ReturnCode::OPERAND_TYPE_ERROR, $previous, false);
    }
}