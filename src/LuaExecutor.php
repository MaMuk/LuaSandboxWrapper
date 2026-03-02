<?php

declare(strict_types=1);

namespace Melmuk\LuaSandboxWrapper;

use LuaSandbox;
use Melmuk\LuaSandboxWrapper\Conversion\ConversionFailure;
use Melmuk\LuaSandboxWrapper\Conversion\TableShapeConverter;
use Melmuk\LuaSandboxWrapper\Exception\DataConversionException;
use Melmuk\LuaSandboxWrapper\Exception\FunctionAccessViolationException;
use Melmuk\LuaSandboxWrapper\Exception\LuaCompilationException;
use Melmuk\LuaSandboxWrapper\Exception\LuaExecutionException;
use Melmuk\LuaSandboxWrapper\Exception\LuaFunctionNotFoundException;
use Melmuk\LuaSandboxWrapper\Exception\LuaRuntimeException;
use Melmuk\LuaSandboxWrapper\Exception\LuaSandboxExtensionMissingException;
use Melmuk\LuaSandboxWrapper\Exception\OutputLimitExceededException;
use Melmuk\LuaSandboxWrapper\FunctionAccess\AccessMode;
use Melmuk\LuaSandboxWrapper\FunctionAccess\FunctionAccessConfig;

/**
 * High-level execution wrapper around the LuaSandbox extension.
 */
final class LuaExecutor
{
    private LuaSandbox $sandbox;
    private SandboxConfig $config;
    private bool $printBootstrapped = false;
    private bool $sandboxInjected;

    private ?string $currentOutput = null;
    private int $currentOutputBytes = 0;
    private string $currentFunctionName = 'unknown';
    private TableShapeConverter $converter;
    private int $callbackCounter = 0;

    /**
     * @param SandboxConfig|null $config Runtime configuration. Defaults are used when null.
     * @param LuaSandbox|null $sandbox Optional sandbox instance for reuse/testing.
     */
    public function __construct(?SandboxConfig $config = null, ?LuaSandbox $sandbox = null)
    {
        $this->assertLuaSandboxExtensionAvailable();
        $this->config = $config ?? SandboxConfig::defaults();
        $this->sandbox = $sandbox ?? new LuaSandbox();
        $this->sandboxInjected = $sandbox !== null;
        $this->converter = new TableShapeConverter();
    }

    /**
     * Executes Lua code and returns the first Lua return value.
     *
     * @throws LuaExecutionException
     */
    public function execute(array $data, LuaCode $code): mixed
    {
        return $this->run($data, $code)->value();
    }

    /**
     * Executes Lua code and returns value, output, and metrics.
     *
     * @throws LuaExecutionException
     */
    public function run(array $data, LuaCode $code): ExecutionResult
    {
        if (!$this->sandboxInjected) {
            $this->assertLuaSandboxExtensionAvailable();
            $this->sandbox = new LuaSandbox();
            $this->printBootstrapped = false;
            $this->callbackCounter = 0;
        }

        $startedAt = microtime(true);
        $this->beginRun($code->functionName());

        try {
            $this->applyLimits();
            $callableRebindings = $this->bootstrapCallbacksAndPrint($code);
            $this->applyFunctionAccessPolicy($code, $callableRebindings);

            $chunk = $this->compileChunk($code);
            $chunk->call();

            $function = $this->resolveFunction($code);
            $luaInput = $this->convertInputToLua($data, $code);
            $rawValue = $this->unwrapSingleLuaReturnValue($function->call($luaInput));
            $value = $this->convertOutputFromLua($rawValue, $code);
        } catch (LuaExecutionException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            throw $this->mapException($exception, $code, 'run');
        } finally {
            $output = $this->finishRun();
        }

        return new ExecutionResult(
            $value,
            $output,
            (microtime(true) - $startedAt) * 1000,
            $this->safeFloatMetric(fn (): float => $this->sandbox->getCPUUsage()),
            $this->safeIntMetric(fn (): int => $this->sandbox->getPeakMemoryUsage()),
        );
    }

