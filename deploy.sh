#!/usr/bin/env bash
# Apriori Studio — deploy script (runs on the production VPS).
#
# Usage:
#   sudo -u www-data ./deploy.sh
#
# Requires: git, node >=22, npm, nginx running, .env in place at /etc/aprioristudio/.env

set -euo pipefail

PROJECT_DIR="${PROJECT_DIR:-/var/www/aprioristudio.com}"
ENV_FILE="${ENV_FILE:-/etc/aprioristudio/.env}"

cd "$PROJECT_DIR"

echo "==> git pull"
git pull --ff-only

echo "==> npm ci"
npm ci --no-audit --no-fund

echo "==> npm run build"
npm run build

echo "==> verify env file present"
if [ ! -r "$ENV_FILE" ]; then
  echo "WARNING: $ENV_FILE not readable — contact form will fail" >&2
fi

echo "==> reload nginx"
sudo systemctl reload nginx

echo "==> done"
