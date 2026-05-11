#!/usr/bin/env sh
set -eu

cd /var/www/html

mkdir -p config data logs qrcode

if [ ! -f config/alipay.php ] && [ -f config/alipay.example.php ]; then
  cp config/alipay.example.php config/alipay.php
fi

exec "$@"
