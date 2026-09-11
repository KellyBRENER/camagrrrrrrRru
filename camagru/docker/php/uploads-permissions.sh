#!/bin/sh
set -eu
cd /var/www/html
mkdir -p public/uploads/photos
chown -R www-data:www-data public/uploads
find public/uploads -type d -exec chmod 755 {} +
find public/uploads -type f -exec chmod 644 {} +
