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
 */


class Common
{
    public static $urlMonitor = "";
    public static $urlMonitorLog = "";
    public static $urlService = "http://0.0.0.0:8000/";

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

    function Common($controller, $status = null)
    {
        self::$controller = $controller;
        self::$status = $status;
        StatusMessage::$status = $status;

        self::clearPorts();
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
//        self::$urlMonitor = "http://localhost:17777/json";
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

//        $client = new PanelClient();
//        self::$controller->view->userStatusBar = $client->statusBar;

        $client = pm_Session::getClient();
        self::$isAdmin = $client->isAdmin();


        $v = pm_Settings::get(self::sidJXcoreMinimumPortNumber);
        self::$minApplicationPort = $v ? $v : self::minApplicationPort_default;

        $v = pm_Settings::get(self::sidJXcoreMaximumPortNumber);
        self::$maxApplicationPort = $v ? $v : self::maxApplicationPort_default;

        self::$dirNativeModules = $varDir . "native_modules/";
        self::$dirAppsConfigs = $varDir . "app_configs/";
        self::$dirSubscriptionConfigs = $varDir . "subs_configs/";

        self::$pathSpawner = pm_Context::getVarDir() . "spawner.js";
        self::$pathService = pm_Context::getVarDir() . "jxcore_service.js";

//        $takenPorts = self::getTakenAppPorts();
//        self::$status->addMessage("info", "min = " . self::$minApplicationPort. ", max = ".self::$maxApplicationPort. ". Taken ports: " . join(",", $takenPorts));
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


    public static function saveConfig($path = null, $subscriptionId = null) {

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

        Common::mkdir(self::$dirSubscriptionConfigs);

        $subs = SubscriptionInfo::getIds();
        foreach ($subs as $id) {
            $sub = SubscriptionInfo::getSubscription($id);
            $sub->saveConfig();
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
        self::saveConfig($path);

        pm_Settings::set(self::sidJXversion, $version);
        pm_Settings::set(self::sidJXpath, $path);

        self::$jxv = null;
        self::$jxpath = null;

        Common::mkdir(self::$dirNativeModules);
        Common::mkdir(self::$dirAppsConfigs);

        self::refreshValues();
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

    //todo: unfinished
    public static function reassignPorts() {
        if (!self::$minApplicationPort || !self::$maxApplicationPort) {
            return;
        }

        $port = self::$minApplicationPort;

        $rows = self::$domains;
        foreach ($rows as $id => $domain) {
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
//        var_dump($takenPorts);
        foreach ($takenPorts as $port) {
//            if (!$port) continue;
//            var_dump($port);
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
    }


    public static function updateBatchAndCron($domainId = null)
    {
        // first we write a batch, which will be executed by cron
        $commands = [];

        // saving to the batch file
//        $monitorEnabled = pm_Settings::get(Common::sidMonitorEnabled) === "1";
        $monitorEnabled = true;

        if ($monitorEnabled) {
            $commands = [
                // enabling proxy
//                'json=$(a2enmod proxy_http)',
//                'if [[ $json == *"new config"* ]]; then',
//                'echo "need restart $json"',
//                'service apache2 restart',
//                'else',
//                'echo "no restart $json"',
//                'fi',
//                'which a2enmod > /tmp/xxx.txt',
//                'a2enmod proxy_http >> /tmp/xxx.txt',


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
                $sub = $domain->getSubscription();

                if (!$sub) {
                    self::$status->addMessage("error", "Cannot find subscription for the domain.");
                    continue;
                }

                $enabled = $domain->JXcoreSupportEnabled();
                $path = $domain->getAppPath(true);

                $out = null;
                $spawner = $domain->getSpawnerPath($out);
                if ($spawner === false) {
                    self::$status->addMessage("error", $out);
                    continue;
                }

                $opt = $domain->getSpawnerParams(array("user" => $domain->sysUser, "log" => $domain->appLogPath, "file" => $path,
                                "domain" => $domain->name, "tcp" => $domain->getAppPort(), "tcps" => $domain->getAppPort(true), "logWebAccess" => $domain->getAppLogWebAccess() ));



                $cmd = $sub->jxpath . " {$spawner} -opt {$opt}";

                if ($enabled) {
                    $commands[] = '';
                    $commands[] = "if [ -e {$spawner} ]; then";
                    $commands[] = $cmd;
                    $commands[] = "fi";
                }

                // stopping or launching application, if provided with the argument $domainId
                if ($domainId && intval($domainId) === $id) {

                    $sleep = 3;
                    $json = null;
                    $monitorRunning = Common::getURL(Common::$urlMonitor, $json);
                    $appRunning = strpos($json, $path) !== false;

                    $msg = "";
                    if (!$enabled && $appRunning) {
                        // stopping application

                        $nginexconf = "/etc/nginx/jxcore.conf.d/" . $domain->name . ".conf";
                        if (@file_exists($nginexconf)) {
                            @unlink($nginexconf);
                            Common::callService("nginx", "reload", null, "Cannot reload nginx config.");
                        }

                        $cmd = Common::$jxpath . " monitor kill {$spawner} 2>&1";
                        @exec($cmd, $out, $ret);
                        // cannot rely on exitcode, so checking the monitor

                        Common::getURL(Common::$urlMonitor, $json);
                        $appRunning = strpos($json, $path) !== false;
                        if ($appRunning) {
                            self::$status->addMessage("error", "Cannot stop the application. ". join("\n", $out) . ". Exit code: $ret");
                        }
                    } else if ($enabled && !$appRunning) {
                        // starting application
                        if (!$monitorRunning) {
                            // no point to run an application now, if monitor is not running
                            $cmd = "";
                            $msg = "Cannot start the application: ";
                        } else {
                            // leave $cmd intact - it will start the application
                            // but enable proxy if its not enabled
                            self::enableServices();

                            @exec($cmd, $out, $ret);
                            // cannot rely on exitcode, so checking the monitor

                            Common::getURL(Common::$urlMonitor, $json);
                            $appRunning = strpos($json, $path) !== false;
                            self::$status->addMessage($appRunning ? "info" : "error", $appRunning ? "The application successfully started." : "The application could not be started.");
                            $cmd = null;
                            sleep($sleep);
                        }
                    } else {
                        $cmd = null;
                    }
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
                self::$status->addMessage('info', $str);
            }
        } else {

            $monitorRunning = Common::getURL(Common::$urlMonitor, $json);
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

    public static function updatehtaccess($domainId)
    {
        $mgr = new pm_FileManager($domainId);
        $domain = self::$domains[$domainId];

        $basename = ".htaccess";
        $file = $domain->rootFolder . $basename;

        $relFile = str_replace($mgr->getFilePath("."), "", $file);

        if ($domain->JXcoreSupportEnabled()) {
            $htaccess = [];
            $htaccess[] = 'RewriteEngine On';

            if ($domain->getAppLogWebAccess()) {
                $htaccess[] = 'RewriteCond %{REQUEST_URI} !/' . $domain->appLogDir;// DomainInfo::appLogBasename;
            }

//            $tcp = $domain->getAppPort();
//            $tcps = $domain->getAppPort(true);
//            $htaccess[] = 'RewriteCond %{SERVER_PORT} 80';
//            $htaccess[] = 'RewriteRule ^(.*)$ http://0.0.0.0:' . $tcp . '/$1 [P]';
//            $htaccess[] = 'RewriteRule ^(.*)$ https://0.0.0.0:' . $tcps . '/$1 [P]';
//            $htaccess[] = 'RewriteRule ^(.*)$ ws://0.0.0.0:' . $tcp . '/$1 [P]';
//            $htaccess[] = 'RewriteRule ^(.*)$ wss://0.0.0.0:' . $tcps . '/$1 [P]';

            $htaccess[] = 'RewriteCond %{SERVER_PORT} 80';
            $htaccess[] = 'RewriteRule ^(.*)$ http://0.0.0.0:' . $domain->getAppPort() . '/$1 [P]';
        } else {
            $htaccess = [];
        }
//var_dump($domain->JXcoreSupportEnabled(), $htaccess);
        $txt = "";

        if ($mgr->fileExists($relFile)) $txt = $mgr->fileGetContents($relFile);

        $txt = self::saveBlockToText($txt, "JXcore-domainID-" . $domainId, join("\n", $htaccess), null);
        $txt = trim($txt);

        $mgr->filePutContents($relFile, $txt);

        $txt2 = file_get_contents($file);
        if ($txt2 !== $txt)
            return "Cannot save $file";

        return true;
    }

    public static function testUser()
    {
//        $mgr = new pm_FileManager(7);
//        $mgr->filePutContents("krowa.txt", "krowa");
//
//        $ret = file_put_contents("krowa2.txt", "krowa2");
//        var_dump("written $ret");
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
//        $cmd2 = "/opt/psa/admin/bin/apache_control_adapter --restart";
        $cmd2 = "/opt/psa/admin/bin/httpd_modules_ctl -e proxy_http";

//        $before = shell_exec($cmd1);

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


    private static function enableNginx() {
        self::callService("nginx", "start", null, "Nginx could not be enabled.");
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

        $json = null;
        $monitorWasRunning = self::getURL(Common::$urlMonitor, $json);

        if ($req == 'restart') {
            // refreshes the config every time monitor is called for restart
            self::saveConfig();

            if ($monitorWasRunning) {
                self::monitorStartStop("stop");
                $monitorWasRunning = self::getURL(Common::$urlMonitor, $json);
            } else {
                // don't restart if was not running
                return;
            }
        }

        if (in_array($req, ['start', 'restart']) && !$monitorWasRunning) {
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

            if ($req === 'stop' && $monitorWasRunning) {
                // weird exit code on jx monitor stop (8)
                $monitorIsRunning = Common::getURL(Common::$urlMonitor, $json);
                if (!$monitorIsRunning) $ret = 0;
            }

            if ($ret && $ret != 255) {
                self::$status->addMessage('error', "Could not execute command: $cmd. Error code = $ret. " . join(", ", $out));
            } //else {
//                $this->_status->addMessage('info', "Executed command: $cmd. Error code = $ret. " . join(", ", $out));
//            }
        }

        $json = null;
        $monitorRunning = Common::getURL(Common::$urlMonitor, $json);

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

    public $isInitialized = false;

    const appLogBasename = "index.txt";
    const appPath_default = "index.js";

    private $sidInitialized = "_domain_initialized";

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

    private function DomainInfo($id)
    {
        $this->domain = new pm_Domain($id);
        $this->fileManager = new pm_FileManager($id);
        $this->id = $id;
        $this->name = $this->domain->getName();

        $this->isInitialized = $this->get($this->sidInitialized) == "true";
    }

    public function get($sid) {
        return pm_Settings::get($sid . $this->id);
    }

    public function wasSet($sid) {
        return pm_Settings::get($sid . $this->id . "isset") == "true";
    }


    public function set($sid, $value) {
        pm_Settings::set($sid . $this->id, $value);
        pm_Settings::set($sid . $this->id . "isset", "true");
    }

    public function isAppRunning($json = null)
    {
        if (!$json) {
            Common::getURL(Common::$urlMonitor, $json);
        }

        $path = $this->getSpawnerPath($out);

        return $path !== false && strpos($json, $path) !== false;
    }

    public function getAppStatus()
    {
        $p = $this->getAppPathOrDefault(true);
        if (file_exists($p)) {
            $json = "";
            $monitorRunning = Common::getURL(Common::$urlMonitor, $json);
//            var_dump($json);
            $appRunning = $this->isAppRunning($json);
            if ($monitorRunning) {
                $port = $this->getAppPort();
                if ($port < Common::$minApplicationPort || $port > Common::$maxApplicationPort) {
                    $port .= "<br><span style='color: orangered'>TCP port out of range.</span>";
                }

                $portSSL = $this->getAppPort(true);
                if ($portSSL < Common::$minApplicationPort || $portSSL > Common::$maxApplicationPort) {
                    $portSSL .= "<br><span style='color: orangered'>TCPS port out of range.</span>";
                }

                return Common::getIcon($appRunning, "Running on TCP: $port, TCPS: $portSSL", "Not running");
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
        return pm_Settings::get(Common::sidDomainJXcoreEnabled . $this->id);
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
        $val = pm_Settings::get(Common::sidDomainJXcoreAppPath . $this->id);
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
    public function getSpawnerPath(&$out)
    {
        $spawnerOrg = pm_Context::getVarDir() . "spawner.js";
        chmod($spawnerOrg, 0644);
        $spawner = pm_Context::getVarDir() . "spawner_{$this->id}.js";
        if (copy($spawnerOrg, $spawner) === false) {
            $out = "Cannot copy spawner.js for application of domain: " . $this->name;
            return false;
        }
        if (chmod($spawner, 0644) === false) {
            $out = "Cannot set permissions for application's spawner of domain: " . $this->name;
            return false;
        }

        return $spawner;
    }

    /**
     * Returns parameters for spawner command line (log, user, appFile)
     * @param $additionalParams
     * @return string
     */
    public function getSpawnerParams($additionalParams)
    {
        $arr = [];

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
            $val = pm_Settings::get($sid . $this->id);
            if (trim($val) != "") {
                $arr[] = "\"{$key}\" : {$val}";
            }
        }

        // as strings
        foreach ($additionalParams as $key => $val) {
            $arr[] = "\"{$key}\" : \"{$val}\"";
        }

        $json = "'{ " . join(", ", $arr) . "}'";
        return $json;
    }

    public function clearLogFile()
    {
        if (file_exists($this->appLogPath)) {
            $oldSize = filesize($this->appLogPath);


            $clearlog = $this->appLogDir . "clearlog.txt";
            $rel = str_replace($this->fileManager->getFilePath("."), "", $clearlog);
            $this->fileManager->filePutContents($rel, "clear");


//            file_put_contents($clearlog, "");
            sleep(1);
//            $fh = fopen($this->appLogPath, 'w' );
//            fclose($fh);

            $newSize = filesize($this->appLogPath);

            return $newSize < $oldSize;
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

        return null;
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
        } else {
            // why this doesn't work?
//            $clientId = intval($clientId);
            // $client = new pm_Client($clientId);
        }

        $this->whoami = shell_exec("whoami");

        $dbAdapter = pm_Bootstrap::getDbAdapter();

//        $sql = "SELECT
//            sys.login as sysLogin, cli.login as cliLogin, cli.type as cliType
//            FROM clients cli
//            join smb_users smb on smb.login = cli.login
//            join sys_users sys on sys.id = smb.id
//            where cli.id = $clientId";


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

//        if (pm_Session::isImpersonated()) {
//            $client = pm_Session::getImpersonatedClientId();
//            $clientId = $client->getId();
//            $this->statusBar .= "Imp client. Id {$clientId}, login: {$client->getProperty('login')} <hr>";
//        }
    }
}


class JXconfig {

    public $portTCP = 0; // int
    public $portTCPS = 0; // int

    public $globalModulePath = null; // string
    public $globalApplicationConfigPath = null; //string

    // accessible by admin:

//    public $maxMemory: long kB
    // maxCPU: int
    // allowCustomSocketPort: bool
    // allowSysExec: bool
    // allowLocalNativeModules: bool

    /**
     * Returns global value
     */
    public static function getGlobal() {
        $fname = dirname(Common::$jxpath) . "/jx.config";
        if (file_exists($fname)) {
            $json = file_get_contents($fname);
        }
    }


    private static function check(&$form, $sid, $domainId) {

        return "";

        if (!$domainId) return;

        $domain = Common::getDomain($domainId);
        $vald = $domain->get($sid);
        $valg = pm_Settings::get($sid);

        if (!$vald && $valg) {


            if (!$form && $valg == 1) $valg = "'true'";
            $ret = "The value $valg from global config will be used.";

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
//            return "<span style='color: green;'>$ret</span>";
        }
        return "";
    }


    /*
     * Gets value for domain or subscription
     */
    private static function get($sid, $id = "", $isDomain = false) {

        if (!$id) $id = "";

        if ($isDomain) {
            $domain = Common::getDomain($id);
            $vald = $domain->get($sid);
            $valg = pm_Settings::get($sid);

            $wasSet = $domain->wasSet($sid);

            $edits = [Common::sidDomainJXcoreAppMaxMemLimit, Common::sidDomainJXcoreAppMaxCPULimit, Common::sidDomainJXcoreAppMaxCPUInterval];
            if (in_array($sid, $edits) && !$vald && "$vald" !== "0" )
                $wasSet = false;

            if (!$wasSet && $valg) {
                return $valg;
            } else {
                return $vald;
            }
        } else {
            return pm_Settings::get($sid . $id);
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
//        if (!Common::$isAdmin) {
//            return;
//        }

        Common::addHR($form);

        $type = $canEdit ? 'text' : 'simpleText';
        $typeChk = $canEdit ? 'checkbox' : 'simpleText';
        $tmpID = 0;

        $val = self::get(Common::sidDomainJXcoreAppMaxMemLimit, $id);
        $form->addElement($type, $canEdit ? Common::sidDomainJXcoreAppMaxMemLimit : ("field" . ($tmpID++)) , array(
            'label' => 'Maximum memory limit',
            'value' => $canEdit ? $val : ($val ? "$val kB" : "disabled"),
            'required' => false,
            'validators' => array(
                'Int',
            ),
            'description' => 'Maximum size of memory (kB), which can be allocated by the application. Value 0 disables the limit.',
            'escape' => false
        ));
        self::check($form, Common::sidDomainJXcoreAppMaxMemLimit, $id);

        $val = self::get(Common::sidDomainJXcoreAppMaxCPULimit, $id);
        $form->addElement($type, $canEdit ? Common::sidDomainJXcoreAppMaxCPULimit : ("field" . ($tmpID++)), array(
            'label' => 'Max CPU',
            'value' => $canEdit ? $val : ($val ? "$val %" : "disabled"),
            'required' => false,
            'validators' => array(
                'Int',
                //array("GreaterThan", true, array('min' => 0))),
                //array("Between", true, array('min' => 1, 'max' => 100))
            ),
            'description' => 'Maximum CPU usage (percentage) allowed for the application. Value 0 disables the limit.',
            'escape' => false
        ));
        self::check($form, Common::sidDomainJXcoreAppMaxCPULimit, $id);

        $val = self::get(Common::sidDomainJXcoreAppMaxCPUInterval, $id);
        $form->addElement($type, $canEdit ? Common::sidDomainJXcoreAppMaxCPUInterval : ("field" . ($tmpID++)), array(
            'label' => 'CPU check interval',
            'value' => $canEdit ? $val : ($val ? "$val seconds" : "default"),
            'required' => false,
            'validators' => array(
                'Int', //, array("Between", true, array('min' => 1, 'max' => 100))
                array("GreaterThan", true, array('min' => 0))
            ),
            'description' => 'Interval (seconds) of Max CPU usage check. Default value is 2.',
            'escape' => false
        ));
        self::check($form, Common::sidDomainJXcoreAppMaxCPUInterval, $id);

        $fake = null;
        $val = self::get(Common::sidDomainJXcoreAppAllowCustomSocketPort, $id);
        $def = self::check($fake, Common::sidDomainJXcoreAppAllowCustomSocketPort, $id);
        $form->addElement($typeChk, $canEdit ? Common::sidDomainJXcoreAppAllowCustomSocketPort : ("field" . ($tmpID++)), array(
            'label' => 'Allow custom socket port' ,
            'description' => "$def",
            'value' => $canEdit ? $val : ($val === "1" ? "Allow" : "Disallow"),
            "escape" => false
        ));


        $val = self::get(Common::sidDomainJXcoreAppAllowSysExec, $id);
        $form->addElement($typeChk, $canEdit ? Common::sidDomainJXcoreAppAllowSysExec : ("field" . ($tmpID++)), array(
            'label' => 'Allow to spawn/exec child processes',
            'description' => self::check($fake, Common::sidDomainJXcoreAppAllowSysExec, $id),
            'value' => $canEdit ? $val : ($val === "1" ? "Allow" : "Disallow")
        ));

        $val = self::get(Common::sidDomainJXcoreAppAllowLocalNativeModules, $id);
        $form->addElement($typeChk, $canEdit ? Common::sidDomainJXcoreAppAllowLocalNativeModules : ("field" . ($tmpID++)), array(
            'label' => 'Allow to call local native modules',
            'description' => self::check($fake, Common::sidDomainJXcoreAppAllowLocalNativeModules, $id),
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

}


class LogForm {

    public static function getForm($controller, $helper, $status, $logPath, $domainId = "")
    {
        if (!$domainId) $domainId = "";
        if ($logPath == "monitor")

        $form = new pm_Form_Simple();
        $sidClearLog = "clear_log";
        $sidLastLinesCount = "last_lines_count";

        $form->addElement('hidden', $sidClearLog, array(
            'value' => "nothing"
        ));

        $form->addElement('simpleText', "size", array(
            'label' => 'Log file size',
            'value' => filesize($logPath) . " bytes" . Common::getSimpleButton($sidClearLog, "Clear log", "clear", Common::iconUrlDelete, null),
            'escape' => false
        ));

        $val = pm_Settings::get($sidLastLinesCount . $domainId);
        if (!$val && $val !=0) $val = 200;
        $form->addElement('text', $sidLastLinesCount, array(
            'label' => 'Show last # lines',
            'value' => $val,
            'required' => false,
            'validators' => array(
                'Int',
            ),
            'description' => 'Displays only last # lines of the log file. Enter 0 to display the whole log.',
            'escape' => false
        ));

        $form->addControlButtons(array(
            'cancelLink' => null,
            'hideLegend' => true
        ));

        if ($controller->getRequest()->isPost() && $form->isValid($controller->getRequest()->getPost())) {
            $actionClearValue = $controller->getRequest()->getParam($sidClearLog);
            $actionClearPressed = $actionClearValue === "clear";

            $val = $form->getValue($sidLastLinesCount);

            if ($actionClearPressed) {

                if ($domainId && ctype_digit($domainId)) {
                    $domain = Common::getDomain($domainId);
                    $ret = $domain->clearLogFile();
                    if ($ret === false) {
                        $status->addMessage('error', 'Could not clear the log file.');
                    } else {
                        $status->addMessage('info', 'Log cleared.');
                    }

                } else {
                    Common::callService("delete", "monitorlogs", "Log cleared.", "Problem: ");
                }

            } else {
                pm_Settings::set($sidLastLinesCount . $domainId, $val);
            }
            $helper->json(array('redirect' => Common::$urlDomainAppLog));
        }

        //$this->readLog($val);
        $controller->view->log = self::readLog($logPath, $val);

        $controller->view->buttonsDisablingScript = Common::getButtonsDisablingScript();
        $controller->view->form = $form;
    }


    private static function readLog($logPath, $tail)
    {
        // $this->_status->addMessage("info", "last lines " . $tail);
        if (file_exists($logPath)) {
            if (!ctype_digit($tail) || $tail == 0) {
                $contents = file_get_contents($logPath);
                $contents = str_replace("\n", "<br>", $contents);
            } else {
                $file = file($logPath);
                $contents = implode("<br>", array_slice($file, -$tail));
            }
        } else {
            $contents = "No log file. " . $logPath;
        }

        if (trim($contents) === "") {
            $contents = "The log file is empty.";
        }
        return $contents;
    }

}

class SubscriptionInfo {

    public $id = null;
    public $sid = null;

    public $mainDomain = null;
    //public $mainDomainId = null;

    public $jxdir = null;
    public $jxpath = null;

    private static $subscriptions = [];
    private static $fetched = false;

    private static function getSubscriptions() {
        if (self::$fetched) return;

        $dbAdapter = pm_Bootstrap::getDbAdapter();
        $sql = "SELECT * from `Subscriptions` where object_type = 'domain'";
        $statement = $dbAdapter->query($sql);

        while ($row = $statement->fetch()) {
            $sub = new SubscriptionInfo();
            $sub->id = $row['id'];
            $sub->sid = "subscription" . $sub->id;
            $sub->mainDomain = Common::getDomain($row['object_id']);

            $sub->jxdir = Common::$dirSubscriptionConfigs . $sub->mainDomain->name . "/";
            $sub->jxpath = $sub->jxdir . basename(Common::$jxpath);

            self::$subscriptions[intval($sub->id)] = $sub;
        }
        self::$fetched = true;
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

    public function saveConfig() {

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

            $params = [
                Common::sidDomainJXcoreAppMaxCPULimit,
                Common::sidDomainJXcoreAppMaxCPUInterval,
                Common::sidDomainJXcoreAppMaxMemLimit,
                Common::sidDomainJXcoreAppAllowCustomSocketPort,
                Common::sidDomainJXcoreAppAllowSysExec,
                Common::sidDomainJXcoreAppAllowLocalNativeModules
            ];

            foreach ($params as $param) {
                $val = $this->get($param);
                if ($val) {
                    $sid = str_replace("jxparam_", "", $param);
                    $cfg .= ",\n" . '"'. $sid.'" : ' . $val;
                }
            }

            $cfg .= '}';

            $ret = file_put_contents($this->jxdir . "jx.config", $cfg);
            if (!$ret)
                StatusMessage::addError("Could not save jx.config for subscription " . $this->mainDomain-name);
        }
    }

    public function get($sid) {
        $wasSet = $this->wasSet($sid);



        return pm_Settings::get($sid . $this->sid);
    }

    /**
     * Sets param's value
     * @param $sid
     * @param $value
     * @return bool - true if value has been changed, false otherwise.
     */
    public function set($sid, $value) {
        $old = $this->get($sid);
        pm_Settings::get($sid . $this->sid);
        pm_Settings::get($sid . $this->sid . "isset");

        return $old != $value;
    }

    public function wasSet($sid) {
        return pm_Settings::get($sid . $this->sid . "isset") == "true";
    }

}


class StatusMessage {
    public static $status = null;

    public static function addError($err) {
        self::$status->addMessage("error", $err);
    }

    public static function addDebug($txt) {
        self::$status->addMessage("warning", $txt);
    }
}