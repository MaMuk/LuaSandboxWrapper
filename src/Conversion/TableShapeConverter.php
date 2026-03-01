<?php

declare(strict_types=1);

namespace Melmuk\LuaSandboxWrapper\Conversion;

/**
 * Strict conversion between PHP values and Lua-table-compatible shapes.
 */
final class TableShapeConverter
{
    private const MAX_DEPTH = 64;
    private const MAX_SAFE_LUA_INT = 9007199254740992;

    /**
     * Converts a PHP value into a Lua-compatible value shape.
     */
    public function toLuaValue(mixed $value, string $mode = ConversionMode::STRICT): mixed
    {
        $this->guardMode($mode);
        if ($mode === ConversionMode::NATIVE_COMPATIBLE) {
            return $this->phpToLuaNative($value, '$', 0);
        }

        return $this->phpToLuaStrict($value, '$', 0);
    }

    /**
     * Converts a Lua-converted PHP value back into a consumer-facing PHP value.
     */
    public function toPhpValue(mixed $value, string $mode = ConversionMode::STRICT): mixed
    {
        $this->guardMode($mode);
        if ($mode === ConversionMode::NATIVE_COMPATIBLE) {
            return $this->luaToPhpNative($value, '$', 0);
        }

        return $this->luaToPhpStrict($value, '$', 0);
    }

    private function phpToLuaStrict(mixed $value, string $path, int $depth): mixed
    {
        $this->guardDepth($path, $depth);

        if (is_null($value) || is_bool($value) || is_int($value) || is_float($value) || is_string($value)) {
            return $value;
        }

        if (is_array($value)) {
            return $this->phpArrayToLuaTableStrict($value, $path, $depth + 1);
        }

        if ($value instanceof \stdClass) {
            /** @var array<string, mixed> $properties */
            $properties = get_object_vars($value);
            return $this->phpAssocToLuaTableStrict($properties, $path, $depth + 1);
        }

        throw new ConversionFailure(
            sprintf('Unsupported PHP type "%s" at %s. Allowed: scalar, null, array, stdClass.', get_debug_type($value), $path),
            $path,
        );
    }

    /**
     * @param array<mixed> $value
     * @return array<mixed>
     */
    private function phpArrayToLuaTableStrict(array $value, string $path, int $depth): array
    {
        if ($value === []) {
            return [];
        }

        $keys = array_keys($value);
        $allInt = $this->allKeysAreInt($keys);
        $allString = $this->allKeysAreString($keys);

        if ($allInt) {
            if (!$this->isZeroBasedConsecutiveList($keys)) {
                throw new ConversionFailure(
                    sprintf(
                        'Numeric-key array at %s must be a contiguous 0-based list. Non-contiguous or sparse numeric arrays are rejected.',
                        $path,
                    ),
                    $path,
                );
            }

            $luaList = [];
            $index = 1;
            foreach ($value as $item) {
                $luaList[$index] = $this->phpToLuaStrict($item, $path . '[' . ($index - 1) . ']', $depth);
                $index++;
            }

            return $luaList;
        }

        if ($allString) {
            /** @var array<string, mixed> $value */
            return $this->phpAssocToLuaTableStrict($value, $path, $depth);
        }

        throw new ConversionFailure(
            sprintf('Mixed string/integer keys at %s are not supported. Use either a list or an associative map.', $path),
            $path,
        );
    }

    /**
     * @param array<string, mixed> $value
     * @return array<string, mixed>
     */
    private function phpAssocToLuaTableStrict(array $value, string $path, int $depth): array
    {
        $luaMap = [];
        foreach ($value as $key => $item) {
            if ($key === '') {
                throw new ConversionFailure(sprintf('Empty string keys are not supported at %s.', $path), $path);
            }

            $luaMap[$key] = $this->phpToLuaStrict($item, $path . '.' . $key, $depth);
        }

        return $luaMap;
    }

    private function luaToPhpStrict(mixed $value, string $path, int $depth): mixed
    {
        $this->guardDepth($path, $depth);

        if (is_null($value) || is_bool($value) || is_int($value) || is_float($value) || is_string($value)) {
            return $value;
        }

        if (is_array($value)) {
            return $this->luaTableToPhpStrict($value, $path, $depth + 1);
        }

        throw new ConversionFailure(
            sprintf('Unsupported Lua-converted PHP type "%s" at %s.', get_debug_type($value), $path),
            $path,
        );
    }

