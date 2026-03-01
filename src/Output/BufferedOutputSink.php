<?php

declare(strict_types=1);

namespace Melmuk\LuaSandboxWrapper\Output;

/**
 * In-memory sink useful for tests or API consumers that need buffered output.
 */
final class BufferedOutputSink implements OutputSink
{
    private string $buffer = '';

    /**
     * Appends a chunk to the in-memory buffer.
     */
    public function write(string $chunk): void
    {
        $this->buffer .= $chunk;
    }

    /**
     * Returns the complete buffered output.
     */
    public function buffer(): string
    {
        return $this->buffer;
    }

    /**
     * Clears the internal output buffer.
     */
    public function clear(): void
    {
        $this->buffer = '';
    }
}
