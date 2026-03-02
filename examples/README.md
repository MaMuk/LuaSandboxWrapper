# Examples

Run any example from project root:

```bash
php examples/01_basic_execute.php
```

Files:
- `01_basic_execute.php`: simplest `execute()` flow.
- `02_run_with_metrics.php`: `run()` with `ExecutionResult` metrics and output.
- `03_luacode_factory_and_accessors.php`: `LuaCode::forFunction()` and accessors.
- `04_config_withers_and_getters.php`: `SandboxConfig` defaults/withers/getters.
- `05_buffered_output_sink.php`: `BufferedOutputSink` (`write`, `buffer`, `clear`).
- `06_custom_output_sink.php`: custom `OutputSink` implementation.
- `07_disable_print.php`: `withPrintEnabled(false)` behavior.
- `08_exception_handling.php`: all wrapper exception types and metadata.
- `09_injected_sandbox_reuse.php`: injected `LuaSandbox` reuse behavior.
- `11_conversion_strictness.php`: strict conversion success/failure behavior.
- `12_conversion_modes.php`: strict vs native-compatible mode differences.
- `13_function_access_tuning.php`: blacklist/whitelist function overlays and callback policy.
- `14_php_library_registration.php`: exposing PHP callbacks as Lua libraries.
- `15_wrap_php_function.php`: using `LuaExecutor::wrapPhpFunction(...)` directly.
