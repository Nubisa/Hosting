<?php

/* Copyright Nubisa, Inc. 2014. All Rights Reserved */

// !!! We needed to do this hack for Plesk 12 since the status messages weren't showing on extension

class Modules_JxcoreSupport_CustomStatus
{
    public $beforeRedirect = null;

    function Modules_JxcoreSupport_CustomStatus($_helper){
        $this->host = $_helper;
        $this->messageId = 0;
    }

    function addElement($script){
        // lets say $this->host  == view
        if(!$this->host->messages)
            $this->host->messages = $script;
        else
            $this->host->messages .= $script;
    }


    function storeMessage($type, $message){
        $sid = "jxcore_messages_" . pm_Session::getClient()->getId();
        $arr = pm_Settings::get($sid);


        if(!$arr)
            $arr = array();
        else
            $arr = unserialize($arr);

        $msg = array();
        $msg[] = $type;
        $msg[] = $message;

        $arr[] = $msg;
        pm_Settings::set($sid, serialize($arr) );

    }


    function checkMessages(){
        if($this->beforeRedirect)
            return;

        $sid = "jxcore_messages_" . pm_Session::getClient()->getId();
        $arrs = pm_Settings::get($sid);

        if($arrs){
            $arr = unserialize($arrs);

            foreach($arr as $msg) {
                $this->addMessage($msg[0], $msg[1]);
            }
            pm_Settings::set($sid, serialize(array()) );
        }
    }


    public function addMessage($type, $message){

        $this->messageId++;

        if($this->beforeRedirect){
            $this->storeMessage($type, $message);
            return;
        }

        $message = str_replace("'"," ", $message);
        $message = str_replace("\""," ", $message);
        $message = str_replace("\n","<br/>", $message);


        $str = "<script>(function(){"
            . " if(!window.__inter" . $this->messageId . "){"
            . "  window.__inter" . $this->messageId . " = setInterval(function(){"
            . "    if(document.getElementById('content')){"
            . "      clearInterval(window.__inter" . $this->messageId . ");"
            . "    }else{return;}"
            . "   __addMessage('".$type."','".$message."');"
            . "  },500);}})();</script>";

       // $this->host->json(array('redirect' => 'javascript:'. $str));

        $this->addElement($str);
    }

    public function hasMessages(){
        return false;
    }

    public function addInfo($message){
        $this->addMessage("info", $message);
    }

    public static function CheckStatusRender($_this){

        if (!Modules_JxcoreSupport_Common::$plesk12)
            return false; // version below 12, no need for workaround

        // now there is api12
        $ver = pm_ProductInfo::getVersion(); // e.g. 12.0.18

        if ($ver == "12.0.18") {

            $sid = "last_known_plesk_path_version";
            $last = pm_Settings::get($sid);
            $last = is_numeric($last) ? intval($last) : -1;

            if ($last >= 8)
                return false;  // plesk 12, patch above v8 - no need for workaround

            // should return int number, by reading /root/.autoinstaller/microupdates.xml
            // gets <patches><product><patch version="xxx .../></product></patches>
            $str = Modules_JxcoreSupport_Common::callService("get_version", "patch", null, null, "silent");
            $patch = is_numeric($str) ? intval($str) : -1;

            if ($patch !== -1)
                pm_Settings::set($sid, $patch);

            // version 12, patch below v8 -  use workaround
            return $patch < 8;
        }

        return false; // version above 12, let's hope that there's no need for workaround
    }
}