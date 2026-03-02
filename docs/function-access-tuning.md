# Function Access Tuning

This document explains how to tune Lua function and callback exposure in the wrapper.

This is a **developer-control feature** for convenience/performance/contract clarity, not a replacement for LuaSandbox's baseline sandboxing.

## Baseline

The extension already enforces a sandbox baseline.

Unavailable by default (extension-level):

- `dofile`, `loadfile`, `io`
- `package` (`require`, `module`)
- `load`, `loadstring`
- `print`
- most of `os` (except `os.clock`, `os.date`, `os.difftime`, `os.time`)
- most of `debug` (except `debug.traceback`)
- `string.dump`
- `collectgarbage`, `gcinfo`, `coroutine`

Modified behavior includes:

- `pcall`/`xpcall` timeout behavior differences
- patched `string.match`
- replaced `math.random`/`math.randomseed`
- `pairs`/`ipairs` supporting `__pairs`/`__ipairs`


Wrapper tuning works as an overlay on top of that baseline.

## Default behavior

Default mode is a blacklist overlay with no extra entries:

```php
<?php

use Melmuk\LuaSandboxWrapper\LuaExecutor;

$executor = new LuaExecutor();
```

## Common tuning patterns

## 1) Blacklist specific globals

```php
<?php

use Melmuk\LuaSandboxWrapper\LuaExecutor;
use Melmuk\LuaSandboxWrapper\SandboxConfig;

$executor = new LuaExecutor(
    SandboxConfig::defaults()
        ->blacklistLuaGlobals(['math.random', 'debug.traceback'])
);
```

## 2) Blacklist full libraries

```php
<?php

$executor = new LuaExecutor(
    SandboxConfig::defaults()
        ->blacklistLuaLibraries(['os'])
);
```

## 3) Strict whitelist mode for deterministic scripts

```php
<?php

$executor = new LuaExecutor(
    SandboxConfig::defaults()
        ->whitelistLuaGlobals(['pairs', 'ipairs'])
        ->whitelistLuaLibraries(['string'])
);
```

## 4) Rebind Lua global to PHP callable

```php
<?php

$executor = new LuaExecutor(
    SandboxConfig::defaults()
        ->withPrintEnabled(false)
        ->rebindLuaGlobal('add', static fn (int $a, int $b): int => $a + $b)
);
```

## 5) Tune PHP callback exposure

```php
<?php

$executor = new LuaExecutor(
    SandboxConfig::defaults()
        ->blacklistPhpCallbacks(['php.internalDebug'])
);
```

Whitelist callback mode:

```php
<?php

$executor = new LuaExecutor(
    SandboxConfig::defaults()
        ->whitelistPhpCallbacks(['php.__wrapper_print'])
);
```

## Advanced config objects

For complex composition, use explicit config objects:

- `Melmuk\LuaSandboxWrapper\FunctionAccess\FunctionAccessConfig`
- `Melmuk\LuaSandboxWrapper\FunctionAccess\CallbackAccessConfig`
- `Melmuk\LuaSandboxWrapper\FunctionAccess\AccessMode`

Most users should prefer the `SandboxConfig` helper methods.

## Behavior notes

- Blacklist mode starts from extension baseline and removes listed entries.
- Whitelist mode keeps only listed entries (plus internal helpers during policy application).
- Callback policy is enforced for wrapper-injected callbacks too.
- Policy violations throw `FunctionAccessViolationException`.

## What this cannot do

- It cannot resurrect extension-level disabled internals as original built-ins.
- It can only control what the wrapper exposes or rebinds at runtime.

## Exceptions

- `FunctionAccessViolationException`: callback denied by policy.
- Other execution errors still map through the standard wrapper exception hierarchy.

## Related docs

- `docs/differences-from-standard-lua.txt`
- `docs/quickstart.md`
- `docs/reference.md`
- `examples/13_function_access_tuning.php`
