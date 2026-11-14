<?php
ini_set('memory_limit', '1024M');
$root = realpath($argv[1]);
chdir($root);
require $root . '/plugins/autoloader.php';
use libspech\Rtp\{AcousticRtpContinuity, EarlyMediaAcousticTap, MediaChannel, rtpc};
use libspech\audio\EarlyMediaAcousticMonitor;
use Swoole\Coroutine as Co;
function check(bool $ok, string $name): void { if (!$ok) throw new RuntimeException($name); }
function packet(int $seq, int $ts, int $duration = 0, int $ssrc = 42, bool $end = true, int $pt = 8): rtpc {
    return new rtpc(pack('CCnNN', 128, $duration ? 101 : $pt, $seq & 65535, $ts & 0xffffffff, $ssrc)
        . ($duration ? pack('CCn', 5, ($end ? 128 : 0) | 10, $duration) : str_repeat(chr(213), 160)));
}
// Explicit monotonic times make temporal checks deterministic, including wraparound.
function timeline(array $rows, int $loss, array $resets, string $name): void {
    $c = new AcousticRtpContinuity(); $got = [];
    foreach ($rows as [$seq, $ts, $duration, $ns, $ssrc, $format]) {
        $p = packet($seq, $ts, $duration, $ssrc);
        $r = $c->inspect($p, $format, $ns, $duration ? 8000 : 0);
        if ($r['accept'] && !$duration) $c->duration($p, 160);
        if ($r['accept'] && $r['reset']) $got[] = $r['reset'];
    }
    check($c->lost === $loss && $got === $resets, $name . json_encode([$c->lost, $got]));
}
function row(int $seq, int $ts, int $dur = 0, int $ns = 0, int $ssrc = 42, string $fmt = 'PCMA/8000/1/8'): array {
    return [$seq,$ts,$dur,$ns,$ssrc,$fmt];
}
timeline([row(1,0),row(2,160),row(3,320)],0,[],'continuous');
timeline([row(1,0),row(2,160,160),row(3,160,800),row(4,960)],0,[],'event time');
timeline([row(1,0),row(2,160,800),row(3,160),row(4,320),row(5,960)],0,[],'concurrent audio');
timeline([row(65534,0xfffffec0),row(65535,0xffffff60,320),row(0,0xffffff60,320),row(1,160)],0,[],'both wraps');
timeline([row(1,0),row(3,320)],1,['packet_loss'],'audio loss');
timeline([row(1,0),row(3,160,320),row(4,480)],1,['packet_loss'],'DTMF loss');
timeline([row(1,0),row(2,160,800),row(4,480)],1,['packet_loss'],'audio loss in event');
timeline([row(1,0),row(3,320),row(2,160),row(2,160),row(4,480)],0,['packet_loss'],'late repairs estimate');
timeline([row(1,0),row(2,160,800),row(2,160,800),row(3,960)],0,[],'event duplicate');
timeline([row(1,0),row(2,160,800),row(3,1120)],0,['timestamp_jump'],'beyond event');
timeline([row(1,0),row(2,160,800),row(3,0)],0,['timestamp_jump'],'backward time');
timeline([row(1,0),row(2,99999,800),row(3,100799)],0,['timestamp_jump'],'unanchored event');
timeline([row(1,0),row(2,160,800,100000000),row(3,160,1600,200000000),row(4,160,2400,300000000),row(5,2560,0,400000000)],0,[],'long event bridges arrival');
timeline([row(1,0),row(2,160,800,300000000)],0,['arrival_gap'],'event arrival gap');
timeline([row(1,0),row(2,160,800,100000000),row(3,960,0,400000001)],0,['arrival_gap'],'resume gap');
timeline([row(1,0),row(2,160,800,0,99),row(3,1234,0,0,99)],0,['ssrc_changed'],'event SSRC');
timeline([row(1,0),row(2,160,800),row(3,960,0,0,42,'PCMU/8000/1/0')],0,['format_changed'],'format');
$cross = new AcousticRtpContinuity();
foreach ([[1,0,0],[2,160,160],[3,960,0]] as [$seq,$ts,$duration]) {
    $p=packet($seq,$ts,$duration); $r=$cross->inspect($p,'OPUS/48000/1/96',0,$duration ? 8000 : 0);
    if (!$duration) $cross->duration($p,960);
}
check($cross->lost === 0 && $r['reset'] === null,'different event clock, continuous audio');
// Compare full monitor evidence and events with the identical audio-only input.
foreach (['PCMA','PCMU','G729','OPUS'] as $codec) foreach (['tap','shared'] as $path) {
    if ($path === 'tap' && in_array($codec, ['G729','OPUS'])) continue; // Real decoders covered below.
    $events = []; $m = new EarlyMediaAcousticMonitor();
    $tap = new EarlyMediaAcousticTap($m, function($e) use (&$events) { $events[] = $e; });
    $rate = $codec === 'OPUS' ? 48000 : 8000; $samples = intdiv($rate,50);
    $pcm = str_repeat("\0", $samples*2);
    $p = packet(1,0); if ($codec === 'G729') $p->payloadRaw = str_repeat("\0",20);
    $tap->receive($p,['leg'=>'b'],$codec,$rate,1,$path === 'shared' ? $pcm : null);
    $before = $m->metrics();
    foreach ([2,3,3,2,4] as $seq) $tap->receive(packet($seq,$samples,160),['leg'=>'b'],'TELEPHONE-EVENT',$rate,1);
    $p->sequence = 5; $p->timestamp = $samples + 160;
    $tap->receive($p,['leg'=>'b'],$codec,$rate,1,$path === 'shared' ? $pcm : null);
    $v = $tap->metrics();
    check(!$v['closed'] && $v['continuity_epoch'] === 0 && $v['lost_packets'] === 0
        && $v['audio_ms'] === ($codec === 'OPUS' ? 20 : 40) && $v['duplicate_packets'] === 2 && $v['analysis_decode_packets'] + $v['shared_pcm_packets'] === 2, "$codec/$path evidence " . json_encode($v));
    check(count(array_filter($events,fn($e)=>$e['event']==='continuity.reset')) === 0,"$codec events");
    $referenceEvents = [];
    $reference = new EarlyMediaAcousticTap(new EarlyMediaAcousticMonitor(),function($e)use(&$referenceEvents){$referenceEvents[]=$e;});
    $p->sequence = 1; $p->timestamp = 0;
    $reference->receive($p,['leg'=>'b'],$codec,$rate,1,$path === 'shared' ? $pcm : null);
    $p->sequence = 2; $p->timestamp = $samples;
    $reference->receive($p,['leg'=>'b'],$codec,$rate,1,$path === 'shared' ? $pcm : null);
    $expected = $reference->metrics();
    foreach (['processing_ns','duplicate_packets','late_packets'] as $key) { unset($v[$key],$expected[$key]); }
    check($v === $expected, "$codec/$path complete acoustic parity");
    $summary = static fn($e) => [$e['event'],$e['audio_ms'],$e['continuity_epoch'],$e['native']['signal'] ?? null];
    check(array_map($summary,$events) === array_map($summary,$referenceEvents), "$codec/$path event parity");
    $reference->close();
    $tap->close(); $tap->close(); check($m->isClosed(),'idempotent close');
}
// A real gap must retain the shared stateful decoder's conservative fail-open.
foreach (['G729','OPUS'] as $codec) {
    $monitor = new EarlyMediaAcousticMonitor(); $events=[];
    $tap = new EarlyMediaAcousticTap($monitor,function($e)use(&$events){$events[]=$e;});
    $rate = $codec === 'OPUS' ? 48000 : 8000; $samples=intdiv($rate,50);
    $p=packet(1,0); if($codec==='G729') $p->payloadRaw=str_repeat("\0",20);
    $tap->receive($p,['leg'=>'b'],$codec,$rate,1,str_repeat("\0",$samples*2));
    $tap->receive(packet(3,$samples,$samples),['leg'=>'b'],'TELEPHONE-EVENT',$rate,1);
    $v=$tap->metrics();
    check($v['closed'] && $v['lost_packets']===1 && $v['continuity_epoch']===1
        && $v['failure_reason']==='shared_decoder_packet_loss',"$codec real shared loss");
    check(in_array('analysis.failed',array_column($events,'event'),true),'shared failure event');
}
// A newly created channel/tap has no transport or decoder evidence from a closed one.
$monitor=new EarlyMediaAcousticMonitor(); $tap=new EarlyMediaAcousticTap($monitor);
$tap->receive(packet(500,9000,160),['leg'=>'b'],'TELEPHONE-EVENT',8000,1);
check($tap->metrics()['audio_ms']===0 && $tap->metrics()['analysis_decode_packets']===0,'event before audio');
$tap->receive(packet(501,9160),['leg'=>'b'],'PCMA',8000,1);
check($tap->metrics()['continuity_epoch']===0 && !$monitor->isClosed(),'fresh transport');
$tap->close();
// Actual socket path: both receive implementations, both channel modes, two legs.
Co\run(function () {
    foreach ([false,true] as $native) foreach ([false,true] as $receiveOnly) foreach (['PCMA','PCMU','G729','OPUS'] as $codec) {
        foreach (['continuous','single','triple','duplicate','reorder','audio-loss','event-loss','during-loss','ssrc','timestamp'] as $case) {
            $s = new SocketMutable(AF_INET,SOCK_DGRAM,SOL_UDP); $s->bind('127.0.0.1',0);
            $tx = new SocketMutable(AF_INET,SOCK_DGRAM,SOL_UDP); $tx->bind('127.0.0.1',0);
            $other = new SocketMutable(AF_INET,SOCK_DGRAM,SOL_UDP); $other->bind('127.0.0.1',0);
            $m = new MediaChannel($s,'acoustic-dtmf'); $m->setNativeRtpReceive($native);
            $m->setReceiveOnlyMode($receiveOnly); $m->setSettings(['sendSilenceProbeToMembers'=>false]);
            $rate = $codec === 'OPUS' ? 48000 : 8000;
            $pt = ['PCMA'=>8,'PCMU'=>0,'G729'=>18,'OPUS'=>96][$codec];
            foreach ([[$tx,'b'],[$other,'a']] as [$sock,$leg]) $m->addMember(['address'=>'127.0.0.1','port'=>$sock->getsockname()['port'], 'codec'=>$codec,'pt'=>$pt,'frequency'=>$rate,'leg'=>$leg,'rxCodecMapper'=>[$pt=>"$codec/$rate/1",101=>"telephone-event/$rate/1"]]);
            $events = []; $monitor = new EarlyMediaAcousticMonitor();
            $tap = new EarlyMediaAcousticTap($monitor,function($e)use(&$events){$events[]=$e;}); $m->setEarlyMediaAcousticTap($tap);
            $samples = intdiv($rate,50);
            $payload = match($codec) {
                'PCMA'=>str_repeat(chr(213),160), 'PCMU'=>str_repeat(chr(255),160),
                'G729'=>(new bcg729Channel())->encode(str_repeat("\0",320)),
                'OPUS'=>(new opusChannel($rate,1))->encode(str_repeat("\0",$samples*2)),
            };
            $send = function($sock,$p)use($s,$payload){ if ($p->payloadType !== 101) $p->payloadRaw=$payload;
                $sock->sendto('127.0.0.1',$s->getsockname()['port'],pack('CCnNN',128,$p->payloadType,$p->sequence,$p->timestamp,$p->ssrc).$p->payloadRaw); Co::sleep(.001); };
            $m->start();
            $send($tx,packet(65533,0,0,42,true,$pt));
            // Other leg must not pollute acoustic transport or evidence.
            $send($other,packet(1234,99000,160,123));
            if ($case === 'continuous') {
                $send($tx,packet(65534,$samples,0,42,true,$pt));
                $send($tx,packet(65535,$samples*2,0,42,true,$pt));
            } else {
                $seqs = match($case) {'single'=>[65534], 'duplicate'=>[65534,65534,65535,0], 'reorder'=>[65534,0,65535], 'event-loss','during-loss'=>[65534,0], default=>[65534,65535,0]};
                foreach ($seqs as $seq) $send($tx,packet($seq,$samples,$samples,42,$case === 'single' || $seq!==65534));
                $next = $case === 'single' ? 65535 : 1;
                if ($case === 'audio-loss') $next++;
                $send($tx,packet($next,$case === 'timestamp' ? $samples*4 : $samples*2,0,$case === 'ssrc' ? 77 : 42,true,$pt));
            }
            $v=$tap->metrics();
            $loss = in_array($case,['audio-loss','during-loss','event-loss']);
            $reset = $loss || in_array($case,['ssrc','timestamp','reorder']);
            check($v['lost_packets'] === ($loss ? 1 : 0),"socket loss $native/$receiveOnly/$codec/$case " . json_encode($v));
            check(($v['continuity_epoch'] > 0) === $reset,"socket epoch $native/$receiveOnly/$codec/$case " . json_encode($v));
            check($v['audio_ms'] === ($codec === 'OPUS' ? ($case === 'continuous' ? 40 : ($reset ? 0 : 20)) : ($case === 'continuous' ? 60 : 40)) && !$v['closed'],"socket audio $codec/$case " . json_encode($v));
            check((count(array_filter($events,fn($e)=>$e['event']==='continuity.reset'))>0)===$reset,'reset events');
            $m->close(); check($monitor->isClosed() && $m->members === [],'channel close'); $tx->close();$other->close();
        }
    }
});
echo "PASS acoustic DTMF continuity, monitor evidence, negative controls and socket matrix\n";
