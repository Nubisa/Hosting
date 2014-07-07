<?php

pm_Context::init('jxcore-support');
$id = pm_Settings::get('customButtonId');


// JXcore crontab cleaning

$binary = "/usr/local/psa/admin/bin/crontabmng";

$tmpfile = pm_Context::getVarDir() . "mycron";
@exec("$binary get root > $tmpfile");
$contents = file_get_contents($tmpfile);

$contents = preg_replace('/(#JXcore-Begin)(.*)(#JXcore-End)/si', '', $contents);
$contents = preg_replace('/(#JXcore-immediate-Begin)(.d*)(#JXcore-immediate-End)/si', '', $contents);

// cleaning crontab
if (trim($contents) === "") {
    @exec("$binary remove root");
} else {
    file_put_contents($tmpfile, $contents);
    @exec("$binary set root $tmpfile");
}

// no need to unlink $tmpfile since the whole folder will be removed anyway

Modules_JxcoreSupport_Common::callService("nginx", "remove&all=1", null, null);

// stopping the monitor
$jxpath = pm_Settings::get("jxpath");
if (file_exists($jxpath)) {
    @exec("$jxpath monitor stop");
}


$request = <<<APICALL
<ui>
    <delete-custombutton>
        <filter>
            <custombutton-id>$id</custombutton-id>
        </filter>
    </delete-custombutton>
</ui>
APICALL;

try {
    $response = pm_ApiRpc::getService()->call($request);

    $result = $response->ui->{"delete-custombutton"}->result;
    if (true || 'ok' == $result->status) {
        echo "done\n";
        exit(0);
    } else {
        echo "error $result->errcode: $result->errtext\n";
        exit(1);
    }

} catch(PleskAPIParseException $e) {
    echo $e->getMessage() . "\n";


    // on plesk 12 we had sometimes exception like "The id is not atomic" or something similar
    // That prevented uninstallation of the extension
    pm_Bootstrap::init();
    pm_Bootstrap::getDbAdapter()->delete('custom_buttons', array("url like '%jxcore-support%'"));

    exit(0);
}
