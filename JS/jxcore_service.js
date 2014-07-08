/**
 * Created by nubisa_krzs on 6/25/14.
 */


var fs = require('fs');
var path = require('path');
var https = require("https");
var http = require("http");
var url = require("url");
var root_functions = require("./root_functions.js");

var jxconfig = root_functions.readJXconfig();

var psaadm_uid = root_functions.getUID("psaadm");

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

        try {
            var stats = fs.statSync(fname);

            if (stats.uid !== psaadm_uid) {
                writeAnswer(res, "Wrong user id of the command.");
                return;
            }
        } catch (ex) {
            writeAnswer(res, "Cannot read stats of the command.");
            return;
        }


        // adding http just for being able to parse the command
        var str = "http://127.0.0.1:/cmd?" + fs.readFileSync(fname).toString().trim();
        var parsed = url.parse(str, true);
        fs.unlinkSync(fname);

        if (parsed.query.install) {
            var nameAndVersion = parsed.query.install;
            var name = nameAndVersion;
            var version = "";
            var pos = nameAndVersion.indexOf("@");
            if (pos > -1) {
                var name = nameAndVersion.slice(0, pos).trim();
                var version = nameAndVersion.slice(pos + 1).trim();
            }

            var cmd = "cd " + jxconfig.globalModulePath + "; '" + process.execPath + "' install " + nameAndVersion;
            //console.log("Installing npm module. name:", name, "version:", version, "with cmd: ", cmd);

            var ret = jxcore.utils.cmdSync(cmd);

            var expectedModulePath = path.join(jxconfig.globalModulePath, "/node_modules/", name);
            var answer = fs.existsSync(expectedModulePath) ? "OK" : "Error. " + ret.out;

            writeAnswer(res, answer);
            return;
        }

        if (parsed.query.remove) {

            var answer = "OK";
            try {
                var modulesDir = path.normalize(jxconfig.globalModulePath + "/node_modules/" + parsed.query.remove);

                var ok = true;
                if (fs.existsSync(modulesDir)) {
                    ok = root_functions.rmdirSync(modulesDir);
                }
                answer = ok ? "OK" : "Could not remove the folder.";
            } catch (ex) {
                answer = ex.toString()
            }

            writeAnswer(res, answer);
            return;
        }

        if (parsed.query.modules && parsed.query.modules == "info") {

            var answer = "OK";
            try {
                var modulesDir = path.normalize(jxconfig.globalModulePath + "/node_modules/");
                var folders = fs.readdirSync(modulesDir);

                var ret = [];
                for (var a = 0, len = folders.length; a < len; a++) {
                    if (folders[a].slice(0, 1) !== ".") {
                        var file = path.join(modulesDir, "/", folders[a], "/package.json");

                        try {
                            if (fs.existsSync(file)) {
                                var json = JSON.parse(fs.readFileSync(file));
                                ret.push(folders[a] + "|" + json.version + "|" + json.description);
                            }
                        } catch (ex) {
                        }
                    }
                }
                answer = ret.join("||");
            } catch (ex) {
                answer = ex.toString();
            }

            writeAnswer(res, answer);
            return;
        }


        if (parsed.query.nginx) {
            var cmd = null;
            var answer = "Unknown command.";
            if (parsed.query.nginx == "remove") {

                var dir = "/etc/nginx/jxcore.conf.d"

                if (parsed.query.domain) {
                    var fname = path.join(dir, "/", parsed.query.domain + ".conf");
                    if (fs.existsSync(fname)) {
                        try {
                            fs.unlinkSync(fname);
                        } catch (ex) {
                            answer = "Cannot remove confog for the application." + ex;
                        }

                        answer = fs.existsSync(fname) ? "Could not remove nginx config for the application." : "OK";
                    } else {
                        answer = "File does not exist."
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

