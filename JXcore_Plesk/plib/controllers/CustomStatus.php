<?php
// !!! We needed to do this hack for Plesk 12 since the status messages weren't showing on extension

class CustomStatus
{
    function CustomStatus(){
        $this->messageId = 0;
    }

    public function addMessage($type, $message){
        $this->messageId++;
        echo  "<script>"
            . " if(!window.__inter" . $this->messageId . "){"
            . "  window.__inter" . $this->messageId . " = setInterval(function(){"
            . "    if(document.getElementById('content')){"
            . "      clearInterval(window.__inter" . $this->messageId . ");"
            . "    }else{return;}"
            . "   __addMessage('".$type."','".$message."');"
            . "  },500);}</script>";
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