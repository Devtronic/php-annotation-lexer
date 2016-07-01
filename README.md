# PHP Annotation Lexer

PHP Annotation Lexer can extract the annotations (a.k.a. PHP Doc) from you sour cecode

### Example
```php
<?php

use Devtronic\PHPAnnotationLexer\PHPAnnotationLexer;

require 'vendor/autoload.php';

// Simple annotation block
$data = <<<PHP
    /**
     * @ANNOTATION\DESC FooBar
     * @ANNOTATION\PARAMETERS {
     *      baz(type="string", required="true")
     * }
     * @return array The Parsed Attributes
     */
PHP;

// Initialize the lexer
$lexer = new PHPAnnotationLexer();

// Lex the block from above
$res = $lexer->lexFromString($data);
print_r($res);

// Lex a file
$res = $lexer->lexFromFile('./vendor/devtronic/php-annotation-lexer/PHPAnnotationLexer.php');
print_r($res);
```

#### Output
```
Array
(
    [0] => Array
        (
            [@ANNOTATION\DESC] => FooBar
            [@ANNOTATION\PARAMETERS] => Array
                (
                    [0] => Array
                        (
                            [@name] => baz
                            [@attr] => Array
                                (
                                    [type] => string
                                    [required] => true
                                )

                        )

                )

            [@return] => array The Parsed Attributes
        )
)



Array
(
    [0] => Array
        (
            [Class] => PHPAnnotationLexer
            [@package] => Devtronic\PHPAnnotationLexer
        )

    [1] => Array
        (
            [@var] => string PHP Source Code To Lex
        )

    [2] => Array
        (
            [@param] => string $sourceFile The Path To The Source File
            [@return] => array|bool If The File Exists The Lexed Source, Otherwise False
        )

    [3] => Array
        (
            [@param] => string $source PHP Source To Lex
            [@return] => array The Lexed Source
        )

    [4] => Array
        (
            [@Foo\Bar)] => Array
                (
                    [0] => Array
                        (
                            [@name] => And The Content
                            [@attr] => Array
                                (
                                )

                        )

                    [1] =>  And Put It Together
                )

            [@return] => array The Result Of Stage One
        )

    [5] => Array
        (
            [@param] => array $result The Result From Stage One
            [@return] => array The Result Of Stage Two
        )

    [6] => Array
        (
            [@param] => string $str The Content From Stage One
            [@return] => array|mixed The Parsed Content
        )

    [7] => Array
        (
            [@param] => string $attr_str The Un-Parsed Attribute String
            [@return] => array The Parsed Attributes
        )

)

```