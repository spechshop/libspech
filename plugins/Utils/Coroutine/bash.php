<?php

namespace libspech\Coroutine;

use Swoole\Coroutine;
use Swoole\Runtime;

class bash
{
    public static function command(string $command, ?callable $onExit = null): void
    {

        if (class_exists(Runtime::class) && defined('SWOOLE_HOOK_PROC')) {
            Runtime::enableCoroutine(SWOOLE_HOOK_PROC);
        }

        $runner = function () use ($command, $onExit) {
            $descriptors = [
                0 => defined('STDIN') ? STDIN : ['file', '/dev/tty', 'r'],
                1 => defined('STDOUT') ? STDOUT : ['file', '/dev/tty', 'w'],
                2 => defined('STDERR') ? STDERR : ['file', '/dev/tty', 'w'],
            ];

            $process = proc_open($command, $descriptors, $pipes);

            if (!is_resource($process)) {
                if ($onExit !== null) {
                    $onExit(-1);
                }
                return;
            }

            // Espera não bloqueante: dentro de corrotina, Coroutine::sleep cede
            // o controle ao escalonador; fora dela, usleep simples.
            $exitCode = -1;
            while (true) {
                $status = proc_get_status($process);
                if (!$status['running']) {
                    $exitCode = $status['exitcode'];
                    break;
                }

                if (class_exists(Coroutine::class) && Coroutine::getCid() > 0) {
                    Coroutine::sleep(0.05);
                } else {
                    usleep(50000);
                }
            }

            // proc_close faz o wait/reap do filho, evitando zumbis.
            proc_close($process);

            if ($onExit !== null) {
                $onExit($exitCode);
            }
        };

        // Lança numa corrotina própria para que command() retorne imediatamente
        // e não bloqueie o fluxo chamador (execução assíncrona).
        if (class_exists(Coroutine::class) && Coroutine::getCid() > 0) {
            Coroutine::create($runner);
        } else {
            $runner();
        }
    }
}
