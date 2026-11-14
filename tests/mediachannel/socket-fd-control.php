<?php
// Independent Swoole reproducer: no autoloader, MediaChannel, SocketMutable or GC.
use Swoole\Coroutine as Co;
function socketFds(): array {
    $fds = [];
    foreach (glob('/proc/self/fd/*') ?: [] as $path) {
        $target = @readlink($path);
        if (is_string($target) && str_starts_with($target, 'socket:[')) $fds[basename($path)] = $target;
    }
    return $fds;
}
if (!is_dir('/proc/self/fd') || !extension_loaded('swoole')) {
    fwrite(STDERR, "Cannot certify socket lifecycle: Linux procfs and Swoole required\n"); exit(1);
}
$rows = [];
Co\run(function () use (&$rows): void {
    foreach ([false, true] as $blocked) {
        $before = socketFds();
        $socket = new Swoole\Coroutine\Socket(AF_INET, SOCK_DGRAM, SOL_UDP);
        $socket->bind('127.0.0.1', 0);
        $owned = array_diff(socketFds(), $before);
        $cid = null;
        if ($blocked) {
            $cid = Co::create(static function () use ($socket): void { $peer = []; $socket->recvfrom($peer, 1); });
            $socket->cancel(SWOOLE_EVENT_READ);
        }
        $socket->close();
        Co::sleep(.02);
        $closed = $socket->isClosed();
        $held = array_intersect(socketFds(), $owned);
        $weak = WeakReference::create($socket);
        unset($socket);
        $destroyedBeforeYield = socketFds();
        Co::sleep(.02);
        $released = array_intersect(socketFds(), $owned);
        $rows[] = compact('blocked', 'before', 'owned', 'closed', 'held', 'destroyedBeforeYield', 'released')
            + ['object_alive' => $weak->get() !== null, 'reader_alive' => $cid !== null && Co::exists($cid)];
    }
});
echo json_encode(['php' => PHP_VERSION, 'swoole' => SWOOLE_VERSION, 'rows' => $rows, 'after_reactor' => socketFds()], JSON_PRETTY_PRINT)."\n";
// Strict certification intentionally fails on runtimes retaining closed objects' FDs.
exit(count(array_filter($rows, fn($r) => $r['held'] || $r['released'] || $r['reader_alive'])) ? 1 : 0);
