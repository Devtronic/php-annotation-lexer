<?php
namespace Devtronic\PHPAnnotationLexer;

/**
 * Class PHPAnnotationLexer
 * @package Devtronic\PHPAnnotationLexer
 */
class PHPAnnotationLexer
{
    /**
     * @var string PHP Source Code To Lex
     */
    private $data = '';

    /**
     * Loads The Source File And Lex It
     * @param string $sourceFile The Path To The Source File
     * @return array|bool If The File Exists The Lexed Source, Otherwise False
     */
    public function lexFromFile($sourceFile)
    {
        if (!is_file($sourceFile)) {
            return false;
        }
        $this->data = file_get_contents($sourceFile);

        $stage1 = $this->stage1();
        $result = $this->stage2($stage1);

        return $result;
    }

    /**
     * Lexes The Passed Source Code
     * @param string $source PHP Source To Lex
     * @return array The Lexed Source
     */
    public function lexFromString($source)
    {
        $this->data = $source;

        $stage1 = $this->stage1();
        $result = $this->stage2($stage1);

        return $result;
    }

    /**
     * Stage One:
     * 1. Extract All Annotations
     * 2. Split It To Lines
     * 3. Remove Leading Spaces And Asterisks / Trailing Slashes
     * 4. Take The Namespace (@Foo\Bar) And The Content (string $parameter) And Put It Together
     * @return array The Result Of Stage One
     */
    private function stage1()
    {
        $doc = $this->data;
        preg_match_all('~\/\*\*(.*?)\*\/~mis', $doc, $matches);

        $tokens = array();
        $currentStatement = array();
        $currentIndex = 0;
        $array_open = 0;

        foreach ($matches[1] as $index => $lines) {
            foreach (preg_split("/((\r?\n)|(\r\n?))/", $lines) as $line) {

                // Remove leading spaces and *
                $line = preg_replace('~^[\s|\*|\r|\n\n]+~', '', $line);
                $line = trim($line);

                $chars = str_split($line);
                $lastChar = '';
                foreach ($chars as $i => $char) {
                    if ($lastChar == '\\' && $char == '@' && $currentIndex == 1) {
                        $char = '&at_';
                    }
                    $lastChar = $char;
                    if ($char == '@') {
                        if (!empty($currentStatement) && !empty($currentStatement[0])) {
                            $tokens[$index][] = $currentStatement;
                        }
                        $currentStatement = array();
                        $currentIndex = 0;
                    } elseif ($char == ' ' && $currentIndex == 0) {
                        $currentIndex = 1;
                        continue;
                    } elseif ($char == '{') {
                        $array_open++;
                    } elseif ($char == '}') {
                        $array_open--;
                    }

                    $char = str_replace('&at_', '@', $char);
                    if(!isset($currentStatement[$currentIndex])) {
                        $currentStatement[$currentIndex] = '';
                    }
                    $currentStatement[$currentIndex] .= $char;
                }
                if ($array_open > 0 && $lastChar != '{') {

                    $currentStatement[$currentIndex] .= ';';
                }
                $currentStatement[$currentIndex] = str_replace(';}', '}', $currentStatement[$currentIndex]);

            }
            if (!empty($currentStatement) && !empty($currentStatement[0])) {
                $tokens[$index][] = $currentStatement;
            }
            $currentStatement = array();
        }
        return $tokens;
    }

    /**
     * Stage Two:
     * 1. Parse Arrays [ Multi-line: { .... }, Inline: "foo;bar;baz" ]
     * 2. Parse Attributes [ myParameter(required="required", "type"="string") ]
     * @param array $result The Result From Stage One
     * @return array The Result Of Stage Two
     */
    public function stage2($result)
    {
        $out = array();
        foreach ($result as $index => &$statement) {
            foreach ($statement as $key => &$token) {
                $out[$index][$token[0]] = $this->parse($token[1]);
            }
        }
        return $out;
    }

    /**
     * Extracts Array and Attributes
     * @param string $str The Content From Stage One
     * @return array|mixed The Parsed Content
     */
    private function parse($str)
    {
        $out = array();
        $is_array = false;
        $has_attributes = false;

        $open_arr = 0;
        $prop_name = '';

        $chars = str_split($str);
        $buffer = '';
        $force_array = false;

        foreach ($chars as $char) {
            // Array start
            if ($char == '{') {
                if ($open_arr > 0) {
                    $buffer .= $char;
                }
                $open_arr++;
                $is_array = true;
                $force_array = true;
                continue;
            } // Array end
            elseif ($char == '}') {
                $open_arr--;
                if ($open_arr > 0) {
                    $buffer .= $char;
                }
                if (trim($buffer) != '') {
                    $res = $this->parse($buffer);
                    if (count($res) > 0) {
                        $out[] = $res;
                        $force_array = false;
                    }
                }
                $is_array = false;
                $buffer = '';
                continue;
            } // Next Entry
            elseif ($char == ';' && $is_array === false && $has_attributes === false) {
                if (trim($buffer) != '') {
                    $out[] = $buffer;
                }
                $buffer = '';
                continue;
            } // Attributes start
            elseif ($char == '(') {
                $has_attributes = true;
                $buffer = trim($buffer, '; ');
                $prop_name = $buffer;
                $buffer = '';
                continue;
            } // Attributes end
            elseif ($char == ')' && $has_attributes) {
                $out[] = $this->parseAttributes($prop_name, $buffer);
                $buffer = '';
                $has_attributes = false;
                continue;
            }
            // Add Current char to buffer
            if ($has_attributes === true && $char == ';') continue;
            $buffer .= $char;
        }
        if (trim($buffer) != '') {
            $out[] = $buffer;
        }
        if (count($out) == 1 && $force_array === false) {
            $out = $out[0];
        }

        return $out;
    }

    /**
     * Parse The Attributes [ myParameter(required="required", "type"="string") ]
     * @param string $prop_name Property Name
     * @param string $attr_str The Un-Parsed Attribute String
     * @return array The Parsed Attributes
     */
    public function parseAttributes($prop_name, $attr_str)
    {
        $out = array('@name' => $prop_name, '@attr' => array());

        $quotes_open = false;
        $attr_open = false;

        $propertyName = '';
        $buffer = '';

        $chars = str_split($attr_str);

        $prev_char = '';
        foreach ($chars as $char) {
            if ($char == '=' && $attr_open === false) {
                $propertyName = $buffer;
                $attr_open = true;
                $buffer = '';
                continue;
            } elseif ($char == '"' && $attr_open == true && $buffer == '' && $quotes_open === false) {
                $quotes_open = true;
                continue;
            } elseif ($char == '"' && $prev_char != '\\' && $attr_open == true && $buffer != '' && $quotes_open === false) {
                die(sprintf('Unexpected %s in %s, attribute %s', '"', $prop_name, $propertyName));
            } elseif ($char == '"' && $prev_char != '\\' && $attr_open == true && $quotes_open === true) {
                $quotes_open = false;
                continue;
            } elseif ($char == ',' && $quotes_open === false) {
                $attr_open = false;
                $quotes_open = false;
                $out['@attr'][$propertyName] = $buffer;
                $buffer = '';
                $propertyName = '';
                continue;
            }
            if ($char == ' ' && $quotes_open === false) {
                continue;
            }
            if ($char != '\\' or $prev_char == '\\') {
                $buffer .= $char;
            }
            $prev_char = $char;
        }
        if (trim($propertyName) != '' && trim($buffer) != '') {
            $out['@attr'][$propertyName] = $buffer;
        }
        return $out;
    }
}