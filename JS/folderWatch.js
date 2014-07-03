//Copyright Nubisa Inc. 2014 All Rights Reserved

var fs = require('fs');
var path = require('path');

var watch_list = exports.watch_list = {};
var change_list = {};
var listeners = {};

var fireAll = function(){
    if(!listeners['change'])
        return;
    for(var o in change_list){
        var arr = listeners['change'];
        for(var i in arr)
            arr[i](o, change_list[o]);
    }
    change_list = {};
};

var watch_me = function(loc){
    this.location = loc;

    this.dirs = [];

    var _this = this;
    this.onchange = function(ev, filename){
        if(filename != null && filename != undefined){
            if(change_list[_this.location] == undefined){
                change_list[_this.location] = filename;
                setTimeout(fireAll, 500);
            }
        }
    };
};

exports.on = function(name, cb){
    if(!listeners[name]){
        listeners[name] = [];
    }
    listeners[name].push(cb);
};

exports.watch = function(location){
    if(watch_list[location]){
        exports.unwatch(location);
    }
    watch_list[location] = new watch_me(location);

    fs.watch(location, {persistent:true}, watch_list[location].onchange);
    var dirs = fs.readdirSync(location);
    for(var o in dirs){
        var sub = location + path.sep + dirs[o];
        if(fs.statSync(sub).isDirectory()){
            watch_list[location].dirs.push(sub);
            watch_list[sub] = new watch_me(sub);
            fs.watch(sub, {persistent:true}, watch_list[sub].onchange);
        }
    }
};

exports.unwatch = function(location){
    if(watch_list[location]){
        if(watch_list[location].dirs && watch_list[location].dirs.length){
            for(var o in watch_list[location].dirs){
                var sub = watch_list[location].dirs[o];
                if(watch_list[sub]){
                    if(watch_list[sub].dirs && watch_list[sub].dirs.length){
                        exports.unwatch(sub);
                        continue;
                    }
                }

                fs.unwatchFile(sub);
                watch_list[sub].dirs = null;
                watch_list[sub] = null;
                delete(watch_list[sub]);
            }
            watch_list[location].dirs = null;
        }
        watch_list[location] = null;
        delete(watch_list[location]);
        fs.unwatchFile(location);
    }
};
