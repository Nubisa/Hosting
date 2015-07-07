
// monitors nginx config folder and performs nginx reload
// this is designed to work as part of service.jx (running as root)

var chokidar = require('chokidar');
var reloading = false;

var dir = "/etc/nginx/jxcore.conf.d/";
var opts = {
    ignored: /[\/\\]\./,
    persistent : true,
    ignoreInitial : true,
    ignorePermissionErrors : true,
    interval : 2500,
    useFsEvents : false,
    usePolling : true
};

chokidar.watch(dir, opts).on('all', function(event, _path) {

    if (_path && _path.slice(-5) === ".conf")
        reload();
});

// **** debug

var log = function() {
    console.log.apply(this, arguments);
};

//var cnt = 0;
//var fs = require("fs");
//var log = function() {
//    console.log.apply(this, arguments);
//    var tmp = "/tmp/x.txt";
//    if (!cnt)
//        fs.writeFileSync(tmp, "");
//
//    fs.appendFileSync(tmp, (cnt++) + ":\n");
//    for(var o in arguments) {
//        fs.appendFileSync(tmp, "\t" + arguments[o]);
//    }
//};
// **** debug

jxcore.tasks.on("message", function(threadId, obj){
    log(obj.reloaded ? "reloaded" : "not reloaded");
    reloading = false;
});


var task = function() {

    var cp = require("child_process");
    process.keepAlive();
    // jxcore.utils.cmdSync() did not work good with reloading!
    // nginx_control was staying as dummy process!
    var child = cp.exec("/usr/local/psa/admin/bin/nginx_control -r", { timeout : 3000 }, function(err, stdout, stderr) {
        process.sendToMain( { reloaded : child.exitCode === 0 });
        process.release();
    });
};


var reload = function() {

    // already called for reload
    if (reloading)
        return;

    reloading = true;
    log("reloading");
    // wait a bit, maybe other nginx configs will be created/changed too...
    setTimeout(function() {
        jxcore.tasks.addTask(task);
    }, 2000)
};