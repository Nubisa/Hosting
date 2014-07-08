<?php

/* Copyright Nubisa, Inc. 2014. All Rights Reserved */

class SubscriptionController extends pm_Controller_Action
{
    private $domain = null;
    private $subscription = null;

    public function init()
    {
        parent::init();

        if(Modules_JxcoreSupport_CustomStatus::CheckStatusRender($this)) // Plesk12
        {
            $this->_status = new Modules_JxcoreSupport_CustomStatus($this->view);
            $this->view->status = new Modules_JxcoreSupport_CustomStatus($this->view);
        }


        $this->view->pageTitle = 'JXcore - subscription configuration';

        $this->common = new Modules_JxcoreSupport_Common($this, $this->_status);

        $this->ID = $this->getRequest()->getParam('id');
        if (!ctype_digit($this->ID)) unset($this->ID);

        if (!$this->ID) {
            $this->ID = pm_Settings::get("currentSubscriptionId" . pm_Session::getClient()->getId() );

            if (!$this->ID) {
                $this->view->err = "Unknown subscription ID";
                return;
            }
        } else {
            pm_Settings::set("currentSubscriptionId" . pm_Session::getClient()->getId(), $this->ID);
        }

        $this->subscription = SubscriptionInfo::getSubscription($this->ID);
        $this->view->breadCrumb = 'Navigation: <a href="' . Modules_JxcoreSupport_Common::$urlJXcoreSubscriptions . '">Subscriptions</a> -> ' . $this->subscription->mainDomain->name;
    }

    public function indexAction()
    {
        $this->_forward('config');
    }


    public function configAction()
    {
        if (!$this->subscription) {
            $this->view->form = "Invalid subscription ID";
            return;
        }

        $json = Modules_JxcoreSupport_Common::getMonitorJSON();
        $monitorRunning = $json !== null;

        $form = new pm_Form_Simple();

        JXconfig::addConfigToForm($form, $this->subscription->id, false);

        if ($monitorRunning) {
            $form->addElement('simpleText', "restartmayoccur", array(
                'label' => '',
                'escape' => false,
                'value' => "<span style='color: red;'>Submitting the form will may result in restarting applications belonging to this subscription.</span>",
                'description' => ""
            ));
        }

        $form->addElement('hidden', 'id', array(
            'value' => pm_Settings::get($this->ID),
        ));

        $form->addControlButtons(array(
            'cancelLink' => Modules_JxcoreSupport_Common::$urlJXcoreSubscriptions
        ));

        if ($this->getRequest()->isPost() && $form->isValid($this->getRequest()->getPost())) {

            $this->_status->beforeRedirect = true;

            $params = [
                Modules_JxcoreSupport_Common::sidDomainJXcoreAppMaxCPULimit,
                Modules_JxcoreSupport_Common::sidDomainJXcoreAppMaxCPUInterval,
                Modules_JxcoreSupport_Common::sidDomainJXcoreAppMaxMemLimit,
                Modules_JxcoreSupport_Common::sidDomainJXcoreAppAllowCustomSocketPort,
                Modules_JxcoreSupport_Common::sidDomainJXcoreAppAllowSysExec,
                Modules_JxcoreSupport_Common::sidDomainJXcoreAppAllowLocalNativeModules
            ];

            foreach ($params as $param) {
                $this->subscription->set($param, $form->getValue($param));
            }

            StatusMessage::dataSavedOrNot($this->subscription->configChanged);

            if ($monitorRunning && $this->subscription->configChanged) {
                $this->subscription->updateConfigs();
            }

            $this->_helper->json(array('redirect' => Modules_JxcoreSupport_Common::$urlJXcoreSubscriptions));
        }

        $this->view->buttonsDisablingScript = Modules_JxcoreSupport_Common::getButtonsDisablingScript();
        $this->view->form = $form;
    }

}