#!/usr/bin/env bash
set -euo pipefail

project_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
env_file="${project_root}/.env"

if [[ -e "${env_file}" ]]; then
    printf '%s\n' "${env_file} already exists; refusing to overwrite it." >&2
    exit 1
fi

random_value() {
    openssl rand -hex 24
}

umask 077
sed \
    -e "s/replace-with-a-local-password/$(random_value)/" \
    -e "s/replace-with-a-local-root-password/$(random_value)/" \
    -e "s/replace-with-a-local-admin-password/$(random_value)Aa1!/" \
    "${project_root}/.env.example" > "${env_file}"

printf '%s\n' "Created ${env_file}."

