<?php

/**
 * @file MemoryFrame.php
 * @author Veranika Saltanava(xsalta01)
 */

namespace IPP\Student;
use InvalidArgumentException;
use IPP\Student\Exception\VariableAccessException;

class MemoryFrame
{
    /**
     * @var array<int|string|bool|null>
     */
    public array $globalFrame = [];
    /**
     * @var array<int|string|bool|null>
     */
    public array $localFrame = [];
    /**
     * @var array<int|string|bool|null>
     */
    public array $temporaryFrame = [];
    /**
     * @var array<string>
     */
    private array $labels = [];


    public function setVariableValue(string $frame,string $name, mixed $value): void
    {
        switch ($frame) {
            case 'GF':
                $this->globalFrame[$name] = $value;
                break;
            case 'LF':
                $this->localFrame[$name] = $value;
                break;
            case 'TF':
                $this->temporaryFrame[$name] = $value;
                break;
            default:
                throw new InvalidArgumentException("Invalid frame '$frame'.");
        }
    }

    public function clearTemporaryFrame(): void
    {
        $this->temporaryFrame = [];
    }

    /**
     * @throws VariableAccessException
     */
    public function getVariableValue(string $frame, string $name): int|string|bool|null
    {
        return match ($frame) {
            'GF' => $this->globalFrame[$name] ?? null,
            'LF' => $this->localFrame[$name] ?? null,
            'TF' => $this->temporaryFrame[$name] ?? null,
            default => throw new VariableAccessException("Invalid frame '$frame'."),
        };
    }

    /**
     * @return array<int|string|bool|null>
     */
    public function getVariables(string $frame): array
    {
        return match ($frame) {
            'GF' => $this->globalFrame,
            'LF' => $this->localFrame,
            'TF' => $this->temporaryFrame,
            default => throw new InvalidArgumentException("Invalid frame '$frame'."),
        };
    }

    /**
     * @throws VariableAccessException
     */
    public function variableExists(string $frame, string $name): bool
    {
        return match ($frame) {
            'GF' => array_key_exists($name, $this->globalFrame),
            'LF' => array_key_exists($name, $this->localFrame),
            'TF' => array_key_exists($name, $this->temporaryFrame),
            default => throw new VariableAccessException("Invalid frame '$frame'."),
        };
    }

    /*----------------------------------------------*/
/*------------------------LABELS-------------------------*/
    /*----------------------------------------------*/

    public function labelExists(string $labelName): bool
    {
        return isset($this->labels[$labelName]);
    }

    public function addLabel(string $labelName): void
    {
        $this->labels[$labelName] = true;
    }

    /**
     * @param array<array{opcode: string, argTypes: array<string>, argValues: array<string>}> $instructions
     */
    public function getLabelIndex(string $labelName, array $instructions): int
    {
        foreach ($instructions as $index => $instruction) {
            if ($instruction['opcode'] === 'LABEL' && $instruction['argValues'][0] === $labelName) {
                return $index;
            }
        }
        throw new \RuntimeException("Label '$labelName' not found.");
    }
}
