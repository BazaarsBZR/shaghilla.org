#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
OUT_DIR="${ROOT_DIR}/cpanel_upload"
APP_DIR="${OUT_DIR}/app"
PUBLIC_DIR="${OUT_DIR}/public_html"
SECRETS_FILE="${ROOT_DIR}/.cpanel-secrets.local"
BUILD_ID="$(date +%Y%m%d_%H%M%S)"

if [[ "${SKIP_ASSET_BUILD:-0}" != "1" && -f "${ROOT_DIR}/package.json" ]]; then
  if [[ ! -d "${ROOT_DIR}/node_modules" ]]; then
    npm ci --silent
  fi

  npm run build --silent
fi

rm -rf "${OUT_DIR}"
mkdir -p "${APP_DIR}" "${PUBLIC_DIR}"

# Copy Laravel app (includes vendor/) without local-only folders.
rsync -a \
  --exclude=".git/" \
  --exclude="node_modules/" \
  --exclude="cpanel_upload/" \
  --exclude="shaghilla_cpanel_upload_*.zip" \
  --exclude=".cpanel-secrets.local" \
  --exclude="database.txt" \
  --exclude="logo-fav.png" \
  --exclude="website-logo.png" \
  --exclude="public/uploads/news/" \
  --exclude="public/uploads/videos/" \
  --exclude="tests/" \
  --exclude=".env" \
  --exclude="laravel.log" \
  --exclude=".phpunit.result.cache" \
  --exclude="storage/logs/*.log" \
  --exclude="storage/framework/cache/*" \
  --exclude="storage/framework/views/*" \
  --exclude="storage/framework/testing/*" \
  "${ROOT_DIR}/" "${APP_DIR}/"

# Stamp the build so public_html/index.php can reliably pick the newest deployed app folder.
echo "${BUILD_ID}" > "${APP_DIR}/.shaghilla-build-id"

# Copy webroot files.
rsync -a \
  --exclude="uploads/news/" \
  --exclude="uploads/videos/" \
  "${ROOT_DIR}/public/" "${PUBLIC_DIR}/"

# Public deploy marker (lets us verify what public_html is actually being served).
cat > "${PUBLIC_DIR}/_deploy.txt" <<EOF
build_id=${BUILD_ID}
generated_at=$(date -u +"%Y-%m-%dT%H:%M:%SZ")
EOF

# Generate a stable, private .env for no-SSH deployments.
# - Secrets are persisted locally in .cpanel-secrets.local (gitignored) so future builds keep the same APP_KEY.
if [[ ! -f "${SECRETS_FILE}" ]]; then
  APP_KEY="$(php -r "echo 'base64:'.base64_encode(random_bytes(32));")"
  RSS_IMPORT_SECRET="$(php -r "echo bin2hex(random_bytes(16));")"

  cat > "${SECRETS_FILE}" <<EOF
APP_KEY=${APP_KEY}
RSS_IMPORT_SECRET=${RSS_IMPORT_SECRET}
EOF
fi

# Load secrets without "source" (avoid executing anything).
APP_KEY="$(grep -E '^APP_KEY=' "${SECRETS_FILE}" | head -n1 | cut -d= -f2-)"
RSS_IMPORT_SECRET="$(grep -E '^RSS_IMPORT_SECRET=' "${SECRETS_FILE}" | head -n1 | cut -d= -f2-)"

DB_NAME="$(grep -E '^Database:' "${ROOT_DIR}/database.txt" 2>/dev/null | head -n1 | sed -E 's/^Database:[[:space:]]*//')"
DB_USER="$(grep -E '^User:' "${ROOT_DIR}/database.txt" 2>/dev/null | head -n1 | sed -E 's/^User:[[:space:]]*//')"
DB_PASS="$(grep -E '^Pass:' "${ROOT_DIR}/database.txt" 2>/dev/null | head -n1 | sed -E 's/^Pass:[[:space:]]*//')"

DB_NAME="${DB_NAME:-}"
DB_USER="${DB_USER:-}"
DB_PASS="${DB_PASS:-}"

cat > "${APP_DIR}/.env" <<EOF
APP_NAME="رابطة الشغيلة"
APP_ENV=production
APP_KEY=${APP_KEY}
APP_DEBUG=false
APP_TIMEZONE=Asia/Beirut
APP_URL=https://shaghilla.org

APP_LOCALE=ar
APP_FALLBACK_LOCALE=en
APP_FAKER_LOCALE=ar_SA

APP_MAINTENANCE_DRIVER=file

LOG_CHANNEL=stack
LOG_STACK=single
LOG_LEVEL=info

DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=${DB_NAME}
DB_USERNAME=${DB_USER}
DB_PASSWORD=${DB_PASS}

SESSION_DRIVER=file
SESSION_LIFETIME=120
SESSION_ENCRYPT=false
SESSION_PATH=/
SESSION_DOMAIN=null

BROADCAST_CONNECTION=log
FILESYSTEM_DISK=local
QUEUE_CONNECTION=sync

CACHE_STORE=file
CACHE_PREFIX=

MAIL_MAILER=log
MAIL_FROM_ADDRESS="hello@example.com"
MAIL_FROM_NAME="\${APP_NAME}"

RSS_IMPORT_SECRET=${RSS_IMPORT_SECRET}

ADMIN_EMAIL=admin@shaghilla.org
ADMIN_PASSWORD=
EOF

# Replace index.php for cPanel sibling-folder setup:
# - Works when webroot is public_html/ OR public_html/<addon-domain>/
# - Auto-detects the private app folder outside public_html.
cat > "${PUBLIC_DIR}/index.php" <<'PHP'
<?php

