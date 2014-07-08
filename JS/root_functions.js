/**
 * Created by nubisa_krzs on 6/25/14.
 */

var fs = require("fs");
var path = require("path");
var fw = require("./folderWatch.js");


exports.watch = function (dir, appLogDir, cb) {

    //var dir = path.dirname(appFileName);
    fw.watch(dir);

    fw.on('change', function (dir, file) {

        var fullPath = path.join(dir, "/", file);

        // skip files starting with "." (like .htaccess)
        if (file.slice(0, 1) === ".") return;
        // skip log files
        var d1 = path.normalize(dir + "/");
        var d2 = path.normalize(appLogDir + "/");

        if (d1.slice(0, d2.length) === d2) {
            if (cb && file.indexOf("clearlog.txt") != -1 && fs.existsSync(path.normalize(dir ,fullPath))) {
                cb({ clearlog: true, dir: dir, file : file });
            }
            return;
        }

        if (cb) {
            cb({ path: fullPath });
        }
    });
};


/**
 * Reads jx.config file located at jx folder.
 * @returns {*} Returns json object or null
 */
exports.readJXconfig = function () {
    var dir = path.dirname(process.execPath);
    var configFile = path.join(dir, "/", "jx.config");
//        log("main cfg file: " + configFile);
    if (!fs.existsSync(configFile)) {
        return null;
    } else {
        try {
            var str = fs.readFileSync(configFile);
            var json = JSON.parse(str);
            return json;
        } catch (ex) {
//            log("Cannot read or parse jx.config: " + ex, true);
            return null;
        }
    }
};


/**
 * Removes folder recursively
 * @param fullDir
 * @returns {boolean} True, if operation succeeded. False otherwise.
 */
exports.rmdirSync = function (fullDir) {

    fullDir = path.normalize(fullDir);
    if (!fs.existsSync(fullDir)) {
        return;
    }

    var cmd = process.platform === 'win32' ? "rmdir /s /q " : "rm -rf ";
    jxcore.utils.cmdSync(cmd + fullDir);

    return !fs.existsSync(fullDir);
};


exports.getUID = function(username) {
    if (process.platform === "win32") {
        return null;
    }

    var ret = jxcore.utils.cmdSync("id -g " + username);
    var uid = parseInt(ret.out);
    if (isNaN(uid)) {
        return null;
    } else {
        return uid;
    }
};