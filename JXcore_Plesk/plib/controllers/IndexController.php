<?php

class IndexController extends pm_Controller_Action
{

    public function init()
    {
        parent::init();

        // Init title for all actions
        $this->view->pageTitle = 'JXcore support for domains';

        require_once("common.php");
        $this->common = new Common($this, $this->_status);

        if (Common::$isAdmin) {

            // Init tabs for all actions
            $this->view->tabs = array(
                array(
                    'title' => 'JXcore Configuration',
                    'action' => 'jxcore',
                ),
                array(
                    'title' => 'Domains',
                    'action' => 'listdomains',
                ),
                array(
                    'title' => 'NPM Modules',
                    'action' => 'modules',
                )
            );

            $this->view->tabs[] = array(
                'title' => 'Monitor log',
                'action' => 'log'
            );
        }
    }

    /**
     * When extension is browsed for the first tim in the panel - the navigation goes
     * to init page for JXcore installation.
     * Also for pages accessible only for admin - navigation goes to main jx config tab.
     * @param bool $onlyAdminIsAllowed
     * @return bool
     */
    private function redirect($onlyAdminIsAllowed = false)
    {
        if (Common::$firstRun) {
            $tab = Common::$isAdmin ? 'init' : 'initNonAdmin';
            $this->_forward($tab);
            return true;
        }

        if ($onlyAdminIsAllowed && !Common::$isAdmin) {
            $this->_forward('listdomains');
            return true;
        }
        return false;
    }


    /**
     * Called when non admin user browses extension in panel, before admin does.
     */
    public function initnonadminAction()
    {
        if (Common::$isAdmin) return;

        // Init form here
        $form = new pm_Form_Simple();

        $form->addElement('hidden', 'screen', array(
            'value' => 'afterIntro',
        ));

        $form->addControlButtons(array(
            'cancelLink' => pm_Context::getModulesListUrl(),
            'hideLegend' => true
        ));

        if ($this->getRequest()->isPost() && $form->isValid($this->getRequest()->getPost())) {
            $this->_helper->json(array('redirect' => pm_Context::getBaseUrl()));
        }

        $this->view->form = $form;
    }


    /**
     * Displays JXcore monitor log in panel's tab.
     */
    public function logAction()
    {
        if ($this->redirect(true)) return;

        $log = "empty";
        Common::getURL(Common::$urlMonitorLog, $log);
        $this->view->log = str_replace("\n", "<br>", $log);
    }

    /*
     * Main page of the module.
     */
    public function indexAction()
    {
        if ($this->redirect()) return;
        $this->_forward('jxcore');
    }

    /**
     * Domains list
     */
    public function listdomainsAction()
    {
        if ($this->redirect()) return;
        $this->view->list = $this->_getDomains();
        if ($this->view->list) {
            $this->view->list->setDataUrl(array('action' => 'listdomains-data'));
        }
        Common::check();
    }

    /**
     * Action for domains list - when user clicks on table's column header.
     */
    public function listdomainsDataAction()
    {
        $this->listdomainsAction();

        // Json data from pm_View_List_Simple
        $this->_helper->json($this->view->list->fetchData());
        Common::check();
    }


    /**
     * The initialization form display right after installation of the extension.
     */
    public function initAction()
    {
        if (!Common::$isAdmin) {
            $this->_forward("initNonAdmin");
            return;
        };

        // Init form here
        $form = new pm_Form_Simple();

        $form->addElement('hidden', 'screen', array(
            'value' => 'afterIntro',
        ));

        $form->addControlButtons(array(
            'cancelLink' => pm_Context::getModulesListUrl(),
            'hideLegend' => true
        ));

        if ($this->getRequest()->isPost() && $form->isValid($this->getRequest()->getPost())) {

            if (!Common::isJXValid()) {
                $out = null;

                Common::enableHttpProxy();

                $ok = $this->download_JXcore($out);
                $this->_status->addMessage($ok ? 'info' : 'error', $out);

                $this->common->refreshValues();
                Common::updateBatchAndCron();
                $this->monitorStartStop("start");
            }

            pm_Settings::set(Common::sidFirstRun, "no");
            $this->_helper->json(array('redirect' => pm_Context::getBaseUrl()));
        }

        $this->view->form = $form;
    }

