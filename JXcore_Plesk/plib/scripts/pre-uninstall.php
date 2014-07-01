<?php

pm_Context::init('jxcore_support');
$id = pm_Settings::get('customButtonId');


// JXcore crontab cleaning

$binary = "/opt/psa/admin/bin/crontabmng";

$tmpfile = pm_Context::getVarDir() . "mycron";
@exec("$binary get root > $tmpfile");
$contents = file_get_contents($tmpfile);
$contents = preg_replace('/(#JXcore_Begin\\n)(.*)(\\n#JXcore_End)/si', '', $contents);

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
    echo "The id is: $id";
    // todo: fix this
    exit(1);
}
