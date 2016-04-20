#!/usr/bin/env bash
cd /usr/local/share/geoIP
wget http://geolite.maxmind.com/download/geoip/database/GeoLite2-City.mmdb.gz
gzip -fd GeoLite2-City.mmdb.gz