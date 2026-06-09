#!/usr/bin/env bash
#
# Phase-2 test instance on the SAME server + SAME production database,
# without cPanel. Exposes the app at: https://YOUR-DOMAIN/phase2
#
# Live app (flexana/) and live URL are not modified except for a small
# /phase2 gateway folder inside the web root.
#
# Usage (SSH):
#   cd /home/flexanastudios/domains/admin-panel-flexana-egypt/flexana
#   chmod +x scripts/setup-phase2-ssh.sh
#   ./scripts/setup-phase2-ssh.sh
#
set -euo pipefail

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

info()  { echo -e "${BLUE}→${NC} $*"; }
ok()    { echo -e "${GREEN}✓${NC} $*"; }
warn()  { echo -e "${YELLOW}⚠${NC} $*"; }
fail()  { echo -e "${RED}✗${NC} $*" >&2; exit 1; }

PHASE2_DIR_NAME="${PHASE2_DIR_NAME:-flexana-phase2}"
GATEWAY_SUBPATH="${GATEWAY_SUBPATH:-phase2}"
SOURCE_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
DOMAIN_ROOT="$(cd "${SOURCE_DIR}/.." && pwd)"
LIVE_DIR="${DOMAIN_ROOT}/flexana"
PHASE2_DIR="${DOMAIN_ROOT}/${PHASE2_DIR_NAME}"

info "Domain root:  ${DOMAIN_ROOT}"
info "Live app:     ${LIVE_DIR}"
info "Phase-2 app:  ${PHASE2_DIR}"
info "Gateway path: /${GATEWAY_SUBPATH}"
echo ""

# --- Resolve web root (where live index.php is served from) ---
resolve_web_root() {
    local public_html="${DOMAIN_ROOT}/public_html"

    if [[ -L "${public_html}" ]]; then
        local target
        target="$(readlink -f "${public_html}")"
        echo "${target}"
        return
    fi

    if [[ -d "${public_html}" && -f "${public_html}/index.php" ]]; then
        echo "${public_html}"
        return
    fi

    if [[ -f "${LIVE_DIR}/public/index.php" ]]; then
        echo "${LIVE_DIR}/public"
        return
    fi

    fail "Could not find web root. Expected public_html or flexana/public."
}

WEB_ROOT="$(resolve_web_root)"
GATEWAY_DIR="${WEB_ROOT}/${GATEWAY_SUBPATH}"
info "Web root:     ${WEB_ROOT}"
info "Gateway dir:  ${GATEWAY_DIR}"
echo ""

# --- Create or update phase-2 application ---
if [[ ! -d "${PHASE2_DIR}" ]]; then
    info "Creating ${PHASE2_DIR} from ${SOURCE_DIR}..."
    cp -a "${SOURCE_DIR}" "${PHASE2_DIR}"
    ok "Copied application"
else
    warn "${PHASE2_DIR} already exists — updating gateway and dependencies only"
fi

cd "${PHASE2_DIR}"

if [[ ! -f "${LIVE_DIR}/.env" ]]; then
    fail "Live .env not found at ${LIVE_DIR}/.env — configure live app first."
fi

# --- .env for phase-2 (same DB, isolated side effects) ---
if [[ ! -f .env ]]; then
    info "Creating .env from live copy..."
    cp "${LIVE_DIR}/.env" .env
fi

live_app_url="$(grep -E '^APP_URL=' "${LIVE_DIR}/.env" | head -1 | cut -d= -f2- | tr -d '"' | tr -d "'")"
if [[ -z "${live_app_url}" ]]; then
    live_app_url="https://admin-panel-flexana-egypt.com"
fi
phase2_app_url="${live_app_url%/}/${GATEWAY_SUBPATH}"

set_env() {
    local key="$1"
    local value="$2"
    if grep -qE "^${key}=" .env; then
        sed -i.bak "s|^${key}=.*|${key}=${value}|" .env
    else
        echo "${key}=${value}" >> .env
    fi
}

set_env "APP_NAME" "\"Flexana Phase2\""
set_env "APP_ENV" "staging"
set_env "APP_DEBUG" "true"
set_env "APP_URL" "${phase2_app_url}"
set_env "LIVEWIRE_BASE_PATH" "${GATEWAY_SUBPATH}"
set_env "CACHE_PREFIX" "flexana_phase2_cache"
set_env "MAIL_MAILER" "log"
set_env "SMS_DRIVER" "log"
set_env "SESSIONS_CLASS_REMINDER_ENABLED" "false"
rm -f .env.bak
ok ".env configured (APP_URL=${phase2_app_url})"

info "Installing PHP dependencies..."
composer install --no-dev --optimize-autoloader --no-interaction
ok "Composer install done"

if command -v npm >/dev/null 2>&1 && [[ -f package.json ]]; then
    info "Building frontend assets..."
    npm ci --no-audit --no-fund 2>/dev/null || npm install --no-audit --no-fund
    npm run build
    ok "Assets built"
