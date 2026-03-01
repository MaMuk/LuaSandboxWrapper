# Wrapper vs Direct `ext-luasandbox`

This document compares writing code with this wrapper library vs using `LuaSandbox` directly.

## Goal

Execute Lua function `execute(data)` with print output and get back transformed data.

## With this wrapper

```php
<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Melmuk\LuaSandboxWrapper\LuaCode;
use Melmuk\LuaSandboxWrapper\LuaExecutor;

$executor = new LuaExecutor();

$result = $executor->execute(
    ['id' => 10],
    new LuaCode(<<<'LUA'
function execute(data)
    print("processing", data.id)
    data.ok = true
    return data
end
LUA)
);
```

What you get by default:
- per-run sandbox isolation
- automatic `print` wiring
- first return-value unwrapping
- strict type/shape conversion with explicit failures
- typed wrapper exceptions
- optional rich `ExecutionResult` via `run(...)`

If you need full extension-compatible table handling, set:

```php
$config = SandboxConfig::defaults()
    ->withConversionMode(ConversionMode::NATIVE_COMPATIBLE);
```

## Directly with `ext-luasandbox`

```php
<?php

declare(strict_types=1);

$sandbox = new LuaSandbox();

$output = '';
$sandbox->registerLibrary('php', [
    '__print' => function (...$args) use (&$output): void {
        $line = implode("\t", array_map(static fn($v) => (string)$v, $args)) . PHP_EOL;
        $output .= $line;
        fwrite(STDOUT, $line);
    },
]);

$sandbox->loadString('print = php.__print')->call();
$sandbox->setMemoryLimit(32 * 1024 * 1024);
$sandbox->setCPULimit(0.5);

$chunk = $sandbox->loadString(<<<'LUA'
function execute(data)
    print("processing", data.id)
    data.ok = true
    return data
end
LUA);
$chunk->call();

$functionResult = $sandbox->loadString('return execute')->call();
$function = is_array($functionResult) ? ($functionResult[0] ?? $functionResult[1] ?? null) : $functionResult;

if (!is_object($function) || !method_exists($function, 'call')) {
    throw new RuntimeException('execute not found');
}

$returnValues = $function->call(['id' => 10]);
$result = is_array($returnValues) && count($returnValues) === 1
    ? ($returnValues[0] ?? $returnValues[1] ?? $returnValues)
    : $returnValues;
```

Things you must manage manually:
- print forwarding
- output limits
- resolve function safely
- normalize return values
- map extension exceptions into your app-level exceptions
- avoid state leakage when reusing sandbox instances

## Why the wrapper is simpler

- Smaller call surface: `execute(...)` for value-only, `run(...)` for rich result.
- Better defaults: isolated runs and predictable output handling.
- Safer operational behavior: max output bytes, typed exceptions with phase/function metadata.
- Easier integration: sink abstraction for stdout, buffered capture, or custom logging.

## When direct extension use still makes sense

- You need raw extension primitives not surfaced by wrapper API.
- You want full manual lifecycle control for unusual performance tuning.
- You are building another abstraction layer and intentionally avoid opinionated defaults.
