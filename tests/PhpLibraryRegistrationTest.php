<?php

declare(strict_types=1);

namespace Melmuk\LuaSandboxWrapper\Tests;

use Melmuk\LuaSandboxWrapper\PhpLibraryRegistration;
use PHPUnit\Framework\TestCase;

final class PhpLibraryRegistrationTest extends TestCase
{
    public function testStoresLibraryAndCallbacks(): void
    {
        $registration = new PhpLibraryRegistration(
            'calc',
            ['add' => static fn (int $a, int $b): int => $a + $b],
        );

        self::assertSame('calc', $registration->library());
        self::assertSame(5, $registration->callbacks()['add'](2, 3));
    }

    public function testRejectsInvalidLibraryName(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new PhpLibraryRegistration('bad-name', ['add' => static fn (): int => 1]);
    }

    public function testRejectsInvalidCallbackName(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new PhpLibraryRegistration('calc', ['bad-name' => static fn (): int => 1]);
    }

    public function testRejectsNonCallableCallback(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new PhpLibraryRegistration('calc', ['add' => 'not-callable']);
    }
}