    /**
     * @return object LuaSandboxFunction instance
     */
    private function compileChunk(LuaCode $code): object
    {
        try {
            if ($code->chunkName() !== null) {
                return $this->sandbox->loadString($code->source(), $code->chunkName());
            }

            return $this->sandbox->loadString($code->source());
        } catch (\Throwable $exception) {
            throw $this->mapException($exception, $code, 'compile');
        }
    }

    /**
     * @return object LuaSandboxFunction instance
     */
    private function resolveFunction(LuaCode $code): object
    {
        try {
            $result = $this->sandbox->loadString(sprintf('return %s', $code->functionName()))->call();
            $function = $this->unwrapSingleLuaReturnValue($result);
        } catch (\Throwable $exception) {
            throw $this->mapException($exception, $code, 'resolve-function');
        }

        if (!is_object($function) || !method_exists($function, 'call')) {
            throw new LuaFunctionNotFoundException(
                sprintf('Lua function "%s" was not found or is not callable.', $code->functionName()),
                $code->functionName(),
                'resolve-function',
            );
        }

        return $function;
    }

    /**
     * Registers wrapper callbacks and returns callable rebindings map: symbol => lua expression.
     *
     * @return array<string, string>
     */
    private function bootstrapCallbacksAndPrint(LuaCode $code): array
    {
        if ($this->printBootstrapped) {
            return [];
        }

        $callbacks = [];
        $callableRebindings = [];

        if ($this->config->printEnabled()) {
            $callback = 'php.__wrapper_print';
            $this->assertCallbackAllowed($callback, $code);

            $callbacks['__wrapper_print'] = function (...$args): void {
                $this->handleLuaPrint($args);
            };
        }

        foreach ($this->config->functionAccessConfig()->rebindings() as $symbol => $target) {
            if (!is_callable($target)) {
                continue;
            }

            $this->callbackCounter++;
            $callbackName = '__rebind_' . $this->callbackCounter;
            $callbackPath = 'php.' . $callbackName;
            $this->assertCallbackAllowed($callbackPath, $code);

            $callbacks[$callbackName] = $target;
            $callableRebindings[$symbol] = $callbackPath;
        }

        try {
            if ($callbacks !== []) {
                $this->sandbox->registerLibrary('php', $this->wrapPhpCallbacks($callbacks));
            }

            if ($this->config->printEnabled()) {
                $this->sandbox->loadString('print = php.__wrapper_print')->call();
            }
        } catch (\Throwable $exception) {
            throw $this->mapException($exception, $code, 'bootstrap-print');
        }

        $this->printBootstrapped = true;

        return $callableRebindings;
    }

    /**
     * @param array<string, string> $callableRebindings
     */
    private function applyFunctionAccessPolicy(LuaCode $code, array $callableRebindings): void
    {
        $policy = $this->config->functionAccessConfig();

        try {
            if ($policy->mode() === AccessMode::BLACKLIST) {
                $this->applyBlacklistPolicy($policy);
            } else {
                $this->applyWhitelistPolicy($policy, $callableRebindings);
            }

            foreach ($policy->rebindings() as $symbol => $target) {
                $expr = is_string($target) ? $target : ($callableRebindings[$symbol] ?? null);
                if ($expr === null) {
                    continue;
                }

                $this->assignSymbolPath($symbol, $expr);
            }
        } catch (LuaExecutionException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            throw $this->mapException($exception, $code, 'function-access');
        }
    }

    private function applyBlacklistPolicy(FunctionAccessConfig $policy): void
    {
        foreach ($policy->libraries() as $library) {
            $this->unsetSymbolPath($library);
        }

        foreach ($policy->globals() as $symbol) {
            $this->unsetSymbolPath($symbol);
        }
    }

