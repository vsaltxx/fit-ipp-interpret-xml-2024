import sys
import re

def help():
    if '--help' in sys.argv and len(sys.argv) == 2:
        print("Nápověda pro tento skript...")
        sys.exit(0)
    elif '--help' in sys.argv and len(sys.argv) > 2:
        print("10")
        sys.exit(10)  # Chyba 10 - chybné parametry skriptu

def read_input():
    lines = []
    for line in sys.stdin:
        lines.append(line.strip())
    return lines

def check_header(lines):
    if not lines:
        print("Chybi zdrojovy kod\n21")
        sys.exit(21)
    header = lines[0].strip()
    if header != ".IPPcode24":
        print("Chybna nebo chybejici hlavicka.\n21")
        sys.exit(21)
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
            print(f"Neplatný operátor: {operator}\n22")
            sys.exit(22)  # Chybový kód 22 - neznámý nebo chybný operátor

def check_operands(operands):
    for operand_list in operands:
        for operand in operand_list:        
            if operand.startswith("GF@") or operand.startswith("LF@") or operand.startswith("TF@"):
                # Operand je proměnná
                if not re.match(r'^[A-Za-z_\-$&%*!?][A-Za-z0-9_\-$&%*!?]*$', operand[3:]):
                    print(f"Neplatný formát proměnné: {operand}\n23")
                    sys.exit(23)  # Chybový kód 23 - lexikální nebo syntaktická chyba
            elif operand.startswith("int@") or operand.startswith("bool@") or operand.startswith("string@") or operand.startswith("nil@"):
                # Operand je literál
                if operand.count('@') != 1:
                    print(f"Neplatný formát literálu: {operand}\n23")
                    sys.exit(23)  # Chybový kód 23 - lexikální nebo syntaktická chyba
            elif operand.startswith("label@"):
                if not re.match(r'^[A-Za-z_\-$&%*!?][A-Za-z0-9_\-$&%*!?]*$', operand[6:]):
                    print(f"Neplatný formát návěští: {operand}")
                    sys.exit(23)  # Chybový kód 23 - lexikální nebo syntaktická chyba
            elif operand.startswith("type@"):
                if not re.match(r'^[A-Za-z_\-$&%*!?][A-Za-z0-9_\-$&%*!?]*$', operand[5:]):
                    print(f"Neplatný formát typu: {operand}\n23")
                    sys.exit(23)
            else:
                print(f"Neplatný operand: {operand}\n23")
                sys.exit(23)  

def generate_xml(operators, operands):
    xml_output = '<?xml version="1.0" encoding="UTF-8"?>\n'
    xml_output += '<program language="IPPcode24">\n'
    
    for i, operator in enumerate(operators):
        xml_output += f'  <instruction order="{i+1}" opcode="{operator}">\n'
        for operand in operands[i]:
            type_, value = operand.split('@', 1)
            xml_output += f'    <arg1 type="{type_}">{escape_xml(value)}</arg1>\n'
        xml_output += '  </instruction>\n'
    
    xml_output += '</program>'
    return xml_output

def escape_xml(string):
    return string.replace('&', '&amp;').replace('<', '&lt;').replace('>', '&gt;')




def main():

    help()
    code_lines = read_input()
    check_header(code_lines)
    operators, operands = parse_instruction(code_lines)
    check_operators(operators)
    check_operands(operands)
    xml_output = generate_xml(operators, operands)
    print(xml_output)




if __name__ == "__main__":
    main()


