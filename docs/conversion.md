# Type and Shape Conversion

The wrapper supports two explicit conversion modes:

- `strict` (default)
- `native-compatible`

Set mode via `SandboxConfig::withConversionMode(...)`.

## Why modes exist

- `strict` optimizes for deterministic data contracts and early failure on ambiguous shapes.
- `native-compatible` optimizes for parity with what `ext-luasandbox` can handle.

Rule: if something is possible with the extension, it should be possible with the library by selecting `native-compatible`.

## Mode summary

### `strict`

Opinionated behavior:
- no mixed-key PHP arrays
- no sparse numeric PHP arrays
- PHP list `0..n-1` becomes Lua sequence `1..n`
- Lua sequence `1..n` becomes PHP list `0..n-1`
- `stdClass` is accepted as map
- arbitrary objects are rejected

### `native-compatible`

Extension-aligned behavior:
- mixed-key arrays are allowed
- sparse numeric arrays are allowed
- numeric keys are preserved (no 0-based <-> 1-based remap)
- any non-array object input is rejected (matches extension constraints)
- integer keys with magnitude above 2^53 are stringified before Lua handoff (matches extension logic)

## PHP -> Lua rules

### Scalars (both modes)

Accepted:
- `null`
- `bool`
- `int`
- `float`
- `string`

### Arrays

#### `strict`

Accepted:
- contiguous list: `['a', 'b']` (`0..n-1` keys)
- associative map with string keys only: `['id' => 1]`

Rejected:
- mixed keys: `['a' => 1, 0 => 2]`
- sparse numeric arrays: `[1 => 'x', 2 => 'y']`

#### `native-compatible`

Accepted (if extension can process it):
- contiguous numeric arrays
- sparse numeric arrays
- mixed numeric/string maps

No reindexing is applied by wrapper in this mode.

### Objects

#### `strict`

- `stdClass` accepted as associative map from public properties.
- other objects rejected.

#### `native-compatible`

- all objects rejected for input conversion (except extension-level internals outside wrapper payload design).

## Lua -> PHP rules

### `strict`

- scalar values: pass through
- table with keys `1..n`: converted to PHP list `0..n-1`
- other tables: converted to map preserving key types

### `native-compatible`

- wrapper keeps key layout as returned by extension
- no sequence reindex normalization

## Error behavior

When conversion fails:
- exception: `Melmuk\LuaSandboxWrapper\Exception\DataConversionException`
- metadata:
  - `phase()`: `convert-input` or `convert-output`
  - `functionName()`
  - `path()` (exact failing path)

This is intentional: no silent fallback coercion.

## Practical guidance

- Choose `strict` for application-level contracts and predictable JSON-like shapes.
- Choose `native-compatible` when migrating existing raw `LuaSandbox` usage or relying on sparse/mixed table keys.
- Be explicit in config so behavior is obvious in code review.

## Example

```php
<?php

use Melmuk\LuaSandboxWrapper\Conversion\ConversionMode;
use Melmuk\LuaSandboxWrapper\SandboxConfig;

$config = SandboxConfig::defaults()
    ->withConversionMode(ConversionMode::NATIVE_COMPATIBLE);
```
