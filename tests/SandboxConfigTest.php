<?php

declare(strict_types=1);

namespace Melmuk\LuaSandboxWrapper\Tests;

use Melmuk\LuaSandboxWrapper\Conversion\ConversionMode;
use Melmuk\LuaSandboxWrapper\Output\BufferedOutputSink;
use Melmuk\LuaSandboxWrapper\SandboxConfig;
use PHPUnit\Framework\TestCase;

final class SandboxConfigTest extends TestCase
{
    public function testDefaultsAndWithers(): void
    {
        $sink = new BufferedOutputSink();

        $config = SandboxConfig::defaults()
            ->withMemoryLimitBytes(8 * 1024 * 1024)
            ->withCpuLimitSeconds(0.25)
            ->withMaxOutputBytes(256)
            ->withPrintEnabled(false)
            ->withConversionMode(ConversionMode::NATIVE_COMPATIBLE)
            ->withOutputSink($sink);

        self::assertSame(8 * 1024 * 1024, $config->memoryLimitBytes());
        self::assertSame(0.25, $config->cpuLimitSeconds());
        self::assertSame(256, $config->maxOutputBytes());
        self::assertFalse($config->printEnabled());
        self::assertSame(ConversionMode::NATIVE_COMPATIBLE, $config->conversionMode());
        self::assertSame($sink, $config->outputSink());
    }

    public function testInvalidValuesThrow(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new SandboxConfig(memoryLimitBytes: 0);
    }

    public function testInvalidConversionModeThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        SandboxConfig::defaults()->withConversionMode('bad-mode');
    }
}
