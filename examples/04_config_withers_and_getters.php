<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Melmuk\LuaSandboxWrapper\Output\BufferedOutputSink;
use Melmuk\LuaSandboxWrapper\SandboxConfig;

$sink = new BufferedOutputSink();

$config = SandboxConfig::defaults()
    ->withMemoryLimitBytes(16 * 1024 * 1024)
    ->withCpuLimitSeconds(0.25)
    ->withPrintEnabled(true)
    ->withMaxOutputBytes(10 * 1024)
    ->withOutputSink($sink);

echo "memoryLimitBytes=" . (string) $config->memoryLimitBytes() . PHP_EOL;
echo "cpuLimitSeconds=" . (string) $config->cpuLimitSeconds() . PHP_EOL;
echo "printEnabled=" . ($config->printEnabled() ? 'true' : 'false') . PHP_EOL;
echo "maxOutputBytes=" . $config->maxOutputBytes() . PHP_EOL;
echo "outputSinkClass=" . $config->outputSink()::class . PHP_EOL;
