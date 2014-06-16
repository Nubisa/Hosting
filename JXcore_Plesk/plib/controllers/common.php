<?php


class Common
{

    public static $urlListDomains = "";
    public static $urlMonitor = "";
    public static $urlMonitorLog = "";
    public static $urlJXcoreConfig = "";
    public static $urlDomainConfig = "";
    public static $urlDomainAppLog = "";

    public static $firstRun = false;

    public static $jxv = null;
    public static $jxpath = null;

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
    const sidDomainJXcoreAppPath = "jx_domain_app_path";
    const sidDomainJXcoreAppMaxCPULimit = "jx_domain_app_max_cpu";
    const sidDomainJXcoreAppMaxMemLimit = "jx_domain_app_max_mem";
    const sidDomainJXcoreAppAllowSpawnChild = "jx_domain_app_child";

    const sidJXcoreMinimumPortNumber = "jx_app_min_port";
    const sidJXcoreMaximumPortNumber = "jx_app_max_port";

    const sidMonitorStartScheduledByCron = "jx_monitor_start_scheduled_by_cron";

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
    private static $status = null;

    private static $buttonsDisabling = [];

    private static $domains = [];
    private static $domainsFetched = false;

    function Common($controller, $status = null)
    {
        self::$controller = $controller;
        self::$status = $status;

        self::refreshValues();
    }

