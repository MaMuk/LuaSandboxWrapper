<?php

declare(strict_types=1);

namespace Melmuk\LuaSandboxWrapper\FunctionAccess;

final class AccessMode
{
    public const BLACKLIST = 'blacklist';
    public const WHITELIST = 'whitelist';

    public static function isValid(string $mode): bool
    {
        return $mode === self::BLACKLIST || $mode === self::WHITELIST;
    }
}
