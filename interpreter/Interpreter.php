<?php

/**
 * @file Interpreter.php
 * @author Veranika Saltanava(xsalta01)
 */

namespace IPP\Student;

use IPP\Core\AbstractInterpreter;
use IPP\Student\Exception\FrameAccessException;
use IPP\Student\Exception\InterpretSemanticException;
use IPP\Student\Exception\MissingValueException;
use IPP\Student\Exception\OperandTypeException;
use IPP\Student\Exception\OperandValueException;
use IPP\Student\Exception\StringOperationException;
use IPP\Student\Exception\VariableAccessException;
use IPP\Student\Exception\XMLStructureException;

class Interpreter extends AbstractInterpreter
{
    /**
     * Array for storing instructions and their arguments
     *
     * @var array<array{
     *     opcode: string,
     *     argTypes: array<string>,
     *     argValues: array<string>
     * }>
     */
    private array $instructions = [];
    // Index of the current instruction being executed
    private int $currentInstruction = 0;


    /**
     * Adds an instruction to the array of instructions.
     *
     * @param string $opcode
     * @param array<string> $argTypes
     * @param array<string> $argValues
     * @return void
     */
    public function addInstruction(string $opcode, array $argTypes, array $argValues): void
    {
        $this->instructions[$this->currentInstruction++] = [
            'opcode'    => $opcode,
            'argTypes'  => $argTypes,
            'argValues' => $argValues
        ]; // Update the index of the current instruction
    }


    /**
     * Collects instructions in the first iteration of the program.
     *
     * @param \DOMElement $instruction
     * @param MemoryFrame $memoryFrame
     * @param ExecuteInstruction $executor
     * @return void
     * @throws XMLStructureException
     * @throws InterpretSemanticException
     */
    private function collectInstructions(\DOMElement $instruction, MemoryFrame $memoryFrame, ExecuteInstruction $executor): void
    {
        $opcode = $instruction->getAttribute('opcode');
        $argTypes = [];
        $args = [];

        // loop through all the elements <arg1>, <arg2>, <arg3>, ... inside the instruction
        for ($i = 1; ; $i++) {
            $argElement = $instruction->getElementsByTagName("arg$i")->item(0);
            if (!$argElement) {
                break; // If there is no more <arg> elements, break the loop
            }
            $argTypes[] = $argElement->getAttribute('type');
            $args[] = $argElement->textContent;
        }


        // if the instruction is LABEL, process it immediately
        if ($opcode == 'LABEL'){
            $executor->executeLabel($args, $memoryFrame);
        }

        $this->addInstruction($opcode, $argTypes, $args);
    }


    /**
     * Executes the program.
     *
     * @return int
     * @throws XMLStructureException
     * @throws InterpretSemanticException
     * @throws VariableAccessException
     * @throws OperandValueException
     * @throws OperandTypeException
     * @throws FrameAccessException
     * @throws MissingValueException
     * @throws VariableAccessException
     * @throws StringOperationException
 */
    public function execute(): int
    {
        $executor = new ExecuteInstruction($this->stdout, $this->stderr, $this->input);
        $memoryFrame = new MemoryFrame();
        $frameStack = new FrameStack();
        $callStack = new CallStack();
        $dataStack = new DataStack();


        $dom = $this->source->getDOMDocument();
        $instructions_dom = $dom->getElementsByTagName('instruction');

        // Collect all instructions in the first iteration
        foreach ($instructions_dom as $instruction) {
            $executor->executeInstruction(); // Increment the count of executed instructions
            $this->collectInstructions($instruction, $memoryFrame, $executor);
        }

        for ($i = 0; $i < count($this->instructions); $i++) {

            $instruction = $this->instructions[$i];
            if ($instruction['opcode'] === 'JUMP') {
                $i = $executor->executeJump($instruction, $memoryFrame, $this->instructions);
            }
           else if ($instruction['opcode'] === 'JUMPIFEQ' || $instruction['opcode'] === 'JUMPIFNEQ') {
                $i = $executor->executeJumpIf_Instruction($instruction, $memoryFrame, $frameStack, $this->instructions, $i);
           }

            else {
                switch ($instruction['opcode']) {

                    case 'CREATEFRAME':
                        $executor->executeCreateframe($memoryFrame);
                        break;
                    case 'DEFVAR':
                        $executor->executeDefVar($instruction, $memoryFrame);
                        break;
                    case 'MOVE':
                        $executor->executeMove($instruction, $memoryFrame);
                        break;
                    case 'PUSHFRAME':
                        $executor->executePushFrame($memoryFrame, $frameStack);
                        break;
                    case 'POPFRAME':
                        $executor->executePopFrame($memoryFrame, $frameStack);
                        break;
                    case "CALL":
                        $i = $executor->executeCall($instruction, $memoryFrame, $callStack, $this->instructions, $i);
                        break;
                    case 'RETURN':
                        $i = $executor->executeReturn($callStack);
                        break;
                    case 'PUSHS':
                        $executor->executePushs($instruction, $memoryFrame, $dataStack);
                        break;
                    case 'POPS':
                        $executor->executePops($instruction, $memoryFrame, $dataStack);
                        break;
                    case 'ADD':
                    case 'SUB':
                    case 'MUL':
                    case 'IDIV':
                        $executor->executeArithmeticInstruction($instruction, $memoryFrame, $frameStack);
                        break;
                    case 'LT':
                    case 'GT':
                    case 'EQ':
                        $executor->executeComparisonInstruction($instruction, $memoryFrame, $frameStack);
                        break;
                    case 'AND':
                    case 'OR':
                    case 'NOT':
                        $executor->executeBooleanInstruction($instruction, $memoryFrame, $frameStack);
                        break;
                    case 'INT2CHAR':
                        $executor->executeIntToCharInstruction($instruction, $memoryFrame, $frameStack);
                        break;
                    case 'STRI2INT':
                        $executor->executeStriToIntInstruction($instruction, $memoryFrame, $frameStack);
                        break;
                    case 'TYPE':
                        $executor->executeType($instruction, $memoryFrame, $frameStack);
                        break;
                    case 'CONCAT':
                        $executor->executeConcat($instruction, $memoryFrame, $frameStack);
                        break;
                    case 'STRLEN':
                        $executor->executeStrlen($instruction, $memoryFrame, $frameStack);
                        break;
                    case 'GETCHAR':
                        $executor->executeGetChar($instruction, $memoryFrame, $frameStack);
                        break;
                    case 'SETCHAR':
                        $executor->executeSetChar($instruction, $memoryFrame, $frameStack);
                        break;
                    case 'READ':
                        $executor->executeRead($instruction, $memoryFrame, $frameStack);
                        break;
                    case 'LABEL':
                        break;
                    case 'WRITE':
                        $executor->executeWrite($instruction, $memoryFrame, $frameStack);
                        break;
                    case 'EXIT':
                        $executor->executeExit($instruction);
                        break;
                    case 'DPRINT':
                        $executor->executeDprint($instruction, $memoryFrame, $frameStack);
                        break;
                    case 'BREAK':
                        $interpreterState = new InterpreterState($i, $executor->getExecutedInstructionsCount());
                        $executor->executeBreak($interpreterState);
                        exit(0);
                    default:
                        throw new XMLStructureException("Unknown instruction: " . $instruction['opcode']);
                }
            }
        }
        return 0;
    }
}
