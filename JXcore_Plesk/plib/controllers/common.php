<?php

/*
 * todo:
 * when uploading and app file, after forced exception - it does not restart
 *
 * domain config panel:
 *      on textboxes, when user clears the textbox - display info about global value used
 *
 * on uninstall:
 *      removing nginx configs for jxcore + reload nginx
 *      cleaning .htaccess for each domain
 *      removing jxcore_logs folder for each domain
 *
 *
 *
 * known issues:
 *      - when extension is uninstalled, and we upload some files to folders, where extension generally resides:
 *      1. /opt/psa/var/modules/jxcore_support/
 *      2. /opt/psa/admin/plib/modules/jxcore_support/
 *      then installimng an extension may fail with:
 *      Error: Unable to install the extension: filemng failed: filemng: Error occurred during /bin/cp command.
 *
 *
 * useful commands:
 *      - fix plesk config:
 *          /usr/local/psa/bin/repair -r
 *      - reload nginx config:
 *          /etc/init.d/nginx reload
 *
 * sudo date --set "15 May 2014 11:30:00"
 *
 *
 * To check:
 *  - is maxMem and maxCPU value - really disabling the restriction?
 */


class Common
{
    public static $urlMonitor = "";
    public static $urlMonitorLog = "";
    public static $urlService = "http://localhost:18999/";

    public static $urlJXcoreConfig = "";
    public static $urlJXcoreDomains = "";
    public static $urlJXcoreModules = "";
    public static $urlJXcoreSubscriptions = "";
    public static $urlJXcoreMonitorLog = "";
    public static $urlDomainConfig = "";
    public static $urlDomainAppLog = "";

    public static $firstRun = false;

    public static $jxv = null;
    public static $jxpath = null;

    public static $dirNativeModules = null;
    public static $dirAppsConfigs = null;
    public static $dirSubscriptionConfigs = null;

    public static $pathSpawner = null;
    public static $pathService = null;

    public static $startupBatchPath = null;

    public static $isAdmin = false;

    // those are keys for storing values from forms.
    // do not change them in production env, or else you will loose access to previous values.
    const sidJXversion = "jxv";
    const sidJXpath = "jxpath";
    const sidMonitorEnabled = "jx_global_monitor_enabled"; // monitor start on reboot
    const sidFirstRun = "jx_first_run"; // empty when extension is started for the first time
    const sidDomainApp = "jx_domain_app"; // for storing information about domain's app (path, port etc.)
    const sidDomainAppLogWebAccess = "jx_domain_app_log_web_access";
    const sidDomainJXcoreEnabled = "jx_domain_jxcore_enabled";
    const sidDomainJXcoreAppPort = "jx_domain_app_port";
    const sidDomainJXcoreAppPortSSL = "jx_domain_app_port_ssl";
    const sidDomainJXcoreAppPath = "jx_domain_app_path";

    // subscription parameters
    public static $subscriptionParams = null;

    const sidDomainJXcoreAppMaxCPULimit = "jxparam_maxCPU";
    const sidDomainJXcoreAppMaxCPUInterval = "jxparam_maxCPUInterval";
    const sidDomainJXcoreAppMaxMemLimit = "jxparam_maxMemory";
    const sidDomainJXcoreAppAllowCustomSocketPort = "jxparam_allowCustomSocketPort";
    const sidDomainJXcoreAppAllowSysExec = "jxparam_allowSysExec";
    const sidDomainJXcoreAppAllowLocalNativeModules = "jxparam_allowLocalNativeModules";

    const sidJXcoreMinimumPortNumber = "jx_app_min_port";
    const sidJXcoreMaximumPortNumber = "jx_app_max_port";

    const sidMonitorStartScheduledByCron = "jx_monitor_start_scheduled_by_cron";
    const sidMonitorStartScheduledByCronAction = "jx_monitor_start_scheduled_by_cron_action";

    const iconON = '<img src="/theme/icons/16/plesk/on.png" style="vertical-align: middle; display: inline; margin-right: 7px;" height="16" width="16">';
    const iconOFF = '<img src="/theme/icons/16/plesk/off.png" style="vertical-align: middle; display: inline; margin-right: 7px;" height="16" width="16">';

    const iconUrlDelete = "/theme/icons/16/plesk/delete.png";
    const iconUrlReload = "/theme/icons/16/plesk/show-all.png";
    const iconUrlDownload = "/theme/icons/16/plesk/download-files.png";

    const minApplicationPort_default = 10000;
    const maxApplicationPort_default = 20000;

    public static $minApplicationPort = minApplicationPort_default;
    public static $maxApplicationPort = maxApplicationPort_default;

    private static $hrId = 0;
    private static $controller = null;
    public static $status = null;

    private static $buttonsDisabling = [];

    private static $domains = [];
    private static $domainsFetched = false;

    private static $monitorJSON = null;
    private static $monitorJSONFetched = false;

    public static $needToReloadNginx = false;

    function Common($controller, $status = null)
    {
        self::$controller = $controller;
        self::$status = $status;
        StatusMessage::$status = $status;

        self::refreshValues();
    }

    public static function getDomain($id) {
        $id = intval($id);
        self::getDomains();
        return self::$domains[$id] ? self::$domains[$id] : null;
    }

    public static function getDomainsIDs () {
        self::getDomains();
        return array_keys(self::$domains);
    }

    /**
     * Reads domain list from database - does this only once per page refresh
     * @return array
     */
    private static function getDomains() {
        if (!self::$domainsFetched) {
            self::$domains = [];
            // fetching domain list
            $dbAdapter = pm_Bootstrap::getDbAdapter();
            $sql = "SELECT d.*, h.www_root, h.sys_user_id as sysId, u.login as sysLogin, u.home as sysHome
                   FROM domains d
                   left outer join hosting h on d.id = h.dom_id
                   left outer join sys_users u on h.sys_user_id = u.id
                   where htype = 'vrt_hst' order by id ASC";

            $statement = $dbAdapter->query($sql);

            while ($row = $statement->fetch()) {
                self::$domains[intval($row['id'])] = DomainInfo::getFromRow($row);
            }
            self::$domainsFetched = true;
        }
        return self::$domains;
    }


    public static function refreshValues()
    {
        self::getDomains();

        $baseUrl = pm_Context::getBaseUrl();
        $varDir = pm_Context::getVarDir();

        self::$urlJXcoreDomains = $baseUrl . "index.php/index/listdomains";
        self::$urlMonitor = "http://localhost:17777/json?silent=true";
        self::$urlMonitorLog = "http://localhost:17777/logs";
        self::$urlJXcoreConfig = $baseUrl . "index.php/index/jxcore";
        self::$urlJXcoreModules = $baseUrl . "index.php/index/listmodules";
        self::$urlJXcoreSubscriptions = $baseUrl . "index.php/index/listsubscriptions";
        self::$urlJXcoreMonitorLog = $baseUrl . "index.php/index/log";
        self::$urlDomainConfig = $baseUrl . "index.php/domain/config";
        self::$urlDomainAppLog = $baseUrl . "index.php/domain/log";

        self::$firstRun = !pm_Settings::get(self::sidFirstRun); // if empty, that it is first run

        self::$jxv = pm_Settings::get(self::sidJXversion);
        self::$jxpath = pm_Settings::get(self::sidJXpath);

        self::$startupBatchPath = $varDir . "jxcore-for-plesk-startup.sh";

        $client = pm_Session::getClient();
        self::$isAdmin = $client->isAdmin();

        $v = pm_Settings::get(self::sidJXcoreMinimumPortNumber);
        self::$minApplicationPort = $v ? $v : self::minApplicationPort_default;

        $v = pm_Settings::get(self::sidJXcoreMaximumPortNumber);
        self::$maxApplicationPort = $v ? $v : self::maxApplicationPort_default;

        self::$dirNativeModules = $varDir . "native_modules/";
        self::$dirAppsConfigs = $varDir . "app_configs/";
        self::$dirSubscriptionConfigs = $varDir . "subs_configs/";

        self::$pathSpawner = pm_Context::getVarDir() . "spawner.jx";
        self::$pathService = pm_Context::getVarDir() . "service.jx";

//        $takenPorts = self::getTakenAppPorts();
//        self::$status->addMessage("info", "min = " . self::$minApplicationPort. ", max = ".self::$maxApplicationPort. ". Taken ports: " . join(",", $takenPorts));

        self::$subscriptionParams = array(
            self::sidDomainJXcoreAppMaxMemLimit => array("disableValue" => "0"),
            self::sidDomainJXcoreAppMaxCPULimit => array("disableValue" => "0"),
            self::sidDomainJXcoreAppMaxCPUInterval => array(),
            self::sidDomainJXcoreAppAllowCustomSocketPort => array(),
            self::sidDomainJXcoreAppAllowSysExec => array("defaultValue" => 1),
            self::sidDomainJXcoreAppAllowLocalNativeModules => array("defaultValue" => 1),
        );
    }


