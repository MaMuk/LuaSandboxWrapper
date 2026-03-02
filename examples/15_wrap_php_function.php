<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Melmuk\LuaSandboxWrapper\LuaExecutor;
use Melmuk\LuaSandboxWrapper\Output\BufferedOutputSink;
use Melmuk\LuaSandboxWrapper\SandboxConfig;

$executor = new LuaExecutor(
    SandboxConfig::defaults()->withOutputSink(new BufferedOutputSink())
);

$wrapped = $executor->wrapPhpFunction(
    static fn (int $a, int $b): int => $a + $b
);

echo "Wrapped object class: " . $wrapped::class . PHP_EOL;

$result = $wrapped->call(7, 5);
echo "Wrapped call result:\n";
print_r($result);
