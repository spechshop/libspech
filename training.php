<?php

include 'plugins/autoloader.php';
Co\run(function (){
    $path = '/home/lotus/projetos/spechdisk/storage/recordings/2026/07/20/*.wav';
    $files = glob($path);

    foreach ($files as $file) {
        if (str_contains($file, '-pre-200')) {
            $transcript = \libspech\libspech\sound::deepGramFile($file, false, getenv('DEEPGRAM'), 'nova-3');
            $pcm = substr(file_get_contents($file), 44);
            $ringAnalyse = \libspech\Sip\analyzeRingPcm($pcm, 8000);
            $hasTranscript = trim($transcript) !== '';

            // O transcript não participa da análise acústica. Ele serve
            // somente para destacar um possível falso positivo da análise.
            $hasDiscrepancy =
                $hasTranscript &&
                $ringAnalyse['ring_from_start_to_end'];

            $comparison = [
                'file' => basename($file),
                'transcript' => $transcript,
                'analysis' => [
                    'has_ring_pattern' =>
                        $ringAnalyse['has_ring_pattern'],
                    'ring_from_start_to_end' =>
                        $ringAnalyse['ring_from_start_to_end'],
                    'reason' => $ringAnalyse['reason'],
                    'confidence' => $ringAnalyse['confidence'],
                    'duration_ms' => $ringAnalyse['duration_ms'],
                    'disturbance_at_ms' =>
                        $ringAnalyse['disturbance_at_ms'],
                    'disturbance_duration_ms' =>
                        $ringAnalyse['disturbance_duration_ms'],
                ],
                'discrepancy' => $hasDiscrepancy,
            ];

            \libspech\Cli\cli::pcl(
                json_encode(
                    $comparison,
                    JSON_PRETTY_PRINT |
                    JSON_UNESCAPED_SLASHES |
                    JSON_UNESCAPED_UNICODE
                ),
                $hasDiscrepancy ? 'red' : 'white'
            );

            \Swoole\Coroutine::sleep(2);
        }
    }
});
