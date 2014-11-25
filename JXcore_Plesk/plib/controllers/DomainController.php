<?php

/* Copyright Nubisa, Inc. 2014. All Rights Reserved */


class DomainController extends pm_Controller_Action
{
    private $domain = null;

    public function init()
    {
        parent::init();

        if(Modules_JxcoreSupport_CustomStatus::CheckStatusRender($this)) // Plesk12
        {
            $this->_status = new Modules_JxcoreSupport_CustomStatus($this->view);
            $this->view->status = new Modules_JxcoreSupport_CustomStatus($this->view);
        }

        $this->view->pageTitle = 'JXcore - single domain configuration';

        $this->view->tabs = array(
            array(
                'title' => 'Configuration',
                'action' => 'config',
            ),
            array(
                'title' => 'JXcore Application Log ',
                'action' => 'log',
            )
        );

        $this->common = new Modules_JxcoreSupport_Common($this, $this->_status);

        $this->ID = $this->getRequest()->getParam('id');
        if (!ctype_digit($this->ID)) unset($this->ID);

        if (!$this->ID) {
            $this->ID = pm_Settings::get("currentDomainId" . pm_Session::getClient()->getId());

            if (!$this->ID)
                return $this->setError("Unknown domain ID");

        } else {
            pm_Settings::set("currentDomainId" . pm_Session::getClient()->getId(), $this->ID);
        }

        $this->domain = Modules_JxcoreSupport_Common::getDomain(intval($this->ID));
        $this->view->breadCrumb = 'Navigation: <a href="' . Modules_JxcoreSupport_Common::$urlJXcoreDomains . '">Domains</a> -> ' . $this->domain->name;
    }

    private function setError($err) {
        $this->view->err = $err;
        $this->view->breadCrumb = "";
        $this->view->tabs = "";
        return false;
    }

    private function check() {

        if (isset($this->view->err))
            return false;

        $this->view->err = "";

        if (!Modules_JxcoreSupport_Common::isJXValid())
            return $this->setError("Access denied. JXcore is not installed.");

        // user can edit only his own domains
        if (!Modules_JxcoreSupport_Common::$isAdmin) {
            $ids = Modules_JxcoreSupport_Common::getDomainsIDsForLoggedClient();
            if (!in_array($this->ID, $ids))
                return $this->setError("Access denied.");
        }

        if (!$this->domain)
            return $this->setError("Access denied.");

        // also if subscription is disabled, nobody can manage the domain, even the admin
        $sub = $this->domain->getSubscription();
        if (!$sub)
            return $this->setError("Invalid subscription ID.");

        // also admin should not go in there
        if (!$sub->JXcoreSupportEnabled())
            return $this->setError("Access denied.");

        return true;
    }

    public function indexAction()
    {
        if (!$this->check())
            return;

        $this->_forward('config');
    }