    public static function getMonitorJSON($refresh = false)
    {
        if (!self::$monitorJSONFetched || $refresh) {
            $tmp = null;
            $monitorRunning = self::getURL(self::$urlMonitor, $tmp);
//            StatusMessage::addDebug("Loading monitor");
            self::$monitorJSONFetched = true;
            if ($monitorRunning) {
                self::$monitorJSON = $tmp;
            } else {
                self::$monitorJSON = null;
            }
        }
        return self::$monitorJSON;
    }

    public static function clearMonitorJSON(){
        self::$monitorJSONFetched = false;
        self::$monitorJSON = null;
    }

    public static function addHR(&$form)
    {
        $form->addElement('hidden', 'hr' . self::$hrId++, array(
                'required' => false,
                'ignore' => true,
                'autoInsertNotEmptyValidator' => false,
                'decorators' => array(
                    array(
                        'HtmlTag', array('tag' => 'hr')
                    )
                )
            )
        );
    }

    public static function isJXValid()
    {
        return self::$jxv && self::$jxpath && file_exists(self::$jxpath);
    }

    public static function updateAllConfigsIfNeeded() {

        Common::updateBatchAndCron();

        Common::mkdir(self::$dirSubscriptionConfigs);

        $subs = SubscriptionInfo::getIds();
        foreach ($subs as $id) {
            $sub = SubscriptionInfo::getSubscription($id);
            $sub->updateConfigs();
        }
    }

