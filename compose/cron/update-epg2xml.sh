#!/bin/sh
timestamp=`date -Iseconds`

echo "${timestamp}: xmltv.xml update has been started."

cd /app
python2 epg2xml.py -i ${IPTV_SYSTEM} -o /httpd/xmltv.xml

echo "${timestamp}: xmltv.xml update has been done."