    public function configAction()
    {
        if (!$this->check())
            return;

        $json = Modules_JxcoreSupport_Common::getMonitorJSON();
        $monitorRunning = $json !== null;
        $appRunning = $this->domain->isAppRunning();

        $sidRestart = "restart";

        $form = new pm_Form_Simple();

        $jxEnabled = $this->domain->JXcoreSupportEnabled_Value();

        $form->addElement('hidden', Modules_JxcoreSupport_Common::sidDomainJXcoreEnabled, array(
            'value' => "nothing"
        ));

        $form->addElement('hidden', $sidRestart, array(
            'value' => "nothing"
        ));

        if ($monitorRunning)
            $description = $jxEnabled ? "If you disable JXcore for the domain, the running Node application will be terminated!" : "When you enable JXcore support, JXcore application will also be launched.";
        else
            $description = $jxEnabled ? "" : "When you enable JXcore, it schedules the application to run as soon as possible.";

        // $canEnable = $this->domain->canEnable();
        $canEnable = true;
        $button = $canEnable === true ?
            Modules_JxcoreSupport_Common::getButtonStartStop($jxEnabled, Modules_JxcoreSupport_Common::sidDomainJXcoreEnabled, array("Enabled", "Enable"), array("Disabled", "Disable")) :
            Modules_JxcoreSupport_Common::getIcon($jxEnabled, "Enabled", "Disabled");

        $restartButton = "";
        $sub = $this->domain->getSubscription();
        if ($sub && $sub->JXcoreSupportEnabled())
            $restartButton = $monitorRunning && $jxEnabled ? Modules_JxcoreSupport_Common::getSimpleButton($sidRestart, "Restart application", "restart", "/theme/icons/16/plesk/show-all.png") : "";

        $form->addElement('simpleText', 'status', array(
            'label' => 'JXcore',
            'escape' => false,
            'value' => $button . $restartButton,
            'description' => $description
        ));

        Modules_JxcoreSupport_Common::addHR($form);

        $form->addElement('simpleText', "txt1", array(
            'label' => 'Application status',
            'escape' => false,
            'value' => $this->domain->getAppStatus(),
            'description' => $jxEnabled ? "" : "Application will start automatically when JXcore support is enabled."
        ));

        $validFileName =  new MyValid_FileName();
        $validFileName->domain = $this->domain;
        $form->addElement('text', Modules_JxcoreSupport_Common::sidDomainJXcoreAppPath, array(
            'label' => 'Application file path',
            'value' => $this->domain->getAppPathOrDefault(false),
            'validators' => array($validFileName),
            'filters' => array('StringTrim'),
            'required' => false,
            'description' => "The path is relative to domain root folder.",
            'escape' => false,
            'size' => 80
        ));

        $validArgs = new MyValid_AppArgs();
        $form->addElement('text', Modules_JxcoreSupport_Common::sidDomainJXcoreAppArgs, array(
            'label' => 'Application parameters',
            'value' => $this->domain->get(Modules_JxcoreSupport_Common::sidDomainJXcoreAppArgs),
            'validators' => array($validArgs),
            'filters' => array('StringTrim'),
            'required' => false,
            'description' => "Command-line arguments for the application. They will be also visible in `process.argv` property.",
            'escape' => false,
            'size' => 80
        ));

        if (Modules_JxcoreSupport_Common::$isAdmin) {
            $form->addElement('simpleText', 'exampleSimpleText', array(
                'label' => 'Domain root folder',
                'escape' => false,
                'value' => $this->domain->rootFolder
            ));
        }

        Modules_JxcoreSupport_Common::addHR($form);

        $form->addElement('checkbox', Modules_JxcoreSupport_Common::sidDomainAppUseSSL, array(
            'label' => 'Enable SSL',
            'value' => $this->domain->get(Modules_JxcoreSupport_Common::sidDomainAppUseSSL)
        ));

        $form->addElement('simpleText', "sslInfo", array(
            'label' => '',
            'escape' => false,
            'value' => "<span style='color: gray; margin-top: -40px; margin-bottom: 20px;'>When you enable SSL option, no changes in Node application are required. Just keep non-SSL (http) server running in your application, and SSL will be applied automatically with certificate files provided below.</span><br>&nbsp;"
        ));

        $validFileName_cert =  new MyValid_CertFileName();
        $validFileName_cert->domain = $this->domain;
        $form->addElement('text', Modules_JxcoreSupport_Common::sidDomainAppSSLCert, array(
            'label' => 'SSL certificate file',
            'value' => $this->domain->get(Modules_JxcoreSupport_Common::sidDomainAppSSLCert),
            'validators' => array($validFileName_cert),
            'filters' => array('StringTrim'),
            'required' => $this->getRequest()->getParam(Modules_JxcoreSupport_Common::sidDomainAppUseSSL),
            'description' => "The path is relative to domain root folder, e.g. `certificates/my_domain.cert`",
            'escape' => false
        ));

        $validFileName_key =  new MyValid_CertFileName();
        $validFileName_key->domain = $this->domain;
        $form->addElement('text', Modules_JxcoreSupport_Common::sidDomainAppSSLKey, array(
            'label' => 'SSL certificate key file',
            'value' => $this->domain->get(Modules_JxcoreSupport_Common::sidDomainAppSSLKey),
            'validators' => array($validFileName_key),
            'filters' => array('StringTrim'),
            'required' => $this->getRequest()->getParam(Modules_JxcoreSupport_Common::sidDomainAppUseSSL),
            'description' => "The path is relative to domain root folder, e.g. `certificates/my_domain.key`",
            'escape' => false
        ));


        JXconfig::addConfigToForm($form, $this->ID, true);

        Modules_JxcoreSupport_Common::addHR($form);

        $val = $this->domain->getAppLogWebAccess();
        $form->addElement('checkbox', Modules_JxcoreSupport_Common::sidDomainAppLogWebAccess, array(
            'label' => 'Application\'s log web access',
            'description' => "Will be available on http://" . $this->domain->name . "/" . basename($this->domain->appLogDir) . "/index.txt",
            'value' => $val
        ));

        if ($appRunning) {
            Modules_JxcoreSupport_Common::addHR($form);
            $form->addElement('simpleText', "someWarning", array(
                'label' => '',
                'escape' => false,
                'value' => "<span style='color: red;'>Submitting the form will restart the application.</span>"
            ));
        }


        $form->addElement('hidden', 'id', array(
            'value' => pm_Settings::get($this->ID),
        ));

        Modules_JxcoreSupport_Common::addHR($form);


        if (Modules_JxcoreSupport_Common::$isAdmin) {
            $validNginx =  new MyValid_NginxDirectives();
            $validNginx->domain = $this->domain;
            $form->addElement('textarea', Modules_JxcoreSupport_Common::sidDomainAppNginxDirectives, array(
                'label' => 'nginx directives',
                'escape' => false,
                'rows' => 4,
                'validators' => array($validNginx),
                'value' => $this->domain->get(Modules_JxcoreSupport_Common::sidDomainAppNginxDirectives),
                'description' => "Here you can specify the settings for the nginx reverse proxy server that runs in front of Apache. Use the same syntax as you use for nginx.conf. For example, if you want to pack all the proxied requests with gzip, add the line: 'gzip_proxied any;'."
            ));
        }


        $form->addControlButtons(array(
            'cancelLink' => Modules_JxcoreSupport_Common::$urlJXcoreDomains
        ));

        if ($this->getRequest()->isPost() && $form->isValid($this->getRequest()->getPost())) {
            $this->_status->beforeRedirect = true;

            $actionValue = $this->getRequest()->getParam(Modules_JxcoreSupport_Common::sidDomainJXcoreEnabled);
            $actionButtonPressed = in_array($actionValue, array("start", "stop"));

            $restartActionValue = $this->getRequest()->getParam($sidRestart);
            $actionRestartPressed = $restartActionValue === "restart";

            if ($actionButtonPressed) {
                $this->domain->set(Modules_JxcoreSupport_Common::sidDomainJXcoreEnabled, $actionValue == "start" ? 1 : 0);
            } else
                if (!$actionButtonPressed && !$actionRestartPressed) {

                    $params = array(
                        Modules_JxcoreSupport_Common::sidDomainJXcoreAppPath,
                        Modules_JxcoreSupport_Common::sidDomainJXcoreAppArgs,
                        Modules_JxcoreSupport_Common::sidDomainAppLogWebAccess,
                        Modules_JxcoreSupport_Common::sidDomainAppUseSSL,
                        Modules_JxcoreSupport_Common::sidDomainAppSSLCert,
                        Modules_JxcoreSupport_Common::sidDomainAppSSLKey
                    );

                    if (Modules_JxcoreSupport_Common::$isAdmin)
                        $params[] = Modules_JxcoreSupport_Common::sidDomainAppNginxDirectives;

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
            Modules_JxcoreSupport_Common::updateAllConfigsIfNeeded();

            $this->_helper->json(array('redirect' => Modules_JxcoreSupport_Common::$urlDomainConfig));
        }

        $this->view->buttonsDisablingScript = Modules_JxcoreSupport_Common::getButtonsDisablingScript();
        $this->view->form = $form;
    }

    public function logAction()
    {
        if (!$this->check())
            return;

        $form = new pm_Form_Simple();
        $sidClearLog = "clear_log";
        $sidLastLinesCount = "last_lines_count";

        $form->addElement('hidden', $sidClearLog, array(
            'value' => "nothing"
        ));

        $form->addElement('simpleText', "size", array(
            'label' => 'Log file size',
            'value' => filesize($this->domain->appLogPath) . " bytes" . Modules_JxcoreSupport_Common::getSimpleButton($sidClearLog, "Clear log", "clear", Modules_JxcoreSupport_Common::iconUrlDelete, null),
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
                $this->domain->clearLogFile();
            } else {
                pm_Settings::set($sidLastLinesCount . $this->ID, $val);
            }
            $this->_helper->json(array('redirect' => Modules_JxcoreSupport_Common::$urlDomainAppLog));
        }

        $this->readLog($val);

        $this->view->buttonsDisablingScript = Modules_JxcoreSupport_Common::getButtonsDisablingScript();
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
        if (!$this->check())
            return;

        $form = new pm_Form_Simple();
        $sidGhostBlogging = "ghost_blogging";
        $sidLastLinesCount = "last_lines_count";

        $form->addElement('hidden', $sidGhostBlogging, array(
            'value' => "nothing"
        ));

        $ghostInstalled = false;

        $form->addElement('simpleText', "ghost", array(
            'label' => 'Ghost blogging',
            'value' => Modules_JxcoreSupport_Common::getButtonStartStop($ghostInstalled, $sidGhostBlogging, array("Installed", "Install"), array("Not installed", "Remove")),
            'escape' => false
        ));

        if ($this->getRequest()->isPost() && $form->isValid($this->getRequest()->getPost())) {
            $this->_status->beforeRedirect = true;
            $actionGhostValue = $this->getRequest()->getParam($sidGhostBlogging);
            $actionGhostPressed = in_array($actionGhostValue, array("start", "stop"));

            $val = $form->getValue($sidLastLinesCount);

            if ($actionGhostPressed) {
                $this->_status->addMessage('info', 'Ghost install presswed.');
            }
            $this->_helper->json(array('redirect' => Modules_JxcoreSupport_Common::$urlDomainAppLog));
        }

        $this->view->buttonsDisablingScript = Modules_JxcoreSupport_Common::getButtonsDisablingScript();
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

        $fullPath = $this->domain->rootFolder . $value;
        if (is_dir($fullPath)) {
            $this->cannotContain = $str;
            $this->_error(self::MSG_ISADIR);
            return false;
        }

        return true;
    }
}



class MyValid_NginxDirectives extends Zend_Validate_Abstract
{
    const MSG_ERR = 'msgErr';