    // saves config for main jx (which will run the monitor)
    private static function saveConfig($path = null) {

        // globalModulePath: string
        // globalApplicationConfigPath

        // accessible by admin:

        // maxMemory: long kB
        // maxCPU: int
        // allowCustomSocketPort: bool
        // allowSysExec: bool
        // allowLocalNativeModules: bool

        if (!$path) $path = self::$jxpath;

        if (file_exists($path)) {
            $dir = dirname($path) . "/";

            $cfg = '{
                       "monitor" :
                       {
                           "log_path" : "' . $dir . 'jx_monitor_[WEEKOFYEAR]_[YEAR].log",
                           "users": [ "psaadm" ]
                       },
                       "globalModulePath" : "' . self::$dirNativeModules . '",
                       "globalApplicationConfigPath" : "' . self::$dirAppsConfigs . '",
                       "npmjxPath" : "' . dirname(self::$jxpath) . '"
                    }';

            file_put_contents($dir . "jx.config", $cfg);
        }
    }

    /*
     * Creates a folder and sets chmod for with write access only for psaadm
     */
    public static function mkdir($dir) {

        // chmod for directories : rwx for psaadm, rx for the rest - processes spawned as users need to read this
        $mode = 0755;
        if (!@is_dir($dir)) {
            @mkdir($dir, $mode);
        } else {
            @chmod($dir, $mode);
        }

        return is_dir($dir);
    }

    /**
     * Removes folder recursively
     * @param $dir
     * @return bool
     */
    public static function rmdir($dir) {
        if (is_dir($dir)) {
            $files = array_diff(scandir($dir), array('.', '..'));

            foreach ($files as $file) {
                @unlink("$dir/$file");
            }
            return @rmdir($dir);
        } else {
            return true;
        }
    }

    public static function setJXdata($version, $path)
    {
        pm_Settings::set(self::sidJXversion, $version);
        pm_Settings::set(self::sidJXpath, $path);

        self::$jxv = null;
        self::$jxpath = null;

        Common::mkdir(self::$dirNativeModules);
        Common::mkdir(self::$dirAppsConfigs);

        self::refreshValues();
        self::saveConfig($path);
    }

    public static function saveBlockToText($contents, $blockName, $blockBody, $beginning)
    {
        $commands = ["#{$blockName}-Begin", $blockBody, "#{$blockName}-End"];

        if (trim($blockBody) == "") {
            // removing block
            $contents = preg_replace('/(' . $commands[0] . ')(.*)(' . $commands[2] . ')/si', '', $contents);
        } else {
            if (strpos($contents, $commands[0]) === false)
                if ($beginning) {
                    $contents = join("\n", $commands) . "\n\n" . $contents;
                } else {
                    $contents .= "\n\n" . join("\n", $commands) . "\n\n";
                }
            else
                $contents = preg_replace('/(' . $commands[0] . ')(.*)(' . $commands[2] . ')/si', '$1' . "\n{$commands[1]}\n" . '$3', $contents);
        }

        $contents = trim($contents);
        if ($contents == "") $contents = "\n";

        return $contents;
    }

    public static function getURL($url, &$output)
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        $data = curl_exec($ch);
        curl_close($ch);

        $ok = $data !== false;
        $output = $ok ? $data : curl_error($ch);

        return $ok;
    }


    public static function clearPorts()
    {
//            self::$status->addMessage("info", "Clearing the ports");
        for ($a = 10000; $a < 10020; $a++) {
            pm_Settings::set(self::sidDomainJXcoreAppPort . "$a", 5);
            pm_Settings::set(self::sidDomainJXcoreAppPortSSL . "$a", 6);
        }
    }

    /**
     * Returns array of ports already taken by JXcore applications assigned to domains
     * @param $domainId - if this param provided, the result will not contain port of applications for this domain
     * @return array
     */
    public static function getTakenAppPorts($domainId = null, $clientId = null, $ssl = null)
    {
        $rows = self::$domains;

        $portsTaken = [];
        foreach ($rows as $id => $domain) {
            $me = $domainId && $id == $domainId;

            if ($clientId && $domain->row['cl_id'] != $clientId) continue;
            // array of strings

//            if (($me && $ssl !== false) || !$me) {
            $skip = $domainId && $id == $domainId && ($ssl === false || $ssl === null);
            if (!$skip) {
                $port = pm_Settings::get(Common::sidDomainJXcoreAppPort . $domain->row['id']);
                if ($port && ctype_digit($port))
                    $portsTaken[] = $port;
            }
            $skip = $domainId && $id == $domainId &&  ($ssl === true || $ssl === null);
//            if (($me && $ssl !== true) || !$me) {
            if (!$skip) {
                $port = pm_Settings::get(Common::sidDomainJXcoreAppPortSSL . $domain->row['id']);
                if ($port && ctype_digit($port))
                    $portsTaken[] = $port;
            }
        }

        return $portsTaken;
    }


    public static function getFreePorts($domainId, $ssl = null)
    {
        $takenPorts = self::getTakenAppPorts($domainId, null, $ssl);
        return self::getFreePortsFromTaken($takenPorts);
    }

    /**
     * Returns 5 first free ports
     * @param $takenPorts
     * @return array
     */
    public static function getFreePortsFromTaken($takenPorts)
    {
        $ret = [];
        for ($a = self::$minApplicationPort; $a <= self::$maxApplicationPort; $a++) {
            if (!in_array($a, $takenPorts)) $ret[] = $a;
            if (count($ret) > 5) break;
        }
        return $ret;
    }

    public static function reassignPorts() {
        if (!self::$minApplicationPort || !self::$maxApplicationPort) {
            return;
        }

        self::refreshValues();
        $port = self::$minApplicationPort;

        $domains = self::getDomains();
        foreach ($domains as $domain) {
            $domain->set(Common::sidDomainJXcoreAppPort, $port++);
            $domain->set(Common::sidDomainJXcoreAppPortSSL, $port++);
        }
    }

    public static function checkPortRange($domainId = null)
    {
        if (!self::$minApplicationPort || !self::$maxApplicationPort) {
            return;
        }

        $clientId = null;
        if (!self::$isAdmin) {
            $client = pm_Session::getClient();
            $clientId = $client->getId();
        }

        $takenPorts = Common::getTakenAppPorts(null, $clientId);
        foreach ($takenPorts as $port) {
            if ($port < self::$minApplicationPort || $port > self::$maxApplicationPort) {
                self::$status->addMessage("warning", "Some of the applications ports out out of the range now. They are still running on old ports.");
                break;
            }
        }
    }

    public static function check()
    {
        self::checkPortRange();
        self::checkCronScheduleStatus(true);
        self::checkNginx();
    }


    public static function updateBatchAndCron()
    {
        // first we write a batch, which will be executed by cron
        $commands = [];

        $monitorEnabled = true;

        if ($monitorEnabled) {
            $commands = [
                // enabling proxy

                'if [ -d /etc/nginx/conf.d ] && [ ! -e /etc/nginx/conf.d/jxcore.conf ]; then',
                'mkdir /etc/nginx/jxcore.conf.d',
                'chown psaadm:nginx /etc/nginx/jxcore.conf.d',
                #'chmod 755 /etc/nginx/jxcore.conf.d',

                'echo "include /etc/nginx/jxcore.conf.d/*.conf;" > "/etc/nginx/conf.d/jxcore.conf"',
                'chown psaadm:nginx /etc/nginx/conf.d/jxcore.conf',
                'chmod 640 /etc/nginx/conf.d/jxcore.conf',
                'fi',
                '',
                'cd ' . dirname(Common::$jxpath),
                './jx monitor start',
                './jx monitor run ' . Common::$pathService . " &"
            ];

            foreach (self::$domains as $id=>$domain) {
                $domain = Common::getDomain($id);

                $enabled = $domain->JXcoreSupportEnabled();
                $path = $domain->getAppPath(true);

                $cmd = $domain->getSpawnerCommand();
                if (!$cmd) continue;

                if ($enabled) {
                    $commands[] = '';
                    $commands[] = "if [ -e {$domain->getSpawnerPath()} ]; then";
                    $commands[] = $cmd;
                    $commands[] = "fi";
                }
            }

            $commands[] = "";
        }

        file_put_contents(Common::$startupBatchPath, join("\n", $commands));
        chmod(Common::$startupBatchPath, 0700);


        // modifying crontab
        $binary = "/opt/psa/admin/bin/crontabmng";

        $tmpfile = pm_Context::getVarDir() . "mycron";
        @exec("$binary get root > $tmpfile");
        $contents = file_get_contents($tmpfile);

        $cmd = "@reboot " . Common::$startupBatchPath;
        $contents = self::saveBlockToText($contents, "JXcore", $monitorEnabled ? $cmd : "", null);

        if (trim($contents) === "") {
            @exec("$binary remove root");
        } else {
            // a NewLine characted was needed on Nubisa's production server
            file_put_contents($tmpfile, $contents . "\n");
            @exec("$binary set root $tmpfile", $out, $ret);
            if ($ret) {
                self::$status->addMessage("error", "Cannot change root's crontab: " . join("\n", $out) . ". Exit code: $ret");
            }
        }

        unlink($tmpfile);
    }


    /**
     * Modifying crontab for immediate execution (next minute)
     * @return int - number of seconds until cron job should be started
     */
    public static function updateCronImmediate($action = null)
    {
        $now = date("i-H-d-m-s");
        $parsed = explode('-', $now);
        $sec = $parsed[4];
        $nextMinute = intval($parsed[0]) + 1;
        $hour = intval($parsed[1]);
        $day = intval($parsed[2]);
        if ($nextMinute > 59) {
            $hour++;
            $nextMinute = $nextMinute - 60;
        }

        $out = null;
        $ret = null;

        $binary = "/opt/psa/admin/bin/crontabmng";

        $tmpfile = pm_Context::getVarDir() . "mycron_immediate";
        @unlink($tmpfile);
        @exec("$binary get root > $tmpfile 2>&1", $out, $ret);

        if ($ret) {
            self::$status->addMessage("error", "Cannot get crontab list: " . join("\n", $out));
            return;
        }

        $contents = file_get_contents($tmpfile);

        // min hour day month
        $timing = "{$nextMinute} " . $hour . " " . intval($parsed[2]) . " " . intval($parsed[3]);
        $cmd = "";
        if ($action == 'start') {
            $cmd = $timing . " * " . Common::$startupBatchPath;
        } else if ($action == 'stop') {
            $cmd = $timing . " * " . Common::$jxpath . " monitor stop";
        }

        $contents = Common::saveBlockToText($contents, "JXcore-immediate", $cmd, null);

        // a NewLine characted was needed on Nubisa's production server
        $contents = trim($contents) . "\n";
        file_put_contents($tmpfile, $contents);
        @exec("$binary set root $tmpfile 2>&1", $out, $ret);
        if ($ret) {
            self::$status->addMessage("error", "Cannot set immediate crontab job (arg = {$action}): " . join("\n", $out) . $cmd);
            return;
        }

        @unlink($tmpfile);

        $timestamp = mktime($hour, $nextMinute, 0, $parsed[3], $parsed[2]);
        pm_Settings::set(self::sidMonitorStartScheduledByCron, $action == null ? null : $timestamp);
        pm_Settings::set(self::sidMonitorStartScheduledByCronAction, $action);
    }

    public static function checkCronScheduleStatus($addMessage)
    {
        $action = pm_Settings::get(self::sidMonitorStartScheduledByCronAction);
        $timestamp = pm_Settings::get(self::sidMonitorStartScheduledByCron);
        if (!$timestamp) return null;

        $now = mktime();
        $diff = $timestamp - $now;

        if ($diff > 0) {

            if ($addMessage) {
                // $waitSeconds = 10;
                $waitSeconds = $diff + 10;
                $refresh = '<script type="text/javascript">var cnt = ' . $waitSeconds
                . '; '
                . 'if(cnt>0){'
                . '  setTimeout(function(){'
                . '    var elm = document.getElementById("content");'
                . '    var msg = document.createElement("div");'
                . '    msg.id = "__waitjx"; msg.name = "__waitjx";'
                . '    msg.innerHTML = "Please wait for JXcore to complete installation.. <style> .clearfix{display:none} </style>";'
                . '    elm.appendChild(msg);'
                . '  },1);'
                . '}; var loop = function() { cnt--; if(cnt>=0){ document.getElementById("jx_refresh_count").innerHTML = cnt; };  if (cnt<0) document.location.reload(); else setTimeout(loop, 1000); }; loop() ;</script>';

                //self::$status->addMessage("info", "timestamp = $timestamp, now = $now, diff = $diff");
                $txt = "";
                if ($action == 'start') $txt = "Completing the operation...";
                if ($action == 'stop') $txt = "Monitor should be stopped in approx {$diff} seconds.";

                $str = "$txt Page will be reloaded in <span id='jx_refresh_count' name='jx_refresh_count'>5</span> seconds." . $refresh;
                self::$status->addMessage('info', $str, true);
            }
        } else {

            $json = self::getMonitorJSON();
            $monitorRunning = $json !== null;
            if ($monitorRunning && $action == 'start') {
                self::updateCronImmediate();
            } else if (!$monitorRunning && $action == 'stop') {
                self::updateCronImmediate();
            } else {
                if ($diff < -15) {
                    $txt = null;
                    if ($action == 'start') $txt = "Could not start the JXcore monitor with crontab";
                    if ($action == 'stop') $txt = "Could not stop the JXcore monitor with crontab";
                    if ($txt) self::$status->addMessage("error", $txt);

                    // error - monitor did not start after 15 secs
                    self::updateCronImmediate();
                }
            }
        }

        return $diff;
    }

    /**
     * Returns Icon representing ON/OFF state (depending of $flag) and displays proper caption.
     * @param $flag
     * @param $captionON
     * @param $captionOff
     * @param null $additionalStyle
     * @return string
     */
    public static function getIcon($flag, $captionON, $captionOff, $additionalStyle = null)
    {
        $style = "vertical-align: middle; display: inline;";
        $icon = $flag ? Common::iconON : Common::iconOFF;
        $caption = $flag ? $captionON : $captionOff;
        return "<span style=\"{$style}{$additionalStyle}\">{$icon}<span style=\"$style\">$caption</span></span>";
    }

    public static function getButtonStartStop($flag, $varName, $captionsOn, $captionsOff, $url = null)
    {
        $captionOn = $captionOff = $actionOn = $actionOff = "";
        if (is_array($captionsOn)) {
            $captionOn = $captionsOn[0];
            if (count($captionsOn) > 1) $actionOn = $captionsOn[1];
        }
        if (is_array($captionsOff)) {
            $captionOff = $captionsOff[0];
            if (count($captionsOff) > 1) $actionOff = $captionsOff[1];
        }

        $btnStart = self::getSimpleButton($varName, $actionOn, "start", "/theme/icons/16/plesk/start.png", $url);
        $btnStop = self::getSimpleButton($varName, $actionOff, "stop", "/theme/icons/16/plesk/stop.png", $url);

        return self::getIcon($flag, $captionOn, $captionOff, "display: inline-block; min-width: 80px;") . ($flag ? $btnStop : $btnStart);
    }

    public static function getSimpleButton($varName, $caption, $command, $iconURL = null, $url = null, $additionalStyle = null)
    {
        $style = "vertical-align: middle; display: inline-block;";
        $iconStyle = "style=\"$style margin-right: 7px;\"";
        $iconId = "jx-icon-{$varName}-" . count(self::$buttonsDisabling);
        $icon = $iconURL ? "<img id=\"$iconId\" name=\"$iconId\" class='tootlipObserved' src='$iconURL' height='16' width='16' $iconStyle>" : "";

        if (!$url) {
            // form
            $btnstyle = "style='height: 15px; margin-left: 20px; margin-bottom: 6px; margin-top: 6px; $style $additionalStyle'";
            $onclick = "href=\"#\"";
            if ($command) $onclick .= " onclick=\"document.getElementById('{$varName}').value = '{$command}'; if (JXDisableButtons) { JXDisableButtons(); }; document.getElementById('pm-form-simple').submit();\"";
        } else {
            // list
            $btnstyle = "style='height: 15px; margin-left: 20px; $style $additionalStyle'";
            $onclick = "href=\"$url\"";
        }

        $id = "jx-btn-{$varName}-" . count(self::$buttonsDisabling);

        $script = "var el = document.getElementById('{$id}');";
        $script .= "if (el) { el.className = 'btn disabled'; delete el.href; el.onclick = 'return false;' }";
        // finding the disabled version of the icon
        if ($iconURL) {
            list( $dirname, $basename, $extension, $filename ) = array_values( @pathinfo($iconURL) );
            $fname = "/usr/local/psa/admin/htdocs/theme/icons/16/plesk/" . $filename . "-disabled." . $extension;
            if (@file_exists($fname)) {
                $script .= "var img = document.getElementById('{$iconId}');";
                $script .= "if (img) img.src = \"" . str_replace( "{$filename}.{$extension}", "{$filename}-disabled.$extension", $iconURL)."\";";
            }
        }
        self::$buttonsDisabling[] = $script;


        return "<a id=\"$id\" name=\"$id\" class='btn' $onclick $btnstyle>{$icon}{$caption}</a>";
    }

    /**
     * Generates javascript code for browser, which disables buttons on the form, whenever one of the buttons was clicked.
     * @return string
     */
    public static function getButtonsDisablingScript() {
        $arr = [];
        $arr[] = "<script type=\"text/javascript\">";
        $arr[] = "function JXDisableButtons() {";

        $arr = array_merge($arr, self::$buttonsDisabling);

        $arr[] = "}";
        $arr[] = "</script>";

        return join("\n", $arr);
    }


    public static function enableServices() {
        self::enableHttpProxy();
        self::enableNginx();
    }

    private static function enableHttpProxy() {
        //return;

        $cmd1 = "/opt/psa/admin/bin/httpd_modules_ctl -s";
        $cmd2 = "/opt/psa/admin/bin/httpd_modules_ctl -e proxy_http";

        $out = null;
        $ret = null;
        @exec($cmd1, $out, $ret);
        if (!$ret) {
            // checks proxy_http status
            $isOn = strpos($out, "proxy_http on") !== false;
            $isOff = strpos($out, "proxy_http on") !== false;

            if ($isOff){
                @exec($cmd2, $out, $ret);
                if (!$ret) {
                    $ret = shell_exec($cmd1);
                    $isOn = strpos($out, "proxy_http on") !== false;
                    if (!$isOn) {
                        self::$status->addMessage("error", "The module proxy_http could not be enabled.");
                    }
                } else {
                    self::$status->addMessage("error", "Cannot enable proxy_http.");
                }
            }
        } else {
            self::$status->addMessage("error", "Cannot fetch proxy_http status");
        }
    }

    /**
     * Checks status of nginx (Enabled/Disabled)
     * @param bool $verbose
     * @return bool
     */
    private static function checkNginx($verbose = true)
    {
        $cmd = '/opt/psa/admin/bin/nginxmng -s 2>&1';
        @exec($cmd, $out, $ret);

        if (!$ret) {
            $str = join("\n", $out);
            $enabled = strpos($str, "Enabled") !== false;
            if (!$enabled && $verbose)
                self::$status->addMessage('warning', "Nginx is not enabled. Status: $str.");

            return $enabled;
        } else {
            self::$status->addMessage("error", "Cannot fetch nginx status. " . join("\n", $out) . ". Exit code: $ret.");
            return false;
        }
    }

    private static function enableNginx()
    {
        $cmd2 = "/opt/psa/admin/bin/nginxmng -e 2>&1";

        if (!self::checkNginx(false)) {
            $out = null;
            $ret = null;
            @exec($cmd2, $out, $ret);
            if (!$ret) {
                $enabled = self::checkNginx(false);
                $isOn = strpos($out, "Enabled") !== false;
                if (!$isOn) {
                    self::$status->addMessage("error", "Nginx could not be enabled.");
                }
            } else {
                self::$status->addMessage("error", "Cannot enable Nginx.");
            }
        }
    }


    /**
     * Calls JXcore service process for running a command as root
     * @param $sid
     * @param $arg
     * @param string $msgOK
     * @param string $msgErr
     * @return bool
     */
    public static function callService($sid, $arg, $msgOK = null, $msgErr = null, $return = null) {

        $url = Common::$urlService . "cmd?{$sid}={$arg}";

        $ret = Common::getURL($url, $out);
        $out = htmlspecialchars($out);
        $ok = str_replace("\n", "<br>", trim($out)) == "OK";
        $err = $ret ? $out: "Cannot connect to JXcore service.";
        $msg = $ok ? $msgOK : ($msgErr ? "$msgErr $err" : null);
        if ($msg) {
            $msg = str_replace("#arg#", $arg, $msg);
            $msg = str_replace("#sid#", $sid, $msg);
            self::$status->addMessage($ok ?  "info": "error", $msg);
        }
        return $return && $ret ? $out : $ok;
    }



    public static function monitorStartStop($req)
    {
        if (!self::isJXValid() || !in_array($req, ['start', 'stop', 'restart'], true)) return;
        $cmd = null;

        $json = self::getMonitorJSON();
        $monitorWasRunning = $json !== null;

        if ($req == 'restart') {
            // refreshes the config every time monitor is called for restart
            self::updateAllConfigsIfNeeded();

            if ($monitorWasRunning) {
                self::monitorStartStop("stop");
                $json = self::getMonitorJSON(true);
                $monitorWasRunning = $json !== null;
            } else {
                // don't restart if was not running
                return;
            }
        }

        if (in_array($req, ['start', 'restart']) && !$monitorWasRunning) {
            self::enableServices();
            $ret = Common::updateCronImmediate("start");
            $cmd = null;
        } else
            if ($req === 'stop' && $monitorWasRunning) {
                $cmd = Common::$jxpath . " monitor stop";
            } else {
                return;
            }

        if ($cmd !== null) {
            $cwd = getcwd();
            chdir(dirname(Common::$jxpath));
            @exec($cmd, $out, $ret);
            chdir($cwd);

            // let's get a new JSON with next self::getMonitorJSON() call
            self::clearMonitorJSON();

            if ($req === 'stop' && $monitorWasRunning) {
                // weird exit code on jx monitor stop (8)
                $json = self::getMonitorJSON();
                if ($json === null) $ret = 0;
            }

            if ($ret && $ret != 255) {
                self::$status->addMessage('error', "Could not execute command: $cmd. Error code = $ret. " . join(", ", $out));
            }
        }

        $json = self::getMonitorJSON();
        $monitorRunning = $json !== null;

        if ($req === 'start' && $monitorRunning && !$monitorWasRunning) {
            self::$status->addMessage('info', "JXcore Monitor successfully started.");
        }
        if ($req === 'stop' && !$monitorRunning && $monitorWasRunning) {
            self::$status->addMessage('info', "JXcore Monitor successfully stopped.");
        }
    }
}


