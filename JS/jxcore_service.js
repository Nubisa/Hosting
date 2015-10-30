/**
 * Created by nubisa_krzs on 6/25/14.
 */


var fs = require('fs');
var path = require('path');
var https = require("https");
var http = require("http");
var url = require("url");
var util = require("util");
var root_functions = require("./root_functions.js");
var semver = require('semver');

var npmModulesLatestVersions = {};

process.title = "jx service";
var jxconfig = root_functions.readJXconfig();
var psaadm = 'psaadm';
var psaadm_uid = root_functions.getUID(psaadm);
var psaadm_gid = root_functions.getUID(psaadm, true);
var modulesDir = path.normalize(jxconfig.globalModulePath + "/node_modules/");

var errors = [];

if (!psaadm_uid) {
    errors.push("Cannot determine uid for psaadm user.");
}
if (!jxconfig) {
    errors.push("Cannot read jxconfig.");
}

var writeAnswer = function (res, answer) {
    res.writeHead(200, {'Content-Type': 'text/plain'});

    if (errors.length) answer = errors.join(" ");

    res.end(answer ? answer : "Unknown command.");
};


var getMonitorJSON = function (cb) {
    if (!cb) {
        return;
    }

    var options = {
        hostname: 'localhost',
        port: 17777,
        path: '/json?silent=true',
        method: 'GET',
        rejectUnauthorized: false
    };

    https.get(options,function (res) {
        var body = "";

        res.on('data', function (chunk) {
            body += chunk;
        });

        res.on('end', function () {
            try {
                var json = JSON.parse(body);
                cb(false, json);
            } catch (ex) {
                cb(true, "Cannot parse json: " + ex);
            }
        });
    }).on('error', function (e) {
        cb(true, e.toString())
    });
};


var options = {
    key: fs.readFileSync(path.join(__dirname, "server.key")),
    cert: fs.readFileSync(path.join(__dirname, "server.crt"))
};

