/**
 * Created by nubisa_krzs on 6/25/14.
 */

var watch = require('./folderWatch.js');

watch.on("change", function(dir, file){
console.log("change", dir, file);
});


var fs = require('fs');
watch.watch("/Users/Gonzo/Desktop");
fs.writeFileSync("/Users/Gonzo/out1.txt", JSON.stringify(watch.watch_list));

watch.unwatch("/Users/Gonzo/Desktop");
console.log("lst", watch.watch_list);

watch.watch("/Users/Gonzo/Desktop");
fs.writeFileSync("/Users/Gonzo/out2.txt", JSON.stringify(watch.watch_list));


