<?php

declare(strict_types=1);

namespace Melmuk\LuaSandboxWrapper\Output;

/**
 * Contract for consuming Lua print output chunks.
 */
interface OutputSink
{
    /**
     * Consumes a chunk of output text.
     */
    public function write(string $chunk): void;
}
