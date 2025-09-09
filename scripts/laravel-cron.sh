#!/bin/bash
# scripts/laravel-cron.sh
# Laravel cron runner for containerized API
# Comments in English only

# Production cron
if docker ps --format "table {{.Names}}" | grep -q "^livrolog-api$"; then
    docker exec -t livrolog-api php artisan schedule:run -q
fi

# Development cron
if docker ps --format "table {{.Names}}" | grep -q "^livrolog-api-dev$"; then
    docker exec -t livrolog-api-dev php artisan schedule:run -q
fi