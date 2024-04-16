<?php

/**
 * @file ExecuteInstruction.php
 * @author Veranika Saltanava(xsalta01)
 */

namespace IPP\Student;

use IPP\Core\Exception\InternalErrorException;
use IPP\Core\Interface\InputReader;
use IPP\Core\Interface\OutputWriter;
use IPP\Student\Exception\FrameAccessException;
use IPP\Student\Exception\InterpretSemanticException;
use IPP\Student\Exception\MissingValueException;
use IPP\Student\Exception\OperandTypeException;
use IPP\Student\Exception\OperandValueException;
use IPP\Student\Exception\StringOperationException;
use IPP\Student\Exception\XMLStructureException;
use IPP\Student\Exception\VariableAccessException;


class ExecuteInstruction
{
    private bool $frameCreated = false;
    private int $executedInstructionsCount = 0;
    private OutputWriter $stdout;
    private OutputWriter $stderr;
    private InputReader $input;
    public function __construct(OutputWriter $stdout, OutputWriter $stderr, InputReader $input)
    {
        $this->stdout = $stdout;
        $this->stderr = $stderr;
        $this->input = $input;
    }

    public function executeInstruction(): void
    {
        $this->executedInstructionsCount++;
    }

    public function getExecutedInstructionsCount(): int
    {
        return $this->executedInstructionsCount;
    }

    // CREATEFRAME
    public function executeCreateframe(MemoryFrame $memoryFrame): void
    {
        // Создаем новый временный фрейм и заменяем текущий временный фрейм на него
        $memoryFrame->clearTemporaryFrame();
        $this->frameCreated = true;
    }

    // DEFVAR <var>
    /**
     * @param array{
     *      opcode: string,
     *      argTypes: array<string>,
     *      argValues: array<string>
     *  } $instruction
     * @param MemoryFrame $memoryFrame
     * @throws InterpretSemanticException
     * @throws VariableAccessException
     */
    public function executeDefVar(array $instruction, MemoryFrame $memoryFrame): void
    {
        $var = $instruction['argValues'][0];
        $frame = substr($var, 0, strpos($var, '@'));
        $varName = substr($var, 3);

        if ($memoryFrame->variableExists($frame, $varName)) {
            throw new InterpretSemanticException("Variable '$varName' already exists in frame '$frame'.");
        }

        // Add the variable to the frame
        $memoryFrame->setVariableValue($frame, $varName, null);
        //var_dump($memoryFrame);
    }

    // MOVE <var> <symb>
    /**
     * @param array{
     *      opcode: string,
     *      argTypes: array<string>,
     *      argValues: array<string>
     *  } $instruction
     * @param MemoryFrame $memoryFrame
     * @throws VariableAccessException
     * @throws OperandTypeException
     */
    public function executeMove(array $instruction, MemoryFrame $memoryFrame): void
    {
        // Получаем данные из аргументов инструкции
        $var = $instruction['argValues'][0];
        $varFrame = substr($var, 0, 2); // Получаем тип фрейма переменной
        $varName = substr($var, 3);

        $symbType = $instruction['argTypes'][1];
        $symbValue = $instruction['argValues'][1];

        // Определяем, из какого фрейма нужно получить значение
        switch ($symbType) {
            case 'var':
                $symbFrame = substr($symbValue, 0, 2);
                $symbName = substr($symbValue, 3);
                if (!$memoryFrame->variableExists($symbFrame, $symbName)) {
                    throw new VariableAccessException("Variable '$symbName' is undefined in frame '$symbFrame'.");
                }
                $value = $memoryFrame->getVariableValue($symbFrame, $symbName);
                break;
            case 'int':
                $value = (int)$symbValue;
                break;
            case 'bool':
                $value = $symbValue === 'true';
                break;
            case 'string':
                $value = (string)$symbValue;
                break;
              case 'nil':
                  $value = null;
                  break;
            default:
                throw new OperandTypeException("Invalid symbol type '$symbType'.");
        }

        if (!$memoryFrame->variableExists($varFrame, $varName)) {
            throw new VariableAccessException("Variable '$varName' is undefined in frame '$varFrame'.");
        }
        $memoryFrame->setVariableValue($varFrame, $varName, $value);
    }

    // PUSHFRAME
    /**
     * @param MemoryFrame $memoryFrame
     * @param FrameStack $frameStack
     * @throws FrameAccessException
     */
    public function executePushFrame(MemoryFrame $memoryFrame, FrameStack $frameStack): void
    {
        if (!$this->frameCreated) {
            throw new FrameAccessException("No frame was created.");
        }

        $newMemoryFrame = new MemoryFrame();

        // Add the variables from the temporary frame to the new frame
        $temporaryFrameVariables = $memoryFrame->getVariables("TF");

        // Copy the values from the temporary frame to the new frame
        if (!empty($temporaryFrameVariables)) {
            foreach ($temporaryFrameVariables as $name => $value) {
                $newMemoryFrame->setVariableValue('LF', $name, $value);
            }
        }

        // Add the new frame to the stack top
        $frameStack->push($newMemoryFrame);

        // Clear the temporary frame
        $memoryFrame->clearTemporaryFrame();
    }

