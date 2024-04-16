<?php

/**
 * @file XMLStructureException.php
 * @author Veranika Saltanava(xsalta01)
 */

namespace IPP\Student\Exception;

use IPP\Core\Exception\IPPException;
use IPP\Core\ReturnCode;
use Throwable;

class XMLStructureException extends IPPException
{
    public function __construct(string $message = "Invalid XML structure", ?Throwable $previous = null)
    {
        parent::__construct($message, ReturnCode::INVALID_SOURCE_STRUCTURE, $previous, false);
    }
}