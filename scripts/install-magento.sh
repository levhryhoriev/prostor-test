#!/usr/bin/env bash
set -euo pipefail

project_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
source "${project_root}/.env"
source_root="${project_root}/src"
compose=(/usr/bin/docker compose --env-file "${project_root}/.env" -f "${project_root}/compose.yaml")

if [[ ! -f "${source_root}/composer.json" ]]; then
    if [[ -n "$(find "${source_root}" -mindepth 1 -maxdepth 1 -print -quit)" ]]; then
        printf '%s\n' "${source_root} is not an empty Magento target; refusing to overwrite it." >&2
        exit 1
    fi

    "${compose[@]}" run --rm --no-deps app composer create-project \
        --repository-url=https://repo.magento.com/ \
        --no-interaction \
        --prefer-dist \
        magento/project-community-edition=2.4.9 .
fi

if [[ ! -f "${source_root}/vendor/autoload.php" ]]; then
    "${compose[@]}" run --rm --no-deps app composer install --no-interaction --prefer-dist
fi

"${compose[@]}" up -d mysql opensearch valkey redis-loyalty loyalty-mock app nginx

cleanup_database=()

if [[ -e "${source_root}/app/etc/env.php" ]]; then
    database_status="$("${compose[@]}" exec -T app bin/magento setup:db:status 2>&1 || true)"

    if [[ "${database_status}" == *"Magento application is not installed."* ]]; then
        cleanup_database=(--cleanup-database)
    else
        printf '%s\n' "Magento is already installed or its state is unknown; refusing to reinstall it." >&2
        exit 1
    fi
fi

"${compose[@]}" exec -T app bin/magento setup:install \
    "${cleanup_database[@]}" \
    --base-url="${MAGENTO_BASE_URL}" \
    --db-host=mysql \
    --db-name="${MYSQL_DATABASE}" \
    --db-user="${MYSQL_USER}" \
    --db-password="${MYSQL_PASSWORD}" \
    --backend-frontname=admin \
    --admin-firstname="${MAGENTO_ADMIN_FIRSTNAME}" \
    --admin-lastname="${MAGENTO_ADMIN_LASTNAME}" \
    --admin-email="${MAGENTO_ADMIN_EMAIL}" \
    --admin-user="${MAGENTO_ADMIN_USER}" \
    --admin-password="${MAGENTO_ADMIN_PASSWORD}" \
    --language=en_US \
    --currency=UAH \
    --timezone=Europe/Kyiv \
    --use-rewrites=1 \
    --search-engine=opensearch \
    --opensearch-host=opensearch \
    --opensearch-port=9200 \
    --opensearch-index-prefix=prostor \
    --opensearch-timeout=15 \
    --session-save=redis \
    --session-save-redis-host=valkey \
    --session-save-redis-port=6379 \
    --cache-backend=valkey \
    --cache-backend-valkey-server=valkey \
    --cache-backend-valkey-port=6379 \
    --page-cache=valkey \
    --page-cache-valkey-server=valkey \
    --page-cache-valkey-port=6379

"${project_root}/scripts/configure-loyalty-cache.sh"

"${compose[@]}" exec -T app bin/magento deploy:mode:set developer
"${compose[@]}" exec -T app bin/magento cache:flush
