<?php
class CustomStatus
{
    function CustomStatus(){
        $this->messageId = 0;
    }
    public function addMessage($type, $message){
        $this->messageId++;
        echo  "<script>"
            . " if(!window.__inter" . $this->messageId . "){"
            . "  window.__inter" . $this->messageId . " = setInterval(function(){"
            . "    if(document.getElementById('content')){"
            . "      clearInterval(window.__inter" . $this->messageId . ");"
            . "    }else{return;}"
            . "   __addMessage('".$type."','".$message."');"
            . "  },500);}</script>";
    }
    public function hasMessages(){
        return false;
    }

    public function addInfo($message){
        $this->addMessage("info", $message);
    }
}

class IndexController extends pm_Controller_Action
{

    public function init()
    {
        parent::init();
        if(get_class($this->view->status) != "AdminPanel_Controller_Action_Status")
         {
            $this->pleskVersion = 12;
            //$this->view->status = new pm_View_Status();
            $str = "<script>"
                 . "  if(!window.__addMessage){"
                 . "  window.__addMessage = function(type, message){"
                 . "    var _content = document.getElementById('content').children;"
                 . "    var xtypes={'info':'information','warn':'warning','err':'error'};"
                 . "    var xtype = !xtypes[type] ? type:xtypes[type]; "
                 . "    for(var o in _content){"
                 . "      if( _content[o].className == 'heading'){"
                 . "        _content[o].innerHTML+='<div class=\'msg-box msg-'+type+'\'>'"
                 . "          + '<div class=\'msg-content\'><span class=\'title\'>' + xtype "
                 . "          + ':</span>' + message + '</div></div>'; "
                 . "        break;"
                 . "      }"
                 . "    }"
                 . "  };} "
                 . "</script>";

            echo $str;

            $this->_status = new CustomStatus();
            $this->view->status = new CustomStatus();
        }
        else{
            $this->pleskVersion = 11;
        }


        // Init title for all actions
        $this->view->pageTitle = 'JXcore Plesk Extension for Node';

        require_once("common.php");

        $this->common = new Common($this, $this->view->status);

        $this->_status->addMessage('info', "aaaaa");

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
                    'action' => 'listmodules',
                ),
                array(
                    'title' => 'Monitor log',
                    'action' => 'log'
                ),
                array(
                    'title' => 'Subscriptions',
                    'action' => 'listsubscriptions'
                ),
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

//        $log = "empty";
//        Common::getURL(Common::$urlMonitorLog, $log);
//        $this->view->log = str_replace("\n", "<br>", $log);

        $form = new pm_Form_Simple();
        $sidClearLog = "clear_log";
        $sidLastLinesCount = "last_lines_count";

        $form->addElement('hidden', $sidClearLog, array(
            'value' => "nothing"
        ));

        $form->addElement('simpleText', "size", array(
            'label' => 'Log file',
            'value' => Common::getSimpleButton($sidClearLog, "Clear log", "clear", Common::iconUrlDelete, null, "margin-left: 0px;"),
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
            ),
            'description' => 'Displays only last # lines of the log file. Enter 0 to display the whole log.',
            'escape' => false
        ));

        $form->addControlButtons(array(
            'cancelLink' => null,
            'hideLegend' => true
        ));

        if ($this->getRequest()->isPost() && $form->isValid($this->getRequest()->getPost())) {
            $actionClearValue = $this->getRequest()->getParam($sidClearLog);
            $actionClearPressed = $actionClearValue === "clear";

            $val = $form->getValue($sidLastLinesCount);

            if ($actionClearPressed) {
                Common::callService("delete", "monitorlogs", "Log cleared.", "Problem: ");
            } else {
                pm_Settings::set($sidLastLinesCount . "monitor", $val);
            }
            $this->_helper->json(array('redirect' => Common::$urlJXcoreMonitorLog));
        }

        $log = "";
        Common::getURL(Common::$urlMonitorLog, $log);
        $this->view->log = implode("<br>", array_slice(explode("\n", trim($log)), -$val));

        $this->view->buttonsDisablingScript = Common::getButtonsDisablingScript();
        $this->view->form = $form;
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

                $ok = $this->download_JXcore($out);
                $this->_status->addMessage($ok ? 'info' : 'error', $out);

                Common::enableServices();
                Common::updateAllConfigsIfNeeded();
                Common::monitorStartStop("start");
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

        $json = Common::getMonitorJSON();
        $monitorRunning = $json !== null;

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
                        "JXcore distribution for this platform will be downloaded and installed."
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
                    'description' => $monitorRunning ? "If you stop, all the monitored applications will be terminated!" : "If you start, all the JXcore enabled applications will be launched."
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

                $domainCnt = count(Common::getDomainsIDs());
                $validator = new MyValid_PortMax();
                $validator->newMinimum = $req->getParam(Common::sidJXcoreMinimumPortNumber);
                $form->addElement('text', Common::sidJXcoreMaximumPortNumber, array(
                    'label' => 'Maximum app port number',
                    'value' => Common::$maxApplicationPort,
                    'required' => true,
                    'validators' => array('Int',
                        array("GreaterThan", true, array('min' => $req->getParam(Common::sidJXcoreMinimumPortNumber))),
                        array("Between", true, array('min' => Common::minApplicationPort_default, 'max' => Common::maxApplicationPort_default)),
                        $validator
                    ),
                    'description' => "The port range should be greater than domain count multiplied by two (HTTP + HTTPS). Right now there are $domainCnt domains, and you need two ports for each of them.",
                    'escape' => false
                ));


