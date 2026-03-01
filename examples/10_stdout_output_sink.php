<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Melmuk\LuaSandboxWrapper\LuaCode;
use Melmuk\LuaSandboxWrapper\LuaExecutor;
use Melmuk\LuaSandboxWrapper\Output\StdoutOutputSink;
use Melmuk\LuaSandboxWrapper\SandboxConfig;

$config = SandboxConfig::defaults()->withOutputSink(new StdoutOutputSink());
$executor = new LuaExecutor($config);

$executor->execute([], new LuaCode('function execute(data) print("this goes to STDOUT sink") return data end'));
