# Quickstart

## 1. Install

```bash
composer require melmuk/luasandbox-wrapper
```

Requirements:
- PHP 8.1+
- `ext-luasandbox` enabled

If missing, the wrapper throws `LuaSandboxExtensionMissingException` with an actionable message.

## 2. Minimal usage

```php
<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Melmuk\LuaSandboxWrapper\LuaCode;
use Melmuk\LuaSandboxWrapper\LuaExecutor;

$executor = new LuaExecutor();

$code = new LuaCode(<<<'LUA'
function execute(data)
    print("hello", data.id)
    data.handled = true
    return data
end
LUA);

$result = $executor->execute(['id' => 42], $code);
print_r($result);
```

What happens:
- Lua function `execute` is resolved and called with your PHP array.
- Lua `print(...)` is forwarded through the configured output sink.
- The first Lua return value is returned from `execute(...)`.
- Input/output values pass through strict shape conversion (see `docs/conversion.md`).

## 3. Rich execution result

Use `run(...)` when you need output and metrics.

```php
<?php

use Melmuk\LuaSandboxWrapper\LuaCode;
use Melmuk\LuaSandboxWrapper\LuaExecutor;

$executor = new LuaExecutor();

$execution = $executor->run(
    ['values' => [1, 2, 3]],
    LuaCode::forFunction(<<<'LUA'
function execute(data)
    local total = 0
    for _, v in pairs(data.values) do
        total = total + v
    end
    print("sum", total)
    return { sum = total }
end
LUA)
);

print_r($execution->value());
echo $execution->output();
printf("duration_ms=%.3f\n", $execution->durationMs());
```

## 4. Configure limits and output

```php
<?php

use Melmuk\LuaSandboxWrapper\LuaExecutor;
use Melmuk\LuaSandboxWrapper\Output\BufferedOutputSink;
use Melmuk\LuaSandboxWrapper\SandboxConfig;
use Melmuk\LuaSandboxWrapper\Conversion\ConversionMode;

$sink = new BufferedOutputSink();

$config = SandboxConfig::defaults()
    ->withMemoryLimitBytes(32 * 1024 * 1024)
    ->withCpuLimitSeconds(0.5)
    ->withMaxOutputBytes(64 * 1024)
    ->withConversionMode(ConversionMode::STRICT)
    ->withOutputSink($sink);

$executor = new LuaExecutor($config);
```

## 5. Handle typed exceptions

```php
<?php

use Melmuk\LuaSandboxWrapper\Exception\LuaExecutionException;

try {
    $executor->execute($data, $code);
} catch (LuaExecutionException $e) {
    echo $e->phase() . "\n";
    echo $e->functionName() . "\n";
    echo $e->getMessage() . "\n";
}
```

If shape conversion fails, you will get `DataConversionException` (subclass of `LuaExecutionException`) with explicit path/phase metadata.

## 6. Pick conversion mode explicitly

- `ConversionMode::STRICT` (default): opinionated list/map rules and 0-based<->1-based normalization.
- `ConversionMode::NATIVE_COMPATIBLE`: follows extension-compatible behavior so any shape accepted by `ext-luasandbox` remains possible.

```php
<?php

use Melmuk\LuaSandboxWrapper\Conversion\ConversionMode;

$config = SandboxConfig::defaults()
    ->withConversionMode(ConversionMode::NATIVE_COMPATIBLE);
```

## 7. Tune function/callback exposure

```php
<?php

$config = SandboxConfig::defaults()
    ->blacklistLuaGlobals(['math.random'])
    ->blacklistLuaLibraries(['os']);
```

Strict whitelist mode:

```php
<?php

$config = SandboxConfig::defaults()
    ->whitelistLuaGlobals(['pairs', 'ipairs'])
    ->whitelistPhpCallbacks(['php.__wrapper_print']);
```

## 8. Run example scripts

See `/examples`:

```bash
php examples/01_basic_execute.php
php examples/08_exception_handling.php
```

## 9. Expose PHP callbacks as a Lua library

```php
<?php

use Melmuk\LuaSandboxWrapper\LuaCode;
use Melmuk\LuaSandboxWrapper\LuaExecutor;
use Melmuk\LuaSandboxWrapper\SandboxConfig;

$config = SandboxConfig::defaults()
    ->withPrintEnabled(false)
    ->withPhpLibrary('calc', [
        'add' => static fn (int $a, int $b): int => $a + $b,
    ]);

$executor = new LuaExecutor($config);

$result = $executor->execute([], new LuaCode(<<<'LUA'
function execute(data)
    return calc.add(2, 3)
end
LUA));
```

This uses extension `registerLibrary` under the hood and exposes PHP callbacks to Lua.

## 10. Use `wrapPhpFunction(...)` directly

For advanced cases, you can directly access extension wrapping through `LuaExecutor`:

```php
<?php

use Melmuk\LuaSandboxWrapper\LuaExecutor;

$executor = new LuaExecutor();
$wrapped = $executor->wrapPhpFunction(
    static fn (int $a, int $b): int => $a + $b
);

$result = $wrapped->call(3, 4);
print_r($result);
```
