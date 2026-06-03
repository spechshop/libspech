<?php
class SocketMutable extends \Swoole\Coroutine\Socket
{
    private ?array $lastSockname = null;
    private bool $closed = false;

    public function destroy(): void
    {
        if ($this->closed) {
            return;
        }

        $this->closed = true;

        try {
            parent::close();
        } catch (\Throwable $e) {
            // fd já pode estar inválido
        }
    }

    public function safeRecvfrom(&$peername, mixed $length)
    {
        if ($this->closed) {
            return false;
        }

        $data = parent::recvfrom($peername, $length);

        if ($data === false && ((int)$this->errCode === 9)) {
            $this->destroy();
            return false;
        }

        return $data;
    }

    public function __destruct()
    {
        $this->destroy();
    }
}