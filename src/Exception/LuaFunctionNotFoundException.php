<?php

declare(strict_types=1);

namespace Melmuk\LuaSandboxWrapper\Exception;

/**
 * Raised when the configured Lua function cannot be resolved as callable.
 */
final class LuaFunctionNotFoundException extends LuaExecutionException
{
}