var srv = https.createServer(options, function (req, res) {

    var parsedUrl = url.parse(req.url, true);

    if (parsedUrl.pathname == "/cmd" && parsedUrl.query && parsedUrl.query.cuid) {

        var fname = path.normalize(process.execPath + "_" + parsedUrl.query.cuid.trim() + ".cmd");
        if (!fs.existsSync(fname)) {
            writeAnswer(res);
            return;
        }

//        try {
//            var stats = fs.statSync(fname);
//
//            if (stats.uid !== psaadm_uid) {
//                writeAnswer(res, "Wrong user id of the command.");
//                return;
//            }
//        } catch (ex) {
//            writeAnswer(res, "Cannot read stats of the command.");
//            return;
//        }


        var fstr = fs.readFileSync(fname).toString('utf8').trim();
        if (fstr.slice(0,1) == "{") {
            // new way with params as json saved in file
            try {
                var parsed = JSON.parse(fstr);
                // for old compatibility:
                parsed.query = {};
            } catch (ex) {
                writeAnswer(res, "Cannot parse json command: " + ex.toString());
                return;
            }
        } else {
            // old way with GET url saved in file
            // adding http just for being able to parse the command
            var str = "http://127.0.0.1:/cmd?" + fstr;
            var parsed = url.parse(str, true);
        }

//        fs.writeFileSync("/tmp/parsed.txt", JSON.stringify(parsed, null, 4));
        fs.unlinkSync(fname);

        var method = null;
        if (parsed.query.modules == "install") method = npmModules_install;
        if (parsed.query.modules == "info") method = npmModules_list;
        if (parsed.query.modules == "remove") method = npmModules_remove;
        if (parsed.query.modules == "removeLog") method = npmModules_removeLog;
        if (parsed.query.modules == "checkForUpdates") method = npmModules_checkForUpdates;
        if (parsed.query.modules == "update") method = npmModules_update;

        if (method) {
            method(parsed, function(err, txt) {
                writeAnswer(res, err || (txt || 'OK'));
            });
            return;
        }

        if (parsed.query.nginx) {
            var cmd = null;
            var answer = "Unknown command.";
            if (parsed.query.nginx == "remove") {

                var dir = "/etc/nginx/jxcore.conf.d";

                if (parsed.query.domain) {
                    var fname = path.join(dir, "/", parsed.query.domain + ".conf");
                    if (fs.existsSync(fname)) {
                        try {
                            fs.unlinkSync(fname);
                            answer = fs.existsSync(fname) ? "Could not remove nginx config for the application." : "OK";
                        } catch (ex) {
                            answer = "Cannot remove config for the application." + ex;
                        }
                    } else {
                        answer = "Nginx config file for the domain does not exist.";
                    }

                } else if (parsed.query.all && parsed.query.all == 1) {
                    var fname = "/etc/nginx/conf.d/jxcore.conf";
                    var cmd = "rm -rf " + dir + "; rm -f " + fname + "; /etc/init.d/nginx reload";
                    jxcore.utils.cmdSync(cmd);

                    answer = fs.existsSync(fname) || fs.existsSync(dir) ? "Could not remove nginx configs." : "OK";
                }
            }

            writeAnswer(res, answer);
            return;
        }

        if (parsed.query.delete) {
            if (parsed.query.delete == "monitorlogs") {

                var answer = "OK";
                try {
                    var dir = path.dirname(process.execPath);
                    var files = fs.readdirSync(dir);

                    for (var a = 0, len = files.length; a < len; a++) {
                        if (files[a].slice(-4) === ".log") {
                            var file = path.join(dir, "/", files[a]);
                            fs.unlinkSync(file);
                            if (fs.existsSync(file)) answer = "Cannot delete some of log files.";
                        }
                    }
                } catch (ex) {
                    answer = ex.toString()
                }

                writeAnswer(res, answer);
                return;
            }


            if (parsed.query.delete == "applog" && parsed.query.path) {
                try {
                    if (fs.existsSync(parsed.query.path)) {
                        fs.unlinkSync(parsed.query.path);
                    }
                    writeAnswer(res, 'OK');
                } catch (ex) {
                    writeAnswer(res, "Cannot delete log file: " + ex);
                }
                return;
            }
        }

        if (parsed.query.kill) {
            var id = parseInt(parsed.query.kill);
            var answer = null;
            if (isNaN(id)) {
                writeAnswer(res, "Unknown id.");
            } else {
                var fname = "spawner_" + id + ".jx";
                var killAll = (id == -1);
                var error = false;

                getMonitorJSON(function (err, json) {
                    if (err) {
                        writeAnswer(res, "Error while connecting to the monitor: " + json);
                    } else {
                        for (var pid in json) {
                            var info = json[pid];
                            if (info.path && (info.path.indexOf(fname) > -1 || killAll)) {

                                // dont kill itself
                                if (info.pid === process.pid) {
                                    continue;
                                }

                                try {
                                    process.kill(info.pid);

                                    if (!killAll) writeAnswer(res, "OK");
                                } catch (ex) {
                                    error = true;
                                    if (!killAll) writeAnswer(res, "Could not kill the application " + fname);
                                }

                                if (!killAll) return;
                            }
                        }

                        if (killAll) {
                            writeAnswer(res, error ? "Some of the applications were not killed." : "OK");
                        } else {
                            writeAnswer(res, "The application " + fname + " is not monitored or is not running.");
                        }
                    }
                });
            }
            return;
        }

        if (parsed.query.get_version) {

            if (parsed.query.get_version === "patch") {
                var getXMLTag = function(tagName, txt, attr) {
                    var anything = "([\\s\\S]*?)";
                    if (!attr) attr = ""; else attr = anything + attr + anything ;
                    var str = "<" + tagName + anything + ">" +anything + "<\\/" + tagName + ">";
                    var res = new RegExp(str).exec(txt);
                    return res && res.length > 2 ? res[2].trim() : null;
                };

                var getXMLAttr = function(attrName, txt) {
                    var anything = "([\\s\\S]*?)";
                    var str = attrName + '="' + anything + '"';
                    var res = new RegExp(str).exec(txt);
                    return res && res.length > 1 ? res[1].trim() : null;
                };

                try {
                    var ret = jxcore.utils.cmdSync('cat /root/.autoinstaller/microupdates.xml');
                    if (ret.exitCode) {
                        writeAnswer(ret, "Could not read patch version.");
                        return;
                    }

                    var patches = getXMLTag("patches", ret.out);
                    var plesk = getXMLTag("product", patches, 'id="plesk"');
                    var ver = getXMLAttr("version", plesk);

                    writeAnswer(res, patches && plesk && ver ? ver : "Could not fetch patch version.");
                } catch (ex) {
                    writeAnswer(res, "Could not read patch version. " + ex);
                }
                return;
            }
        }

        // new way of passing arrays - as parsable json:
        if (parsed.cmd == "nginx-test") {

            if (!parsed.spawner_args) {
                writeAnswer(res, "Spawner args not provided to callService method.");
                return;
            }

            var options = parsed.spawner_args; // this is a json object
            options.nginx = parsed.arg;  // nginx directives to be tested. may be empty as well
            var ret = root_functions.saveNginxConfigFileForDomain(options, true);

            writeAnswer(res, ret.err || "OK");
            return;
        }

    }

    writeAnswer(res);
});

