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

    if (!logPath) return;

    if (isRoot) {
        fs.appendFileSync(logPath, str + os.EOL);
    } else {
        console.log(str);
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
var logPathDir = path.dirname(logPath);

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

    var str = "Exiting, to be respawned by JXcore monitor."
    if (!isRoot) {
        str = "I am not a root. " + str;
    }
    log(str);

    // subscribing to monitor
    jxcore.monitor.followMe(function (err, txt) {
        if (err) {
            log("Did not subscribed to the monitor: " + txt, true);
        } else {
            log("Subscribed successfully: " + txt);
        }
    }, function (delay) {
        //log("Subscribing is delayed by " + delay+ " ms.");
        setTimeout(function () {
            process.exit(77);
        }, delay + 500);
    });

} else {

    var root_functions = require("./root_functions.js");

    var checkAccess = function (path) {
        var str = 'sudo -u "' + user + '" -- test -r "' + path + '" && echo "OK"';
        var ret = jxcore.utils.cmdSync(str);

//        log("checking " + str + JSON.stringify(ret));
        if (ret.out.toString().trim() !== "OK") {
            log("User " + user + " has no read access to file " + path, true);
            process.exit(7);
        }
    };

    var file = options.file;

    checkAccess(file);
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
            var ret = jxcore.utils.cmdSync("chown psaadm:nginx " + confFile + "; /etc/init.d/nginx reload");
            if (ret.exitCode) {
                log("Cannot reload nginx config: " + ret.out);
            }
//            log("return from nginx reload: " + JSON.stringify(ret));
        } catch(ex) {
            log("Cannot save nginx conf file: " + ex);
        }
    }
    //


    // this can be done only by privileged user.
    // node throws exception otherwise
    var spawn = require('child_process').spawn;

    var out = 'ignore';
    var err = 'ignore';

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
            err = fs.openSync(logPath, 'a');
        } catch (ex) {
            // logging will no be possible, but app can still run
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
    var configFile = file + ".jxcore.config";
    var configFileIsDefault = true;
    var jxconfig = root_functions.readJXconfig();
//    log("config read: " + JSON.stringify(jxconfig));
    if (jxconfig && jxconfig.globalApplicationConfigPath) {
        var base = file.replace(/[\/]/g, "_").replace(/[\\]/g, "_").replace(/:/g, "_") + ".jxcore.config";
        // assuming, that folder exists (it's created by php)
        if (fs.existsSync(jxconfig.globalApplicationConfigPath)) {
            configFile = path.join(jxconfig.globalApplicationConfigPath, "/", base);
            configFileIsDefault = false;
        }
    }
//    log("app cfg file: " + configFile);
    fs.writeFileSync(configFile, JSON.stringify(options));

    var child = spawn(process.execPath, [file], { uid: uid, stdio: [ 'ignore', out, err ], cwd : path.dirname(file)});

    child.on('error', function (err) {
        if (err.toString().trim().length) {
            log("Child error: " + err, true);
        }
    });

    child.on('exit', function () {
        if (!exitting) {
            process.exit();
        }
    });

    // subscribing to monitor
    jxcore.monitor.followMe(function (err, txt) {
        if (err) {
            log("Did not subscribed to the monitor: " + txt, true);
        } else {
            log("Subscribed successfully: " + txt);

            // deleting config file
            try {
                if (configFileIsDefault) fs.unlinkSync(configFile);
            } catch (ex) {
                log("Cannot delete config file: " + ex);
            }

            root_functions.watch(file, logPathDir, function() {
                log("Files changed - restarting the application.");
//                var ret = jxcore.utils.cmdSync('"' + process.execPath + "' monitor kill " + __filename);
//                log('"' + process.execPath + "' monitor kill " + __filename + " : " + JSON.stringify(ret));
                process.exit();
            });

        }
    }, function (delay) {
        log("Subscribing is delayed by " + delay + " ms.");
        setTimeout(function () {
        }, delay + 500);
    });

    var exit = function (code) {
        exitting = true;
        try {
            if (child) {
                process.kill(child.pid);
            }
        } catch (ex) {
        }
        try {
            if (!code) {
                process.exit(77);
            }
        } catch (ex) {
        }
    };

    process.on('exit', exit);
    process.on('SIGBREAK', exit);
    process.on('SIGTERM', exit);
    process.on('SIGINT', exit);
}