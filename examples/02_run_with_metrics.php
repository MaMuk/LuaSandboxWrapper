<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Melmuk\LuaSandboxWrapper\LuaCode;
use Melmuk\LuaSandboxWrapper\LuaExecutor;

$executor = new LuaExecutor();

$execution = $executor->run(
    ['values' => [2, 4, 8]],
    LuaCode::forFunction(<<<'LUA'
function execute(data)
    local total = 0
    for _, v in pairs(data.values) do
        total = total + v
    end

    print("sum", total)
    return { sum = total }
end
LUA)
);

echo "Value:\n";
print_r($execution->value());

echo "Output:\n";
echo $execution->output();

echo "Metrics:\n";
printf(
    "duration_ms=%.3f cpu_seconds=%.6f peak_memory_bytes=%d\n",
    $execution->durationMs(),
    $execution->cpuUsageSeconds(),
    $execution->peakMemoryBytes(),
);
