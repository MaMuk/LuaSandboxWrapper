<?php

declare(strict_types=1);

namespace Melmuk\LuaSandboxWrapper\Tests;

use Melmuk\LuaSandboxWrapper\Conversion\ConversionMode;
use Melmuk\LuaSandboxWrapper\Conversion\ConversionFailure;
use Melmuk\LuaSandboxWrapper\Conversion\TableShapeConverter;
use PHPUnit\Framework\TestCase;

final class TableShapeConverterTest extends TestCase
{
    private TableShapeConverter $converter;

    protected function setUp(): void
    {
        $this->converter = new TableShapeConverter();
    }

    public function testPhpListBecomesLuaOneBasedSequence(): void
    {
        $lua = $this->converter->toLuaValue(['a', 'b']);

        self::assertSame([1 => 'a', 2 => 'b'], $lua);
    }

    public function testPhpAssocMapKeepsStringKeys(): void
    {
        $lua = $this->converter->toLuaValue(['name' => 'x', 'active' => true]);

        self::assertSame(['name' => 'x', 'active' => true], $lua);
    }

    public function testStdClassConvertsToMap(): void
    {
        $obj = new \stdClass();
        $obj->name = 'martin';

        $lua = $this->converter->toLuaValue($obj);
        self::assertSame(['name' => 'martin'], $lua);
    }

    public function testMixedKeysAreRejected(): void
    {
        $this->expectException(ConversionFailure::class);
        $this->converter->toLuaValue(['a' => 1, 0 => 2]);
    }

    public function testSparseNumericArrayIsRejected(): void
    {
        $this->expectException(ConversionFailure::class);
        $this->converter->toLuaValue([1 => 'x', 2 => 'y']);
    }

    public function testUnsupportedObjectIsRejected(): void
    {
        $this->expectException(ConversionFailure::class);
        $this->converter->toLuaValue(new \DateTimeImmutable());
    }

    public function testLuaOneBasedSequenceBecomesPhpList(): void
    {
        $php = $this->converter->toPhpValue([1 => 'a', 2 => 'b']);

        self::assertSame(['a', 'b'], $php);
    }

    public function testLuaMapWithNumericKeysStaysMap(): void
    {
        $php = $this->converter->toPhpValue([0 => 'zero', 2 => 'two']);

        self::assertSame([0 => 'zero', 2 => 'two'], $php);
    }

    public function testNativeCompatibleAllowsSparseAndMixedKeys(): void
    {
        $lua = $this->converter->toLuaValue(
            [1 => 'x', 'name' => 'n'],
            ConversionMode::NATIVE_COMPATIBLE
        );

        self::assertSame([1 => 'x', 'name' => 'n'], $lua);
    }

    public function testNativeCompatibleDoesNotReindexLuaSequences(): void
    {
        $php = $this->converter->toPhpValue(
            [1 => 'a', 2 => 'b'],
            ConversionMode::NATIVE_COMPATIBLE
        );

        self::assertSame([1 => 'a', 2 => 'b'], $php);
    }
}
