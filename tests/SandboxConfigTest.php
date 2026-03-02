<?php

declare(strict_types=1);

namespace Melmuk\LuaSandboxWrapper\Tests;

use Melmuk\LuaSandboxWrapper\Conversion\ConversionMode;
use Melmuk\LuaSandboxWrapper\FunctionAccess\AccessMode;
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
            ->blacklistLuaGlobals(['math.random'])
            ->blacklistLuaLibraries(['debug'])
            ->blacklistPhpCallbacks(['php.secret'])
            ->withPhpLibrary('calc', ['add' => static fn (int $a, int $b): int => $a + $b])
            ->withOutputSink($sink);

        self::assertSame(8 * 1024 * 1024, $config->memoryLimitBytes());
        self::assertSame(0.25, $config->cpuLimitSeconds());
        self::assertSame(256, $config->maxOutputBytes());
        self::assertFalse($config->printEnabled());
        self::assertSame(ConversionMode::NATIVE_COMPATIBLE, $config->conversionMode());
        self::assertSame(AccessMode::BLACKLIST, $config->functionAccessConfig()->mode());
        self::assertSame(['math.random'], $config->functionAccessConfig()->globals());
        self::assertSame(['debug'], $config->functionAccessConfig()->libraries());
        self::assertSame(AccessMode::BLACKLIST, $config->callbackAccessConfig()->mode());
        self::assertSame(['php.secret'], $config->callbackAccessConfig()->callbacks());
        self::assertCount(1, $config->phpLibraries());
        self::assertSame('calc', $config->phpLibraries()[0]->library());
        self::assertSame(7, ($config->phpLibraries()[0]->callbacks()['add'])(3, 4));
        self::assertSame($sink, $config->outputSink());
    }

    public function testWhitelistHelpersSwitchModes(): void
    {
        $config = SandboxConfig::defaults()
            ->whitelistLuaGlobals(['pairs'])
            ->whitelistLuaLibraries(['string'])
            ->whitelistPhpCallbacks(['php.__wrapper_print']);

        self::assertSame(AccessMode::WHITELIST, $config->functionAccessConfig()->mode());
        self::assertSame(['pairs'], $config->functionAccessConfig()->globals());
        self::assertSame(['string'], $config->functionAccessConfig()->libraries());
        self::assertSame(AccessMode::WHITELIST, $config->callbackAccessConfig()->mode());
        self::assertSame(['php.__wrapper_print'], $config->callbackAccessConfig()->callbacks());
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

    public function testWithPhpLibraryMergesAndOverridesCallbacks(): void
    {
        $config = SandboxConfig::defaults()
            ->withPhpLibrary('calc', [
                'add' => static fn (int $a, int $b): int => $a + $b,
                'mul' => static fn (int $a, int $b): int => $a * $b,
            ])
            ->withPhpLibrary('calc', [
                'add' => static fn (int $a, int $b): int => $a - $b,
            ]);

        $callbacks = $config->phpLibraries()[0]->callbacks();

        self::assertSame(['add', 'mul'], array_keys($callbacks));
        self::assertSame(2, $callbacks['add'](5, 3));
        self::assertSame(15, $callbacks['mul'](5, 3));
    }

    public function testWithPhpCallbackUsesDefaultPhpLibrary(): void
    {
        $config = SandboxConfig::defaults()
            ->withPhpCallback('sum', static fn (int $a, int $b): int => $a + $b);

        self::assertCount(1, $config->phpLibraries());
        self::assertSame('php', $config->phpLibraries()[0]->library());
        self::assertSame(3, ($config->phpLibraries()[0]->callbacks()['sum'])(1, 2));
    }

    public function testInvalidPhpLibraryIdentifierThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        SandboxConfig::defaults()->withPhpLibrary('bad-name', ['sum' => static fn (): int => 1]);
    }

    public function testInvalidPhpCallbackDefinitionThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        SandboxConfig::defaults()->withPhpLibrary('calc', ['sum' => 'not-a-callable']);
    }
}
