#!/bin/bash

echo "=== Restarting Queue Workers ==="
echo "Time: $(date)"

# Kill existing PHP queue workers
echo "Stopping existing queue workers..."
pkill -f "php artisan queue:work" || echo "No existing workers found"

# Wait a moment for processes to die
sleep 2

# Start production queue worker
if [ -d "/var/www/livrolog/current/api" ]; then
    echo "Starting production queue worker..."
    cd /var/www/livrolog/current/api
    nohup php artisan queue:work --verbose --tries=3 --timeout=60 > storage/logs/queue-worker.log 2>&1 &
    echo "Production worker started with PID: $!"
fi

# Start dev queue worker
if [ -d "/var/www/livrolog-dev/current/api" ]; then
    echo "Starting dev queue worker..."
    cd /var/www/livrolog-dev/current/api
    nohup php artisan queue:work --verbose --tries=3 --timeout=60 > storage/logs/queue-worker.log 2>&1 &
    echo "Dev worker started with PID: $!"
fi

# Wait and verify
sleep 3
echo ""
echo "Active queue workers:"
ps aux | grep "queue:work" | grep -v grep | grep -v bash

echo ""
echo "=== Queue workers restarted successfully ==="