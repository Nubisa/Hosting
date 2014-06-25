/**
 * Created by nubisa_krzs on 6/25/14.
 */

var fs = require("fs");
var path = require("path");
var fw = require("./folderWatch.js");


exports.watch = function (appFileName, appLogDir, cb) {

    var dir = path.dirname(appFileName);// + path.sep;
    fw.watch(dir);
    console.log("watching", dir);

    var changed = false;

    fw.on('change', function (dir, file) {

        if (changed) return;

        // skip files starting with "." (like .htaccess)
        if (file.slice(0, 1) === ".") return;
        // skip log files
        var d1 = path.normalize(dir + "/");
        var d2 = path.normalize(appLogDir + "/");
//        console.log("dir",d1, "appLogDir", d2);
        if (d1.slice(0, d2.length) === d2) return;

        changed = true;


        if (cb) {
            setTimeout(function () {
                cb(true);
                changed = false;
            }, 500);
        }
        console.log("changed", dir, file);
    });
};



