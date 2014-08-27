<?php

class DebugController extends pm_Controller_Action
{

    public function init()
    {
        parent::init();

//        if(Modules_JxcoreSupport_CustomStatus::CheckStatusRender($this)) // Plesk12
//        {
//            $this->_status = new Modules_JxcoreSupport_CustomStatus($this->view);
//            $this->view->status = new Modules_JxcoreSupport_CustomStatus($this->view);
//        }

        $this->view->pageTitle = 'JXcore Plesk Extension for Node';

        $this->common = new Modules_JxcoreSupport_Common($this, $this->_status);

        if (Modules_JxcoreSupport_Common::$isAdmin) {

            // Init tabs for all actions
            $this->view->tabs = array();


            $this->view->tabs[] = array(
                'title' => 'Test',
                'action' => 'test'
            );
        }
    }

    private $txtId = 0;

    private function addText(&$form, $label, $value)
    {
        $this->txtId++;
        $form->addElement('simpleText', 'txt' . $this->txtId, array(
            'label' => $label,
            'escape' => false,
            'value' => "^" . str_replace("\n", "<br>", $value) . "^"
        ));
    }

    // dumps var to string instead of to client
    private function varDump($var)
    {
        ob_start();
        var_dump($var);
        return ob_get_clean();
    }


    public function indexAction()
    {
        $form = new pm_Form_Simple();

        // reading crontab
        $binary = "/usr/local/psa/admin/bin/crontabmng";
        $tmpfile = pm_Context::getVarDir() . "mycron";
        @exec("$binary get root > $tmpfile");
        $contents = file_get_contents($tmpfile);


        $this->addText($form, "Root's current crontab", $contents);


        // var contents
        Modules_JxcoreSupport_Common::addHR($form);
        $this->addText($form, "pm_Context::getVarDir()", pm_Context::getVarDir());
        $this->addText($form, "current date", date("Y-m-d H:i:s"));


        Modules_JxcoreSupport_Common::addHR($form);


        $ids = Modules_JxcoreSupport_Common::getDomainsIDs();
        $log = [];
        foreach ($ids as $id) {
            $domain = Modules_JxcoreSupport_Common::getDomain($id);
            $sub = $domain->getSubscription();

            $log[] = "domain: {$domain->name}, sub: {$sub->mainDomain->name}";
        }

        $this->addText($form, "subs", join("<br>", $log));


        $client = pm_Session::getClient();
        $clid = $client->getId();

        Modules_JxcoreSupport_Common::addHR($form);

        $domain = Modules_JxcoreSupport_Common::getDomain(7);

//        $binary = "/usr/local/psa/admin/bin/crontabmng";
//        $tmpfile = pm_Context::getVarDir() . "mycron";
//        @exec("$binary get root > $tmpfile");
//        $contents = file_get_contents($tmpfile);
//        $this->addText($form, "crontab before", $contents);
//        $contents = preg_replace('/(#JXcore_Begin)(.?*)(#JXcore_End)/si', '$1 sss $2', $contents);
//        $this->addText($form, "crontab after", $contents);

//        // cleaning crontab
//        if (trim($contents) === "") {
//            @exec("$binary remove root");
//        } else {
//            file_put_contents($tmpfile, $contents);
//            @exec("$binary set root $tmpfile");
//        }

        $this->testBlock($form);

        $this->view->form = $form;

        $this->_status->addMessage("info", "Some message");
        $this->_status->addMessage("warning", "Some warning");
        $this->_status->addMessage("error", "Some error");


        Modules_JxcoreSupport_Common::reloadNginx();
    }


    private function testBlock(&$form) {

        $str = '#JXcore-immediate-Begin
16 13 15 5 * /usr/local/psa/var/modules/jxcore-support/jxcore-for-plesk-startup.sh
#JXcore-immediate-End

#JXcore-Begin
something
#JXcore-End

';

        $this->addText($form, "original block", $str);

        // removing
        $str1 = preg_replace('/(#JXcore-Begin)(.*)(#JXcore-End)/si', "", $str);
        $this->addText($form, "after block remove", $str1);


        // replacing
        $str1 = preg_replace('/(#JXcore-Begin)(.*)(#JXcore-End)/si', "$1\nreplacement\n$3", $str);
        $this->addText($form, "after block replace", $str1);

        $str2 = Modules_JxcoreSupport_Common::saveBlockToText($str, "JXcore-immediate", "krowa", false);
        $this->addText($form, "after saveBlockToText", $str2);

        $str2 = Modules_JxcoreSupport_Common::saveBlockToText($str, "JXcore-immediate", "", false);
        $this->addText($form, "after saveBlockToText remove", $str2);
    }
}
