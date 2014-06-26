<?php

class DomainController extends pm_Controller_Action
{
    private $domain = null;

    public function init()
    {
        parent::init();

        // Init title for all actions
        $this->view->pageTitle = 'JXcore - single domain configuration';

        // Init tabs for all actions
        $this->view->tabs = array(
            array(
                'title' => 'Configuration',
                'action' => 'config',
            ),
            array(
                'title' => 'JXcore Node.JS application log ',
                'action' => 'log',
            ),
//            array(
//                'title' => 'Third party apps',
//                'action' => 'third-party',
//            ),
        );

        require_once("common.php");
        $this->common = new Common($this, $this->_status);

        $this->ID = $this->getRequest()->getParam('id');
        if (!ctype_digit($this->ID)) unset($this->ID);

        if (!$this->ID) {
            $this->ID = pm_Settings::get("currentDomainId");

            if (!$this->ID) {
                $this->view->err = "Unknown domain ID";
                return;
            }
        } else {
            pm_Settings::set("currentDomainId", $this->ID);
        }

        $this->domain = Common::getDomain(intval($this->ID));
        $this->view->breadCrumb = 'Navigation: <a href="' . Common::$urlListDomains . '">Domains</a> -> ' . $this->domain->name;
    }

    public function indexAction()
    {
        $this->_forward('config');
    }


