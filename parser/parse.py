import sys
import re

ERROR_MISSING_PARAMETER = 10
ERROR_HEADER_MISSING = 21
ERROR_UNKNOWN_OPERATOR = 22
ERROR_LEXICAL_OR_SYNTAX = 23

type_ = ""
value = ""

# TODO add description
def help():
    if '--help' in sys.argv and len(sys.argv) == 2:
        print("ADD TEXT") #TODO
        sys.exit(0)
    elif '--help' in sys.argv and len(sys.argv) > 2:
        sys.exit(ERROR_MISSING_PARAMETER) 


def read_input():
    lines = []
    for line in sys.stdin:
        lines.append(line.strip())
    return lines


def remove_comments(lines):
    cleaned_lines = []
    for line in lines:
        line = line.split('#', 1)[0].strip()
        if line:
            cleaned_lines.append(line)
    return cleaned_lines


def check_header(lines):
    if not lines:
        sys.exit(ERROR_HEADER_MISSING)
    header = lines[0].strip()
    if header != ".IPPcode24":
        sys.exit(ERROR_HEADER_MISSING)

    if len(lines) > 1:
        if header == lines[1].strip():
            sys.exit(ERROR_LEXICAL_OR_SYNTAX)
    del lines[0]
    
    
def parse_instruction(lines):
    operators = []
    operands = []
    for line in lines:
        parts = line.split()

        if parts:
            operator = parts[0]
            operators.append(operator.upper()) 
            
            if len(parts) > 1:
                operands.append(parts[1:])
            else:
                operands.append([])
        else:
            continue

    return operators, operands


def check_operators(operators):
    valid_operators = [
        "DEFVAR", "MOVE", "CREATEFRAME", "PUSHFRAME", "POPFRAME",
        "CALL", "RETURN", "PUSHS", "POPS", "ADD", "SUB", "MUL", "IDIV", 
        "LT", "GT", "EQ", "AND", "OR", "NOT", "INT2CHAR", "STRI2INT", 
        "READ", "WRITE", "CONCAT", "STRLEN", "GETCHAR", "SETCHAR", "TYPE",
        "LABEL", "JUMP", "JUMPIFEQ", "JUMPIFNEQ", "EXIT", "DPRINT", "BREAK"
    ]
    for operator in operators:
        if operator not in valid_operators:
            sys.exit(ERROR_UNKNOWN_OPERATOR)  


def check_allowed_numper_of_operator_operands(operators, operands):
    for operand_list, operator in zip(operands, operators):

        if (operator == 'ADD' or 
            operator == 'SUB' or
            operator == 'MUL' or
            operator == 'IDIV' or  
            operator == 'LT' or 
            operator == 'GT' or 
            operator == 'EQ' or 
            operator == 'AND' or 
            operator == 'OR' or
            operator == 'STRING2INT' or
            operator == 'CONCAT' or
            operator == 'GETCHAR' or
            operator == 'SETCHAR' or
            operator == 'JUMPIFEQ' or
            operator == 'JUMPIFNEQ') and (len(operand_list) != 3):
            sys.exit(ERROR_LEXICAL_OR_SYNTAX)
        

        if (operator == 'NOT' or 
            operator == 'MOVE' or
            operator == 'INT2CHAR' or 
            operator == 'READ' or
            operator == 'STRLEN' or
            operator == 'TYPE') and (len(operand_list) != 2):
            sys.exit(ERROR_LEXICAL_OR_SYNTAX)
        

        if (operator == 'DEFVAR' or 
            operator == 'CALL' or
            operator == 'PUSH' or
            operator == 'POP' or
            operator == 'WRITE' or
            operator == 'LABEL' or
            operator == 'JUMP' or
            operator == 'EXIT' or
            operator == 'DPRINT') and (len(operand_list) != 1):
            sys.exit(ERROR_LEXICAL_OR_SYNTAX)
        

        if (operator == 'CREATEFRAME' or 
            operator == 'PUSHFRAME' or
            operator == 'POPFRAME' or
            operator == 'RETURN' or
            operator == 'BREAK') and (len(operand_list) != 0):
            sys.exit(ERROR_LEXICAL_OR_SYNTAX)
        

def validate_string(string):

    if "\\" in string:

        escape_pattern = r'\\[0-9]{3}'
        escape_sequences = re.findall(escape_pattern, string)

        if not escape_sequences:
            return False

        for escape_seq in escape_sequences:
            
            num = int(escape_seq[1:])
            if 0 <= num <= 32 or num == 35 or num == 92:
                continue  
            else:
                return False  
    return True  