srv.on('error', function (e) {
    console.error("Server error: \n" + e);
});

srv.on("listening", function () {
//    console.log("listening 2001");
});

srv.listen(18999, "127.0.0.1");


try {
    require("./nginxWatch.js");
} catch (ex) {
    console.log("Cannot require ./nginxWatch.js", ex);
}


var npmModules_check = function (parsed, checkModuleName) {

//    console.log(parsed);
    var ret = {};

    // here the node_modules will be created
    ret.baseDir = parsed.query.dir;
    if (!ret.baseDir || !fs.existsSync(ret.baseDir))
        return { err: 'Wrong directory.'};

    ret.userName = parsed.query.user;
    if (!ret.userName || !root_functions.getUID(ret.userName))
        return { err: 'Wrong user name.'};

    ret.moduleName = parsed.query.name;
    if (checkModuleName && !ret.moduleName)
        return { err: 'Wrong module name.'};

    ret.modulesDir = path.join(ret.baseDir, 'node_modules');

    // extra check
    if (ret.userName !== psaadm && ret.modulesDir === modulesDir)
        return { err: 'Access denied for user.'};

    return ret;
};

var npmModules_install = function (parsed, cb) {

    var errorAnswer = null;
    try {
        var ret = npmModules_check(parsed, true);
        if (ret.err)
            return cb(ret.err);

        var namesToInstall = parsed.query.name.toString().replace(/ -g/gi, '').replace(/ --global/gi, '');

        if (!fs.existsSync(ret.modulesDir))
            fs.mkdirSync(ret.modulesDir);

        root_functions.chownSyncRecursive(ret.modulesDir, ret.userName);

        var npmlog = path.join(ret.baseDir, 'npm-debug.log');
        if (fs.existsSync(npmlog))
            fs.unlinkSync(npmlog);

        var cmd = util.format('cd "%s"; "%s" install %s --no-bin-links', ret.baseDir, process.execPath, namesToInstall);
//        console.log("Installing npm module. name:", parsed.query.name, "namesToInstall:", namesToInstall, "with cmd: ", cmd, "\n");
        var cmdResult = jxcore.utils.cmdSync(cmd);

        // chmod + chown for installed modules
        var files = fs.readdirSync(ret.modulesDir);

        for (var a = 0, len = files.length; a < len; a++) {
            var _mod = path.join(ret.modulesDir, files[a]);
            var _stat = fs.statSync(_mod);
//            console.log(_mod, _stat.isDirectory());
            if (!_stat.isDirectory())
                continue;

            root_functions.chownSyncRecursive(_mod, ret.userName, _stat);

            var _mark = path.join(_mod, '_jx_chmoded');
            if (!fs.existsSync(_mark)) {
                try {
                    root_functions.chmodSyncRecursive(_mod);
                    fs.writeFileSync(_mod, 'ok');
                } catch (ex) {
                    // ignore chmod errors for now
                }
            }
        }

        if (fs.existsSync(npmlog)) {
            fs.appendFileSync(npmlog, "\njx install " + namesToInstall + "\n");
            fs.chownSync(npmlog, root_functions.getUID(psaadm), root_functions.getUID(psaadm, true));
            errorAnswer = "Error" + cmdResult.out;
        }
        npmModules_checkForUpdates(parsed);
    } catch (ex) {
        errorAnswer = ex.toString();
    }

    cb(errorAnswer);
};


