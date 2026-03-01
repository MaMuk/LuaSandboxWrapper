<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use LuaSandbox;
use Melmuk\LuaSandboxWrapper\LuaCode;
use Melmuk\LuaSandboxWrapper\LuaExecutor;
use Melmuk\LuaSandboxWrapper\Output\BufferedOutputSink;
use Melmuk\LuaSandboxWrapper\SandboxConfig;

$sandbox = new LuaSandbox();
$config = SandboxConfig::defaults()->withOutputSink(new BufferedOutputSink());
$executor = new LuaExecutor($config, $sandbox);

$first = $executor->execute([], new LuaCode(<<<'LUA'
state = 10
function execute(data)
    state = state + 1
    return { state = state }
end
LUA));

$second = $executor->execute([], new LuaCode(<<<'LUA'
function execute(data)
    state = state + 1
    return { state = state }
end
LUA));

echo "State persists because sandbox was injected:\n";
print_r($first);
print_r($second);
