#!/bin/bash
set -euo pipefail

echo "=== Fixing Apache Proxy Configuration for Docker Containers ==="
date

# Check if Apache is running
if ! systemctl is-active --quiet apache2; then
    echo "❌ Apache is not running"
    exit 1
fi

echo "✅ Apache is running"

# Backup current Apache configuration
echo "📋 Backing up current Apache configuration..."
sudo cp -r /opt/bitnami/apache/conf /opt/bitnami/apache/conf.backup.$(date +%Y%m%d_%H%M%S)

# Enable required Apache modules for proxy
echo "🔧 Enabling Apache proxy modules..."
sudo /opt/bitnami/apache/bin/httpd -M 2>/dev/null | grep -q proxy_module || {
    echo "LoadModule proxy_module modules/mod_proxy.so" | sudo tee -a /opt/bitnami/apache/conf/httpd.conf
}

sudo /opt/bitnami/apache/bin/httpd -M 2>/dev/null | grep -q proxy_http_module || {
    echo "LoadModule proxy_http_module modules/mod_proxy_http.so" | sudo tee -a /opt/bitnami/apache/conf/httpd.conf
}

# Create/Update virtual host configuration for dev.livrolog.com
echo "🌐 Creating virtual host configuration for dev.livrolog.com..."
sudo tee /opt/bitnami/apache/conf/vhosts/dev-livrolog-com.conf > /dev/null << 'VHOST_EOF'
# Virtual Host for dev.livrolog.com (Development)
<VirtualHost *:80>
    ServerName dev.livrolog.com
    ServerAlias www.dev.livrolog.com
    
    # Proxy all requests to Docker container
    ProxyPreserveHost On
    ProxyPass / http://127.0.0.1:8080/
    ProxyPassReverse / http://127.0.0.1:8080/
    
    # Optional: Add headers for debugging
    ProxyAddHeaders On
    
    # Logging
    ErrorLog /opt/bitnami/apache/logs/dev-livrolog-error.log
    CustomLog /opt/bitnami/apache/logs/dev-livrolog-access.log combined
</VirtualHost>

# Virtual Host for api.dev.livrolog.com (Development API)
<VirtualHost *:80>
    ServerName api.dev.livrolog.com
    ServerAlias www.api.dev.livrolog.com
    
    # Proxy all requests to Docker API container
    ProxyPreserveHost On
    ProxyPass / http://127.0.0.1:8081/
    ProxyPassReverse / http://127.0.0.1:8081/
    
    # Optional: Add headers for debugging
    ProxyAddHeaders On
    
    # API-specific headers
    Header always set Access-Control-Allow-Origin "*"
    Header always set Access-Control-Allow-Methods "GET, POST, PUT, DELETE, OPTIONS"
    Header always set Access-Control-Allow-Headers "Content-Type, Authorization"
    
    # Logging
    ErrorLog /opt/bitnami/apache/logs/api-dev-livrolog-error.log
    CustomLog /opt/bitnami/apache/logs/api-dev-livrolog-access.log combined
</VirtualHost>
VHOST_EOF

# Test Apache configuration
echo "🧪 Testing Apache configuration..."
if ! sudo /opt/bitnami/apache/bin/httpd -t; then
    echo "❌ Apache configuration test failed"
    exit 1
fi

echo "✅ Apache configuration test passed"

# Restart Apache
echo "🔄 Restarting Apache..."
sudo systemctl restart apache2 || sudo systemctl restart bitnami

# Wait for Apache to restart
sleep 5

# Check if Apache is running after restart
if systemctl is-active --quiet apache2; then
    echo "✅ Apache restarted successfully"
else
    echo "❌ Apache failed to restart"
    sudo systemctl status apache2
    exit 1
fi

# Test the proxy configuration
echo "🧪 Testing proxy configuration..."

# Test web frontend
echo "Testing dev.livrolog.com..."
WEB_STATUS=$(curl -s -o /dev/null -w "%{http_code}" -H "Host: dev.livrolog.com" http://127.0.0.1/ 2>/dev/null || echo "000")
echo "Web frontend test: HTTP $WEB_STATUS"

# Test API
echo "Testing api.dev.livrolog.com..."
API_STATUS=$(curl -s -o /dev/null -w "%{http_code}" -H "Host: api.dev.livrolog.com" http://127.0.0.1/healthz 2>/dev/null || echo "000")
echo "API test: HTTP $API_STATUS"

# Test books endpoint
if [ "$API_STATUS" = "200" ]; then
    echo "Testing books API endpoint..."
    BOOKS_TEST=$(curl -s -w "\nHTTP_CODE:%{http_code}" -H "Host: api.dev.livrolog.com" http://127.0.0.1/books?sort_by=popular 2>/dev/null | tail -1)
    echo "Books API test result: $BOOKS_TEST"
fi

echo "📊 Final Apache status:"
systemctl status apache2 --no-pager -l

echo "✅ Proxy configuration fix completed!"
echo "📍 Services should now be accessible via:"
echo "  • Web: http://dev.livrolog.com"
echo "  • API: http://api.dev.livrolog.com"