    // POPFRAME
    /**
     * @param MemoryFrame $memoryFrame
     * @param FrameStack $frameStack
     * @throws MissingValueException
     * @throws VariableAccessException
     * @throws FrameAccessException
     */
    public function executePopFrame(MemoryFrame $memoryFrame, FrameStack $frameStack): void
    {

        if (!$this->frameCreated) {
            throw new FrameAccessException("No frame was created.");
        }

        $memoryFrame->temporaryFrame = $memoryFrame->localFrame;

        // Check if the frame stack is empty
        if ($frameStack->isEmpty()) {
            throw new FrameAccessException("Frame stack is empty.");
        }

        // Get the top frame from the stack
        $localFrame = $frameStack->top();

        // Copy the values from the local frame to the temporary frame
        foreach ($localFrame->getVariables('LF') as $name => $value) {
            $memoryFrame->setVariableValue('LF', $name, $value);
        }
        // Renew the LF variable values
        $previousFrame = $frameStack->pop();

        $memoryFrame->localFrame = $previousFrame->localFrame;

        $this->frameCreated = false;
    }

    // CALL <label>
    /**
     * @param array{
     *      opcode: string,
     *      argTypes: array<string>,
     *      argValues: array<string>
     *  } $instruction
     * @param MemoryFrame $memoryFrame
     * @param CallStack $callStack
     * @param array<array{
     *      opcode: string,
     *      argTypes: array<string>,
     *      argValues: array<string>
     *  }> $instructions
     * @param int $instructionPointer
     * @return int
     * @throws InterpretSemanticException
     */
    public function executeCall(array $instruction, MemoryFrame $memoryFrame, CallStack $callStack, array $instructions, int $instructionPointer): int
    {
        $labelName = $instruction['argValues'][0];
        if (!$memoryFrame->labelExists($labelName)) {
            throw new InterpretSemanticException("Label '$labelName' not found.");
        }
        $callStack->push($instructionPointer);
        return $memoryFrame->getLabelIndex($labelName, $instructions);
    }

    // RETURN
    /**
     * @param CallStack $callStack
     * @return int
     * @throws MissingValueException
     */
    public function executeReturn(CallStack $callStack): int {
        $returnPosition = $callStack->pop();
        if ($returnPosition === null) {
            throw new MissingValueException("Call stack is empty.");
        }
        return $returnPosition;
    }

    // PUSHS <symb>
    /**
     * @param array{
     *      opcode: string,
     *      argTypes: array<string>,
     *      argValues: array<string>
     *  } $instruction
     * @param MemoryFrame $memoryFrame
     * @param DataStack $dataStack
     * @throws OperandTypeException
     * @throws VariableAccessException
     */
    public function executePushs(array $instruction, MemoryFrame $memoryFrame, DataStack $dataStack): void {
        $symbValue = $instruction['argValues'][0];
        $symbType = $instruction['argTypes'][0];

        switch ($symbType) {
            case 'var':
                $varFrame = substr($symbValue, 0, 2);
                $varName = substr($symbValue, 3);
                if (!$memoryFrame->variableExists($varFrame, $varName)) {
                    throw new VariableAccessException("Variable '$varName' is undefined in frame '$varFrame'.");
                }
                $value = $memoryFrame->getVariableValue($varFrame, $varName);
                break;
            case 'int':
                $value = (int)$symbValue;
                break;
            case 'bool':
                $value = $symbValue === 'true';
                break;
            case 'string':
                $value = (string)$symbValue;
                break;
            case 'nil':
                $value = null;
                break;
            default:
                throw new OperandTypeException("Invalid symbol type '$symbType'.");
        }
        $dataStack->push($value);
    }

    // POPS <var>
    /**
     * @param array{
     *      opcode: string,
     *      argTypes: array<string>,
     *      argValues: array<string>
     *  } $instruction
     * @param MemoryFrame $memoryFrame
     * @param DataStack $dataStack
     * @throws MissingValueException
     * @throws VariableAccessException
     * @throws OperandTypeException
     */
    public function executePops(array $instruction, MemoryFrame $memoryFrame, DataStack $dataStack): void {
        $var = $instruction['argValues'][0];
        $varType = $instruction['argTypes'][0];

        if ($varType != 'var') {
           throw new OperandTypeException("First argument of POPS instruction must be a variable.");
        }

        $varFrame = substr($var, 0, 2);
        $varName = substr($var, 3);
        if (!$memoryFrame->variableExists($varFrame, $varName)) {
            throw new VariableAccessException("Variable '$varName' is undefined in frame '$varFrame'.");
        }

        // Проверяем, что стек данных не пуст
        if ($dataStack->isEmpty()) {
            throw new MissingValueException("Data stack is empty.");
        }

        // Извлекаем значение из стека данных
        $value = $dataStack->pop();

        // Устанавливаем значение переменной в памяти
        $memoryFrame->setVariableValue($varFrame, $varName, $value);
    }

    // Function to validate and retrieve symbol value
    /**
     * @param string $symb
     * @param string $symbType
     * @param FrameStack $frameStack
     * @param MemoryFrame $memoryFrame
     * @param string $opcode
     * @return int|bool|string|null
     * @throws FrameAccessException
     * @throws VariableAccessException
     * @throws OperandTypeException
     */
    function validateAndRetrieveSymb(string $symb, string $symbType, FrameStack $frameStack, MemoryFrame $memoryFrame, string $opcode): int|bool|string|null
    {
        if ($symbType == 'var') {
            $frame = substr($symb, 0, 2);
            $name = substr($symb, 3);
            if ($frame == 'LF') {
                $currentLocalFrame = $frameStack->top();
                if (!$currentLocalFrame) {
                    throw new FrameAccessException("No local frame is available on the stack.");
                }
                if (!$currentLocalFrame->variableExists($frame, $name)) {
                    throw new VariableAccessException("Variable '$name' is undefined in frame '$frame'.");
                }
                $value = $currentLocalFrame->getVariableValue($frame, $name);
            } else {
                if (!$memoryFrame->variableExists($frame, $name)) {
                    throw new VariableAccessException("Variable '$name' is undefined in frame '$frame'.");
                }
                $value = $memoryFrame->getVariableValue($frame, $name);
            }
            if (gettype($value) != 'integer' && $opcode != 'WRITE') {
                throw new OperandTypeException("Variable '$name' is not of type int.");
            }
            return $value;
        } else {
            if (!in_array($symbType, ['int', 'var']) and $opcode != 'WRITE') {
                throw new OperandTypeException("Invalid symbol type '$symbType'.");
            }
            if ($opcode == 'WRITE') {
                return $symb;
            }
            return (int)$symb;
        }
    }


