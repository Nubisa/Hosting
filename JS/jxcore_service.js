/**
 * Created by nubisa_krzs on 6/25/14.
 */


var fs = require('fs');
var path = require('path');
var http = require("http");
var url = require("url");
var root_functions = require("./root_functions.js");

var jxconfig = root_functions.readJXconfig();

if (!jxconfig) {
    console.log("Cannot read jxconfig.");
}

var srv = http.createServer(function (req, res) {

    var parsed = url.parse(req.url, true);

    if (parsed.pathname == "/cmd" && parsed.query) {


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
            console.log("Installing npm module. name:", name, "version:", version, "with cmd: ", cmd);

            var ret = jxcore.utils.cmdSync(cmd);
            console.log(ret);

            var expectedModulePath = path.join(jxconfig.globalModulePath, "/node_modules/", name);
            var answer = fs.existsSync(expectedModulePath) ? "OK" : ret.out;

            res.writeHead(200, {'Content-Type': 'text/plain'});
            res.end(answer);
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

            res.writeHead(200, {'Content-Type': 'text/plain'});
            res.end(answer);
            return;
        }

        if (parsed.query.modules && parsed.query.modules == "info") {

            var answer = "OK";
            try {
                var modulesDir = path.normalize(jxconfig.globalModulePath + "/node_modules/");
                var folders = fs.readdirSync(modulesDir);
//                console.log("dir", folders);

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

            res.writeHead(200, {'Content-Type': 'text/plain'});
            res.end(answer);
            return;
        }


        if (parsed.query.nginx) {
            var cmd = null;
            var answer = null;
            if (parsed.query.nginx == "reload") {
                cmd = "/etc/init.d/nginx reload";
            } else
            if (parsed.query.nginx == "start") {
                cmd = "/opt/psa/admin/sbin/nginxmng -e";
            }
            if (parsed.query.nginx == "check") {
                var tmp = "/opt/psa/admin/sbin/nginxmng -s";
                var ret = jxcore.utils.cmdSync(tmp);
                answer = ret.out;
            }

            if (cmd) {
                var ret = jxcore.utils.cmdSync(cmd);
                answer = ret.exitCode ? ret.out : "OK";
            } else {
                if (!answer)
                    answer = "Unknown command.";
            }

            res.writeHead(200, {'Content-Type': 'text/plain'});
            res.end(answer);

            return;
        }

        if (parsed.query.delete) {
            if (parsed.query.delete == "monitorlogs") {

                var answer = "OK";
                try {
                    var dir = path.dirname(process.execPath);
                    var files = fs.readdirSync(dir);
//                    console.log("dir", files);

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

                res.writeHead(200, {'Content-Type': 'text/plain'});
                res.end(answer);
            }
            return;
        }


        res.writeHead(200, {'Content-Type': 'text/plain'});
        res.end("Unknown command.");

    }
});

srv.on('error', function (e) {
    console.error("Server error: \n" + e);
});

srv.on("listening", function () {
//    console.log("listening 2001");
});

srv.listen(18999, "127.0.0.1");