    public $domain = null;
    public $msgErr = "";

    protected $_messageVariables = array(
        'msgErr' => 'msgErr'
    );

    protected $_messageTemplates = array(
        self::MSG_ERR => "Provided directives failed on test. %msgErr%"
    );

    public function isValid($value)
    {
        $value = trim($value);

        $this->_setValue($value);

        $opt = $this->domain->getSpawnerParams(false, $value);
        $ret = Modules_JxcoreSupport_Common::callService("nginx", "test&opt=" . $opt, null, null, true);

        if ($ret !== "OK") {
            $this->msgErr = html_entity_decode($ret);
            $this->_error(self::MSG_ERR);
            return false;
        }

        return true;
    }
}



class MyValid_CertFileName extends Zend_Validate_Abstract
{
    const MSG_CANNOTCONTAIN = 'msgCannotContain';
    const MSG_CANNOTSTART = 'msgCannotStart';
    const MSG_ISADIR = 'msgIsaDir';
    const MSG_NOTEXISTS = 'msgNotExists';

    public $cannotContain = 0;
    public $cannotStart = 0;
    public $domain = null;
    public $enableSSL = false;

    protected $_messageVariables = array(
        'cannotContain' => 'cannotContain',
        'cannotStart' => 'cannotStart'
    );

    protected $_messageTemplates = array(
        self::MSG_CANNOTCONTAIN => "The file name cannot contain '%cannotContain%'.",
        self::MSG_CANNOTSTART => "The file name cannot start with a '%cannotStart%'.",
        self::MSG_ISADIR => "Provided path exists and is a directory.",
        self::MSG_NOTEXISTS => "The file does not exist."
    );

    public function isValid($value)
    {
        $value = trim($value);
        $fullPath = $this->domain->rootFolder . $value;

        if (!file_exists($fullPath)) {
            $this->_error(self::MSG_NOTEXISTS);
            return false;
        }

        $this->_setValue($value);

        $forbidden = array( './', '/.', '.\\', '\\.' );
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


        if (is_dir($fullPath)) {
            $this->cannotContain = $str;
            $this->_error(self::MSG_ISADIR);
            return false;
        }

        return true;
    }
}


class MyValid_AppArgs extends Zend_Validate_Abstract
{
    const MSG_ERR = 'msgErr';
    public $msgErr = "";

    protected $_messageVariables = array(
        'msgErr' => 'msgErr'
    );

    protected $_messageTemplates = array(
        self::MSG_ERR => "Cannot parse application parameters."
    );

    public function isValid($value)
    {
        $value = trim($value);
        $this->_setValue($value);

        $ret = Modules_JxcoreSupport_Common::parseAppArgs($value);
        if (!$ret) {
            $this->_error(self::MSG_ERR);
            return false;
        }

        return true;
    }
}
