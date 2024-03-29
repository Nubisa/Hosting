<?php

/* Copyright Nubisa, Inc. 2014. All Rights Reserved */

/*
 *
 * useful commands:
 *      - fix plesk config:
 *          /usr/local/psa/bin/repair -r
 *      - reload nginx config:
 *          /etc/init.d/nginx reload
 *
*/


class Modules_JxcoreSupport_Common
{
    public static $urlMonitor = "";
    public static $urlMonitorLog = "";
    public static $urlService = "https://localhost:18999/";

    public static $urlJXcoreConfig = "";
    public static $urlJXcoreDomains = "";
    public static $urlJXcoreModules = "";
    public static $urlJXcoreSubscriptions = "";
    public static $urlJXcoreMonitorLog = "";
    public static $urlDomainConfig = "";
    public static $urlDomainAppLog = "";
    public static $urlDomainRestartManager = "";
    public static $urlDomainModules = "";

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
    const sidDomainAppUseSSL = "jx_domain_app_use_SSL";
    const sidDomainAppSSLCert = "jx_domain_app_ssl_cert";
    const sidDomainAppSSLKey = "jx_domain_app_ssl_key";

    const sidDomainRestartMgrEnabled = "jx_domain_restart_mgr_enabled";
    const sidDomainRestartMgrInterval = "jx_domain_restart_mgr_interval";
    const sidDomainRestartMgrDepth = "jx_domain_restart_mgr_depth";
    const sidDomainRestartMgrWatchedPaths = "jx_domain_restart_mgr_watchedPaths";
    const sidDomainRestartMgrIgnoredPaths = "jx_domain_restart_mgr_ignoredPaths";

    const sidDomainAppLogWebAccess = "jx_domain_app_log_web_access";
    const sidDomainAppNginxDirectives = "jx_domain_app_nginx_directives";
    const sidDomainJXcoreEnabled = "jx_domain_jxcore_enabled";
    const sidSubscriptionJXcoreEnabled = "jx_subscription_jxcore_enabled";
    const sidDomainJXcoreAppPort = "jx_domain_app_port";
    const sidDomainJXcoreAppPortSSL = "jx_domain_app_port_ssl";
    const sidDomainJXcoreAppPath = "jx_domain_app_path";
    const sidDomainJXcoreAppArgs = "jx_domain_app_args";
    const sidDomainJXcoreAppArgsArrayStringified = "jx_domain_app_args_arr_str";
    const sidDomainJXcoreAppEnvVars = "jx_domain_app_env_vars";

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
    const sidJXcoreAllowNPMInstall = "jx_app_allow_npm_install";

    const sidMonitorStartScheduledByCron = "jx_monitor_start_scheduled_by_cron";
    const sidMonitorStartScheduledByCronAction = "jx_monitor_start_scheduled_by_cron_action";

    const iconON = '<img src="/theme/icons/16/plesk/on.png" style="vertical-align: middle; display: inline; margin-right: 7px;" height="16" width="16">';
    const iconOFF = '<img src="/theme/icons/16/plesk/off.png" style="vertical-align: middle; display: inline; margin-right: 7px;" height="16" width="16">';
    const iconError = '<img src="/theme/icons/16/plesk/warning.png" style="vertical-align: middle; display: inline; margin-right: 7px;" height="16" width="16">';

    const iconUrlDelete = "/theme/icons/16/plesk/delete.png";
    const iconUrlReload = "/theme/icons/16/plesk/show-all.png";
    const iconUrlDownload = "/theme/icons/16/plesk/download-files.png";

    const minApplicationPort_default = 10000;
    const maxApplicationPort_default = 20000;

    public static $minApplicationPort = self::minApplicationPort_default;
    public static $maxApplicationPort = self::maxApplicationPort_default;
    public static $allowNPMInstall = false;

    private static $hrId = 0;
    private static $controller = null;
    public static $status = null;
    public static $plesk12 = null;

    private static $buttonsDisabling = array();

    private static $domains = array();
    private static $domainsFetched = false;

    private static $monitorJSON = null;
    private static $monitorJSONFetched = false;

    public static $needToReloadNginx = false;
    private static $nginxReloaded = false;

    public static $restartFlag = false;

