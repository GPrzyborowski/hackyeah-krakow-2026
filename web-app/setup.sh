#!/usr/bin/env bash
# One-time project setup. Only Docker is required on the host machine.
# Usage: ./setup.sh
set -euo pipefail

cd "$(dirname "$0")"

case "$(uname -s)" in
    MINGW*|MSYS*|CYGWIN*)
        echo "On Windows, run this script inside WSL 2 (e.g. Ubuntu), not Git Bash. See README.md." >&2
        exit 1 ;;
esac

if ! docker info > /dev/null 2>&1; then
    echo "Docker is not running. Start Docker Desktop and try again." >&2
    exit 1
fi

if [ ! -f .env ]; then
    echo "Creating .env from .env.example..."
    cp .env.example .env
fi

echo "Installing Composer dependencies (inside Docker)..."
docker run --rm \
    -u "$(id -u):$(id -g)" \
    -v "$(pwd):/var/www/html" \
    -w /var/www/html \
    -e COMPOSER_HOME=/tmp/composer \
    laravelsail/php84-composer:latest \
    bash -c "composer install --ignore-platform-reqs --no-interaction && \
             (grep -qE '^APP_KEY=.+' .env || php artisan key:generate --ansi)"

SAIL=./vendor/bin/sail

echo "Building and starting containers..."
$SAIL up -d --build

echo "Waiting for MySQL to be ready..."
DB_PASSWORD=$(grep -E '^DB_PASSWORD=' .env | cut -d '=' -f 2-)
until $SAIL exec -T mysql mysqladmin ping -h 127.0.0.1 -p"$DB_PASSWORD" --silent > /dev/null 2>&1; do
    sleep 2
done

$SAIL artisan migrate --seed
$SAIL npm install
$SAIL npm run build:ssr

echo
echo "Done! App:     http://localhost"
echo "      Mailpit: http://localhost:8025"
echo "Run '$SAIL npm run dev' to start Vite (hot reload + SSR)."
