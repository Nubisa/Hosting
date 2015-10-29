
if(!window.__addMessage){
    window.__addMessage = function(type, message){
      var _content = document.getElementById('content').children;

      var xtypes={'info':'information','warn':'warning','err':'error'};

      var xtype = !xtypes[type] ? type:xtypes[type];

      for(var o in _content){
        if( _content[o].className && _content[o].className.indexOf && _content[o].className.indexOf( 'heading' )>=0 ){
          _content[o].innerHTML+='<div class=\'msg-box msg-'+type+'\'>'
            + '<div class=\'msg-content\'><span class=\'title\'>' + xtype
            + ': </span>' + message + '</div></div>';
          break;
        }
      }
    };
}


// hide/show npm-debug.log panel (on NPM Modules tab)
if (Jsw) {
    Jsw.onReady(function() {

        $$('#caption-control-npm-debug-log').each(function(element) {

            $(element).on("click", function(e) {
                e.preventDefault();

                $$('#active-list-item-npm-debug-log').each(function(element2) {

                    var item = $(element2);
                    var cl = element2.className;

                    if (element.__visible === true) {
                        element.__visible = false;
                        element2.className = cl.replace('active-list-item-expanded', 'active-list-item-collapsed');
                    } else {
                        element.__visible = true;
                        element2.className = cl.replace('active-list-item-collapsed', 'active-list-item-expanded');
                    }
                });
            });
        });
    });
}