class DomainInfo
{
    public $id = 0;
    public $rootFolder = "";
    public $appLogPath = "";
    public $appLogDir = "";
    public $name = "";

    public $sysUser = null;
    public $sysUserId = null;
    public $sysUserHomeDir = null;

    public $webspaceId = null;
    public $subscriptionId = null;

    private $domain = null;
    private $fileManager = null;

    const appLogBasename = "index.txt";
    const appPath_default = "index.js";

    public $configChanged = false;
    private $appFileNameChanged = false;

    public $row = null;

    public $log = [];

    public static function getFromRow($row) {
        $domain = new DomainInfo($row['id']);
        $domain->rootFolder = $row['www_root'] . "/";
        $domain->appLogDir = $domain->rootFolder . "jxcore_logs/";
        $domain->appLogPath = $domain->appLogDir . self::appLogBasename;
        $domain->row = $row;
        $domain->webspaceId = $row['webspace_id'];

        $domain->sysUserId = $row['sysId'];
        $domain->sysUser = $row['sysLogin'];
        $domain->sysUserHomeDir = $row['sysHome'];
        return $domain;
    }

    // private constructor
    private function DomainInfo($id)
    {
        $this->domain = new pm_Domain($id);
        $this->fileManager = new pm_FileManager($id);
        $this->id = $id;
        $this->name = $this->domain->getName();
    }

