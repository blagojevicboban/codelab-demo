#!/bin/bash
set -e
DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$DIR"

echo "[$(date -Iseconds)] Pulling latest demo files from GitHub..."
git fetch origin main || true
git reset --hard FETCH_HEAD || git pull origin main || true
git clean -fd || true

# Locate Moodle directory
if [ -n "$MOODLE_DIR" ] && [ -d "$MOODLE_DIR" ]; then
    TARGET_DIR="$MOODLE_DIR"
elif [ -f "$HOME/public_html/moodle/config.php" ]; then
    TARGET_DIR="$HOME/public_html/moodle"
elif [ -f "/home/codelab/public_html/moodle/config.php" ]; then
    TARGET_DIR="/home/codelab/public_html/moodle"
elif [ -d "/home/codelab/public_html/moodle" ]; then
    TARGET_DIR="/home/codelab/public_html/moodle"
elif [ -f "/var/www/moodle/config.php" ]; then
    TARGET_DIR="/var/www/moodle"
else
    echo "ERROR: Could not locate Moodle directory!"
    exit 1
fi

echo "[$(date -Iseconds)] Deploying demo files to ${TARGET_DIR}..."
cp demo.php "${TARGET_DIR}/"
cp -f *.mbz "${TARGET_DIR}/" 2>/dev/null || true
if [ -d cards ]; then
    mkdir -p "${TARGET_DIR}/cards"
    cp -r cards/* "${TARGET_DIR}/cards/"
fi

# Function to clone or pull a Moodle plugin cleanly
sync_plugin() {
    local repo_url="$1"
    local dest_dir="$2"
    local plugin_name="$3"
    echo "[$(date -Iseconds)] Syncing plugin ${plugin_name} -> ${dest_dir}..."
    mkdir -p "$(dirname "$dest_dir")"
    if [ -d "$dest_dir/.git" ]; then
        (
            cd "$dest_dir"
            git fetch --depth 1 origin main || git fetch origin main || true
            git reset --hard FETCH_HEAD || git reset --hard origin/main || true
            git clean -fd || true
        ) || true
    elif [ -d "$dest_dir" ]; then
        echo "[$(date -Iseconds)] Non-git directory found at ${dest_dir}, cloning to temp..."
        local tmp_dir="${dest_dir}_tmp_sync"
        rm -rf "$tmp_dir"
        if git clone --depth 1 "$repo_url" "$tmp_dir"; then
            rm -rf "$dest_dir"
            mv "$tmp_dir" "$dest_dir"
        else
            echo "[$(date -Iseconds)] WARNING: git clone failed, keeping existing ${dest_dir}"
            rm -rf "$tmp_dir"
        fi
    else
        git clone --depth 1 "$repo_url" "$dest_dir" || true
    fi
}

echo "[$(date -Iseconds)] Updating all 3x3 plugins across ecosystems..."

# 1. Algorithm suite
sync_plugin "https://github.com/blagojevicboban/moodle-tiny_algorithm.git" "${TARGET_DIR}/lib/editor/tiny/plugins/algorithm" "tiny_algorithm"
sync_plugin "https://github.com/blagojevicboban/moodle-filter_algorithm.git" "${TARGET_DIR}/filter/algorithm" "filter_algorithm"
sync_plugin "https://github.com/blagojevicboban/moodle-qtype_algorithm.git" "${TARGET_DIR}/question/type/algorithm" "qtype_algorithm"

# 2. CodeLab suite
sync_plugin "https://github.com/blagojevicboban/moodle-tiny_codelab.git" "${TARGET_DIR}/lib/editor/tiny/plugins/codelab" "tiny_codelab"
sync_plugin "https://github.com/blagojevicboban/moodle-filter_codelab.git" "${TARGET_DIR}/filter/codelab" "filter_codelab"
sync_plugin "https://github.com/blagojevicboban/moodle-qtype_codelab.git" "${TARGET_DIR}/question/type/codelab" "qtype_codelab"

# 3. AISim suite
sync_plugin "https://github.com/blagojevicboban/moodle-tiny_aisim.git" "${TARGET_DIR}/lib/editor/tiny/plugins/aisim" "tiny_aisim"
sync_plugin "https://github.com/blagojevicboban/moodle-filter_aisim.git" "${TARGET_DIR}/filter/aisim" "filter_aisim"
sync_plugin "https://github.com/blagojevicboban/moodle-qtype_aisim.git" "${TARGET_DIR}/question/type/aisim" "qtype_aisim"

echo "[$(date -Iseconds)] Running Moodle database and plugin upgrades..."
/usr/bin/php "${TARGET_DIR}/admin/cli/upgrade.php" --non-interactive || true

echo "[$(date -Iseconds)] Purging Moodle caches..."
/usr/bin/php "${TARGET_DIR}/admin/cli/purge_caches.php" || true

echo "[$(date -Iseconds)] Running demo.php reset..."
/usr/bin/php "${TARGET_DIR}/demo.php" reset

echo "[$(date -Iseconds)] CodeLab demo deploy finished successfully!"
pm2 reload codelab-webhook >/dev/null 2>&1 || true
