<?php

class SocketMutable extends \Co\Socket
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

    public function safeRecvfrom(&$peername, mixed $int)
    {
        return parent::recvfrom($peername, $int);
    }
}