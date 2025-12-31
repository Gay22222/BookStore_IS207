#!/usr/bin/env bash

set -e

PORT="${PORT:-8080}"

# 1) Koyeb inject $PORT -> đổi cổng Apache lúc runtime
sed -ri "s/^Listen .*/Listen ${PORT}/" /etc/apache2/ports.conf

# 2) Đồng bộ VirtualHost theo PORT (tránh vhost vẫn *:80)
sed -ri "s/<VirtualHost \*:[0-9]+>/<VirtualHost *:${PORT}>/g" /etc/apache2/sites-available/000-default.conf

# 3) Fix 403: Apache không expand ${APACHE_DOCUMENT_ROOT} trong conf như bạn mong muốn
#    => thay trực tiếp thành path thật
sed -ri "s#\\$\\{APACHE_DOCUMENT_ROOT\\}#/var/www/html/public#g" /etc/apache2/sites-available/000-default.conf

# Xóa cache cũ (an toàn khi biến môi trường thay đổi)
php artisan config:clear || true
php artisan route:clear  || true

exec "$@"
