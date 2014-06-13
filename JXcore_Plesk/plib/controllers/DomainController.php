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
                'title' => 'JXcore application log ',
                'action' => 'log',
            ),
            array(
                'title' => 'Third party apps',
                'action' => 'third-party',
            ),
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

        $this->domain = new DomainInfo(intval($this->ID));
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
        $canEdit = !$appRunning;

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
            $description = $jxEnabled ? "When you disable JXcore support, if there is running JXcore application, it will be terminated!" : "When you enable JXcore support, JXcore application will also be launched.";
        else
            $description = $jxEnabled ? "" : "When you enable JXcore support, JXcore application will be scheduled to be launched as soon as JXcore Monitor will be started by an administrator.";

        $canEnable = $this->domain->canEnable();
        $button = $canEnable === true ?
            Common::getButtonStartStop($jxEnabled, Common::sidDomainJXcoreEnabled, ["Enabled", "Enable"], ["Disabled", "Disable"]) :
            Common::getIcon($jxEnabled, "Enabled", "Disabled");

        $restartButton = $monitorRunning && $jxEnabled ? Common::getSimpleButton($sidRestart, "Restart application", "restart", "/theme/icons/16/plesk/show-all.png") : "";

        $form->addElement('simpleText', 'status', array(
            'label' => 'JXcore support',
            'escape' => false,
            'value' => $button . $restartButton,
            'description' => $description
        ));

        if ($canEnable !== true) {
            $form->addElement('simpleText', 'statuserr', array(
                'label' => '',
                'escape' => false,
                'value' => "Cannot enable JXcore support: $canEnable",
//                'description' => $description
            ));
        }

        Common::addHR($form);

        $form->addElement('checkbox', Common::sidDomainAppLogWebAccess, array(
            'label' => 'Application\'s log web access',
            'description' => "Will be available on http://" . $this->domain->name . "/" . DomainInfo::appLogBasename,
            'value' => $this->domain->getAppLogWebAccess()
        ));


        if (!$canEdit) {
            Common::addHR($form);
            $form->addElement('simpleText', "cannotedit", array(
                'label' => '',
                'escape' => false,
                'value' => "<span style='color: red;'>Options below can be changed only when application is not running (JXcore support is disabled).</span>",
                'description' => $jxEnabled ? "" : "Application will start automatically when JXcore support will be enabled."
            ));
        }


        Common::addHR($form);

        $form->addElement('simpleText', "txt1", array(
            'label' => 'Application status',
            'escape' => false,
            'value' => $this->domain->getAppStatus(),
            'description' => $jxEnabled ? "" : "Application will start automatically when JXcore support will be enabled."
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

        Common::addHR($form);

        $validator = new MyValid_NumericBetween();
        $validator->domainId = $this->ID;


        $form->addElement($canEdit ? 'text' : 'simpleText', Common::sidDomainJXcoreAppPort, array(
            'label' => 'Application\'s port',
            'value' => $this->domain->getAppPortOrDefault(),
            'required' => true,
            'validators' => array(
                'Int',
                $validator
            ),
            'description' => 'Port on which JXcore application is running.',
            'escape' => false
        ));

        $form->addElement('simpleText', "portrange", array(
            'label' => 'Available ports range',
            'value' => Common::$minApplicationPort . " - " . Common::$maxApplicationPort,
            'description' => "First five available ports: " . join(", ", Common::getFreePorts(null)),
            'escape' => false
        ));

        Common::addHR($form);

        $val = pm_Settings::get(Common::sidDomainJXcoreAppMaxCPULimit . $this->ID);
        $form->addElement($canEdit ? 'text' : 'simpleText', $canEdit ? Common::sidDomainJXcoreAppMaxCPULimit : "cpu", array(
            'label' => 'Max CPU',
            'value' => $canEdit ? $val : ($val ? "$val %" : "disabled"),
            'required' => false,
            'validators' => array(
                'Int', array("Between", true, array('min' => 1, 'max' => 100))
            ),
            'description' => 'Maximum CPU usage allowed for the application (percentage: 1-100).',
            'escape' => false
        ));

        $val = pm_Settings::get(Common::sidDomainJXcoreAppMaxMemLimit . $this->ID);
        $form->addElement($canEdit ? 'text' : 'simpleText', $canEdit ? Common::sidDomainJXcoreAppMaxMemLimit : "mem", array(
            'label' => 'Max MEM',
            'value' => $canEdit ? $val : ($val ? "$val kb" : "disabled"),
            'required' => false,
            'validators' => array(
                'Int', //array("Between", true, array( 'min' => 1, 'max' => 100))
            ),
            'description' => 'Maximum size of memory allocated by the the application (kilobytes).',
            'escape' => false
        ));

        $val = pm_Settings::get(Common::sidDomainJXcoreAppAllowSpawnChild . $this->ID);
        $form->addElement($canEdit ? 'checkbox' : 'simpleText', $canEdit ? Common::sidDomainJXcoreAppAllowSpawnChild : "child", array(
            'label' => 'Prevent spawning other processes',
            'value' => $canEdit ? $val : ($val === "1" ? "Allow" : "Disallow"),
            'escape' => false
        ));

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
            } else if ($actionRestartPressed) {
                $cmd = Common::$jxpath . " monitor kill " . $this->domain->getSpawnerPath() . " 2>&1";
                @exec($cmd, $out, $ret);
                if ($ret && $ret != 77) {
                    $this->_status->addMessage($ret ? "error" : "info", "Cannot stop the application: " . join("\n", $out) . ". Exit code: $ret");
                } //else {
//                    $this->_status->addMessage("info", "Application is stopped.");
//                }
                // application will start in updateBatchAndCron()
            } else {
                if ($canEdit) {
                    $params = [Common::sidDomainJXcoreAppPath, Common::sidDomainJXcoreAppPort, Common::sidDomainAppLogWebAccess,
                        Common::sidDomainJXcoreAppMaxCPULimit, Common::sidDomainJXcoreAppMaxMemLimit,
                        Common::sidDomainJXcoreAppAllowSpawnChild];


                } else {
                    $params = [Common::sidDomainAppLogWebAccess];
                }

                foreach ($params as $param) {
                    pm_Settings::set($param . $this->ID, $form->getValue($param));
//                        $this->_status->addMessage("info", "$param = " . $form->getValue($param));
                }
                $this->_status->addMessage('info', 'Data was successfully saved.');


                if (!file_exists($this->domain->getAppPath(false, true))) {
                    $this->_status->addMessage('warning', 'Application file does not exist on filesystem: ' . $this->domain->getAppPath());
                }

            }

            Common::updateBatchAndCron($this->ID);
            $ret = Common::updatehtaccess($this->ID);
            if ($ret !== true) $this->_status->addMessage('error', $ret);

            $this->_helper->json(array('redirect' => Common::$urlDomainConfig));
        }

        $this->view->form = $form;
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
        if (!$val) $val = 200;
        $form->addElement('text', $sidLastLinesCount, array(
            'label' => 'Show last # lines',
            'value' => $val,
            'required' => true,
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

        $this->view->form = $form;
    }

    private function readLog($tail)
    {
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
        $this->view->form = $form;
    }

}


class MyValid_NumericBetween extends Zend_Validate_Abstract
{
    const MSG_MINIMUM = 'msgMinimum';
    const MSG_MAXIMUM = 'msgMaximum';
    const MSG_BUSY = "msgBusy";

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
        self::MSG_BUSY => "'%value%' is already taken. %free%'"
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


        $takenPorts = Common::getTakenAppPorts($this->domainId);

        if (in_array($value, $takenPorts)) {
            $freePorts = Common::getFreePortsFromTaken($takenPorts);
            $this->free = "Try one of the following: " . join(", ", $freePorts);
            $this->_error(self::MSG_BUSY);
            return false;
        }

        return true;
    }
}

