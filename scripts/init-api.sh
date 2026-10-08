#!/usr/bin/env bash
# Bootstraps apps/api as a Laravel project. Requires PHP >= 8.2 and Composer.
# Usage: ./scripts/init-api.sh
set -euo pipefail

command -v php >/dev/null 2>&1 || { echo "PHP is not installed. Install PHP >= 8.2 first (apt install php8.2-cli php8.2-mysql php8.2-mbstring php8.2-xml php8.2-curl php8.2-zip)."; exit 1; }
command -v composer >/dev/null 2>&1 || { echo "Composer is not installed. See https://getcomposer.org/download/"; exit 1; }

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

composer create-project laravel/laravel "$ROOT_DIR/apps/api"

cd "$ROOT_DIR/apps/api"
composer require laravel/sanctum barryvdh/laravel-dompdf maatwebsite/excel
composer require --dev larastan/larastan

php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"

echo "apps/api scaffolded. Next: create config/convene.php and the M1-milestone migrations"
echo "(users, roles, permissions, role_permissions, grants, geography_nodes, location_sets,"
echo "location_set_members, user_locations, project_locations)."