    public function get($sid) {
        return pm_Settings::get($sid . $this->id);
    }

    public function wasSet($sid) {
        return pm_Settings::get($sid . $this->id . "isset") == "true";
    }

    /**
     * Sets param's value
     * @param $sid
     * @param $value
     */
    public function set($sid, $value) {
        $old = $this->get($sid);
        pm_Settings::set($sid . $this->id, $value === null ? "" : $value);
        pm_Settings::set($sid . $this->id . "isset", $value === null ? "" : "true");

        $changed = $old != $value;
//        if ($changed) StatusMessage::addDebug("Changed $sid from $old to $value");

        if ($changed) {
            $this->configChanged = true;
            if ($sid == Common::sidDomainJXcoreAppPath)
                $this->appFileNameChanged = true;
        }
    }


    /**
     * Gets value defined for config param, or if was not defined - from subscription
     * @param $sid
     * @return null
     */
    public function getFinalConfigValue($sid) {

        $isCommon = isset(Common::$subscriptionParams[$sid]);

        if (!$isCommon) {
            return $this->get($sid);
        }

        $sub = $this->getSubscription();

        $vald = $this->get($sid);
        $vals = $sub->get($sid);

        $wasSet = $this->wasSet($sid);

        $edits = [Common::sidDomainJXcoreAppMaxMemLimit, Common::sidDomainJXcoreAppMaxCPULimit, Common::sidDomainJXcoreAppMaxCPUInterval];
        if (in_array($sid, $edits) && !$vald && "$vald" !== "0" )
            $wasSet = false;

        $ret = null;

        if (!$wasSet && $vals) {
            $ret = $vals;
        } else {
            $ret = $vald;
        }

        return $ret;
    }


    /**
     * Asks the monitor if the app si running
     * @param null $wait - if true, then checking is done in short loop
     * @return bool
     */
    public function isAppRunning($wait = null)
    {
        $json = Common::getMonitorJSON();
        $path = $this->getSpawnerPath();

        $running =  $json !== null && $path !== false && strpos($json, $path) !== false;

        if ($wait) {
            sleep(2);
            for($a=1; $a<10; $a++) {
                Common::clearMonitorJSON();
                $running = $this->isAppRunning(null);
                if ($running) break;
                sleep(1);
            }

        }
        return $running;
    }

    public function getAppStatus()
    {
        $p = $this->getAppPathOrDefault(true);
        if (file_exists($p)) {
            $json = Common::getMonitorJSON();
            $monitorRunning = $json !== null;

            if ($monitorRunning) {
                $port = $this->getAppPort();
                if ($port < Common::$minApplicationPort || $port > Common::$maxApplicationPort) {
                    $port .= "<br><span style='color: orangered'>TCP port out of range.</span>";
                }

                $portSSL = $this->getAppPort(true);
                if ($portSSL < Common::$minApplicationPort || $portSSL > Common::$maxApplicationPort) {
                    $portSSL .= "<br><span style='color: orangered'>TCPS port out of range.</span>";
                }

                return Common::getIcon($this->isAppRunning(), "Running on TCP: $port, TCPS: $portSSL", "Not running");
            } else {
                $ret = Common::checkCronScheduleStatus(false);
                $str = ($ret && $ret > 0) ? "<br>Monitor is starting in $ret secs." : "Monitor offline.";
                return Common::getIcon(false, "", "Not running. $str");
            }
        } else {
            return "<span style=\"color: orangered;\">No file:</span> " . $this->getAppPathOrDefault();
        }
    }

    public function JXcoreSupportEnabled()
    {
        return $this->get(Common::sidDomainJXcoreEnabled);
    }

    public function getAppPort($ssl = false)
    {
        $sid = $ssl ? Common::sidDomainJXcoreAppPortSSL : Common::sidDomainJXcoreAppPort;
        return pm_Settings::get( $sid . $this->id);
    }

    public function getAppPortOrDefault($updateIfEmpty = false, $ssl = false)
    {
        $sid = $ssl ? Common::sidDomainJXcoreAppPortSSL : Common::sidDomainJXcoreAppPort;

        $port = pm_Settings::get($sid . $this->id);
        if (!$port || trim($port) === '') {
            $port = Common::getFreePorts($this->id, $ssl)[0];
            if ($updateIfEmpty)
                $this->setAppPort($port, $ssl);
        }
        return $port;
    }

    public function setAppPort($port, $ssl = false)
    {
        $sid = $ssl ? Common::sidDomainJXcoreAppPortSSL : Common::sidDomainJXcoreAppPort;
        pm_Settings::set($sid . $this->id, $port);
    }

    private function getPortStatus($val)
    {
        if (!$val)
            return "<span style='color: orangered'>not defined.</span>";

        if ($val < Common::$minApplicationPort || $val > Common::$maxApplicationPort)
            return "<br><span style='color: orangered'>out of range.</span>";
        else
            return $val;
    }

    public function getAppPortStatus($ssl = null, $addType = false)
    {
        $ret = "";
        if ($ssl === null || $ssl === false) {
            if ($addType) $ret .= "TCP: ";
            $ret .= $this->getPortStatus($this->getAppPort(false));
        }

        if ($ssl === null) $ret .= " / ";

        if ($ssl === null || $ssl === true) {
            if ($addType) $ret .= "TCPS: ";
            $ret .= $this->getPortStatus($this->getAppPort(true));
        }

        $ret = trim($ret);
//        $ret = str_replace("\n", "<br>", $ret);
        return $ret;
    }

    public function getAppPath($fullPath = false)
    {
        $val = $this->get(Common::sidDomainJXcoreAppPath);
        if (!$val)
            return false;
        else
            return $fullPath ? $this->rootFolder . $val : $val;
    }

    public function getAppPathOrDefault($fullPath = false, $updateIfEmpty = false)
    {
        $path = $this->getAppPath(false);

        if (!$path || trim($path) === '') {
            $path = DomainInfo::appPath_default;
            if ($updateIfEmpty)
                pm_Settings::set(Common::sidDomainJXcoreAppPath . $this->id, $path);
        }

        return $fullPath ? $this->rootFolder . $path : $path;
    }

    public function getAppLogWebAccess()
    {
        return pm_Settings::get(Common::sidDomainAppLogWebAccess . $this->id);
    }


