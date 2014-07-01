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

exports.createConfig = function(domain, node_ports, log_location){ // node_ports is an array (first http, second https)
    var config_str = "map $http_upgrade $connection_upgrade {\n"
        +"  default upgrade;\n"
        +"  '' close;\n"
        +"}\n\n";

    var sports = ["80", "443"];
    for(var i in sports){
        var sport = sports[i];
        for(var o in ifc_list){
            var ip = ifc_list[o];
            var str_config =
                "server{\n"
                    +"  listen "+ip+":"+sport+";\n"
                    +"  server_name "+domain+";\n"

                    +"  location / {\n"
                    +"    proxy_pass http://127.0.0.1:"+node_ports[i]+";\n"
                    +"    proxy_read_timeout 9999999;\n"
                    +"    proxy_http_version 1.1;\n"
                    +"    proxy_set_header Upgrade $http_upgrade;\n"
                    +"    proxy_set_header Connection \"Upgrade\";\n"
                    +"  }\n"
                    +"  location /jxcore_logs {\n"
                    +"    autoindex on;\n"
                    +"    alias "+log_location+";\n"
                    +"  }\n"
                    +"}\n";

            config_str += str_config + "\n\n";
        }
    }

    return config_str;
};