    /**
     * @param array<string, string> $callableRebindings
     */
    private function applyWhitelistPolicy(FunctionAccessConfig $policy, array $callableRebindings): void
    {
        $keepTopLevel = ['_G' => true];
        $internalHelpers = ['pairs', 'ipairs', 'type'];
        $whitelistedLibraries = array_fill_keys($policy->libraries(), true);
        $memberWhitelist = [];

        foreach ($policy->libraries() as $library) {
            $keepTopLevel[$library] = true;
        }

        foreach ($policy->globals() as $symbol) {
            $parts = $this->parseSymbolPath($symbol);
            $top = $parts[0];
            $keepTopLevel[$top] = true;

            if (count($parts) > 1) {
                $memberWhitelist[$top][] = $parts[1];
            }
        }

        foreach (array_keys($policy->rebindings()) as $symbol) {
            $parts = $this->parseSymbolPath($symbol);
            $top = $parts[0];
            $keepTopLevel[$top] = true;
            if (count($parts) > 1) {
                $memberWhitelist[$top][] = $parts[1];
            }
        }

        foreach ($callableRebindings as $expression) {
            $parts = $this->parseSymbolPath($expression);
            $keepTopLevel[$parts[0]] = true;
        }

        if ($this->config->printEnabled()) {
            $keepTopLevel['print'] = true;
            $keepTopLevel['php'] = true;
            $memberWhitelist['php'][] = '__wrapper_print';
        }

        foreach ($internalHelpers as $helper) {
            $keepTopLevel[$helper] = true;
        }

        $this->pruneTopLevelGlobals(array_keys($keepTopLevel));

        foreach ($memberWhitelist as $library => $members) {
            if (isset($whitelistedLibraries[$library])) {
                continue;
            }
            $this->pruneLibraryMembers($library, array_values(array_unique($members)));
        }

        foreach ($internalHelpers as $helper) {
            if (isset($whitelistedLibraries[$helper])) {
                continue;
            }

            if (in_array($helper, $policy->globals(), true)) {
                continue;
            }

            $this->unsetSymbolPath($helper);
        }
    }

