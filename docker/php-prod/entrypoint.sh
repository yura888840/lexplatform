#!/bin/sh
set -e

# JWT-ключі: генеруємо при першому старті, якщо не змонтовані секретом
if [ ! -f config/jwt/private.pem ]; then
  php bin/console lexik:jwt:generate-keypair --skip-if-exists || true
fi

# Очищаємо і прогріваємо кеш контейнера, щоб зміни конфігу гарантовано підхопились
php bin/console cache:clear --env=prod --no-warmup || true
php bin/console cache:warmup --env=prod || true

exec "$@"
