#!/bin/bash
# IoT Platform stack bootstrap for this pod.
# Reinstalls PHP 8.4 (lost on pod restart) and starts Laravel on :8001.
set -e

if ! command -v php8.4 >/dev/null 2>&1; then
  curl -sSLo /usr/share/keyrings/deb.sury.org-php.gpg https://packages.sury.org/php/apt.gpg || true
  echo "deb [signed-by=/usr/share/keyrings/deb.sury.org-php.gpg] https://packages.sury.org/php/ bookworm main" > /etc/apt/sources.list.d/php.list
  apt-get update -qq
  DEBIAN_FRONTEND=noninteractive apt-get install -y -qq php8.4-cli php8.4-mbstring php8.4-xml php8.4-curl php8.4-sqlite3 php8.4-zip php8.4-bcmath php8.4-intl php8.4-gd
fi

if ! ss -tln | grep -q ':8001 '; then
  cd /app/backend
  nohup php8.4 artisan serve --host=0.0.0.0 --port=8001 >> /var/log/laravel.log 2>&1 &
fi
echo "Laravel: $(curl -s -o /dev/null -w '%{http_code}' http://127.0.0.1:8001/api/auth/me -H 'Accept: application/json')"
