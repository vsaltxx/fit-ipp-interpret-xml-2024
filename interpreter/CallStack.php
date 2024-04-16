<?php

/**
 * @file CallStack.php
 * @author Veranika Saltanava(xsalta01)
 */

namespace IPP\Student;

class CallStack
{
    /**
     * @var array<int>
     */
    private array $stack = [];

    public function push(int $position): void
    {
        array_push($this->stack, $position);
    }

    public function pop(): ?int
    {
        return array_pop($this->stack);
    }
}
