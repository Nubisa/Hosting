/**
 * Created by nubisa_krzs on 6/3/14.
 */


var fs = require("fs");
var pathModule = require("path");
var os = require("os");

var whoami = jxcore.utils.cmdSync("whoami").out.toString().trim();
var isRoot = whoami.toString().trim() === "root";
var _str = JSON.stringify(process.argv);
// respawn_id is and old arg coming from monitor < v0.3.0.3
var respawned = _str.indexOf("respawn_id") > -1 || jxcore.monitor.respawned;
delete _str;
var exiting = false;

var log = function (str, error) {
    var str = "Spawner " + (error ? "error" : "info" ) + ":\t" + str;

    console.log(str);

    if (!logPath) return;

    if (fs.existsSync(logPath)) {
        fs.appendFileSync(logPath, str + os.EOL);
    }
};

var spawner_data_file = process.argv[1] + ".dat";
var readSpawnerDataFile = function(exitOnError) {

    try {
        var spawner_data_str = fs.readFileSync(spawner_data_file).toString();
    } catch (ex) {
        log("Cannot read spawner data file. Is application disabled in Plesk?", true);
        if (exitOnError)
            process.exit(7);
    }

    var data = null;
    try {
        data = JSON.parse(spawner_data_str);
    } catch(ex){
        log("Cannot parse spawner data file. " + ex, true);
        if (exitOnError)
            process.exit(7);
    }

    try {
        if (data) {
            data.env = parseAppEnv(data.env) || {};
            data.env.JX_PLESK_APP_HTTP_PORT = data.tcp;
            data.env.JX_PLESK_APP_HTTPS_PORT = data.tcps;
            data.env.JX_PLESK_APP_DOMAIN = data.domain;
            return data;
        }
    } catch(ex) {
        log("Cannot parse app environment variables." + ex, true);
    }

    return null;
};

