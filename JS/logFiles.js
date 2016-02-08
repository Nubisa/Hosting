/**
 * Created by nubisa_krzs on 1/22/16.
 */

var fs = require('fs');
var path = require('path');

var psaadm = 'psaadm';
exports.psaadm_uid = null;
exports.psaadm_gid = null;

exports.get = function(ret, remove) {
  return new logFiles(ret, remove);
};

var logFiles = function(ret, remove) {

    var _this = this;

    this.npm_debug = path.join(ret.baseDir, 'npm-debug.log');
    this.npm_out = path.join(ret.baseDir, 'command.log');

    var _delete = function(file) {
        try {
            if (fs.existsSync(file))
                fs.unlinkSync(file);
        } catch(ex) {
            return ex + '';
        }

        if (fs.existsSync(file))
            return "Could not remove the file. File still exists.";
    };

    this.delete = function() {
        var ret = '';
        ret += _delete(_this.npm_debug) || '';
        ret += _delete(_this.npm_out) || '';
        return ret;
    };

    this.append = function(title, cmd, fullOutput, ret) {

        var arr = [];
        arr.push('command: ' + title);
        arr.push('full command: ' + cmd);
        if (ret) {
            if (ret.userName && ret.modulesDir)
                arr.push('chown of ' + ret.modulesDir + ' made for ' + ret.userName);
        }

        fs.writeFileSync(_this.npm_out, '#INFO#' + arr.join('\n#INFO#') + '\n\n' + fullOutput.trim());
        fs.chownSync(_this.npm_out, exports.psaadm_uid, exports.psaadm_gid);
    };

    this.exists = function() {
        return fs.existsSync(_this.npm_debug);
    };

    var _backup = function(file) {
        var npmlog_backup = file + '.backup';
        try {
            if (fs.existsSync(file))
                fs.renameSync(file, npmlog_backup);
        } catch (ex) {
        }
    };

    var _restore = function(file) {
        var npmlog_backup = file + '.backup';
        try {
            if (fs.existsSync(npmlog_backup))
                fs.renameSync(npmlog_backup, file);
        } catch (ex) {
        }
    };

    this.backup = function() {
        _backup(_this.npm_debug);
        _backup(_this.npm_out);
    };

    this.restore = function() {
        _restore(_this.npm_debug);
        _restore(_this.npm_out);
    };

    if (remove)
        this.delete();
};