else
    warn "npm not available — copy css/js from live public if admin UI looks broken"
fi

php artisan key:generate --force
chmod -R 775 storage bootstrap/cache 2>/dev/null || true
php artisan storage:link 2>/dev/null || true
php artisan optimize:clear
php artisan optimize
ok "Laravel optimized"

# --- Gateway (only /phase2 is web-accessible for the test app) ---
info "Creating gateway at ${GATEWAY_DIR}..."
mkdir -p "${GATEWAY_DIR}"

# Relative path from gateway to phase-2 app root
if command -v python3 >/dev/null 2>&1; then
    REL_APP="$(python3 -c "import os; print(os.path.relpath('${PHASE2_DIR}', '${GATEWAY_DIR}'))")"
elif command -v python >/dev/null 2>&1; then
    REL_APP="$(python -c "import os; print(os.path.relpath('${PHASE2_DIR}', '${GATEWAY_DIR}'))")"
elif [[ "${WEB_ROOT}" == *"/public_html" ]]; then
    REL_APP="../${PHASE2_DIR_NAME}"
else
    REL_APP="../../../${PHASE2_DIR_NAME}"
fi

cat > "${GATEWAY_DIR}/index.php" <<PHP
<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

\$appRoot = __DIR__ . '/${REL_APP}';

if (file_exists(\$maintenance = \$appRoot . '/storage/framework/maintenance.php')) {
    require \$maintenance;
}

require \$appRoot . '/vendor/autoload.php';

/** @var Application \$app */
\$app = require_once \$appRoot . '/bootstrap/app.php';

\$app->handleRequest(Request::capture());
PHP

cat > "${GATEWAY_DIR}/.htaccess" <<'HTACCESS'
<IfModule mod_rewrite.c>
    <IfModule mod_negotiation.c>
        Options -MultiViews -Indexes
    </IfModule>

    RewriteEngine On
    RewriteBase /phase2/

    RewriteCond %{HTTP:Authorization} .
    RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]

    RewriteCond %{HTTP:x-xsrf-token} .
    RewriteRule .* - [E=HTTP_X_XSRF_TOKEN:%{HTTP:X-XSRF-Token}]

    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_URI} (.+)/$
    RewriteRule ^ %1 [L,R=301]

    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule ^ index.php [L]
</IfModule>
HTACCESS

# Replace RewriteBase if custom subpath
if [[ "${GATEWAY_SUBPATH}" != "phase2" ]]; then
    sed -i.bak "s|RewriteBase /phase2/|RewriteBase /${GATEWAY_SUBPATH}/|" "${GATEWAY_DIR}/.htaccess"
    rm -f "${GATEWAY_DIR}/.htaccess.bak"
fi

# Sync public assets (css, js, images, etc.) into gateway — not the whole Laravel tree
for item in css js images fonts build favicon.ico robots.txt serve-storage.php; do
    if [[ -e "${PHASE2_DIR}/public/${item}" ]]; then
        rm -rf "${GATEWAY_DIR}/${item}"
        cp -a "${PHASE2_DIR}/public/${item}" "${GATEWAY_DIR}/${item}"
    fi
done

# Storage symlink inside gateway for /phase2/storage URLs
if [[ -L "${PHASE2_DIR}/public/storage" ]]; then
    rm -f "${GATEWAY_DIR}/storage"
    ln -sf "${PHASE2_DIR}/public/storage" "${GATEWAY_DIR}/storage" 2>/dev/null \
        || cp -a "${PHASE2_DIR}/public/storage" "${GATEWAY_DIR}/storage"
fi

ok "Gateway created"

# --- Cleanup common mistaken upload path ---
WRONG_NESTED="${WEB_ROOT}/public/flexana"
if [[ -d "${WRONG_NESTED}" ]]; then
    warn "Found nested copy at ${WRONG_NESTED}"
    warn "Remove it after confirming /${GATEWAY_SUBPATH} works:"
    warn "  rm -rf ${WRONG_NESTED}"
fi

echo ""
echo "=========================================="
ok "Phase-2 is ready"
echo "=========================================="
echo ""
echo "  Admin / API base: ${phase2_app_url}"
echo "  Mobile API test:  ${phase2_app_url}/api/v1/sessions"
echo ""
echo "  Live app untouched: ${LIVE_DIR}"
echo ""
echo "Safety (already set in phase-2 .env):"
echo "  - Same DB as live"
echo "  - MAIL_MAILER=log, SMS_DRIVER=log"
echo "  - SESSIONS_CLASS_REMINDER_ENABLED=false"
echo "  - Do NOT add phase-2 to cron"
echo ""
echo "Verify:"
echo "  curl -I ${phase2_app_url}"
echo "  curl -I ${phase2_app_url}/api/v1/sessions"
echo ""
