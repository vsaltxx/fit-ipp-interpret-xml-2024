<?php

/**
 * @file FrameStack.php
 * @author Veranika Saltanava(xsalta01)
 */

namespace IPP\Student;

class FrameStack {
    /**
     * @var MemoryFrame[]
     */
    private array $stack = [];

    public function push(MemoryFrame $frame): void {
        $this->stack[] = $frame;
    }

    public function pop(): ?MemoryFrame {
        return array_pop($this->stack);
    }

    public function isEmpty(): bool
    {
        return empty($this->stack);
    }

    public function top(): ?MemoryFrame {
        return end($this->stack);
    }
}