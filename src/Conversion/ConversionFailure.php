<?php

declare(strict_types=1);

namespace Melmuk\LuaSandboxWrapper\Conversion;

/**
 * Thrown when value conversion fails with a precise path to the offending node.
 */
final class ConversionFailure extends \InvalidArgumentException
{
    /**
     * @param string $message Human-readable conversion failure details.
     * @param string $path Dot/bracket path to the failing value.
     */
    public function __construct(
        string $message,
        private readonly string $path,
    ) {
        parent::__construct($message);
    }

    /**
     * Returns the conversion path where the error occurred.
     */
    public function path(): string
    {
        return $this->path;
    }
}
