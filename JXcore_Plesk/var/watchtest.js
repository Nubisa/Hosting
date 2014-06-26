/**
 * Created by nubisa_krzs on 6/25/14.
 */


//var arg = process.argv[process.argv.length - 1];
//
//if (arg == "new") {
//    var fw = require("./root_functions.js");
//    fw.watch("/var/www/vhosts/krissubscription.com/httpdocs/", "/var/www/vhosts/krissubscription.com/httpdocs/jxcore_logs//", function () {
//        console.log("Files changed - restarting the application.");
//    });
//} else {
//    var fw = require("./folderWatch.js");
//    fw.watch("/var/www/vhosts/krissubscription.com/httpdocs");
//
//    fw.on('change', function (dir, file) {
//        console.log("changed", dir, file);
//    });
//}


var os = require('os');
var ifcs = os.networkInterfaces();

ifc_list = [];
for (var i in ifcs) {
    var arr = ifcs[i];
    for(var o in arr){
        if(arr[o] && arr[o].family === "IPv4")
        {
            ifc_list.push(arr[o].address);
        }
    }
}

console.log(ifc_list);