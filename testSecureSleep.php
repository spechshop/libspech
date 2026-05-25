<?php

use function libspech\Sip\interruptibleSleep;

include 'plugins/autoloader.php';

Co\run(function () {
   $aborted = true;
   interruptibleSleep(2.5, $aborted);
});