    /**
     * @param array<mixed> $value
     * @return array<mixed>
     */
    private function luaTableToPhpStrict(array $value, string $path, int $depth): array
    {
        if ($value === []) {
            return [];
        }

        $keys = array_keys($value);

        if ($this->allKeysAreInt($keys) && $this->isOneBasedConsecutiveList($keys)) {
            $phpList = [];
            $max = count($keys);
            for ($luaIndex = 1; $luaIndex <= $max; $luaIndex++) {
                $phpList[] = $this->luaToPhpStrict($value[$luaIndex], $path . '[' . ($luaIndex - 1) . ']', $depth);
            }

            return $phpList;
        }

        $phpMap = [];
        foreach ($value as $key => $item) {
            if (!is_int($key) && !is_string($key)) {
                throw new ConversionFailure(
                    sprintf('Unsupported Lua table key type "%s" at %s.', get_debug_type($key), $path),
                    $path,
                );
            }

            $segment = is_int($key) ? '[' . $key . ']' : '.' . $key;
            $phpMap[$key] = $this->luaToPhpStrict($item, $path . $segment, $depth);
        }

        return $phpMap;
    }

    /**
     * @param array<int, int|string> $keys
     */
    private function allKeysAreInt(array $keys): bool
    {
        foreach ($keys as $key) {
            if (!is_int($key)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<int, int|string> $keys
     */
    private function allKeysAreString(array $keys): bool
    {
        foreach ($keys as $key) {
            if (!is_string($key)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<int, int> $keys
     */
    private function isZeroBasedConsecutiveList(array $keys): bool
    {
        sort($keys);

        $expected = 0;
        foreach ($keys as $key) {
            if ($key !== $expected) {
                return false;
            }
            $expected++;
        }

        return true;
    }

    /**
     * @param array<int, int> $keys
     */
    private function isOneBasedConsecutiveList(array $keys): bool
    {
        sort($keys);

        $expected = 1;
        foreach ($keys as $key) {
            if ($key !== $expected) {
                return false;
            }
            $expected++;
        }

        return true;
    }

    private function guardDepth(string $path, int $depth): void
    {
        if ($depth <= self::MAX_DEPTH) {
            return;
        }

        throw new ConversionFailure(
            sprintf('Maximum nesting depth (%d) exceeded at %s.', self::MAX_DEPTH, $path),
            $path,
        );
    }

    private function phpToLuaNative(mixed $value, string $path, int $depth): mixed
    {
        $this->guardDepth($path, $depth);

        if (is_null($value) || is_bool($value) || is_int($value) || is_float($value) || is_string($value)) {
            return $value;
        }

        if (!is_array($value)) {
            throw new ConversionFailure(
                sprintf(
                    'Unsupported PHP type "%s" at %s in native-compatible mode. Only values supported by ext-luasandbox are accepted.',
                    get_debug_type($value),
                    $path,
                ),
                $path,
            );
        }

        $luaTable = [];
        foreach ($value as $key => $item) {
            $luaKey = $key;
            if (is_int($key) && ($key > self::MAX_SAFE_LUA_INT || $key < -self::MAX_SAFE_LUA_INT)) {
                $luaKey = (string) $key;
            }

            $segment = is_int($key) ? '[' . $key . ']' : '.' . $key;
            $luaTable[$luaKey] = $this->phpToLuaNative($item, $path . $segment, $depth + 1);
        }

        return $luaTable;
    }

    private function luaToPhpNative(mixed $value, string $path, int $depth): mixed
    {
        $this->guardDepth($path, $depth);

        if (!is_array($value)) {
            return $value;
        }

        $php = [];
        foreach ($value as $key => $item) {
            $segment = is_int($key) ? '[' . $key . ']' : '.' . (string) $key;
            $php[$key] = $this->luaToPhpNative($item, $path . $segment, $depth + 1);
        }

        return $php;
    }

    private function guardMode(string $mode): void
    {
        if (ConversionMode::isValid($mode)) {
            return;
        }

        throw new ConversionFailure(sprintf('Unsupported conversion mode "%s".', $mode), '$');
    }
}
