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
            $str = "<script>"
                 . "  if(!window.__addMessage){"
                 . "  window.__addMessage = function(type, message){"
                 . "    var _content = document.getElementById('content').children;"
                 . "    var xtypes={'info':'information','warn':'warning','err':'error'};"
                 . "    var xtype = !xtypes[type] ? type:xtypes[type]; "
                 . "    for(var o in _content){"
                 . "      if( _content[o].className == 'heading'){"
                 . "        _content[o].innerHTML+='<div class=\'msg-box msg-'+type+'\'>'"
                 . "          + '<div class=\'msg-content\'><span class=\'title\'>' + xtype "
                 . "          + ':</span>' + message + '</div></div>'; "
                 . "        break;"
                 . "      }"
                 . "    }"
                 . "  };} "
                 . "</script>";

            echo $str;

            return true;
        }
        else{
            $_this->pleskVersion = 11;
            return false;
        }
    }
}