
if(!window.__addMessage){
    window.__addMessage = function(type, message){
      var _content = document.getElementById('content').children;

      var xtypes={'info':'information','warn':'warning','err':'error'};

      var xtype = !xtypes[type] ? type:xtypes[type];

      for(var o in _content){
        if( _content[o].className && _content[o].className.indexOf && _content[o].className.indexOf( 'heading' )>=0 ){
          _content[o].innerHTML+='<div class=\'msg-box msg-'+type+'\'>'
            + '<div class=\'msg-content\'><span class=\'title\'>' + xtype
            + ':</span>' + message + '</div></div>';
          break;
        }
      }
    };
}