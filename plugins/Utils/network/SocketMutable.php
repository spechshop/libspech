<?php

class SocketMutable extends \Swoole\Coroutine\Socket
{
    private ?array $lastSockname = null;

    public function getsockname(): array
    {
        $result = parent::getsockname();

        if ($result !== false) {
            $this->lastSockname = $result;
            return $result;
        }

        return $this->lastSockname ?? [];
    }
}