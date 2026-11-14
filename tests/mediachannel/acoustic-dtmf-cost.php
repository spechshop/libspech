<?php
ini_set('memory_limit','1024M');
$root = realpath($argv[1]); chdir($root); require $root.'/plugins/autoloader.php';
use libspech\Rtp\{MediaChannel,EarlyMediaAcousticTap,rtpc};
use libspech\audio\EarlyMediaAcousticMonitor;
$n = (int)($argv[3] ?? 30000); $kind = $argv[2] ?? 'audio';
$baseline = ($argv[4] ?? '') === 'baseline';
function cpuUs(array $r): int {return ($r['ru_utime.tv_sec']+$r['ru_stime.tv_sec'])*1000000+$r['ru_utime.tv_usec']+$r['ru_stime.tv_usec'];}
Swoole\Coroutine\run(function()use($n,$kind,$baseline){
    $tap = new EarlyMediaAcousticTap(new EarlyMediaAcousticMonitor());
    $socket = new SocketMutable(AF_INET,SOCK_DGRAM,SOL_UDP); $socket->bind('127.0.0.1',0);
    $media = new MediaChannel($socket,'cost'); $process = new ReflectionMethod($media,'processDtmf');
    $audio = new rtpc(pack('CCnNN',128,8,0,0,42).str_repeat(chr(213),160));
    $event = new rtpc(pack('CCnNNCCn',128,101,0,0,42,5,138,160));
    $member=['leg'=>'b']; $peer=['address'=>'127.0.0.1','port'=>9]; $noop=static function(){};
    $start=cpuUs(getrusage()); $mem=memory_get_usage(); $time=hrtime(true); $seq=0;
    for($i=0;$i<$n;$i++) {
        $audio->sequence=$seq++ & 65535; $audio->timestamp=$i*($kind==='audio'?160:320);
        $tap->receive($audio,$member,'PCMA',8000,1);
        if($kind==='event') for($j=0;$j<3;$j++) {
            $event->sequence=$seq++ & 65535; $event->timestamp=$audio->timestamp+160;
            if(!$baseline) $tap->receive($event,$member,'TELEPHONE-EVENT',8000,1);
            $process->invoke($media,$event,$peer,$noop);
        }
    }
    $cpu=cpuUs(getrusage())-$start;
    echo json_encode(['kind'=>$kind,'iterations'=>$n,'rtp_packets'=>$seq,'cpu_us'=>$cpu,'cpu_us_per_rtp'=>$cpu/$seq,
        'cpu_us_per_iteration'=>$cpu/$n,'wall_ns'=>hrtime(true)-$time,'retained_bytes'=>memory_get_usage()-$mem,
        'peak_bytes'=>memory_get_peak_usage(),'metrics'=>$tap->metrics()])."\n";
    $tap->close();$media->close();
});
