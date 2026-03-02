<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Melmuk\LuaSandboxWrapper\LuaCode;
use Melmuk\LuaSandboxWrapper\LuaExecutor;
use Melmuk\LuaSandboxWrapper\Output\BufferedOutputSink;
use Melmuk\LuaSandboxWrapper\SandboxConfig;

$config = SandboxConfig::defaults()
    ->withPrintEnabled(false)
    ->withOutputSink(new BufferedOutputSink())
    ->withPhpLibrary('calc', [
        'add' => static fn (int $a, int $b): int => $a + $b,
        'mul' => static fn (int $a, int $b): int => $a * $b,
    ])
    ->withPhpCallback('join', static fn (string $left, string $right): string => $left . $right, 'textx');

$executor = new LuaExecutor($config);

$result = $executor->execute([], new LuaCode(<<<'LUA'
function execute(data)
    return {
        sum = calc.add(3, 4),
        product = calc.mul(3, 4),
        joined = textx.join("lua", "-bridge")
    }
end
LUA));

print_r($result);
