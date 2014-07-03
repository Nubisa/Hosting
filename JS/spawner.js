/**
 * Created by nubisa_krzs on 6/3/14.
 */


var fs = require("fs");
var path = require("path");
var os = require("os");

var whoami = jxcore.utils.cmdSync("whoami").out.toString().trim();
var isRoot = whoami.toString().trim() === "root";
var respawned = JSON.stringify(process.argv).indexOf("respawn_id") > -1;
var exitting = false;

var log = function (str, error) {
    var str = "Spawner " + (error ? "error" : "info" ) + ":\t" + str;

    console.log(str);

    if (!logPath) return;

    //if (isRoot) {
    if (fs.existsSync(logPath)) {
        fs.appendFileSync(logPath, str + os.EOL);
    }
    //} //else {
//        console.log(str);
    //}
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
var logPathDir = path.dirname(logPath);


//console.log("logPath", logPath);
//console.log("logDir", logPathDir);

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

            var str = "Exiting, to be respawned by JXcore monitor."
            if (!isRoot) {
                str = "I am not a root. " + str;
            }
            log(str);
            process.exit(77);
        }

    }, function (delay) {
        //log("Subscribing is delayed by " + delay+ " ms.");
        setTimeout(function () {
        }, delay + 5000);
    });

} else {

    var out = 'ignore';
//    var err = 'ignore';

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
//            err = fs.openSync(logPath, 'a');

        } catch (ex) {
            // logging will no be possible, but app can still run
        }
    }

    var root_functions = require("./root_functions.js");

    var checkAccess = function (path) {
        var str = 'sudo -u "' + user + '" -- test -r "' + path + '" && echo "OK"';
        var ret = jxcore.utils.cmdSync(str);

//        log("checking " + str + JSON.stringify(ret));
        if (ret.out.toString().trim() !== "OK") {
            log("User " + user + " has no read access to file " + path, true);
            setTimeout(function(){
                process.exit(7);
            }, 1500);
        }
    };

    var file = options.file;

    //checkAccess(file);
    checkAccess(path.dirname(file));

    // ########  saving nginx conf
    var confDir = "/etc/nginx/jxcore.conf.d/";
    var confFile = confDir + options.domain + ".conf";

    if (fs.existsSync(confDir)) {
        var nginx = require("./nginxconf.js");
        nginx.resetInterfaces();
        var logWebAccess = options.logWebAccess == 1 || options.logWebAccess == "true";
        var conf = nginx.createConfig(options.domain, [ options.tcp, options.tcps], logWebAccess ? path.dirname(logPath) : null);

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

    // default path for app config
//    var configFile = file + ".jxcore.config";
//    var configFileIsDefault = true;
//    var jxconfig = root_functions.readJXconfig();
////    log("config read: " + JSON.stringify(jxconfig));
//    if (jxconfig && jxconfig.globalApplicationConfigPath) {
//        var base = file.replace(/[\/]/g, "_").replace(/[\\]/g, "_").replace(/:/g, "_") + ".jxcore.config";
//        // assuming, that folder exists (it's created by php)
//        if (fs.existsSync(jxconfig.globalApplicationConfigPath)) {
//            configFile = path.join(jxconfig.globalApplicationConfigPath, "/", base);
//            configFileIsDefault = false;
//        }
//    }
////    log("app cfg file: " + configFile);
//    fs.writeFileSync(configFile, JSON.stringify(options));

    // this can be done only by privileged user.
    // node throws exception otherwise
    // and if file does not exists, fileWatcher fill check for this
    if (fs.existsSync(file)) {
        var spawn = require('child_process').spawn;
        var child = spawn(process.execPath, [file], { uid: uid, stdio: [ 'ignore', out, out ], cwd: path.dirname(file)});

        child.on('error', function (err) {
            if (err.toString().trim().length) {
                log("Child error: " + err, true);
            }
        });

        child.on('exit', function () {
            if (!exitting) {
                exitting = true;
                setTimeout(function(){
                    process.exit();
                },5000);
            }
        });
    }

    // subscribing to monitor
    jxcore.monitor.followMe(function (err, txt) {
        if (err) {
            log("Did not subscribed (as root) to the monitor: " + txt, true);
        } else {
            log("Subscribed successfully: " + txt);

//            // deleting config file
//            try {
//                if (configFileIsDefault) fs.unlinkSync(configFile);
//            } catch (ex) {
//                log("Cannot delete config file: " + ex);
//            }

            log("Starting watch folder");


            root_functions.watch(path.dirname(file), logPathDir, function (param) {

                if (param.clearlog && out && out != "ignore" ) {
                    fs.ftruncateSync(out, 0);
//                    log("clearing the log!: " + JSON.stringify(param) );
                    try {
                        fs.unlinkSync(path.join(param.dir, "/", param.file));
                    } catch (ex) {
                    }
                    try {
                        fs.unlinkSync(path.join(param.dir, "/clearlog.txt"));
                    } catch (ex) {
                    }
                }

               // log("CHANGED!!! " + fname + ", file = " + file);

                if (param.fname) {
                    var restart = false;
                    if (fname == file) {
                        // app itself was changed
                        if (!fs.existsSync(fname)) {
                            // lets kill the child

                            try {
                                if (child) {
                                    exiting = true;
                                    process.kill(child.pid);
                                    child = null;
                                    var counter = 0;
                                    setInterval(function(){
                                        counter++;

                                        if(counter>=10 || fs.existsSync(fname)){
                                            process.exit();
                                        }
                                    }, 500);
                                    return;
                                }
                            } catch (ex) {
                            }
                        } else {
                            // child was killed previously, so lets restart the app
                            restart = true;
                        }
                    } else {
                        restart = true;
                    }

                    if (restart) {
                        log("Files changed - restarting the application.");
                        //                var ret = jxcore.utils.cmdSync('"' + process.execPath + "' monitor kill " + __filename);
                        //                log('"' + process.execPath + "' monitor kill " + __filename + " : " + JSON.stringify(ret));
                       setTimeout(function(){
                        process.exit(77);
                       },2000);
                    }
                }
            });

        }
    }, function (delay) {
        log("Subscribing is delayed by " + delay + " ms.");
        setTimeout(function () {
        }, delay + 500);
    });

    var exit = function (code) {
        if(!exitting){
            exitting = true;
            setTimeout(function(){
                try {
                    if (child) {
                        process.kill(child.pid);
                    }
                } catch (ex) {
                }
                try {
                    process.exit(77);
                } catch (ex) {
                }
            }, 1500);
        }
    };

    process.on('exit', exit);
    process.on('SIGBREAK', exit);
    process.on('SIGTERM', exit);
    process.on('SIGINT', exit);
}