#!/bin/bash
# Railway Pre-Deploy Command for this app's service, per docs/DEPLOYMENT.md.
# Runs once per deploy, after the build completes and before traffic is
# routed to the new instance -- safe to run on every deploy (idempotent).
set -e

php artisan optimize:clear
php artisan migrate --force
php artisan storage:link
php artisan config:cache
php artisan event:cache
php artisan route:cache
php artisan view:cache
