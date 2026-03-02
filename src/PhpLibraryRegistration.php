<?php

declare(strict_types=1);

namespace Melmuk\LuaSandboxWrapper;

/**
 * Immutable registration of PHP callbacks under a Lua library table.
 */
final class PhpLibraryRegistration
{
    /**
     * @param array<string, callable> $callbacks
     */
    public function __construct(
        private readonly string $library,
        private readonly array $callbacks,
    ) {
        self::assertIdentifier($library, 'library');

        foreach ($callbacks as $name => $callback) {
            if (!is_string($name)) {
                throw new \InvalidArgumentException('Callback map keys must be Lua function names.');
            }

            self::assertIdentifier($name, 'callback');

            if (!is_callable($callback)) {
                throw new \InvalidArgumentException(sprintf(
                    'Callback "%s.%s" must be callable.',
                    $library,
                    $name,
                ));
            }
        }
    }

    public function library(): string
    {
        return $this->library;
    }

    /**
     * @return array<string, callable>
     */
    public function callbacks(): array
    {
        return $this->callbacks;
    }

    /**
     * @param array<string, callable> $callbacks
     */
    public function withMergedCallbacks(array $callbacks): self
    {
        return new self($this->library, array_merge($this->callbacks, $callbacks));
    }

    private static function assertIdentifier(string $value, string $context): void
    {
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $value)) {
            throw new \InvalidArgumentException(sprintf(
                'Invalid %s identifier "%s". Use Lua identifier syntax.',
                $context,
                $value,
            ));
        }
    }
}
