<?php

pm_Context::init('jxcore_support');
$id = pm_Settings::get('customButtonId');

require_once("/opt/psa/admin/plib/modules/jxcore_support/controllers/common.php");
//Common::updateCronImmediate(false);

// JXcore crontab cleaning

$binary = "/opt/psa/admin/bin/crontabmng";

$tmpfile = pm_Context::getVarDir() . "mycron";
@exec("$binary get root > $tmpfile");
$contents = file_get_contents($tmpfile);

$contents = preg_replace('/(#JXcore-Begin)(.?*)(#JXcore-End)/si', '', $contents);
$contents = preg_replace('/(#JXcore-immediate-Begin)(.?*)(#JXcore-immediate-End)/si', '', $contents);

// cleaning crontab
if (trim($contents) === "") {
    @exec("$binary remove root");
} else {
    file_put_contents($tmpfile, $contents);
    @exec("$binary set root $tmpfile");
}

// no need to unlink $tmpfile since the whole folder will be removed anyway


// stopping the monitor
$jxpath = pm_Settings::get("jxpath");
if (file_exists($jxpath)) {
    @exec("$jxpath monitor stop");
}


Common::callService("nginx", "remove&all=1", null, null);

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
    echo "Just the debug. Uninstalling jxcore extension in pre-uninstall.php: The id is: $id";

    pm_Bootstrap::init();
    pm_Bootstrap::getDbAdapter()->delete('custom_buttons', array("url like '%jxcore_support%'"));

    exit(0);
}