    public function configAction()
    {
        $json = "";
        $monitorRunning = Common::getURL(Common::$urlMonitor, $json);
        $appRunning = $this->domain->isAppRunning($json);
        $canEdit = Common::$isAdmin;

        $sidRestart = "restart";

        // Init form here
        $form = new pm_Form_Simple();

        $jxEnabled = $this->domain->JXcoreSupportEnabled();

        $form->addElement('hidden', Common::sidDomainJXcoreEnabled, array(
            'value' => "nothing"
        ));

        $form->addElement('hidden', $sidRestart, array(
            'value' => "nothing"
        ));

        if ($monitorRunning)
            $description = $jxEnabled ? "If you disable JXcore for the domain, the running Node.JS application will be terminated!" : "When you enable JXcore support, JXcore application will also be launched.";
        else
            $description = $jxEnabled ? "" : "When you enable JXcore, it schedules the application to run as soon as possible.";

        // $canEnable = $this->domain->canEnable();
        $canEnable = true;
        $button = $canEnable === true ?
            Common::getButtonStartStop($jxEnabled, Common::sidDomainJXcoreEnabled, ["Enabled", "Enable"], ["Disabled", "Disable"]) :
            Common::getIcon($jxEnabled, "Enabled", "Disabled");

        $restartButton = $monitorRunning && $jxEnabled ? Common::getSimpleButton($sidRestart, "Restart application", "restart", "/theme/icons/16/plesk/show-all.png") : "";

        $form->addElement('simpleText', 'status', array(
            'label' => 'JXcore Node.JS',
            'escape' => false,
            'value' => $button . $restartButton,
            'description' => $description
        ));


//        if ($canEnable !== true) {
//            $form->addElement('simpleText', 'statuserr', array(
//                'label' => '',
//                'escape' => false,
//                'value' => "Cannot enable JXcore: $canEnable",
////                'description' => $description
//            ));
//        }

//        Common::addHR($form);
//
//        $form->addElement('checkbox', Common::sidDomainAppLogWebAccess, array(
//            'label' => 'Application\'s log web access',
//            'description' => "Will be available on http://" . $this->domain->name . "/" . DomainInfo::appLogBasename,
//            'value' => $this->domain->getAppLogWebAccess()
//        ));


//        if (!$canEdit && Common::$isAdmin) {
//            Common::addHR($form);
//            $form->addElement('simpleText', "cannotedit", array(
//                'label' => '',
//                'escape' => false,
//                'value' => "<span style='color: red;'>Options below can be changed only when application is not running (JXcore support is disabled).</span>",
//                'description' => $jxEnabled ? "" : "Application will start automatically when JXcore support is enabled."
//            ));
//        }


        Common::addHR($form);

        $form->addElement('simpleText', "txt1", array(
            'label' => 'Application status',
            'escape' => false,
            'value' => $this->domain->getAppStatus(),
            'description' => $jxEnabled ? "" : "Application will start automatically when JXcore support is enabled."
        ));

        $form->addElement($canEdit ? 'text' : 'simpleText', Common::sidDomainJXcoreAppPath, array(
            'label' => 'Application file path',
            'value' => $this->domain->getAppPathOrDefault(false),
            'required' => false,
            'description' => "The path is relative to domain root folder.",
            'escape' => false
        ));

        if (Common::$isAdmin) {
            $form->addElement('simpleText', 'exampleSimpleText', array(
                'label' => 'Domain root folder',
                'escape' => false,
                'value' => $this->domain->rootFolder
            ));
        }

        self::addConfigToForm($form, $canEdit);

//        Common::addHR($form);
//
//        $validator = new MyValid_NumericBetween();
//        $validator->domainId = $this->ID;
//        $validator->ssl = false;
//        $validator->otherValue = $this->getRequest()->getParam(Common::sidDomainJXcoreAppPortSSL);
//
//
//        $form->addElement($canEdit && Common::$isAdmin ? 'text' : 'simpleText', Common::sidDomainJXcoreAppPort, array(
//            'label' => 'Application\'s TCP port',
//            'value' => $this->domain->getAppPortOrDefault(),
//            'required' => true,
//            'validators' => array(
//                'Int',
//                $validator
//            ),
//            'description' => 'TCP port on which JXcore application is allowed to run.',
//            'escape' => false
//        ));
//
//        $validator2 = new MyValid_NumericBetween();
//        $validator2->domainId = $this->ID;
//        $validator2->ssl = true;
//        $validator2->otherValue = $this->getRequest()->getParam(Common::sidDomainJXcoreAppPort);
//
//        $form->addElement($canEdit && Common::$isAdmin ? 'text' : 'simpleText', Common::sidDomainJXcoreAppPortSSL, array(
//            'label' => 'Application\'s TCPS port',
//            'value' => $this->domain->getAppPortOrDefault(false, true),
//            'required' => true,
//            'validators' => array(
//                'Int',
//                $validator2
//            ),
//            'description' => 'TCP Secure port on which JXcore application is allowed to run.',
//            'escape' => false
//        ));

//        $form->addElement('simpleText', "portrange", array(
//            'label' => 'Available ports range',
//            'value' => Common::$minApplicationPort . " - " . Common::$maxApplicationPort,
//            'description' => "First five available ports: " . join(", ", Common::getFreePorts(null)),
//            'escape' => false
//        ));

//        Common::addHR($form);


        if ($canEdit && $appRunning) {
            Common::addHR($form);
            $form->addElement('simpleText', "someWarning", array(
                'label' => '',
                'escape' => false,
                'value' => "<span style='color: red;'>Submitting the form will restart the application.</span>"
            ));
        }


        $form->addElement('hidden', 'id', array(
            'value' => pm_Settings::get($this->ID),
        ));

        $form->addControlButtons(array(
            'cancelLink' => Common::$urlListDomains
        ));

        if ($this->getRequest()->isPost() && $form->isValid($this->getRequest()->getPost())) {

            $actionValue = $this->getRequest()->getParam(Common::sidDomainJXcoreEnabled);
            $actionButtonPressed = in_array($actionValue, ["start", "stop"]);

            $restartActionValue = $this->getRequest()->getParam($sidRestart);
            $actionRestartPressed = $restartActionValue === "restart";

            if ($actionButtonPressed) {
                pm_Settings::set(Common::sidDomainJXcoreEnabled . $this->ID, $actionValue == "start" ? 1 : 0);
            }


            if ($canEdit && Common::$isAdmin) {
                $params = [Common::sidDomainJXcoreAppPath, Common::sidDomainAppLogWebAccess,
                    Common::sidDomainJXcoreAppMaxCPULimit,
                    Common::sidDomainJXcoreAppMaxCPUInterval,
                    Common::sidDomainJXcoreAppMaxMemLimit,
                    Common::sidDomainJXcoreAppAllowCustomSocketPort,
                    Common::sidDomainJXcoreAppAllowSysExec,
                    Common::sidDomainJXcoreAppAllowLocalNativeModules
                    //  Common::sidDomainJXcoreAppAllowSpawnChild
                ];

            }

            $changed = false;
            foreach ($params as $param) {
                if (pm_Settings::get($param . $this->ID) !== $form->getValue($param))
                    $changed = true;
                pm_Settings::set($param . $this->ID, $form->getValue($param));
//                    $this->_status->addMessage("info", "$param = " . $form->getValue($param));
            }
            $this->_status->addMessage('info', 'Data was successfully saved.');


            if (!file_exists($this->domain->getAppPath(true))) {
                $this->_status->addMessage('warning', 'Application file does not exist on filesystem: ' . $this->domain->getAppPath());
            }

//            $this->_status->addMessage("info", "actionRestartPressed = $actionRestartPressed, changed = $changed");
            if ($appRunning && ($actionRestartPressed || $changed)) {
                $cmd = Common::$jxpath . " monitor kill " . $this->domain->getSpawnerPath() . " 2>&1";
//                $cmd = $this->domain->getSpawnerExitCommand();;
                @exec($cmd, $out, $ret);
                if ($ret && $ret != 77) {
                    $this->_status->addMessage($ret ? "error" : "info", "Cannot stop the application: " . join("\n", $out) . ". Exit code: $ret");
                }
            }

            Common::updateBatchAndCron($this->ID);
            $ret = Common::updatehtaccess($this->ID);
            if ($ret !== true) $this->_status->addMessage('error', $ret);

            $this->_helper->json(array('redirect' => Common::$urlDomainConfig));
        }

        $this->view->buttonsDisablingScript = Common::getButtonsDisablingScript();
        $this->view->form = $form;
    }