    /**
     * JXcore configuration tab.
     */
    public function jxcoreAction()
    {
        if ($this->redirect(true)) return;

        $sidMonitor = 'jx_monitor_start_stop';
        $sidJXcore = 'jx_binary_install_uninstall';
        $req = $this->getRequest();

        $json = null;
        $monitorRunning = Common::getURL(Common::$urlMonitor, $json);

        // Init form here
        $form = new pm_Form_Simple();

        $jxvalid = Common::isJXValid();

        if (Common::$isAdmin) {

            // JXcore install uninstall
            $form->addElement('hidden', $sidJXcore, array(
                'value' => 'nothing',
            ));

            $form->addElement('simpleText', 'jxversion', array(
                'label' => 'JXcore version',
                'escape' => false,
                'value' => Common::getIcon(Common::$jxv, "Installed " . Common::$jxv, 'Not installed')
            ));

            if (Common::$jxv) {
                $form->addElement('simpleText', 'jxpath', array(
                    'label' => 'JXcore path',
                    'escape' => false,
                    'value' => $jxvalid ? Common::$jxpath : "<span style=\"color: red;\">Could not find JXcore executable file (path = '" . Common::$jxpath . "'). Try reinstalling JXcore.</span>",
                ));
            }

            if (Common::$jxv) {
                $buttons =
                    Common::getSimpleButton($sidJXcore, 'Reinstall', "install", "/theme/icons/16/plesk/show-all.png", null, "margin-left: 0;") .
                    Common::getSimpleButton($sidJXcore, 'Uninstall', "uninstall", "/theme/icons/16/plesk/delete.png");
            } else {
                $buttons = Common::getSimpleButton($sidJXcore, 'Install', "install", "/theme/icons/16/plesk/upload-files.png", null, "margin-left: 0;");
            }

            $form->addElement('simpleText', 'reinstall', array(
                'label' => '',
                'escape' => false,
                'value' => $buttons,
                'description' => Common::$jxv ?
                        "When reinstalling or uninstalling, all currently running applications will be terminated!" :
                        "JXcore version specific for this platform will be downloaded and installed."
            ));


            if ($jxvalid) {

                Common::addHr($form);

                // monitor start / stop button
                $form->addElement('hidden', $sidMonitor, array(
                    'value' => 'nothing',
                ));

                $cronAction = pm_Settings::get(Common::sidMonitorStartScheduledByCronAction);
                $ret = Common::checkCronScheduleStatus(false);
                if ($ret && $ret > 0) {
                    if ($cronAction == "start")
                        $btn = Common::getIcon($monitorRunning, "Online", "Offline") . "<div> Monitor is scheduled to be launched in $ret seconds.</div>";
                    else  if ($cronAction == "stop")
                         $btn = Common::getIcon($monitorRunning, "Online", "Offline") . "<div> Monitor is scheduled to be stopped in $ret seconds.</div>";
                } else {
                    $btn = Common::getButtonStartStop($monitorRunning, $sidMonitor, ["Online", "Start"], ["Offline", "Stop"]);
                }


                $form->addElement('simpleText', 'status', array(
                    'label' => 'JXcore Monitor status',
                    'escape' => false,
                    'value' => $btn,
                    'description' => $monitorRunning ? "All monitored applications will be terminated!" : "All applications with enabled JXcore support will also be launched."
                ));

//                $form->addElement('checkbox', Common::sidMonitorEnabled, array(
//                    'label' => "Launch JXcore Monitor at system`s startup.",
//                    'value' => pm_Settings::get(Common::sidMonitorEnabled),
//                ));

                Common::addHr($form);


                $form->addElement('text', Common::sidJXcoreMinimumPortNumber, array(
                    'label' => 'Minimum app port number',
                    'value' => Common::$minApplicationPort,
                    'required' => true,
                    'validators' => array(
                        'Int',
                        array("LessThan", true, array('max' => $req->getParam(Common::sidJXcoreMaximumPortNumber))),
                        array("Between", true, array('min' => Common::minApplicationPort_default, 'max' => Common::maxApplicationPort_default))
                    ),
                    'description' => '',
                    'escape' => false
                ));

                $form->addElement('text', Common::sidJXcoreMaximumPortNumber, array(
                    'label' => 'Maximum app port number',
                    'value' => Common::$maxApplicationPort,
                    'required' => true,
                    'validators' => array('Int',
                        array("GreaterThan", true, array('min' => $req->getParam(Common::sidJXcoreMinimumPortNumber))),
                        array("Between", true, array('min' => Common::minApplicationPort_default, 'max' => Common::maxApplicationPort_default))
                    ),
                    'description' => '',
                    'escape' => false
                ));

                $form->addControlButtons(array(
                    'cancelLink' => pm_Context::getModulesListUrl(),
                ));
            }

            if ($req->isPost() && $form->isValid($req->getPost())) {

                $monitorAction = in_array($req->getParam($sidMonitor), ["start", "stop"]);
                $installAction = in_array($req->getParam($sidJXcore), ["install", "uninstall"]);

                if ($monitorAction) {
                    $this->monitorStartStop($req->getParam($sidMonitor));
                } else if ($installAction) {
                    $this->JXcoreInstallUninstall($req->getParam($sidJXcore));
                } else {
//                    $params = [Common::sidMonitorEnabled, Common::sidJXcoreMinimumPortNumber, Common::sidJXcoreMaximumPortNumber];
                    $params = [Common::sidJXcoreMinimumPortNumber, Common::sidJXcoreMaximumPortNumber];
                    foreach ($params as $param) {
                        pm_Settings::set($param, $form->getValue($param));
                    }

                    Common::updateBatchAndCron(null);
                    Common::refreshValues();
                    $this->_status->addMessage('info', 'Data was successfully saved.');
                }
                $this->_helper->json(array('redirect' => Common::$urlJXcoreConfig));
            }
        }

        $this->view->buttonsDisablingScript = Common::getButtonsDisablingScript();
        $this->view->form = $form;
        Common::check();
    }



