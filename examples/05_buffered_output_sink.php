<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Melmuk\LuaSandboxWrapper\Output\BufferedOutputSink;

$sink = new BufferedOutputSink();

$sink->write("line-one\n");
$sink->write("line-two\n");

echo "Buffered:\n";
echo $sink->buffer();

$sink->clear();
echo "After clear, length=" . strlen($sink->buffer()) . PHP_EOL;
