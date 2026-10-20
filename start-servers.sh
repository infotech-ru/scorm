#!/bin/bash
set -e

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
RUN_DIR="$ROOT/.run"

if [ -f "$ROOT/.env" ]; then
    set -a
    . "$ROOT/.env"
    set +a
fi

MAIN_APP_HOST="${MAIN_APP_HOST:-app.local}"
MAIN_APP_PORT="${MAIN_APP_PORT:-8090}"
SCORM_PLAYER_HOST="${SCORM_PLAYER_HOST:-scorm.local}"
SCORM_PLAYER_PORT="${SCORM_PLAYER_PORT:-8091}"
SCORM_CONTENT_HOST="${SCORM_CONTENT_HOST:-scorm.local}"
SCORM_CONTENT_PORT="${SCORM_CONTENT_PORT:-8092}"
export MAIN_APP_URL="http://$MAIN_APP_HOST:$MAIN_APP_PORT"
export SCORM_PLAYER_URL="http://$SCORM_PLAYER_HOST:$SCORM_PLAYER_PORT"
export SCORM_CONTENT_URL="http://$SCORM_CONTENT_HOST:$SCORM_CONTENT_PORT"

if ! command -v php > /dev/null; then
    echo "php not found in PATH" >&2
    exit 1
fi

"$ROOT/stop-servers.sh" > /dev/null
mkdir -p "$RUN_DIR"

start() {
    local name="$1" docroot="$2" address="$3"
    php -S "$address" -t "$docroot" "$docroot/router.php" > "$RUN_DIR/$name.log" 2>&1 &
    echo $! > "$RUN_DIR/$name.pid"
}

start main-app "$ROOT/main-app/web" "$MAIN_APP_HOST:$MAIN_APP_PORT"
start scorm-player "$ROOT/scorm-player/web" "$SCORM_PLAYER_HOST:$SCORM_PLAYER_PORT"
start scorm-content "$ROOT/scorm-player/content" "$SCORM_CONTENT_HOST:$SCORM_CONTENT_PORT"

echo "Main app:     $MAIN_APP_URL"
echo "SCORM player: $SCORM_PLAYER_URL"
echo "Content:      $SCORM_CONTENT_URL"
echo "Logs:         $RUN_DIR"