    // function to validate the variable
    /**
     * @param MemoryFrame $memoryFrame
     * @param FrameStack $frameStack
     * @param string $var
     * @return array<string>
     * @throws FrameAccessException
     * @throws VariableAccessException
     */
    public function validateAndRetrieveVariable(MemoryFrame $memoryFrame, FrameStack $frameStack, string $var): array {
        $varFrame = substr($var, 0, 2);
        $varName = substr($var, 3);

        if ($varFrame == 'LF') {
            $currentLocalFrame = $frameStack->top();
            if (!$currentLocalFrame) throw new FrameAccessException("No local frame is available on the stack.");
            if (!$currentLocalFrame->variableExists($varFrame, $varName)) {
                throw new VariableAccessException("Variable '$varName' is undefined in frame '$varFrame'.");
            }
        } else {
            if (!$memoryFrame->variableExists($varFrame, $varName)) {
                throw new VariableAccessException("Variable '$varName' is undefined in frame '$varFrame'.");
            }
        }
        return [$varFrame, $varName];
    }


    // ADD <var> <symb1> <symb2>
    // SUB <var> <symb1> <symb2>
    // MUL <var> <symb1> <symb2>
    // IDIV <var> <symb1> <symb2>
    /**
     * @param array{
     *      opcode: string,
     *      argTypes: array<string>,
     *      argValues: array<string>
     *  } $instruction
     * @param MemoryFrame $memoryFrame
     * @param FrameStack $frameStack
     * @throws OperandTypeException
     * @throws OperandValueException
     * @throws InterpretSemanticException
     * @throws VariableAccessException
     * @throws FrameAccessException
     */
    public function executeArithmeticInstruction(array $instruction, MemoryFrame $memoryFrame, FrameStack $frameStack): void
    {
        $opcode = $instruction['opcode'];
        $var = $instruction['argValues'][0];
        $symb1 = $instruction['argValues'][1];
        $symb2 = $instruction['argValues'][2];

        $varType = $instruction['argTypes'][0];
        if ($varType != 'var') {
            throw new OperandTypeException("First argument of arithmetic instruction must be a variable.");
        }
        [$varFrame, $varName] = $this->validateAndRetrieveVariable($memoryFrame, $frameStack, $var);

        $symb1Type = $instruction['argTypes'][1];
        $symb2Type = $instruction['argTypes'][2];

        $symb1Value = $this->validateAndRetrieveSymb($symb1, $symb1Type, $frameStack, $memoryFrame, $opcode);
        $symb2Value = $this->validateAndRetrieveSymb($symb2, $symb2Type, $frameStack, $memoryFrame, $opcode);


        switch ($opcode) {
            case 'ADD':
                $result = $symb1Value + $symb2Value;
                break;
            case 'SUB':
                $result = $symb1Value - $symb2Value;
                break;
            case 'MUL':
                $result = $symb1Value * $symb2Value;
                break;
            case 'IDIV':
                if ($symb2Value == 0) {
                    throw new OperandValueException("Division by zero.");
                }
                $result = (int)($symb1Value / $symb2Value);
                break;
            default:
                throw new InterpretSemanticException("Invalid opcode for arithmetic instruction.");
        }
        $memoryFrame->setVariableValue($varFrame, $varName, $result);
    }


    // Helper function to validate and retrieve symbol for comparison
    /**
     * @param MemoryFrame $memoryFrame
     * @param FrameStack $frameStack
     * @param string $symbType
     * @param string $symbValue
     * @param string $opcode
     * @return array<int|bool|string|null>
     * @throws OperandTypeException
     * @throws VariableAccessException
     * @throws FrameAccessException
     */
    private function validateAndRetrieveSymbolForCompare(MemoryFrame $memoryFrame, FrameStack $frameStack, string $symbType, string $symbValue, string $opcode): array
    {
        if ($opcode === 'LT' || $opcode === 'GT' || $opcode === 'EQ') {

            if ($symbType == 'int') {
                return [$symbType, (int)$symbValue];
            }
            else if ($symbType == 'bool') {
                return [$symbType, $symbValue === 'true'];
            }
            else if ($symbType === 'string') {
                return [$symbType, $symbValue];
            }
            else if ($symbType === 'nil' && $opcode === 'EQ') {
                return [$symbType, null];
            }
            elseif ($symbType === 'var') {
                [$symbFrame, $symbName] = $this->validateAndRetrieveVariable($memoryFrame, $frameStack, $symbValue);
                $symbValue = $symbFrame === 'LF' ? $frameStack->top()->getVariableValue($symbFrame, $symbName) : $memoryFrame->getVariableValue($symbFrame, $symbName);
                if ($symbValue === null && $opcode != 'EQ') {
                    throw new OperandTypeException("Null type variable can only be used with EQ opcode.");
                }
                return [$symbType, $symbValue];
            }
            else {
                throw new OperandTypeException("Invalid symbol type '$symbType'.");
            }
        } else//if ($opcode == 'AND' || $opcode == 'OR' || $opcode == 'NOT')
        {
            if ($symbType === 'bool') {
                return [$symbType, $symbValue];
            }
            elseif ($symbType === 'var') {
                [$symbFrame, $symbName] = $this->validateAndRetrieveVariable($memoryFrame, $frameStack, $symbValue);
                $symbValue = $symbFrame === 'LF' ? $frameStack->top()->getVariableValue($symbFrame, $symbName) : $memoryFrame->getVariableValue($symbFrame, $symbName);

                if (gettype($symbValue) !== 'boolean') {
                    throw new OperandTypeException("Variable '$symbName' is not of type bool.");
                }
                return [$symbType, $symbValue];
            }
            else {
                throw new OperandTypeException("Invalid symbol type '$symbType'.");
            }
        }
    }