var npmModules_list = function (parsed, cb) {

    var errorAnswer = null;
    try {

        var ret = npmModules_check(parsed);
        if (ret.err)
            return cb(ret.err);

        // no error. panel will print empty list
        if (!fs.existsSync(ret.modulesDir))
            return cb(null, "OK");

        var folders = fs.readdirSync(ret.modulesDir);

        var arr = [];
        for (var a = 0, len = folders.length; a < len; a++) {
            if (folders[a].slice(0, 1) !== ".") {
                var file = path.join(ret.modulesDir, folders[a], "package.json");

                try {
                    if (fs.existsSync(file)) {
                        var json = JSON.parse(fs.readFileSync(file));
                        var info = npmModulesLatestVersions[folders[a]] || "Check for updates";
                        arr.push(folders[a] + "|" + json.version + "|" + info + "|" + json.description);
                    } else {

                    }
                } catch (ex) {
                }
            }
        }
    } catch (ex) {
        errorAnswer = ex.toString();
    }

    cb(errorAnswer, arr && arr.join("||"));
};


var npmModules_remove = function (parsed, cb) {

    var errorAnswer = null;
    try {

        var ret = npmModules_check(parsed, true);
        if (ret.err)
            return cb(ret.err);

        var nameToRemove = parsed.query.name;
        var all = nameToRemove === "_all_";

        var _dir = all ? ret.modulesDir : path.join(ret.modulesDir, nameToRemove);
        if (fs.existsSync(_dir))
            root_functions.rmdirSync(_dir);

        if (fs.existsSync(_dir))
            errorAnswer = "Could not remove the folder.";

        if (!all)
            npmModules_checkForUpdates(parsed);

    } catch (ex) {
        errorAnswer = ex.toString()
    }

    cb(errorAnswer);
};

var npmModules_removeLog = function (parsed, cb) {

    var errorAnswer = null;
    try {

        var ret = npmModules_check(parsed);
        if (ret.err)
            return cb(ret.err);

        var file = path.join(ret.baseDir, 'npm-debug.log');
        if (fs.existsSync(file))
            fs.unlinkSync(file);

        if (fs.existsSync(file))
            errorAnswer = "Could not remove the file.";

    } catch (ex) {
        errorAnswer = ex.toString()
    }

    cb(errorAnswer);
};


var npmModules_checkForUpdates = function (parsed, cb) {

    var errorAnswer = null;
    try {

        var ret = npmModules_check(parsed);
        if (ret.err)
            return cb ? cb(ret.err) : null;

        // no error. just no modules.
        if (!fs.existsSync(ret.modulesDir))
            return cb ? cb(null, "OK") : null;

        var folders = fs.readdirSync(ret.modulesDir);

        var npmlog = path.join(ret.baseDir, 'npm-debug.log');
        var npmlog_backup = path.join(ret.baseDir, 'npm-debug.log.backup');
        // backing up npm-debug.log
        if (fs.existsSync(npmlog))
            fs.renameSync(npmlog, npmlog_backup);

        npmModulesLatestVersions.__checking_in_progress = true;
        for (var a = 0, len = folders.length; a < len; a++) {
            if (folders[a].slice(0, 1) !== ".") {
                var dir = path.join(ret.modulesDir, folders[a]);
                var _stat = fs.statSync(dir);
                if (!_stat.isDirectory())
                    continue;

                var file = path.join(dir, "package.json");

                try {
                    if (fs.existsSync(file)) {
                        var json = JSON.parse(fs.readFileSync(file));

                        var cmd = util.format('cd "%s"; "%s" npm view %s version --loglevel silent', ret.baseDir, process.execPath, folders[a]);
                        var cmdResult = jxcore.utils.cmdSync(cmd);

                        var v = cmdResult.out.trim();
//                        console.log("Check for update:", folders[a],
//                            "\ncmd:", cmd,
//                            "\nver: ", ">" + v + "<",
//                            "\njson ver: ", ">" + json.version + "<",
//                            "\nsemver valid:", semver.valid(v)
//                            "\nlt", semver.lt(json.version, v),
//                            "\ngt", semver.gt(json.version, v)
//                        );
                        var info = null;
                        if (!semver.valid(v)) {
                            info = "Invalid remote version"
                        } else {
                            if (semver.lt(json.version, v)) {
                                info = "#Update to " + v;
                            }
                            if (semver.gt(json.version, v)) {
                                info = "This is newer"
                            } else if (json.version === v) {
                                info = "This is the latest";
                            }
                        }

                        if (!info)
                            info = util.format("Could not compare %s vs %s", json.version, v);

                        npmModulesLatestVersions[folders[a]] = info;
                    }
                } catch (ex) {
                    console.log("exception", ex);
                }
            }
        }
        npmModulesLatestVersions.__lastCheck = Date.now();
        npmModulesLatestVersions.__checking_in_progress = false;

    } catch (ex) {
        errorAnswer = ex.toString()
    }

    try {
        // restoring from backup npm-debug.log
        if (fs.existsSync(npmlog_backup))
            fs.renameSync(npmlog_backup, npmlog);
    } catch (ex) {
    }

    if (cb) cb(errorAnswer);
};

