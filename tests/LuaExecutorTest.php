<?php

declare(strict_types=1);

namespace Melmuk\LuaSandboxWrapper\Tests;

use Melmuk\LuaSandboxWrapper\Conversion\ConversionMode;
use Melmuk\LuaSandboxWrapper\Exception\DataConversionException;
use Melmuk\LuaSandboxWrapper\Exception\FunctionAccessViolationException;
use Melmuk\LuaSandboxWrapper\Exception\LuaCompilationException;
use Melmuk\LuaSandboxWrapper\Exception\LuaFunctionNotFoundException;
use Melmuk\LuaSandboxWrapper\Exception\OutputLimitExceededException;
use Melmuk\LuaSandboxWrapper\LuaCode;
use Melmuk\LuaSandboxWrapper\LuaExecutor;
use Melmuk\LuaSandboxWrapper\Output\BufferedOutputSink;
use Melmuk\LuaSandboxWrapper\SandboxConfig;
use PHPUnit\Framework\TestCase;

final class LuaExecutorTest extends TestCase
{
    protected function setUp(): void
    {
        if (!extension_loaded('luasandbox')) {
            self::markTestSkipped('ext-luasandbox is not installed.');
        }
    }

    public function testRunReturnsMutatedDataAndCapturedOutput(): void
    {
        $sink = new BufferedOutputSink();
        $config = SandboxConfig::defaults()->withOutputSink($sink)->withMaxOutputBytes(0);

        $executor = new LuaExecutor($config);
        $code = new LuaCode(<<<'LUA'
function execute(data)
    print("processing", data.id)
    data.handled = true
    return data
end
LUA);

        $result = $executor->run(['id' => 42], $code);

        self::assertSame(['id' => 42, 'handled' => true], $result->value());
        self::assertSame("processing\t42\n", $result->output());
        self::assertSame("processing\t42\n", $sink->buffer());
        self::assertGreaterThanOrEqual(0.0, $result->durationMs());
    }

    public function testWrapPhpFunctionIsExposedPublicly(): void
    {
        $executor = new LuaExecutor(SandboxConfig::defaults()->withOutputSink(new BufferedOutputSink()));
        $wrapped = $executor->wrapPhpFunction(static fn (int $a, int $b): int => $a + $b);

        self::assertIsObject($wrapped);
        self::assertTrue(method_exists($wrapped, 'call'));
    }

    public function testBlacklistCanDisableSpecificGlobalFunction(): void
    {
        $executor = new LuaExecutor(
            SandboxConfig::defaults()
                ->blacklistLuaGlobals(['math.random'])
                ->withOutputSink(new BufferedOutputSink())
        );

        $result = $executor->execute(
            ['x' => 1],
            new LuaCode(<<<'LUA'
function execute(data)
    return { random_disabled = (math.random == nil), math_still_available = (math ~= nil) }
end
LUA)
        );

        self::assertSame(['math_still_available' => true, 'random_disabled' => true], $result);
    }

    public function testWhitelistKeepsOnlyConfiguredGlobals(): void
    {
        $executor = new LuaExecutor(
            SandboxConfig::defaults()
                ->whitelistLuaGlobals(['pairs'])
                ->withOutputSink(new BufferedOutputSink())
        );

        $result = $executor->execute(
            ['x' => 1],
            new LuaCode(<<<'LUA'
function execute(data)
    return { has_pairs = (pairs ~= nil), has_math = (math ~= nil), has_type = (type ~= nil) }
end
LUA)
        );

        self::assertSame(['has_pairs' => true, 'has_math' => false, 'has_type' => false], $result);
    }

    public function testWhitelistCallbackPolicyCanBlockWrapperPrint(): void
    {
        $executor = new LuaExecutor(
            SandboxConfig::defaults()
                ->whitelistPhpCallbacks([])
                ->withOutputSink(new BufferedOutputSink())
        );

        $this->expectException(FunctionAccessViolationException::class);

        $executor->execute(['x' => 1], new LuaCode('function execute(data) return data end'));
    }

