#!/bin/bash
# Railway Pre-Deploy Command for this app's service, per docs/DEPLOYMENT.md.
# Runs once per deploy, after the build completes and before traffic is
# routed to the new instance -- safe to run on every deploy (idempotent).
set -e

php artisan optimize:clear
php artisan migrate --force

# Only seed if roles table is empty
if [ "$(php artisan tinker --execute="echo DB::table('roles')->count();")" = "0" ]; then
 echo "Seeding RoleSeeder..."
 php artisan db:seed --class=RoleSeeder --force
 echo "Seeding EmailTemplateSeeder..."
 php artisan db:seed --class=EmailTemplateSeeder --force
else
 echo "Roles already exist, skipping seeders to avoid lock contention."
fi

php artisan config:cache
php artisan event:cache
php artisan route:cache
php artisan view:cache
