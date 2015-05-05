/**
 * Created by nubisa_krzs on 6/25/14.
 */


var fs = require('fs');
var path = require('path');
var https = require("https");
var http = require("http");
var url = require("url");
var root_functions = require("./root_functions.js");
require("./nginxWatch.js");

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

        if (parsed.query.install) {
            var nameAndVersion = parsed.query.install;
            var name = nameAndVersion;
            var version = "";
            var pos = nameAndVersion.indexOf("@");
            if (pos > -1) {
                var name = nameAndVersion.slice(0, pos).trim();
                var version = nameAndVersion.slice(pos + 1).trim();
            }

            var answer = "OK";
            try {
                var cmd = "cd " + jxconfig.globalModulePath + "; '" + process.execPath + "' install " + nameAndVersion;
                //console.log("Installing npm module. name:", name, "version:", version, "with cmd: ", cmd);

                var ret = jxcore.utils.cmdSync(cmd);

                var expectedModulePath = path.join(jxconfig.globalModulePath, "/node_modules/", name);
//            var answer = fs.existsSync(expectedModulePath) ? "OK" : "Error. " + ret.out;

                // now testing if it's installed
                var cmd = "cd " + jxconfig.globalModulePath + "; '" + process.execPath + "' install -ls --depth=0";
                var ret1 = jxcore.utils.cmdSync(cmd);
                var ok = ret1.out.toString().indexOf("─ " + nameAndVersion) !== -1;

                answer = ok ? "OK" : "Error. " + ret.out;

                if (!ok)
                    root_functions.rmdirSync(expectedModulePath);
            } catch(ex) {
                answer = ex.toString()
            }

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

