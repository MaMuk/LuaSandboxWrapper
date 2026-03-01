<?php

declare(strict_types=1);

namespace Melmuk\LuaSandboxWrapper;

/**
 * Rich execution response containing value, output and usage metrics.
 */
final class ExecutionResult
{
    /**
     * @param mixed $value Converted Lua function return value.
     * @param string $output Captured Lua print output.
     * @param float $durationMs Wall-clock execution time in milliseconds.
     * @param float $cpuUsageSeconds CPU usage reported by LuaSandbox.
     * @param int $peakMemoryBytes Peak memory usage reported by LuaSandbox.
     */
    public function __construct(
        private readonly mixed $value,
        private readonly string $output,
        private readonly float $durationMs,
        private readonly float $cpuUsageSeconds,
        private readonly int $peakMemoryBytes,
    ) {
    }

    /**
     * Returns the converted Lua function return value.
     */
    public function value(): mixed
    {
        return $this->value;
    }

    /**
     * Returns captured output emitted via Lua print.
     */
    public function output(): string
    {
        return $this->output;
    }

    /**
     * Returns wall-clock execution duration in milliseconds.
     */
    public function durationMs(): float
    {
        return $this->durationMs;
    }

    /**
     * Returns CPU usage in seconds as reported by LuaSandbox.
     */
    public function cpuUsageSeconds(): float
    {
        return $this->cpuUsageSeconds;
    }

    /**
     * Returns peak memory usage in bytes as reported by LuaSandbox.
     */
    public function peakMemoryBytes(): int
    {
        return $this->peakMemoryBytes;
    }
}