use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// cPanel sibling-folder setup (no SSH):
// - Your webroot is public_html (or a subfolder inside it).
// - Your private Laravel app folder lives *outside* public_html (sibling).
//
// Optional override:
// - Create a text file: public_html/.app-root
// - Put a path inside it (absolute or relative to this index.php), e.g.:
//   ../my-private-app

$appRoot = null;
$indexBuild = 'BUILD_ID_PLACEHOLDER';

/**
 * @param  list<string>  $candidates
 */
function pickNewestStampedApp(array $candidates): ?string
{
    $valid = [];

    foreach ($candidates as $candidate) {
        if (! is_dir($candidate)) {
            continue;
        }

        if (! is_file($candidate . '/vendor/autoload.php') || ! is_file($candidate . '/bootstrap/app.php')) {
            continue;
        }

        $stampFile = $candidate . '/.shaghilla-build-id';
        $stamp = is_file($stampFile) ? trim((string) file_get_contents($stampFile)) : '';

        $valid[] = [
            'path' => (realpath($candidate) ?: $candidate),
            'stamp' => $stamp,
        ];
    }

    if ($valid === []) {
        return null;
    }

    usort($valid, function (array $a, array $b): int {
        return strcmp((string) $b['stamp'], (string) $a['stamp']);
    });

    return $valid[0]['path'];
}

$overrideFile = __DIR__ . '/.app-root';
if (is_file($overrideFile)) {
    $value = trim((string) file_get_contents($overrideFile));
    if ($value !== '') {
        $candidate = $value;
        if ($candidate[0] !== '/') {
            $candidate = __DIR__ . '/' . $candidate;
        }

        if (is_file($candidate . '/vendor/autoload.php') && is_file($candidate . '/bootstrap/app.php')) {
            $appRoot = realpath($candidate) ?: $candidate;
        }
    }
}

if (! $appRoot) {
    // Fast path: common layouts
    $directCandidates = [
        __DIR__ . '/../app',     // public_html/index.php          with ../app
        __DIR__ . '/../../app',  // public_html/<site>/index.php   with ../../app
    ];

    $appRoot = pickNewestStampedApp($directCandidates);
}

if (! $appRoot) {
    // Detect the cPanel home directory by finding a parent that contains public_html.
    $possibleHomeDirs = array_values(array_filter([
        realpath(__DIR__ . '/..') ?: null,
        realpath(__DIR__ . '/../..') ?: null,
    ]));

    $homeDir = null;
    foreach ($possibleHomeDirs as $dir) {
        if (is_dir($dir . '/public_html')) {
            $homeDir = $dir;
            break;
        }
    }

    if ($homeDir) {
        // Prefer a folder literally named "app" first, then scan siblings.
        $scanCandidates = [$homeDir . '/app'];

        $dirs = glob($homeDir . '/*', GLOB_ONLYDIR) ?: [];
        foreach ($dirs as $dir) {
            if (basename($dir) === 'public_html') {
                continue;
            }
            $scanCandidates[] = $dir;
        }

        $scanCandidates = array_values(array_unique($scanCandidates));

        $appRoot = pickNewestStampedApp($scanCandidates);
    }
}

if (! $appRoot) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=UTF-8');
    echo "App bootstrap not found.\n\n"
        . "Expected a private Laravel folder outside public_html containing:\n"
        . "- vendor/autoload.php\n"
        . "- bootstrap/app.php\n\n"
        . "Fix:\n"
        . "- Ensure your private app folder is uploaded outside public_html, OR\n"
        . "- Create public_html/.app-root containing the relative/absolute path to it.\n";
    exit(1);
}

// Diagnostics (safe): helps confirm which code is serving requests.
$appStamp = 'unknown';
$stampFile = $appRoot . '/.shaghilla-build-id';
if (is_file($stampFile)) {
    $appStamp = trim((string) file_get_contents($stampFile));
}

header('X-Shaghilla-Index-Build: ' . $indexBuild);
header('X-Shaghilla-App-Build: ' . $appStamp);
header('X-Shaghilla-App-Root-Hash: ' . substr(hash('sha256', (string) $appRoot), 0, 16));

// Ensure runtime directories exist before Laravel boots.
foreach ([
    $appRoot . '/storage',
    $appRoot . '/storage/framework',
    $appRoot . '/storage/framework/cache',
    $appRoot . '/storage/framework/sessions',
    $appRoot . '/storage/framework/views',
    $appRoot . '/bootstrap/cache',
] as $dir) {
    if (! is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
}

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = $appRoot.'/storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require $appRoot.'/vendor/autoload.php';

// Bootstrap Laravel and handle the request...
$app = require_once $appRoot.'/bootstrap/app.php';
$app->usePublicPath(__DIR__);
$app->handleRequest(Request::capture());
PHP

# Inject the build id into the generated index.php (portable between BSD/GNU sed).
if sed --version >/dev/null 2>&1; then
  sed -i -e "s/BUILD_ID_PLACEHOLDER/${BUILD_ID}/g" "${PUBLIC_DIR}/index.php"
else
  sed -i '' -e "s/BUILD_ID_PLACEHOLDER/${BUILD_ID}/g" "${PUBLIC_DIR}/index.php"
fi

ZIP_NAME="shaghilla_cpanel_upload_${BUILD_ID}.zip"
(
  cd "${OUT_DIR}"
  zip -qr "../${ZIP_NAME}" "app" "public_html"
)

echo "Created:"
echo "- ${OUT_DIR}/app"
echo "- ${OUT_DIR}/public_html"
echo "- ${ROOT_DIR}/${ZIP_NAME}"
echo ""
echo "Generated:"
echo "- ${APP_DIR}/.env (private; do not upload to public_html)"
echo "- ${SECRETS_FILE} (kept locally for stable secrets across updates)"
