<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Melmuk\LuaSandboxWrapper\Exception\LuaCompilationException;
use Melmuk\LuaSandboxWrapper\Exception\LuaExecutionException;
use Melmuk\LuaSandboxWrapper\Exception\LuaFunctionNotFoundException;
use Melmuk\LuaSandboxWrapper\Exception\LuaRuntimeException;
use Melmuk\LuaSandboxWrapper\Exception\OutputLimitExceededException;
use Melmuk\LuaSandboxWrapper\LuaCode;
use Melmuk\LuaSandboxWrapper\LuaExecutor;
use Melmuk\LuaSandboxWrapper\Output\BufferedOutputSink;
use Melmuk\LuaSandboxWrapper\SandboxConfig;

$executor = new LuaExecutor(
    SandboxConfig::defaults()
        ->withOutputSink(new BufferedOutputSink())
        ->withMaxOutputBytes(8)
);

$cases = [
    'compile' => new LuaCode('function execute(data) return data'),
    'runtime' => new LuaCode('function execute(data) error("boom") end'),
    'missing-function' => new LuaCode('function transform(data) return data end', 'execute'),
    'output-limit' => new LuaCode('function execute(data) print("123456789") return data end'),
];

foreach ($cases as $name => $code) {
    try {
        $executor->execute([], $code);
        echo "{$name}: no exception\n";
    } catch (OutputLimitExceededException $e) {
        echo "{$name}: OutputLimitExceededException phase={$e->phase()} fn={$e->functionName()}\n";
    } catch (LuaFunctionNotFoundException $e) {
        echo "{$name}: LuaFunctionNotFoundException phase={$e->phase()} fn={$e->functionName()}\n";
    } catch (LuaCompilationException $e) {
        echo "{$name}: LuaCompilationException phase={$e->phase()} fn={$e->functionName()}\n";
    } catch (LuaRuntimeException $e) {
        echo "{$name}: LuaRuntimeException phase={$e->phase()} fn={$e->functionName()}\n";
    } catch (LuaExecutionException $e) {
        echo "{$name}: LuaExecutionException phase={$e->phase()} fn={$e->functionName()}\n";
    }
}
