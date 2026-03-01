<?php

declare(strict_types=1);

namespace Melmuk\LuaSandboxWrapper;

use Melmuk\LuaSandboxWrapper\Conversion\ConversionMode;
use Melmuk\LuaSandboxWrapper\Output\OutputSink;
use Melmuk\LuaSandboxWrapper\Output\StdoutOutputSink;

/**
 * Immutable runtime configuration for Lua sandbox execution.
 */
final class SandboxConfig
{
    /**
     * @param int|null $memoryLimitBytes Lua memory limit in bytes. Null keeps extension default.
     * @param float|null $cpuLimitSeconds Lua CPU limit in seconds. Null keeps extension default.
     * @param bool $enablePrint Whether Lua global print should be wired to the output sink.
     * @param int $maxOutputBytes Max bytes captured from Lua print per run. 0 disables limit.
     * @param string $conversionMode strict|native-compatible conversion behavior.
     */
    public function __construct(
        private readonly ?int $memoryLimitBytes = null,
        private readonly ?float $cpuLimitSeconds = null,
        private readonly bool $enablePrint = true,
        private readonly int $maxOutputBytes = 1024 * 1024,
        private readonly string $conversionMode = ConversionMode::STRICT,
        private readonly OutputSink $outputSink = new StdoutOutputSink(),
    ) {
        if ($memoryLimitBytes !== null && $memoryLimitBytes <= 0) {
            throw new \InvalidArgumentException('memoryLimitBytes must be greater than zero when provided.');
        }

        if ($cpuLimitSeconds !== null && $cpuLimitSeconds <= 0) {
            throw new \InvalidArgumentException('cpuLimitSeconds must be greater than zero when provided.');
        }

        if ($maxOutputBytes < 0) {
            throw new \InvalidArgumentException('maxOutputBytes cannot be negative.');
        }

        if (!ConversionMode::isValid($conversionMode)) {
            throw new \InvalidArgumentException(sprintf(
                'Invalid conversionMode "%s". Use "%s" or "%s".',
                $conversionMode,
                ConversionMode::STRICT,
                ConversionMode::NATIVE_COMPATIBLE,
            ));
        }
    }

    /**
     * Creates a configuration with default values.
     */
    public static function defaults(): self
    {
        return new self();
    }

    /**
     * Returns a copy with a new Lua memory limit.
     */
    public function withMemoryLimitBytes(?int $memoryLimitBytes): self
    {
        return new self(
            $memoryLimitBytes,
            $this->cpuLimitSeconds,
            $this->enablePrint,
            $this->maxOutputBytes,
            $this->conversionMode,
            $this->outputSink,
        );
    }

    /**
     * Returns a copy with a new Lua CPU limit.
     */
    public function withCpuLimitSeconds(?float $cpuLimitSeconds): self
    {
        return new self(
            $this->memoryLimitBytes,
            $cpuLimitSeconds,
            $this->enablePrint,
            $this->maxOutputBytes,
            $this->conversionMode,
            $this->outputSink,
        );
    }

    /**
     * Returns a copy with print capture enabled or disabled.
     */
    public function withPrintEnabled(bool $enablePrint): self
    {
        return new self(
            $this->memoryLimitBytes,
            $this->cpuLimitSeconds,
            $enablePrint,
            $this->maxOutputBytes,
            $this->conversionMode,
            $this->outputSink,
        );
    }

    /**
     * Returns a copy with a new maximum captured output size in bytes.
     */
    public function withMaxOutputBytes(int $maxOutputBytes): self
    {
        return new self(
            $this->memoryLimitBytes,
            $this->cpuLimitSeconds,
            $this->enablePrint,
            $maxOutputBytes,
            $this->conversionMode,
            $this->outputSink,
        );
    }

    /**
     * Returns a copy with a different conversion mode.
     */
    public function withConversionMode(string $conversionMode): self
    {
        return new self(
            $this->memoryLimitBytes,
            $this->cpuLimitSeconds,
            $this->enablePrint,
            $this->maxOutputBytes,
            $conversionMode,
            $this->outputSink,
        );
    }

    /**
     * Returns a copy with a different output sink implementation.
     */
    public function withOutputSink(OutputSink $outputSink): self
    {
        return new self(
            $this->memoryLimitBytes,
            $this->cpuLimitSeconds,
            $this->enablePrint,
            $this->maxOutputBytes,
            $this->conversionMode,
            $outputSink,
        );
    }

    /**
     * Returns the Lua memory limit in bytes, or null when unset.
     */
    public function memoryLimitBytes(): ?int
    {
        return $this->memoryLimitBytes;
    }

    /**
     * Returns the Lua CPU limit in seconds, or null when unset.
     */
    public function cpuLimitSeconds(): ?float
    {
        return $this->cpuLimitSeconds;
    }

    /**
     * Indicates whether Lua print is wired to the configured sink.
     */
    public function printEnabled(): bool
    {
        return $this->enablePrint;
    }

    /**
     * Returns the maximum allowed captured output bytes per run.
     */
    public function maxOutputBytes(): int
    {
        return $this->maxOutputBytes;
    }

    /**
     * Returns the configured conversion mode.
     */
    public function conversionMode(): string
    {
        return $this->conversionMode;
    }

    /**
     * Returns the output sink used for streamed print output.
     */
    public function outputSink(): OutputSink
    {
        return $this->outputSink;
    }
}