    /**
     * Returns true if JXcore support can be enabled for this domain, or err string, if not.
     * @return bool|string
     */
    public function canEnable()
    {
        $str = "";
        $port = $this->getAppPort();
        if (!$port) $str .= "App port is not specified.";

        $path = $this->getAppPathOrDefault(true);
        if (!file_exists($path)) $str .= "No file: " . $this->getAppPath() . ".";

        return trim($str) != "" ? "<span style='color: orangered'>$str</span>" : true;
    }

    /**
     * Copies spawner file for domain, sets it's permissions and returns full parh (or false on error)
     * @return bool|string
     */
    public function getSpawnerPath()
    {
        $spawnerOrg = Common::$pathSpawner;
        chmod($spawnerOrg, 0644);
        $spawner = pm_Context::getVarDir() . "spawner_{$this->id}.jx";
        if (copy($spawnerOrg, $spawner) === false) {
            StatusMessage::addError("Cannot copy spawner for application of domain {$this->name}.");
            return false;
        }
        if (chmod($spawner, 0644) === false) {
            StatusMessage::addError("Cannot set permissions for application's spawner of domain {$this->name}.");
            return false;
        }

        return $spawner;
    }

    /**
     * Returns parameters for spawner command line (log, user, appFile)
     * @param $additionalParams
     * @return string
     */
    private function getSpawnerParams()
    {
        $additionalParams = array(
            "user" => $this->sysUser,
            "log" => $this->appLogPath,
            "file" => $this->getAppPath(true),
            "domain" => $this->name,
            "tcp" => $this->getAppPort(),
            "tcps" => $this->getAppPort(true),
            "logWebAccess" => $this->getAppLogWebAccess());

        $arr = [];

        // as strings
        foreach ($additionalParams as $key => $val) {
            $arr[] = "\"{$key}\" : \"{$val}\"";
        }

        $json = "'{ " . join(", ", $arr) . "}'";
        return $json;
    }

    /**
     * Returns command to run a domains spawner, or null
     * @return null
     */
    public function getSpawnerCommand() {
        $sub = $this->getSubscription();
        if (!$sub) return null;

        $spawner = $this->getSpawnerPath();
        if ($spawner === false) return null;

        $opt = $this->getSpawnerParams();
        return $sub->jxpath . " {$spawner} -opt {$opt}";
    }

    public function clearLogFile()
    {
        if (file_exists($this->appLogPath)) {
            $oldSize = filesize($this->appLogPath);

            $running = $this->isAppRunning();

            $ret = false;
            if ($running) {
                $clearlog = $this->appLogDir . "clearlog.txt";
                $rel = str_replace($this->fileManager->getFilePath("."), "", $clearlog);
                $this->fileManager->filePutContents($rel, "clear");
                sleep(1);
                $newSize = filesize($this->appLogPath);

                $ret = $newSize < $oldSize;
            } else {
                $out = Common::callService("delete", "applog&path=" .$this->appLogPath, null, null, true);
                $ret = !file_exists($this->appLogPath);
            }

            StatusMessage::infoOrError(!$ret, 'Log cleared.', 'Could not clear the log file.');
        }
        return true;
    }

    public function getSubscription() {
        $id = $this->webspaceId;

        if ($id == 0) $id = $this->id;

        $subs = SubscriptionInfo::getIds();

        foreach ($subs as $sub_id) {
            $sub = SubscriptionInfo::getSubscription($sub_id);

            if ($sub->mainDomain->id === $id) {
                return $sub;
            }
        }

        // domain should always have a subscription
        StatusMessage::addError("Cannot find subscription for domain {$this->name}.");
        return null;
    }


    public function updateConfigs($forceRestart = false) {
        $this->updateJXConfig();
        $this->updatehtaccess();

        $running = $this->isAppRunning();
        $enabled = $this->JXcoreSupportEnabled();

        if ($this->configChanged || $forceRestart) {
            if ($enabled) {
                if ($running) {
                    $this->restartApp();
                } else {
                    $this->startApp();
                }
            } else {
                if ($running) {
                    $this->stopApp();
                } else {
                    // do nothing
                }
            }
        }
    }

    /**
     * Saves jx.config for the application
     * @return bool
     */
    private function updateJXConfig()
    {
        $jxenabled = $this->JXcoreSupportEnabled();

        // base file name for jx.config
        $bname = $this->getAppPath(true);
        $bname = str_replace("/", "_", $bname);
        $bname = str_replace("\\", "_", $bname);
        $bname = str_replace(":", "_", $bname);
        $bname .= ".jxcore.config";

        $fname = Common::$dirAppsConfigs . $bname;

        if (!$jxenabled) {
            @unlink($fname);
        } else {
            $params = array(
                "portTCP" => Common::sidDomainJXcoreAppPort,
                "portTCPS" => Common::sidDomainJXcoreAppPortSSL,
                "maxCPU" => Common::sidDomainJXcoreAppMaxCPULimit,
                "maxCPUInterval" => Common::sidDomainJXcoreAppMaxCPUInterval,
                "maxMemory" => Common::sidDomainJXcoreAppMaxMemLimit,
                "allowCustomSocketPort" => Common::sidDomainJXcoreAppAllowCustomSocketPort,
                "allowSysExec" => Common::sidDomainJXcoreAppAllowSysExec,
                "allowLocalNativeModules" => Common::sidDomainJXcoreAppAllowLocalNativeModules);

            // as non-strings
            foreach ($params as $key => $sid) {
                $val = $this->getFinalConfigValue($sid);
                if (trim($val) != "") {
                    $arr[] = "\"{$key}\" : {$val}";
                }
            }

            $json = "{ " . join(", ", $arr) . "}";

            $old = @file_get_contents($fname);


            if ($old !== $json) {
                $this->configChanged = true;

                $written = @file_put_contents($fname, $json);
                if ($written === false) {
                    StatusMessage::addError("Cannot write config for domain " . $this->name);
                    @unlink($fname);
                }
            }
        }
    }


    private function updatehtaccess()
    {
        $mgr = $this->fileManager;

        $basename = ".htaccess";
        $file = $this->rootFolder . $basename;

        $relFile = str_replace($mgr->getFilePath("."), "", $file);

        if ($this->JXcoreSupportEnabled()) {
            $htaccess = [];
            $htaccess[] = 'RewriteEngine On';

            if ($this->getAppLogWebAccess()) {
                $htaccess[] = 'RewriteCond %{REQUEST_URI} !/' . $this->appLogDir;// DomainInfo::appLogBasename;
            }

            $tcp = $this->getAppPort();
            $tcps = $this->getAppPort(true);
            $htaccess[] = 'RewriteCond %{SERVER_PORT} 80';
            $htaccess[] = 'RewriteRule ^(.*)$ http://0.0.0.0:' . $tcp . '/$1 [P]';

            $htaccess[] = 'RewriteCond %{SERVER_PORT} 443';
            $htaccess[] = 'RewriteRule ^(.*)$ https://0.0.0.0:' . $tcps . '/$1 [P]';

        } else {
            $htaccess = [];
        }
        $txt = "";

        if ($mgr->fileExists($relFile)) $txt = $mgr->fileGetContents($relFile);

        $txt = Common::saveBlockToText($txt, "JXcore-domainID-" . $this->id, join("\n", $htaccess), null);
        $txt = trim($txt);

        $mgr->filePutContents($relFile, $txt);

        $txt2 = file_get_contents($file);
        if ($txt2 !== $txt)
            return "Cannot save $file";

        return true;
    }

    private function startApp() {
        $json = Common::getMonitorJSON();
        $errMsg = "Cannot start the application {$this->name}:";

        // starting application
        if ($json === null) {
            // no point to run an application now, if monitor is not running
            StatusMessage::addError("$errMsg the JXcore monitor is not running.");
        } else {
            if ($this->isAppRunning()) return;

            $cmd = $this->getSpawnerCommand();
            if (!$cmd) {
                StatusMessage::addError("$errMsg invalid spawner command.");
                return;
            }
            @exec($cmd, $out, $ret);
            // waiting for the app to be restarted by monitor
            // cannot rely on exitcode, so checking the monitor
            Common::clearMonitorJSON();
            $appRunning = $this->isAppRunning(true);
            StatusMessage::infoOrError(!$appRunning, "The application {$this->name} successfully started.", "The application {$this->name} could not be started.");

            $this->configChanged = false;
        }
    }

