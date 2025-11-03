# FuncyStr PHP

A PHP port of the FuncyStr JavaScript library for processing strings with embedded function calls.

## Installation

Install via Composer:

```bash
composer require mooeypoo/funcystr
```

## Usage

```php
<?php

use FuncyStr\FuncyStr;

$funcyStr = new FuncyStr([
    'uppercase' => function($params, $str) {
        return strtoupper($str);
    },
    'repeat' => function($params, $str, $times) {
        return str_repeat($str, (int)$times);
    }
]);

$result = $funcyStr->process(
    "{{uppercase|this}} is a demo that is {{repeat|really |3}} simple."
);
// Output: "THIS is a demo that is really really really simple."
```

## With Parameters

```php
<?php

use FuncyStr\FuncyStr;

$funcyStr = new FuncyStr([
    'charname' => function($params) {
        return $params['name'] ?? '';
    },
    'pronoun' => function($params, $he, $she, $they) {
        if (($params['pronoun'] ?? '') === 'he') return $he;
        if (($params['pronoun'] ?? '') === 'she') return $she;
        return $they;
    }
]);

$result = $funcyStr->process(
    '{{charname}} went to the store and bought {{pronoun|himself|herself|themselves}} groceries.',
    ['name' => 'Sam', 'pronoun' => 'they']
);
// Output: "Sam went to the store and bought themselves groceries."
```

## Requirements

- PHP 8.0 or higher

## License

MIT

