# Lua Sandbox Wrapper

PHP wrapper around the `luasandbox` extension.

## Why this wrapper

`ext-luasandbox` is powerful but low-level. This package gives you:
- simple `execute(array $data, LuaCode $code): mixed` API
- deterministic per-run sandbox isolation by default
- typed wrapper exceptions
- explicit conversion modes: strict and native-compatible
- configurable memory/CPU limits
- pluggable print output sinks with output-size guard
- rich execution result (`value`, `output`, metrics)

## Installation

```bash
composer require melmuk/luasandbox-wrapper
```

Requirements:
- PHP `^8.1`
- `ext-luasandbox`

## Quick start

```php
<?php

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
```

## Advanced usage

```php
<?php

use Melmuk\LuaSandboxWrapper\LuaCode;
use Melmuk\LuaSandboxWrapper\Conversion\ConversionMode;
use Melmuk\LuaSandboxWrapper\LuaExecutor;
use Melmuk\LuaSandboxWrapper\Output\BufferedOutputSink;
use Melmuk\LuaSandboxWrapper\SandboxConfig;

$sink = new BufferedOutputSink();

$config = SandboxConfig::defaults()
    ->withMemoryLimitBytes(32 * 1024 * 1024)
    ->withCpuLimitSeconds(0.5)
    ->withConversionMode(ConversionMode::STRICT)
    ->withOutputSink($sink)
    ->withMaxOutputBytes(64 * 1024);

$executor = new LuaExecutor($config);

$execution = $executor->run(['id' => 42], LuaCode::forFunction(<<<'LUA'
function execute(data)
    print("processing", data.id)
    data.ok = true
    return data
end
LUA));

$value = $execution->value();
$output = $execution->output();
$durationMs = $execution->durationMs();
```

## Error handling

Catch typed exceptions from `Melmuk\LuaSandboxWrapper\Exception`:
- `LuaSandboxExtensionMissingException`
- `LuaCompilationException`
- `LuaRuntimeException`
- `LuaFunctionNotFoundException`
- `OutputLimitExceededException`
- `LuaExecutionException` (base type)

```php
<?php

use Melmuk\LuaSandboxWrapper\Exception\LuaExecutionException;

try {
    $executor->execute($data, $code);
} catch (LuaExecutionException $e) {
    // $e->functionName(), $e->phase(), $e->getMessage()
}
```

## Output sinks

Included sinks:
- `StdoutOutputSink` (default)
- `BufferedOutputSink`

You can implement `Melmuk\LuaSandboxWrapper\Output\OutputSink` to route print output to logs, queues, etc.

## Testing

```bash
composer install
composer test
```

Tests include config, code object, and integration behavior for execution, output capture, and exception mapping.

## Docs

- `docs/quickstart.md`
- `docs/conversion.md`
- `docs/wrapper-vs-extension.md`
- `docs/reference.md`
