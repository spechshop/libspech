<?php

namespace libspech\Cli;

class cli
{

    public static function color($color, $message): string
    {
        $colors = [
            'black' => '0;30',
            'red' => '0;31',
            'green' => '0;32',
            'yellow' => '0;33',
            'blue' => '0;34',
            'magenta' => '0;35',
            'cyan' => '0;36',
            'white' => '0;37',
            'bold_black' => '1;30',
            'bold_red' => '1;31',
            'bold_green' => '1;32',
            'bold_yellow' => '1;33',
            'bold_blue' => '1;34',
            'bold_magenta' => '1;35',
            'bold_cyan' => '1;36',
            'bold_white' => '1;37'
        ];

        $colorCode = $colors[$color] ?? '0';
        return "\033[" . $colorCode . "m" . $message . "\033[0m";
    }

    public static function pcl(string $message, string $color = 'white'): void
    {
        $colors = [
            'black' => '0;30',
            'red' => '0;31',
            'green' => '0;32',
            'yellow' => '0;33',
            'blue' => '0;34',
            'magenta' => '0;35',
            'cyan' => '0;36',
            'white' => '0;37',
            'bold_black' => '1;30',
            'bold_red' => '1;31',
            'bold_green' => '1;32',
            'bold_yellow' => '1;33',
            'bold_blue' => '1;34',
            'bold_magenta' => '1;35',
            'bold_cyan' => '1;36',
            'bold_white' => '1;37'
        ];

        $colorCode = $colors[$color] ?? '0';
       print "\033[" . $colorCode . "m" . $message . "\033[0m" . "\n";
    }


    public static function cl(string $color, string $message): string
    {
        $colors = [
            'black' => '0;30',
            'red' => '0;31',
            'green' => '0;32',
            'yellow' => '0;33',
            'blue' => '0;34',
            'magenta' => '0;35',
            'cyan' => '0;36',
            'white' => '0;37',
            'bold_black' => '1;30',
            'bold_red' => '1;31',
            'bold_green' => '1;32',
            'bold_yellow' => '1;33',
            'bold_blue' => '1;34',
            'bold_magenta' => '1;35',
            'bold_cyan' => '1;36',
            'bold_white' => '1;37'
        ];

        $colorCode = $colors[$color] ?? '0';
        return "\033[" . $colorCode . "m" . $message . "\033[0m" . "\n";
    }

    public static function formatBytes($bytes, $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));
        return round($bytes, $precision) . ' ' . $units[$pow];
    }

    public static function debugResources(): void
    {
        self::pcl("--- DEBUG DETALHADO ---", "bold_cyan");

        self::pcl("Swoole Coroutine Stats:", "bold_yellow");
        if (class_exists('\Swoole\Coroutine')) {
            foreach (\Swoole\Coroutine::stats() as $key => $val) {
                $displayVal = $val;
                if ($key === 'c_stack_size' && is_numeric($val)) {
                    $displayVal = $val . " (" . self::formatBytes($val) . ")";
                }
                self::pcl("  $key: $displayVal", "cyan");
            }
        } else {
            self::pcl("  Swoole Coroutine not found", "red");
        }

        self::pcl("PHP Memory Usage:", "bold_yellow");
        self::pcl("  Current: " . self::formatBytes(memory_get_usage()), "cyan");
        self::pcl("  Peak: " . self::formatBytes(memory_get_peak_usage()), "cyan");

        self::pcl("Process CPU & Resource Usage:", "bold_yellow");
        $rusage = getrusage();
        self::pcl("  User time: " . ($rusage['ru_utime.tv_sec'] + $rusage['ru_utime.tv_usec'] / 1000000) . "s", "cyan");
        self::pcl("  System time: " . ($rusage['ru_stime.tv_sec'] + $rusage['ru_stime.tv_usec'] / 1000000) . "s", "cyan");
        // ru_maxrss is in KB on Linux
        self::pcl("  Max RSS: " . self::formatBytes($rusage['ru_maxrss'] * 1024), "cyan");
        self::pcl("  Soft page faults: " . $rusage['ru_minflt'], "cyan");
        self::pcl("  Hard page faults: " . $rusage['ru_majflt'], "cyan");
        self::pcl("  Voluntary context switches: " . $rusage['ru_nvcsw'], "cyan");
        self::pcl("  Involuntary context switches: " . $rusage['ru_nivcsw'], "cyan");

        self::pcl("System Load:", "bold_yellow");
        if (function_exists('sys_getloadavg')) {
            $load = sys_getloadavg();
            if ($load) {
                self::pcl("  1min: {$load[0]}, 5min: {$load[1]}, 15min: {$load[2]}", "cyan");
            }
        }

        self::pcl("Garbage Collector:", "bold_yellow");
        $gc = gc_status();
        self::pcl("  Runs: {$gc['runs']}, Collected: {$gc['collected']}, Threshold: {$gc['threshold']}", "cyan");

        if (file_exists('/proc/self/fd')) {
            $fds = count(scandir('/proc/self/fd')) - 2;
            self::pcl("Open File Descriptors: $fds", "bold_yellow");
        }
        self::pcl("------------------------", "bold_cyan");
    }
}