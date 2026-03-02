# Reference

This is the API and behavior reference for the wrapper library.

## Package overview

Namespace root: `Melmuk\LuaSandboxWrapper`

Core classes:
- `LuaExecutor`
- `LuaCode`
- `SandboxConfig`
- `PhpLibraryRegistration`
- `ExecutionResult`
- `Conversion\ConversionMode`
- `Conversion\TableShapeConverter`
- `FunctionAccess\AccessMode`
- `FunctionAccess\FunctionAccessConfig`
- `FunctionAccess\CallbackAccessConfig`

Output abstractions:
- `Output\OutputSink`
- `Output\StdoutOutputSink`
- `Output\BufferedOutputSink`

Exceptions:
- `Exception\LuaSandboxExtensionMissingException`
- `Exception\LuaExecutionException`
- `Exception\DataConversionException`
- `Exception\FunctionAccessViolationException`
- `Exception\LuaCompilationException`
- `Exception\LuaRuntimeException`
- `Exception\LuaFunctionNotFoundException`
- `Exception\OutputLimitExceededException`

## `LuaExecutor`

File: `src/LuaExecutor.php`

### Constructor

```php
public function __construct(?SandboxConfig $config = null, ?LuaSandbox $sandbox = null)
```

Behavior:
- Uses `SandboxConfig::defaults()` when config is omitted.
- Creates internal `LuaSandbox` when sandbox is omitted.
- If sandbox is omitted, each `run()` call uses a fresh sandbox for isolation.
- If sandbox is injected, it is reused between calls.
- Throws `LuaSandboxExtensionMissingException` if `ext-luasandbox` is not loaded.

### `execute(...)`

```php
public function execute(array $data, LuaCode $code): mixed
```

Behavior:
- Executes the given Lua code and function.
- Returns first Lua return value (unwrapped single return).
- Equivalent to `$this->run(...)->value()`.

Throws:
- `LuaExecutionException` subclasses.

### `run(...)`

```php
public function run(array $data, LuaCode $code): ExecutionResult
```

Behavior:
- Applies configured memory/CPU limits.
- Bootstraps Lua `print` to configured `OutputSink`.
- Registers configured PHP callback libraries via `registerLibrary(...)`.
- Normalizes callback return values before registration:
  - `null` return => no Lua return values
  - scalar/object return => one Lua return value
  - array return => passed through as-is
- Compiles and executes Lua chunk.
- Resolves function by name from `LuaCode`.
- Calls resolved function with `$data`.
- Captures output and returns metrics.

Returns:
- `ExecutionResult`

Throws:
- `DataConversionException`
- `LuaCompilationException`
- `LuaRuntimeException`
- `LuaFunctionNotFoundException`
- `OutputLimitExceededException`
- `LuaExecutionException` (base)

### `wrapPhpFunction(...)`

```php
public function wrapPhpFunction(callable $function): object
```

Behavior:
- Calls extension `LuaSandbox::wrapPhpFunction(...)` on the underlying sandbox.
- Returns a `LuaSandboxFunction` object.

## `LuaCode`

File: `src/LuaCode.php`

### Constructor

```php
public function __construct(
    string $source,
    string $functionName = 'execute',
    ?string $chunkName = 'user-script'
)
```

Validation:
- `source` cannot be empty.
- `functionName` cannot be empty.
- `chunkName` may be `null`, but if set it cannot be empty.

### Factory

```php
public static function forFunction(
    string $source,
    string $functionName = 'execute',
    ?string $chunkName = 'user-script'
): self
```

### Accessors

```php
public function source(): string
public function functionName(): string
public function chunkName(): ?string
```

## `SandboxConfig`

File: `src/SandboxConfig.php`

Immutable configuration object with wither methods.

### Constructor parameters

```php
public function __construct(
    ?int $memoryLimitBytes = null,
    ?float $cpuLimitSeconds = null,
    bool $enablePrint = true,
    int $maxOutputBytes = 1048576,
    string $conversionMode = ConversionMode::STRICT,
    FunctionAccessConfig $functionAccessConfig = new FunctionAccessConfig(),
    CallbackAccessConfig $callbackAccessConfig = new CallbackAccessConfig(),
    array $phpLibraries = [],
    OutputSink $outputSink = new StdoutOutputSink(),
)
```

Rules:
- `memoryLimitBytes` must be `> 0` when non-null.
- `cpuLimitSeconds` must be `> 0` when non-null.
- `maxOutputBytes` must be `>= 0`.
- `maxOutputBytes = 0` disables output-size limit.
- `conversionMode` must be `ConversionMode::STRICT` or `ConversionMode::NATIVE_COMPATIBLE`.

### Static constructor

```php
public static function defaults(): self
```

### Withers

```php
public function withMemoryLimitBytes(?int $memoryLimitBytes): self
public function withCpuLimitSeconds(?float $cpuLimitSeconds): self
public function withPrintEnabled(bool $enablePrint): self
public function withMaxOutputBytes(int $maxOutputBytes): self
public function withConversionMode(string $conversionMode): self
public function withFunctionAccessConfig(FunctionAccessConfig $functionAccessConfig): self
public function withCallbackAccessConfig(CallbackAccessConfig $callbackAccessConfig): self
public function withPhpLibrary(string $library, array $callbacks): self
public function withPhpCallback(string $callbackName, callable $callback, string $library = 'php'): self
public function withOutputSink(OutputSink $outputSink): self
```

Convenience tuning helpers:

```php
public function blacklistLuaGlobals(array $symbols): self
public function whitelistLuaGlobals(array $symbols): self
public function blacklistLuaLibraries(array $libraries): self
public function whitelistLuaLibraries(array $libraries): self
public function rebindLuaGlobal(string $name, callable|string $target): self
public function blacklistPhpCallbacks(array $callbacks): self
public function whitelistPhpCallbacks(array $callbacks): self
```

