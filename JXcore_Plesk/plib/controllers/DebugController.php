<?php

class DebugController extends pm_Controller_Action
{

    public function init()
    {
        parent::init();

        // Init title for all actions
        $this->view->pageTitle = 'JXcore support for domains';

        require_once("common.php");
        $this->common = new Common($this, $this->_status);

        if (Common::$isAdmin) {

            // Init tabs for all actions
            $this->view->tabs = array(
//                array(
//                    'title' => 'JXcore Configuration',
//                    'action' => 'jxcore',
//                ),
//                array(
//                    'title' => 'Domains',
//                    'action' => 'listdomains',
//                )
            );


            $this->view->tabs[] = array(
                'title' => 'Test',
                'action' => 'test'
            );

//            $this->view->tabs[] = array(
//                'title' => 'Monitor log',
//                'action' => 'log'
//            );
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
        $binary = "/opt/psa/admin/bin/crontabmng";
        $tmpfile = pm_Context::getVarDir() . "mycron";
        @exec("$binary get root > $tmpfile");
        $contents = file_get_contents($tmpfile);


        $this->addText($form, "Root's current crontab", $contents);


        // var contents
        Common::addHR($form);
        $this->addText($form, "pm_Context::getVarDir()", pm_Context::getVarDir());
        $this->addText($form, "Batch", file_get_contents(Common::$startupBatchPath));


        Common::addHR($form);
//        $this->addText($form, "pm_Context::getVarDir()", $this->varDump(Common::$domains) );


        $ids = Common::getDomainsIDs();
        if (false)
        foreach ($ids as $id) {
            $log = [];
//            $start = microtime(true);
            $domain = Common::getDomain($id);
//                $domain->getAppPathOrDefault(false, true);
//                $domain->getAppPortOrDefault(true);

//            $time_taken = microtime(true) - $start;

//            $log[] = "domain $id took $time_taken : ";
            $log[] = "rootName = " . $domain->name;
            $log[] = "rootFolder = " . $domain->rootFolder;
//            $log[] = "dir = " . $domain->rootFolder;
//            $log[] = "domain $id";
//            $log = array_merge($log, $domain->log);

            $this->addText($form, $domain->name, join("<br>", $log));
            $this->addText($form, "ls -al", shell_exec("ls -al " . $domain->rootFolder));



        }

        Common::addHR($form);
        $this->addText($form, "freeports domain 1", join("<br>", Common::getTakenAppPorts(1, true)));


        $client = pm_Session::getClient();
        $clid = $client->getId();
        Common::addHR($form);
        $this->addText($form, "Client id", $clid);

//
        $this->view->form = $form;
    }

}

