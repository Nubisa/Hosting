/**
 * Created by nubisa_krzs on 6/25/14.
 */


var fs = require("fs");
var path = require("path");
var os = require("os");

var whoami = jxcore.utils.cmdSync("whoami").out.toString().trim();
var isRoot = whoami.toString().trim() === "root";
var respawned = JSON.stringify(process.argv).indexOf("respawn_id") > -1;

var logPath = __filename + ".log";

var log = function (str, error) {
    if (isRoot && respawned) {
        fs.appendFileSync(logPath, str + os.EOL);
    } else {
        console.log(str);
    }
};

var pos = process.argv.indexOf("-opt");
if (pos > -1 && process.argv[pos + 1]) {
    var decoded = process.argv[pos + 1];
    options = JSON.parse(decoded);
} else {
    log("Options not found.", true);
    process.exit(7);
}

if (!isRoot || !respawned) {
    // exiting, so monitor can spawn the spawner as root
    // and then the spawner may spawn the app as -u (user)

    log("Exiting, to be respawned by JXcore monitor.");

    // subscribing to monitor
    jxcore.monitor.followMe(function (err, txt) {
        if (err) {
            log("Did not subscribed to the monitor: " + txt, true);
        }
    }, function (delay) {
        setTimeout(function () {
            process.exit(77);
        }, delay + 500);
    });

} else {

    try {
        fs.unlinkSync(logPath);
    } catch(ex){
    }

    if (options.cmd) {
        log("executing cmd: " + options.cmd);

        var ret = jxcore.utils.cmdSync(options.cmd);
        log("result:" + JSON.stringify(ret));
    }
}