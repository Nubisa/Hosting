<?php
pm_Context::init("jxcore-support");

$iconPath = rtrim(pm_Context::getHtdocsDir(), '/') . '/images/Nubisa.ico';
$baseUrl = pm_Context::getBaseUrl();

$request = <<<APICALL
<ui>
   <create-custombutton>
         <owner>
            <admin/>
         </owner>
      <properties>
         <file>$iconPath</file>
         <public>true</public>
         <internal>true</internal>
         <noframe>true</noframe>
         <place>navigation</place>
         <url>$baseUrl</url>
         <text>JXcore Plesk for Node</text>
      </properties>
   </create-custombutton>
</ui>
APICALL;

try {
    $response = pm_ApiRpc::getService()->call($request);

    $result = $response->ui->{"create-custombutton"}->result;
    if ('ok' == $result->status) {
        pm_Settings::set('customButtonId', $result->id);
        echo "done\n";
        exit(0);
    } else {
        echo "error $result->errcode: $result->errtext\n";
        exit(1);
    }

} catch(PleskAPIParseException $e) {
    echo $e->getMessage() . "\n";
    exit(1);
}