def remove_leading_zeros_and_sign(number_str):

    is_negative = False
    if number_str.startswith('-'):
        is_negative = True
        number_str = number_str[1:]

    if number_str.startswith('0o') or number_str.startswith('0x'):

        new_number_str = number_str[2:]
        new_number_str = new_number_str.lstrip('0')

        if not new_number_str or new_number_str == '0x' or new_number_str == '0o':
            return '0'

        if  number_str.startswith('0o'):
            number_str = '0o' + new_number_str

        if  number_str.startswith('0x'):
            number_str = '0x' + new_number_str
    else:
        number_str = number_str.lstrip('0')
        if not number_str:
            return '0'

    if is_negative:
        number_str = '-' + number_str

    return number_str


def validate_format(number_str):

    number_str = remove_leading_zeros_and_sign(number_str)

    if re.match(r'^(0|-?[1-9]\d*)$', number_str):  # Десятичная система
        return True
    elif re.match(r'^-?0x[0-9A-Fa-f]+$', number_str):  # Шестнадцатеричная система
        return True
    elif re.match(r'^-?0o[0-7]+$', number_str):  # Восьмеричная система
        return True
    else:
        return False
    

def generate_xml(operators, operands):
    xml_output = '<?xml version="1.0" encoding="UTF-8"?>\n'
    xml_output += '<program language="IPPcode24">\n'
    for i, operator in enumerate(operators):
        xml_output += f'  <instruction order="{i+1}" opcode="{operator}">\n'
        arg_number = 0
        for operand in operands[i]:
            
            if operand.startswith("GF@") or operand.startswith("LF@") or operand.startswith("TF@"):

                if not re.match(r'^[A-Za-z_\-$&%*!?][A-Za-z0-9_\-$&%*!?]*$', operand[3:]):
                    sys.exit(ERROR_LEXICAL_OR_SYNTAX)
                if operand.count('@') != 1:
                    sys.exit(ERROR_LEXICAL_OR_SYNTAX)
                type_ = 'var'
                value = operand 


            elif operand.startswith("int@") or operand.startswith("bool@") or operand.startswith("string@") or operand.startswith("nil@"):

                type_, value = operand.split('@', 1)

                if type_ == 'int':
                    if not validate_format(value):
                        sys.exit(ERROR_LEXICAL_OR_SYNTAX) 

                if type_ == 'string':
                    if not validate_string(value):
                        sys.exit(ERROR_LEXICAL_OR_SYNTAX)             

            elif operator == 'LABEL' or operator == 'CALL' or operator == 'JUMP' or  operator == 'JUMPIFEQ' or operator == 'JUMPIFNEQ':
                type_ = 'label'
                value = operand
                
                    
            elif operand.startswith("int") or operand.startswith("bool") or operand.startswith("string") or operand.startswith("nil"):
                type_ = 'type'
                value = operand

            else:
                sys.exit(ERROR_LEXICAL_OR_SYNTAX)  

             
            if (operator == 'POPS' and type_ != 'var'):
                sys.exit(ERROR_LEXICAL_OR_SYNTAX)  

            if (operator == 'READ' and arg_number == 0 and type_ != 'var') or (operator == 'READ' and arg_number == 1 and type_ != 'type'):
                sys.exit(ERROR_LEXICAL_OR_SYNTAX)  


            if ((operator == 'PUSHS') and (type_ == 'type')):
                sys.exit(ERROR_LEXICAL_OR_SYNTAX)  


            arg_number +=  1
            xml_output += f'    <arg{arg_number} type="{type_}">{escape_xml(value)}</arg{arg_number}>\n'
        xml_output += '  </instruction>\n'
    
    xml_output += '</program>'
    return xml_output


def escape_xml(string):
    return string.replace('&', '&amp;').replace('<', '&lt;').replace('>', '&gt;')



def main():

    help()
    code_lines = read_input()
    
    code_lines = remove_comments(code_lines)
    
    check_header(code_lines)
    operators, operands = parse_instruction(code_lines)
    check_operators(operators)

    check_allowed_numper_of_operator_operands(operators, operands)
    xml_output = generate_xml(operators, operands)
    print(xml_output)




if __name__ == "__main__":
    main()