    private function stopApp() {

        if (!$this->isAppRunning()) return;

        $nginexconf = "/etc/nginx/jxcore.conf.d/" . $this->name . ".conf";
        if (@file_exists($nginexconf)) {
            @unlink($nginexconf);
            Common::callService("nginx", "reload", null, "Cannot reload nginx config.");
        }

        $cmd = Common::$jxpath . " monitor kill {$this->getSpawnerPath()} 2>&1";
        @exec($cmd, $out, $ret);

        // cannot rely on exitcode, so checking the monitor
        Common::clearMonitorJSON();
        StatusMessage::infoOrError($this->isAppRunning(), "The application {$this->name} successfully stopped.", "Cannot stop the application. ". join("\n", $out) . ". Exit code: $ret");
        $this->configChanged = false;
    }

    private function restartApp() {
        if (!$this->isAppRunning()) {
            $this->startApp();
        } else {
            if ($this->appFileNameChanged) {
                $this->stopApp();
                $this->startApp();
            } else {
                // faster by killing teh process and letting the monitor to respawn
                $out = Common::callService("kill", $this->id, null, null, true);
//            StatusMessage::addDebug($out);
                Common::clearMonitorJSON();
                StatusMessage::infoOrError(!$this->isAppRunning(true), "The application {$this->name} was successfully restarted.", "Could not restart the application {$this->name}. $out");
            }
            $this->configChanged = false;
        }
    }
}


class PanelClient
{

    public $sysUser = null;
    public $panelLogin = null;
    public $type = null;
    public $whoami = null;
    public $statusBar = null;


    function PanelClient($clientId = null)
    {
        if (!$clientId) {
            $client = pm_Session::getClient();
            $clientId = $client->getId();
        }

        $this->whoami = shell_exec("whoami");

        $dbAdapter = pm_Bootstrap::getDbAdapter();


        $sql = "SELECT
            cli.login as cliLogin, cli.type as cliType
            FROM clients cli
            where cli.id = $clientId";

        $statement = $dbAdapter->query($sql);

        $row = $statement->fetch();



        $this->sysUser = $row["sysLogin"];
        $this->panelLogin = $row["cliLogin"];
        $this->type = $row["cliType"];
        $this->statusBar = "Client Id: {$clientId}, Username: <b>{$this->panelLogin}</b>. Account type: <b>{$this->type}</b>. Whoami: <b>{$this->whoami}</b>. System user: <b>{$this->sysUser}</b><hr>";
    }
}


class JXconfig {

    public $portTCP = 0; // int
    public $portTCPS = 0; // int

    public $globalModulePath = null; // string
    public $globalApplicationConfigPath = null; //string

    private static function addOrgValue(&$form, $sid, $domainId, $isDomain) {

        if (!$isDomain) return;

        $domain = Common::getDomain($domainId);
        $vald = $domain->get($sid);

        $form->addElement('hidden', "{$sid}_org", array(
            'value' => $vald
        ));
    }

    private static function check(&$form, $sid, $domainId, $isDomain) {

        return "";
        if (!$isDomain) return;

        $domain = Common::getDomain($domainId);
        $sub = $domain->getSubscription();

        $vald = $domain->get($sid);
        $vals = $sub->get($sid);

        if (!$vald && $vals) {

            if (!$form && $vals == 1) $vals = "'true'";
            $ret = "The value $vals from subscription config will be used unless you submit this form.";

            if ($form) {
                $form->addElement('simpleText', "{$sid}tmp", array(
                    'label' => '',
                    'escape' => false,
                    'value' => "<span style='color: green;'>$ret</span>",
                    'description' => ""
                ));

                Common::addHR($form);
            }

            return $ret;
        }
        return "";
    }


    /*
     * Gets value for domain or subscription
     */
    private static function get(&$form, $sid, $id, $isDomain = false) {

        if ($isDomain) {
            $domain = Common::getDomain($id);
            $sub = $domain->getSubscription();

            $vald = $domain->get($sid);
            $vals = $sub->get($sid);

            $wasSet = $domain->wasSet($sid);

            $edits = [Common::sidDomainJXcoreAppMaxMemLimit, Common::sidDomainJXcoreAppMaxCPULimit, Common::sidDomainJXcoreAppMaxCPUInterval];
            if (in_array($sid, $edits) && !$vald && "$vald" !== "0" )
                $wasSet = false;

            $ret = null;

            if (!$wasSet && $vals) {
                $ret = $vals;
            } else {
                $ret = $vald;
            }

            $form->addElement('hidden', "{$sid}_org", array(
                'value' => $ret
            ));
            return $ret;
        } else {
            $subscription = SubscriptionInfo::getSubscription($id);
            $val = $subscription->get($sid);
//            StatusMessage::addDebug("value for $sid = $val");
            return $val;
        }

    }

    public static function addConfigToForm(&$form, $id = "", $isDomain = false) {
        // portTCP: int
        // portTCPS: int
        // globalModulePath: string
        // globalApplicationConfigPath

        // accessible by admin:

        // maxMemory: long kB
        // maxCPU: int
        // allowCustomSocketPort: bool
        // allowSysExec: bool
        // allowLocalNativeModules: bool

        $canEdit = Common::$isAdmin;

        Common::addHR($form);

        $type = $canEdit ? 'text' : 'simpleText';
        $typeChk = $canEdit ? 'checkbox' : 'simpleText';
        $tmpID = 0;

        $maxInt = array('Int', array("LessThan", true, array('max' => 2147483647)));

        $val = self::get($form, Common::sidDomainJXcoreAppMaxMemLimit, $id, $isDomain);
        $form->addElement($type, $canEdit ? Common::sidDomainJXcoreAppMaxMemLimit : ("field" . ($tmpID++)) , array(
            'label' => 'Maximum memory limit',
            'value' => $canEdit ? $val : ($val ? "$val kB" : "disabled"),
            'required' => false,
            'validators' => array('Int', $maxInt),
            'description' => 'Maximum size of memory (kB), which can be allocated by the application. Value 0 disables the limit.',
            'escape' => false
        ));
        self::check($form, Common::sidDomainJXcoreAppMaxMemLimit, $id, $isDomain);

        $val = self::get($form, Common::sidDomainJXcoreAppMaxCPULimit, $id, $isDomain);
        $form->addElement($type, $canEdit ? Common::sidDomainJXcoreAppMaxCPULimit : ("field" . ($tmpID++)), array(
            'label' => 'Max CPU',
            'value' => $canEdit ? $val : ($val ? "$val %" : "disabled"),
            'required' => false,
            'validators' => array(
                'Int', $maxInt
                //array("GreaterThan", true, array('min' => 0))),
                //array("Between", true, array('min' => 1, 'max' => 100))
            ),
            'description' => 'Maximum CPU usage (percentage) allowed for the application. Value 0 disables the limit.',
            'escape' => false
        ));
        self::check($form, Common::sidDomainJXcoreAppMaxCPULimit, $id, $isDomain);

        $val = self::get($form, Common::sidDomainJXcoreAppMaxCPUInterval, $id, $isDomain);
        $form->addElement($type, $canEdit ? Common::sidDomainJXcoreAppMaxCPUInterval : ("field" . ($tmpID++)), array(
            'label' => 'CPU check interval',
            'value' => $canEdit ? $val : ($val ? "$val seconds" : "default"),
            'required' => false,
            'validators' => array(
                'Int', //, array("Between", true, array('min' => 1, 'max' => 100))
                array("GreaterThan", true, array('min' => 0)),
                $maxInt
            ),
            'description' => 'Interval (seconds) of Max CPU usage check. Default value is 2.',
            'escape' => false
        ));
        self::check($form, Common::sidDomainJXcoreAppMaxCPUInterval, $id, $isDomain);

        $fake = null;
        $val = self::get($form, Common::sidDomainJXcoreAppAllowCustomSocketPort, $id, $isDomain);
        $def = self::check($fake, Common::sidDomainJXcoreAppAllowCustomSocketPort, $id, $isDomain);
        $form->addElement($typeChk, $canEdit ? Common::sidDomainJXcoreAppAllowCustomSocketPort : ("field" . ($tmpID++)), array(
            'label' => 'Allow custom socket port' ,
            'description' => "$def",
            'value' => $canEdit ? $val : ($val === "1" ? "Allow" : "Disallow"),
            "escape" => false
        ));

        $val = self::get($form, Common::sidDomainJXcoreAppAllowSysExec, $id, $isDomain);
        $form->addElement($typeChk, $canEdit ? Common::sidDomainJXcoreAppAllowSysExec : ("field" . ($tmpID++)), array(
            'label' => 'Allow to spawn/exec child processes',
            'description' => self::check($fake, Common::sidDomainJXcoreAppAllowSysExec, $id, $isDomain),
            'value' => $canEdit ? $val : ($val === "1" ? "Allow" : "Disallow")
        ));

        $val = self::get($form, Common::sidDomainJXcoreAppAllowLocalNativeModules, $id, $isDomain);
        $form->addElement($typeChk, $canEdit ? Common::sidDomainJXcoreAppAllowLocalNativeModules : ("field" . ($tmpID++)), array(
            'label' => 'Allow to call local native modules',
            'description' => self::check($fake, Common::sidDomainJXcoreAppAllowLocalNativeModules, $id, $isDomain),
            'value' => $canEdit ? $val : ($val === "1" ? "Allow" : "Disallow")
        ));

        if ($isDomain) {
            Common::addHR($form);

            $domain = Common::getDomain($id);

            $val = $domain->getAppLogWebAccess();
            $form->addElement($typeChk, $canEdit ? Common::sidDomainAppLogWebAccess : ("field" . ($tmpID++)), array(
                'label' => 'Application\'s log web access',
                'description' => "Will be available on http://" . $domain->name . "/" . basename($domain->appLogDir) . "/index.txt",
                'value' => $canEdit ? $val : ($val === "1" ? "Enabled" : "Disabled")
            ));
        }

    }

