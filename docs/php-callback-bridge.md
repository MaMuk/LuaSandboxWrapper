# PHP Callback Bridge

This guide explains how to expose PHP functions to Lua in this wrapper.

## What It Is For

Use the PHP callback bridge when Lua code should call back into PHP logic.

Typical uses:
- expose app/domain helpers to Lua (`calc.add`, `textx.join`)
- keep core business logic in PHP but script orchestration in Lua
- control exactly which callbacks are visible to Lua

The wrapper exposes PHP callbacks to Lua through extension `registerLibrary(...)`.

## Main API

Use `SandboxConfig` helpers:
- `withPhpLibrary(string $library, array $callbacks)`
- `withPhpCallback(string $callbackName, callable $callback, string $library = 'php')`

Example:

```php
<?php

use Melmuk\LuaSandboxWrapper\LuaCode;
use Melmuk\LuaSandboxWrapper\LuaExecutor;
use Melmuk\LuaSandboxWrapper\SandboxConfig;

$config = SandboxConfig::defaults()
    ->withPrintEnabled(false)
    ->withPhpLibrary('calc', [
        'add' => static fn (int $a, int $b): int => $a + $b,
    ])
    ->withPhpCallback('join', static fn (string $a, string $b): string => $a . $b, 'textx');

$executor = new LuaExecutor($config);

$result = $executor->execute([], new LuaCode(<<<'LUA'
function execute(data)
    return {
        sum = calc.add(2, 3),
        joined = textx.join("lua", "-bridge")
    }
end
LUA));
```

## Callback Return Behavior

Registered callbacks are normalized for Lua calls:
- `null` => no Lua return values
- scalar/object => one Lua return value
- array => passed through as multiple Lua return values

## Access Policy

Callback exposure still obeys callback access policy:
- `blacklistPhpCallbacks([...])`
- `whitelistPhpCallbacks([...])`

Callback names are matched as `library.function` (for example `calc.add`).

## Power-User API

If you need direct extension-level wrapping, use:

```php
$wrapped = $executor->wrapPhpFunction($callable);
```

This exposes `LuaSandbox::wrapPhpFunction(...)` via `LuaExecutor`.

## Example Scripts

See:
- `examples/14_php_library_registration.php`
- `examples/15_wrap_php_function.php`
