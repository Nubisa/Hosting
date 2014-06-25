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
            var cmd = "cd " + jxconfig.globalModulePath + "; '" + process.execPath + "' install " + parsed.query.install;
            console.log("Installing npm module", parsed.query.install);

            var ret = jxcore.utils.cmdSync(cmd);
            console.log(ret);

            var expectedModulePath = path.join(jxconfig.globalModulePath, "/node_modules/", parsed.query.install);
            var answer = fs.existsSync(expectedModulePath) ? "OK" : ret.out;

            res.writeHead(200, {'Content-Type': 'text/plain'});
            res.end(answer);

        } else if (parsed.query.nginx) {
            if (parsed.query.nginx == "reload") {
                var cmd = "/etc/init.d/nginx reload";

                var ret = jxcore.utils.cmdSync(cmd);

                var answer = ret.exitCode ? ret.out : "OK";

                res.writeHead(200, {'Content-Type': 'text/plain'});
                res.end(answer);
            }
        }
    }
});

srv.on('error', function (e) {
    console.error("Server error: \n" + e);
});

srv.on("listening", function () {
    console.log("listening 2001");
});

srv.listen(8000, "0.0.0.0");

