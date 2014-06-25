/**
 * Created by nubisa_krzs on 6/25/14.
 */


var arg = process.argv[process.argv.length - 1];

if (arg == "new") {
    var fw = require("./root_functions.js");
    fw.watch("/var/www/vhosts/krissubscription.com/httpdocs/index2.js", "/var/www/vhosts/krissubscription.com/httpdocs/jxcore_logs//", function () {
        console.log("Files changed - restarting the application.");
    });
} else {
    var fw = require("./folderWatch.js");
    fw.watch("/var/www/vhosts/krissubscription.com/httpdocs");

    fw.on('change', function (dir, file) {
        console.log("changed", dir, file);
    });
}
