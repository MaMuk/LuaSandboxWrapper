<?php

declare(strict_types=1);

namespace Melmuk\LuaSandboxWrapper\Exception;

/**
 * Raised when captured Lua print output exceeds the configured byte limit.
 */
final class OutputLimitExceededException extends LuaExecutionException
{
}
