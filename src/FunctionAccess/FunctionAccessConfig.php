<?php

declare(strict_types=1);

namespace Melmuk\LuaSandboxWrapper\FunctionAccess;

/**
 * Runtime Lua function/library access overlay configuration.
 */
final class FunctionAccessConfig
{
    /**
     * @param array<int, string> $globals
     * @param array<int, string> $libraries
     * @param array<string, callable|string> $rebindings
     */
    public function __construct(
        private readonly string $mode = AccessMode::BLACKLIST,
        private readonly array $globals = [],
        private readonly array $libraries = [],
        private readonly array $rebindings = [],
    ) {
        if (!AccessMode::isValid($mode)) {
            throw new \InvalidArgumentException(sprintf(
                'Invalid function access mode "%s". Use "%s" or "%s".',
                $mode,
                AccessMode::BLACKLIST,
                AccessMode::WHITELIST,
            ));
        }

        foreach ($globals as $symbol) {
            self::assertSymbolPath($symbol, 'globals');
        }

        foreach ($libraries as $library) {
            self::assertIdentifier($library, 'libraries');
        }

        foreach ($rebindings as $symbol => $target) {
            self::assertSymbolPath($symbol, 'rebindings');
            if (!is_string($target) && !is_callable($target)) {
                throw new \InvalidArgumentException(sprintf(
                    'Rebinding target for "%s" must be callable|string.',
                    $symbol,
                ));
            }
            if (is_string($target) && trim($target) === '') {
                throw new \InvalidArgumentException(sprintf('Rebinding string target for "%s" cannot be empty.', $symbol));
            }
        }
    }

    public static function blacklist(array $globals = [], array $libraries = []): self
    {
        return new self(AccessMode::BLACKLIST, $globals, $libraries, []);
    }

    public static function whitelist(array $globals = [], array $libraries = []): self
    {
        return new self(AccessMode::WHITELIST, $globals, $libraries, []);
    }

    /**
     * @param array<int, string> $globals
     */
    public function withGlobals(array $globals): self
    {
        return new self($this->mode, self::uniqueStrings($globals), $this->libraries, $this->rebindings);
    }

    /**
     * @param array<int, string> $libraries
     */
    public function withLibraries(array $libraries): self
    {
        return new self($this->mode, $this->globals, self::uniqueStrings($libraries), $this->rebindings);
    }

    /**
     * @param array<string, callable|string> $rebindings
     */
    public function withRebindings(array $rebindings): self
    {
        return new self($this->mode, $this->globals, $this->libraries, $rebindings);
    }

    /**
     * @param array<int, string> $symbols
     */
    public function mergeGlobals(array $symbols): self
    {
        return $this->withGlobals(array_values(array_unique(array_merge($this->globals, $symbols))));
    }

    /**
     * @param array<int, string> $libraries
     */
    public function mergeLibraries(array $libraries): self
    {
        return $this->withLibraries(array_values(array_unique(array_merge($this->libraries, $libraries))));
    }

    public function addRebinding(string $symbol, callable|string $target): self
    {
        $map = $this->rebindings;
        $map[$symbol] = $target;

        return $this->withRebindings($map);
    }

    public function withMode(string $mode): self
    {
        return new self($mode, $this->globals, $this->libraries, $this->rebindings);
    }

    public function mode(): string
    {
        return $this->mode;
    }

    /**
     * @return array<int, string>
     */
    public function globals(): array
    {
        return $this->globals;
    }

    /**
     * @return array<int, string>
     */
    public function libraries(): array
    {
        return $this->libraries;
    }

    /**
     * @return array<string, callable|string>
     */
    public function rebindings(): array
    {
        return $this->rebindings;
    }

    /**
     * @param array<int, string> $values
     * @return array<int, string>
     */
    private static function uniqueStrings(array $values): array
    {
        return array_values(array_unique($values));
    }

    private static function assertSymbolPath(string $symbol, string $context): void
    {
        if ($symbol === '') {
            throw new \InvalidArgumentException(sprintf('Empty symbol in %s is not allowed.', $context));
        }

        foreach (explode('.', $symbol) as $segment) {
            self::assertIdentifier($segment, $context);
        }
    }

    private static function assertIdentifier(string $identifier, string $context): void
    {
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $identifier)) {
            throw new \InvalidArgumentException(sprintf(
                'Invalid identifier "%s" in %s. Use Lua identifier syntax.',
                $identifier,
                $context,
            ));
        }
    }
}