    function Modules_JxcoreSupport_Common($controller, $status = null)
    {
        self::$controller = $controller;
        self::$status = $status;
        StatusMessage::init($status);

        $_class = get_class($controller);
        $_clientId = PanelClient::getLogged()->id;

        if ($_class !== 'DomainController')
            pm_Settings::set("currentDomainId" . $_clientId, "");

        if ($_class !== 'SubscriptionController')
            pm_Settings::set("currentSubscriptionId" . $_clientId, "");

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
            self::$domains = array();
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
        self::$urlMonitor = "https://localhost:17777/json?silent=true";
        self::$urlMonitorLog = "https://localhost:17777/logs";
        self::$urlJXcoreConfig = $baseUrl . "index.php/index/jxcore";
        self::$urlJXcoreModules = $baseUrl . "index.php/index/listmodules";
        self::$urlJXcoreSubscriptions = $baseUrl . "index.php/index/listsubscriptions";
        self::$urlJXcoreMonitorLog = $baseUrl . "index.php/index/log";
        self::$urlDomainConfig = $baseUrl . "index.php/domain/config";
        self::$urlDomainAppLog = $baseUrl . "index.php/domain/log";
        self::$urlDomainRestartManager = $baseUrl . "index.php/domain/restartmanager";
        self::$urlDomainModules = $baseUrl . "index.php/domain/listmodules";

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

        self::$allowNPMInstall = pm_Settings::get(Modules_JxcoreSupport_Common::sidJXcoreAllowNPMInstall);
        self::$plesk12 = class_exists('pm_ProductInfo');

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

        //self::saveConfig();
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

    /**
     * @param null $restartFlag.
     *      "nowait" - restarts apps if needed, but does not wait fo them.
     *      "norestart" - does not restart applications
     */
    public static function updateAllConfigsIfNeeded($restartFlag = null) {

        self::$restartFlag = $restartFlag;

        Modules_JxcoreSupport_Common::updateBatchAndCron();

        Modules_JxcoreSupport_Common::mkdir(self::$dirSubscriptionConfigs);

        $subs = SubscriptionInfo::getIds();
        foreach ($subs as $id) {
            $sub = SubscriptionInfo::getSubscription($id);
            if ($sub) {
                $sub->updateConfigs();
            }
        }

        if (self::$needToReloadNginx) {
            self::reloadNginx();
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
                           "users": [ "psaadm" ],
                           "https" : {
                                "httpsKeyLocation" : "' . pm_Context::getVarDir()  .'server.key",
                                "httpsCertLocation" : "' . pm_Context::getVarDir()  .'server.crt"
                            }
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
            @exec("rm -rf $dir");

            // was not working recursively
//            $files = array_diff(scandir($dir), array('.', '..'));
//
//            foreach ($files as $file) {
//                @unlink("$dir/$file");
//            }
//            return @rmdir($dir);
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

        Modules_JxcoreSupport_Common::mkdir(self::$dirNativeModules);
        Modules_JxcoreSupport_Common::mkdir(self::$dirAppsConfigs);

        self::refreshValues();
        self::saveConfig($path);
    }

    public static function saveBlockToText($contents, $blockName, $blockBody)
    {
        $commands = array("#{$blockName}-Begin", $blockBody, "#{$blockName}-End");

        if (trim($blockBody) == "") {
            // removing block
            $contents = preg_replace('/(' . $commands[0] . ')(.*)(' . $commands[2] . ')/si', '', $contents);
        } else {
            if (strpos($contents, $commands[0]) === false)
                $contents .= "\n\n" . join("\n", $commands) . "\n\n";
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
        curl_setopt($ch, CURLOPT_FRESH_CONNECT, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        $data = curl_exec($ch);

        $ok = $data !== false;
        $output = $ok ? $data : curl_error($ch);

        curl_close($ch);

        return $ok;
    }


    /**
     * Returns array of ports already taken by JXcore applications assigned to domains
     * @param null $domainId - if this param provided, the result will not contain port of applications for this domain
     * @param null $clientId
     * @param null $ssl
     * @return array
     */
    public static function getTakenAppPorts($domainId = null, $clientId = null, $ssl = null)
    {
        $rows = self::$domains;

        $portsTaken = array();
        foreach ($rows as $id => $domain) {
            $me = $domainId && $id == $domainId;

            if ($clientId && $domain->row['cl_id'] != $clientId) continue;
            // array of strings

//            if (($me && $ssl !== false) || !$me) {
            $skip = $domainId && $id == $domainId && ($ssl === false || $ssl === null);
            if (!$skip) {
                $port = pm_Settings::get(Modules_JxcoreSupport_Common::sidDomainJXcoreAppPort . $domain->row['id']);
                if ($port && ctype_digit($port))
                    $portsTaken[] = $port;
            }
            $skip = $domainId && $id == $domainId &&  ($ssl === true || $ssl === null);
//            if (($me && $ssl !== true) || !$me) {
            if (!$skip) {
                $port = pm_Settings::get(Modules_JxcoreSupport_Common::sidDomainJXcoreAppPortSSL . $domain->row['id']);
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
        $ret = array();
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
            $domain->set(Modules_JxcoreSupport_Common::sidDomainJXcoreAppPort, $port++);
            $domain->set(Modules_JxcoreSupport_Common::sidDomainJXcoreAppPortSSL, $port++);
        }
    }

    public static function checkPortRange($domainId = null)
    {
        if (!self::$minApplicationPort || !self::$maxApplicationPort) {
            return;
        }

        $logged = PanelClient::getLogged();
        $clientId = $logged->isAdmin ? null : $logged->id;
        $takenPorts = Modules_JxcoreSupport_Common::getTakenAppPorts(null, $clientId);
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
        $commands = array();

        $monitorEnabled = true;

        if ($monitorEnabled) {
            $commands = array(

                'if [ -d /etc/nginx/conf.d ] && [ ! -e /etc/nginx/conf.d/jxcore.conf ]; then',
                'mkdir /etc/nginx/jxcore.conf.d',
                'chown psaadm:nginx /etc/nginx/jxcore.conf.d',
                #'chmod 755 /etc/nginx/jxcore.conf.d',

                'echo "include /etc/nginx/jxcore.conf.d/*.conf;" > "/etc/nginx/conf.d/jxcore.conf"',
                'chown psaadm:nginx /etc/nginx/conf.d/jxcore.conf',
                'chmod 640 /etc/nginx/conf.d/jxcore.conf',
                'fi',
                '',
                'rm -rf /etc/nginx/jxcore.conf.d/*.conf',  //config files will be recreated on each app start
                '',
                'cd ' . dirname(Modules_JxcoreSupport_Common::$jxpath),
                './jx monitor start',
                './jx monitor run ' . Modules_JxcoreSupport_Common::$pathService . " &"
            );

            foreach (self::$domains as $id=>$domain) {
                $domain = Modules_JxcoreSupport_Common::getDomain($id);

                $enabled = $domain->JXcoreSupportEnabled();

                if ($enabled) {

                    $cmd = $domain->getSpawnerCommand();
                    if (!$cmd) continue;

                    $commands[] = '';
                    $commands[] = "if [ -e {$domain->getSpawnerPath()} ]; then";
                    $commands[] = "cp " . Modules_JxcoreSupport_Common::$pathSpawner . " " . $domain->getSpawnerPath();
                    $commands[] = $cmd;
                    $commands[] = "fi";
                }
            }

            $commands[] = "";
        }

        // reload nginx on batch run
        $commands[] = "/usr/local/psa/admin/bin/nginx_control -r";

        file_put_contents(Modules_JxcoreSupport_Common::$startupBatchPath, join("\n", $commands));
        chmod(Modules_JxcoreSupport_Common::$startupBatchPath, 0700);
    }


    public static function updateCron() {
        // modifying crontab
        $binary = "/usr/local/psa/admin/bin/crontabmng";

        $tmpfile = pm_Context::getVarDir() . "mycron";
        @exec("$binary get root > $tmpfile");
        $contents = file_get_contents($tmpfile);

        $cmd = "@reboot " . Modules_JxcoreSupport_Common::$startupBatchPath;
        $contents = self::saveBlockToText($contents, "JXcore", $cmd, null);

        if (trim($contents) === "") {
            @exec("$binary remove root");
        } else {
            // a NewLine character was needed on Nubisa's production server
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

        $binary = "/usr/local/psa/admin/bin/crontabmng";

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
            $cmd = $timing . " * " . Modules_JxcoreSupport_Common::$startupBatchPath;
        } else if ($action == 'stop') {
            $cmd = $timing . " * " . Modules_JxcoreSupport_Common::$jxpath . " monitor stop";
        }

        $contents = Modules_JxcoreSupport_Common::saveBlockToText($contents, "JXcore-immediate", $cmd, null);

        // a NewLine character was needed on Nubisa's production server
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
        return;

        $action = pm_Settings::get(self::sidMonitorStartScheduledByCronAction);
        $timestamp = pm_Settings::get(self::sidMonitorStartScheduledByCron);
        if (!$timestamp) return null;

        $now = mktime();
        $diff = $timestamp - $now;

        if ($diff > 0) {

            if ($addMessage) {

//                $waitSeconds = $diff + 10;
//                $refresh = '<script type="text/javascript">var cnt = ' . $waitSeconds
//                . '; '
//                . 'if(cnt>0){'
//                . '  setTimeout(function(){'
//                . '    var elm = document.getElementById("content");'
//                . '    var msg = document.createElement("div");'
//                . '    msg.id = "__waitjx"; msg.name = "__waitjx";'
//                . '    msg.innerHTML = "Please wait for JXcore to complete installation.. <style> .clearfix{display:none} </style>";'
//                . '    elm.appendChild(msg);'
//                . '  },1);'
//                . '}; var loop = function() { cnt--; if(cnt>=0){ document.getElementById("jx_refresh_count").innerHTML = cnt; };  if (cnt<0) document.location.reload(); else setTimeout(loop, 1000); }; loop() ;</script>';
//
//                //self::$status->addMessage("info", "timestamp = $timestamp, now = $now, diff = $diff");
//                $txt = "";
//                if ($action == 'start') $txt = "Completing the operation...";
//                if ($action == 'stop') $txt = "Monitor should be stopped in approx {$diff} seconds.";
//
//                $str = "$txt Page will be reloaded in <span id='jx_refresh_count' name='jx_refresh_count'>5</span> seconds." . $refresh;
//                self::$status->addMessage('info', $str, true);


                $refresh = '<script type="text/javascript">'
                    . '    var elm = document.getElementById("content");'
                    . '    var msg = document.createElement("div");'
                    . '    msg.id = "__waitjx"; msg.name = "__waitjx";'
                    . '    msg.innerHTML = "Please wait for JXcore to complete installation.. <style> .clearfix{display:none} </style>";'
                    . '    elm.appendChild(msg);'
                    . '    setTimeout( function() { document.location.reload(); }, 5000);'
                    . '    </script>';

                $str = "Completing the operation..." . $refresh;
                self::$status->addMessage('info', $str, true);
            }
        } else {

            $json = self::getMonitorJSON();
            $monitorRunning = $json !== null;
            if ($monitorRunning && $action == 'start') {
                self::updateCronImmediate();
                self::$status->addMessage('info', "JXcore monitor successfully started.");
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
        $icon = $flag ? Modules_JxcoreSupport_Common::iconON : Modules_JxcoreSupport_Common::iconOFF;
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

    public static function getSimpleButton($varName, $caption, $command, $iconURL = null, $url = null, $additionalStyle = null, $askConfirm = false)
    {
        $style = "vertical-align: middle; display: inline-block;";
        $iconStyle = "style=\"$style margin-right: 7px;\"";
        $iconId = "jx-icon-{$varName}-" . count(self::$buttonsDisabling);
        $icon = $iconURL ? "<img id=\"$iconId\" name=\"$iconId\" class='tootlipObserved' src='$iconURL' height='16' width='16' $iconStyle>" : "";

        if (!$url) {
            // form
            $btnstyle = "style='height: 15px; margin-left: 20px; margin-bottom: 6px; margin-top: 6px; $style $additionalStyle'";
            $onclick = "href=\"#\"";
            $ask = $askConfirm ? "if (!confirm('{$askConfirm}')) return false; " : "";
            if ($command) $onclick .= " onclick=\"{$ask}document.getElementById('{$varName}').value = '{$command}'; if (JXDisableButtons) { JXDisableButtons(); }; document.getElementById('pm-form-simple').submit();\"";
        } else {
            // list
            $btnstyle = "style='height: 15px; margin-left: 20px; $style $additionalStyle'";
            $onclick = strpos($url, "js:", 0) === false ? "href=\"$url\"" : "onclick=\"$url\"";
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
        $arr = array();
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
        // disabled
        return;

        $cmd1 = "/usr/local/psa/admin/bin/httpd_modules_ctl -s";
        $cmd2 = "/usr/local/psa/admin/bin/httpd_modules_ctl -e proxy_http";

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
        // disabled
        return false;
        $cmd = '/usr/local/psa/admin/bin/nginxmng -s 2>&1';
        @exec($cmd, $out, $ret);
        $str = join("\n", $out);

        if (!$ret) {
            $enabled = strpos($str, "Enabled") !== false;
            if (!$enabled && $verbose)
                self::$status->addMessage('warning', "Nginx is not enabled. Status: $str.");

            return $enabled;
        } else {
            $str = PanelClient::getLogged()->isAdmin ? $str . "." : "";
            self::$status->addMessage("error", "Cannot fetch nginx status.{$str} Exit code: $ret.");
            return false;
        }
    }

    private static function enableNginx()
    {
        // disabled
        return;
        $cmd2 = "/usr/local/psa/admin/bin/nginxmng -e 2>&1";

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

    public static function reloadNginx($force = null) {

        if (self::checkNginx(false) && (!self::$nginxReloaded || $force)) {
            $cmd = "/usr/local/psa/admin/bin/nginx_control -r";
//            @exec($cmd, $out, $ret);

//            StatusMessage::infoOrError($ret, "Nginx reloaded successfully.", "Cannot reload nginx. " . join("\n", $out) . ". Exit code: $ret." );
            self::$nginxReloaded = true;
        }
    }


    /**
     * Calls JXcore service process for running a command as root by passing args in form of GET url
     * @param $sid
     * @param $arg
     * @param string $msgOK
     * @param string $msgErr
     * @return bool
     */
    public static function callService($sid, $arg, $msgOK = null, $msgErr = null, $return = null) {

        // saving the command to the file (as psaadm)
        $cmd = "{$sid}={$arg}";
        $uid = uniqid();
        // Modules_JxcoreSupport_Common::$jxpath may not be available before calling constructor:
        // new Modules_JxcoreSupport_Common()
        $_jxpath = Modules_JxcoreSupport_Common::$jxpath ? Modules_JxcoreSupport_Common::$jxpath : pm_Settings::get(self::sidJXpath);

        if (!$_jxpath) {
            $err = "Cannot connect to JXcore service. Is JXcore installed and monitor running? $sid";
            if ($return !== "silent") StatusMessage::addError($err);
            return $err;
        }

        $fname = $_jxpath . "_{$uid}.cmd";

        if (is_array($sid)) {
            file_put_contents($fname, json_encode($sid));
        } else{
            file_put_contents($fname, $cmd);
        }

        // calling te service with file uid
        $url = Modules_JxcoreSupport_Common::$urlService . "cmd?cuid=$uid";

        $ret = Modules_JxcoreSupport_Common::getURL($url, $out);
        $out = trim(htmlspecialchars($out));
        $ok = str_replace("\n", "<br>", "$out") == "OK";
        $err = $ret ? $out : "Cannot connect to JXcore service.";
        $msg = $ok ? $msgOK : ($msgErr ? "$msgErr $err" : null);
        if ($msg && $return !== "silent") {
            $msg = str_replace("#arg#", $arg, $msg);
            $msg = str_replace("#sid#", $sid, $msg);
            self::$status->addMessage($ok ?  "info": "error", $msg);
        }
        return $return && $ret ? $out : $ok;
    }



    public static function monitorStartStop($req)
    {
        if (!self::isJXValid() || !in_array($req, array('start', 'stop', 'restart'), true)) return;
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

        if (in_array($req, array('start', 'restart')) && !$monitorWasRunning) {
            self::enableServices();
            $ret = Modules_JxcoreSupport_Common::updateCronImmediate("start");

            $interval = 1000000 / 2;// half of sec
            // wait for 90 sec. 60 should be enough anyway
            for($a=1; $a<180; $a++) {
                usleep($interval);
                $json = self::getMonitorJSON(true);
                if ($json !== null) break;
            }

            $cmd = null;
        } else
            if ($req === 'stop' && $monitorWasRunning) {
                $cmd = Modules_JxcoreSupport_Common::$jxpath . " monitor stop";
            } else {
                return;
            }

        if ($cmd !== null) {
            $cwd = getcwd();
            chdir(dirname(Modules_JxcoreSupport_Common::$jxpath));
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

            self::reloadNginx();
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

    public static function parseAppArgs($str) {

        $cmd = Modules_JxcoreSupport_Common::$jxpath . ' -e "console.log(JSON.stringify(process.argv.slice(1)))" ' . $str;
        @exec($cmd, $out, $ret);

        return !$ret ? join("", $out) : false;
    }


    public static function getHTMLTag($tagName, $strValue, $class = null, $style = null) {
        $class = $class ? " class=\"$class\" " : "";
        $style = $style ? " style=\"$style\" " : "";
        return "<$tagName{$class}{$style}>$strValue</$tagName>";
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
    private $appLogPathRelative = null;

    const appLogBasename = "index.txt";
    const appPath_default = "index.js";

    public $configChanged = false;
    private $nginxConfigChanged = false;

    public $row = null;

    public $log = array();

    public static function getFromRow($row) {
        $id = $row['id'];

        $d = null;
        try {
            $d = new pm_Domain($id);
        } catch (Exception $ex) {
        }

        if (!$d) {
            return null;
        }

        $domain = new DomainInfo();
        $domain->domain = $d;
        $domain->fileManager = new pm_FileManager($id);
        $domain->id = $id;
        $domain->name = $d->getName();
        $domain->rootFolder = $row['www_root'] . "/";
        $domain->appLogPathRelative = "jxcore_logs/" . self::appLogBasename;
        $domain->appLogDir = $domain->rootFolder . "jxcore_logs/";
        $domain->appLogPath = $domain->rootFolder . $domain->appLogPathRelative;
        $domain->row = $row;
        $domain->webspaceId = $row['webspace_id'];

        $domain->sysUserId = $row['sysId'];
        $domain->sysUser = $row['sysLogin'];
        $domain->sysUserHomeDir = $row['sysHome'];
        return $domain;
    }

    public function get($sid) {
        $wasSet = $this->wasSet($sid);

        $defaults = array();
        $defaults[Modules_JxcoreSupport_Common::sidDomainRestartMgrEnabled] = 1;
        $defaults[Modules_JxcoreSupport_Common::sidDomainRestartMgrWatchedPaths] = "*.js\n*.jx";
        $defaults[Modules_JxcoreSupport_Common::sidDomainRestartMgrIgnoredPaths] = "node_modules";
        $defaults[Modules_JxcoreSupport_Common::sidDomainRestartMgrInterval] = 5000;
        $defaults[Modules_JxcoreSupport_Common::sidDomainRestartMgrDepth] = 2;

        if (!$wasSet && isset($defaults[$sid]))
            return $defaults[$sid];
        else
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
        pm_Settings::set($sid . $this->id . "isset", $value === null ? "false" : "true");

        if ($sid === Modules_JxcoreSupport_Common::sidDomainJXcoreAppArgs) {
            $str = Modules_JxcoreSupport_Common::parseAppArgs($value);
            pm_Settings::set(Modules_JxcoreSupport_Common::sidDomainJXcoreAppArgsArrayStringified . $this->id, $str ? $str : "[]");
        }

        $changed = $old != $value;
        if ($changed) {
            $this->configChanged = true;
            if (in_array($sid,
                    array(
                        Modules_JxcoreSupport_Common::sidDomainJXcoreAppPath,
                        Modules_JxcoreSupport_Common::sidDomainJXcoreAppArgs,
                        Modules_JxcoreSupport_Common::sidDomainAppLogWebAccess,
                        Modules_JxcoreSupport_Common::sidDomainJXcoreAppPort,
                        Modules_JxcoreSupport_Common::sidDomainJXcoreAppPortSSL,
                        Modules_JxcoreSupport_Common::sidDomainAppNginxDirectives,
                        Modules_JxcoreSupport_Common::sidDomainAppUseSSL,
                        Modules_JxcoreSupport_Common::sidDomainAppSSLCert,
                        Modules_JxcoreSupport_Common::sidDomainAppSSLKey
                    )))
                $this->nginxConfigChanged = true;
        }
    }


    /**
     * Gets value defined for config param, or if was not defined - from subscription
     * @param $sid
     * @return null
     */
    public function getFinalConfigValue($sid) {

        $isCommon = isset(Modules_JxcoreSupport_Common::$subscriptionParams[$sid]);

        if (!$isCommon) {
            return $this->get($sid);
        }

        $sub = $this->getSubscription();

        $vald = $this->get($sid);
        $vals = $sub->get($sid);

        $wasSet = $this->wasSet($sid);

        $edits = array(Modules_JxcoreSupport_Common::sidDomainJXcoreAppMaxMemLimit, Modules_JxcoreSupport_Common::sidDomainJXcoreAppMaxCPULimit, Modules_JxcoreSupport_Common::sidDomainJXcoreAppMaxCPUInterval);
        if (in_array($sid, $edits) && !$vald && "$vald" !== "0" )
            $wasSet = false;

        $ret = null;

        if (!$wasSet) {
//            StatusMessage::addDebug("OK returning val sub $vals for $sid");
            $ret = $vals;
        } else {
            $ret = $vald;
//            StatusMessage::addError("OK returning val dom $vald for $sid");
        }

        return $ret;
    }


    /**
     * Asks the monitor if the app is running
     * @param null $wait - if true, then checking is done in short loop
     * @return bool
     */
    public function isAppRunning($wait = null)
    {
        $json = Modules_JxcoreSupport_Common::getMonitorJSON();
        $path = $this->getSpawnerPath();

        $running =  $json !== null && $path !== false && strpos($json, $path) !== false;

        $interval = 1000000 / 2; // half of sec
        if ($wait && Modules_JxcoreSupport_Common::$restartFlag !== "nowait") {
            usleep($interval);
            // wait for 5 sec
            for($a=1; $a<10; $a++) {
                Modules_JxcoreSupport_Common::clearMonitorJSON();
                $running = $this->isAppRunning();
                if ($running) break;
                usleep($interval);
            }
        }
        return $running;
    }

    public function getAppStatus()
    {
        $p = $this->getAppPathOrDefault(true);
        if (file_exists($p)) {
            $json = Modules_JxcoreSupport_Common::getMonitorJSON();
            $monitorRunning = $json !== null;

            if ($monitorRunning) {
                $port = $this->getAppPort();
                if ($port < Modules_JxcoreSupport_Common::$minApplicationPort || $port > Modules_JxcoreSupport_Common::$maxApplicationPort) {
                    $port .= "<br><span style='color: orangered'>TCP port out of range.</span>";
                }

                $portSSL = $this->getAppPort(true);
                if ($portSSL < Modules_JxcoreSupport_Common::$minApplicationPort || $portSSL > Modules_JxcoreSupport_Common::$maxApplicationPort) {
                    $portSSL .= "<br><span style='color: orangered'>TCPS port out of range.</span>";
                }

//                return Modules_JxcoreSupport_Common::getIcon($this->isAppRunning(), "Running on TCP: $port, TCPS: $portSSL", "Not running");
                return Modules_JxcoreSupport_Common::getIcon($this->isAppRunning(), "Running on TCP: $port", "Not running");
            } else {
//                $ret = Common::checkCronScheduleStatus(false);
//                $str = ($ret && $ret > 0) ? "<br>Monitor is starting in $ret secs." : "Monitor offline.";
                $str = "Monitor offline.";
                return Modules_JxcoreSupport_Common::getIcon(false, "", "Not running. $str");
            }
        } else {
            return "<span style=\"color: orangered;\">No file:</span> " . $this->getAppPathOrDefault();
        }
    }

    public function JXcoreSupportEnabled_Value()
    {
        return $this->get(Modules_JxcoreSupport_Common::sidDomainJXcoreEnabled);
    }

    public function JXcoreSupportEnabled()
    {
        $sub = $this->getSubscription();
        if (!$sub) {
            return null;
        }
        return $sub->JXcoreSupportEnabled() && $this->JXcoreSupportEnabled_Value();
    }

    public function getAppPort($ssl = false)
    {
        $sid = $ssl ? Modules_JxcoreSupport_Common::sidDomainJXcoreAppPortSSL : Modules_JxcoreSupport_Common::sidDomainJXcoreAppPort;
        return pm_Settings::get( $sid . $this->id);
    }

    public function getAppPortOrDefault($updateIfEmpty = false, $ssl = false)
    {
        $sid = $ssl ? Modules_JxcoreSupport_Common::sidDomainJXcoreAppPortSSL : Modules_JxcoreSupport_Common::sidDomainJXcoreAppPort;

        $port = pm_Settings::get($sid . $this->id);
        if (!$port || trim($port) === '') {
            $port = Modules_JxcoreSupport_Common::getFreePorts($this->id, $ssl)[0];
            if ($updateIfEmpty)
                $this->setAppPort($port, $ssl);
        }
        return $port;
    }

    public function setAppPort($port, $ssl = false)
    {
        $sid = $ssl ? Modules_JxcoreSupport_Common::sidDomainJXcoreAppPortSSL : Modules_JxcoreSupport_Common::sidDomainJXcoreAppPort;
        pm_Settings::set($sid . $this->id, $port);
    }

    private function getPortStatus($val)
    {
        if (!$val)
            return "<span style='color: orangered'>not defined.</span>";

        if ($val < Modules_JxcoreSupport_Common::$minApplicationPort || $val > Modules_JxcoreSupport_Common::$maxApplicationPort)
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
        $val = $this->get(Modules_JxcoreSupport_Common::sidDomainJXcoreAppPath);
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
                pm_Settings::set(Modules_JxcoreSupport_Common::sidDomainJXcoreAppPath . $this->id, $path);
        }

        return $fullPath ? $this->rootFolder . $path : $path;
    }

    public function getAppLogWebAccess()
    {
        return pm_Settings::get(Modules_JxcoreSupport_Common::sidDomainAppLogWebAccess . $this->id);
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
     * Copies spawner file for domain, sets it's permissions and returns full path (or false on error)
     * @return bool|string
     */
    public function getSpawnerPath()
    {
        $spawnerOrg = Modules_JxcoreSupport_Common::$pathSpawner;
        chmod($spawnerOrg, 0644);
        $spawner = pm_Context::getVarDir() . "spawner_{$this->id}.jx";

        if ($this->JXcoreSupportEnabled()) {
            if (copy($spawnerOrg, $spawner) === false) {
                StatusMessage::addError("Cannot copy spawner for application of domain {$this->name}.");
                return false;
            }
            if (chmod($spawner, 0644) === false) {
                StatusMessage::addError("Cannot set permissions for application's spawner of domain {$this->name}.");
                return false;
            }
        }

        return $spawner;
    }

    public function getSpawnerDataPath()
    {
        $spawner = $this->getSpawnerPath();
        if ($spawner === false)
            return null;
        return $spawner . ".dat";
    }
    /**
     * Returns parameters for spawner command line (log, user, appFile) as array
     * @param $additionalParams
     * @return array
     */
    public function getSpawnerParams()
    {
        $args = $this->get(Modules_JxcoreSupport_Common::sidDomainJXcoreAppArgsArrayStringified);

        $strings = array(
            "user" => $this->sysUser,
            "home" => $this->rootFolder,
            "log" => $this->appLogPathRelative,
            "file" => $this->getAppPath(false),
            "domain" => $this->name,
            "tcp" => $this->getAppPort(),
            "tcps" => $this->getAppPort(true),
            "logWebAccess" => $this->getAppLogWebAccess(),
            "args" => $args ? json_decode($args) : array(),
            "plesk" => true
        );

        if ($this->get(Modules_JxcoreSupport_Common::sidDomainAppUseSSL)) {
            $strings["ssl_key"] = $this->rootFolder . $this->get(Modules_JxcoreSupport_Common::sidDomainAppSSLKey);
            $strings["ssl_crt"] = $this->rootFolder . $this->get(Modules_JxcoreSupport_Common::sidDomainAppSSLCert);
        }

        return $strings;
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
        $spawner_data_file = $this->getSpawnerDataPath();

        // we will compare previous nginx directives with current
        $nginx_directives = $this->get(Modules_JxcoreSupport_Common::sidDomainAppNginxDirectives);

        $arr = $this->getSpawnerParams();
        // argv for the spawner will not contain nginx directives any more - will contain just the bool flag,
        // that nginx directives are defined
        if ($nginx_directives)
            $arr["nginx"] = true;

        $spawner_args = json_encode($arr);

        if ($this->JXcoreSupportEnabled()) {
            $arr["nginx"] = $nginx_directives;
            $arr["env"] = $this->get(Modules_JxcoreSupport_Common::sidDomainJXcoreAppEnvVars);

            $watch = array();
            $watch["enabled"] = $this->get(Modules_JxcoreSupport_Common::sidDomainRestartMgrEnabled);
            $watch["paths"] = $this->get(Modules_JxcoreSupport_Common::sidDomainRestartMgrWatchedPaths);
            $watch["ignore"] = $this->get(Modules_JxcoreSupport_Common::sidDomainRestartMgrIgnoredPaths);
            $watch["interval"] = intval($this->get(Modules_JxcoreSupport_Common::sidDomainRestartMgrInterval));
            $watch["depth"] = intval($this->get(Modules_JxcoreSupport_Common::sidDomainRestartMgrDepth));
            $arr["watch"] = $watch;

            $data_new = json_encode($arr, 128);  // 128 stands for pretty print
            $data_old = "";
            if (file_exists($spawner_data_file))
                $data_old = file_get_contents($spawner_data_file);

            if ($data_old != $data_new) {
                //StatusMessage::addDebug("spawner_data_file save request for {$this->name}. From file: >{$data_old}<, From form: >{$data_new}<");
                file_put_contents($spawner_data_file, $data_new);
            }
        }

        return $sub->jxpath . " {$spawner} -opt '{$spawner_args}'";
    }

    public function clearLogFile()
    {
        if (file_exists($this->appLogPath)) {
            $oldSize = filesize($this->appLogPath);

            $running = $this->isAppRunning();

            $json = Modules_JxcoreSupport_Common::getMonitorJSON();
            $monitorRunning = $json !== null;

            if (!$monitorRunning) {
//                StatusMessage::addDebug("monitor not running - trying to delete by php " . $this->appLogPath);
                unlink($this->appLogPath);
                StatusMessage::infoOrError(file_exists($this->appLogPath), 'Log cleared.', 'Could not clear the log file.');
                return;
            }

            $ret = false;
            if ($running) {
//                StatusMessage::addDebug("running - trying to delete log by clearfile");
                $clearlog = $this->appLogDir . "clearlog.txt";
                $rel = str_replace($this->fileManager->getFilePath("."), "", $clearlog);
                $this->fileManager->filePutContents($rel, "clear" + mt_rand());

                $interval = 1000000 / 3;  // 1/3 of sec
                // wait for 5 sec ( 1/3 * 3 * 5 = 15)
                for($a=1; $a<15; $a++) {
                    usleep($interval);
                    clearstatcache();
                    $newSize = filesize($this->appLogPath);
                    // 32 = "Spawner info: Log file cleared."
                    $ret = $newSize < $oldSize || !$newSize || $newSize == 32;
                    if ($ret) break;
                }

            } else {
//                StatusMessage::addDebug("not running - trying to delete log by service");
                $out = Modules_JxcoreSupport_Common::callService("delete", "applog&path=" .$this->appLogPath, null, null, true);
                $ret = !file_exists($this->appLogPath);
            }

            StatusMessage::infoOrError(!$ret, 'Log cleared.', 'Could not clear the log file.');
//            StatusMessage::addDebug($out);
        }
        return true;
    }

    public function getSubscription() {
        $id = $this->webspaceId;

        if ($id == 0) $id = $this->id;

        $subs = SubscriptionInfo::getIds();

        foreach ($subs as $sub_id) {
            $sub = SubscriptionInfo::getSubscription($sub_id);

            if ($sub && $sub->mainDomain->id === $id) {
                return $sub;
            }
        }

        // domain should always have a subscription
        //StatusMessage::addError("Cannot find subscription for domain {$this->name}.");
        return null;
    }


    public function updateConfigs($forceRestart = false)
    {
        $this->updateJXConfig();
        $this->updatehtaccess();

        $running = $this->isAppRunning();
        $enabled = $this->JXcoreSupportEnabled();

        if ($this->configChanged) {
            Modules_JxcoreSupport_Common::$needToReloadNginx = true;
        }

        if ($this->configChanged || $forceRestart) {
            if ($enabled) {
                if ($running) {
                    $this->restartApp();
                } else {
                    $this->startApp();
                }
            } else {
                // this occurs when app was disabled from panel
                if ($running) {
                    $this->stopApp();
                } else {
                    // do nothing
                }
                $this->clearFiles();
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

        $fname = Modules_JxcoreSupport_Common::$dirAppsConfigs . $bname;

        if (!$jxenabled) {
            @unlink($fname);
        } else {
            $params = array(
                "portTCP" => Modules_JxcoreSupport_Common::sidDomainJXcoreAppPort,
                "portTCPS" => Modules_JxcoreSupport_Common::sidDomainJXcoreAppPortSSL,
                "maxCPU" => Modules_JxcoreSupport_Common::sidDomainJXcoreAppMaxCPULimit,
                "maxCPUInterval" => Modules_JxcoreSupport_Common::sidDomainJXcoreAppMaxCPUInterval,
                "maxMemory" => Modules_JxcoreSupport_Common::sidDomainJXcoreAppMaxMemLimit,
                "allowCustomSocketPort" => Modules_JxcoreSupport_Common::sidDomainJXcoreAppAllowCustomSocketPort,
                "allowSysExec" => Modules_JxcoreSupport_Common::sidDomainJXcoreAppAllowSysExec,
                "allowLocalNativeModules" => Modules_JxcoreSupport_Common::sidDomainJXcoreAppAllowLocalNativeModules);

            // as non-strings
            foreach ($params as $key => $sid) {
                $val = $this->getFinalConfigValue($sid);

                if ($val === 0 || $val === "0") {
                    // 0 means disable the limit
                    if ($key === "maxCPU" || $key === "maxMemory")
                        continue;
                }

                if (trim($val) != "") {
                    $arr[] = "\"{$key}\" : {$val}";
                }
            }
            $arr[] = "\"allowMonitoringAPI\" : false";

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
        //disabled
        return;

        $mgr = $this->fileManager;

        $basename = ".htaccess";
        $file = $this->rootFolder . $basename;

        $relFile = str_replace($mgr->getFilePath("."), "", $file);

        if ($this->JXcoreSupportEnabled()) {
            $htaccess = array();
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
            $htaccess = array();
        }
        $txt = "";

        if ($mgr->fileExists($relFile)) $txt = $mgr->fileGetContents($relFile);
        $old = $txt;

        $txt = Modules_JxcoreSupport_Common::saveBlockToText($txt, "JXcore-domainID-" . $this->id, join("\n", $htaccess), null);
        $txt = trim($txt);

        if ($txt !== $old) {
//            StatusMessage::addDebug(".htaccess path test. Writing to file disabled.\nFor domain {$this->name}:\n the full path is $file\nbut i'm using relative path {$relFile}");

//        $mgr->filePutContents($relFile, $txt);
//
//        $txt2 = file_get_contents($file);
//        if ($txt2 !== $txt)
//            return "Cannot save $file";
        }

        return true;
    }

    private function startApp() {

        if (Modules_JxcoreSupport_Common::$restartFlag === "norestart") return;

        $json = Modules_JxcoreSupport_Common::getMonitorJSON();
        $errMsg = "Cannot start the application {$this->name}:";

        // starting application
        if ($json === null) {
            // no point to run an application now, if monitor is not running
            StatusMessage::addError("$errMsg the JXcore monitor is not running.");
        } else {
            if ($this->isAppRunning()) return;

            $sub = $this->getSubscription();
            if (!$sub) {
                // this will rather not happen
                StatusMessage::addError("$errMsg No subscription.");
                return;
            }

            if (!$sub->JXcoreSupportEnabled()) {
                StatusMessage::addError("$errMsg JXcore support for the subscription is disabled.");
                return;
            }

            $cmd = $this->getSpawnerCommand();
            if (!$cmd) {
                StatusMessage::addError("$errMsg invalid spawner command.");
                return;
            }
            @exec($cmd, $out, $ret);

            Modules_JxcoreSupport_Common::clearMonitorJSON();

            if (Modules_JxcoreSupport_Common::$restartFlag !== "nowait") {
                // waiting for the app to be restarted by monitor
                // cannot rely on exitcode, so checking the monitor
                $appRunning = $this->isAppRunning(true);
                StatusMessage::infoOrError(!$appRunning, "The application {$this->name} successfully started.", "The application {$this->name} could not be started. " . join("\n", $out));
            }

            $this->configChanged = false;
        }
    }

    private function stopApp()
    {
        if (!$this->isAppRunning() || Modules_JxcoreSupport_Common::$restartFlag === "norestart") return;

        $cmd = Modules_JxcoreSupport_Common::$jxpath . " monitor kill {$this->getSpawnerPath()} 2>&1";
        @exec($cmd, $out, $ret);
        Modules_JxcoreSupport_Common::clearMonitorJSON();

        if (Modules_JxcoreSupport_Common::$restartFlag !== "nowait") {
            // cannot rely on exitcode, so checking the monitor
            StatusMessage::infoOrError($this->isAppRunning(), "The application {$this->name} successfully stopped.", "Cannot stop the application. " . join("\n", $out) . ". Exit code: $ret");
        }
        $this->configChanged = false;
    }

    private function restartApp() {

        if (Modules_JxcoreSupport_Common::$restartFlag === "norestart") return;

        if (!$this->isAppRunning()) {
            $this->startApp();
        } else {
            if ($this->nginxConfigChanged) {
                $this->stopApp();
                $this->startApp();
            } else {
                // faster by killing the process and letting the monitor to respawn
                $out = Modules_JxcoreSupport_Common::callService("kill", $this->id, null, null, true);
                Modules_JxcoreSupport_Common::clearMonitorJSON();

                if (Modules_JxcoreSupport_Common::$restartFlag !== "nowait") {
                    StatusMessage::infoOrError(!$this->isAppRunning(true), "The application {$this->name} was successfully restarted.", "Could not restart the application {$this->name}. $out");
                }
              }
            $this->configChanged = false;
        }
    }

    /**
     * Calls JXcore service process for running a command as root by passing an array to be easily stringified to json
     * @param $cmd
     * @param $arg
     * @param null $msgOK
     * @param null $msgErr
     * @param null $return
     * @return bool
     */
    public function callService($cmd, $arg, $msgOK = null, $msgErr = null, $return = null) {
        $arr = array();
        $arr["cmd"] = $cmd;
        $arr["arg"] = $arg;
        $arr["spawner_args"] = $this->getSpawnerParams();
        return Modules_JxcoreSupport_Common::callService($arr, null, $msgOK, $msgErr, $return);
    }

    private function clearFiles() {
        // removing nginx conf for the domain
        Modules_JxcoreSupport_Common::callService("nginx", "remove&domain=" . $this->name, null, null);

        $spawner = $this->getSpawnerPath();
        if ($spawner === false) return null;
        $spawner_data_file = $this->getSpawnerDataPath();

        if (file_exists($spawner)) unlink($spawner);
        if (file_exists($spawner_data_file)) unlink($spawner_data_file);
    }
}


class PanelClient
{
    public $panelLogin = null;
    public $type = null;
    public $whoami = null;
    public $statusBar = null;
    public $id = null;
    public $smbUserId = null;
    public $restrictedMainDomainId = null;
    public $isAdmin = null;
    public $isAdminRestricted = null;

    private static $smbUsers = array();
    private static $smbUsersFetched = false;

    function PanelClient($clientId = null)
    {
        if (!$clientId) {
            $client = pm_Session::getClient();
            $clientId = $client->getId();
        }

        $this->id = $clientId;
        // leave this order
        $this->smbUserId = self::getCurrentSmbUserId();
        $this->restrictedMainDomainId = $this->getRestrictedMainDomainId();

        $this->isImpersonated = pm_Session::isImpersonated();
        $this->getImpersonatedClientId = pm_Session::getImpersonatedClientId();
        $this->whoami = trim(shell_exec("whoami"));
        $this->isAdminRestricted = $client->isAdmin() && $this->restrictedMainDomainId;
        $this->isAdmin = $client->isAdmin() && !$this->restrictedMainDomainId;
        $this->isReseller = $client->isReseller();
        $this->isClient = $client->isClient();

        $dbAdapter = pm_Bootstrap::getDbAdapter();


        $sql = "SELECT *,
            cli.login as cliLogin, cli.type as cliType
            FROM clients cli
            where cli.id = $clientId";

        $statement = $dbAdapter->query($sql);

        $row = $statement->fetch();

//        var_dump($client);

        $this->panelLogin = $row["cliLogin"];
        $this->type = $row["cliType"];
//        $this->statusBar = "Client Id: {$clientId}, Username: <b>{$this->panelLogin}</b>. Account type: <b>{$this->type}</b>. Whoami: <b>{$this->whoami}</b>.<hr>";
        $this->statusBar = "";

        if ($this->isAdmin)
            $this->statusBar = 'Logged as <b>admin</b>.<br>';
        else if ($this->isAdminRestricted) {
            $d = Modules_JxcoreSupport_Common::getDomain($this->restrictedMainDomainId);
            $this->statusBar = 'Logged as <b>admin</b> with access limited only to ' . $d->name . '<br>';
        }
    }

    private static function getCurrentSmbUserId() {

        // plesk 12+
        if (isset($_SESSION['auth']) && isset($_SESSION['auth']['smbUserId']))
            return intval($_SESSION['auth']['smbUserId']);

        // lower Plesk versions
        $sid = session_id() . '_smb_user_id';
        if (isset($_SESSION[$sid]))
            return intval($_SESSION[$sid]);

        return null;
    }

    private function getRestrictedMainDomainId() {

        if (!$this->smbUserId)
            return null;

        PanelClient::getSmbUsers();

        if (isset(PanelClient::$smbUsers[$this->smbUserId]) && PanelClient::$smbUsers[$this->smbUserId]['subscriptionDomainId'])
            return intval(PanelClient::$smbUsers[$this->smbUserId]['subscriptionDomainId']);

        return null;
    }


    // this is implemented of smb users, for which  $client->hasAccessToDomain() is not working properly
    public function hasAccessToDomain($domain) {

        if (!$domain) return false;
        if ($this->isAdmin) return true;

        if (!$this->restrictedMainDomainId)
            return $this->id == $domain->row['cl_id'];

        //        StatusMessage::addDebug("subid $subId, ssid $sub->id, name $domain->name, webspacjeid $domain->webspaceId, submaindomainid $sub->mainDomainId");

        // check subscription restriction
        return $this->hasAccessToSubscription($domain->getSubscription());
    }

    public function hasAccessToSubscription($sub) {

        if (!$sub) return false;
        if ($this->isAdmin) return true;

        return $sub && $sub->mainDomainId == $this->restrictedMainDomainId;
    }

    public function getAvailableDomains() {
        // make sure domains are read from db
        Modules_JxcoreSupport_Common::getDomainsIDs();

        $domain_ids = Modules_JxcoreSupport_Common::getDomainsIDs();

        $ids = array();
        foreach($domain_ids as $id) {
            $d = Modules_JxcoreSupport_Common::getDomain($id);
            if (self::hasAccessToDomain($d)) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    private static function getSmbUsers() {
        if (!self::$smbUsersFetched) {

            $smbUserId = self::getCurrentSmbUserId();
            if ($smbUserId === null) {
                // no need to load the data if $smbUserId is unknown
                self::$smbUsersFetched = true;
                return;
            }

            self::$smbUsers = array();
            $dbAdapter = pm_Bootstrap::getDbAdapter();
            $sql = "SELECT * FROM smb_users order by id ASC";

            $statement = $dbAdapter->query($sql);
            while ($row = $statement->fetch()) {
                self::$smbUsers[intval($row['id'])] = $row;
            }
            self::$smbUsersFetched = true;
        }
        return self::$smbUsers;
    }

    private static $logged = null;

    public static function getLogged() {
        if (!PanelClient::$logged)
            PanelClient::$logged = new PanelClient();

        return PanelClient::$logged;
    }
}


class JXconfig {

    public $portTCP = 0; // int
    public $portTCPS = 0; // int

    public $globalModulePath = null; // string
    public $globalApplicationConfigPath = null; //string


    /*
     * Gets value for domain or subscription
     */
    private static function get(&$form, $sid, $id, $isDomain = false) {

        if ($isDomain) {
            $domain = Modules_JxcoreSupport_Common::getDomain($id);
            $ret = $domain->getFinalConfigValue($sid);

            $form->addElement('hidden', "{$sid}_org", array(
                'value' => $ret
            ));
            return $ret;
        } else {
            $subscription = SubscriptionInfo::getSubscription($id);
            $val = $subscription->get($sid);
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

        $canEdit = PanelClient::getLogged()->isAdmin;

        Modules_JxcoreSupport_Common::addHR($form);

        $type = $canEdit ? 'text' : 'simpleText';
        $typeChk = $canEdit ? 'checkbox' : 'simpleText';
        $tmpID = 0;

        $maxInt = array('Int', array("LessThan", true, array('max' => 2147483647)));

        $val = self::get($form, Modules_JxcoreSupport_Common::sidDomainJXcoreAppMaxMemLimit, $id, $isDomain);
        $form->addElement($type, $canEdit ? Modules_JxcoreSupport_Common::sidDomainJXcoreAppMaxMemLimit : ("field" . ($tmpID++)) , array(
            'label' => 'Maximum memory limit',
            'value' => $canEdit ? $val : ($val ? "$val kB" : "no limit"),
            'required' => false,
            'validators' => array('Int', $maxInt, array("GreaterThan", true, array('min' => -1))),
            'description' => 'Maximum size of memory (kB), which can be allocated by the application. Value 0 disables the limit.',
            'escape' => false
        ));

        $val = self::get($form, Modules_JxcoreSupport_Common::sidDomainJXcoreAppMaxCPULimit, $id, $isDomain);
        $form->addElement($type, $canEdit ? Modules_JxcoreSupport_Common::sidDomainJXcoreAppMaxCPULimit : ("field" . ($tmpID++)), array(
            'label' => 'Max CPU',
            'value' => $canEdit ? $val : ($val ? "$val %" : "no limit"),
            'required' => false,
            'validators' => array('Int', $maxInt, array("GreaterThan", true, array('min' => -1))),
            'description' => 'Maximum CPU usage (percentage) allowed for the application. Value 0 disables the limit.',
            'escape' => false
        ));

        $val = self::get($form, Modules_JxcoreSupport_Common::sidDomainJXcoreAppMaxCPUInterval, $id, $isDomain);
        $form->addElement($type, $canEdit ? Modules_JxcoreSupport_Common::sidDomainJXcoreAppMaxCPUInterval : ("field" . ($tmpID++)), array(
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

        $fake = null;
        $val = self::get($form, Modules_JxcoreSupport_Common::sidDomainJXcoreAppAllowCustomSocketPort, $id, $isDomain);
         $form->addElement($typeChk, $canEdit ? Modules_JxcoreSupport_Common::sidDomainJXcoreAppAllowCustomSocketPort : ("field" . ($tmpID++)), array(
            'label' => 'Allow custom socket port' ,
            'description' => "",
            'value' => $canEdit ? $val : ("$val" === "1" ? "Allow" : "Disallow"),
            "escape" => false
        ));

        $val = self::get($form, Modules_JxcoreSupport_Common::sidDomainJXcoreAppAllowSysExec, $id, $isDomain);
        $form->addElement($typeChk, $canEdit ? Modules_JxcoreSupport_Common::sidDomainJXcoreAppAllowSysExec : ("field" . ($tmpID++)), array(
            'label' => 'Allow to spawn/exec child processes',
            'description' => "",
            'value' => $canEdit ? $val : ("$val" === "1" ? "Allow" : "Disallow")
        ));

        $val = self::get($form, Modules_JxcoreSupport_Common::sidDomainJXcoreAppAllowLocalNativeModules, $id, $isDomain);
        $form->addElement($typeChk, $canEdit ? Modules_JxcoreSupport_Common::sidDomainJXcoreAppAllowLocalNativeModules : ("field" . ($tmpID++)), array(
            'label' => 'Allow to call local native modules',
            'description' => "",
            'value' =>  $canEdit ? $val : ("$val" === "1" ? "Allow" : "Disallow")
        ));

    }

    public static function saveDomainValues($form, $domain) {

        $subscription = $domain->getSubscription();

        $params = array_keys(Modules_JxcoreSupport_Common::$subscriptionParams);

        foreach ($params as $param) {
            $val_new = $form->getValue($param);
            $val_org = $form->getValue("{$param}_org");

            $val_subs = $subscription->get($param);
            $equalToSub = $val_new === $val_subs;
            $differentThanBefore = $val_new !== $val_org;

            $val_to_save = $val_org;
            if ($equalToSub) {
                $val_to_save = null;
                //StatusMessage::addDebug("Saving null to $param");
            }
            else if ($differentThanBefore) {
                $val_to_save = $val_new;
                //StatusMessage::addDebug("Saving new $val_new to $param (old val = $val_org");
            } else {
                //StatusMessage::addDebug("Saving org $val_org to $param");
            }

            // we save always, even null values !
            //if ($differentThanBefore) {
                $domain->set($param, $val_to_save);
                $domain->configChanged = true;
            //}
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

    private static $subscriptions = array();
    private static $fetched = false;

    public $configChanged = false;

    private static function getSubscriptions() {
        if (self::$fetched) return;

        $dbAdapter = pm_Bootstrap::getDbAdapter();
        $sql = "SELECT * from `Subscriptions` where object_type = 'domain'";
        $statement = $dbAdapter->query($sql);

        $domainIds = Modules_JxcoreSupport_Common::getDomainsIDs();

        while ($row = $statement->fetch()) {

            $mainDomainId = $row['object_id'];

            if (in_array($mainDomainId, $domainIds)) {
                $sub = new SubscriptionInfo();
                $sub->id = $row['id'];
                $sub->sid = "subscription" . $sub->id;
                $sub->mainDomainId = $mainDomainId;
                $sub->mainDomain = Modules_JxcoreSupport_Common::getDomain($mainDomainId);

                $sub->jxdir = Modules_JxcoreSupport_Common::$dirSubscriptionConfigs . $sub->mainDomain->name . "/";
                $sub->jxpath = $sub->jxdir . basename(Modules_JxcoreSupport_Common::$jxpath);

                self::$subscriptions[intval($sub->id)] = $sub;
            } else {
                // subscription might be invalid - without main domain
                //StatusMessage::addDebug("Invalid domain id " . $mainDomainId);
            }
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

        if (!Modules_JxcoreSupport_Common::mkdir($this->jxdir)) {
            StatusMessage::addError("Could not create directory for subscription jx: " . $this->jxdir);
        }

        if (!file_exists($this->jxpath)) {
            copy(Modules_JxcoreSupport_Common::$jxpath, $this->jxpath);
            // rx for all
            chmod($this->jxpath, 0555);
        }

        if (file_exists($this->jxpath)) {

            $cfg = '{
                       "monitor" :
                       {
                           "log_path" : "' . $this->jxdir . 'jx_monitor_[WEEKOFYEAR]_[YEAR].log",
                           "users": [ "psaadm" ],
                            "https" : {
                                "httpsKeyLocation" : "' . pm_Context::getVarDir()  .'server.key",
                                "httpsCertLocation" : "' . pm_Context::getVarDir()  .'server.crt"
                            }
                       },
                       "globalModulePath" : "' . Modules_JxcoreSupport_Common::$dirNativeModules . '",
                       "globalApplicationConfigPath" : "' . Modules_JxcoreSupport_Common::$dirAppsConfigs . '",
                       "npmjxPath" : "' . dirname(Modules_JxcoreSupport_Common::$jxpath) . '"';

            $cfg .= '}';

            $fname = $this->jxdir . "jx.config";
            $old = file_exists($fname) ? file_get_contents($fname) : null;

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

        if ($this->configChanged) {
            Modules_JxcoreSupport_Common::$needToReloadNginx = true;
        }
    }

    public function get($sid) {
        $wasSet = $this->wasSet($sid);

        $defaults = array();
        $defaults[Modules_JxcoreSupport_Common::sidDomainJXcoreAppAllowSysExec] = 1;
        $defaults[Modules_JxcoreSupport_Common::sidDomainJXcoreAppAllowLocalNativeModules] = 1;
        $defaults[Modules_JxcoreSupport_Common::sidSubscriptionJXcoreEnabled] = 1;

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
        $ids = Modules_JxcoreSupport_Common::getDomainsIDs();

        $ret = array();
        foreach($ids as $id) {
            $domain = Modules_JxcoreSupport_Common::getDomain($id);
            // first condition applies to main domain of the subscription
            // second condition applies to all other domains of the subscription
            if ($this->mainDomainId == $id  || $this->mainDomainId == $domain->webspaceId) {
                $ret[$domain->id] = $domain;
            }
        }
        return $ret;
    }

    public function JXcoreSupportEnabled()
    {
        return $this->get(Modules_JxcoreSupport_Common::sidSubscriptionJXcoreEnabled);
    }
}


class StatusMessage {
    private static $status = null;
    private static $osInfo = null;
    private static $logFile = null;

    public static function init(&$status) {
        self::$status = $status;
        self::$logFile = pm_Context::getVarDir() . 'debug.log';
        // remove the file on first call
        // this way it is always refreshed on tab/page refresh
        if (file_exists(self::$logFile))
            @unlink(self::$logFile);
    }

    private static function addMessage($type, $txt) {
        self::save($txt);
        if (!self::$status) return false;
        $txt = str_replace("\n", "<br>", "$txt");
        self::$status->addMessage($type, $txt);
    }

    private static function save($txt) {
        self::$osInfo = JXcoreOSInfo::get();
        if (!self::$osInfo->error && self::$osInfo->debug_log)
            file_put_contents(self::$logFile, PHP_EOL . $txt, FILE_APPEND);
    }

    public static function addError($err) {
        self::addMessage('error', $err);
    }

    public static function addWarning($txt) {
        self::addMessage('warning', $txt);
    }

    public static function addDebug($txt) {
        self::addMessage('warning', $txt);
    }

    public static function dataSavedOrNot($saved) {
        self::addMessage('info', $saved ? 'Data was successfully saved.' : 'Nothing to save.');
    }

    public static function infoOrError($isError, $infoMsg, $errMsg) {
        self::addMessage($isError ? 'error' : 'info', $isError ? $errMsg : $infoMsg);
    }

    public static function orange($txt) {
        return '<span class="jxOrangeRed">' . $txt . '</span>';
    }

    public static function green($txt) {
        return '<span class="jxGreen">' . $txt . '</span>';
    }
}


class JXcoreLatestVersionInfo {

    // url to S3 folder
    public $url = null;
    // version of JXcore in S3 folder
    public $version = null;
    // true, if there is already latest version installed
    public $isLatest = null;
    // true, if current version is lower than the newest
    public $isUpdateAvailable = null;
    public $mustUpdate = null;
    public $error = null;
    // update info displayed on JXcore Configuration tab (next tu JXcore Version)
    public $status = null;

    // e.g. 0.2.3.7
    private $localNumber = null;
    // e.g. 0.3.0.0
    private $remoteNumber = null;

    function JXcoreLatestVersionInfo($showError = false){
        $output = "";
        $ret = Modules_JxcoreSupport_Common::getURL("https://jxcore.s3.amazonaws.com/latest.txt", $output);
        $arr = explode("|", $output);
        if (!$ret || count($arr) < 2) {
            $this->status = "Could not fetch the latest version number: " . $output;
            $this->error = true;
            if ($showError)
                StatusMessage::addError($this->error);
            return;
        }

        $this->url = trim($arr[0]);
        if (substr($this->url, -1) !== "/")
            $this->url .= "/";
        $this->version = trim($arr[1]);

        if (!Modules_JxcoreSupport_Common::$jxv)
            return;

        $remove = array("v ", "Beta-", "v");
        $this->localNumber = trim(str_replace( $remove, "", Modules_JxcoreSupport_Common::$jxv));
        $this->remoteNumber = trim(str_replace($remove, "", $this->version));

        $this->isLatest = $this->remoteNumber === $this->localNumber;
        $this->isUpdateAvailable = $this->remoteNumber > $this->localNumber;
        $this->mustUpdate = $this->remoteNumber > $this->localNumber && $this->localNumber < "0.3.0.0";

        if ($this->isLatest)
            $this->status = "This is the latest.";

        if ($this->isUpdateAvailable) {
            $this->status = "New version is available: " . $arr[1];
            if ($this->localNumber < "0.3.0.5" && $this->remoteNumber >= "0.3.0.5")
                $this->status .= ".<br><span style='color: red;'>It is highly recommended to upgrade to this version.</span>";
        }

        if ($this->mustUpdate)
            $this->status = "Please, you must update JXcore to {$this->version}.";
    }
}

class JXcoreOSInfo {

    public $platform = null;
    public $arch = null;
    public $error = null;
    public $basename = null;
    public $custom = false;
    public $debug_log = false;

    private function determinePlatform() {
        $uname_s = strtolower(php_uname("s"));
        $platform = null;

        if ($uname_s == "darwin") {
            $platform = "osx";
        } else {
            if ($uname_s == "linux") {

                $procv = shell_exec('cat /proc/version');

                $distros = array(
                    "Red Hat" => "rh", // red hat/fedora/centos
                    "Ubuntu" => "ub", // ubuntu/mint
                    'SUSE' => 'suse',
                    'Debian' => 'deb',
                    'FreeBSD' => 'bsd'
                );

                foreach ($distros as $key => $val) {
                    $pos = stripos($procv, $key);
                    if ($pos !== false) {
                        $platform = $val;
                        break;
                    }
                }

                if ($platform === "bsd") {
                    $pos = stripos($procv, "9.");
                    if ($pos !== false)
                        $platform .= "9";
                    else
                        $platform .= "10";
                }
            }
        }
        return $platform;
    }

    function JXcoreOSInfo(){
        $tmpdir = pm_Context::getVarDir();
        $ini_array = parse_ini_file("$tmpdir/jxos.ini");

        if (isset($ini_array["platform"])) {
            $this->platform = $ini_array["platform"];
            $this->custom = true;
        } else {
           $this->platform = $this->determinePlatform();
           if (!$this->platform)
               $this->error = "Could not determine platform for this machine";
        }

        if (isset($ini_array["arch"])) {
            $this->arch = $ini_array["arch"];
            $this->custom = true;
        } else {
            // 32 or 64
            $this->arch = PHP_INT_SIZE * 8;
        }

        if (isset($ini_array["debug_log"])) {
            // "on"/"off" are internally translated into "1"/"" (empty string)
            $d = $ini_array["debug_log"];
            $this->debug_log = $d === "1" || $d === "on";
        }

        $this->basename = "jx_{$this->platform}{$this->arch}v8";
    }


    public static $info = null;

    public static function get() {
        if (!JXcoreOSInfo::$info)
            JXcoreOSInfo::$info = new JXcoreOSInfo();

        return JXcoreOSInfo::$info;
    }
}


class NPMModules {

    private $dir_base = null;
    private $dir_node_modules = null;

    private function check(&$form, &$list, &$view, $helper, $req, $domain) {
        $errStr = null;
        if (!$form) $errStr = 'form';
        if (!$list) $errStr = 'list';
        if (!$view) $errStr = 'view';
        if (!$helper) $errStr = 'helper';
        if (!$req) $errStr = 'req';

        if ($errStr) {
            StatusMessage::addWarning("The $errStr object is null");
            return false;
        }

        $logged = PanelClient::getLogged();
        if (!$logged->isAdmin && !$domain) {
            StatusMessage::addWarning("Wrong call for non-admin user.");
                return false;
        }

        return true;
    }

    function NPMModules(&$form, &$list, &$view, $helper, $req, $domain = null) {

        if (!$this->check($form, $list, $view, $helper, $req, $domain))
            return;

        $this->dir_base = $domain ? $domain->rootFolder : Modules_JxcoreSupport_Common::$dirNativeModules;
        $this->dir_node_modules = $this->dir_base . "node_modules/";
        $nameToInstall = trim($req->getParam("names"));
        $npmlog = $this->dir_base . "npm-debug.log";
        $npmout = $this->dir_base . "command.log";

        // if domain is not provided this is an admin anyway
        $sysUser = $domain ? $domain->sysUser : 'psaadm';
        $uri = "&user=$sysUser&dir={$this->dir_base}";

        if (file_exists($npmlog)) {

            $logCommand = trim($req->getParam("logCommand"));
            if ($logCommand === 'remove') {
                Modules_JxcoreSupport_Common::callService("modules", "removeLog{$uri}", "The npm-debug.log was successfully removed.", "Cannot remove npm-debug.log file.");
            } else {
                $view->logDiv = $this->getLogDiv($npmlog, $npmout);
//                $btnRemove = Modules_JxcoreSupport_Common::getSimpleButton("logCommand", "Remove", "remove", Modules_JxcoreSupport_Common::iconUrlDelete, null, $style);
            }
        }

        $form->addElement('hidden', "moduleAction", array('value' => 'nothing'));
        $form->addElement('hidden', 'logCommand', array('value' => 'nothing'));
        $form->addElement('hidden', 'remove', array('value' => 'nothing'));
        $form->addElement('hidden', 'update', array('value' => 'nothing'));

        $form->addElement('text', "names", array(
            'label' => 'Install new module',
            'value' => $nameToInstall,
            'validators' => array(new MyValid_Module()),
            'filters' => array('StringTrim'),
            'description' => 'Name or names of NPM module to install, e.g. "jxm server@5.0.3"',
            'escape' => false,
            'size' => 80
        ));

        $form->addElement('simpleText', 'path', array(
            'label' => 'Path',
            'escape' => false,
            'value' => $this->dir_node_modules
        ));

        $form->addControlButtons(array(
            'cancelLink' => null,
            'hideLegend' => true
        ));

        if ($req->isPost() && $form->isValid($req->getPost())) {

            $view->status->beforeRedirect = true;

            $moduleAction = trim($req->getParam("moduleAction"));
            $nameToRemove = trim($req->getParam("remove"));
            $nameToUpdate = trim($req->getParam("update"));

            if ($moduleAction === "check_for_updates") {
                Modules_JxcoreSupport_Common::callService("modules", "checkForUpdates$uri", "Check for update completed. See details on table list below.", "Error while checking for update.");
            } else
            if ($moduleAction === "update_all") {
                Modules_JxcoreSupport_Common::callService("modules", "update&name=_all_{$uri}", "Modules was successfully updated.", "There were some errors.");
            } else
            if ($moduleAction === "remove_all") {
                Modules_JxcoreSupport_Common::callService("modules", "remove&name=_all_{$uri}", "Modules was removed updated.", "There were some errors.");
            } else
            if ($nameToRemove !== "nothing") {
                Modules_JxcoreSupport_Common::callService("modules", "remove&name={$nameToRemove}{$uri}", "Module was successfully removed.", "Cannot remove module.");
            }
            else
            if ($nameToUpdate !== "nothing") {
                Modules_JxcoreSupport_Common::callService("modules", "update&name={$nameToUpdate}{$uri}", "Module was successfully updated.", "Cannot update module.");
            } else
            if ($nameToInstall) {
                $many = strpos($nameToInstall, " ") !== false;
                $str_ok = $many ? "Modules were successfully installed." :  "Module was successfully installed.";
                $str_err = "There were some errors. See below for details.";
                $ret = Modules_JxcoreSupport_Common::callService("modules", "install&name={$nameToInstall}{$uri}", $str_ok, $str_err, "silent");
                if ($ret)
                    StatusMessage::infoOrError($ret !== "OK", $str_ok, file_exists($npmlog) ? $str_err : $ret);
            }

            $helper->json(array('redirect' => $domain ? Modules_JxcoreSupport_Common::$urlDomainModules : Modules_JxcoreSupport_Common::$urlJXcoreModules));
        }

        $view->buttonsDisablingScript = Modules_JxcoreSupport_Common::getButtonsDisablingScript();
        $view->form = $form;
        $rows = $this->setData($list, $uri);

        if (count($rows)) {
            $btn = Modules_JxcoreSupport_Common::getSimpleButton("moduleAction", "Check for updates", "check_for_updates", "/theme/icons/16/plesk/question.png", null, "margin-left: 0px");
            $btn .= Modules_JxcoreSupport_Common::getSimpleButton("moduleAction", "Update all", "update_all", "/theme/icons/16/plesk/update.png", null, "margin-left: 0px");
            $btn .= Modules_JxcoreSupport_Common::getSimpleButton("moduleAction", "Remove all", "remove_all", "/theme/icons/16/plesk/delete.png", null, "margin-left: 0px", "Are you sure?");
            $view->form .= "<br>$btn";
        }

    }


    private function setData(&$list, $uri) {

        $data = array();
        $info = Modules_JxcoreSupport_Common::callService("modules", "info{$uri}", null, null, true);
        $iconUpdate = Modules_JxcoreSupport_Common::$plesk12 ? "" : "/theme/icons/16/plesk/update.png";
        $iconRemove = Modules_JxcoreSupport_Common::$plesk12 ? "" : "/theme/icons/16/plesk/delete.png";

        if ($info === "OK")
            return;

        if (strpos($info, '|') === false)
            StatusMessage::addError('Cannot read modules list: ' . $info);

        $modules = explode("||", $info);
        foreach($modules as $str) {
            $parsed = explode("|", $str);
            if (count($parsed) == 4) {
                $modules[$parsed[0] . "_version"] = $parsed[1];
                $modules[$parsed[0] . "_update_info"] = $parsed[2];
                $modules[$parsed[0] . "_description"] = $parsed[3];
            }
        }

        if (file_exists($this->dir_node_modules)) {
            $d = dir($this->dir_node_modules);
            while (false !== ($entry = $d->read())) {
                if (substr($entry, 0, 1) !== "." && is_dir($this->dir_node_modules . $entry)) {

                    $ver = isset($modules[$entry . "_version"]) ? $modules[$entry . "_version"] : "Cannot read version";
                    $desc = isset($modules[$entry . "_description"]) ? $modules[$entry . "_description"] : "Cannot read description";
                    $update = isset($modules[$entry . "_update_info"]) ? $modules[$entry . "_update_info"] : "Cannot read update info";

                    if (strpos($update, "#") === 0) {
                        $update = str_replace("#", "", $update);
                        $update = str_replace(" ", "&nbsp;", $update);
                        $update = Modules_JxcoreSupport_Common::getSimpleButton("update", $update, "$entry", $iconUpdate, null, "margin: 0px;");
                    }

                    $data[] = array(
                        'column-1' => Modules_JxcoreSupport_Common::iconON,
                        'column-2' => $entry,
                        'column-3' => $ver,
                        'column-4' => $desc,
                        'column-5' => Modules_JxcoreSupport_Common::getSimpleButton("remove", "Remove", "$entry", $iconRemove, null, "margin: 0px;"),
                        'column-6' => $update
                    );
                }
            }
            $d->close();
        }

        $list->setData($data);
        $columns = array(
            'column-1' => array(
                'title' => '',
                'noEscape' => true,
            ),
            'column-2' => array(
                'title' => 'Module name',
                'noEscape' => true,
                'searchable' => true
            ),
            'column-3' => array(
                'title' => 'module version',
                'noEscape' => true,
            ),
            'column-4' => array(
                'title' => 'Description',
                'noEscape' => true,
                'searchable' => true
            ),
            'column-5' => array(
                'title' => 'Remove',
                'noEscape' => true,
            ),
            'column-6' => array(
                'title' => 'Update',
                'noEscape' => true,
            )
        );

        $list->setColumns($columns);
        $list->setDataUrl(array('action' => 'listmodules-data'));

        return $data;
    }


    private function getLogDiv($npmlog, $npmout) {

        $html = '
<div id="sites-active-list" class="active-list active-list-collapsible">
    <div class="active-list-wrap">


        <div id="active-list-item-npm-debug-log" class="active-list-item active-list-item-collapsible active-list-item-collapsed">
            <div class="active-list-item-wrap">

                <div class="caption">
                    <div class="caption-wrap">
                        <div class="panel-heading-name" style="margin: 10px;">
                            <img alt="" src="/theme//icons/16/plesk/warning.png"
                                 style="margin-right: 5px; margin-top: -3px;">

                            <span>Expand to see </span><span class="jxOrangeRed">npm-debug.log</span>
                            <span style="margin-right: 20px;"> (#file_date#) </span>
                            <span>Failed command: </span><span class="jxOrangeRed">#cmd#</span>
                            <span style="float: right; margin-top: -5px;">#remove_btn#</span>
                        </div>

                        <div class="caption-control" id="caption-control-npm-debug-log"><span class="caption-control-wrap"><i></i></span></div>

                    </div>
                </div>

                <div class="active-list-details">
                    <div class="active-list-details-wrap">

                        <div class="panel-content">
                            <div class="panel-content-wrap">
                                <div class="stat-block">
                                    <div class="___stat-name">

                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="panel-content">
                            <div class="panel-content-wrap">
                                #div#
                            </div>
                        </div>

                        <div class="panel-content">
                            <div class="panel-content-wrap">
                                #remove_btn#
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>
        ';

        $log = '';
        $hr = '#HR#';
        // adding cmd output log before npm-debug.log
        if (file_exists($npmout))
            $log .= "#INFO#log file: {$npmout}\n" . trim(file_get_contents($npmout)) . "\n$hr";

        $log .= "#INFO#log file: {$npmlog}\n\n" .trim(file_get_contents($npmlog));

        $log = htmlspecialchars($log);
        $arr = explode("\n", $log);
        $cmd = "unknown";

        foreach($arr as $key => $line) {
            $errpos = strpos($line, ' error ');
            if ($errpos >0 && $errpos <5) $arr[$key] = StatusMessage::orange($line);

            if (strpos($line, 'npm ERR! ') !== false) $arr[$key] = StatusMessage::orange($line);
            if (strpos($line, ' info ') !== false) $arr[$key] = StatusMessage::green($line);
            if (strpos($line, ' warn ') !== false) $arr[$key] = Modules_JxcoreSupport_Common::getHTMLTag("span", $line, null, "color:magenta");
            if (strpos($line, ' verbose ') !== false) $arr[$key] = Modules_JxcoreSupport_Common::getHTMLTag("span", $line, null, "color:blue");
            if (strpos($line, ' silly ') !== false) $arr[$key] = Modules_JxcoreSupport_Common::getHTMLTag("span", $line, null, "color:darkgray");

            if (strpos($line, 'npm JXcore ') !== false || strpos($line, 'gyp JXcore ') !== false)
                $arr[$key] = Modules_JxcoreSupport_Common::getHTMLTag("span", $line, null, "color:dodgerblue");

            if (strpos($line, "#INFO#command:") === 0)
                $cmd = trim(str_replace('#INFO#command: ', '', $line));
        }

        $log = implode('<br>', $arr);
        $div = '
            <div id="div_npmdebug" class="panels-group-wrap"
                 style="height: 300px; overflow: scroll; margin-top: 10px; margin-bottom: 10px; padding: 0px">
                #log#
            </div>
        ';
        $div = str_replace("#log#", $log, $div);

        $bname = basename($npmlog);
        $bnameID = str_replace(array( '.', '_', ' '), '-', $bname);

        $btnRemove = Modules_JxcoreSupport_Common::getSimpleButton("logCommand", "Remove log files", "remove" , Modules_JxcoreSupport_Common::iconUrlDelete, null, "margin: 0px;", "Are you sure? The file will be permanently removed.");
        $html = str_replace("#remove_btn#", $btnRemove, $html);
        $html = str_replace("#div#", $div, $html);
        $html = str_replace("#file_date#", date ("F d Y H:i:s", filemtime($npmlog)), $html);
        $html = str_replace("#cmd#", $cmd , $html);
        $html = str_replace("#basename#", $bname, $html);
        $html = str_replace("#ID#", $bnameID, $html);
        $html = str_replace("#INFO#", '<img style="margin-right: 5px; margin-top: -3px;" src="/theme//icons/16/plesk/info.png" alt="">', $html);
        $html = str_replace($hr, '<hr>', $html);
        return $html;
    }
}


class MyValid_Module extends Zend_Validate_Abstract
{
    const MSG_CANNOTCONTAIN = 'msgCannotContain';
    const MSG_CANNOTSTART = 'msgCannotStart';
    const MSG_ISADIR = 'msgIsaDir';

    public $cannotContain = 0;
    public $cannotStart = 0;

    protected $_messageVariables = array(
        'cannotContain' => 'cannotContain',
        'cannotStart' => 'cannotStart'
    );

    protected $_messageTemplates = array(
        self::MSG_CANNOTCONTAIN => "The file name cannot contain '%cannotContain%'.",
        self::MSG_CANNOTSTART => "The file name cannot start with a '%cannotStart%'.",
        self::MSG_ISADIR => "Provided path exists and is a directory."
    );

    public function isValid($value)
    {
        $this->_setValue($value);

        $forbidden = array( './', '/.', '.\\', '\\.'  );
        foreach($forbidden as $str) {
            if (strpos($value, $str) !== false) {
                $this->cannotContain = $str;
                $this->_error(self::MSG_CANNOTCONTAIN);
                return false;
            }
        }

        $forbidden = array( '/', '\\' );
        foreach($forbidden as $str) {
            if (substr($value, 0, strlen($str)) === $str) {
                $this->cannotStart = $str;
                $this->_error(self::MSG_CANNOTSTART);
                return false;
            }
        }

        return true;
    }
}