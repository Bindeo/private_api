#!/usr/bin/env bash
cd /var/www/html/bindeo/private_api
composer install
rm -rf var/cache/*