<?php

declare(strict_types=1);

namespace Melmuk\LuaSandboxWrapper\Exception;

/**
 * Base wrapper exception for all errors raised by this library.
 */
class LuaExecutionException extends \RuntimeException
{
    /**
     * @param string $functionName Lua function name in scope when the error occurred.
     * @param string $phase Execution phase that raised the error.
     */
    public function __construct(
        string $message,
        private readonly string $functionName,
        private readonly string $phase,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    /**
     * Returns the Lua function associated with the failure.
     */
    public function functionName(): string
    {
        return $this->functionName;
    }

    /**
     * Returns the execution phase where the failure occurred.
     */
    public function phase(): string
    {
        return $this->phase;
    }
}