//                JXconfig::addConfigToForm($form);

                $form->addElement('simpleText', "restartmayoccur", array(
                    'label' => '',
                    'escape' => false,
                    'value' => "<span style='color: red;'>Submitting the form will may result in restarting the monitor together with all of the applications.</span>",
                    'description' => ""
                ));

                $form->addControlButtons(array(
                    'cancelLink' => pm_Context::getModulesListUrl(),
                ));
            }

            if ($req->isPost() && $form->isValid($req->getPost())) {

                $monitorAction = in_array($req->getParam($sidMonitor), ["start", "stop"]);
                $installAction = in_array($req->getParam($sidJXcore), ["install", "uninstall"]);

                if ($monitorAction) {
                    Common::monitorStartStop($req->getParam($sidMonitor));
                } else if ($installAction) {
                    $this->JXcoreInstallUninstall($req->getParam($sidJXcore));
                } else {
//                    $params = [Common::sidMonitorEnabled, Common::sidJXcoreMinimumPortNumber, Common::sidJXcoreMaximumPortNumber];
                    $params = [Common::sidJXcoreMinimumPortNumber, Common::sidJXcoreMaximumPortNumber,

//                        Common::sidDomainJXcoreAppPath, Common::sidDomainAppLogWebAccess,

//                        Common::sidDomainJXcoreAppMaxCPULimit,
//                        Common::sidDomainJXcoreAppMaxCPUInterval,
//                        Common::sidDomainJXcoreAppMaxMemLimit,
//                        Common::sidDomainJXcoreAppAllowCustomSocketPort,
//                        Common::sidDomainJXcoreAppAllowSysExec,
//                        Common::sidDomainJXcoreAppAllowLocalNativeModules
                    ];


                    $portsChanged = false;
                    foreach ($params as $param) {
                        if (pm_Settings::get($param) !== $form->getValue($param)) $portsChanged = true;
                        pm_Settings::set($param, $form->getValue($param));
                    }

                    if ($portsChanged) Common::reassignPorts();
                    Common::updateAllConfigsIfNeeded();

                    $this->_status->addMessage('info', 'Data was successfully saved.');
                }
                $this->_helper->json(array('redirect' => Common::$urlJXcoreConfig));
            }
        }

        $this->view->buttonsDisablingScript = Common::getButtonsDisablingScript();
        $this->view->form = $form;
        Common::check();
    }



    public function listmodulesAction()
    {
        if ($this->redirect(true)) return;

        $form = new pm_Form_Simple();


        $form->addElement('hidden', "remove", array(
            'label' => 'Installed modules',
            'value' => ""
        ));

//        $form->addElement('simpleText', "installedModules", array(
//            'label' => 'Installed modules',
//            'value' => count($installed_modules) ? join("<br>", $installed_modules) : "None",
//            'escape' => false
//        ));

        $nameToInstall = trim($this->getRequest()->getParam("names"));
        $form->addElement('text', "names", array(
            'label' => 'Install new module',
            'value' => $nameToInstall,
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

            $nameToRemove = trim($this->getRequest()->getParam("remove"));
            if ($nameToRemove) {
                Common::callService("remove", $nameToRemove, "Module #arg# was successfully removed.", "Cannot remove #arg# module.");
            } else
            if ($nameToInstall) {
                Common::callService("install", $nameToInstall, "Module #arg# was successfully installed.", "Cannot install #arg# module.");
            }

            $this->_helper->json(array('redirect' => Common::$urlJXcoreModules));
        }

        $this->view->buttonsDisablingScript = Common::getButtonsDisablingScript();
        $this->view->form = $form;

        $this->view->list = $this->getModulesList();
        if ($this->view->list) {
            $this->view->list->setDataUrl(array('action' => 'listmodules-data'));
        }

        Common::check();
    }

    public function listmodulesDataAction()
    {
        $this->listmodulesAction();

        // Json data from pm_View_List_Simple
        $this->_helper->json($this->view->list->fetchData());
        Common::check();
    }

    private function getModulesList() {
        $list = new pm_View_List_Simple($this->view, $this->_request);

        $data = [];
        $info = Common::callService("modules", "info", null, null, true);

        $modules = explode("||", $info);
        foreach($modules as $str) {
            $parsed = explode("|", $str);
            if (count($parsed) == 3) {
                $modules[$parsed[0] . "_version"] = $parsed[1];
                $modules[$parsed[0] . "_description"] = $parsed[2];
            }
        }


        $node_modules = Common::$dirNativeModules . "node_modules/";
//        $installed_modules = [];
        if (file_exists($node_modules)) {
            $d = dir($node_modules);
            while (false !== ($entry = $d->read())) {
                if (substr($entry, 0, 1) !== "." && is_dir($node_modules . $entry)) {

                    $ver = isset($modules[$entry . "_version"]) ? $modules[$entry . "_version"] : "Cannot read version";
                    $desc = isset($modules[$entry . "_description"]) ? $modules[$entry . "_description"] : "Cannot read description";

                    $data[] = array(
                        'column-1' => Common::iconON,
                        'column-2' => $entry,
                        'column-3' => $ver,
                        'column-4' => $desc,
                        'column-5' => Common::getSimpleButton("remove", "Remove", "$entry", Common::iconUrlDelete, null, "margin: 0px;")
                    );

                    //$installed_modules[] = '<div style="width: 120px; display: inline-block">' . Common::getIcon(true, $entry, "") . "</div>" . Common::getSimpleButton("remove", "Remove", "$entry", Common::iconUrlDelete);
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
                'title' => 'Manage',
                'noEscape' => true,
            )
        );

        $list->setColumns($columns);
        // Take into account listDataAction corresponds to the URL /list-data/
        $list->setDataUrl(array('action' => 'listdomains-data'));
        return $list;
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
            $editUrl = $baseUrl . 'config/id/' . $id;

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
        // Take into account listDataAction corresponds to the URL /list-data/
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
        Common::check();
    }

    /**
     * Action for subscription list - when user clicks on table's column header.
     */
    public function listsubscriptionsDataAction()
    {
        $this->listsubscriptionsAction();

        // Json data from pm_View_List_Simple
        $this->_helper->json($this->view->list->fetchData());
        Common::check();
    }


    private function getSubscriptions()
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

        $json = Common::getMonitorJSON();

        // fetching domain list
        $dbAdapter = pm_Bootstrap::getDbAdapter();
        $sql = "SELECT * from `Subscriptions` where object_type = 'domain'";
        $statement = $dbAdapter->query($sql);

        while ($row = $statement->fetch()) {
            $id = intval($row['id']);
            $domainId = intval($row['object_id']);
            $domain = Common::getDomain($domainId);
            $sub = SubscriptionInfo::getSubscription($id);

            if (!Common::$isAdmin && $clid != $domain->row['cl_id']) {
                continue;
            }

            $status = $domain->JXcoreSupportEnabled();
            if ($status != 1) $status = false; else $status = true;

            $baseUrl = pm_Context::getBaseUrl() . 'index.php/subscription/';
            $editUrl = $baseUrl . 'config/id/' . $id;

            $domains = $sub->getDomains();
            $domains_str = "";
            foreach($domains as $d) {
                $domains_str .=  $d->name . "<br>";
//                $domains_str .= $d- $d->getAppStatus() . "<br>";
            }


            $data[] = array(
                'column-1' => $id,
                'column-2' => url($editUrl, $domain->row['displayName']),
                'column-3' => $domains_str,
//                'column-4' => Common::getIcon($status, "Enabled", "Disabled"),
            //    'column-5' => $domain->getAppPortStatus(null, false),
            //    'column-6' =>  $domain->getAppStatus(),
//                'column-7' => url($editUrl, $domain->sysUser),
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
//                        exec("rm -rf $unzippedDir");
                        Common::rmdir($unzippedDir);
                        Common::rmdir(Common::$dirSubscriptionConfigs);
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




    private function JXcoreInstallUninstall($req)
    {
        // shutting down monitor if it's online
        if (in_array($req, ['install', 'uninstall'], true) && Common::isJXValid()) {
            Common::monitorStartStop('stop');
        }

        if ($req === 'install') {
            $out = null;
            $ok = $this->download_JXcore($out);
            $this->_status->addMessage($ok ? 'info' : 'error', $out);

            Common::monitorStartStop('start');
        } else
            if ($req === 'uninstall' && Common::isJXValid()) {

                $dir = dirname(Common::$jxpath) . "/";
                // deleting subscriptions folder, because there are copies of jx binaries there
                Common::rmdir(Common::$dirSubscriptionConfigs);
                // deleting jxcore folder
                $ok = Common::rmdir($dir);
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
//        $this->minimum = Common::$minApplicationPort;
//        $this->maximum = Common::$maxApplicationPort;

        $this->_setValue($value);


        $this->domainCount = count(Common::getDomainsIDs()) * 2;

        if ($value - $this->newMinimum + 1 < $this->domainCount) {
            $this->_error(self::MSG_TOOLITTLE);
            return false;
        }

        return true;
    }
}
