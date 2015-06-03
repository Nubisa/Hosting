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

process.on("uncaughtException", function(err) {
    log("UncaughtException (" + whoami + "): " + err, true);
});

var options = null;
var pos = process.argv.indexOf("-opt");
if (pos > -1 && process.argv[pos + 1]) {
    var decoded = process.argv[pos + 1];
    options = JSON.parse(decoded);
} else {
    log("Options not found.", true);
    process.exit(7);
}

if (!options.home || !fs.existsSync(options.home)) {
    log("Unknown home dir.", true);
    process.exit(7);
}

var logPath = pathModule.join(options.home, options.log);
if (!logPath) {
    log("Unknown log path.", true);
    process.exit(7);
}
var logPathDir = pathModule.dirname(logPath);
var logClear = pathModule.join(logPathDir, "clearlog.txt");

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

    // subscribing to monitor as non-root user
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
            try {
                fs.mkdirSync(logPathDir);
            } catch (ex) {
                log("Cannot create log's directory: " + ex, true);
            }
        }
        if (fs.existsSync(logPathDir)) {
            try {
                fs.chownSync(logPathDir, uid, gid);
                //if (!options.plesk)
                    fs.chmodSync(logPathDir, "0711"); // others only execute
            } catch (ex) {
                log("Cannot set ownership of this log's directory: " + ex, true);
            }
        }

        if (!fs.existsSync(logPath)) {
            try {
                fs.writeFileSync(logPath, "");
            } catch(ex) {
                log("Cannot create log file: " + ex, true);
            }
        }

        if (fs.existsSync(logPath)) {
            try {
                fs.chownSync(logPath, uid, gid);
               // if (!options.plesk)
                    fs.chmodSync(logPath, "0644");  // others only read
            } catch (ex) {
                log("Cannot set ownership of this log file: " + ex, true);
            }
            try {
                out = fs.openSync(logPath, 'a');
            } catch (ex) {
                // logging will no be possible, but app can still run
            }
        }
    }

    var root_functions = require("./root_functions.js");
    var chokidar = require('chokidar');

    var file = pathModule.join(options.home, options.file);
    var appDir = pathModule.dirname(file);
    var spawner_data = null;

    // ########  saving nginx conf
    if (options.plesk) {
        try {
            var spawner_data_file = process.argv[1] + ".dat";
            if (!fs.existsSync(spawner_data_file)) {
                log("Cannot find spawner data file. Is application disabled in Plesk Panel?", true);
                process.exit(7);
            }

            var spawner_data_str = fs.readFileSync(spawner_data_file).toString();
            try {
                spawner_data = JSON.parse(spawner_data_str);
            } catch(ex){
                log("Cannot parse spawner data file.", true);
                process.exit(7);
            }

            if (spawner_data.disabled) {
                // exiting to prevent dummy process
                // however probably this condition will newer be fired, since for disabled apps
                // we remove spawner fire and spawner data file, so it will end up much sooner
                log("Application is disabled in Plesk Panel.");
                process.exit(7);
            }

            // nginx directives are no longer provided with argv to the spawner (options variable)
            // instead they are stored in spawner_xx.jx.dat file (spawner_data variable)
            // however options.nginx contain true, if directives are provided

            if (options.nginx && !spawner_data.nginx) {
                log("Could not find nginx directives in spawner data.");
                process.exit(7);
            }

            options.nginx = spawner_data.nginx;
            var ret = root_functions.saveNginxConfigFileForDomain(options);
            if (ret.err) {
                log(ret.err, true);
                process.exit(7);
            }
        } catch(ex) {
            log(ex.toString(), true);
            process.exit(7);
        }
    }

    if (spawner_data && spawner_data.domain)
        process.title = "jx " + spawner_data.domain;

    var child = null;
    // this can be done only by privileged user.
    // node throws exception otherwise
    // and if file does not exists, fileWatcher fill check for it later
    var runApp = function(){
        if (fs.existsSync(file)) {
            var spawn = require('child_process').spawn;
            var args = [file];
            if (options.args)
                args = args.concat(options.args);  //expected array (after options was parsed)

            child = spawn(process.execPath, args, { uid: uid, gid: gid, maxBuffer: 1e7, stdio: [ 'ignore', out, out ], cwd: appDir});

            child.on('error', function (err) {
                if (err.toString().trim().length) {
                    log("Child error: " + err, true);
                }
            });

            child.on('exit', function (code) {
                if (code && !exiting) {
                    log("Application exited by itself with code: " + code, true);
                }
                if (!exiting) {
                    exiting = true;
                    setTimeout(function(){
                        process.exit(55);
                    },2000);
                }
            });
        }
    };

    // if app is located in subfolders - let's create them
    if (!fs.existsSync(appDir)) {
        try {
            jxcore.utils.cmdSync('mkdir -p ' + appDir);
        } catch (ex) {
            log("Cannot create app's directory: " + ex, true);
            process.exit(7);
        }

        if (fs.existsSync(appDir)) {
            // if app is located in domain.home/sub1/sub2/sub3/index.js
            // then after we create /sub1/sub2/sub3/
            // we set ownership recursively for /sub1 folder
            var relative_arr = pathModule.normalize("/" + options.file).split(pathModule.sep);
            // result: [ '', 'sub1', 'sub2', 'sub3', 'index.js' ]
            if (relative_arr[1]) {
                var relative_root = pathModule.join(options.home, relative_arr[1]);
                var ret = jxcore.utils.cmdSync("chown -R " + uid + ":" + gid + " " + relative_root);
                if (ret.exitCode) {
                    log("Cannot set app's directory ownership: " + ex, true);
                    process.exit(7);
                }
            }
        }
    }

    // subscribing to monitor
    jxcore.monitor.followMe(function (err, txt) {
        if (err) {
            log("Did not subscribed (as root) to the monitor: " + txt, true);
        } else {
            log("Subscribed successfully: " + txt);

            try{
                runApp();
            }catch(ex){
                log("Cannot run the application: " + ex, true);
                exiting = true;
                process.exit();
            }

            var callback = function(event, path) {

                if (pathModule.dirname(path) === pathModule.normalize(logPathDir)) {

                    // condition below is not used by jxpanel (only by plesk)
                    if (options.plesk && path === logClear && out && out != "ignore" ) {
                        try {
                            fs.ftruncateSync(out, 0);
                            log("Log file cleared.");
                        } catch(ex) {
                            log("Could not clear the log file: " + ex, true);
                        }
                        try {
                            // removing clearlog.txt
                            if (fs.existsSync(logClear))
                                fs.unlinkSync(logClear);
                        } catch (ex) {
                            log("Could not remove clearlog file: " + ex, true);
                        }
                    }
                    // ignore other changes inside log directory
                    return;
                }

                if (exiting)
                    return;

                log("File change (" + event + ") detected on " + path.replace(options.home, ""));

                exiting = true;

                var counter = 0;
                var _inter = setInterval(function(){
                    counter++;

                    if(counter>=6 || fs.existsSync(file)){
                        clearInterval(_inter);

                        log("Restarting the application.")
                        process.exit(77);
                    }
                }, 500);
            };

            var errorCallback = function (error) {
                log('Watcher error: ' + error);
            };

            var test = [ "jxcore_logs/**/*",
                "node_modules",
                "nope.jx" ];05

            var rg = "(" + test.join("|") + ")";

//            for(var o in test) {
//                test[o] = new RegExp('^' + test[o] + '$');
//            }

            var opts = {
//                ignored: function(path) {
//
//                    var ignore = false;
////                    for(var o in test) {
//                        if (rg.test(path)) {
//                            console.log('IGNORED ' + path);
//                            return true;
//                        }
////                    }
//                    console.log('NOT IGNORED ' + path);
//                    return false;
//                },
                ignored : rg,
                persistent : true,
                ignoreInitial : true,
                ignorePermissionErrors : true,
                followSymlinks : false,
                interval : 2000,
                binaryInterval : 2000,
                // 'fsevents' module was manually deleted from node_modules
                // (it contained .node files and was not portable)
                // however this module is optional anyway
                useFsEvents : false,
                usePolling : false, // do not change or you might have "Error: watch ENOSPC"
                depth : 2
            };

            var filesToBeWatched = [
                pathModule.join(appDir, "**", "*.js"),
                pathModule.join(appDir, "**", "*.jx")
            ];

            chokidar.watch(filesToBeWatched, opts).on('all', callback).on('error', errorCallback);
            // separately as jxcore_logs is excluded in options
            chokidar.watch(logClear, { interval : 1000 }).on('all', callback).on('error', errorCallback);
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