<?php

declare(strict_types=1);

namespace Melmuk\LuaSandboxWrapper\Exception;

/**
 * Raised when input/output value conversion fails around Lua execution.
 */
final class DataConversionException extends LuaExecutionException
{
    /**
     * @param string $path Dot/bracket path to the value that failed conversion.
     */
    public function __construct(
        string $message,
        string $functionName,
        string $phase,
        private readonly string $path,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $functionName, $phase, $previous);
    }

    /**
     * Returns the conversion path that caused the exception.
     */
    public function path(): string
    {
        return $this->path;
    }
}
