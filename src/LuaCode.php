<?php

declare(strict_types=1);

namespace Melmuk\LuaSandboxWrapper;

/**
 * Immutable Lua code descriptor.
 */
final class LuaCode
{
    /**
     * @param string $source Lua source code to compile and execute.
     * @param string $functionName Global Lua function name to invoke after loading.
     * @param string|null $chunkName Optional chunk name used by LuaSandbox for diagnostics.
     */
    public function __construct(
        private readonly string $source,
        private readonly string $functionName = 'execute',
        private readonly ?string $chunkName = 'user-script',
    ) {
        if ($source === '') {
            throw new \InvalidArgumentException('Lua source cannot be empty.');
        }

        if ($functionName === '') {
            throw new \InvalidArgumentException('Lua function name cannot be empty.');
        }

        if ($chunkName !== null && $chunkName === '') {
            throw new \InvalidArgumentException('Lua chunk name must not be an empty string.');
        }
    }

    /**
     * Named constructor for creating a Lua code descriptor.
     */
    public static function forFunction(string $source, string $functionName = 'execute', ?string $chunkName = 'user-script'): self
    {
        return new self($source, $functionName, $chunkName);
    }

    /**
     * Returns the Lua source code.
     */
    public function source(): string
    {
        return $this->source;
    }

    /**
     * Returns the global Lua function name to execute.
     */
    public function functionName(): string
    {
        return $this->functionName;
    }

    /**
     * Returns the configured chunk name used for compilation diagnostics.
     */
    public function chunkName(): ?string
    {
        return $this->chunkName;
    }
}
