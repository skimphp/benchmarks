#!/bin/bash
# Ensure a worker SKIM variant is installed at the version pinned in its .env file.
#
# Usage: ensure_worker_version.sh <variant_dir_relative_to_app_root> [env_file]
# Example: ensure_worker_version.sh worker_bench/skim_worker
set -euo pipefail

normalize_version() {
    printf '%s' "$1" | sed 's/^v//'
}

VARIANT_PATH=${1:?usage: ensure_worker_version.sh <variant_dir> [env_file]}
ENV_FILE=${2:-}
PROJECT_ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
VARIANT_DIR="$PROJECT_ROOT/$VARIANT_PATH"
VARIANT_NAME="$(basename "$VARIANT_PATH")"

if [ -z "$ENV_FILE" ]; then
    ENV_FILE="$PROJECT_ROOT/worker_bench/$VARIANT_NAME.env"
fi

SIDECAR="$VARIANT_DIR/.skim_version"
APP_VARIANT_PATH="/app/$VARIANT_PATH"
COMPOSE_FILE="$PROJECT_ROOT/worker_bench/docker-compose.worker.yml"
SOURCE_DIR="$PROJECT_ROOT/worker_bench/source/skim_framework_src"
APP_SOURCE_DIR="/app/worker_bench/source/skim_framework_src"

if [ ! -d "$VARIANT_DIR" ]; then
    echo "FATAL: variant directory $VARIANT_DIR does not exist" >&2
    exit 1
fi

if [ ! -f "$ENV_FILE" ]; then
    echo "FATAL: env file $ENV_FILE not found (expected SKIM_VERSION=... in it)" >&2
    exit 1
fi

load_env_file() {
    while IFS='=' read -r key value || [ -n "$key" ]; do
        key="$(printf '%s' "$key" | xargs)"
        [ -z "$key" ] && continue
        [[ "$key" == \#* ]] && continue
        value="${value%\"}"
        value="${value#\"}"
        value="${value%\'}"
        value="${value#\'}"
        export "$key=$value"
    done < "$ENV_FILE"
}

load_env_file

SKIM_VERSION=${SKIM_VERSION:-}
SKIM_REPO_URL=${SKIM_REPO_URL:-https://github.com/skimphp/framework.git}
SKIM_BRANCH=${SKIM_BRANCH:-worker_mode}
SKIM_COMMIT=${SKIM_COMMIT:-}
FRANKENPHP_VERSION=${FRANKENPHP_VERSION:-php8.5}
export SKIM_VERSION SKIM_REPO_URL SKIM_BRANCH SKIM_COMMIT FRANKENPHP_VERSION

if [ -z "$SKIM_VERSION" ]; then
    echo "FATAL: SKIM_VERSION missing or empty in $ENV_FILE" >&2
    exit 1
fi

if [ -z "$SKIM_COMMIT" ]; then
    echo "FATAL: SKIM_COMMIT missing or empty in $ENV_FILE" >&2
    exit 1
fi

mkdir -p "$SOURCE_DIR"

if [ ! -d "$SOURCE_DIR/.git" ]; then
    printf "  \033[33m↓\033[0m %-14s cloning %s#%s\n" "$VARIANT_NAME" "$SKIM_BRANCH" "$SKIM_COMMIT"
    git clone --branch "$SKIM_BRANCH" "$SKIM_REPO_URL" "$SOURCE_DIR"
else
    git -C "$SOURCE_DIR" fetch origin "$SKIM_BRANCH"
fi

git -C "$SOURCE_DIR" fetch origin "$SKIM_COMMIT"
git -C "$SOURCE_DIR" checkout "$SKIM_COMMIT"

read_installed_version() {
    docker compose -f "$COMPOSE_FILE" --env-file "$ENV_FILE" run --rm -T --no-deps \
        -w "$APP_VARIANT_PATH" \
        bench bash -lc '
            root=/app/'"$VARIANT_PATH"'
            f=$root/vendor/composer/installed.json
            if [ -f "$f" ]; then
                v=$(python3 -c "
import json, sys
d = json.load(open(sys.argv[1]))
for p in d.get(\"packages\", []):
    if p.get(\"name\") == \"skim/framework\":
        v = p.get(\"version\", \"\")
        if v: print(v); sys.exit(0)
sys.exit(1)
" "$f" 2>/dev/null || true)
                if [ -n "$v" ]; then echo "$v"; exit 0; fi
            fi
            f=$root/vendor/skim/framework/composer.json
            if [ -f "$f" ]; then
                v=$(python3 -c "import json,sys; d=json.load(open(sys.argv[1])); print(d.get(\"version\", \"\"))" "$f" 2>/dev/null || true)
                if [ -n "$v" ]; then echo "$v"; exit 0; fi
            fi
            echo NONE
        ' 2>/dev/null | tr -d '[:space:]'
}

INSTALLED=$(read_installed_version)
if [ -z "$INSTALLED" ]; then
    INSTALLED=NONE
fi

if [ -f "$SIDECAR" ] && [ "$(tr -d '[:space:]' < "$SIDECAR")" = "$SKIM_VERSION" ]; then
    printf "  \033[32m✓\033[0m %-14s %s (matches %s)\n" "$VARIANT_NAME" "$SKIM_VERSION" "$(basename "$ENV_FILE")"
    exit 0
fi

printf "  \033[33m⟳\033[0m %-14s have=%s need=%s — running composer require...\n" \
    "$VARIANT_NAME" "$INSTALLED" "$SKIM_VERSION"

AUTH_PRELUDE='
    composer config -g github-protocols https
'

if [ -n "${GITHUB_TOKEN:-}" ]; then
    AUTH_PRELUDE='
        mkdir -p /root/.composer
        cat > /root/.composer/auth.json <<JSON
{
    "github-oauth": {
        "github.com": "'"$GITHUB_TOKEN"'"
    }
}
JSON
        composer config -g github-protocols https
    '
fi

docker compose -f "$COMPOSE_FILE" --env-file "$ENV_FILE" run --rm -T --no-deps \
    -w "$APP_VARIANT_PATH" \
    bench bash -lc "
        ${AUTH_PRELUDE}
        composer config repositories.skim-framework path '$APP_SOURCE_DIR'
        composer require 'skim/framework:*' \
            --no-interaction \
            --with-all-dependencies \
            2>&1
    " || {
        echo "FATAL: composer require failed for $VARIANT_NAME@$SKIM_VERSION" >&2
        exit 1
    }

echo "$SKIM_VERSION" > "$SIDECAR"
printf "  \033[32m✓\033[0m %-14s now running %s at %s (vendor reported: %s)\n" \
    "$VARIANT_NAME" "$SKIM_VERSION" "$SKIM_COMMIT" "$INSTALLED"