    public function modulesAction()
    {
        if ($this->redirect(true)) return;

        $node_modules = Common::$dirNativeModules . "node_modules/";

        $installed_modules = [];
        if (file_exists($node_modules)) {
            $d = dir($node_modules);
            while (false !== ($entry = $d->read())) {
                $installed_modules[] = $entry;
            }
            $d->close();
        }


        $form = new pm_Form_Simple();

        $form->addElement('simpleText', "installedModules", array(
            'label' => 'InstalledModules',
            'value' => count($installed_modules) ? join("<br>", $installed_modules) : "None",
            'escape' => false
        ));

        $names = $this->getRequest()->getParam("names");
        $form->addElement('text', "names", array(
            'label' => 'Install new module',
            'value' => $names,
            'validators' => array(

            ),
            //'description' => 'Name, or names (comma separated) of NPM modules to install.',
            'description' => 'Name of NPM module to install.',
            'escape' => false
        ));

        $form->addControlButtons(array(
            'cancelLink' => null,
            'hideLegend' => true
        ));

        if ($this->getRequest()->isPost() && $form->isValid($this->getRequest()->getPost())) {

            $arr = explode(",", $names);
            $str = "";
            foreach($arr as $name) {
                $name = trim($name);
                $str .= "$name|";
            }

//            $cmd =

            $this->_status->addMessage('info', $str);

//            $actionClearValue = $this->getRequest()->getParam($sidClearLog);
//            $actionClearPressed = $actionClearValue === "clear";
//
//            $val = $form->getValue($sidLastLinesCount);
//
//            if ($actionClearPressed) {
//                $ret = $this->domain->clearLogFile();
//                if ($ret === false) {
//                    $this->_status->addMessage('error', 'Could not clear the log file.');
//                } else {
//                    $this->_status->addMessage('info', 'Log cleared.');
//                }
//            } else {
//                pm_Settings::set($sidLastLinesCount . $this->ID, $val);
//            }
            $this->_helper->json(array('redirect' => Common::$urlJXcoreModules));
        }

        $this->view->form = $form;
        Common::check();
    }


