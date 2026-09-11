#!/usr/bin/env bash
set -euo pipefail

project_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
env_file="${project_root}/.env"
compose=(/usr/bin/docker compose --env-file "${env_file}" -f "${project_root}/compose.yaml")

if [[ ! -f "${env_file}" ]]; then
    printf '%s\n' "${env_file} does not exist. Run ./scripts/bootstrap-env.sh first." >&2
    exit 1
fi

"${compose[@]}" exec -T app bin/magento prostor:cumdiscount:seed-demo