    // LT <var> <symb1> <symb2>
    // GT <var> <symb1> <symb2>
    // EQ <var> <symb1> <symb2>
    /**
     * @param array{
     *      opcode: string,
     *      argTypes: array<string>,
     *      argValues: array<string>
     *  } $instruction
     * @param MemoryFrame $memoryFrame
     * @param FrameStack $frameStack
     * @throws OperandTypeException
     * @throws InterpretSemanticException
     * @throws VariableAccessException
     * @throws FrameAccessException
     */
    public function executeComparisonInstruction(array $instruction, MemoryFrame $memoryFrame, FrameStack $frameStack): void
    {
        $opcode = $instruction['opcode'];
        $var = $instruction['argValues'][0];
        $symb1 = $instruction['argValues'][1];
        $symb2 = $instruction['argValues'][2];
        $symb1Type =  $instruction['argTypes'][1];
        $symb2Type =  $instruction['argTypes'][2];

        // Check if the first argument is a variable
        $varType = $instruction['argTypes'][0];
        if ($varType !== 'var') {
            throw new OperandTypeException("First argument of comparison instruction must be a variable.");
        }
        [$varFrame, $varName] = $this->validateAndRetrieveVariable($memoryFrame, $frameStack, $var);


        [$symb1Type, $symb1Value] = $this->validateAndRetrieveSymbolForCompare($memoryFrame, $frameStack, $symb1Type, $symb1, $opcode);
        [$symb2Type, $symb2Value] = $this->validateAndRetrieveSymbolForCompare($memoryFrame, $frameStack, $symb2Type, $symb2, $opcode);


        if (gettype($symb1Value) == 'NULL' || gettype($symb2Value) == 'NULL') {
            if ($opcode != 'EQ') {
                throw new OperandTypeException("With comparison operand NULL must be instruction EQ.");
            }
        }
        else if (gettype($symb1Value) !== gettype($symb2Value)){
            throw new OperandTypeException("Comparison operands must be of the same type.");
        }

        $result = match ($instruction['opcode']) {
            'LT' => $symb1Value < $symb2Value,
            'GT' => $symb1Value > $symb2Value,
            'EQ' => $symb1Value === $symb2Value,
            default => throw new InterpretSemanticException("Invalid opcode for comparison instruction.")
        };

        $memoryFrame->setVariableValue($varFrame, $varName, $result ? 'true' : 'false');
    }


    // AND <var> <symb1> <symb2>
    // OR <var> <symb1> <symb2>
    // NOT <var> <symb1>
    /**
     * @param array{
     *      opcode: string,
     *      argTypes: array<string>,
     *      argValues: array<string>
     *  } $instruction
     * @param MemoryFrame $memoryFrame
     * @param FrameStack $frameStack
     * @throws OperandTypeException
     * @throws InterpretSemanticException
     * @throws VariableAccessException
     * @throws FrameAccessException
     */
    public function executeBooleanInstruction(array $instruction, MemoryFrame $memoryFrame, FrameStack $frameStack): void
    {
        $opcode = $instruction['opcode'];
        [$varFrame, $varName] = $this->validateAndRetrieveVariable($memoryFrame, $frameStack, $instruction['argValues'][0]);
        [, $symb1Value] = $this->validateAndRetrieveSymbolForCompare($memoryFrame, $frameStack, $instruction['argTypes'][1], $instruction['argValues'][1], $opcode);

        switch ($instruction['opcode']) {
            case 'AND':
                [, $symb2Value] = $this->validateAndRetrieveSymbolForCompare($memoryFrame, $frameStack, $instruction['argTypes'][2], $instruction['argValues'][2], $opcode);
                if (gettype($symb1Value) !== 'boolean' || gettype($symb2Value) !== 'boolean') {
                    $symb2Value = $symb2Value === 'true';
                    $symb1Value = $symb1Value === 'true';
                }
                $result = $symb1Value && $symb2Value;
                break;
            case 'OR':
                [, $symb2Value] = $this->validateAndRetrieveSymbolForCompare($memoryFrame, $frameStack, $instruction['argTypes'][2], $instruction['argValues'][2], $opcode);
                if (gettype($symb1Value) !== 'boolean' || gettype($symb2Value) !== 'boolean') {
                    $symb2Value = $symb2Value === 'true';
                    $symb1Value = $symb1Value === 'true';
                }
                $result = $symb1Value || $symb2Value;
                break;
            case 'NOT':
                if (gettype($symb1Value) !== 'boolean') {
                    $symb1Value = $symb1Value === 'true';
                }
                $result = !$symb1Value;
                break;
            default:
                throw new InterpretSemanticException("Invalid opcode for boolean instruction.");
        }
        $memoryFrame->setVariableValue($varFrame, $varName, $result);
    }

