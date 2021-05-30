<?php
    /**
     *   parser.php
     *   Ales Jaksik, xjaksi01
     */

    ini_set('display_errors', 'stderr');

    // zjisteni zda help
    $argHelp = getopt("h", ["help"]);
    // pokud tam je JEN help nebo h a nic jineho
    if ($argHelp) {
        if ($argc == 2) {
            if (array_key_exists('help', $argHelp)) {
                callHelp();
            }
            elseif(array_key_exists('h', $argHelp)) {
                callHelp();
            }
            else {
                exit(10);
            }
        }
        else {
            exit(10);
        }
    }
    
    loadLines();
    


    /**
     * Nacteni souboru a volani kontroly pro kazdy radek
     * + kontrola prvniho radku
     */
    function loadLines(){
        // ziskani souboru ze standartniho vztupu a vypusteni prazdnych radek
        $soubor = file("php://stdin", FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        //kontrola otevreni
        if (! $soubor) 
        {
            exit(11);   // chyba pri otevirani vstupnich souboru
        }

        // pocet radku
        $noLines = count($soubor);
        $i = 0;
        $firstLine = $soubor[$i];

        // hledani jestli se hlavicka v souboru vubec nachazi
        if (!preg_grep('/\A\s*\.ippcode21/i', $soubor)) 
        {
            exit(21);
        }
        
        // preskoceni komentaru pred hlavickou
        while (preg_match('/(\A\s*#.*)/', $firstLine)) 
        {
            $i++;
            $firstLine = $soubor[$i];
        }
        // orezani prvniho radku kde by se mela nachazet hlavicka
        $firstLine = trim($soubor[$i]);
        $i++;
        
        // pokud neni nalezena hlavicka 
        if (!(preg_match('/.ippcode21(\z|\s*#)/i', $firstLine))) 
        {
            exit(21);
        }

        // otevreni zapisu xml souboru
        $xml = xmlwriter_open_memory();
        // zapnuti indentu
        xmlwriter_set_indent($xml, 1);
        // indent = tab
        xmlwriter_set_indent_string($xml, "\t");
        // vypis hlavicky souboru
        xmlwriter_start_document($xml, '1.0', 'UTF-8');
        xmlwriter_start_element($xml, 'program');
        xmlwriter_start_attribute($xml, 'language');
        xmlwriter_text($xml, 'IPPcode21');
        xmlwriter_end_attribute($xml);

        // pro kazdy dalsi radek volam specialni funkci
        $noInstr = 1;
        while ($noLines > $i) 
        {
            if (preg_match('/(\A#.*|\n|"")/', $soubor[$i])) 
            {
                $i++;
            }
            else 
            {
                // echo $soubor[$i], "\n";
                $loadedLine = $soubor[$i];
                $loadedLine = preg_replace('/#.+/', ' ', $loadedLine);
                $loadedLine = trim($loadedLine);
                $loadedLine = preg_replace('/\s+/', ' ', $loadedLine);
               // echo $loadedLine, "\n";
                checkLine($loadedLine, $xml, $noInstr);
                $i++;
                $noInstr++;
            }
        }

        // uzavreni xml dokumentu
        xmlwriter_end_element($xml);
        xmlwriter_end_document($xml);
        // vypis XML na stdout
        echo xmlwriter_output_memory($xml);
    }

    /**
     * Kontrola prvniho znaku na radku,
     * zda je to existujici prikaz
     */
    function checkLine($line, $xml, $i){
        // postarani se o mezery
        $line = trim($line);
        $line = explode(' ', $line);
        // velke pismena
        $first = strtoupper($line[0]);

        // rozhodovani a volani prislusicich kontrol
        switch ($first) {
        // VAR SYMB  
            case 'MOVE' :
            case 'NOT':
            case 'INT2CHAR':
            case 'STRLEN':
            case 'TYPE':
                if (count($line) != 3) {
                    exit(23);
                }
                if (var_symb($line)) {
                    // vypis instrukce  <instruction order="_" opcode="_____">
                    xmlwriter_start_element($xml, "instruction");
                    xmlwriter_start_attribute($xml, "order");
                    xmlwriter_text($xml, $i);
                    xmlwriter_end_attribute($xml);
                    xmlwriter_start_attribute($xml, "opcode");
                    xmlwriter_text($xml, $first);
                    xmlwriter_end_attribute($xml);

                    // vypis prvniho argumentu  <arg1 type="var">
                    xmlwriter_start_element($xml, "arg1");
                    xmlwriter_start_attribute($xml, "type");
                    xmlwriter_text($xml, "var");
                    xmlwriter_end_attribute($xml);
                    // vypis promenne
                    xmlwriter_text($xml, $line[1]);
                    // zavreni elementu arg1 </arg1>
                    xmlwriter_end_element($xml);
                    
                    // vypis druheho argumentu <arg2 type="__">
                    xmlwriter_start_element($xml, "arg2");
                    xmlwriter_start_attribute($xml, "type");
                    // ziskani typu, nalezeny typ je v promenne $printType
                    $printType1 = explode("@", $line[2], 2);
                    if (preg_match('/\A(LF|GF|TF)\z/', $printType1[0])) {
                        xmlwriter_text($xml, "var");
                        xmlwriter_end_attribute($xml);
                        xmlwriter_text($xml, $line[2]);
                    }
                    else {
                        xmlwriter_text($xml, $printType1[0]);
                        // pokud je string prazdny
                        if(empty($printType1[1])) {
                            xmlwriter_end_attribute($xml);
                        }
                        // pokud je tam ', musi se prevest
                        if (preg_match('/\'/', $printType1[1])) {
                            $replaced = preg_replace('/\'/', "&apos;", $printType1[1]);
                            xmlwriter_end_attribute($xml);
                            xmlwriter_text($xml, $replaced);
                        }
                        else {
                            xmlwriter_end_attribute($xml);
                            xmlwriter_text($xml, $printType1[1]);
                        }  
                    }
                    // zavreni elementu arg2
                    xmlwriter_end_element($xml);

                    // zavreni elementu instruction </instruction>
                    xmlwriter_end_element($xml);
                }
                else {
                    exit(23);
                }
                break;

        // bez pokracovani
            case 'CREATEFRAME':
            case 'PUSHFRAME':
            case 'POPFRAME':
            case 'RETURN':
            case 'BREAK':
                if (count($line) != 1) {
                    exit(23);
                }
                if (justthis($line)) {
                    // vypis instrukce  <instruction order="_" opcode="_____">
                    xmlwriter_start_element($xml, "instruction");
                    xmlwriter_start_attribute($xml, "order");
                    xmlwriter_text($xml, $i);
                    xmlwriter_end_attribute($xml);
                    xmlwriter_start_attribute($xml, "opcode");
                    xmlwriter_text($xml, $first);
                    xmlwriter_end_attribute($xml);
                    xmlwriter_end_element($xml);
                }
                break;
            
        // VAR
            case 'DEFVAR':
            case 'POPS':
                if (count($line) != 2) {
                    exit(23);
                }
                if (var_only($line)) {
                    xmlwriter_start_element($xml, "instruction");
                    xmlwriter_start_attribute($xml, "order");
                    xmlwriter_text($xml, $i);
                    xmlwriter_end_attribute($xml);
                    xmlwriter_start_attribute($xml, "opcode");
                    xmlwriter_text($xml, $first);
                    xmlwriter_end_attribute($xml);

                    // vypis prvniho argumentu  <arg1 type="var">
                    xmlwriter_start_element($xml, "arg1");
                    xmlwriter_start_attribute($xml, "type");
                    xmlwriter_text($xml, "var");
                    xmlwriter_end_attribute($xml);
                    // vypis promenne
                    xmlwriter_text($xml, $line[1]);
                    // zavreni elementu arg1 </arg1>
                    xmlwriter_end_element($xml);

                    // zavreni elementu instruction </instruction>
                    xmlwriter_end_element($xml);
                }
                break;

        // LABEL
            case 'CALL':
            case 'LABEL':
            case 'JUMP':
                if (count($line) != 2) {
                    exit(23);
                }
              //   print_r($line);
                if (label_only($line)) {
                    // vypis instrukce  <instruction order="_" opcode="_____">
                    xmlwriter_start_element($xml, "instruction");
                    xmlwriter_start_attribute($xml, "order");
                    xmlwriter_text($xml, $i);
                    xmlwriter_end_attribute($xml);
                    xmlwriter_start_attribute($xml, "opcode");
                    xmlwriter_text($xml, $first);
                    xmlwriter_end_attribute($xml);

                    // vypis prvniho argumentu  <arg1 type="label">
                    xmlwriter_start_element($xml, "arg1");
                    xmlwriter_start_attribute($xml, "type");
                    xmlwriter_text($xml, "label");
                    xmlwriter_end_attribute($xml);
                    // vypis promenne
                    xmlwriter_text($xml, $line[1]);
                    // zavreni elementu arg1 </arg1>
                    xmlwriter_end_element($xml);

                    // zavreni elementu instruction </instruction>
                    xmlwriter_end_element($xml);
                }
                break;

        // SYMB
            case 'PUSHS':
            case 'WRITE':
            case 'EXIT':
            case 'DPRINT':
                if (count($line) != 2) {
                    exit(23);
                }
                if (symb_only($line)) {
                    // vypis instrukce  <instruction order="_" opcode="_____">
                    xmlwriter_start_element($xml, "instruction");
                    xmlwriter_start_attribute($xml, "order");
                    xmlwriter_text($xml, $i);
                    xmlwriter_end_attribute($xml);
                    xmlwriter_start_attribute($xml, "opcode");
                    xmlwriter_text($xml, $first);
                    xmlwriter_end_attribute($xml);
 
                    // vypis druheho argumentu <arg1 type="__">
                    xmlwriter_start_element($xml, "arg1");
                    xmlwriter_start_attribute($xml, "type");
                    // ziskani typu, nalezeny typ je v promenne $printType
                    $printType1 = explode("@", $line[1], 2);
                    if (preg_match('/\A(LF|GF|TF)\z/', $printType1[0])) {
                        xmlwriter_text($xml, "var");
                        xmlwriter_end_attribute($xml);
                        xmlwriter_text($xml, $line[1]);
                    }
                    else {
                        xmlwriter_text($xml, $printType1[0]);
                        xmlwriter_end_attribute($xml);
                        xmlwriter_text($xml, $printType1[1]);
                    }
                    // zavreni elementu arg2
                    xmlwriter_end_element($xml);

                    // zavreni elementu instruction </instruction>
                    xmlwriter_end_element($xml);
                }
                else {
                    exit(23);
                }
                break;

        // VAR TYPE
            case 'READ':
                if (count($line) != 3) {
                    exit(23);
                }
               if (var_type($line)) {
                   // vypis instrukce  <instruction order="_" opcode="_____">
                   xmlwriter_start_element($xml, "instruction");
                   xmlwriter_start_attribute($xml, "order");
                   xmlwriter_text($xml, $i);
                   xmlwriter_end_attribute($xml);
                   xmlwriter_start_attribute($xml, "opcode");
                   xmlwriter_text($xml, $first);
                   xmlwriter_end_attribute($xml);

                   // vypis prvniho argumentu  <arg1 type="var">
                   xmlwriter_start_element($xml, "arg1");
                   xmlwriter_start_attribute($xml, "type");
                   xmlwriter_text($xml, "var");
                   xmlwriter_end_attribute($xml);
                   // vypis promenne
                   xmlwriter_text($xml, $line[1]);
                   // zavreni elementu arg1 </arg1>
                   xmlwriter_end_element($xml);

                   // vypis prvniho argumentu  <arg2 type="type">
                   xmlwriter_start_element($xml, "arg2");
                   xmlwriter_start_attribute($xml, "type");
                   xmlwriter_text($xml, "type");
                   xmlwriter_end_attribute($xml);
                   // vypis promenne
                   xmlwriter_text($xml, $line[2]);
                   // zavreni elementu arg1 </arg1>
                   xmlwriter_end_element($xml);

                   // zavreni elementu instruction </instruction>
                   xmlwriter_end_element($xml);
               }
                break;

        // VAR SYMB1 SYMB2
            case 'ADD':
            case 'SUB':
            case 'MUL':
            case 'IDIV':
            case 'LT':
            case 'GT':
            case 'EQ':
            case 'AND':
            case 'OR':
            case 'STRI2INT':
            case 'CONCAT':
            case 'GETCHAR':
            case 'SETCHAR':
                if (count($line) != 4) {
                    exit(23);
                }
                if (var_symb_symb($line)) {
                    // vypis instrukce  <instruction order="_" opcode="_____">
                    xmlwriter_start_element($xml, "instruction");
                    xmlwriter_start_attribute($xml, "order");
                    xmlwriter_text($xml, $i);
                    xmlwriter_end_attribute($xml);
                    xmlwriter_start_attribute($xml, "opcode");
                    xmlwriter_text($xml, $first);
                    xmlwriter_end_attribute($xml);

                    // vypis prvniho argumentu  <arg1 type="var">
                    xmlwriter_start_element($xml, "arg1");
                    xmlwriter_start_attribute($xml, "type");
                    xmlwriter_text($xml, "var");
                    xmlwriter_end_attribute($xml);
                    // vypis promenne
                    xmlwriter_text($xml, $line[1]);
                    // zavreni elementu arg1 </arg1>
                    xmlwriter_end_element($xml);
                    
                    // vypis druheho argumentu <arg2 type="__">
                    xmlwriter_start_element($xml, "arg2");
                    xmlwriter_start_attribute($xml, "type");
                    // ziskani typu, nalezeny typ je v promenne $printType
                    $printType1 = explode("@", $line[2], 2);
                    if (preg_match('/\A(LF|GF|TF)\z/', $printType1[0])) {
                        xmlwriter_text($xml, "var");
                        xmlwriter_end_attribute($xml);
                        xmlwriter_text($xml, $line[2]);
                    }
                    else {
                        xmlwriter_text($xml, $printType1[0]);
                        xmlwriter_end_attribute($xml);
                        xmlwriter_text($xml, $printType1[1]);
                    }
                    // zavreni elementu arg2
                    xmlwriter_end_element($xml);
                    
                    // vypis druheho argumentu <arg3 type="__">
                    xmlwriter_start_element($xml, "arg3");
                    xmlwriter_start_attribute($xml, "type");
                    // ziskani typu, nalezeny typ je v promenne $printType
                    $printType2 = explode("@", $line[3], 2);
                    if (preg_match('/\A(LF|GF|TF)\z/', $printType2[0])) {
                        xmlwriter_text($xml, "var");
                        xmlwriter_end_attribute($xml);
                        xmlwriter_text($xml, $line[3]);
                    }
                    else {
                        xmlwriter_text($xml, $printType2[0]);
                        xmlwriter_end_attribute($xml);
                        xmlwriter_text($xml, $printType2[1]);
                    }
                    // zavreni elementu arg3
                    xmlwriter_end_element($xml);

                    // zavreni elementu instruction </instruction>
                    xmlwriter_end_element($xml);
                }
                else {
                    exit(23);
                }
                break;
        
        // LABEL SYMB SYMB
            case 'JUMPIFEQ':
            case 'JUMPIFNEQ':
                if (count($line) != 4) {
                    exit(23);
                }
                if (label_symb_symb($line)) {
                    // vypis instrukce  <instruction order="_" opcode="_____">
                    xmlwriter_start_element($xml, "instruction");
                    xmlwriter_start_attribute($xml, "order");
                    xmlwriter_text($xml, $i);
                    xmlwriter_end_attribute($xml);
                    xmlwriter_start_attribute($xml, "opcode");
                    xmlwriter_text($xml, $first);
                    xmlwriter_end_attribute($xml);

                    // vypis prvniho argumentu  <arg1 type="var">
                    xmlwriter_start_element($xml, "arg1");
                    xmlwriter_start_attribute($xml, "type");
                    xmlwriter_text($xml, "label");
                    xmlwriter_end_attribute($xml);
                    // vypis promenne
                    xmlwriter_text($xml, $line[1]);
                    // zavreni elementu arg1 </arg1>
                    xmlwriter_end_element($xml);

                    // vypis druheho argumentu <arg2 type="__">
                    xmlwriter_start_element($xml, "arg2");
                    xmlwriter_start_attribute($xml, "type");
                    // ziskani typu, nalezeny typ je v promenne $printType
                    $printType1 = explode("@", $line[2], 2);
                    if (preg_match('/\A(LF|GF|TF)\z/', $printType1[0])) {
                        xmlwriter_text($xml, "var");
                        xmlwriter_end_attribute($xml);
                        xmlwriter_text($xml, $line[2]);
                    }
                    else {
                        xmlwriter_text($xml, $printType1[0]);
                        xmlwriter_end_attribute($xml);
                        xmlwriter_text($xml, $printType1[1]);
                    }
                    // zavreni elementu arg2
                    xmlwriter_end_element($xml);
                    
                    // vypis druheho argumentu <arg3 type="__">
                    xmlwriter_start_element($xml, "arg3");
                    xmlwriter_start_attribute($xml, "type");
                    // ziskani typu, nalezeny typ je v promenne $printType
                    $printType2 = explode("@", $line[3], 2);
                    //print_r($printType2);
                    if (preg_match('/\A(LF|GF|TF)\z/', $printType2[0])) {
                        xmlwriter_text($xml, "var");
                        xmlwriter_end_attribute($xml);
                        xmlwriter_text($xml, $line[3]);
                    }
                    else {
                        xmlwriter_text($xml, $printType2[0]);
                        xmlwriter_end_attribute($xml);
                        xmlwriter_text($xml, $printType2[1]); // HERE
                    }
                    // zavreni elementu arg3
                    xmlwriter_end_element($xml);

                    // zavreni elementu instruction </instruction>
                    xmlwriter_end_element($xml);
                }
                break;

        // neplatny vyraz
            default:
                    exit(22);
                break;
        }
    }


    
    /**
     * Kontrola <var> <symb>
     */
    function var_symb($line)
    {
        // rozlozit var ($line[1]) podle @ na ramec a promennou (GF@text => GF, text) na presne 2 polozky
        $varControl = explode("@", $line[1], 2);

        // kontrola, ze var zacina ramcem
        if (preg_match('/\A(LF|GF|TF)\z/', $varControl[0]))
        {
            // kontrola, ze za ramcem se nachazi promenna ve validnim tvaru
            if (preg_match('/\A(_|-|\$|&|%|\*|!|\?|[a-zA-Z]*)+(_|-|\$|&|%|\*|!|\?|[a-zA-Z0-9]*)*\z/', $varControl[1]))
            {
                // pokud promenna zacina cislem, je to spatne
                if (preg_match('/\A\d/', $varControl[1])) {
                    exit(23);
                }
                // rozlozit symb podle @
                $symbControl = explode("@", $line[2], 2);

                // kontrola ze symb zacina int, bool, string nebo nil, najity vysledek se ulozi do $foundType
                if (preg_match('/\A(LF|GF|TF)\z/', $symbControl[0]))
                {
                    // kontrola, ze za ramcem se nachazi promenna ve validnim tvaru
                    if (preg_match('/\A(_|-|\$|&|%|\*|!|\?|[a-zA-Z]*)+(_|-|\$|&|%|\*|!|\?|[a-zA-Z0-9]*)*\z/', $symbControl[1]))
                    {
                        if (preg_match('/\A\d/', $varControl[1])) {
                            exit(23);
                        }
                        return true;
                    }
                    else {
                        exit(23);
                    }
                }
                elseif (preg_match('/\A(int|bool|string|nil)\z/', $symbControl[0], $foundType)) {
                    // kontrola toho, co se nachází za @ podle toho, o jaký typ se jedná
                    switch ($foundType[0]) {
                        case 'int':
                            // pokud za int je pouze jakékoliv cislo
                            if (preg_match('/\A(\d+|-\d+|\+\d+)\z/', $symbControl[1])) {
                                return true;
                            }
                            else {
                                exit(23);
                            }
                            break;

                        case 'bool':
                            // za bool muze byt jen true nebo false
                            if (preg_match('/\A(true|false)\z/', $symbControl[1])) {
                                return true;
                            }
                            else {
                                exit(23);
                            }
                            break;
                        
                        case 'nil':
                            // za nil muze byt pouze nil
                            if ($symbControl[1] == 'nil') {
                                return true;
                            }
                            else {
                                exit(23);
                            }
                            break;

                        case 'string':
                            // pokud se ve stringu objevuje nekorektni zapis escape sekvence:
                            //      \\[\D]+             =>  \a      (tedy samotne \ uprostred textu)
                            //      \\+\z               =>  \       (samotne \ na konci string)
                            //      \\[0-9][\D]         =>  \1a     
                            //      \[0-9]\z            =>  \1
                            //      \\[0-9][0-9]\z      => \11
                            //      \\[0-9][0-9][\D]    => \11a
                            if (preg_match('/(\\\\[\D]+|\\\\+\z|\\\\[0-9][\D]|\\\\[0-9]\z|\\\\[0-9][0-9][\D]|\\\\[0-9][0-9]\z)/u', $symbControl[1])) {
                                if (preg_match('/\A\\\\[0-9]{3}/', $symbControl[1])) {
                                    return true;
                                }
                                else {
                                    exit(23);
                                }
                            }
                            else {
                                return true;
                            }
                            break;
                        default:
                            exit(23);
                            break;
                    }
                }
            }
            else {
                exit(23);
            }
        }
        else {
            exit(23);
        }
    }

    /**
     * Kontrola samotneho prikazu
     * nesmi nic nasledovat
     */
    function justthis($line)
    {
        if (count($line) == 1) {
            return true;
        }
        else {
            exit(23);
        }
    }


    /**
     * Kontrola <var>
     */
    function var_only($line)
    {
        $varControl = explode("@", $line[1], 2);

        // kontrola, ze var zacina ramcem
        if (preg_match('/\A(LF|GF|TF)\z/', $varControl[0]))
        {
            // kontrola, ze za ramcem se nachazi promenna ve validnim tvaru
            if (preg_match('/\A(_|-|\$|&|%|\*|!|\?|[a-zA-Z]*)+(_|-|\$|&|%|\*|!|\?|[a-zA-Z0-9]*)*\z/', $varControl[1]))
            {
                if (preg_match('/\A\d/', $varControl[1])) {
                    exit(23);
                }
                else {
                    return true;
                }
            }
            else {
                exit(23);
            }
        }
        else {
            exit(23);
        }
    }


    /**
     * Kontrola <label>
     */
    function label_only($line)
    {
        // echo "TADYYYYYy ", $line, "\n";
        if (preg_match('/\A(_|-|\$|&|%|\*|!|\?|[a-zA-Z]*)+(_|-|\$|&|%|\*|!|\?|[a-zA-Z0-9]*)*\z/', $line[1])) {
            return true;
        }
        else {
            exit(23);
        }
    }



    /**
     * Kontrola <symb>
     */
    function symb_only($line)
    {
        $symbControl = explode("@", $line[1], 2);

        if (preg_match('/\A(LF|GF|TF)\z/', $symbControl[0]))
        {
            // kontrola, ze za ramcem se nachazi promenna ve validnim tvaru
            if (preg_match('/\A(_|-|\$|&|%|\*|!|\?|[a-zA-Z]*)+(_|-|\$|&|%|\*|!|\?|[a-zA-Z0-9]*)*\z/', $symbControl[1]))
            {
                if (preg_match('/\A\d/', $symbControl[1])) {
                    exit(23);
                }
                return true;
            }
            else {
                exit(23);
            }
        }
        // kontrola ze symb zacina int, bool, string nebo nil, najity vysledek se ulozi do $foundType
        elseif (preg_match('/\A(int|bool|string|nil)\z/', $symbControl[0], $foundType)) {
            // kontrola toho, co se nachází za @ podle toho, o jaký typ se jedná
            switch ($foundType[0]) {
                case 'int':
                    // pokud za int je pouze jakékoliv cislo
                    if (preg_match('/\A(\d+|-\d+)\z/', $symbControl[1])) {
                        return true;
                    }
                    else {
                        exit(23);
                    }
                    break;

                case 'bool':
                    // za bool muze byt jen true nebo false
                    if (preg_match('/\A(true|false)\z/', $symbControl[1])) {
                        return true;
                    }
                    else {
                        exit(23);
                    }
                    break;
                
                case 'nil':
                    // za nil muze byt pouze nil
                    if ($symbControl[1] == 'nil') {
                        return true;
                    }
                    else {
                        exit(23);
                    }
                    break;

                case 'string':
                    // pokud se ve stringu objevuje nekorektni zapis escape sekvence:
                    //      \\[\D]+             =>  \a      (tedy samotne \ uprostred textu)
                    //      \\+\z               =>  \       (samotne \ na konci string)
                    //      \\[0-9][\D]         =>  \1a     
                    //      \[0-9]\z            =>  \1
                    //      \\[0-9][0-9]\z      =>  \11
                    //      \\[0-9][0-9][\D]    =>  \11a
                    if (preg_match('/ (\\\\[\D]+|\\\\+\z|\\\\[0-9][\D]|\\\\[0-9]\z|\\\\[0-9][0-9][\D]|\\\\[0-9][0-9]\z)/u', $symbControl[1])) {
                        if (preg_match('/\A\\\\[0-9]{3}/', $symbControl[1])) {
                            return true;
                        }
                        else {
                            exit(23);
                        }
                    }
                    else {
                        return true;
                    }
                    break;
                default:
                    exit(23);
                    break;
            }
        }
        else {
            exit(23);
        }
    }


    /**
     * Kontrola <var> <type>
     */
    function var_type($line)
    {
        if (var_only($line)) {
            if (preg_match('/\A(int|bool|string)\z/u', $line[2])) {
                return true;
            }
            else {
                exit(23);
            }
        }
        else {
            exit(23);
        }
    }


    /**
     * Kontrola <var> <symb1> <symb2>
     */
    function var_symb_symb($line)
    {
        //echo "HERE 1 \n";
        if (var_symb($line)) {
           // echo "HERE 2 \n";
            $symbControl = explode("@", $line[3], 2);

            if (preg_match('/\A(LF|GF|TF)\z/', $symbControl[0]))
            {
                // kontrola, ze za ramcem se nachazi promenna ve validnim tvaru
                if (preg_match('/\A(_|-|\$|&|%|\*|!|\?|[a-zA-Z]*)+(_|-|\$|&|%|\*|!|\?|[a-zA-Z0-9]*)*\z/', $symbControl[1]))
                {
                    if (preg_match('/\A\d/', $symbControl[1])) {
                        exit(23);
                    }
                    else {
                        return true;
                    }
                }
                else {
                    exit(23);
                }
            }
            // kontrola ze symb zacina int, bool, string nebo nil, najity vysledek se ulozi do $foundType
            elseif (preg_match('/\A(int|bool|string|nil)\z/', $symbControl[0], $foundType)) {
               // echo "HERE 3 \n";
                // kontrola toho, co se nachází za @ podle toho, o jaký typ se jedná
                switch ($foundType[0]) {
                    case 'int':
                        // pokud za int je pouze jakékoliv cislo
                        if (preg_match('/\A(\d+|-\d+)\z/', $symbControl[1])) {
                            return true;
                        }
                        else {
                            exit(23);
                        }
                        break;

                    case 'bool':
                        // za bool muze byt jen true nebo false
                        if (preg_match('/\A(true|false)\z/', $symbControl[1])) {
                            return true;
                        }
                        else {
                            exit(23);
                        }
                        break;
                    
                    case 'nil':
                        // za nil muze byt pouze nil
                        if ($symbControl[1] == 'nil') {
                            return true;
                        }
                        else {
                            exit(23);
                        }
                        break;

                    case 'string':
                        // pokud se ve stringu objevuje nekorektni zapis escape sekvence:
                        //      \\[\D]+             =>  \a      (tedy samotne \ uprostred textu)
                        //      \\+\z               =>  \       (samotne \ na konci string)
                        //      \\[0-9][\D]         =>  \1a     
                        //      \[0-9]\z            =>  \1
                        //      \\[0-9][0-9]\z      => \11
                        //      \\[0-9][0-9][\D]    => \11a
                      //  echo "HERE 4 \n";
                        if (preg_match('/(\\\\[\D]+|\\\\+\z|\\\\[0-9][\D]|\\\\[0-9]\z|\\\\[0-9][0-9][\D]|\\\\[0-9][0-9]\z)/u', $symbControl[1])) 
                        {
                          //  echo "HERE 5 \n";
                            if (preg_match('/\A\\\\[0-9]{3}/', $symbControl[1])) {
                              //  echo "HERE 4 \n";
                                return true;
                            }
                            else {
                                exit(23);
                            }
                            exit(23);
                        }
                        
                        else {
                            return true;
                        }
                        break;
                    
                    default:
                         exit(23);
                        break;
                }
            }
        }
    }

    /**
     * Kontrola <label> <symb1> <symb2>
     */
    function label_symb_symb($line) {
        label_only($line);
        $symbControl = explode("@", $line[2], 2);
        if (preg_match('/\A(LF|GF|TF)\z/', $symbControl[0]))
        {
            // kontrola, ze za ramcem se nachazi promenna ve validnim tvaru
            if (preg_match('/\A(_|-|\$|&|%|\*|!|\?|[a-zA-Z]*)+(_|-|\$|&|%|\*|!|\?|[a-zA-Z0-9]*)*\z/', $symbControl[1]))
            {
                if (preg_match('/\A\d/', $symbControl[1])) {
                    exit(23);
                }
                else {
                    
                }
                
            }
            else {
                exit(23);
            }
        }
        // kontrola ze symb zacina int, bool, string nebo nil, najity vysledek se ulozi do $foundType
        elseif (preg_match('/\A(int|bool|string|nil)\z/', $symbControl[0], $foundType)) {
            // kontrola toho, co se nachází za @ podle toho, o jaký typ se jedná
            //echo "HERE \n";
            switch ($foundType[0]) {
                case 'int':
                    // pokud za int je pouze jakékoliv cislo
                    if (preg_match('/\A(\d+|-\d+)\z/', $symbControl[1])) {
                        
                    }
                    else {
                        exit(23);
                    }
                    break;

                case 'bool':
                    // za bool muze byt jen true nebo false
                    if (preg_match('/\A(true|false)\z/', $symbControl[1])) {
                        
                    }
                    else {
                        exit(23);
                    }
                    break;
                
                case 'nil':
                    // za nil muze byt pouze nil
                    if ($symbControl[1] == 'nil') {
                        
                    }
                    else {
                        exit(23);
                    }
                    break;

                case 'string':
                    // pokud se ve stringu objevuje nekorektni zapis escape sekvence:
                    //      \\[\D]+             =>  \a      (tedy samotne \ uprostred textu)
                    //      \\+\z               =>  \       (samotne \ na konci string)
                    //      \\[0-9][\D]         =>  \1a     
                    //      \[0-9]\z            =>  \1
                    //      \\[0-9][0-9]\z      =>  \11
                    //      \\[0-9][0-9][\D]    =>  \11a
                    if (preg_match('/ (\\\\[\D]+|\\\\+\z|\\\\[0-9][\D]|\\\\[0-9]\z|\\\\[0-9][0-9][\D]|\\\\[0-9][0-9]\z)/u', $symbControl[1])) {
                        if (preg_match('/\A\\\\[0-9]{3}/', $symbControl[1])) {
                            
                        }
                        else {
                            exit(23);
                        }
                    }
                    else {
                        
                    }
                    break;
                default:
                    exit(23);
                    break;
            }
        }
        else {
            exit(23);
        }

        $symbControl1 = explode("@", $line[3], 2);
        if (preg_match('/\A(LF|GF|TF)\z/', $symbControl1[0]))
        {
            // kontrola, ze za ramcem se nachazi promenna ve validnim tvaru
            if (preg_match('/\A(_|-|\$|&|%|\*|!|\?|[a-zA-Z]*)+(_|-|\$|&|%|\*|!|\?|[a-zA-Z0-9]*)*\z/', $symbControl1[1]))
            {
                if (preg_match('/\A\d/', $symbControl1[1])) {
                    exit(23);
                }
                else {
                    return true;
                }
                return true;
            }
            else {
                exit(23);
            }
        }
        // kontrola ze symb zacina int, bool, string nebo nil, najity vysledek se ulozi do $foundType
        elseif (preg_match('/\A(int|bool|string|nil)\z/', $symbControl1[0], $foundType)) {
            // kontrola toho, co se nachází za @ podle toho, o jaký typ se jedná
            switch ($foundType[0]) {
                case 'int':
                    // pokud za int je pouze jakékoliv cislo
                    if (preg_match('/\A(\d+|-\d+)\z/', $symbControl1[1])) {
                        return true;
                    }
                    else {
                        exit(23);
                    }
                    break;

                case 'bool':
                    // za bool muze byt jen true nebo false
                    if (preg_match('/\A(true|false)\z/', $symbControl1[1])) {
                        return true;
                    }
                    else {
                        exit(23);
                    }
                    break;
                
                case 'nil':
                    // za nil muze byt pouze nil
                    if ($symbControl1[1] == 'nil') {
                        return true;
                    }
                    else {
                        exit(23);
                    }
                    break;

                case 'string':
                    // pokud se ve stringu objevuje nekorektni zapis escape sekvence:
                    //      \\[\D]+             =>  \a      (tedy samotne \ uprostred textu)
                    //      \\+\z               =>  \       (samotne \ na konci string)
                    //      \\[0-9][\D]         =>  \1a     
                    //      \[0-9]\z            =>  \1
                    //      \\[0-9][0-9]\z      =>  \11
                    //      \\[0-9][0-9][\D]    =>  \11a
                    if (preg_match('/ (\\\\[\D]+|\\\\+\z|\\\\[0-9][\D]|\\\\[0-9]\z|\\\\[0-9][0-9][\D]|\\\\[0-9][0-9]\z)/u', $symbControl1[1])) {
                        if (preg_match('/\A\\\\[0-9]{3}/', $symbControl1[1])) {
                            return true;
                        }
                        else {
                            exit(23);
                        }
                    }
                    else {
                        return true;
                    }
                    break;
                default:
                    exit(23);
                    break;
            }
        }
        else {
            exit(23);
        }
    }
    
    function callHelp(){
        echo "              Nápověda pro parser .IPPCODE21                   \n",
             "-------------------------------------------------------------- \n",
             " Skript typu filtr nacte ze standardniho vstupu zdrojovy kod   \n",
             " v IPPcode21, zkontroluje lexikalni a syntaktickou spravnost   \n",
             " kodu a vypise na standardni vystup XML reprezentaci programu  \n",
             "-------------------------------------------------------------- \n";
            exit(0);
            }
?>