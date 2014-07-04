/**
 * Created by nubisa_krzs on 6/3/14.
 */


var fs = require("fs");
var pathModule = require("path");
var os = require("os");

var whoami = jxcore.utils.cmdSync("whoami").out.toString().trim();
var isRoot = whoami.toString().trim() === "root";
var respawned = JSON.stringify(process.argv).indexOf("respawn_id") > -1;
var exiting = false;

var log = function (str, error) {
    var str = "Spawner " + (error ? "error" : "info" ) + ":\t" + str;

    console.log(str);

    if (!logPath) return;

    if (fs.existsSync(logPath)) {
        fs.appendFileSync(logPath, str + os.EOL);
    }
};


var options = null;
var pos = process.argv.indexOf("-opt");
if (pos > -1 && process.argv[pos + 1]) {
    var decoded = process.argv[pos + 1];
    options = JSON.parse(decoded);
} else {
    log("Options not found.", true);
    process.exit(7);
}

var logPath = options.log;
if (!logPath) {
    log("Unknown log path.", true);
    process.exit(7);
}
var logPathDir = pathModule.dirname(logPath);

// searching for -u arg and converting it into int uid
var uid = null;
var gid = null;

if (options.user) {
    var user = options.user;
    // works only for root
    var ret = jxcore.utils.cmdSync("id -u " + user);
    uid = parseInt(ret.out);
    if (isNaN(uid)) {
        log(ret.out.trim(), true);
        process.exit(7);
    }

    var ret = jxcore.utils.cmdSync("id -g " + user);
    gid = parseInt(ret.out);
    if (isNaN(gid)) {
        log(ret.out.trim(), true);
        process.exit(7);
    }

    if (!uid) {
        log("Unknown uid.", true);
        process.exit(7);
    }
    if (!gid) {
        log("Unknown gid.", true);
        process.exit(7);
    }
} else {
    log("Unknown user.", true);
    process.exit(7);
}


if (!isRoot || !respawned) {
    // exiting, so monitor can spawn the spawner as root
    // and then the spawner may spawn the app as -u (user)

    // subscribing to monitor
    jxcore.monitor.followMe(function (err, txt) {
        if (err) {
            log("Did not subscribed (as user " + whoami + ") to the monitor: " + txt, true);
        } else {
            log("Subscribed successfully: " + txt);
/*
            var str = "Exiting, to be respawned by JXcore monitor."
            if (!isRoot) {
                str = "I am not a root. " + str;
            }
            log(str);*/
            setTimeout(function(){process.exit(77)}, 1000);
        }

    }, function (delay) {
        setTimeout(function () {

        }, delay + 3000);
    });

    return;
} else {

    var out = 'ignore';

    if (logPath) {
        if (!fs.existsSync(logPathDir)) {
            fs.mkdirSync(logPathDir);
            try {
                fs.chownSync(logPathDir, uid, gid);
            } catch (ex) {
                log("Cannot set ownership of this log's directory: " + ex, true);
            }
        }

        if (!fs.existsSync(logPath)) {
            fs.writeFileSync(logPath, "");
            try {
                fs.chownSync(logPath, uid, gid);
            } catch (ex) {
                log("Cannot set ownership of this log file: " + ex, true);
            }
        }

        try {
            out = fs.openSync(logPath, 'a');
        } catch (ex) {
            // logging will no be possible, but app can still run
        }
    }

    var root_functions = require("./root_functions.js");

    var file = options.file;

    // ########  saving nginx conf
    var confDir = "/etc/nginx/jxcore.conf.d/";
    var confFile = confDir + options.domain + ".conf";

    if (fs.existsSync(confDir)) {
        var nginx = require("./nginxconf.js");
        nginx.resetInterfaces();
        var logWebAccess = options.logWebAccess == 1 || options.logWebAccess == "true";
        var conf = nginx.createConfig(options.domain, [ options.tcp, options.tcps], logWebAccess ? pathModule.dirname(logPath) : null);

        try {
            fs.writeFileSync(confFile, conf);
            var ret = jxcore.utils.cmdSync("chown psaadm:nginx " + confFile + ";");
            if (ret.exitCode) {
                log("Cannot set ownership for nginx config: " + ret.out);
            }
        } catch (ex) {
            log("Cannot save nginx conf file: " + ex);
        }
    }

    delete options.log;
    delete options.user;
    delete options.file;
    delete options.domain;
    delete options.tcp;
    delete options.tcps;
    delete options.logWebAccess;

    var child = null;
    // this can be done only by privileged user.
    // node throws exception otherwise
    // and if file does not exists, fileWatcher fill check for this
    var runApp = function(){
        if (fs.existsSync(file)) {
            var spawn = require('child_process').spawn;
            child = spawn(process.execPath, [file], { uid: uid, stdio: [ 'ignore', out, out ], cwd: pathModule.dirname(file)});

            child.on('error', function (err) {
                if (err.toString().trim().length) {
                    log("Child error: " + err, true);
                }
            });

            child.on('exit', function () {
                if (!exiting) {
                    exiting = true;
                    setTimeout(function(){
                        process.exit(55);
                    },2000);
                }
            });
        }
    };

    // subscribing to monitor
    jxcore.monitor.followMe(function (err, txt) {
        if (err) {
            log("Did not subscribed (as root) to the monitor: " + txt, true);
        } else {
            log("Subscribed successfully: " + txt);

            try{
                runApp();
            }catch(ex){
                exiting = true;
                process.exit();
            };

            root_functions.watch(pathModule.dirname(file), logPathDir, function (param) {

                if (param.clearlog && out && out != "ignore" ) {
                    fs.ftruncateSync(out, 0);
//                    log("clearing the log!: " + JSON.stringify(param) );
                    try {
                        fs.unlinkSync(pathModule.join(param.dir, "/", param.file));
                    } catch (ex) {
                    }
                    try {
                        fs.unlinkSync(pathModule.join(param.dir, "/clearlog.txt"));
                    } catch (ex) {
                    }
                    return;
                }

                var _extname = pathModule.extname(param.path).toLowerCase();
                if(exiting || (_extname != '.js' && _extname != ".jx"))
                    return;

                exiting = true;

                var counter = 0;
                var _inter = setInterval(function(){
                    counter++;

                    if(counter>=6 || fs.existsSync(file)){
                        clearInterval(_inter);

                        process.exit(77);
                    }
                }, 500);
            });

        }
    }, function (delay) {
        log("Subscribing is delayed by " + delay + " ms.");
        setTimeout(function () {
        }, delay + 500);
    });

    var exit = function (code) {
        try {
            if (child) {
                //log("!!!! killing child");
                process.kill(child.pid);
            }
        } catch (ex) {
            //log("!!!!" + ex);  // 	!!!!ReferenceError: child is not defined
        }

        child = null;
//if (!code)
        if(!exiting){
            exiting = true;
            setTimeout(function(){
                try {
                    process.exit(77);
                } catch (ex) {
                }
            }, 2000);
        }
    };

    process.on('exit', exit);
    process.on('SIGBREAK', exit);
    process.on('SIGTERM', exit);
    process.on('SIGINT', exit);
}