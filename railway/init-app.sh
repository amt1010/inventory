#!/bin/bash
# Railway Pre-Deploy Command for this app's service, per docs/DEPLOYMENT.md.
# Runs once per deploy, after the build completes and before traffic is
# routed to the new instance -- safe to run on every deploy (idempotent).
set -e

php artisan optimize:clear
php artisan migrate --force

# Previously this gated seeding on `php artisan tinker --execute="echo
# DB::table('roles')->count();"`, but tinker boots a full REPL, which is
# slow and can hang, blowing past the pre-deploy health check timeout and
# leaving the service unable to start.
#
# Both seeders below are idempotent (RoleSeeder upserts permissions and
# uses firstOrCreate for roles; EmailTemplateSeeder skips any key that
# already exists), so it's safe -- and much faster -- to just run them on
# every deploy instead of doing a separate existence check first.
echo "Seeding RoleSeeder..."
php artisan db:seed --class=RoleSeeder --force
echo "Seeding EmailTemplateSeeder..."
php artisan db:seed --class=EmailTemplateSeeder --force

php artisan config:cache
php artisan event:cache
php artisan route:cache
php artisan view:cache
