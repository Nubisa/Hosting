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

    private function check() {

        if (isset($this->view->err))
            return false;

        $this->view->err = "";

        if (!Modules_JxcoreSupport_Common::$isAdmin)
            $this->view->err = "Access denied.";

        if (!$this->subscription)
            $this->view->err = "Access denied.";

        if ($this->view->err) {
            $this->view->breadCrumb = "";
            $this->view->tabs = "";
            return false;
        }

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

        $form = new pm_Form_Simple();

        $jxEnabled = $this->subscription->JXcoreSupportEnabled();

        if ($monitorRunning)
            $description = $jxEnabled ? "If you disable JXcore for the subscription, the running Node applications of it's domains will be terminated!" : "When you enable JXcore support, JXcore applications for all domains in that subscription (with JXcore support enabled) will also be launched.";
        else
            $description = $jxEnabled ? "" : "When you enable JXcore, it schedules the applications of it's domains to run as soon as possible.";

        $button = Modules_JxcoreSupport_Common::getButtonStartStop($jxEnabled, Modules_JxcoreSupport_Common::sidSubscriptionJXcoreEnabled, array("Enabled", "Enable"), array("Disabled", "Disable"));

        $form->addElement('hidden', Modules_JxcoreSupport_Common::sidSubscriptionJXcoreEnabled, array(
            'value' => "nothing"
        ));

        $form->addElement('simpleText', 'status', array(
            'label' => 'JXcore',
            'escape' => false,
            'value' => $button,
            'description' => $description
        ));

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

            $actionValue = $this->getRequest()->getParam(Modules_JxcoreSupport_Common::sidSubscriptionJXcoreEnabled);
            $actionButtonPressed = in_array($actionValue, array("start", "stop"));

            if ($actionButtonPressed) {
                $this->subscription->set(Modules_JxcoreSupport_Common::sidSubscriptionJXcoreEnabled, $actionValue == "start" ? 1 : 0);
            }

            $params = array(
                Modules_JxcoreSupport_Common::sidDomainJXcoreAppMaxCPULimit,
                Modules_JxcoreSupport_Common::sidDomainJXcoreAppMaxCPUInterval,
                Modules_JxcoreSupport_Common::sidDomainJXcoreAppMaxMemLimit,
                Modules_JxcoreSupport_Common::sidDomainJXcoreAppAllowCustomSocketPort,
                Modules_JxcoreSupport_Common::sidDomainJXcoreAppAllowSysExec,
                Modules_JxcoreSupport_Common::sidDomainJXcoreAppAllowLocalNativeModules
            );

            foreach ($params as $param) {
                $this->subscription->set($param, $form->getValue($param));
            }

            StatusMessage::dataSavedOrNot($this->subscription->configChanged);

            Modules_JxcoreSupport_Common::updateAllConfigsIfNeeded("nowait");

            $this->_helper->json(array('redirect' => Modules_JxcoreSupport_Common::$urlJXcoreSubscriptions));
        }

        $this->view->buttonsDisablingScript = Modules_JxcoreSupport_Common::getButtonsDisablingScript();
        $this->view->form = $form;
    }

}