    public static function saveDomainValues($form, $domain) {

        $subscription = $domain->getSubscription();

        $params = array_keys(Common::$subscriptionParams);

        foreach ($params as $param) {
            $val_new = $form->getValue($param);
            $val_org = $form->getValue("{$param}_org");
            $val_subs = $subscription->get($param);
            $equalToSub = $val_new === $val_subs;
            $differentThanBefore = $val_new !== $val_org;

//            $str = "$param val_org: $val_org, val_new: $val_new, val_sub = $val_subs, equalToSub: $equalToSub, differentThanBefore: $differentThanBefore";
//            StatusMessage::addDebug($str);

            if ($equalToSub) {
                $domain->set($param, null);
            } else {
                if ($differentThanBefore) {
                    $domain->set($param, $val_new);
                    $domain->configChanged = true;
                }
            }
        }
    }

}

class SubscriptionInfo {

    public $id = null;
    public $sid = null;

    public $mainDomain = null;
    public $mainDomainId = null;

    public $jxdir = null;
    public $jxpath = null;

    private static $subscriptions = [];
    private static $fetched = false;

    public $configChanged = false;

    private static function getSubscriptions() {
        if (self::$fetched) return;

        $dbAdapter = pm_Bootstrap::getDbAdapter();
        $sql = "SELECT * from `Subscriptions` where object_type = 'domain'";
        $statement = $dbAdapter->query($sql);

        while ($row = $statement->fetch()) {
            $sub = new SubscriptionInfo();
            $sub->id = $row['id'];
            $sub->sid = "subscription" . $sub->id;
            $sub->mainDomainId = $row['object_id'];
            $sub->mainDomain = Common::getDomain($sub->mainDomainId);

            $sub->jxdir = Common::$dirSubscriptionConfigs . $sub->mainDomain->name . "/";
            $sub->jxpath = $sub->jxdir . basename(Common::$jxpath);

            self::$subscriptions[intval($sub->id)] = $sub;
        }
        self::$fetched = true;
    }


    /**
     * marks itself as changed and all belonging domains for restart
     */
    private function invalidate()
    {
        if (!$this->configChanged) {
            $domains = $this->getDomains();
            foreach ($domains as $d) {
                $d->configChanged = true;
            }
            $this->configChanged = true;
        }
    }

    /**
     * Saves jx.config for subscription. Does not save all of the params (common for domains)
     * since each domain will save them for itself.
     */
    private function updateJXConfig() {

        // globalModulePath: string
        // globalApplicationConfigPath

        // accessible by admin:

        // maxMemory: long kB
        // maxCPU: int
        // allowCustomSocketPort: bool
        // allowSysExec: bool
        // allowLocalNativeModules: bool

        if (!Common::mkdir($this->jxdir)) {
            StatusMessage::addError("Could not create directory for subscription jx: " . $this->jxdir);
        }

        if (!file_exists($this->jxpath)) {
            copy(Common::$jxpath, $this->jxpath);
            // rx for all
            chmod($this->jxpath, 0555);
        }

        if (file_exists($this->jxpath)) {

            $cfg = '{
                       "monitor" :
                       {
                           "log_path" : "' . $this->jxdir . 'jx_monitor_[WEEKOFYEAR]_[YEAR].log",
                           "users": [ "psaadm" ]
                       },
                       "globalModulePath" : "' . Common::$dirNativeModules . '",
                       "globalApplicationConfigPath" : "' . Common::$dirAppsConfigs . '",
                       "npmjxPath" : "' . dirname(Common::$jxpath) . '"';

            $cfg .= '}';

            $fname = $this->jxdir . "jx.config";
            $old = file_get_contents($fname);

            if ($old !== $cfg) {
                $this->invalidate();
                $ret = file_put_contents($fname, $cfg);
                if (!$ret)
                    StatusMessage::addError("Could not save jx.config for subscription " . $this->mainDomain->name);
            }
        }
    }

    /**
     * @return array - Returns is of subscriptions
     */
    public static function getIds() {
        self::getSubscriptions();
        return array_keys(self::$subscriptions);
    }

    /**
     * Returns subscription class instance or null
     * @param $id
     * @return null
     */
    public static function getSubscription($id) {
        self::getSubscriptions();
        $id = intval($id);

        if (isset(self::$subscriptions[$id]))
            return self::$subscriptions[$id];
        else
            return null;
    }


    /**
     * Updates jx.config for the subscription and all configs for all domains belonging to it
     */
    public function updateConfigs() {
        $this->updateJXConfig();

        $domains = $this->getDomains();
        foreach ($domains as $d) {
            $d->updateConfigs();
        }
    }

    public function get($sid) {
        $wasSet = $this->wasSet($sid);

        $defaults = [];
        $defaults[Common::sidDomainJXcoreAppAllowSysExec] = 1;
        $defaults[Common::sidDomainJXcoreAppAllowLocalNativeModules] = 1;

        if (!$wasSet && isset($defaults[$sid]))
            return $defaults[$sid];
        else
            return pm_Settings::get($sid . $this->sid);
    }

    /**
     * Sets param's value
     * @param $sid
     * @param $value
     */
    public function set($sid, $value) {
        $old = $this->get($sid);
        pm_Settings::set($sid . $this->sid, $value);
        pm_Settings::set($sid . $this->sid . "isset", "true");

        $changed = $old != $value;
        if ($changed) $this->invalidate();
    }

    public function wasSet($sid) {
        return pm_Settings::get($sid . $this->sid . "isset") == "true";
    }

    /**
     * Gets domains for current subscription
     * @return array
     */
    public function getDomains() {
        $ids = Common::getDomainsIDs();

        $ret = [];
        foreach($ids as $id) {
            $domain = Common::getDomain($id);
            if ($id == $this->mainDomainId || $this->mainDomainId == $domain->webspaceId) {
                $ret[$domain->id] = $domain;
            }
        }
        return $ret;
    }

}


class StatusMessage {
    public static $status = null;

    public static function addError($err) {
        self::$status->addMessage('error', $err);
    }

    public static function addDebug($txt) {
        $txt = str_replace("\n", "<br>", $txt);
        self::$status->addMessage('warning', $txt);
    }

    public static function dataSavedOrNot($saved) {
        self::$status->addMessage('info', $saved ? 'Data was successfully saved.' : 'Nothing to save.');
    }

    public static function infoOrError($isError, $infoMsg, $errMsg) {
        self::$status->addMessage($isError ? 'error' : 'info', $isError ? $errMsg : $infoMsg);
    }
}