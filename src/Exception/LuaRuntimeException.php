<?php

declare(strict_types=1);

namespace Melmuk\LuaSandboxWrapper\Exception;

/**
 * Raised when Lua code fails at runtime after successful compilation.
 */
final class LuaRuntimeException extends LuaExecutionException
{
}