    private function _getDomains()
    {
        if (!Common::$jxv) {
            $this->_status->addMessage("error", "JXcore is not installed");
            return;
        }

        function url($url, $str)
        {
            return '<a href="' . $url . '">' . $str . '</a>';
        }

        $client = pm_Session::getClient();
        $clid = $client->getId();

        $data = array();
        $ids = Common::getDomainsIDs();
        $cnt = 1;
        foreach ($ids as $id) {
            $domain = Common::getDomain($id);

            if (!Common::$isAdmin && $clid != $domain->row['cl_id']) {
                continue;
            }

            $domain->getAppPathOrDefault(false, true);
            $domain->getAppPortOrDefault(true, false);
            $domain->getAppPortOrDefault(true, true);

            $status = $domain->JXcoreSupportEnabled();
            if ($status != 1) $status = false; else $status = true;

            $baseUrl = pm_Context::getBaseUrl() . 'index.php/domain/';
            $switchUrl = Common::$urlListDomains . "/id/$id";
            $editUrl = $baseUrl . 'config/id/' . $id;

            // switching JXcore support (enabled/disabled) from listdomains
            $id_GET = $this->getRequest()->getParam('id');
            if ($id_GET == $id) {
                pm_Settings::set(Common::sidDomainJXcoreEnabled . $id, !$status);
                $status = !$status;

                // when enabling, we apply default values (if empty)
//                if ($status) {
//                    $domain->getAppPathOrDefault(false, true);
//                    $domain->getAppPortOrDefault(true);
//                }

                Common::updateBatchAndCron($id);

                $ret = Common::updatehtaccess($id);
                if ($ret !== true) $this->_status->addMessage('error', $ret);
            }

            $ret = $domain->canEnable();
            $switch = //$ret === true ?
//                Common::getButtonStartStop($status, "id", ["Enabled", "Enable"], ["Disabled", "Disable"], $switchUrl) :
//                Common::getIcon($status, "Enabled", "Disabled");

           // $cl = new PanelClient($domain->row['cl_id']);
//            $sysUser = $cl->sysUser;

            $data[] = array(
                'column-1' => $id,
                'column-2' => url($editUrl, $domain->row['displayName']),
                'column-3' => url($editUrl, $domain->row['cr_date']),
                'column-4' => Common::getIcon($status, "Enabled", "Disabled"),
                'column-5' => $domain->getAppPortStatus(null, false),
                'column-6' =>  $domain->getAppStatus(),
                'column-7' => url($editUrl, $domain->sysUser),
                'column-8' => Common::getSimpleButton("edit", "Manage", null, null, $editUrl),
            );
        }

        $list = new pm_View_List_Simple($this->view, $this->_request);
        $list->setData($data);
        $columns = array(
            'column-1' => array(
                'title' => 'Id',
                'noEscape' => true,
            ),
            'column-2' => array(
                'title' => 'Domain Name',
                'noEscape' => true,
            ),
            'column-3' => array(
                'title' => 'Creation date',
                'noEscape' => true,
            ),
            'column-4' => array(
                'title' => 'JXcore support',
                'noEscape' => true,
            ),
            'column-5' => array(
                'title' => 'TCP / TCPS',
                'noEscape' => true,
            ),
            'column-6' => array(
                'title' => 'Application status',
                'noEscape' => true,
            ),
            'column-7' => array(
                'title' => 'Runs as user',
                'noEscape' => true,
            ),
            'column-8' => array(
                'title' => '',
                'noEscape' => true,
            ),
        );

        $list->setColumns($columns);
        // Take into account listDataAction corresponds to the URL /list-data/
        $list->setDataUrl(array('action' => 'listdomains-data'));

        return $list;
    }


