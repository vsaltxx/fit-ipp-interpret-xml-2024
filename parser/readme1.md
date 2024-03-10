**Implementační dokumentace k 1. úloze do IPP 2023/2024** <br>
**Jméno a příjmení:** Veranika Saltanava <br>
**Login:** xsalta01 <br>

# Python Script Documentation: parse.py

## Introduction
`parse.py` is a Python script designed to parse input in the IPPCode24 format and create 
an XML representation of it. 

## Input Format
The script expects input in the form of IPPCode24. Each line represents a single instruction.

## Output Format
The output of the script is an XML representation of the IPPCode24 instructions. The XML format is as follows:
```
<?xml version="1.0" encoding="UTF-8"?>
<program language="IPPcode24">
  <!-- Instructions go here -->
</program>
```

## Usage
The script can be executed from the command line using the following syntax:
``` bash 
python3.10 parse.py [options] < [file]
```
- `[options]`: Optional command-line options.
- `[file]`: Input file containing IPPCode24 code to be parsed.

Default options:
- `--help`: Prints help information.

## Functionality
The script performs the following main tasks:
1. Reads IPPCode24 input from stdin.
2. Removes comments from the input.
3. Checks the header of the input for correctness.
4. Parses each instruction from the input.
5. Validates the syntax and semantics of the instructions.
6. Generates an XML representation of the input.

## Error Codes
The script may exit with the following error codes:
- `ERROR_MISSING_PARAMETER (10)`: Missing command-line parameter.
- `ERROR_HEADER_MISSING (21)`: Missing or incorrect header in the input.
- `ERROR_UNKNOWN_OPERATOR (22)`: Unknown operator in the input.
- `ERROR_LEXICAL_OR_SYNTAX (23)`: Lexical or syntax error in the input.

## Functions
1. `help()`: Displays help information if requested.
2. `read_input()`: Reads input from stdin.
3. `remove_comments(lines)`: Removes comments from input lines.
4. `check_header(lines)`: Checks the header of the input for correctness.
5. `parse_instruction(lines)`: Parses each instruction from input lines.
6. `check_operators(operators)`: Checks for unknown operators in the input.
7. `check_allowed_number_of_operator_operands(operators, operands)`: Validates the number of operands for each operator.
8. `validate_string(string)`: Validates the format of string operands.
9. `remove_leading_zeros_and_sign(number_str)`: Removes leading zeros and sign from number operands.
10. `validate_format(number_str)`: Validates the format of number operands.
11.validate_instruction(operator, arg_number, type_, help_type): Validates the syntax and semantics of each instruction based on its operator and operands.
12. `generate_xml(operators, operands)`: Generates an XML representation of parsed instructions.
13. `escape_xml(string)`: Escapes special characters in XML strings.

## Main Function
`main()`: Orchestrates the execution of the script by calling the above functions in sequence.

## Dependencies
The script requires Python 3.x to run.
