<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Melmuk\LuaSandboxWrapper\Conversion\ConversionMode;
use Melmuk\LuaSandboxWrapper\Exception\DataConversionException;
use Melmuk\LuaSandboxWrapper\LuaCode;
use Melmuk\LuaSandboxWrapper\LuaExecutor;
use Melmuk\LuaSandboxWrapper\Output\BufferedOutputSink;
use Melmuk\LuaSandboxWrapper\SandboxConfig;

$lua = new LuaCode(<<<'LUA'
function execute(data)
    local total = 0
    for _, score in ipairs(data.scores) do
        total = total + score
    end
    return { total = total }
end
LUA);

$input = ['scores' => [1 => 20, 2 => 30]];

$strictExecutor = new LuaExecutor(
    SandboxConfig::defaults()
        ->withConversionMode(ConversionMode::STRICT)
        ->withOutputSink(new BufferedOutputSink())
);

try {
    $strictExecutor->execute($input, $lua);
} catch (DataConversionException $e) {
    echo "STRICT rejected input: " . $e->path() . PHP_EOL;
}

$nativeExecutor = new LuaExecutor(
    SandboxConfig::defaults()
        ->withConversionMode(ConversionMode::NATIVE_COMPATIBLE)
        ->withOutputSink(new BufferedOutputSink())
);

$nativeResult = $nativeExecutor->execute($input, $lua);
echo "NATIVE accepted input:\n";
print_r($nativeResult);
