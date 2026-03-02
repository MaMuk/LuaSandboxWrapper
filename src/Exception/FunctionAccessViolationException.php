<?php

declare(strict_types=1);

namespace Melmuk\LuaSandboxWrapper\Exception;

final class FunctionAccessViolationException extends LuaExecutionException
{
    public function __construct(
        string $message,
        string $functionName,
        string $phase,
        private readonly string $symbol,
        private readonly string $source,
        private readonly string $mode,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $functionName, $phase, $previous);
    }

    public function symbol(): string
    {
        return $this->symbol;
    }

    public function source(): string
    {
        return $this->source;
    }

    public function mode(): string
    {
        return $this->mode;
    }
}