// turns string "AA=aaa\nBB=bbb into object
var parseAppEnv = function(env) {
    if (!env) {
        env = {};
        return;
    }

    // string expected
    var lines = env.split("\n");
    env = {};
    for(var o in lines) {
        if (!lines.hasOwnProperty(o))
            continue;

        var pair = lines[o].split("=");
        if (pair.length !== 2 || !pair[0].trim() || !pair[1].trim())
            continue;

        env[pair[0]] = pair[1].trim();
    }
    return env;
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
            spawner_data = readSpawnerDataFile(true);

            if (spawner_data.disabled) {
                // exiting to prevent dummy process
                // however probably this condition will newer be fired, since for disabled apps
                // we remove spawner fire and spawner data file, so it will end up much sooner
                log("Application is disabled in Plesk.");
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
        process.title = "jx spawner " + spawner_data.domain;

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

            var env = spawner_data.env || {};
//            log("spawner_data " + JSON.stringify(spawner_data, null,4));
//            log("spawner_data " + JSON.stringify(spawner_data, null,4));

            child = spawn(process.execPath, args, {
                uid: uid,
                gid: gid,
                maxBuffer: 1e7,
                stdio: [ 'ignore', out, out ],
                cwd: appDir,
                env : env
            });

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
            log("Subscribed successfully (as root): " + txt);

            try{
                runApp();
            }catch(ex){
                log("Cannot run the application: " + ex, true);
                exiting = true;
                process.exit();
            }

            var watcher = null;
            var ignored_org = "jxcore_logs/**/*";

            var watcher_options = {
                persistent : true,
                ignoreInitial : true,
                ignorePermissionErrors : true,
                followSymlinks : false,
                interval : 2000,
                binaryInterval : 2000,
                // 'fsevents' module was manually deleted from node_modules
                // (it contained .node files and was not portable)
                // anyway this module is treated by chokidar as optional
                useFsEvents : false,
                usePolling : true, // do not change or you might have "Error: watch ENOSPC"
                depth : 2
            };

            // callback for internal support (log file/spawner data file)
            var callbackInternal = function (path, attempt) {

                if (attempt === undefined)
                    attempt = 0;
                else
                    attempt++;

                if (path === logClear && out && out != "ignore") {
                    try {
                        fs.ftruncateSync(out, 0);
                        log("Log file cleared.");
                    } catch (ex) {
                        log("Could not clear the log file: " + ex, true);
                    }
                    try {
                        // removing clearlog.txt
                        if (fs.existsSync(logClear))
                            fs.unlinkSync(logClear);
                    } catch (ex) {
                        log("Could not remove clearlog file: " + ex, true);
                    }
                    return;
                }

                if (path === spawner_data_file) {
                    var data = readSpawnerDataFile(false);
                    if (data)
                        watchAppFiles(data);
                    else {
                        if (attempt >= 3) {
                            log("Could not apply changes to Restart Manager.");
                        } else {
                            // try again
                            setTimeout(function() {
                                callbackInternal(path, attempt);
                            }, 100);
                        }
                    }
                }
            };

            // callback for user's apps restart watch
            var callbackRestart = function(event, path) {

                if (exiting) return;
                log("File change (" + event + ") detected on " + path.replace(options.home, ""));
                exiting = true;

                var counter = 0;
                var _inter = setInterval(function(){
                    counter++;
                    if(counter>=6 || fs.existsSync(file)){
                        clearInterval(_inter);
                        log("Restarting the application.");
                        process.exit(77);
                    }
                }, 500);
            };

            var errorCallback = function (error) {
                log('Watcher error: ' + error);
            };

            var watcherReloaded = 0;

            var resolve = function(mask) {
                mask = mask.trim();

                if (mask === "MAIN") return file;

                var appRoot = mask.slice(0,2) === "./";
                if (appRoot)
                    mask = pathModule.join(appDir, mask.slice(2));
                else
                    mask = pathModule.join(appDir, "**", mask);

                return mask;
            };

            var watchAppFiles = function(_spawner_data) {
                var watcher_enabled = true; // default
                var _ignored = [];
                var _watched = [];

                // skip if there is no change
                if (_spawner_data && JSON.stringify(spawner_data.watch) === JSON.stringify(_spawner_data.watch)) {
//                    log("No change in watcher data.");
                    return;
                }

                if (watcherReloaded)
                    log("Restart Manager is reloading configuration.");

                watcherReloaded++;
                if (watcherReloaded === Number.MAX_VALUE) watcherReloaded = 1;

                if (!_spawner_data)
                    _spawner_data = spawner_data;

//                log("watcher before = " + JSON.stringify(_spawner_data.watch).replace(/\n/g, "<br>"));
                // if there is no spawner_data.watch - the defaults are used
                if (_spawner_data && _spawner_data.watch) {
                    var _w = _spawner_data.watch;
                    if (_w.interval) watcher_options.interval = watcher_options.binaryInterval = _w.interval;
                    if (_w.depth || _w.depth === 0) watcher_options.depth = _w.depth;
                    if (_w.enabled != 1) {
                        watcher_enabled = false;
                        log("Restart manager is disabled by user.");
                    }

                    _w.ignore = _w.ignore || "";
                    if (ignored_org) _w.ignore += "\n" + ignored_org;

                    var _arr = _w.ignore.split("\n");
                    for(var o = 0, len = _arr.length; o < len; o++) {
                        var s = _arr[o].trim();
                        if (s) _ignored.push(resolve(s));
                    }

                    if (_w.paths) {
                        var _arr = _w.paths.split("\n");
                        for(var o = 0, len = _arr.length; o < len; o++) {
                            var s = _arr[o].trim();
                            if (s) _watched.push(resolve(s));
                        }
                    }
                }

                if (watcher) {
                    watcher.close();
                    watcher = null;
                }

                if (watcher_enabled) {

                    var filesToBeWatched = _watched;

                    if (!filesToBeWatched.length) filesToBeWatched =[
                        pathModule.join(appDir, "**", "*.js"),
                        pathModule.join(appDir, "**", "*.jx")
                    ];

                    watcher_options.ignored = _ignored;

                    log("Restart manager is active.");
//                    log("xxx opts = " + JSON.stringify(watcher_options, null, 4).replace(/\n/g, "<br>").replace(/\s\s\s\s/g, "&nbsp;&nbsp;&nbsp;&nbsp;"));
//                    log("xxx filesToBeWatched  = " + JSON.stringify(filesToBeWatched, null, 4).replace(/\n/g, "<br>").replace(/\s\s\s\s/g, "&nbsp;&nbsp;&nbsp;&nbsp;"));
                    watcher = chokidar.watch(filesToBeWatched, watcher_options).on('all', callbackRestart).on('error', errorCallback);
                }
            };

            if (options.plesk) {
                var internal_opts = {
                    interval : 2500,
                    ignoreInitial : true,
                    ignorePermissionErrors : true,
                    useFsEvents : false,
                    usePolling : true
                };
                // this should be watched always, however separately,
                // since jxcore_logs is excluded (ignored) in options
                chokidar.watch([ logClear, spawner_data_file ], internal_opts )
                    .on('add', callbackInternal)
                    .on('change', callbackInternal)
                    .on('error', errorCallback);
            }

            watchAppFiles();
        }
    }, function (delay) {
        log("Subscribing is delayed by " + delay + " ms.");
        setTimeout(function () {
        }, delay + 500);
    });

    var exit = function (code) {
//        log("xxx exit called");
        try {
            if (child) {
                var killed = process.kill(child.pid);
                if (!killed)
                    log("Spawner could not kill the app when exiting.");
            }
        } catch (ex) {
            log("Spawner could not kill the app when exiting: " + ex);
        }

        child = null;
        if(!exiting){
            exiting = true;
            process.exit(77);
        }
    };

    process.on('exit', exit);
    process.on('SIGBREAK', exit);
    process.on('SIGTERM', exit);
    process.on('SIGINT', exit);

//    log("xxx OnExit attached");
//
//    log("respawned ? " + jxcore.monitor.respawned);
//    log("process.env ? " + JSON.stringify(process.env, null,4));
}