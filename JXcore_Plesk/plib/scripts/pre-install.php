<?php

pm_Context::init("jxcore-support");

if (false !== ($upgrade = array_search('upgrade', $argv))) {

    $previous_version = $argv[2];

    if ($previous_version < '0.2.6') {
        $urlMonitor = "https://localhost:17777/json?silent=true";
        $json = null;
        $monitorRunning = Modules_JxcoreSupport_Common::getURL($urlMonitor, $json);

        if ($monitorRunning) {
            print("Please stop JXcore monitor before updating the extension.");
            exit(1);
        }
    }
}

exit(0);