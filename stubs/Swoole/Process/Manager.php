<?php

declare(strict_types=1);

namespace Swoole\Process;


class Manager {

    
    public function __construct(\int $ipcType = 0, \int $msgQueueKey = 0) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function add(\callable $func, \bool $enableCoroutine = false): \Swoole\Process\Manager {
        return class_exists(\Swoole\Process\Manager::class) ? \Swoole\Process\Manager::class : \stdClass::class;
    }

    
    public function addBatch(\int $workerNum, \callable $func, \bool $enableCoroutine = false): \Swoole\Process\Manager {
        return class_exists(\Swoole\Process\Manager::class) ? \Swoole\Process\Manager::class : \stdClass::class;
    }

    
    public function start() {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function setIPCType(\int $ipcType): \Swoole\Process\Manager {
        return class_exists(\Swoole\Process\Manager::class) ? \Swoole\Process\Manager::class : \stdClass::class;
    }

    
    public function getIPCType(): \int {
        return 0;
    }

    
    public function setMsgQueueKey(\int $msgQueueKey): \Swoole\Process\Manager {
        return class_exists(\Swoole\Process\Manager::class) ? \Swoole\Process\Manager::class : \stdClass::class;
    }

    
    public function getMsgQueueKey(): \int {
        return 0;
    }
}
