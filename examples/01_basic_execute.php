<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Melmuk\LuaSandboxWrapper\LuaCode;
use Melmuk\LuaSandboxWrapper\LuaExecutor;

$executor = new LuaExecutor();

$code = new LuaCode(<<<'LUA'
function execute(data)
    print("hello from lua", data.id)
    data.handled = true
    return data
end
LUA);

$result = $executor->execute(['id' => 7], $code);
print_r($result);
