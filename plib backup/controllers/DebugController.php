<?php

class DebugController extends pm_Controller_Action
{

    public function init()
    {
        parent::init();

//        $this->before = $this->getEnvVars();

        if(Modules_JxcoreSupport_CustomStatus::CheckStatusRender($this)) // Plesk12
        {
            $this->_status = new Modules_JxcoreSupport_CustomStatus($this->view);
            $this->view->status = new Modules_JxcoreSupport_CustomStatus($this->view);
        }

//        $this->after = $this->getEnvVars();

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

//        $this->showEnvVars($form, $this->before);
//        $this->showEnvVars($form, $this->after);
//        $this->showEnvVars($form, $this->getEnvVars());
//        $this->domainList($form);
//        $this->showClient($form);
//        $this->showRootsCrontab($form);

        $this->basicDebug($form);


//        Modules_JxcoreSupport_Common::addHR($form);

//        $domain = Modules_JxcoreSupport_Common::getDomain(7);

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

//        $this->testBlock($form);

        $this->view->form = $form;

//        $this->_status->addMessage("info", "Some message");
//        $this->_status->addMessage("warning", "Some warning");
//        $this->_status->addMessage("error", "Some error");


//        Modules_JxcoreSupport_Common::reloadNginx();
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


    private function domainList(&$form) {

        Modules_JxcoreSupport_Common::addHR($form);

        // domain list
        $ids = Modules_JxcoreSupport_Common::getDomainsIDs();
        $log = [];
        foreach ($ids as $id) {
            $domain = Modules_JxcoreSupport_Common::getDomain($id);
            $sub = $domain->getSubscription();

            $log[] = "domain: {$domain->name}, sub: {$sub->mainDomain->name}";
        }

        $this->addText($form, "subs", join("<br>", $log));
    }


    private function showRootsCrontab(&$form) {

        Modules_JxcoreSupport_Common::addHR($form);

        // reading crontab
        $binary = "/usr/local/psa/admin/bin/crontabmng";
        $tmpfile = pm_Context::getVarDir() . "mycron";
        @exec("$binary get root > $tmpfile");
        $contents = file_get_contents($tmpfile);

        $this->addText($form, "Root's current crontab", $contents);
    }

    private function getEnvVars () {

        $exists = class_exists(pm_ProductInfo);
        $ver = $exists ? pm_ProductInfo::getVersion() : "no support for pm_ProductInfo::getVersion()";
        $arr = explode(".", $ver);
        $major = count($arr) > 1 ? $arr[0] : -1;
        $patch = Modules_JxcoreSupport_Common::callService("get_version", "patch", null, null, true);

        $last_sid = "last_known_plesk_path_version";
        $last = pm_Settings::get($last_sid);
        $last = is_numeric($last) ? intval($last) : -1;

        $arr = array(
            "pm_Context::getVarDir()" =>  pm_Context::getVarDir(),
            "current date" =>  date("Y-m-d H:i:s"),
            "class_exists(pm_ProductInfo)" => $exists,
            "pm_ProductInfo::getVersion()" => $ver,
            "major" => $major,
            "patch version" => $patch,
            "patch version as int" => is_numeric($patch) ? intval($patch) : -1,
            "useWorkaround" => Modules_JxcoreSupport_CustomStatus::CheckStatusRender($this),
            $last_sid => $last
        );

        return $arr;
    }

    private function showEnvVars(&$form, $arr) {

        // var contents
        Modules_JxcoreSupport_Common::addHR($form);


        foreach($arr as $key=>$val) {
            $this->addText($form, $key, $val);
        }

        return;
        $this->addText($form, "pm_Context::getVarDir()", pm_Context::getVarDir());
        $this->addText($form, "current date", date("Y-m-d H:i:s"));

        $useWorkaround = Modules_JxcoreSupport_CustomStatus::CheckStatusRender($this);
        $this->addText($form, "useWorkaround", $useWorkaround);

        $ver = null;
//        try {
            $exists = class_exists(pm_ProductInfo);
            if ($exists)
                $ver = pm_ProductInfo::getVersion();
            else
                $ver = "no support for pm_ProductInfo::getVersion()";
//        } catch (Exception $ex) {
//            $ver = "no support for pm_ProductInfo::getVersion()";
//        }

        $this->addText($form, "class_exists pm_ProductInfo", class_exists(pm_ProductInfo));
        $this->addText($form, "pm_ProductInfo::getVersion()", $ver);

        $arr = explode(".", $ver);
        $major = count($arr) > 1 ? $arr[0] : -1;

        $this->addText($form, "major", $major);

        $v = Modules_JxcoreSupport_Common::callService("get_version", "patch", null, null, true);
        $this->addText($form, "patch version", $v);

        $i = is_numeric($v) ? intval($v) : -1;
        $this->addText($form, "patch version as int", $i);
    }


    private function showClient(&$form) {

        Modules_JxcoreSupport_Common::addHR($form);

        $client = pm_Session::getClient();
        $clid = $client->getId();
        $this->addText($form, '$client->getId()', $clid);
    }


    private function basicDebug(&$form) {

        $this->addText($form, "phpversion", phpversion());

        $exists = class_exists(pm_ProductInfo);
        if ($exists)
            $ver = pm_ProductInfo::getVersion();
        else
            $ver = "no support for pm_ProductInfo::getVersion()";
//        } catch (Exception $ex) {
//            $ver = "no support for pm_ProductInfo::getVersion()";
//        }

        $this->addText($form, "class_exists pm_ProductInfo", class_exists(pm_ProductInfo));
        $this->addText($form, "pm_ProductInfo::getVersion()", $ver);
    }

}

