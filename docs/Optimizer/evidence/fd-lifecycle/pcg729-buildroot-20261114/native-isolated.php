<?php
// Isolated local validation, not a replacement for the Swoole PHPT suite.
use Swoole\Coroutine as Co;
use Swoole\Coroutine\Socket;
function check($ok, string $what): void { if (!$ok) throw new RuntimeException($what); }
function fds(): array {
    $a=[]; foreach (glob('/proc/self/fd/*') as $p) { $t=@readlink($p); if (is_string($t) && str_starts_with($t,'socket:[')) $a[basename($p)]=$t; } return $a;
}
function absent(array $owned): array { $held=array_intersect(fds(),$owned); check(!$held,'FD retained: '.json_encode($held)); return $held; }
$mode=$argv[1]; $result=['mode'=>$mode,'php'=>PHP_VERSION,'swoole'=>SWOOLE_VERSION];
try {
Co\run(function () use ($mode, &$result, $argv) {
    Co::sleep(.001); $base=fds();
    if (str_starts_with($mode,'tls-')) {
        $listener=new Socket(AF_INET,SOCK_STREAM,0);
        check($listener->setProtocol(['open_ssl'=>true,'ssl_cert_file'=>$argv[2],'ssl_key_file'=>$argv[3]]),'TLS server protocol');
        check($listener->bind('127.0.0.1',0) && $listener->listen(),'listen');
        $port=$listener->getsockname()['port']; $server=null; $handshake=false;
        $acceptCid=Co::create(function () use ($listener,&$server,&$handshake) {
            $server=$listener->accept(2); check($server instanceof Socket,'accept');
            check($server->sslHandshake(),'server TLS handshake'); $handshake=true;
            check($server->recvAll(4,2)==='ping','TLS request'); check($server->send('pong')===4,'TLS response');
        });
        $client=new Socket(AF_INET,SOCK_STREAM,0);
        check($client->setProtocol(['open_ssl'=>true,'ssl_verify_peer'=>true,'ssl_cafile'=>$argv[2],'ssl_host_name'=>'localhost']),'TLS client protocol');
        check($client->connect('127.0.0.1',$port,2),'client TLS connect');
        check($client->send('ping')===4 && $client->recvAll(4,2)==='pong','TLS roundtrip');
        check($handshake && !Co::exists($acceptCid),'server complete');
        $reader=null; $recv=null;
        if ($mode==='tls-blocked') {
            $reader=Co::create(function () use ($server,&$recv) { $recv=$server->recv(65536,2); });
            check(Co::exists($reader),'TLS read suspended');
        }
        $owned=array_diff(fds(),$base);
        check($server->close() && $client->close() && $listener->close(),'TLS closes');
        Co::sleep(.05); $result+=['owned'=>$owned,'held'=>absent($owned),'handshake'=>$handshake,'reader_alive'=>$reader!==null && Co::exists($reader)];
        check($server->isClosed() && $client->isClosed() && $listener->isClosed(),'TLS closed state');
        check($reader===null || (!Co::exists($reader) && $recv===false),'TLS reader canceled');
        // Keep closed owners alive while reusing their numeric descriptors.
        $guard=new Socket(AF_INET,SOCK_DGRAM,0); check($guard->bind('127.0.0.1',0),'reuse bind');
        $guardTarget=@readlink('/proc/self/fd/'.$guard->fd);
        check(array_key_exists($guard->fd,$owned),'numeric TLS FD not reused'); $result['reused_fd']=$guard->fd;
        unset($server,$client,$listener); Co::sleep(.02);
        check(@readlink('/proc/self/fd/'.$guard->fd)===$guardTarget,'TLS destruction closed reused FD');
        $port=$guard->getsockname()['port']; check($guard->sendto('127.0.0.1',$port,'reuse')===5,'reuse send');
        $peer=[]; check($guard->recvfrom($peer,1)==='reuse','reuse receive'); $guard->close();
    } else {
        $pair=stream_socket_pair(STREAM_PF_UNIX,STREAM_SOCK_STREAM,0); check($pair!==false,'stream pair');
        [$stream,$peer]=$pair; unset($pair); $owned=array_diff(fds(),$base);
        $socket=Socket::import($stream); check($socket instanceof Socket,'import');
        check($socket->send('a')===1 && fread($peer,1)==='a','import traffic');
        if ($mode==='import-destruct') {
            unset($socket); Co::sleep(.02);
            check(is_resource($stream) && fwrite($stream,'b')===1 && fread($peer,1)==='b','stream survives wrapper destruction');
            fclose($stream);
        } elseif ($mode==='import-close') {
            check($socket->close(),'import explicit close');
            check(!is_resource($stream),'stream invalidated by wrapper close');
        } elseif ($mode==='import-stream-first') {
            check(fclose($stream),'stream first close');
            // Do not use the wrapper for I/O after its externally owned stream is closed.
        } else { throw new RuntimeException('unknown case'); }
        fclose($peer); Co::sleep(.05); $result+=['owned'=>$owned,'held'=>absent($owned)];
        $guard=new Socket(AF_INET,SOCK_DGRAM,0); check($guard->bind('127.0.0.1',0),'guard bind');
        $target=@readlink('/proc/self/fd/'.$guard->fd);
        check(array_key_exists($guard->fd,$owned),'numeric imported FD not reused'); $result['reused_fd']=$guard->fd;
        unset($socket); Co::sleep(.02);
        check(@readlink('/proc/self/fd/'.$guard->fd)===$target,'import destruction closed reused FD');
        $port=$guard->getsockname()['port']; check($guard->sendto('127.0.0.1',$port,'guard')===5,'guard send');
        $addr=[]; check($guard->recvfrom($addr,1)==='guard','guard recv'); $guard->close();
    }
    Co::sleep(.02); $result['remaining']=array_diff(fds(),$base); check(!$result['remaining'],'extra FDs remain');
});
$result['passed']=true;
} catch (Throwable $e) { $result['passed']=false; $result['error']=$e->getMessage(); }
echo json_encode($result,JSON_PRETTY_PRINT)."\n"; exit($result['passed']?0:1);