### Getters

```php
public function memoryLimitBytes(): ?int
public function cpuLimitSeconds(): ?float
public function printEnabled(): bool
public function maxOutputBytes(): int
public function conversionMode(): string
public function functionAccessConfig(): FunctionAccessConfig
public function callbackAccessConfig(): CallbackAccessConfig
public function phpLibraries(): array
public function outputSink(): OutputSink
```

`withPhpLibrary(...)` exposes PHP callables to Lua through `LuaSandbox::registerLibrary(...)`.
It does not declare Lua-script functions.

## `ExecutionResult`

File: `src/ExecutionResult.php`

Returned by `LuaExecutor::run(...)`.

### Constructor shape

```php
public function __construct(
    mixed $value,
    string $output,
    float $durationMs,
    float $cpuUsageSeconds,
    int $peakMemoryBytes,
)
```

### Accessors

```php
public function value(): mixed
public function output(): string
public function durationMs(): float
public function cpuUsageSeconds(): float
public function peakMemoryBytes(): int
```

## `Conversion\\TableShapeConverter`

File: `src/Conversion/TableShapeConverter.php`

Public API:

```php
public function toLuaValue(mixed $value, string $mode = ConversionMode::STRICT): mixed
public function toPhpValue(mixed $value, string $mode = ConversionMode::STRICT): mixed
```

Behavior:
- strict or native-compatible conversion behavior
- throws `Conversion\\ConversionFailure` for invalid/ambiguous structures
- used internally by `LuaExecutor` and surfaced as `DataConversionException`

## `Conversion\\ConversionMode`

File: `src/Conversion/ConversionMode.php`

Constants:

```php
ConversionMode::STRICT
ConversionMode::NATIVE_COMPATIBLE
```

## `FunctionAccess\\AccessMode`

File: `src/FunctionAccess/AccessMode.php`

Constants:

```php
AccessMode::BLACKLIST
AccessMode::WHITELIST
```

## `FunctionAccess\\FunctionAccessConfig`

File: `src/FunctionAccess/FunctionAccessConfig.php`

Purpose:
- configure Lua runtime global/library overlays and rebindings.

Core fields:
- mode (`blacklist`|`whitelist`)
- globals (symbol paths like `math.random` or `pairs`)
- libraries (top-level tables like `math`, `string`)
- rebindings (symbol => callable|string expression)

## `FunctionAccess\\CallbackAccessConfig`

File: `src/FunctionAccess/CallbackAccessConfig.php`

Purpose:
- control which PHP callbacks are exportable to Lua.

Core fields:
- mode (`blacklist`|`whitelist`)
- callbacks (qualified names like `php.__wrapper_print`)

## Output API

### `OutputSink`

File: `src/Output/OutputSink.php`

```php
interface OutputSink
{
    public function write(string $chunk): void;
}
```

### `StdoutOutputSink`

File: `src/Output/StdoutOutputSink.php`

Writes each chunk to `STDOUT`.

### `BufferedOutputSink`

File: `src/Output/BufferedOutputSink.php`

Methods:

```php
public function write(string $chunk): void
public function buffer(): string
public function clear(): void
```

Use this when you want to inspect output programmatically.

## Exceptions

### `LuaSandboxExtensionMissingException`

File: `src/Exception/LuaSandboxExtensionMissingException.php`

Thrown when the wrapper is used in an environment where `ext-luasandbox` is not loaded.

### `FunctionAccessViolationException`

File: `src/Exception/FunctionAccessViolationException.php`

Thrown when policy denies callback exposure.

Additional metadata:

```php
public function symbol(): string
public function source(): string
public function mode(): string
```

### `LuaExecutionException`

File: `src/Exception/LuaExecutionException.php`

Base exception type. Extends `RuntimeException`.

Extra metadata:

```php
public function functionName(): string
public function phase(): string
```

Typical phases:
- `compile`
- `resolve-function`
- `run`
- `bootstrap-print`
- `output`

### Subclasses

- `DataConversionException`: strict input/output conversion failure.
- `LuaCompilationException`: compile/syntax failures.
- `LuaRuntimeException`: runtime failures.
- `LuaFunctionNotFoundException`: target function missing or not callable.
- `OutputLimitExceededException`: printed output exceeded `maxOutputBytes`.

`DataConversionException` also exposes:

```php
public function path(): string
```

## Behavioral details

Type/shape conversion is mode-driven (`strict` or `native-compatible`). See `docs/conversion.md` for complete rules.

### Return value normalization

LuaSandbox often returns arrays of Lua return values. Wrapper behavior:
- If exactly one return value exists, it is unwrapped and returned directly.
- If multiple values exist, raw array is preserved.

### Print handling

- Wrapper wires Lua global `print` to internal callback.
- Callback formats args with tab separators and newline suffix.
- Output is both captured in-memory for `ExecutionResult` and written to configured sink.
- If print is disabled (`withPrintEnabled(false)`), print calls are ignored.

### Isolation model

- Default mode: fresh sandbox per `run()` call.
- Injected sandbox mode: persistent state across calls.

### Metrics

- `durationMs`: wall-clock timing around execution.
- `cpuUsageSeconds`: from sandbox, or `0.0` if metric unavailable.
- `peakMemoryBytes`: from sandbox, or `0` if metric unavailable.

## Operational guidance

- For untrusted code, always set memory and CPU limits.
- Keep `maxOutputBytes` finite in multi-tenant/server contexts.
- Prefer `run(...)` for observability and auditing.
- Prefer default (non-injected) sandbox mode to avoid state leakage.

## Related docs

- `docs/quickstart.md`
- `docs/wrapper-vs-extension.md`
- `/examples`
