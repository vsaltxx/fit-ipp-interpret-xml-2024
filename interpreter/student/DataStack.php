<?php

/**
 * @file DataStack.php
 * @author Veranika Saltanava(xsalta01)
 */

namespace IPP\Student;

class DataStack
{

    /**
     * @var array<int|string|bool|null>
     */
    private array $stack = [];


    public function push(mixed $value): void
    {
        array_push($this->stack, $value);
    }

    public function pop(): mixed
    {
        if ($this->isEmpty()) {
            echo "Data stack is empty\n";
            exit(1);
        }
        return array_pop($this->stack);
    }

    public function isEmpty(): bool
    {
        return empty($this->stack);
    }
}