    private function addConfigToForm(&$form, $canEdit = null) {
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

        $val = pm_Settings::get(Common::sidDomainJXcoreAppMaxMemLimit . $this->ID);
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

        $val = pm_Settings::get(Common::sidDomainJXcoreAppMaxCPULimit . $this->ID);
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


        $val = pm_Settings::get(Common::sidDomainJXcoreAppMaxCPUInterval . $this->ID);
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



        $val = pm_Settings::get(Common::sidDomainJXcoreAppAllowCustomSocketPort . $this->ID);
        $form->addElement($typeChk, $canEdit ? Common::sidDomainJXcoreAppAllowCustomSocketPort : ("field" . ($tmpID++)), array(
            'label' => 'Allow custom socket port',
            'description' => "",
            'value' => $canEdit ? $val : ($val === "1" ? "Allow" : "Disallow")
        ));

        $val = pm_Settings::get(Common::sidDomainJXcoreAppAllowSysExec . $this->ID);
        $form->addElement($typeChk, $canEdit ? Common::sidDomainJXcoreAppAllowSysExec : ("field" . ($tmpID++)), array(
            'label' => 'Allow to spawn/exec child processes',
            'description' => "",
            'value' => $canEdit ? $val : ($val === "1" ? "Allow" : "Disallow")
        ));

        $val = pm_Settings::get(Common::sidDomainJXcoreAppAllowLocalNativeModules . $this->ID);
        $form->addElement($typeChk, $canEdit ? Common::sidDomainJXcoreAppAllowLocalNativeModules : ("field" . ($tmpID++)), array(
            'label' => 'Allow to call local native modules',
            'description' => "",
            'value' => $canEdit ? $val : ($val === "1" ? "Allow" : "Disallow")
        ));

        Common::addHR($form);

        $val = $this->domain->getAppLogWebAccess();
        $form->addElement($typeChk, $canEdit ? Common::sidDomainAppLogWebAccess : ("field" . ($tmpID++)), array(
            'label' => 'Application\'s log web access',
            'description' => "Will be available on http://" . $this->domain->name . "/" . basename($this->domain->appLogDir),
            'value' => $canEdit ? $val : ($val === "1" ? "Enabled" : "Disabled")
        ));

//        $form->addElement('checkbox', Common::sidDomainAppLogWebAccess, array(
//            'label' => 'Application\'s log web access',
//            'description' => "Will be available on http://" . $this->domain->name . "/" . DomainInfo::appLogBasename,
//            'value' => $this->domain->getAppLogWebAccess()
//        ));

    }


