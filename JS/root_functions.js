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

// parses args string into array with proper quoted values, e.g.: s1 s2="s s" s3='test '
exports.parseUserArgs = function(args_str) {

    if (args_str) {
        var jxPath = '"' + process.execPath + '"';

        var ret = jxcore.utils.cmdSync(jxPath + ' -e "console.log(JSON.stringify(process.argv.slice(1)))" '+ args_str);
        if (ret.exitCode)
            return { err : true };

        try {
            return JSON.parse(ret.out);
        } catch(ex) {
            return { err : true };
        }
    } else {
        return [];
    }
};



/*
options:

 opt: {
     "user" : "krisuser",
     "log" : "/var/www/vhosts/krissubscription.com/httpdocs/jxcore_logs/index.txt",
     "file" : "/var/www/vhosts/krissubscription.com/httpdocs/index.js",
     "domain" : "krissubscription.com",
     "tcp" : "10008",
     "tcps" : "10009",
     "nginx" : "",
     "logWebAccess" : "0"}
 }
 */
exports.saveNginxConfigFileForDomain = function(options, onlyForTest) {

    var confDir = "/etc/nginx/jxcore.conf.d/";
    // for test we don't add .conf ext so nginx will not take this file during reloading
    var confFile = confDir + options.domain + (onlyForTest ? "" : ".conf");

    var ssl_info = null;
    if (options.ssl_key && options.ssl_crt) {
        ssl_info = { key : options.ssl_key, crt : options.ssl_crt };
    }

    if (fs.existsSync(confDir)) {
        var nginx = require("./nginxconf.js");
        nginx.resetInterfaces();
        var logWebAccess = options.logWebAccess == 1 || options.logWebAccess == "true";
        var conf = nginx.createConfig(options.domain, [ options.tcp, options.tcps], logWebAccess ? path.dirname(options.log) : null, options.nginx, ssl_info);
        conf = conf.replace(/JX_ROOT/g, options.home);

        if (onlyForTest) {
            conf = "events {} http { \n" + conf + "\n}";
        }

        try {
            fs.writeFileSync(confFile, conf);
            var ret = jxcore.utils.cmdSync("chown psaadm:nginx " + confFile + ";");
            if (ret.exitCode)
                return { err : "Cannot set ownership for nginx config: " + ret.out };
        } catch (ex) {
            return { err : "Cannot save nginx conf file: " };
        }

        if (onlyForTest) {
            // testing conf file
            var ret = jxcore.utils.cmdSync("/usr/sbin/nginx -t -c " + confFile);

            try {
                fs.unlinkSync(confFile);
            } catch(ex){}

            if (ret.out.toString().indexOf("failed") !== -1) {
                return { err : ret.out.replace(new RegExp(confDir, "ig"), "[...]") };
            }
        }

        return false;
    } else {
        return { err : "Nginx config dir does not exists." };
    }


};



exports.chmodSyncRecursive = function(dir) {

    if (!fs.existsSync(dir))
        return { err : 'Cannot chmod ' + dir + '. Folder does not exists.'};

    var files = fs.readdirSync(dir);

    try {
        // for directories, others only read and execute
        fs.chmodSync(dir, '0755');
    } catch(ex) {
        return { err : 'Cannot chmod ' + dir + '. ' + ex };
    }

    var ret = true;

    for(var o in files) {
        if (!files.hasOwnProperty(o))
            continue;

        var _path = path.join(dir, files[o]);
        var stat = fs.statSync(_path);

        if (stat.isDirectory()) {
            var ret1 = exports.chmodSyncRecursive(_path);
            if (ret1.err)
                ret = ret1;
        } else {
            // for files, others only read
            try {
                fs.chmodSync(_path, '0644');
            } catch(ex) {
                // ignore file permission errors for now
            }
        }
    }

    return ret;
};