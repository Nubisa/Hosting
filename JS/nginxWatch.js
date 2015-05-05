
// monitors nginx config folder and performs nginx reload
// this is designed to work as part of service.jx (running as root)

var chokidar = require('chokidar');
var reloadNginx = false;

var dir = "/etc/nginx/jxcore.conf.d/";
var opts = { ignored: /[\/\\]\./, persistent : true, ignoreInitial : true, ignorePermissionErrors : true };
chokidar.watch(dir, opts ).on('all', function(event, _path) {

    if (_path && _path.slice(-5) === ".conf")
        reloadNginx = true;
});

setInterval(function() {
    if (reloadNginx) {
        try {
            var ret = jxcore.utils.cmdSync("/usr/local/psa/admin/bin/nginx_control -r");
            if (!ret.exitCode)
                reloadNginx = false;

        } catch (ex) {
        }
    }
}, 3000);