var npmModules_update = function (parsed, cb) {

    var errorAnswer = null;
    try {
        var ret = npmModules_check(parsed, true);
        if (ret.err)
            return cb(ret.err);

        if (!fs.existsSync(ret.modulesDir))
            return cb("OK");

        var nameForUpdate = parsed.query.name;
        if (nameForUpdate === "_all_") nameForUpdate = "";

        root_functions.chownSyncRecursive(ret.modulesDir, ret.userName);

        var npmlog = path.join(ret.baseDir, 'npm-debug.log');
        if (fs.existsSync(npmlog))
            fs.unlinkSync(npmlog);

        var cmd = util.format('cd "%s"; "%s" npm update %s --no-bin-links', ret.baseDir, process.execPath, nameForUpdate);
        var cmdResult = jxcore.utils.cmdSync(cmd);
        console.log("Updating npm module. name:", nameForUpdate, "with cmd: ", cmd);
        console.log(cmdResult);

        // chmod + chown for installed modules
        var files = fs.readdirSync(ret.modulesDir);

        for (var a = 0, len = files.length; a < len; a++) {
            var _mod = path.join(ret.modulesDir, files[a]);
            var _stat = fs.statSync(_mod);
//            console.log(_mod, _stat.isDirectory());
            if (!_stat.isDirectory())
                continue;

            root_functions.chownSyncRecursive(_mod, ret.userName, _stat);

            var _mark = path.join(_mod, '_jx_chmoded');
            if (!fs.existsSync(_mark)) {
                try {
                    root_functions.chmodSyncRecursive(_mod);
                    fs.writeFileSync(_mod, 'ok');
                } catch (ex) {
                    // ignore chmod errors for now
                }
            }
        }

        if (fs.existsSync(npmlog)) {
            fs.appendFileSync(npmlog, "\njx update " + namesToInstall + "\n");
            fs.chownSync(npmlog, root_functions.getUID(psaadm), root_functions.getUID(psaadm, true));
            errorAnswer = "Error" + cmdResult.out;
        } else {
            if (cmdResult.exitCode)
                errorAnswer = cmdResult.out;
        }

        npmModules_checkForUpdates(parsed);

    } catch (ex) {
        errorAnswer = ex.toString();
    }

    cb(errorAnswer);
};



// 30 minutes
var keepAlive = 30 * 1000 * 60;
var cleanNpmModulesLatestVersions = function () {

    if (npmModulesLatestVersions.__checking_in_progress || !npmModulesLatestVersions.__lastCheck)
        return;

    if (Date.now() - npmModulesLatestVersions.__lastCheck < keepAlive)
        return;

    npmModulesLatestVersions = {};
};


setInterval(cleanNpmModulesLatestVersions, 5 * 1000 * 60)