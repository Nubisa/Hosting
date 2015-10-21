<?php

/* Copyright Nubisa, Inc. 2014. All Rights Reserved */

class IndexController extends pm_Controller_Action
{
    private $varDir = null;
    private $jxDir = null;
    private $jxFileName = null;

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

        // starting from 0.2.7 we will use static dir for jx
        // no need to keep platform specific dir, e.g. /.../jx_ub32v8/
        // let it be /.../jxcore/
        $this->varDir = pm_Context::getVarDir();
        $this->jxDir = "{$this->varDir}jxcore/";
        $this->jxFileName = "{$this->jxDir}jx";
    }

    /**
     * When extension is browsed for the first time in the panel - the navigation goes
     * to init page for JXcore installation.
     * Also for pages accessible only for admin - navigation goes to main jx config tab.
     * @param bool $onlyAdminIsAllowed
     * @return bool
     */
    private function redirect($onlyAdminIsAllowed = false)
    {
        if (Modules_JxcoreSupport_Common::$firstRun || !Modules_JxcoreSupport_Common::isJXValid()) {
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
        if (Modules_JxcoreSupport_Common::$isAdmin)
            $this->_forward('jxcoresite');
        else
             $this->_forward('jxcore');
    }

    public function jxcoresiteAction() {
        if ($this->redirect(true)) return;
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
        if ($this->redirect()) return;
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

                list($err, $msg) = $this->downloadAndUnpack_JXcore();
                if ($msg) $this->_status->addMessage($err ? 'error' : 'info', $msg);

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

            $newVersion = new JXcoreLatestVersionInfo(false);
            $latest = $newVersion->isLatest || $newVersion->isUpdateAvailable ? '. <span class="hint" style="display: inline-block; vertical-align: text-top;">' . $newVersion->status . '</span>' : "";

            // JXcore install uninstall
            $form->addElement('hidden', $sidJXcore, array(
                'value' => 'nothing',
            ));

            $form->addElement('simpleText', 'jxversion', array(
                'label' => 'JXcore version',
                'escape' => false,
                'value' => Modules_JxcoreSupport_Common::getIcon(Modules_JxcoreSupport_Common::$jxv, "Installed <b>" . trim(Modules_JxcoreSupport_Common::$jxv) . "</b>". $latest, 'Not installed')
            ));

            if (Modules_JxcoreSupport_Common::$jxv) {
                $form->addElement('simpleText', 'jxpath', array(
                    'label' => 'JXcore path',
                    'escape' => false,
                    'value' => $jxvalid ? Modules_JxcoreSupport_Common::$jxpath : "<span style=\"color: red;\">Could not find JXcore executable file (path = '" . Modules_JxcoreSupport_Common::$jxpath . "'). Try reinstalling JXcore.</span>",
                ));
            }

            if (Modules_JxcoreSupport_Common::$jxv) {
                $caption = 'Reinstall';
                if (!$newVersion->error && $newVersion->isUpdateAvailable)
                    $caption = "Update to " . $newVersion->version;
                $buttons = Modules_JxcoreSupport_Common::getSimpleButton($sidJXcore, $caption , "install", "/theme/icons/16/plesk/show-all.png", null, "margin-left: 0;");
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

                $btn = Modules_JxcoreSupport_Common::getButtonStartStop($monitorRunning, $sidMonitor, array("Online", "Start"), array("Offline", "Stop"));
                if (!$monitorRunning && $newVersion->mustUpdate)
                    $btn = Modules_JxcoreSupport_Common::getIcon($monitorRunning, "non-important", "Offline", "display: inline-block; min-width: 80px;") .
                        '<span style="color: red;">You cannot start the monitor right now. ' . $newVersion->status . '</span>';

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

                $monitorAction = in_array($req->getParam($sidMonitor), array("start", "stop"));
                $installAction = in_array($req->getParam($sidJXcore), array("install", "uninstall"));

                if ($monitorAction) {
                    $monitorActionValue = $req->getParam($sidMonitor);
                    if ($monitorActionValue === "start") {
                        Modules_JxcoreSupport_Common::updateAllConfigsIfNeeded("norestart");
                    }
                    Modules_JxcoreSupport_Common::monitorStartStop($monitorActionValue);
                    Modules_JxcoreSupport_Common::reloadNginx();
                } else if ($installAction) {
                    $this->JXcoreInstallUninstall($req->getParam($sidJXcore));
                } else {
                    $params = array(Modules_JxcoreSupport_Common::sidJXcoreMinimumPortNumber, Modules_JxcoreSupport_Common::sidJXcoreMaximumPortNumber);

                    $portsChanged = false;
                    foreach ($params as $param) {
                        if (pm_Settings::get($param) !== $form->getValue($param)) $portsChanged = true;
                        pm_Settings::set($param, $form->getValue($param));
                    }

                    if ($portsChanged) Modules_JxcoreSupport_Common::reassignPorts();
                    Modules_JxcoreSupport_Common::updateAllConfigsIfNeeded("nowait");
                    // nginx IS reloaded (in updateAllConfigsIfNeeded()),
                    // but somehow without the second call is not catching the changes of port range...
                    if ($portsChanged) Modules_JxcoreSupport_Common::reloadNginx(true);

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
        if ($this->redirect(true)) return;

        $this->listmodulesAction();
        $this->_helper->json($this->view->list->fetchData());
        Modules_JxcoreSupport_Common::check();
    }

    private function getModulesList() {
        $list = new pm_View_List_Simple($this->view, $this->_request);

        $data = array();
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


        $data = array();
        $col_count = 8;
        $ids = Modules_JxcoreSupport_Common::getDomainsIDs();
        foreach ($ids as $id) {
            $domain = Modules_JxcoreSupport_Common::getDomain($id);
            $sub = $domain->getSubscription();

            if ($sub == null) {
                continue;
            }

//            if (!Modules_JxcoreSupport_Common::$isAdmin && $clid != $domain->row['cl_id']) {
//                continue;
//            }

            if (!Modules_JxcoreSupport_Common::hasAccessToDomain($domain))
                continue;

            $domain->getAppPathOrDefault(false, true);
            $domain->getAppPortOrDefault(true, false);
            $domain->getAppPortOrDefault(true, true);

            $status = $domain->JXcoreSupportEnabled_Value();
            if ($status != 1) $status = false; else $status = true;

            $baseUrl = pm_Context::getBaseUrl() . 'index.php/domain/';
            $editUrl = $baseUrl . 'config/id/' . $id;

            if ($sub->JXcoreSupportEnabled()) {
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
            } else {
                if (Modules_JxcoreSupport_Common::$isAdmin) {
                    $data[] = array(
                        'column-1' => $id,
                        'column-2' => $domain->row['displayName'],
                        'column-3' => $domain->row['cr_date'],
                        'column-4' => Modules_JxcoreSupport_Common::getIcon($status, "Enabled", "Disabled"),
                        'column-5' => $domain->getAppPortStatus(null, false),
                        'column-6' => $domain->getAppStatus(),
                        'column-7' => $domain->sysUser,
                        'column-8' => StatusMessage::orange("Subscription disabled.")
                    );
                } else {
                    $data[] = array(
                        'column-1' => $id,
                        'column-2' => $domain->row['displayName'],
                        'column-3' => $domain->row['cr_date'],
                        'column-4' => "You have no access to manage the domain. " . StatusMessage::orange("JXcore support for the subscription is disabled.")
                    );
                    $col_count = 4;
                }
            }

        }

        $list = new pm_View_List_Simple($this->view, $this->_request);
        $list->setData($data);
        $_columns = array(
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

        $columns = array();
        $id = 1;
        foreach ($_columns as $key=>$arr1) {
            if ($id > $col_count)
                break;

            $columns[$key] = $_columns[$key];
            $id++;
        }

        $list->setColumns($columns);
        $list->setDataUrl(array('action' => 'listdomains-data'));

        return $list;
    }




    /**
     * Subscription list
     */
    public function listsubscriptionsAction()
    {
        if ($this->redirect(true)) return;


        $form = new pm_Form_Simple();


        $form->addElement('hidden', "subAction", array(
            'label' => 'Installed modules',
            'value' => "krowa"
        ));


        if ($this->getRequest()->isPost() && $form->isValid($this->getRequest()->getPost())) {

            $this->view->status->beforeRedirect = true;

            $do = $this->getRequest()->getParam('subAction');
            if ($do === "sub-enable" || $do === "sub-disable") {

                $ids = SubscriptionInfo::getIds();
                foreach($ids as $id) {
                    $sub = SubscriptionInfo::getSubscription($id);
                    $sub->set(Modules_JxcoreSupport_Common::sidSubscriptionJXcoreEnabled, $do === "sub-enable" ? 1 : 0);
                }

                Modules_JxcoreSupport_Common::updateAllConfigsIfNeeded("nowait");
            }

            $this->_helper->json(array('redirect' => Modules_JxcoreSupport_Common::$urlJXcoreSubscriptions));
        }

        $buttons = Modules_JxcoreSupport_Common::getSimpleButton("subAction", "Enable All", "sub-enable", "/theme/icons/16/plesk/start.png", null, "margin-left: 0px", "Are you sure?") .
                   Modules_JxcoreSupport_Common::getSimpleButton("subAction", "Disable All", "sub-disable", "/theme/icons/16/plesk/stop.png", null, "margin-left: 0px", "Are you sure?");

        $this->view->buttonsDisablingScript = Modules_JxcoreSupport_Common::getButtonsDisablingScript();
        $this->view->form = $form . $buttons;

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
        if ($this->redirect(true)) return;
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
                $edit_url = "/modules/jxcore-support/index.php/domain/config/id/" . $d->id;

                if (!$sub->JXcoreSupportEnabled())
                    $domains_str .=  $d->name . "<br>";
                else
                    $domains_str .=  url($edit_url, $d->name) . "<br>";
            }

            $status = $sub->JXcoreSupportEnabled();
            if ($status != 1) $status = false; else $status = true;

            $data[] = array(
                'column-1' => $id,
                'column-2' => url($editUrl, $sub->mainDomain->row['displayName']),
                'column-3' => Modules_JxcoreSupport_Common::getIcon($status, "Enabled", "Disabled"),
                'column-4' => $domains_str,
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
                'title' => 'JXcore',
                'noEscape' => true,
            ),
            'column-4' => array(
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


    // downloads only
    // returns array(err, zipFileName)
    // where err:  null or errMsg
    public function download_JXcore() {
        // this was for 2.3.7
        // $downloadURL = "https://s3.amazonaws.com/nodejx/";

        $newVersion = new JXcoreLatestVersionInfo();
        if ($newVersion->error)
            return array($newVersion->status, null);

        $downloadURL = $newVersion->url;

        $osInfo = JXcoreOSInfo::get();
        if ($osInfo->error)
            return array($osInfo->error, null);

        $url = $downloadURL . $osInfo->basename . ".zip";

        // /opt/psa/var/modules/jxcore-support/ or
        // /usr/local/psa/var/modules/jxcore-support/
        $varDir = pm_Context::getVarDir();

        $zip = $varDir . $osInfo->basename . ".zip";
        $file = @fopen($url, 'r');
        if (!$file)
            return array("Cannot download file {$url}. Please check the internet connection or whether this platform is supported or not. ", null);

        if (file_put_contents($zip, $file) === false) {
            @unlink($zip);
            return array('Cannot save downloaded file {$file} into {$zip}.', null);
        }

        return array(null, $zip);
    }

    // copies new binary into old one (with error check and rollback)
    // returns true if success, or err message otherwise
    private function replace_JXcore($from) {

        if (!file_exists($from)) return "Source file does not exist: $from";

        if (!file_exists($this->jxDir)) {
            if (!@mkdir($this->jxDir))
                return "Cannot create directory: " . error_get_last()['message'];
        }

        $backup = null;
        if (file_exists($this->jxFileName)) {
            // backup first
            $backup = $this->jxFileName . ".old";
            if (!@rename($this->jxFileName, $backup))
                return "Cannot create a backup file: " . error_get_last()['message'];
        }

        if (!@copy($from, $this->jxFileName)) {
            // restore backup
            if ($backup) @rename($backup, $this->jxFileName);
            return "Cannot copy source file: " . error_get_last()['message'];
        }

        if (!@chmod($this->jxFileName, 0555)) {
            // restore backup
            if ($backup) @rename($backup, $this->jxFileName);
            return "Cannot chmod target file: " . error_get_last()['message'];
        }

        $jxv = shell_exec("{$this->jxFileName} -jxv");
        if ($jxv === NULL) {
            if ($backup) @rename($backup, $this->jxFileName);
            return "Cannot execute `jx -jxv`. Is the binary downloaded for the right platform?";
        }

        Modules_JxcoreSupport_Common::setJXdata($jxv, $this->jxFileName);
        Modules_JxcoreSupport_Common::updateCron();

        @unlink($backup);
        return true;
    }

    // returns filename if it exists or false
    private function getAlternateJXcore() {
        // /opt/psa/var/modules/jxcore-support/jx
        $alternate = $this->varDir . "jx";
        if (file_exists($alternate))
            return $alternate;
        else
            return false;
    }

    // return true if alternate binary was used or err message otherwise
    private function useAlternateJXcore() {
        $alternate = $this->getAlternateJXcore();
        if ($alternate !== false) {

            $ret = $this->replace_JXcore($alternate);
            if ($ret !== true) {
                StatusMessage::addWarning("Cannot use alternate $alternate file. Error: $ret");
                return $ret;
            } else {
                StatusMessage::addWarning("Used $alternate file instead of downloaded.");
                return true;
            }
        }
        return false;
    }

    // returns array(error: true/false, msg)
    private function unpack_JXcore($zip)
    {
        if ($this->useAlternateJXcore() === true)
            return array(false, "");

        $osInfo = JXcoreOSInfo::get();

        $unzippedDir237 = "{$this->varDir}jx_{$osInfo->platform}{$osInfo->arch}/";
        $unzippedDir = "{$this->varDir}{$osInfo->basename}/";
        $unzippedJX = "{$unzippedDir}jx";

        Modules_JxcoreSupport_Common::rmdir($unzippedDir);
        Modules_JxcoreSupport_Common::rmdir(Modules_JxcoreSupport_Common::$dirSubscriptionConfigs);

        $zipObj = new ZipArchive();
        $res = $zipObj->open($zip);
        if ($res === true) {
            $r = $zipObj->extractTo($this->varDir);
            $zipObj->close();

            if ($r !== true)
                return array(true, "Could not unzip JXcore downloaded package: {$zip}.");

            $ret = $this->replace_JXcore($unzippedJX);
            if ($ret !== true)
                return array(true, $ret);

            @unlink($zip);
            $jxv = pm_Settings::get(Modules_JxcoreSupport_Common::sidJXversion);
            return array(false, "JXcore {$osInfo->basename} version {$jxv} successfully installed.");
        } else {
            Modules_JxcoreSupport_Common::rmdir($unzippedDir237);
            return array(true, "Could not open JXcore downloaded package: {$zip}.");
        }
    }

    // return array(error: true/false, msg)
    private function downloadAndUnpack_JXcore() {
        list($err, $zip) = $this->download_JXcore();
        if ($err)
            return array(true, $err);

        list($err, $msg) = $this->unpack_JXcore($zip);
        return array($err, $msg);
    }



    private function JXcoreInstallUninstall($req)
    {
        list ($err, $zip) = $this->download_JXcore();
        if ($err) {
            $this->_status->addMessage('error', $err);
            return;
        }

        // shutting down monitor if it's online
        if (in_array($req, array('install', 'uninstall'), true) && Modules_JxcoreSupport_Common::isJXValid()) {
            Modules_JxcoreSupport_Common::monitorStartStop('stop');
        }

        if ($req === 'install') {
            list($err, $msg) = $this->unpack_JXcore($zip);
            if ($msg) $this->_status->addMessage($err ? 'error' : 'info', $msg);
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
                    $this->_status->addMessage('info', "JXcore successfully uninstalled.");
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