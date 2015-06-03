#!/bin/bash
rm JXcore_Plesk.zip
cd JS
jx compile service.jxp
jx package spawner.js -add folderWatch.js,nginxconf.js,root_functions.js,node_modules -author "Nubisa Inc." -description "JXcore Plesk Spawner" -company "Nubisa Inc." -website "http://jxcore.com" -library false -fs_reach_sources false
mv spawner.jx ../JXcore_Plesk/var/
#jx compile spawner.jxp
cd ../JXcore_Plesk
zip -r JXcore_Plesk.zip *
mv JXcore_Plesk.zip ../
cd ..
