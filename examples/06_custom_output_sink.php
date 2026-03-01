<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Melmuk\LuaSandboxWrapper\LuaCode;
use Melmuk\LuaSandboxWrapper\LuaExecutor;
use Melmuk\LuaSandboxWrapper\Output\OutputSink;
use Melmuk\LuaSandboxWrapper\SandboxConfig;

final class PrefixSink implements OutputSink
{
    private array $lines = [];

    public function write(string $chunk): void
    {
        $this->lines[] = '[lua] ' . rtrim($chunk, "\n");
    }

    public function lines(): array
    {
        return $this->lines;
    }
}

$sink = new PrefixSink();
$config = SandboxConfig::defaults()->withOutputSink($sink);
$executor = new LuaExecutor($config);

$executor->execute(
    ['id' => 101],
    new LuaCode('function execute(data) print("custom sink", data.id) return data end')
);

print_r($sink->lines());
