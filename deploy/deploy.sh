#!/bin/bash
set -e
DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$DIR"

if [ "$1" != "--no-pull" ]; then
    echo "[$(date -Iseconds)] Pulling latest demo files from GitHub..."
    git fetch origin main
    git reset --hard FETCH_HEAD
    git clean -fd
    exec /bin/bash "$0" --no-pull "$@"
fi

# Locate Moodle directory by searching for config.php
TARGET_DIR=""
for cand in \
    "$MOODLE_DIR" \
    "$HOME/public_html/moodle" \
    "$HOME/public_html" \
    "/home/codelab/public_html/moodle" \
    "/home/algoritam/public_html/moodle" \
    "/home/aisim/public_html/moodle" \
    "/home/codelab/public_html" \
    "/home/algoritam/public_html" \
    "/home/aisim/public_html" \
    "/var/www/moodle" \
    "/var/www/html"; do
    if [ -n "$cand" ] && [ -f "$cand/config.php" ]; then
        TARGET_DIR="$cand"
        break
    fi
done

if [ -z "$TARGET_DIR" ]; then
    echo "ERROR: Could not locate Moodle config.php!"
    exit 1
fi

# If Moodle has a /public directory (Moodle 5.0+ public dirroot), plugins and webroot are in /public
PARENT_DIR="$TARGET_DIR"
if [ -d "${TARGET_DIR}/public" ]; then
    TARGET_DIR="${TARGET_DIR}/public"
fi

LOG_FILE="${TARGET_DIR}/deploy_log.txt"
exec > >(tee "$LOG_FILE") 2>&1
echo "=== Deploy started at $(date -Iseconds) ==="
echo "[$(date -Iseconds)] Active Moodle DIRROOT: ${TARGET_DIR}"

echo "[$(date -Iseconds)] Deploying demo files..."
cp demo.php "${TARGET_DIR}/"
cp demo.php "${PARENT_DIR}/" 2>/dev/null || true
cp -f *.mbz "${TARGET_DIR}/" 2>/dev/null || true
cp -f *.mbz "${PARENT_DIR}/" 2>/dev/null || true
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
            git fetch --depth 1 origin || git fetch origin || true
            git reset --hard FETCH_HEAD || true
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
        echo "[$(date -Iseconds)] Cloning new plugin ${plugin_name} from ${repo_url}..."
        git clone --depth 1 "$repo_url" "$dest_dir" || true
    fi
    if [ -f "${dest_dir}/version.php" ]; then
        chmod -R a+rX "$dest_dir" 2>/dev/null || true
        echo "[$(date -Iseconds)] Plugin ${plugin_name} verified OK (version.php exists)"
    else
        echo "[$(date -Iseconds)] ERROR: Plugin ${plugin_name} missing version.php at ${dest_dir}!"
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

CLI_DIR="${TARGET_DIR}/admin/cli"
if [ ! -f "${CLI_DIR}/upgrade.php" ] && [ -f "${PARENT_DIR}/admin/cli/upgrade.php" ]; then
    CLI_DIR="${PARENT_DIR}/admin/cli"
fi

echo "[$(date -Iseconds)] Running Moodle database and plugin upgrades using ${CLI_DIR}..."
/usr/bin/php -d max_input_vars=10000 "${CLI_DIR}/upgrade.php" --non-interactive || true

echo "[$(date -Iseconds)] Purging Moodle caches..."
/usr/bin/php "${CLI_DIR}/purge_caches.php" || true

echo "[$(date -Iseconds)] Running demo.php reset..."
if [ -f "${PARENT_DIR}/demo.php" ]; then
    /usr/bin/php -d max_input_vars=10000 "${PARENT_DIR}/demo.php" reset
else
    /usr/bin/php -d max_input_vars=10000 "${TARGET_DIR}/demo.php" reset
fi

echo "[$(date -Iseconds)] Demo deploy finished successfully!"
