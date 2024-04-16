<?php

/**
 * @file StringOperationException.php
 * @author Veranika Saltanava(xsalta01)
 */

namespace IPP\Student\Exception;

use IPP\Core\Exception\IPPException;
use IPP\Core\ReturnCode;
use Throwable;

class StringOperationException extends IPPException
{
    public function __construct(string $message = "String Operation Exception", ?Throwable $previous = null)
    {
        parent::__construct($message, ReturnCode::STRING_OPERATION_ERROR, $previous, false);
    }
}