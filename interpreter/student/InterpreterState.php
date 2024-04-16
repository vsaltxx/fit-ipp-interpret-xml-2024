<?php

/**
 * @file InterpreterState.php
 * @author Veranika Saltanava(xsalta01)
 */

namespace IPP\Student;

class InterpreterState
{
    private int $codePosition;
    private int $executedInstructionsCount;

    public function __construct(int $codePosition, int $executedInstructionsCount)
    {
        $this->codePosition = $codePosition;
        $this->executedInstructionsCount = $executedInstructionsCount;
    }

    public function getCodePosition(): int
    {
        return $this->codePosition;
    }

    public function getExecutedInstructionsCount(): int
    {
        return $this->executedInstructionsCount;
    }
}
