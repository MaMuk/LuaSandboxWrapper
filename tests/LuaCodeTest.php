<?php

declare(strict_types=1);

namespace Melmuk\LuaSandboxWrapper\Tests;

use Melmuk\LuaSandboxWrapper\LuaCode;
use PHPUnit\Framework\TestCase;

final class LuaCodeTest extends TestCase
{
    public function testFactoryAndAccessors(): void
    {
        $code = LuaCode::forFunction('function transform(data) return data end', 'transform', 'my-chunk');

        self::assertSame('function transform(data) return data end', $code->source());
        self::assertSame('transform', $code->functionName());
        self::assertSame('my-chunk', $code->chunkName());
    }

    public function testEmptySourceThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new LuaCode('');
    }
}