    // INT2CHAR <var> <symb>
    /**
     * @param array{
     *      opcode: string,
     *      argTypes: array<string>,
     *      argValues: array<string>
     *  } $instruction
     * @param MemoryFrame $memoryFrame
     * @param FrameStack $frameStack
     * @throws OperandTypeException
     * @throws StringOperationException
     * @throws VariableAccessException
     * @throws FrameAccessException
     */
    public function executeIntToCharInstruction(array $instruction, MemoryFrame $memoryFrame, FrameStack $frameStack): void
    {
        // Get the variable and symbol values
        $var = $instruction['argValues'][0];
        $symb = $instruction['argValues'][1];
        $symbType = $instruction['argTypes'][1];

        // Check and retrieve the symbol value
        $symbValue = $this->validateAndRetrieveSymbUniversal($symb, $symbType, $frameStack, $memoryFrame);

        // Check if the symbol is a valid Unicode code point
        if (gettype($symbValue) !== 'integer')
            throw new OperandTypeException("Invalid Unicode code point for INT2CHAR instruction.");

        if ($symbValue < 0 || $symbValue > 0x10FFFF || !mb_check_encoding(mb_chr($symbValue, 'UTF-8')))
            throw new StringOperationException("Invalid Unicode code point for INT2CHAR instruction.");


        // Convert the Unicode code point to a character
        $char = mb_chr($symbValue, 'UTF-8');

        // Save the character to the variable
        [$varFrame, $varName] = $this->validateAndRetrieveVariable($memoryFrame, $frameStack, $var);
        $memoryFrame->setVariableValue($varFrame, $varName, $char);
    }

    // Helper function to validate and retrieve symbol value
    /**
     * @param string $symb
     * @param string $symbType
     * @param FrameStack $frameStack
     * @param MemoryFrame $memoryFrame
     * @return int|bool|string|null
     * @throws FrameAccessException
     * @throws VariableAccessException
     * @throws OperandTypeException
     */
    function validateAndRetrieveSymbUniversal(string $symb, string $symbType, FrameStack $frameStack, MemoryFrame $memoryFrame): int|bool|string|null
    {
        if ($symbType == 'var') {
            $frame = substr($symb, 0, 2);
            $name = substr($symb, 3);
            if ($frame == 'LF') {
                $currentLocalFrame = $frameStack->top();
                if (!$currentLocalFrame) {
                    throw new FrameAccessException("No local frame is available on the stack.");
                }
                if (!$currentLocalFrame->variableExists($frame, $name)) {
                    throw new VariableAccessException("Variable '$name' is undefined in frame '$frame'.");
                }
                $value = $currentLocalFrame->getVariableValue($frame, $name);
            } else {
                if (!$memoryFrame->variableExists($frame, $name)) {
                    throw new VariableAccessException("Variable '$name' is undefined in frame '$frame'.");
                }
                $value = $memoryFrame->getVariableValue($frame, $name);
            }
            return $value;
        } else {
            return match ($symbType) {
                'int' => (int)$symb,
                'bool' => $symb === 'true',
                'string' => $symb,
                'nil' => null,
                default => throw new OperandTypeException("Invalid symbol type '$symbType'."),
            };
        }
    }

    // STRI2INT <var> <symb1> <symb2>
    /**
     * @param array{
     *      opcode: string,
     *      argTypes: array<string>,
     *      argValues: array<string>
     *  } $instruction
     * @param MemoryFrame $memoryFrame
     * @param FrameStack $frameStack
     * @throws OperandTypeException
     * @throws StringOperationException
     * @throws VariableAccessException
     * @throws FrameAccessException
     */
    function executeStriToIntInstruction(array $instruction, MemoryFrame $memoryFrame, FrameStack $frameStack): void
    {
        $var = $instruction['argValues'][0];
        $symb1 = $instruction['argValues'][1];
        $symb2 = $instruction['argValues'][2];

        // Arguments validation
        $symb1Value = $this->validateAndRetrieveSymbUniversal($symb1, $instruction['argTypes'][1], $frameStack, $memoryFrame);
        $symb2Value = $this->validateAndRetrieveSymbUniversal($symb2, $instruction['argTypes'][2], $frameStack, $memoryFrame);

        // Check the first operand for a string
        if (!is_string($symb1Value)) {
            throw new OperandTypeException("The first operand of STRI2INT instruction must be a string.");
        }

        // Check the second operand for an integer
        if (!is_int($symb2Value)) {
            throw new OperandTypeException("The second operand of STRI2INT instruction must be an integer.");
        }

        // Check the second operand for a valid index
        if ($symb2Value < 0 || $symb2Value >= mb_strlen($symb1Value)) {
            throw new StringOperationException("Index out of bounds in STRI2INT instruction.");
        }

        // Get the character at the specified index
        $char = mb_substr($symb1Value, $symb2Value, 1);

        // Get the Unicode value of the character
        $unicodeValue = mb_ord($char);

        // Save the Unicode value to the variable
        [$varFrame, $varName] = $this->validateAndRetrieveVariable($memoryFrame, $frameStack, $var);
        $memoryFrame->setVariableValue($varFrame, $varName, $unicodeValue);
    }