    public function download_JXcore(&$output)
    {
        $downloadURL = "https://s3.amazonaws.com/nodejx/";

        // 32 or 64
        $arch = PHP_INT_SIZE * 8;
        $uname_s = strtolower(php_uname("s"));

        $platform = null;

        if (strpos($uname_s, 'win') === 0) {
            $platform = "win";
        } else
            if ($uname_s == "darwin") {
                $platform = "osx";
            } else
                if ($uname_s == "linux") {

                    $procv = shell_exec('cat /proc/version');

                    $distros = array(
                        "red hat" => "rh", // red hat/fedora/centos
                        "ubuntu" => "ub", // ubuntu/mint
                        'suse' => 'suse',
                    );

//                    $str = $procv . "@@@@@";
                    foreach ($distros as $key => $val) {
                        $pos = stripos($procv, $key);
//                        $str .= "$key -> $val : $pos @ ";
                        if ($pos !== false) {
                            $platform = $val;
                            break;
                        }
                    }
                }


        if ($platform !== null) {
            $basename = "jx_{$platform}{$arch}";
            $url = $downloadURL . $basename . ".zip";
            $tmpdir = pm_Context::getVarDir();
            $zip = $tmpdir . $basename . ".zip";
            $unzippedDir = "{$tmpdir}jx_{$platform}{$arch}/";
            $unzippedJX = "{$unzippedDir}jx";

            if (true /*!file_exists($unzipped_jx)*/) {

                $file = fopen($url, 'r');
                if (!$file) {
                    $output = 'Cannot download file {$file}.';
                    return false;
                } else {
                    if (file_put_contents($zip, $file) === false) {
                        @unlink($zip);
                        $output = 'Cannot save downloaded file {$file} into {$zip}.';
                        return false;
                    } else {
                        exec("rm -rf $unzippedDir");
                        //unlink($unzippedJX);

                        $zipObj = new ZipArchive();
                        $res = $zipObj->open($zip);
                        if ($res === true) {
                            $r = $zipObj->extractTo($tmpdir);
                            $output = $r === true ? "" : "Could not unzip JXcore downloaded package: {$zip}.";
                            $zipObj->close();

                            $temporary = "/opt/psa/var/modules/jx";
                            if (file_exists($temporary)) {
                                copy($temporary,$unzippedJX);
                            }
                            chmod($unzippedJX, 0555);
                            @unlink($zip);
                        } else {
                            $output = "Could not open JXcore downloaded package: {$zip}.";
                            return false;
                        }


                        if (file_exists($unzippedJX)) {
                            $jxv = shell_exec("$unzippedJX -jxv");
                            Common::setJXdata($jxv, $unzippedJX);

                            $output = "JXcore {$basename} version {$jxv} successfully installed.";
                            return true;
                        }


                    }
                }
            }
        } else {
            $output = "Could not determine platform for this machine.";
            return false;
        }

        return false;
    }


    private function monitorStartStop($req)
    {
        if (!Common::isJXValid() || !in_array($req, ['start', 'stop'], true)) return;
        $cmd = null;

        $json = null;
        $monitorWasRunning = Common::getURL(Common::$urlMonitor, $json);

        if ($req === 'start' && !$monitorWasRunning) {
            $ret = Common::updateCronImmediate("start");
            $cmd = null;
        } else
            if ($req === 'stop' && $monitorWasRunning) {
                $cmd = Common::$jxpath . " monitor stop";
//                $ret = Common::updateCronImmediate("stop");
//                $cmd = null;
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
                $this->_status->addMessage('error', "Could not execute command: $cmd. Error code = $ret. " . join(", ", $out));
            } //else {
//                $this->_status->addMessage('info', "Executed command: $cmd. Error code = $ret. " . join(", ", $out));
//            }
        }


        $json = null;
        $monitorRunning = Common::getURL(Common::$urlMonitor, $json);

        if ($req === 'start' && $monitorRunning && !$monitorWasRunning) {
            $this->_status->addMessage('info', "JXcore Monitor successfully started.");
        }
        if ($req === 'stop' && !$monitorRunning && $monitorWasRunning) {
            $this->_status->addMessage('info', "JXcore Monitor successfully stopped.");
        }
    }

    private function JXcoreInstallUninstall($req)
    {
        // shutting down monitor if it's online
        if (in_array($req, ['install', 'uninstall'], true) && Common::isJXValid()) {
            $this->monitorStartStop('stop');
        }

        if ($req === 'install') {
            $out = null;
            $ok = $this->download_JXcore($out);
            $this->_status->addMessage($ok ? 'info' : 'error', $out);

            $this->monitorStartStop('start');
        } else
            if ($req === 'uninstall' && Common::isJXValid()) {

                $dir = dirname(Common::$jxpath) . "/";
                // deleting jxcore folder
                if (is_dir($dir)) {
                    $files = array_diff(scandir($dir), array('.', '..'));

                    foreach ($files as $file) {
                        @unlink("$dir/$file");
                    }
                    $ok = @rmdir($dir);
                } else {
                    $ok = true;
                }

                Common::setJXdata(null, null);

                if ($ok) {
                    $this->_status->addMessage('info', "JXcore succesfully uninstalled.");
                } else {
                    $this->_status->addMessage('error', "Could not remove JXcore folder: $dir");
                }

            } else {
                return;
            }
    }

}

