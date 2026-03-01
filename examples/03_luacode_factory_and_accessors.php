<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Melmuk\LuaSandboxWrapper\LuaCode;

$code = LuaCode::forFunction(
    'function transform(data) data.ok = true return data end',
    'transform',
    'transform-chunk'
);

echo "Source: " . $code->source() . PHP_EOL;
echo "Function: " . $code->functionName() . PHP_EOL;
echo "Chunk: " . ($code->chunkName() ?? 'null') . PHP_EOL;