    // CONCAT <var> <symb1> <symb2>
    /**
     * @param array{
     *      opcode: string,
     *      argTypes: array<string>,
     *      argValues: array<string>
     *  } $instruction
     * @param MemoryFrame $memoryFrame
     * @param FrameStack $frameStack
     * @throws OperandTypeException
     * @throws VariableAccessException
     * @throws FrameAccessException
     */
    public function executeConcat(array $instruction, MemoryFrame $memoryFrame, FrameStack $frameStack): void
    {
        $var = $instruction['argValues'][0];
        $symb1 = $instruction['argValues'][1];
        $symb2 = $instruction['argValues'][2];

        // Arguments validation
        $symb1Value = $this->validateAndRetrieveSymbUniversal($symb1, $instruction['argTypes'][1], $frameStack, $memoryFrame);
        $symb2Value = $this->validateAndRetrieveSymbUniversal($symb2, $instruction['argTypes'][2], $frameStack, $memoryFrame);

        // Check the operands for strings
        if (!is_string($symb1Value) || !is_string($symb2Value)) {
            throw new OperandTypeException("Both operands of CONCAT instruction must be strings.");
        }

        // Concatenate the strings
        $result = $symb1Value . $symb2Value;

        // Save the result to the variable
        [$varFrame, $varName] = $this->validateAndRetrieveVariable($memoryFrame, $frameStack, $var);
        $memoryFrame->setVariableValue($varFrame, $varName, $result);
    }

    // STRLEN <var> <symb>
    /**
     * @param array{
     *      opcode: string,
     *      argTypes: array<string>,
     *      argValues: array<string>
     *  } $instruction
     * @param MemoryFrame $memoryFrame
     * @param FrameStack $frameStack
     * @throws OperandTypeException
     * @throws VariableAccessException
     * @throws FrameAccessException
     */
    public function executeStrlen(array $instruction, MemoryFrame $memoryFrame, FrameStack $frameStack): void
    {
        $var = $instruction['argValues'][0];
        $symb = $instruction['argValues'][1];

        // Arguments validation
        $symbValue = $this->validateAndRetrieveSymbUniversal($symb, $instruction['argTypes'][1], $frameStack, $memoryFrame);

        // Check the operand for a string
        if (!is_string($symbValue)) {
            throw new OperandTypeException("The operand of STRLEN instruction must be a string.");
        }

        // Get the length of the string
        $result = mb_strlen($symbValue);

        // Save the result to the variable
        [$varFrame, $varName] = $this->validateAndRetrieveVariable($memoryFrame, $frameStack, $var);
        $memoryFrame->setVariableValue($varFrame, $varName, $result);
    }

    // GETCHAR <var> <symb1> <symb2>
    /**
     * @param array{
     *      opcode: string,
     *      argTypes: array<string>,
     *      argValues: array<string>
     *  } $instruction
     * @param MemoryFrame $memoryFrame
     * @param FrameStack $frameStack
     * @throws OperandTypeException
     * @throws StringOperationException
     * @throws VariableAccessException
     * @throws FrameAccessException
     */
    public function executeGetChar(array $instruction, MemoryFrame $memoryFrame, FrameStack $frameStack): void
    {
        $var = $instruction['argValues'][0];
        $symb1 = $instruction['argValues'][1];
        $symb2 = $instruction['argValues'][2];

        // Arguments validation
        $symb1Value = $this->validateAndRetrieveSymbUniversal($symb1, $instruction['argTypes'][1], $frameStack, $memoryFrame);
        $symb2Value = $this->validateAndRetrieveSymbUniversal($symb2, $instruction['argTypes'][2], $frameStack, $memoryFrame);

        // Check the first operand for a string
        if (!is_string($symb1Value)) {
            throw new OperandTypeException("The first operand of GETCHAR instruction must be a string.");
        }

        // Check the second operand for an integer
        if (!is_int($symb2Value)) {
            throw new OperandTypeException("The second operand of GETCHAR instruction must be an integer.");
        }

        // Check the second operand for a valid index
        if ($symb2Value < 0 || $symb2Value >= mb_strlen($symb1Value)) {
            throw new StringOperationException("Index out of bounds in GETCHAR instruction.");
        }

        // Get the character at the specified index
        $char = mb_substr($symb1Value, $symb2Value, 1);

        // Save the character to the variable
        [$varFrame, $varName] = $this->validateAndRetrieveVariable($memoryFrame, $frameStack, $var);
        $memoryFrame->setVariableValue($varFrame, $varName, $char);
    }

