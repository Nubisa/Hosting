//Copyright Nubisa Inc. 2014 All Rights Reserved

var os = require('os');
var ifcs = os.networkInterfaces();

var ifc_list = [];

exports.resetInterfaces = function(){
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
};

exports.resetInterfaces();

//ssl_info {key: "", crt: ""}  OR NULL
exports.createConfig = function(domain, node_ports, log_location, directives, ssl_info){ // node_ports is an array (first http, second https)
    var config_str = "map $http_upgrade $connection_upgrade {\n"
        +"  default upgrade;\n"
        +"  '' close;\n"
        +"}\n\n";

    if (directives) {
        directives = directives.trim() + ";";
        directives = '  #plan`s directives\n  '
            + directives.replace(/\n/g, '').replace(/;;/g, ";").replace(/;/g, ';\n    ').trim()
            + "\n";
    } else {
        directives = "";
    }

    var sports = ["80"];
    if(ssl_info)
        sports.push("443");

    config_str +=
        'upstream jxcore_target{\n'
       +'  server 127.0.0.1:'+ node_ports[0] +';\n'
      // when this was present, changes in domain's config and reloading nginx did not have immediate effect
      // since old connection was still kept
      // +'  keepalive 9999999;\n'
       +'}\n\n';

    for(var i in sports){
        var sport = sports[i];
        for(var o in ifc_list){
            var ip = ifc_list[o];
            var str_config =
                "server{\n"
                    +"  listen "+ip+":"+sport+ (sport=='443'?' ssl;':';') + '\n'
                    +"  server_name "+domain+";\n"
                    +(sport=='443'?"  ssl on;\n":'')
                    +(sport=='443'?"  ssl_certificate_key " + ssl_info.key + ";\n" : "")
                    +(sport=='443'?"  ssl_certificate " + ssl_info.crt + ";\n" : "")
                    +"  location / {\n"
                    +"    proxy_pass http://jxcore_target;\n"
                    +"    proxy_read_timeout 9999999;\n"
                    +"    proxy_http_version 1.1;\n"
                    +"    proxy_set_header Upgrade $http_upgrade;\n"
                    +"    proxy_set_header Connection \"Upgrade\";\n"
                    +"  }\n"
                    +"  location /jxcore_logs {\n"
                    +"    autoindex on;\n"
                    +"    alias "+log_location+";\n"
                    +"    add_header Content-type text/plain;\n"
                    +"  }\n"
                    +directives
                    +"}\n";

            config_str += str_config + "\n\n";
        }
    }

    return config_str;
};
