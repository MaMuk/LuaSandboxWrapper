<?php

declare(strict_types=1);

namespace Melmuk\LuaSandboxWrapper;

use LuaSandbox;
use Melmuk\LuaSandboxWrapper\Conversion\ConversionFailure;
use Melmuk\LuaSandboxWrapper\Conversion\TableShapeConverter;
use Melmuk\LuaSandboxWrapper\Exception\DataConversionException;
use Melmuk\LuaSandboxWrapper\Exception\LuaCompilationException;
use Melmuk\LuaSandboxWrapper\Exception\LuaExecutionException;
use Melmuk\LuaSandboxWrapper\Exception\LuaFunctionNotFoundException;
use Melmuk\LuaSandboxWrapper\Exception\LuaRuntimeException;
use Melmuk\LuaSandboxWrapper\Exception\LuaSandboxExtensionMissingException;
use Melmuk\LuaSandboxWrapper\Exception\OutputLimitExceededException;

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
        }

        $startedAt = microtime(true);
        $this->beginRun($code->functionName());

        try {
            $this->applyLimits();
            $this->bootstrapPrint($code);

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

    private function bootstrapPrint(LuaCode $code): void
    {
        if ($this->printBootstrapped) {
            return;
        }

        try {
            $this->sandbox->registerLibrary('php', [
                '__wrapper_print' => function (...$args): void {
                    $this->handleLuaPrint($args);
                },
            ]);
            $this->sandbox->loadString('print = php.__wrapper_print')->call();
        } catch (\Throwable $exception) {
            throw $this->mapException($exception, $code, 'bootstrap-print');
        }

        $this->printBootstrapped = true;
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

    private function safeFloatMetric(\Closure $metric): float
    {
        try {
            return $metric();
        } catch (\Throwable) {
            return 0.0;
        }
    }

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
