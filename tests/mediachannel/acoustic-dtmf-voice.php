<?php
// Additional laboratory check with a caller-supplied real 8 kHz mono PCM16 fixture.
ini_set('memory_limit','1024M');
$root=realpath($argv[1]);chdir($root);require $root.'/plugins/autoloader.php';
use libspech\Rtp\{EarlyMediaAcousticTap,rtpc};
use libspech\audio\EarlyMediaAcousticMonitor;
$voice=file_get_contents($argv[2]); if ($voice===false) throw new RuntimeException('speech fixture required');
$native=new AcousticStreamAnalyzer(8000,20);$frames=[];
foreach(str_split($voice,320) as $frame){if(strlen($frame)!==320)continue;$native->push($frame);$state=$native->state();
    if($state['frame_signal']==='voice_like' && $state['signal']==='voice_like')$frames[]=$frame;}
if(!$frames)throw new RuntimeException('fixture contains no voiced frames');
$results=[];
foreach([false,true] as $dtmf){
    $events=[];$monitor=new EarlyMediaAcousticMonitor();$tap=new EarlyMediaAcousticTap($monitor,function($e)use(&$events){$events[]=$e;});
    $seq=0;$ts=0;
    for($i=0;$i<500&&!$monitor->isClosed();$i++){
        $p=new rtpc(pack('CCnNN',128,8,$seq++ & 65535,$ts,42).encodePcmToPcma($frames[$i%count($frames)]));
        $tap->receive($p,['leg'=>'b'],'PCMA',8000,1);$ts+=160;
        if($dtmf && $i%15===10) {
            for($j=0;$j<3;$j++)$tap->receive(new rtpc(pack('CCnNNCCn',128,101,$seq++ & 65535,$ts,42,5,138,160)),['leg'=>'b'],'TELEPHONE-EVENT',8000,1);
            $ts+=160;
        }
    }
    $v=$tap->metrics();unset($v['processing_ns']);$d=$monitor->decision();unset($d['confirmed_ns']);
    $results[]=['metrics'=>$v,'decision'=>$d,'events'=>array_map(fn($e)=>[$e['event'],$e['audio_ms'],$e['continuity_epoch']],$events)];
}
if(!$results[0]['decision'] || $results[0]!==$results[1])throw new RuntimeException('real voice evidence/decision/event parity failed '.json_encode($results));
echo json_encode(['passed'=>true,'fixture_sha256'=>hash('sha256',$voice),'results'=>$results],JSON_PRETTY_PRINT)."\n";
