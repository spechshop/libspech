<?php


include 'plugins/autoloader.php';


\co\run(function () {
    \libspech\Coroutine\bash::command('php readline.php');
    \libspech\Cli\cli::pcl('Isto deve aparecer antes da resposta');
});