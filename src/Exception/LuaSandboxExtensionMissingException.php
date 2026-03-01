<?php

declare(strict_types=1);

namespace Melmuk\LuaSandboxWrapper\Exception;

/**
 * Raised when the required ext-luasandbox PHP extension is unavailable.
 */
final class LuaSandboxExtensionMissingException extends \RuntimeException
{
    /**
     * Creates a standardized extension-missing message.
     */
    public function __construct()
    {
        parent::__construct(
            'The PHP extension "ext-luasandbox" is required but not loaded. ' .
            'Install/enable luasandbox for the current PHP runtime and verify with: php --ri luasandbox'
        );
    }
}
