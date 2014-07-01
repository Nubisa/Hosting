<?php

class SubscriptionController extends pm_Controller_Action
{
    private $domain = null;
    private $subscription = null;

    public function init()
    {
        parent::init();

        $this->view->pageTitle = 'JXcore - subscription configuration';

        require_once("common.php");
        $this->common = new Common($this, $this->_status);

        $this->ID = $this->getRequest()->getParam('id');
        if (!ctype_digit($this->ID)) unset($this->ID);

        if (!$this->ID) {
            $this->ID = pm_Settings::get("currentSubscriptionId");

            if (!$this->ID) {
                $this->view->err = "Unknown subscription ID";
                return;
            }
        } else {
            pm_Settings::set("currentSubscriptionId", $this->ID);
        }

        $this->subscription = SubscriptionInfo::getSubscription($this->ID);
        $this->view->breadCrumb = 'Navigation: <a href="' . Common::$urlJXcoreSubscriptions . '">Subscriptions</a> -> ' . $this->subscription->mainDomain->name;
    }

    public function indexAction()
    {
        $this->_forward('config');
    }


    public function configAction()
    {
        $json = Common::getMonitorJSON();
        $monitorRunning = $json !== null;

        $form = new pm_Form_Simple();

        JXconfig::addConfigToForm($form, $this->subscription->id, false);

        if ($monitorRunning) {
            $form->addElement('simpleText', "restartmayoccur", array(
                'label' => '',
                'escape' => false,
                'value' => "<span style='color: red;'>Submitting the form will may result in restarting the monitor together with all of the applications.</span>",
                'description' => ""
            ));
        }

        $form->addElement('hidden', 'id', array(
            'value' => pm_Settings::get($this->ID),
        ));

        $form->addControlButtons(array(
            'cancelLink' => Common::$urlJXcoreSubscriptions
        ));

        if ($this->getRequest()->isPost() && $form->isValid($this->getRequest()->getPost())) {

            $params = [
                Common::sidDomainJXcoreAppMaxCPULimit,
                Common::sidDomainJXcoreAppMaxCPUInterval,
                Common::sidDomainJXcoreAppMaxMemLimit,
                Common::sidDomainJXcoreAppAllowCustomSocketPort,
                Common::sidDomainJXcoreAppAllowSysExec,
                Common::sidDomainJXcoreAppAllowLocalNativeModules
            ];

            $changed = false;
            foreach ($params as $param) {
                $val = $form->getValue($param);

                if ($this->subscription->set($param, $val))
                    $changed = true;
            }
            $this->_status->addMessage('info', 'Data was successfully saved.');

            if ($changed && $monitorRunning) {
//                $this->subscription->saveConfig();
                Common::monitorStartStop('restart');
            }

            $this->_helper->json(array('redirect' => Common::$urlJXcoreSubscriptions));
        }

        $this->view->buttonsDisablingScript = Common::getButtonsDisablingScript();
        $this->view->form = $form;
    }

}