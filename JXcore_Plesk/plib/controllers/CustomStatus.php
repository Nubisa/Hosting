<?php
// !!! We needed to do this hack for Plesk 12 since the status messages weren't showing on extension

class CustomStatus
{
    function CustomStatus($_helper){
        $this->host = $_helper;
        $this->messageId = 0;

        $this->checkMessages();
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
            $arr = [];
        else
            $arr = unserialize($arr);

        $msg = [];
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
            pm_Settings::set($sid, serialize([]) );
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
         if(get_class($_this->view->status) != "AdminPanel_Controller_Action_Status")
         {
            $_this->pleskVersion = 12;
            return true;
        }
        else{
            $_this->pleskVersion = 11;
            return false;
        }
    }
}