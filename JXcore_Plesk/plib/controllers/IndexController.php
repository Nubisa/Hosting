<?php

/* Copyright Nubisa, Inc. 2014. All Rights Reserved */

class IndexController extends pm_Controller_Action
{
    public function init()
    {
        parent::init();

        if(Modules_JxcoreSupport_CustomStatus::CheckStatusRender($this)) // Plesk12
        {
            $this->_status = new Modules_JxcoreSupport_CustomStatus($this->view);
            $this->view->status = $this->_status;
        }

        $this->view->pageTitle = 'JXcore Plesk Extension for Node';

        $this->common = new Modules_JxcoreSupport_Common($this, $this->_status);

        if (Modules_JxcoreSupport_Common::$isAdmin) {

            $this->view->tabs = array(
                array(
                    'title' => 'Welcome',
                    'action' => 'jxcoresite'
                ),
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
                    'action' => 'listmodules',
                ),
                array(
                    'title' => 'Subscriptions',
                    'action' => 'listsubscriptions'
                )
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
        if (Modules_JxcoreSupport_Common::$firstRun) {
            $tab = Modules_JxcoreSupport_Common::$isAdmin ? 'init' : 'initNonAdmin';
            $this->_forward($tab);
            return true;
        }

        if ($onlyAdminIsAllowed && !Modules_JxcoreSupport_Common::$isAdmin) {
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
        if (Modules_JxcoreSupport_Common::$isAdmin) return;

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
            $this->_status->beforeRedirect = true;
            $this->_helper->json(array('redirect' => pm_Context::getModulesListUrl()));
        }

        $this->view->form = $form;
    }


    /**
     * Displays JXcore monitor log in panel's tab.
     */
    public function logAction_inactive()
    {
        if ($this->redirect(true)) return;

        $form = new pm_Form_Simple();
        $sidClearLog = "clear_log";
        $sidLastLinesCount = "last_lines_count";

        $form->addElement('hidden', $sidClearLog, array(
            'value' => "nothing"
        ));

        $form->addElement('simpleText', "size", array(
            'label' => 'Log file',
            'value' => Modules_JxcoreSupport_Common::getSimpleButton($sidClearLog, "Clear log", "clear", Modules_JxcoreSupport_Common::iconUrlDelete, null, "margin-left: 0px;"),
            'escape' => false
        ));

        $val = pm_Settings::get($sidLastLinesCount . "monitor");
        if (!$val && $val !=0) $val = 200;
        $form->addElement('text', $sidLastLinesCount, array(
            'label' => 'Show last # lines',
            'value' => $val,
            'required' => false,
            'validators' => array(
                'Int',
                array("GreaterThan", true, array('min' => -1)),
            ),
            'description' => 'Displays only last # lines of the log file. Enter 0 to display the whole log.',
            'escape' => false
        ));

        $form->addControlButtons(array(
            'cancelLink' => null,
            'hideLegend' => true
        ));

        if ($this->getRequest()->isPost() && $form->isValid($this->getRequest()->getPost())) {
            $this->_status->beforeRedirect = true;
            $actionClearValue = $this->getRequest()->getParam($sidClearLog);
            $actionClearPressed = $actionClearValue === "clear";

            $val = $form->getValue($sidLastLinesCount);

            if ($actionClearPressed) {
                Modules_JxcoreSupport_Common::callService("delete", "monitorlogs", "Log cleared.", "Problem: ");
            } else {
                pm_Settings::set($sidLastLinesCount . "monitor", $val);
            }
            $this->_helper->json(array('redirect' => Modules_JxcoreSupport_Common::$urlJXcoreMonitorLog));
        }

        $log = "";
        Modules_JxcoreSupport_Common::getURL(Modules_JxcoreSupport_Common::$urlMonitorLog, $log);
        $this->view->log = implode("<br>", array_slice(explode("\n", trim($log)), -$val));

        $this->view->buttonsDisablingScript = Modules_JxcoreSupport_Common::getButtonsDisablingScript();
        $this->view->form = $form;
    }

    /*
     * Main page of the module.
     */
    public function indexAction()
    {
        if ($this->redirect()) return;
        $this->_forward('jxcoresite');
    }

    public function jxcoresiteAction() {
        $str = "";
        Modules_JxcoreSupport_Common::getURL("https://nodejx.s3.amazonaws.com/plesk_help.html", $str);
        $this->view->jxcoreSiteContents = $str;
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
        Modules_JxcoreSupport_Common::check();
    }

    /**
     * Action for domains list - when user clicks on table's column header.
     */
    public function listdomainsDataAction()
    {
        $this->listdomainsAction();

        // Json data from pm_View_List_Simple
        $this->_helper->json($this->view->list->fetchData());
        Modules_JxcoreSupport_Common::check();
    }


    /**
     * The initialization form display right after installation of the extension.
     */
    public function initAction()
    {
        if (!Modules_JxcoreSupport_Common::$isAdmin) {
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

            $this->_status->beforeRedirect = true;
            if (!Modules_JxcoreSupport_Common::isJXValid()) {
                $out = null;

                $ok = $this->download_JXcore($out);
                $this->_status->addMessage($ok ? 'info' : 'error', $out);

                Modules_JxcoreSupport_Common::enableServices();
                Modules_JxcoreSupport_Common::updateAllConfigsIfNeeded();
                Modules_JxcoreSupport_Common::monitorStartStop("start");
            }

            pm_Settings::set(Modules_JxcoreSupport_Common::sidFirstRun, "no");
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

        $json = Modules_JxcoreSupport_Common::getMonitorJSON();
        $monitorRunning = $json !== null;

        // Init form here
        $form = new pm_Form_Simple();

        $jxvalid = Modules_JxcoreSupport_Common::isJXValid();

        if (Modules_JxcoreSupport_Common::$isAdmin) {

            // JXcore install uninstall
            $form->addElement('hidden', $sidJXcore, array(
                'value' => 'nothing',
            ));

            $form->addElement('simpleText', 'jxversion', array(
                'label' => 'JXcore version',
                'escape' => false,
                'value' => Modules_JxcoreSupport_Common::getIcon(Modules_JxcoreSupport_Common::$jxv, "Installed " . Modules_JxcoreSupport_Common::$jxv, 'Not installed')
            ));

            if (Modules_JxcoreSupport_Common::$jxv) {
                $form->addElement('simpleText', 'jxpath', array(
                    'label' => 'JXcore path',
                    'escape' => false,
                    'value' => $jxvalid ? Modules_JxcoreSupport_Common::$jxpath : "<span style=\"color: red;\">Could not find JXcore executable file (path = '" . Modules_JxcoreSupport_Common::$jxpath . "'). Try reinstalling JXcore.</span>",
                ));
            }

            if (Modules_JxcoreSupport_Common::$jxv) {
                $buttons = Modules_JxcoreSupport_Common::getSimpleButton($sidJXcore, 'Reinstall', "install", "/theme/icons/16/plesk/show-all.png", null, "margin-left: 0;");
            } else {
                $buttons = Modules_JxcoreSupport_Common::getSimpleButton($sidJXcore, 'Install', "install", "/theme/icons/16/plesk/upload-files.png", null, "margin-left: 0;");
            }

            $form->addElement('simpleText', 'reinstall', array(
                'label' => '',
                'escape' => false,
                'value' => $buttons,
                'description' => Modules_JxcoreSupport_Common::$jxv ?
                        "If you reinstall JXcore, all the running node applications will be restarted after updating JXcore binary." :
                        "JXcore distribution for this platform will be downloaded and installed."
            ));


            if ($jxvalid) {

                Modules_JxcoreSupport_Common::addHr($form);

                // monitor start / stop button
                $form->addElement('hidden', $sidMonitor, array(
                    'value' => 'nothing',
                ));

//                $cronAction = pm_Settings::get(Common::sidMonitorStartScheduledByCronAction);
//                $ret = Common::checkCronScheduleStatus(false);
//                if ($ret && $ret > 0) {
//                    if ($cronAction == "start")
//                        $btn = Common::getIcon($monitorRunning, "Online", "Offline") . "<div> Monitor is scheduled to be launched in $ret seconds.</div>";
//                    else  if ($cronAction == "stop")
//                         $btn = Common::getIcon($monitorRunning, "Online", "Offline") . "<div> Monitor is scheduled to be stopped in $ret seconds.</div>";
//                } else {
                    $btn = Modules_JxcoreSupport_Common::getButtonStartStop($monitorRunning, $sidMonitor, ["Online", "Start"], ["Offline", "Stop"]);
//                }


                $form->addElement('simpleText', 'status', array(
                    'label' => 'JXcore Monitor status',
                    'escape' => false,
                    'value' => $btn,
                    'description' => $monitorRunning ? "If you stop, all the monitored applications will be terminated!" : "If you start, all the JXcore enabled applications will be launched."
                ));

                Modules_JxcoreSupport_Common::addHr($form);

                $form->addElement('text', Modules_JxcoreSupport_Common::sidJXcoreMinimumPortNumber, array(
                    'label' => 'Minimum app port number',
                    'value' => Modules_JxcoreSupport_Common::$minApplicationPort,
                    'required' => true,
                    'validators' => array(
                        'Int',
                        array("LessThan", true, array('max' => $req->getParam(Modules_JxcoreSupport_Common::sidJXcoreMaximumPortNumber))),
                        array("Between", true, array('min' => Modules_JxcoreSupport_Common::minApplicationPort_default, 'max' => Modules_JxcoreSupport_Common::maxApplicationPort_default))
                    ),
                    'description' => '',
                    'escape' => false
                ));

                $domainCnt = count(Modules_JxcoreSupport_Common::getDomainsIDs());
                $validator = new MyValid_PortMax();
                $validator->newMinimum = $req->getParam(Modules_JxcoreSupport_Common::sidJXcoreMinimumPortNumber);
                $form->addElement('text', Modules_JxcoreSupport_Common::sidJXcoreMaximumPortNumber, array(
                    'label' => 'Maximum app port number',
                    'value' => Modules_JxcoreSupport_Common::$maxApplicationPort,
                    'required' => true,
                    'validators' => array('Int',
                        array("GreaterThan", true, array('min' => $req->getParam(Modules_JxcoreSupport_Common::sidJXcoreMinimumPortNumber))),
                        array("Between", true, array('min' => Modules_JxcoreSupport_Common::minApplicationPort_default, 'max' => Modules_JxcoreSupport_Common::maxApplicationPort_default)),
                        $validator
                    ),
                    'description' => "The port range should be greater than domain count multiplied by two (HTTP + HTTPS). Right now there are $domainCnt domains, and you need two ports for each of them.",
                    'escape' => false
                ));

                $form->addElement('simpleText', "restartmayoccur", array(
                    'label' => '',
                    'escape' => false,
                    'value' => "<span style='color: red;'>Submitting the form may result in restarting the monitor together with all running applications.</span>",
                    'description' => ""
                ));

                $form->addControlButtons(array(
                    'cancelLink' => pm_Context::getModulesListUrl(),
                ));
            }

            if ($req->isPost() && $form->isValid($req->getPost())) {

                $this->_status->beforeRedirect = true;

                $monitorAction = in_array($req->getParam($sidMonitor), ["start", "stop"]);
                $installAction = in_array($req->getParam($sidJXcore), ["install", "uninstall"]);

                if ($monitorAction) {
                    Modules_JxcoreSupport_Common::monitorStartStop($req->getParam($sidMonitor));
                } else if ($installAction) {
                    $this->JXcoreInstallUninstall($req->getParam($sidJXcore));
                } else {
                    $params = [Modules_JxcoreSupport_Common::sidJXcoreMinimumPortNumber, Modules_JxcoreSupport_Common::sidJXcoreMaximumPortNumber];

                    $portsChanged = false;
                    foreach ($params as $param) {
                        if (pm_Settings::get($param) !== $form->getValue($param)) $portsChanged = true;
                        pm_Settings::set($param, $form->getValue($param));
                    }

                    if ($portsChanged) Modules_JxcoreSupport_Common::reassignPorts();
                    Modules_JxcoreSupport_Common::updateAllConfigsIfNeeded("nowait");

                    $this->_status->addMessage('info', 'Data was successfully saved.');
                }
                $this->_helper->json(array('redirect' => Modules_JxcoreSupport_Common::$urlJXcoreConfig));
            }
        }

        $this->view->buttonsDisablingScript = Modules_JxcoreSupport_Common::getButtonsDisablingScript();
        $this->view->form = $form;
        Modules_JxcoreSupport_Common::check();
    }



    public function listmodulesAction()
    {
        if ($this->redirect(true)) return;

        $form = new pm_Form_Simple();


        $form->addElement('hidden', "remove", array(
            'label' => 'Installed modules',
            'value' => ""
        ));

        $nameToInstall = trim($this->getRequest()->getParam("names"));
        $form->addElement('text', "names", array(
            'label' => 'Install new module',
            'value' => $nameToInstall,
            'validators' => array(new MyValid_Module()),
            'filters' => array('StringTrim'),
            'description' => 'Name of NPM module to install.',
            'escape' => false
        ));

        $form->addControlButtons(array(
            'cancelLink' => null,
            'hideLegend' => true
        ));

        if ($this->getRequest()->isPost() && $form->isValid($this->getRequest()->getPost())) {

            $this->view->status->beforeRedirect = true;

            $nameToRemove = trim($this->getRequest()->getParam("remove"));
            if ($nameToRemove) {
                Modules_JxcoreSupport_Common::callService("remove", $nameToRemove, "Module #arg# was successfully removed.", "Cannot remove #arg# module.");
            } else
            if ($nameToInstall) {
                Modules_JxcoreSupport_Common::callService("install", $nameToInstall, "Module #arg# was successfully installed.", "Cannot install #arg# module.");
            }

            $this->_helper->json(array('redirect' => Modules_JxcoreSupport_Common::$urlJXcoreModules));
        }

        $this->view->buttonsDisablingScript = Modules_JxcoreSupport_Common::getButtonsDisablingScript();
        $this->view->form = $form;

        $this->view->list = $this->getModulesList();
        if ($this->view->list) {
            $this->view->list->setDataUrl(array('action' => 'listmodules-data'));
        }

        Modules_JxcoreSupport_Common::check();
    }

    public function listmodulesDataAction()
    {
        $this->listmodulesAction();
        $this->_helper->json($this->view->list->fetchData());
        Modules_JxcoreSupport_Common::check();
    }

    private function getModulesList() {
        $list = new pm_View_List_Simple($this->view, $this->_request);

        $data = [];
        $info = Modules_JxcoreSupport_Common::callService("modules", "info", null, null, true);

        $modules = explode("||", $info);
        foreach($modules as $str) {
            $parsed = explode("|", $str);
            if (count($parsed) == 3) {
                $modules[$parsed[0] . "_version"] = $parsed[1];
                $modules[$parsed[0] . "_description"] = $parsed[2];
            }
        }

        $node_modules = Modules_JxcoreSupport_Common::$dirNativeModules . "node_modules/";
        if (file_exists($node_modules)) {
            $d = dir($node_modules);
            while (false !== ($entry = $d->read())) {
                if (substr($entry, 0, 1) !== "." && is_dir($node_modules . $entry)) {

                    $ver = isset($modules[$entry . "_version"]) ? $modules[$entry . "_version"] : "Cannot read version";
                    $desc = isset($modules[$entry . "_description"]) ? $modules[$entry . "_description"] : "Cannot read description";

                    $data[] = array(
                        'column-1' => Modules_JxcoreSupport_Common::iconON,
                        'column-2' => $entry,
                        'column-3' => $ver,
                        'column-4' => $desc,
                        'column-5' => Modules_JxcoreSupport_Common::getSimpleButton("remove", "Remove", "$entry", null, null, "margin: 0px;")
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
            ),
            'column-3' => array(
                'title' => 'module version',
                'noEscape' => true,
            ),
            'column-4' => array(
                'title' => 'Description',
                'noEscape' => true,
            ),
            'column-5' => array(
                'title' => '',
                'noEscape' => true,
            )
        );

        $list->setColumns($columns);
        $list->setDataUrl(array('action' => 'listdomains-data'));
        return $list;
    }




    private function _getDomains()
    {
        if (!Modules_JxcoreSupport_Common::$jxv) {
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
        $ids = Modules_JxcoreSupport_Common::getDomainsIDs();
        foreach ($ids as $id) {
            $domain = Modules_JxcoreSupport_Common::getDomain($id);
            $sub = $domain->getSubscription();

            if ($sub == null) {
                continue;
            }

            if (!Modules_JxcoreSupport_Common::$isAdmin && $clid != $domain->row['cl_id']) {
                continue;
            }

            $domain->getAppPathOrDefault(false, true);
            $domain->getAppPortOrDefault(true, false);
            $domain->getAppPortOrDefault(true, true);

            $status = $domain->JXcoreSupportEnabled();
            if ($status != 1) $status = false; else $status = true;

            $baseUrl = pm_Context::getBaseUrl() . 'index.php/domain/';
            $editUrl = $baseUrl . 'config/id/' . $id;

            $data[] = array(
                'column-1' => $id,
                'column-2' => url($editUrl, $domain->row['displayName']),
                'column-3' => url($editUrl, $domain->row['cr_date']),
                'column-4' => Modules_JxcoreSupport_Common::getIcon($status, "Enabled", "Disabled"),
                'column-5' => $domain->getAppPortStatus(null, false),
                'column-6' =>  $domain->getAppStatus(),
                'column-7' => url($editUrl, $domain->sysUser),
                'column-8' => Modules_JxcoreSupport_Common::getSimpleButton("edit", "Manage", null, null, $editUrl),
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
                'title' => 'JXcore',
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
        $list->setDataUrl(array('action' => 'listdomains-data'));

        return $list;
    }




    /**
     * Subscription list
     */
    public function listsubscriptionsAction()
    {
        if ($this->redirect()) return;
        $this->view->list = $this->getSubscriptions();
        if ($this->view->list) {
            $this->view->list->setDataUrl(array('action' => 'listsubscriptions-data'));
        }
        Modules_JxcoreSupport_Common::check();
    }

    /**
     * Action for subscription list - when user clicks on table's column header.
     */
    public function listsubscriptionsDataAction()
    {
        $this->listsubscriptionsAction();

        // Json data from pm_View_List_Simple
        $this->_helper->json($this->view->list->fetchData());
        Modules_JxcoreSupport_Common::check();
    }


    private function getSubscriptions()
    {
        if (!Modules_JxcoreSupport_Common::$jxv) {
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

        // fetching domain list
        $dbAdapter = pm_Bootstrap::getDbAdapter();
        $sql = "SELECT * from `Subscriptions` where object_type = 'domain'";
        $statement = $dbAdapter->query($sql);

        while ($row = $statement->fetch()) {
            $id = intval($row['id']);
            $sub = SubscriptionInfo::getSubscription($id);

            if (!$sub) {
//                StatusMessage::addError("Invalid subscription with id = $id.");
                continue;
            }

            if (!Modules_JxcoreSupport_Common::$isAdmin && $clid != $sub->mainDomain->row['cl_id']) {
                continue;
            }

            $baseUrl = pm_Context::getBaseUrl() . 'index.php/subscription/';
            $editUrl = $baseUrl . 'config/id/' . $id;

            $domains = $sub->getDomains();
            $domains_str = "";
            foreach($domains as $d) {
                $domains_str .=  $d->name . "<br>";
            }

            $data[] = array(
                'column-1' => $id,
                'column-2' => url($editUrl, $sub->mainDomain->row['displayName']),
                'column-3' => $domains_str,
                'column-8' => Modules_JxcoreSupport_Common::getSimpleButton("edit", "Manage", null, null, $editUrl),
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
                'title' => 'Subscription',
                'noEscape' => true,
            ),
            'column-3' => array(
                'title' => 'Domains',
                'noEscape' => true,
            ),
            'column-8' => array(
                'title' => '',
                'noEscape' => true,
            ),
        );

        $list->setColumns($columns);
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
                        "Red Hat" => "rh", // red hat/fedora/centos
                        "Ubuntu" => "ub", // ubuntu/mint
                        'SUSE' => 'suse',
                        'Debian' => 'deb'
                    );

                    foreach ($distros as $key => $val) {
                        $pos = stripos($procv, $key);
                        if ($pos !== false) {
                            $platform = $val;
                            break;
                        }
                    }
                }


        if ($platform !== null) {
//            if($platform == "suse")
//                $basename = "jx_suse32.zip";
//            else
                $basename = "jx_{$platform}{$arch}";

//            if($arch ."" == "32" && $platform == "deb")
//                $basename = "jx_ub32.zip";

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
//                        exec("rm -rf $unzippedDir");
                        Modules_JxcoreSupport_Common::rmdir($unzippedDir);
                        Modules_JxcoreSupport_Common::rmdir(Modules_JxcoreSupport_Common::$dirSubscriptionConfigs);
                        //unlink($unzippedJX);

                        $zipObj = new ZipArchive();
                        $res = $zipObj->open($zip);
                        if ($res === true) {
                            $r = $zipObj->extractTo($tmpdir);
                            $output = $r === true ? "" : "Could not unzip JXcore downloaded package: {$zip}.";
                            $zipObj->close();

                            $temporary = "/usr/local/psa/var/modules/jx";
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
                            Modules_JxcoreSupport_Common::setJXdata($jxv, $unzippedJX);
                            Modules_JxcoreSupport_Common::updateCron();

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




    private function JXcoreInstallUninstall($req)
    {
        // shutting down monitor if it's online
        if (in_array($req, ['install', 'uninstall'], true) && Modules_JxcoreSupport_Common::isJXValid()) {
            Modules_JxcoreSupport_Common::monitorStartStop('stop');
        }

        if ($req === 'install') {
            $out = null;
            $ok = $this->download_JXcore($out);
            $this->_status->addMessage($ok ? 'info' : 'error', $out);
            Modules_JxcoreSupport_Common::updateAllConfigsIfNeeded("norestart");
            Modules_JxcoreSupport_Common::monitorStartStop('start');
        } else
            if ($req === 'uninstall' && Modules_JxcoreSupport_Common::isJXValid()) {

                $dir = dirname(Modules_JxcoreSupport_Common::$jxpath) . "/";
                // deleting subscriptions folder, because there are copies of jx binaries there
                Modules_JxcoreSupport_Common::rmdir(Modules_JxcoreSupport_Common::$dirSubscriptionConfigs);
                // deleting jxcore folder
                $ok = Modules_JxcoreSupport_Common::rmdir($dir);
                Modules_JxcoreSupport_Common::setJXdata(null, null);

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




class MyValid_PortMax extends Zend_Validate_Abstract
{
    const MSG_MINIMUM = 'msgMinimum';
    const MSG_MAXIMUM = 'msgMaximum';
    const MSG_TOOLITTLE = 'msgTooLittle';


    public $newMinimum = 0;
    public $newMaximum = 0;

    public $minimum = 0;
    public $maximum = 0;
    public $domainCount = 0;

    protected $_messageVariables = array(
        'min' => 'minimum',
        'max' => 'maximum',
        'cnt' => 'domainCount'
    );

    protected $_messageTemplates = array(
        self::MSG_MINIMUM => "'%value%' must be at least '%min%'",
        self::MSG_MAXIMUM => "'%value%' must be no more than '%max%'",
        self::MSG_TOOLITTLE => "Too small range. You need at least %cnt% ports."
    );

    public function isValid($value)
    {
        $this->_setValue($value);

        $this->domainCount = count(Modules_JxcoreSupport_Common::getDomainsIDs()) * 2;

        if ($value - $this->newMinimum + 1 < $this->domainCount) {
            $this->_error(self::MSG_TOOLITTLE);
            return false;
        }

        return true;
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

        $forbidden = [ './', '/.', '.\\', '\\.'  ];
        foreach($forbidden as $str) {
            if (strpos($value, $str) !== false) {
                $this->cannotContain = $str;
                $this->_error(self::MSG_CANNOTCONTAIN);
                return false;
            }
        }

        $forbidden = [ '/', '\\'];
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