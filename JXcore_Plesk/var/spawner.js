/**
 * Created by nubisa_krzs on 6/3/14.
 */


// START : copy from monitor.js  - BLOCK TO BE REMOVED

//var http = require('http');
//
//var sendRequest = function (path, jsonData, expectedAnswer, cb) {
//
//    var requestOptions = {
//        host: '127.0.0.1',
//        port: 17777,
//        path: '/' + path,
//        method: 'POST',
//        headers: {
//            'Content-Type': 'application/json',
//            'Content-Length': 0
//        }
//    };
//
//    var str = JSON.stringify(jsonData);
//    requestOptions.headers['Content-Length'] = str.length;
//
//    var req = http.request(requestOptions, function (res) {
//
//        res.setEncoding('utf-8');
//
//        var body = '';
//
//        res.on('data', function (data) {
//            body += data;
//        });
//
//        res.on('end', function () {
//            if (cb) {
//                if (body.toString().indexOf(expectedAnswer) === -1) {
//                    var err = "Problem with connecting to the monitor. Received not what expected: " + body;
//                    cb(true, err);
//                } else {
//                    cb(false, body);
//                }
//            }
//
//            req.connection.destroy();
//        });
//
//        res.on('error', function (err) {
//            if (cb) {
//                cb(false, err);
//            }
//        });
//    });
//
//    req.on('error', function (e) {
//        if (cb) {
//            cb(true, e);
//        }
//        req.connection.destroy();
//    });
//
//    req.write(str);
//    req.end();
//};
//
//
//var subscribe = function (pid) {
//    var json = { pid: pid, path: cmd[0], argv: cmd,
//        config: null, threadIDs: [-1] };
//
//    console.log("sending", json);
//    sendRequest("sending_data", json, "thanks_for_sending_the_data", function (err, msg) {
//        console.log(err ? "Cannot subscribe: " + msg : "Subscribed: " + msg);
//
//        if (err) process.exit();
//    });
//};


// END : copy from monitor.js

// usage:
// > jx spawner.js -u krisuser -log "log_path.txt" app.js

var fs = require("fs");
var path = require("path");
var os = require("os");

var whoami = jxcore.utils.cmdSync("whoami").out.toString().trim();
var isRoot = whoami.toString().trim() === "root";
var respawned = JSON.stringify(process.argv).indexOf("respawn_id") > -1;
var exitting = false;


var log = function(str, error) {
    var str = "Spawner "  + (error ? "error" : "info" )  + ":\t" + str;
    //fs.appendFileSync("/tmp/wiadro.txt", str + os.EOL);

    if (!logPath) return;

    if (isRoot) {
        fs.appendFileSync(logPath, str + os.EOL);
    } else {
        console.log(str);
    }
};



var options = null;
var optionsOrg = null;
var pos = process.argv.indexOf("-opt");
if (pos > -1 && process.argv[pos + 1]) {
    optionsOrg = process.argv[pos + 1];
    var buf = new Buffer(optionsOrg, 'base64');
    var decoded = buf.toString();

    console.log('decoded', decoded);
    options = JSON.parse(decoded);
    console.log('options', options);
} else {
    log("Options not found.", true);
    process.exit(7);
}


var logPath = options.log;
if (!logPath) {
    log("Unknown log path.", true);
    process.exit(7);
}

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
        setTimeout(function() {
            process.exit(77);
        }, delay + 500);
    });

} else {

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

    // this can be done only by privileged user.
    // node throws exception otherwise
    var spawn = require('child_process').spawn;

    var out = 'ignore';
    var err = 'ignore';

    if (logPath) {
        if (!fs.existsSync(logPath)) {
            fs.writeFileSync(logPath, "");
            try {
                fs.chownSync(logPath, uid, gid);
            } catch(ex) {
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

    var configFile = file + ".jxcore.config";
    fs.writeFileSync(configFile, JSON.stringify(options));

    var child = spawn(process.execPath, [file], { uid: uid, stdio: [ 'ignore', out, err ]});

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
        }
    }, function (delay) {
        log("Subscribing is delayed by " + delay+ " ms.");
        setTimeout(function() {}, delay + 500);
    });

    var exit = function(code) {
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
        } catch(ex) {
        }
    };

    process.on('exit', exit);
    process.on('SIGBREAK', exit);
    process.on('SIGTERM', exit);
    process.on('SIGINT', exit);
}