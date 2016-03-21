#!/usr/bin/env bash
cd /var/www/html/bindeo/api
composer install
rm -rf var/cache/*