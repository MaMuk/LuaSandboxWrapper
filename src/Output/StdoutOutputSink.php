<?php

declare(strict_types=1);

namespace Melmuk\LuaSandboxWrapper\Output;

/**
 * Output sink that writes Lua print output directly to STDOUT.
 */
final class StdoutOutputSink implements OutputSink
{
    /**
     * Writes a chunk directly to STDOUT.
     */
    public function write(string $chunk): void
    {
        fwrite(STDOUT, $chunk);
    }
}
