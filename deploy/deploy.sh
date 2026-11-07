#!/bin/bash
set -e
DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$DIR"

echo "[$(date -Iseconds)] Pulling latest demo files..."
git pull origin main

MOODLE_DIR="/home/codelab/public_html/moodle"
cp demo.php "$MOODLE_DIR/"
cp codelab-demo.mbz "$MOODLE_DIR/"
cp codelab-demo-sr.mbz "$MOODLE_DIR/"

echo "[$(date -Iseconds)] Purging Moodle caches..."
/usr/bin/php "$MOODLE_DIR/admin/cli/purge_caches.php" || true

echo "[$(date -Iseconds)] Running demo.php reset..."
/usr/bin/php "$MOODLE_DIR/demo.php" reset
echo "[$(date -Iseconds)] CodeLab demo deploy finished successfully!"
