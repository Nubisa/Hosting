<?php

class DomainController extends pm_Controller_Action
{
    private $domain = null;

    public function init()
    {
        parent::init();

        $this->view->pageTitle = 'JXcore - single domain configuration';

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
        $this->view->breadCrumb = 'Navigation: <a href="' . Common::$urlJXcoreDomains . '">Domains</a> -> ' . $this->domain->name;
    }

    public function indexAction()
    {
        $this->_forward('config');
    }


    public function configAction()
    {
        $json = Common::getMonitorJSON();
        $monitorRunning = $json !== null;
        $appRunning = $this->domain->isAppRunning();
        $canEdit = Common::$isAdmin;

        $sidRestart = "restart";

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

        Common::addHR($form);

        $form->addElement('simpleText', "txt1", array(
            'label' => 'Application status',
            'escape' => false,
            'value' => $this->domain->getAppStatus(),
            'description' => $jxEnabled ? "" : "Application will start automatically when JXcore support is enabled."
        ));

        $validFileName = $canEdit ? new MyValid_FileName() : null;
        if ($validFileName) $validFileName->domain = $this->domain;
        $form->addElement($canEdit ? 'text' : 'simpleText', Common::sidDomainJXcoreAppPath, array(
            'label' => 'Application file path',
            'value' => $this->domain->getAppPathOrDefault(false),
            'validators' => array($validFileName),
            'filters' => array('StringTrim'),
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

        JXconfig::addConfigToForm($form, $this->ID, true);

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
            'cancelLink' => Common::$urlJXcoreDomains
        ));

        if ($this->getRequest()->isPost() && $form->isValid($this->getRequest()->getPost())) {

            $actionValue = $this->getRequest()->getParam(Common::sidDomainJXcoreEnabled);
            $actionButtonPressed = in_array($actionValue, ["start", "stop"]);

            $restartActionValue = $this->getRequest()->getParam($sidRestart);
            $actionRestartPressed = $restartActionValue === "restart";

            if ($actionButtonPressed) {
                $this->domain->set(Common::sidDomainJXcoreEnabled, $actionValue == "start" ? 1 : 0);
            } else
                if (!$actionButtonPressed && !$actionRestartPressed && $canEdit) {

                    $params = [Common::sidDomainJXcoreAppPath, Common::sidDomainAppLogWebAccess];

                    foreach ($params as $param) {
                        $this->domain->set($param, $form->getValue($param));
                    }

                    JXconfig::saveDomainValues($form, $this->domain);
                    StatusMessage::dataSavedOrNot($this->domain->configChanged);
                }


            if (!file_exists($this->domain->getAppPath(true))) {
                $this->_status->addMessage('warning', 'Application file does not exist on filesystem: ' . $this->domain->getAppPath());
            }

            if ($actionRestartPressed) {
                $this->domain->configChanged = true;
            }
            Common::updateAllConfigsIfNeeded();

            $this->_helper->json(array('redirect' => Common::$urlDomainConfig));
        }

        $this->view->buttonsDisablingScript = Common::getButtonsDisablingScript();
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
                $this->domain->clearLogFile();
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

class MyValid_FileName extends Zend_Validate_Abstract
{
    const MSG_CANNOTCONTAIN = 'msgCannotContain';
    const MSG_CANNOTSTART = 'msgCannotStart';
    const MSG_ISADIR = 'msgIsaDir';

    public $cannotContain = 0;
    public $cannotStart = 0;
    public $domain = null;

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
        $value = trim($value);
        //if (substr($value, 0, 1) == "/") $value = substr($value, 1);

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


//            $this->cannotStart = substr($value, 0, strlen($str));
//            $this->_error(self::MSG_CANNOTSTART);
//            return false;

            if (substr($value, 0, strlen($str)) === $str) {
                $this->cannotStart = $str;
                $this->_error(self::MSG_CANNOTSTART);
                return false;
            }
        }

        $fullPath = $this->domain->rootFolder . $value;
        if (is_dir($fullPath)) {
            $this->cannotContain = $str;
            $this->_error(self::MSG_ISADIR);
            return false;
        }

        return true;
    }
}