    public function testCallableRebindingWorks(): void
    {
        $executor = new LuaExecutor(
            SandboxConfig::defaults()
                ->withPrintEnabled(false)
                ->rebindLuaGlobal('add', static fn (int $a, int $b): int => $a + $b)
                ->withOutputSink(new BufferedOutputSink())
        );

        $result = $executor->execute(
            ['x' => 1],
            new LuaCode(<<<'LUA'
function execute(data)
    return { value = add(2, 3) }
end
LUA)
        );

        self::assertSame(['value' => 5], $result);
    }

    public function testRegisteredPhpLibraryCanBeCalledFromLua(): void
    {
        $executor = new LuaExecutor(
            SandboxConfig::defaults()
                ->withPrintEnabled(false)
                ->withPhpLibrary('calc', ['add' => static fn (int $a, int $b): int => $a + $b])
                ->withOutputSink(new BufferedOutputSink())
        );

        $result = $executor->execute(
            [],
            new LuaCode(<<<'LUA'
function execute(data)
    return { value = calc.add(2, 3) }
end
LUA)
        );

        self::assertSame(['value' => 5], $result);
    }

    public function testCanRegisterMultiplePhpLibrariesInOneRun(): void
    {
        $executor = new LuaExecutor(
            SandboxConfig::defaults()
                ->withPrintEnabled(false)
                ->withPhpLibrary('calc', ['add' => static fn (int $a, int $b): int => $a + $b])
                ->withPhpLibrary('textx', ['join' => static fn (string $a, string $b): string => $a . $b])
                ->withOutputSink(new BufferedOutputSink())
        );

        $result = $executor->execute(
            [],
            new LuaCode(<<<'LUA'
function execute(data)
    return { sum = calc.add(4, 6), joined = textx.join("ab", "cd") }
end
LUA)
        );

        self::assertSame(['sum' => 10, 'joined' => 'abcd'], $result);
    }

    public function testCallbackPolicyCanBlockRegisteredLibraryCallback(): void
    {
        $executor = new LuaExecutor(
            SandboxConfig::defaults()
                ->withPrintEnabled(false)
                ->withPhpLibrary('calc', ['add' => static fn (int $a, int $b): int => $a + $b])
                ->whitelistPhpCallbacks([])
                ->withOutputSink(new BufferedOutputSink())
        );

        try {
            $executor->execute([], new LuaCode('function execute(data) return data end'));
            self::fail('Expected FunctionAccessViolationException to be thrown.');
        } catch (FunctionAccessViolationException $exception) {
            self::assertSame('calc.add', $exception->symbol());
            self::assertSame('php-callback', $exception->source());
            self::assertSame('whitelist', $exception->mode());
            self::assertSame('callback-policy', $exception->phase());
            self::assertSame('execute', $exception->functionName());
        }
    }

    public function testPerRunSandboxReappliesPhpLibraryRegistration(): void
    {
        $executor = new LuaExecutor(
            SandboxConfig::defaults()
                ->withPrintEnabled(false)
                ->withPhpLibrary('calc', ['add' => static fn (int $a, int $b): int => $a + $b])
                ->withOutputSink(new BufferedOutputSink())
        );

        $code = new LuaCode(<<<'LUA'
function execute(data)
    return calc.add(data.a, data.b)
end
LUA);

        self::assertSame(3, $executor->execute(['a' => 1, 'b' => 2], $code));
        self::assertSame(7, $executor->execute(['a' => 3, 'b' => 4], $code));
    }

    public function testInjectedSandboxRetainsPhpLibraryRegistrationAcrossCalls(): void
    {
        $executor = new LuaExecutor(
            SandboxConfig::defaults()
                ->withPrintEnabled(false)
                ->withPhpLibrary('calc', ['add' => static fn (int $a, int $b): int => $a + $b])
                ->withOutputSink(new BufferedOutputSink()),
            new \LuaSandbox(),
        );

        $code = new LuaCode(<<<'LUA'
function execute(data)
    return calc.add(data.a, data.b)
end
LUA);

        self::assertSame(3, $executor->execute(['a' => 1, 'b' => 2], $code));
        self::assertSame(9, $executor->execute(['a' => 4, 'b' => 5], $code));
    }

