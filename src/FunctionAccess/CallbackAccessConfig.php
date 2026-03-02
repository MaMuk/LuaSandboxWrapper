<?php

declare(strict_types=1);

namespace Melmuk\LuaSandboxWrapper\FunctionAccess;

/**
 * Controls which PHP callbacks are exposed to Lua.
 */
final class CallbackAccessConfig
{
    /**
     * @param array<int, string> $callbacks
     */
    public function __construct(
        private readonly string $mode = AccessMode::BLACKLIST,
        private readonly array $callbacks = [],
    ) {
        if (!AccessMode::isValid($mode)) {
            throw new \InvalidArgumentException(sprintf(
                'Invalid callback access mode "%s". Use "%s" or "%s".',
                $mode,
                AccessMode::BLACKLIST,
                AccessMode::WHITELIST,
            ));
        }

        foreach ($callbacks as $callback) {
            if ($callback === '') {
                throw new \InvalidArgumentException('Callback names cannot be empty.');
            }
        }
    }

    /**
     * @param array<int, string> $callbacks
     */
    public static function blacklist(array $callbacks = []): self
    {
        return new self(AccessMode::BLACKLIST, $callbacks);
    }

    /**
     * @param array<int, string> $callbacks
     */
    public static function whitelist(array $callbacks = []): self
    {
        return new self(AccessMode::WHITELIST, $callbacks);
    }

    /**
     * @param array<int, string> $callbacks
     */
    public function withCallbacks(array $callbacks): self
    {
        return new self($this->mode, array_values(array_unique($callbacks)));
    }

    /**
     * @param array<int, string> $callbacks
     */
    public function mergeCallbacks(array $callbacks): self
    {
        return $this->withCallbacks(array_values(array_unique(array_merge($this->callbacks, $callbacks))));
    }

    public function withMode(string $mode): self
    {
        return new self($mode, $this->callbacks);
    }

    public function mode(): string
    {
        return $this->mode;
    }

    /**
     * @return array<int, string>
     */
    public function callbacks(): array
    {
        return $this->callbacks;
    }

    public function allows(string $callback): bool
    {
        $listed = in_array($callback, $this->callbacks, true);

        if ($this->mode === AccessMode::WHITELIST) {
            return $listed;
        }

        return !$listed;
    }
}
