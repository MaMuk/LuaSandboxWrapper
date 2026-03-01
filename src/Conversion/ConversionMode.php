<?php

declare(strict_types=1);

namespace Melmuk\LuaSandboxWrapper\Conversion;

/**
 * Supported value-conversion strategies for PHP<->Lua boundaries.
 */
final class ConversionMode
{
    /**
     * Strict shape conversion with normalized list/map semantics.
     */
    public const STRICT = 'strict';
    /**
     * Native compatibility mode aligned with ext-luasandbox behavior.
     */
    public const NATIVE_COMPATIBLE = 'native-compatible';

    /**
     * Checks whether a conversion mode is supported.
     */
    public static function isValid(string $mode): bool
    {
        return $mode === self::STRICT || $mode === self::NATIVE_COMPATIBLE;
    }
}