    // SETCHAR <var> <symb1> <symb2>
    /**
     * @param array{
     *      opcode: string,
     *      argTypes: array<string>,
     *      argValues: array<string>
     *  } $instruction
     * @param MemoryFrame $memoryFrame
     * @param FrameStack $frameStack
     * @throws OperandTypeException
     * @throws StringOperationException
     * @throws VariableAccessException
     * @throws FrameAccessException
     */
    public function executeSetChar(array $instruction, MemoryFrame $memoryFrame, FrameStack $frameStack): void
    {
        $var = $instruction['argValues'][0];
        $symb1 = $instruction['argValues'][1];
        $symb2 = $instruction['argValues'][2];

        [$varFrame, $varName] = $this->validateAndRetrieveVariable($memoryFrame, $frameStack, $var);
        $varValue = $memoryFrame->getVariableValue($varFrame, $varName);

        // Check the variable for a string
        if (!is_string($varValue)) {
            throw new OperandTypeException("The variable of SETCHAR instruction must be a string.");
        }

        // Arguments validation
        $index = $this->validateAndRetrieveSymbUniversal($symb1, $instruction['argTypes'][1], $frameStack, $memoryFrame);
        $symb2Value = $this->validateAndRetrieveSymbUniversal($symb2, $instruction['argTypes'][2], $frameStack, $memoryFrame);

        // Check the index for an integer
        if (!is_int($index)) {
            throw new OperandTypeException("The first operand of SETCHAR instruction must be a string.");
        }

        // Check the second operand for a string
        if (!is_string($symb2Value)) {
            throw new OperandTypeException("The second operand of SETCHAR instruction must be an integer.");
        }

        // Check the index for a valid index
        if ($index < 0 || $index >= mb_strlen($varValue)) {
            throw new StringOperationException("Index out of bounds in SETCHAR instruction.");
        }

        // Change the character at the specified index
        $varValue[$index] = mb_substr($symb2Value, 0, 1);

        // Save the result to the variable
        $memoryFrame->setVariableValue($varFrame, $varName, $varValue);
    }

    // READ <var> <type>
    /**
     * @param array{
     *      opcode: string,
     *      argTypes: array<string>,
     *      argValues: array<string>
     *  } $instruction
     * @param MemoryFrame $memoryFrame
     * @param FrameStack $frameStack
     * @throws XMLStructureException
     * @throws VariableAccessException
     * @throws FrameAccessException
     */
    public function executeRead(array $instruction, MemoryFrame $memoryFrame, FrameStack $frameStack): void
    {
        $var = $instruction['argValues'][0];
        $type = $instruction['argValues'][1];

        [$varFrame, $varName] = $this->validateAndRetrieveVariable($memoryFrame, $frameStack, $var);

        // Type must be one of int, string, bool
        if (!in_array($type, ['int', 'string', 'bool'])) {
            throw new XMLStructureException("Invalid type for READ instruction.");
        }

        // Read a value from a file using the method of the corresponding object type of the FileInputReader class
        $value = match ($type) {
            'int' =>  $this->input->readInt(),
            'string' => $this->input->readString(),
            'bool' => $this->input->readBool(),
            default => null,
        };

        // Save the value to the variable
        $memoryFrame->setVariableValue($varFrame, $varName, $value);
    }

    // TYPE <var> <symb>
    /**
     * @param array{
     *      opcode: string,
     *      argTypes: array<string>,
     *      argValues: array<string>
     *  } $instruction
     * @param MemoryFrame $memoryFrame
     * @param FrameStack $frameStack
     * @throws OperandTypeException
     * @throws VariableAccessException
     * @throws FrameAccessException
     */
    public function executeType(array $instruction, MemoryFrame $memoryFrame, FrameStack $frameStack): void
    {
        $var = $instruction['argValues'][0];
        $symb = $instruction['argValues'][1];
        $symbType = $instruction['argTypes'][1];

        [$varFrame, $varName] = $this->validateAndRetrieveVariable($memoryFrame, $frameStack, $var);

        $value = $this->validateAndRetrieveSymbUniversal($symb, $symbType, $frameStack, $memoryFrame);

        // Get the type of the value
        if ($symbType == 'var' && $value == null) {
            $type = "";
        }
        else {
            $type = match (gettype($value)) {
                'integer' => 'int',
                'boolean' => 'bool',
                'string' => 'string',
                'NULL' => 'nil',
            };
        }


        $memoryFrame->setVariableValue($varFrame, $varName, $type);
    }

    // WRITE <symb>
    /**
     * @param array{
     *      opcode: string,
     *      argTypes: array<string>,
     *      argValues: array<string>
     *  } $instruction
     * @param MemoryFrame $memoryFrame
     * @param FrameStack $frameStack
     */
    public function executeWrite(array $instruction, MemoryFrame $memoryFrame, FrameStack $frameStack): void
    {
        // Get data from the instruction
        $opcode = $instruction['opcode'];
        $symbType = $instruction['argTypes'][0];
        $symbValue = $instruction['argValues'][0];

        switch ($symbType) {
            case 'bool':
                $this->stdout->writeBool($symbValue === 'true');
                break;
            case 'int':
                $this->stdout->writeInt((int)$symbValue);
                break;
            case 'string':
                $esq_pattern = "/\\\\([0-9]{3})/";
                $symbValue = preg_replace_callback($esq_pattern, function ($mat)
                {
                    return chr(intval($mat[1]));
                }, $symbValue);
                $this->stdout->writeString($symbValue);
                break;
            case 'nil':
                $this->stdout->writeString('');
                break;
            case 'var':
                $value = $this->validateAndRetrieveSymb($symbValue, $symbType, $frameStack, $memoryFrame, $opcode);
                if (is_bool($value))
                    $this->stdout->writeBool($value);
                if (is_int($value))
                    $this->stdout->writeInt($value);
                if (is_string($value)) {
                    // Replace escape sequences
                    $esq_pattern = "/\\\\([0-9]{3})/";
                    $value = preg_replace_callback($esq_pattern, function ($mat) {
                        return chr(intval($mat[1]));
                    }, $value);
                    $this->stdout->writeString($value);
                }
                break;
            default:
                break;
        }
    }

    // LABEL <label>
    /**
     * @param array<string> $args
     * @param MemoryFrame $memoryFrame
     * @throws InterpretSemanticException
     */
    public function executeLabel(array $args, MemoryFrame $memoryFrame): void
    {
        $labelName = $args[0];
        if ($memoryFrame->labelExists($labelName)) {
            throw new InterpretSemanticException("Label already exist for label '$labelName'.");
        }
        $memoryFrame->addLabel($labelName);
    }