    public static function getDomain($id) {
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
            $sql = "SELECT d.*, h.www_root FROM domains d left outer join hosting h on d.id = h.dom_id where htype = 'vrt_hst' order by id ASC";

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
        self::$urlListDomains = pm_Context::getBaseUrl() . "index.php/index/listdomains";
//        self::$urlMonitor = "http://localhost:17777/json?silent=true";
        self::$urlMonitor = "http://localhost:17777/json";
        self::$urlMonitorLog = "http://localhost:17777/logs";
        self::$urlJXcoreConfig = pm_Context::getBaseUrl() . "index.php/index/jxcore";
        self::$urlDomainConfig = pm_Context::getBaseUrl() . "index.php/domain/config";
        self::$urlDomainAppLog = pm_Context::getBaseUrl() . "index.php/domain/log";

        self::$firstRun = !pm_Settings::get(self::sidFirstRun); // if empty, that it is first run

        self::$jxv = pm_Settings::get(self::sidJXversion);
        self::$jxpath = pm_Settings::get(self::sidJXpath);

        self::$startupBatchPath = pm_Context::getVarDir() . "jxcore-for-plesk-startup.sh";

//        $client = new PanelClient();
//        $controller->view->userStatusBar = $client->statusBar;

        $client = pm_Session::getClient();
        self::$isAdmin = $client->isAdmin();


        $v = pm_Settings::get(self::sidJXcoreMinimumPortNumber);
        self::$minApplicationPort = $v ? $v : self::minApplicationPort_default;

        $v = pm_Settings::get(self::sidJXcoreMaximumPortNumber);
        self::$maxApplicationPort = $v ? $v : self::maxApplicationPort_default;
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


    public static function setJXdata($version, $path)
    {

        if (file_exists($path)) {
            $dir = dirname($path) . "/";

            $cfg = '{
                       "monitor" :
                       {
                           "log_path" : "' . $dir . 'jx_monitor_[WEEKOFYEAR]_[YEAR].log"
                       }
                     }';
            file_put_contents($dir . "jx.config", $cfg);
        }

        pm_Settings::set(self::sidJXversion, $version);
        pm_Settings::set(self::sidJXpath, $path);

        self::$jxv = null;
        self::$jxpath = null;

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


    public function clearPorts()
    {
        for ($a = 10000; $a < 10010; $a++) {
            pm_Settings::set($this->sidDomainJXcoreAppPort . $a, "");
        }
    }

    /**
     * Returns array of ports already taken by JXcore applications assigned to domains
     * @param $domainId - if this param provided, the result will not contain port of applications for this domain
     * @return array
     */
    public static function getTakenAppPorts($domainId = null, $clientId = null)
    {
        $rows = self::$domains;

        $portsTaken = [];
        foreach ($rows as $id=>$domain) {
            if ($domainId && $id == $domainId) continue;
            if ($clientId && $domain->row['cl_id'] != $clientId) continue;
            // array of strings
            $port = pm_Settings::get(self::sidDomainJXcoreAppPort . $domain->row['id']);
            if ($port && ctype_digit($port))
                $portsTaken[] = $port;
        }

        return $portsTaken;
    }


    public static function getFreePorts($domainId)
    {
        $takenPorts = self::getTakenAppPorts($domainId);
        return self::getFreePortsFromTaken($takenPorts);
    }

    public static function getFreePortsFromTaken($takenPorts)
    {
        $ret = [];
        for ($a = self::$minApplicationPort; $a <= self::$maxApplicationPort; $a++) {
            if (!in_array($a, $takenPorts)) $ret[] = $a;
            if (count($ret) > 5) break;
        }
        return $ret;
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
                "cd " . dirname(Common::$jxpath),
                "./jx monitor start"
            ];

            foreach (self::$domains as $id=>$domain) {
                $domain = Common::getDomain($id);
                $client = new PanelClient($domain->row['cl_id']);
//                self::$status->addMessage("info", "user clid {$row['cl_id']}, sysUser {$client->sysUser}");

                $enabled = $domain->JXcoreSupportEnabled();
                $path = $domain->getAppPath(true);

                $out = null;
                $spawner = $domain->getSpawnerPath($out);
                if ($spawner === false) {
                    self::$status->addMessage("error", $out);
                    continue;
                }
                if ($domain->writeSpawnerOptions($out, $spawner) === false) {
                    self::$status->addMessage("error", $out);
                    continue;
                }
//                self::$status->addMessage("info", $out);

                $opt = $domain->getSpawnerParams(array("user" => $client->sysUser, "log" => $domain->appLogPath, "file" => $path));
//                self::$status->addMessage("info", $opt);
//                $cmd = Common::$jxpath . " {$spawner} -u {$client->sysUser} -log '" . $domain->appLogPath . "' -opt {$opt} $path";
                $cmd = Common::$jxpath . " {$spawner} -opt {$opt}";

                if ($enabled) {
                    $commands[] = '';
                    $commands[] = "if [ -e {$path} ]; then";
                    $commands[] = $cmd;
                    $commands[] = "fi";
                }

                // stopping or launching application, if provided with the argument $domainId
                if ($domainId && intval($domainId) === $id) {

                    $json = null;
                    $monitorRunning = Common::getURL(Common::$urlMonitor, $json);
                    $appRunning = strpos($json, $path) !== false;

                    $msg = "";
                    if (!$enabled && $appRunning) {
                        $cmd = Common::$jxpath . " monitor kill {$spawner} 2>&1";
                        $msg = " Cannot stop the application: ";
                    } else if ($enabled && !$appRunning) {
                        if (!$monitorRunning) {
                            // no point to run an application now, if monitor is not running
                            $cmd = "";
                            $msg = "Cannot start the application: ";
                        } else {
                            // leave $cmd intact
                        }
                    } else {
                        $cmd = null;
                    }

                    if ($cmd) {
                        @exec($cmd, $out, $ret);
                        if ($ret && $ret != 77) {
                            self::$status->addMessage($ret ? "error" : "info", $msg . join("\n", $out) . ". Exit code: $ret");
                        }
                        // let the monitor respawn app as root
                        sleep(3);
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

//        self::$status->addMessage("info", "Previous roots crontab: $contents");

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

//        $check = shell_exec("crontab -l");
//        $pos = strpos($check, $cmd);
//        if ($monitorEnabled) {
//            if ($pos === false) $this->_status->addMessage("error", "Could not add cron job. Monitor may not start on reboot.");
//        } else {
//            if ($pos !== false) $this->_status->addMessage("error", "Could remove cron job. Monitor may start again on reboot.");
//        }

    }


    /**
     * Modifying crontab for immediate execution (next minute)
     * @return int - number of seconds until cron job should be started
     */
    public static function updateCronImmediate($remove)
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
        $cmd = $timing . " * " . Common::$startupBatchPath;
        $contents = Common::saveBlockToText($contents, "JXcore-immediate", $remove ? "" : $cmd, null);

        // a NewLine characted was needed on Nubisa's production server
        $contents = trim($contents) . "\n";
        file_put_contents($tmpfile, $contents);
        @exec("$binary set root $tmpfile 2>&1", $out, $ret);
        if ($ret) {
            self::$status->addMessage("error", "Cannot set immediate crontab job (arg = {$remove}): " . join("\n", $out) . $cmd);
            return;
        }

        @unlink($tmpfile);

        $timestamp = mktime($hour, $nextMinute, 0, $parsed[3], $parsed[2]);
        pm_Settings::set(self::sidMonitorStartScheduledByCron, $remove ? null : $timestamp);
    }

    public static function checkCronScheduleStatus($addMessage)
    {
        $timestamp = pm_Settings::get(self::sidMonitorStartScheduledByCron);
        if (!$timestamp) return null;

        $now = mktime();
        $diff = $timestamp - $now;

        if ($diff > 0) {

            if ($addMessage) {
                // $waitSeconds = 10;
                $waitSeconds = $diff + 10;
                $refresh = '<script type="text/javascript">var cnt = ' . $waitSeconds . '; var loop = function() { cnt--; document.getElementById("jx_refresh_count").innerHTML = cnt;  if (cnt<0) document.location.reload(); else setTimeout(loop, 1000); }; loop() ;</script>';
                //self::$status->addMessage("info", "timestamp = $timestamp, now = $now, diff = $diff");
                $str = "Monitor should be launched in approx {$diff} seconds. Page will refresh after <span id='jx_refresh_count' name='jx_refresh_count'>5</span> seconds." . $refresh;
                self::$status->addMessage('info', $str);
            }
        } else {

            $monitorRunning = Common::getURL(Common::$urlMonitor, $json);
            if ($monitorRunning)
                self::updateCronImmediate(true);
            else {
                if ($diff < -15) {
                    // error - monitor did not start after 15 secs
                    self::updateCronImmediate(true);
                    self::$status->addMessage("error", "Could not start the JXcore monitor with crontab.");
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

        $relFile = str_replace($mgr->getFilePath(), "", $file);

        if ($domain->JXcoreSupportEnabled()) {
            $htaccess = [];
            $htaccess[] = 'RewriteEngine On';

            if ($domain->getAppLogWebAccess()) {
                $htaccess[] = 'RewriteCond %{REQUEST_URI} !/' . DomainInfo::appLogBasename;
            }

            $htaccess[] = 'RewriteCond %{SERVER_PORT} 80';
            $htaccess[] = 'RewriteRule ^(.*)$ http://0.0.0.0:' . $domain->getAppPort() . '/$1 [P]';
        } else {
            $htaccess = [];
        }

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
//            self::$status->addMessage("warning", $fname);
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
}


class DomainInfo
{
    public $id = 0;
    public $rootFolder = "";
    public $appLogPath = "";
    public $name = "";

    private $domain = null;
    private $fileManager = null;

    const appLogBasename = "JX_log.html";
    const appPath_default = "index.js";

    public $row = null;

    public $log = [];

    public static function getFromRow($row) {
        $domain = new DomainInfo($row['id']);
        $domain->rootFolder = $row['www_root'] . "/";
        $domain->appLogPath = $domain->rootFolder . self::appLogBasename;
        $domain->row = $row;
        return $domain;
    }

    private function DomainInfo($id)
    {
        $this->domain = new pm_Domain($id);
        $this->fileManager = new pm_FileManager($id);
        $this->id = $id;
        $this->name = $this->domain->getName();
    }

//    /**
//     * Returns root directory for domain, e.g. /var/www/vhosts/local.domain
//     * @param $domainId
//     * @return null|string
//     */
//    private function getDomainRootFolder()
//    {
//        // domain
//        $path2 = $this->fileManager->getFilePath(".");
//        // subdomain
//        $path1 =  $path2. $this->name . "/";
//
//        if (is_dir($path1))
//            return $path1;
//        else
//            if (is_dir($path2))
//                return $path2;
//            else
//                return null;
//    }

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
                    $port .= "<br><span style='color: red'>Port out of range.</span>";
                }
                return Common::getIcon($appRunning, "Running on port " . $port, "Not running");
            } else {
                $ret = Common::checkCronScheduleStatus(false);
                $str = ($ret && $ret > 0) ? "<br>Monitor is starting in $ret secs." : "Monitor offline.";
                return Common::getIcon(false, "", "Not running. $str");
            }
        } else {
            return "<span style=\"color: red;\">No file:</span> " . $this->getAppPathOrDefault();
        }
    }

    public function JXcoreSupportEnabled()
    {
        return pm_Settings::get(Common::sidDomainJXcoreEnabled . $this->id);
    }

    public function getAppPort()
    {
        return pm_Settings::get(Common::sidDomainJXcoreAppPort . $this->id);
    }

    public function getAppPortOrDefault($updateIfEmpty = false)
    {
        $port = pm_Settings::get(Common::sidDomainJXcoreAppPort . $this->id);
        if (!$port || trim($port) === '') {
            $port = Common::getFreePorts($this->id)[0];
            if ($updateIfEmpty)
                $this->setAppPort($port);
        }
        return $port;
    }

    public function setAppPort($port)
    {
        pm_Settings::set(Common::sidDomainJXcoreAppPort . $this->id, $port);
    }

    public function getAppPortStatus()
    {
        $port = $this->getAppPort();
        if (!$port)
            return "<span style='color: red'>Port is not defined.</span>";

        if ($port < Common::$minApplicationPort || $port > Common::$maxApplicationPort)
            return "<br><span style='color: red'>Port out of range.</span>";
        else
            return $port;
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

        return trim($str) != "" ? "<span style='color: red'>$str</span>" : true;
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


    public function writeSpawnerOptions(&$out, $spawnerPath)
    {
        return true;
        $json = $this->getSpawnerOptions();

        $path = $this->getAppPath(true);
        $configFile = $path . ".config.template";
        $out = $json;
        if (trim($json)==="{}") {
            if (file_exists($configFile)) {
                unlink($configFile);
            }
            return !file_exists($configFile);
        } else {

            if (file_put_contents($configFile, $json) === false){
                $out = "Cannot save spawner options.";
                return false;
            }
//            var_dump($configFile);
            // only for psadm (and root)
            if (chmod($configFile, 0600) === false) {
                $out = "Cannot set spawner's option file permission.";
                unlink($configFile);
                return false;
            }
            $out = "$configFile, exists ? " . file_exists($configFile);
            return file_exists($configFile);
        }
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
            "port" => Common::sidDomainJXcoreAppPort,
            "cpu" => Common::sidDomainJXcoreAppMaxCPULimit,
            "mem" => Common::sidDomainJXcoreAppMaxMemLimit,
            "child" => Common::sidDomainJXcoreAppAllowSpawnChild);

        foreach ($params as $key => $sid) {
            $val = pm_Settings::get($sid . $this->id);
            if (trim($val) != "") {
                $arr[] = "\"{$key}\" : \"{$val}\"";
            }
        }

        foreach ($additionalParams as $key => $val) {
            $arr[] = "\"{$key}\" : \"{$val}\"";
        }

        $json = "'{ " . join(", ", $arr) . "}'";
        return $json;
    }

    /**
     * Returns restriction options for application of this domain
     * @return string
     */
    private function getSpawnerOptions()
    {
        $arr = [];

        $params = array(
            "port" => Common::sidDomainJXcoreAppPort,
            "cpu" => Common::sidDomainJXcoreAppMaxCPULimit,
            "mem" => Common::sidDomainJXcoreAppMaxMemLimit,
            "child" => Common::sidDomainJXcoreAppAllowSpawnChild);

        foreach ($params as $key => $sid) {
            $val = pm_Settings::get($sid . $this->id);
            if (trim($val) != "") {
                $arr[] = "\t\"{$key}\" : \"{$val}\"";
            }
        }

        $json = "{\n" . join(",\n", $arr) . "\n}";
//        $base64 = base64_encode($json);
        return $json;
    }


    public function clearLogFile()
    {
        if (file_exists($this->appLogPath)) {
            $rel = str_replace($this->fileManager->getFilePath(), "", $this->appLogPath);
            $this->fileManager->filePutContents($rel, "");
            $newSize = filesize($this->appLogPath);

            return $newSize === 0;
        }
        return true;
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
        $sql = "SELECT
            sys.login as sysLogin, cli.login as cliLogin, cli.type as cliType
            FROM clients cli
            join smb_users smb on smb.login = cli.login
            join sys_users sys on sys.id = smb.id
            where cli.id = $clientId";

        $statement = $dbAdapter->query($sql);

        $row = $statement->fetch();

        $this->sysUser = $row["sysLogin"];
        $this->panelLogin = $row["cliLogin"];
        $this->type = $row["cliType"];
        $this->statusBar = "Username: <b>{$this->panelLogin}</b>. Account type: <b>{$this->type}</b>. Whoami: <b>{$this->whoami}</b>. System user: <b>{$this->sysUser}</b><hr>";
    }
}