**Implementační dokumentace k 2. úloze do IPP 2023/2024** <br>
**Jméno a příjmení:** Veranika Saltanava <br>
**Login:** xsalta01 <br>

# Úvod
Tato implementační dokumentace popisuje návrh, implementaci a filozofii řešení skriptu interpret.php pro 2. úlohu v rámci předmětu IPP. Skript interpret.php je implementován v jazyce PHP a slouží k interpretaci kódu ve formátu XML reprezentujícím instrukční pás. Cílem skriptu je načíst a vykonat instrukce podle zadaného XML souboru a výstup předat na standardní výstup.

# Filozofie návrhu

Filozofie návrhu skriptu interpret.php se zakládá na modularitě, čitelnosti a údržbě. Každá část kódu je rozdělena do samostatných funkcí, které mají jasně definovaný účel a odpovědnost. Komentáře jsou použity k dokumentaci kódu a k vysvětlení specifických částí implementace.


# Interní reprezentace

Interní reprezentace programu je založena na třídách a objektech, které reprezentují jednotlivé části instrukčního pásu, paměťové rámy a interpret. Základní třídou je `Interpreter`, která zajišťuje načtení XML souboru, interpretaci instrukcí a správu paměťových rámců.

Interně jsou instrukce reprezentovány pomocí asociativního pole, kde klíče odpovídají jednotlivým atributům instrukce (např. opcode, argTypes, argValues). Pro uchovávání proměnných a paměťových rámců je použita třída `MemoryFrame`, která umožňuje přidávat, mazat a upravovat proměnné v rámci různých paměťových rámců. Paměťové rámce udržují stav proměnných a jejich hodnot v různých částech programu. Instrukční pole obsahuje všechny instrukce programu, které jsou postupně vykonávány interpretem.


# Specifický postup řešení

- Pro implementaci jednotlivých instrukcí byly vytvořeny odpovídající metody v třídě `executeInstruction`.
- Pro validaci a zpracování argumentů instrukcí byly vytvořeny pomocné metody, které zajišťují kontrolu typů argumentů a získání jejich hodnot.
- Využití tříd `MemoryFrame` a `FrameStack` pro správu paměťových rámců a zásobníku rámců.
- Implementace třídy `FileInputReader` pro načítání vstupních dat ze souboru.
- Využití výjimek pro správu chyb a neočekávaných situací.

# UML diagram tříd

![UML diagram tříd](uml_diagram.png)


