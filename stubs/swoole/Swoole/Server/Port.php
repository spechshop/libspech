<?php

declare(strict_types=1);

namespace Swoole\Server;

class Port
{
    private $onConnect = NULL;
    private $onReceive = NULL;
    private $onClose = NULL;
    private $onPacket = NULL;
    private $onBufferFull = NULL;
    private $onBufferEmpty = NULL;
    private $onRequest = NULL;
    private $onHandshake = NULL;
    private $onOpen = NULL;
    private $onMessage = NULL;
    private $onDisconnect = NULL;
    private $onBeforeHandshakeResponse = NULL;
    public $host = NULL;
    public $port = 0;
    public $type = 0;
    public $sock = -1;
    public $ssl = false;
    public $setting = NULL;
    public $connections = NULL;

    private function __construct() {}

    public function __destruct() {}

    public function set(array $settings): void {}

    public function on(string $event_name, callable $callback): bool {}

    public function getCallback(string $event_name): ?\Closure {}

    public function getSocket(): \Socket|false {}
}
