<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Melmuk\LuaSandboxWrapper\LuaCode;
use Melmuk\LuaSandboxWrapper\LuaExecutor;
use Melmuk\LuaSandboxWrapper\Output\BufferedOutputSink;
use Melmuk\LuaSandboxWrapper\SandboxConfig;

$sink = new BufferedOutputSink();
$config = SandboxConfig::defaults()
    ->withOutputSink($sink)
    ->withPrintEnabled(false);

$executor = new LuaExecutor($config);
$run = $executor->run(
    ['id' => 1],
    new LuaCode('function execute(data) print("this is suppressed") return data end')
);

echo 'captured-output-length=' . strlen($run->output()) . PHP_EOL;
echo 'sink-length=' . strlen($sink->buffer()) . PHP_EOL;
