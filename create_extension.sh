#!/bin/bash
rm JXcore_Plesk.zip
cd JS
jx compile service.jxp
jx compile spawner.jxp
cd ../JXcore_Plesk
zip -r JXcore_Plesk.zip *
mv JXcore_Plesk.zip ../
cd ..
