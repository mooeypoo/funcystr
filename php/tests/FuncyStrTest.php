<?php

namespace FuncyStr\Tests;

use PHPUnit\Framework\TestCase;
use FuncyStr\FuncyStr;

class FuncyStrTest extends TestCase
{
    private FuncyStr $funcyStr;

    protected function setUp(): void
    {
        $this->funcyStr = new FuncyStr([
            'PRONOUN' => function($params, $he, $she, $they = null) {
                return ($params['pronoun'] ?? '') === 'he' ? $he : 
                       (($params['pronoun'] ?? '') === 'she' ? $she : $they);
            },
            'PLURAL' => function($params, $one, $plural) {
                return ($params['plural'] ?? false) ? $plural : $one;
            },
            'CHAR_NAME' => function($params) {
                return $params['char_name'] ?? '';
            },
            'BRACKOUTPUT' => function($params) {
                return '{{';
            },
            'BRACKOUTPUTEND' => function($params) {
                return '}}';
            },
            'OUTPUTFUNC' => function($params) {
                return ($params['m'] ?? false) ? '{{PLURAL|man|men}}' : '{{PLURAL|woman|women}}';
            }
        ]);
    }

    public function testSimplePronounFunction(): void
    {
        $result = $this->funcyStr->process(
            'This is a {{PRONOUN|he|she|they}}.',
            ['pronoun' => 'he']
        );
        $this->assertEquals('This is a he.', $result);
    }

    public function testSimplePluralFunction(): void
    {
        $result = $this->funcyStr->process(
            'There is one {{PLURAL|apple|apples}}.',
            ['plural' => true]
        );
        $this->assertEquals('There is one apples.', $result);
    }

    public function testReplacementFunctionWithoutInlineValues(): void
    {
        $result = $this->funcyStr->process(
            'Hello, {{CHAR_NAME}}.',
            ['char_name' => 'John']
        );
        $this->assertEquals('Hello, John.', $result);
    }

    public function testNestedFunctions(): void
    {
        $result = $this->funcyStr->process(
            'We see that {{PLURAL|this is|these are}} {{PRONOUN|{{PLURAL|a man|men}}|{{PLURAL|a woman|women}}}}.',
            ['pronoun' => 'he', 'plural' => true]
        );
        $this->assertEquals('We see that these are men.', $result);
    }

    public function testMissingFunctionDefinitions(): void
    {
        $result = $this->funcyStr->process(
            'This is a {{UNKNOWN|arg1|arg2}}.',
            []
        );
        $this->assertEquals('This is a {{UNKNOWN|arg1|arg2}}.', $result);
    }

    public function testEmptyString(): void
    {
        $result = $this->funcyStr->process('', []);
        $this->assertEquals('', $result);
    }

    public function testStringWithoutFunctions(): void
    {
        $result = $this->funcyStr->process('This is a plain string.', []);
        $this->assertEquals('This is a plain string.', $result);
    }
}