    // JUMP <label>
    /**
     * @param array{
     *      opcode: string,
     *      argTypes: array<string>,
     *      argValues: array<string>
     *  } $instruction
     * @param MemoryFrame $memoryFrame
     * @param array<array{
     *      opcode: string,
     *      argTypes: array<string>,
     *      argValues: array<string>
     *  }> $instructions
     * @return int
     * @throws InterpretSemanticException
     */
    public function executeJump(array $instruction, MemoryFrame $memoryFrame, array $instructions): int
    {
        $labelName = $instruction['argValues'][0];
        if (!$memoryFrame->labelExists($labelName)) {
            throw new InterpretSemanticException("Label '$labelName' not found.");
        }
        // Return the index of the label instruction in the instructions array
        return $memoryFrame->getLabelIndex($labelName, $instructions);
    }


    // JUMPIFEQ <label> <symb1> <symb2>
    // JUMPIFNEQ <label> <symb1> <symb2>
    /**
     * @param array{
     *      opcode: string,
     *      argTypes: array<string>,
     *      argValues: array<string>
     *  } $instruction
     * @param MemoryFrame $memoryFrame
     * @param FrameStack $frameStack
     * @param array<array{
     *      opcode: string,
     *      argTypes: array<string>,
     *      argValues: array<string>
     *  }> $instructions
     * @param int $i
     * @return int
     * @throws InterpretSemanticException
     * @throws OperandTypeException
     * @throws VariableAccessException
     * @throws FrameAccessException
     */
    public function executeJumpIf_Instruction(array $instruction, MemoryFrame $memoryFrame, FrameStack $frameStack, array $instructions, int $i): int
    {
        $opcode = $instruction['opcode'];
        $labelName = $instruction['argValues'][0];
        if (!$memoryFrame->labelExists($labelName)) {
            throw new InterpretSemanticException("Label '$labelName' not found.");
        }

        $symb1 = $instruction['argValues'][1];
        $symb2 = $instruction['argValues'][2];

        // Arguments validation
        $symb1Value = $this->validateAndRetrieveSymbUniversal($symb1, $instruction['argTypes'][1], $frameStack, $memoryFrame);
        $symb2Value = $this->validateAndRetrieveSymbUniversal($symb2, $instruction['argTypes'][2], $frameStack, $memoryFrame);

        if (gettype($symb1Value) !== gettype($symb2Value)) {
            throw new OperandTypeException("Comparison operands must be of the same type.");
        }

        if ($opcode === 'JUMPIFEQ') {
            if (($symb1Value === $symb2Value) || ($symb1Value === null || $symb2Value === null)) {
                return $this->executeJump($instruction, $memoryFrame, $instructions);
            } else
                return $i;
        }
        else // JUMPIFNEQ
        {
            if (($symb1Value !== $symb2Value) && ($symb1Value !== null && $symb2Value !== null)) {
                return $this->executeJump($instruction, $memoryFrame, $instructions);
            } else
                return $i;
        }
    }

    // EXIT <symb>
    /**
     * @param array{
     *      opcode: string,
     *      argTypes: array<string>,
     *      argValues: array<string>
     *  } $instruction
     * @throws OperandValueException
     * @throws OperandTypeException
     */
    public function executeExit(array $instruction): void
    {
        $symb = $instruction['argValues'][0];
        if (ctype_digit($symb)){
            if ($symb >= 0 && $symb <= 9) {
                exit((int)$symb);
            }
            else {
                throw new OperandValueException("Invalid exit code value '$symb'.");
            }
        }
        else {
            throw new OperandTypeException("Invalid exit code '$symb'.");
        }
    }

    // DPRINT <symb>
    /**
     * @param array{
     *      opcode: string,
     *      argTypes: array<string>,
     *      argValues: array<string>
     *  } $instruction
     * @param MemoryFrame $memoryFrame
     * @param FrameStack $frameStack
     * @throws OperandTypeException
     * @throws VariableAccessException
     * @throws FrameAccessException
     */
    public function executeDprint(array $instruction, MemoryFrame $memoryFrame, FrameStack $frameStack): void
    {
        $symb = $instruction['argValues'][0];
        $symbType = $instruction['argTypes'][0];

        $value = $this->validateAndRetrieveSymbUniversal($symb, $symbType, $frameStack, $memoryFrame);

        if (is_bool($value))
            $this->stdout->writeBool($value);
        if (is_int($value))
            $this->stdout->writeInt($value);
        if (is_string($value)) {
            $esq_pattern = "/\\\\([0-9]{3})/";
            $value = preg_replace_callback($esq_pattern, function ($mat) {
                return chr(intval($mat[1]));
            }, $value);
            $this->stdout->writeString($value);
        }
    }

    // BREAK
    /**
     * BREAK instruction - Prints the state of the interpreter to stderr
     *
     * @param InterpreterState $interpreterState Current state of the interpreter
     */
    public function executeBreak(InterpreterState $interpreterState): void
    {
        $this->stderr->writeString("Interpreter State:\n\n");
        $this->stderr->writeString( "Code Position: ");
        $this->stderr->writeString($interpreterState->getCodePosition() . "\n");
        $this->stderr->writeString("Total number of instructions: ");
        $this->stderr->writeString($interpreterState->getExecutedInstructionsCount() . "\n");
    }
}