    public function logAction()
    {
        $form = new pm_Form_Simple();
        $sidClearLog = "clear_log";
        $sidLastLinesCount = "last_lines_count";

        $form->addElement('hidden', $sidClearLog, array(
            'value' => "nothing"
        ));

        $form->addElement('simpleText', "size", array(
            'label' => 'Log file size',
            'value' => filesize($this->domain->appLogPath) . " bytes" . Common::getSimpleButton($sidClearLog, "Clear log", "clear", Common::iconUrlDelete, null),
            'escape' => false
        ));

        $val = pm_Settings::get($sidLastLinesCount . $this->ID);
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
                $ret = $this->domain->clearLogFile();
                if ($ret === false) {
                    $this->_status->addMessage('error', 'Could not clear the log file.');
                } else {
                    $this->_status->addMessage('info', 'Log cleared.');
                }
            } else {
                pm_Settings::set($sidLastLinesCount . $this->ID, $val);
            }
            $this->_helper->json(array('redirect' => Common::$urlDomainAppLog));
        }

        $this->readLog($val);

        $this->view->buttonsDisablingScript = Common::getButtonsDisablingScript();
        $this->view->form = $form;
    }

    private function readLog($tail)
    {
       // $this->_status->addMessage("info", "last lines " . $tail);
        if (file_exists($this->domain->appLogPath)) {
            if (!ctype_digit($tail) || $tail == 0) {
                $contents = file_get_contents($this->domain->appLogPath);
                $contents = str_replace("\n", "<br>", $contents);
            } else {
                $file = file($this->domain->appLogPath);
                $contents = implode("<br>", array_slice($file, -$tail));
            }
        } else {
            $contents = "No log file. " . $this->domain->appLogPath;
        }

        if (trim($contents) === "") {
            $contents = "The log file is empty.";
        }
        $this->view->log = $contents;
    }

    public function thirdPartyAction()
    {
        $form = new pm_Form_Simple();
        $sidGhostBlogging = "ghost_blogging";
        $sidLastLinesCount = "last_lines_count";

        $form->addElement('hidden', $sidGhostBlogging, array(
            'value' => "nothing"
        ));

        $ghostInstalled = false;

        $form->addElement('simpleText', "ghost", array(
            'label' => 'Ghost blogging',
            'value' => Common::getButtonStartStop($ghostInstalled, $sidGhostBlogging, ["Installed", "Install"], ["Not installed", "Remove"]),
            'escape' => false
        ));

//        $val = pm_Settings::get($sidLastLinesCount . $this->ID);
//        if (!$val) $val = 200;
//        $form->addElement('text', $sidLastLinesCount, array(
//            'label' => 'Show last # lines',
//            'value' => $val,
//            'required' => true,
//            'validators' => array(
//                'Int',
//            ),
//            'description' => 'Displays only last # lines of the log file. Enter 0 to display the whole log.',
//            'escape' => false
//        ));
//
//        $form->addControlButtons(array(
//            'cancelLink' => null,
//            'hideLegend' => true
//        ));

        if ($this->getRequest()->isPost() && $form->isValid($this->getRequest()->getPost())) {
            $actionGhostValue = $this->getRequest()->getParam($sidGhostBlogging);
            $actionGhostPressed = in_array($actionGhostValue, ["start", "stop"]);

            $val = $form->getValue($sidLastLinesCount);

            if ($actionGhostPressed) {
//                $ret = $this->domain->clearLogFile();
//                if ($ret === false) {
//                    $this->_status->addMessage('error', 'Could not clear the log file.');
//                } else {
                $this->_status->addMessage('info', 'Ghost install presswed.');
//                }
            } else {
//                pm_Settings::set($sidLastLinesCount . $this->ID, $val);
            }
            $this->_helper->json(array('redirect' => Common::$urlDomainAppLog));
        }

        $this->view->buttonsDisablingScript = Common::getButtonsDisablingScript();
        $this->view->form = $form;
    }

}


class MyValid_NumericBetween extends Zend_Validate_Abstract
{
    const MSG_MINIMUM = 'msgMinimum';
    const MSG_MAXIMUM = 'msgMaximum';
    const MSG_BUSY = "msgBusy";
    const MSG_DIFFERENT = "msgDifferent";

    public $minimum = 0;
    public $maximum = 0;
    public $free = "";

    protected $_messageVariables = array(
        'min' => 'minimum',
        'max' => 'maximum',
        'free' => 'free'
    );

    protected $_messageTemplates = array(
        self::MSG_MINIMUM => "'%value%' must be at least '%min%'",
        self::MSG_MAXIMUM => "'%value%' must be no more than '%max%'",
        self::MSG_BUSY => "'%value%' is already taken. %free%'",
        self::MSG_DIFFERENT => "TCP and TCPS port values cannot have the same values"
    );

    public function isValid($value)
    {
        $this->minimum = Common::$minApplicationPort;
        $this->maximum = Common::$maxApplicationPort;

        $this->_setValue($value);


        if ($value < $this->minimum) {
            $this->_error(self::MSG_MINIMUM);
            return false;
        }

        if ($value > $this->maximum) {
            $this->_error(self::MSG_MAXIMUM);
            return false;
        }

        if ($value == $this->otherValue) {
            $this->_error(self::MSG_DIFFERENT);
            return false;
        }

        $takenPorts = Common::getTakenAppPorts($this->domainId, $this->ssl);

        if (in_array($value, $takenPorts)) {
            $freePorts = Common::getFreePortsFromTaken($takenPorts);

            if(($key = array_search($this->otherValue, $freePorts)) !== false) {
                unset($freePorts[$key]);
            }

            $this->free = "Try one of the following: " . join(", ", $freePorts);
            $this->_error(self::MSG_BUSY);
            return false;
        }

        return true;
    }
}