    public function testRegisteredCallbacksAreWrappedForLuaReturnShape(): void
    {
        $executor = new LuaExecutor(
            SandboxConfig::defaults()
                ->withPrintEnabled(false)
                ->withPhpLibrary('ret', [
                    'scalar' => static fn (): int => 7,
                    'none' => static fn () => null,
                    'multi' => static fn (): array => [1, 2],
                ])
                ->withOutputSink(new BufferedOutputSink())
        );

        $result = $executor->execute(
            [],
            new LuaCode(<<<'LUA'
function execute(data)
    local x = ret.scalar()
    local is_nil = (ret.none() == nil)
    local a, b = ret.multi()
    return { x = x, is_nil = is_nil, a = a, b = b }
end
LUA)
        );

        self::assertSame(7, $result['x']);
        self::assertTrue($result['is_nil']);
        self::assertSame(1, $result['a']);
        self::assertSame(2, $result['b']);
    }

    public function testPhpListIsConvertedToLuaSequenceForIpairs(): void
    {
        $executor = new LuaExecutor(SandboxConfig::defaults()->withOutputSink(new BufferedOutputSink()));

        $result = $executor->execute(
            ['scores' => [10, 20, 30]],
            new LuaCode(<<<'LUA'
function execute(data)
    local total = 0
    for _, score in ipairs(data.scores) do
        total = total + score
    end
    return { total = total }
end
LUA)
        );

        self::assertSame(['total' => 60], $result);
    }

    public function testLuaOneBasedSequenceReturnsAsZeroBasedPhpList(): void
    {
        $executor = new LuaExecutor(SandboxConfig::defaults()->withOutputSink(new BufferedOutputSink()));

        $result = $executor->execute(
            ['x' => 1],
            new LuaCode(<<<'LUA'
function execute(data)
    return {"a", "b", "c"}
end
LUA)
        );

        self::assertSame(['a', 'b', 'c'], $result);
    }

    public function testInvalidSparseNumericInputFailsExplicitly(): void
    {
        $executor = new LuaExecutor(SandboxConfig::defaults()->withOutputSink(new BufferedOutputSink()));

        $this->expectException(DataConversionException::class);

        $executor->execute(
            ['scores' => [1 => 20, 2 => 30]],
            new LuaCode('function execute(data) return data end')
        );
    }

    public function testNativeCompatibleAllowsSparseNumericInput(): void
    {
        $config = SandboxConfig::defaults()
            ->withConversionMode(ConversionMode::NATIVE_COMPATIBLE)
            ->withOutputSink(new BufferedOutputSink());

        $executor = new LuaExecutor($config);
        $result = $executor->execute(
            ['scores' => [1 => 20, 2 => 30]],
            new LuaCode(<<<'LUA'
function execute(data)
    local total = 0
    for _, score in ipairs(data.scores) do
        total = total + score
    end
    return { total = total }
end
LUA)
        );

        self::assertSame(['total' => 50], $result);
    }

    public function testCompilationErrorsAreWrapped(): void
    {
        $executor = new LuaExecutor(SandboxConfig::defaults()->withOutputSink(new BufferedOutputSink()));

        $this->expectException(LuaCompilationException::class);

        $executor->execute(['id' => 1], new LuaCode('function execute(data) return data'));
    }

    public function testMissingFunctionThrowsDomainException(): void
    {
        $executor = new LuaExecutor(SandboxConfig::defaults()->withOutputSink(new BufferedOutputSink()));

        $this->expectException(LuaFunctionNotFoundException::class);

        $executor->execute(
            ['id' => 1],
            new LuaCode('function transform(data) return data end', 'execute')
        );
    }

    public function testOutputLimitIsEnforced(): void
    {
        $config = SandboxConfig::defaults()
            ->withOutputSink(new BufferedOutputSink())
            ->withMaxOutputBytes(8);

        $executor = new LuaExecutor($config);

        $this->expectException(OutputLimitExceededException::class);

        $executor->execute(
            ['id' => 1],
            new LuaCode(<<<'LUA'
function execute(data)
    print("this-is-too-long")
    return data
end
LUA)
        );
    }
}
