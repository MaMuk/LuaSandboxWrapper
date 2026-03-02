<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Melmuk\LuaSandboxWrapper\Exception\FunctionAccessViolationException;
use Melmuk\LuaSandboxWrapper\LuaCode;
use Melmuk\LuaSandboxWrapper\LuaExecutor;
use Melmuk\LuaSandboxWrapper\Output\BufferedOutputSink;
use Melmuk\LuaSandboxWrapper\SandboxConfig;

$blacklistExecutor = new LuaExecutor(
    SandboxConfig::defaults()
        ->blacklistLuaGlobals(['math.random'])
        ->withOutputSink(new BufferedOutputSink())
);

$blacklistResult = $blacklistExecutor->execute([], new LuaCode(<<<'LUA'
function execute(data)
    return { random_disabled = (math.random == nil), math_available = (math ~= nil) }
end
LUA));

echo "Blacklist result:\n";
print_r($blacklistResult);

$whitelistExecutor = new LuaExecutor(
    SandboxConfig::defaults()
        ->whitelistLuaGlobals(['pairs'])
        ->withOutputSink(new BufferedOutputSink())
);

$whitelistResult = $whitelistExecutor->execute([], new LuaCode(<<<'LUA'
function execute(data)
    return { pairs_ok = (pairs ~= nil), math_ok = (math ~= nil) }
end
LUA));

echo "Whitelist result:\n";
print_r($whitelistResult);

try {
    $blockedCallbacks = new LuaExecutor(
        SandboxConfig::defaults()
            ->whitelistPhpCallbacks([])
            ->withOutputSink(new BufferedOutputSink())
    );

    $blockedCallbacks->execute([], new LuaCode('function execute(data) return data end'));
} catch (FunctionAccessViolationException $e) {
    echo "Callback policy blocked: {$e->symbol()} ({$e->mode()})\n";
}
