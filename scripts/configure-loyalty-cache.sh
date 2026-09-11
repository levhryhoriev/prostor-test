#!/usr/bin/env bash
set -euo pipefail

project_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
compose=(/usr/bin/docker compose --env-file "${project_root}/.env" -f "${project_root}/compose.yaml")

"${compose[@]}" exec -T app php -r '
require "app/bootstrap.php";
$bootstrap = \Magento\Framework\App\Bootstrap::create(BP, $_SERVER);
$writer = $bootstrap->getObjectManager()->get(\Magento\Framework\App\DeploymentConfig\Writer::class);
$writer->saveConfig([
    \Magento\Framework\Config\File\ConfigFilePool::APP_ENV => [
        "cache" => [
            "frontend" => [
                "prostor_loyalty" => [
                    "backend" => "redis",
                    "backend_options" => [
                        "server" => "redis-loyalty",
                        "port" => "6379",
                        "database" => "0"
                    ]
                ]
            ],
            "type" => [
                "prostor_loyalty" => ["frontend" => "prostor_loyalty"]
            ]
        ],
        "cache_types" => ["prostor_loyalty" => 1]
    ]
]);
'

printf '%s\n' 'Loyalty cache mapping reconciled.'
