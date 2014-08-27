/**
 * Created by nubisa_krzs on 8/27/14.
 */


var nc = require("./nginxconf")

var conf = nc.createConfig("jxcore.com", [10008, 10009], "", "osiem", {key : "key", crt : "crt"});

console.log(conf);