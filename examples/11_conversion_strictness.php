<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Melmuk\LuaSandboxWrapper\Exception\DataConversionException;
use Melmuk\LuaSandboxWrapper\LuaCode;
use Melmuk\LuaSandboxWrapper\LuaExecutor;
use Melmuk\LuaSandboxWrapper\Output\BufferedOutputSink;
use Melmuk\LuaSandboxWrapper\SandboxConfig;

$executor = new LuaExecutor(
    SandboxConfig::defaults()->withOutputSink(new BufferedOutputSink())
);

$ok = $executor->execute(
    ['scores' => [10, 20, 30]],
    new LuaCode('function execute(data) local s=0 for _,v in ipairs(data.scores) do s=s+v end return {sum=s} end')
);

echo "Accepted list shape:\n";
print_r($ok);

try {
    $executor->execute(
        ['scores' => [1 => 20, 2 => 30]],
        new LuaCode('function execute(data) return data end')
    );
} catch (DataConversionException $e) {
    echo "\nRejected sparse numeric array:\n";
    echo $e->getMessage() . PHP_EOL;
    echo 'phase=' . $e->phase() . PHP_EOL;
    echo 'path=' . $e->path() . PHP_EOL;
}