    /**
     * @param array<int, string> $topLevelSymbols
     */
    private function pruneTopLevelGlobals(array $topLevelSymbols): void
    {
        $keep = array_fill_keys($topLevelSymbols, true);
        $keepEntries = [];
        foreach (array_keys($keep) as $symbol) {
            $keepEntries[] = sprintf('["%s"] = true', $symbol);
        }

        $script = <<<LUA
local keep = { %s }
local drop = {}
for key in pairs(_G) do
    if not keep[key] then
        drop[#drop + 1] = key
    end
end
for _, key in ipairs(drop) do
    _G[key] = nil
end
LUA;

        $this->sandbox->loadString(sprintf($script, implode(', ', $keepEntries)))->call();
    }

    /**
     * @param array<int, string> $members
     */
    private function pruneLibraryMembers(string $library, array $members): void
    {
        $memberEntries = [];
        foreach (array_values(array_unique($members)) as $member) {
            $memberEntries[] = sprintf('["%s"] = true', $member);
        }

        $script = <<<LUA
local lib = _G["%s"]
if type(lib) ~= "table" then
    return
end
local keep = { %s }
local drop = {}
for key in pairs(lib) do
    if not keep[key] then
        drop[#drop + 1] = key
    end
end
for _, key in ipairs(drop) do
    lib[key] = nil
end
LUA;

        $this->sandbox->loadString(sprintf($script, $library, implode(', ', $memberEntries)))->call();
    }

    private function unsetSymbolPath(string $path): void
    {
        $parts = $this->parseSymbolPath($path);
        $pathLiteral = $this->luaPathArrayLiteral($parts);

        $script = <<<LUA
local path = %s
local tableRef = _G
for i = 1, #path - 1 do
    local nextRef = tableRef[path[i]]
    if type(nextRef) ~= "table" then
        return
    end
    tableRef = nextRef
end
tableRef[path[#path]] = nil
LUA;

        $this->sandbox->loadString(sprintf($script, $pathLiteral))->call();
    }

    private function assignSymbolPath(string $path, string $luaExpression): void
    {
        $parts = $this->parseSymbolPath($path);
        $pathLiteral = $this->luaPathArrayLiteral($parts);

        $script = <<<LUA
local path = %s
local tableRef = _G
for i = 1, #path - 1 do
    if type(tableRef[path[i]]) ~= "table" then
        tableRef[path[i]] = {}
    end
    tableRef = tableRef[path[i]]
end
tableRef[path[#path]] = %s
LUA;

        $this->sandbox->loadString(sprintf($script, $pathLiteral, $luaExpression))->call();
    }

    /**
     * @return array<int, string>
     */
    private function parseSymbolPath(string $path): array
    {
        if ($path === '') {
            throw new \InvalidArgumentException('Symbol path cannot be empty.');
        }

        $parts = explode('.', $path);
        foreach ($parts as $segment) {
            if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $segment)) {
                throw new \InvalidArgumentException(sprintf('Invalid symbol path segment "%s" in "%s".', $segment, $path));
            }
        }

        return $parts;
    }

    /**
     * @param array<int, string> $segments
     */
    private function luaPathArrayLiteral(array $segments): string
    {
        $quoted = array_map(static fn (string $segment): string => sprintf('"%s"', $segment), $segments);

        return '{' . implode(', ', $quoted) . '}';
    }

    private function assertCallbackAllowed(string $callbackName, LuaCode $code): void
    {
        $policy = $this->config->callbackAccessConfig();
        if ($policy->allows($callbackName)) {
            return;
        }

        throw new FunctionAccessViolationException(
            sprintf(
                'PHP callback "%s" is not allowed by callback access policy (%s mode).',
                $callbackName,
                $policy->mode(),
            ),
            $code->functionName(),
            'callback-policy',
            $callbackName,
            'php-callback',
            $policy->mode(),
        );
    }

    /**
     * Normalizes PHP callbacks to LuaSandbox's expected return-value shape.
     *
     * @param array<string, callable> $callbacks
     * @return array<string, callable>
     */
    private function wrapPhpCallbacks(array $callbacks): array
    {
        $wrapped = [];

        foreach ($callbacks as $name => $callback) {
            $wrapped[$name] = static function (...$args) use ($callback): array {
                $result = $callback(...$args);

                if ($result === null) {
                    return [];
                }

                if (is_array($result)) {
                    return $result;
                }

                return [$result];
            };
        }

        return $wrapped;
    }

    /**
     * @param array<int, mixed> $args
     */
    private function handleLuaPrint(array $args): void
    {
        if ($this->currentOutput === null || !$this->config->printEnabled()) {
            return;
        }

        $line = $this->formatPrintArgs($args) . PHP_EOL;
        $lineBytes = strlen($line);
        $nextTotal = $this->currentOutputBytes + $lineBytes;

        $maxOutputBytes = $this->config->maxOutputBytes();
        if ($maxOutputBytes > 0 && $nextTotal > $maxOutputBytes) {
            throw new OutputLimitExceededException(
                sprintf(
                    'Lua print output exceeded %d bytes for function "%s".',
                    $maxOutputBytes,
                    $this->currentFunctionName,
                ),
                $this->currentFunctionName,
                'output',
            );
        }

        $this->currentOutput .= $line;
        $this->currentOutputBytes = $nextTotal;
        $this->config->outputSink()->write($line);
    }

    /**
     * @param array<int, mixed> $args
     */
    private function formatPrintArgs(array $args): string
    {
        $parts = [];

        foreach ($args as $arg) {
            $normalized = $this->convertPrintArgFromLua($arg);

            if (is_bool($normalized)) {
                $parts[] = $normalized ? 'true' : 'false';
                continue;
            }

            if ($normalized === null) {
                $parts[] = 'nil';
                continue;
            }

            if (is_scalar($normalized)) {
                $parts[] = (string) $normalized;
                continue;
            }

            $parts[] = json_encode($normalized, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: gettype($normalized);
        }

        return implode("\t", $parts);
    }

    private function convertInputToLua(array $data, LuaCode $code): array
    {
        try {
            $converted = $this->converter->toLuaValue($data, $this->config->conversionMode());
        } catch (ConversionFailure $exception) {
            throw new DataConversionException(
                sprintf(
                    'Input conversion failed for function "%s": %s',
                    $code->functionName(),
                    $exception->getMessage(),
                ),
                $code->functionName(),
                'convert-input',
                $exception->path(),
                $exception,
            );
        }

        if (!is_array($converted)) {
            throw new DataConversionException(
                sprintf('Input conversion for function "%s" did not result in an array/table.', $code->functionName()),
                $code->functionName(),
                'convert-input',
                '$',
            );
        }

        return $converted;
    }

    private function convertOutputFromLua(mixed $value, LuaCode $code): mixed
    {
        try {
            return $this->converter->toPhpValue($value, $this->config->conversionMode());
        } catch (ConversionFailure $exception) {
            throw new DataConversionException(
                sprintf(
                    'Output conversion failed for function "%s": %s',
                    $code->functionName(),
                    $exception->getMessage(),
                ),
                $code->functionName(),
                'convert-output',
                $exception->path(),
                $exception,
            );
        }
    }

    private function convertPrintArgFromLua(mixed $value): mixed
    {
        try {
            return $this->converter->toPhpValue($value, $this->config->conversionMode());
        } catch (ConversionFailure) {
            return $value;
        }
    }

    private function applyLimits(): void
    {
        if ($this->config->memoryLimitBytes() !== null) {
            $this->sandbox->setMemoryLimit($this->config->memoryLimitBytes());
        }

        if ($this->config->cpuLimitSeconds() !== null) {
            $this->sandbox->setCPULimit($this->config->cpuLimitSeconds());
        }
    }

    private function beginRun(string $functionName): void
    {
        $this->currentOutput = '';
        $this->currentOutputBytes = 0;
        $this->currentFunctionName = $functionName;
    }

    private function finishRun(): string
    {
        $output = $this->currentOutput ?? '';
        $this->currentOutput = null;
        $this->currentOutputBytes = 0;
        $this->currentFunctionName = 'unknown';

        return $output;
    }

    private function unwrapSingleLuaReturnValue(mixed $result): mixed
    {
        if (!is_array($result) || count($result) !== 1) {
            return $result;
        }

        if (array_key_exists(0, $result)) {
            return $result[0];
        }

        if (array_key_exists(1, $result)) {
            return $result[1];
        }

        return $result;
    }

    private function mapException(\Throwable $exception, LuaCode $code, string $phase): LuaExecutionException
    {
        if ($exception instanceof LuaExecutionException) {
            return $exception;
        }

        $previous = $exception->getPrevious();
        if ($previous instanceof OutputLimitExceededException) {
            return $previous;
        }

        $message = sprintf(
            'Lua execution failed during %s for function "%s": %s',
            $phase,
            $code->functionName(),
            $exception->getMessage(),
        );

        if ($this->isLuaSyntaxError($exception)) {
            return new LuaCompilationException($message, $code->functionName(), $phase, $exception);
        }

        if ($this->isLuaRuntimeError($exception)) {
            return new LuaRuntimeException($message, $code->functionName(), $phase, $exception);
        }

        return new LuaExecutionException($message, $code->functionName(), $phase, $exception);
    }

    private function isLuaSyntaxError(\Throwable $exception): bool
    {
        return class_exists('LuaSandboxSyntaxError') && $exception instanceof \LuaSandboxSyntaxError;
    }

    private function isLuaRuntimeError(\Throwable $exception): bool
    {
        return (class_exists('LuaSandboxRuntimeError') && $exception instanceof \LuaSandboxRuntimeError)
            || (class_exists('LuaSandboxFatalError') && $exception instanceof \LuaSandboxFatalError)
            || (class_exists('LuaSandboxError') && $exception instanceof \LuaSandboxError);
    }

    /**
     * @param \Closure():float $metric
     */
    private function safeFloatMetric(\Closure $metric): float
    {
        try {
            return $metric();
        } catch (\Throwable) {
            return 0.0;
        }
    }

    /**
     * @param \Closure():int $metric
     */
    private function safeIntMetric(\Closure $metric): int
    {
        try {
            return $metric();
        } catch (\Throwable) {
            return 0;
        }
    }

    private function assertLuaSandboxExtensionAvailable(): void
    {
        if (extension_loaded('luasandbox') && class_exists('LuaSandbox', false)) {
            return;
        }

        throw new LuaSandboxExtensionMissingException();
    }
}
