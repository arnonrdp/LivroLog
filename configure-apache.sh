#!/bin/bash

# Simple script to configure Apache proxy via SSH
# This will be executed manually

echo "Configuring Apache proxy for LivroLog development containers..."

# Create temporary SSH script
cat > /tmp/apache_config_script.sh << 'SCRIPT_END'
#!/bin/bash
set -e

echo "=== Configuring Apache Proxy ==="
date

# Create virtual host configuration
sudo tee /opt/bitnami/apache/conf/vhosts/livrolog-dev.conf > /dev/null << 'VHOST_END'
# Load required proxy modules
LoadModule proxy_module modules/mod_proxy.so
LoadModule proxy_http_module modules/mod_proxy_http.so
LoadModule headers_module modules/mod_headers.so

# API Virtual Host (api.dev.livrolog.com -> Docker container port 8081)
<VirtualHost *:80>
    ServerName api.dev.livrolog.com
    
    # Proxy configuration
    ProxyPreserveHost On
    ProxyPass / http://127.0.0.1:8081/
    ProxyPassReverse / http://127.0.0.1:8081/
    
    # CORS headers for API
    Header always set Access-Control-Allow-Origin "*"
    Header always set Access-Control-Allow-Methods "GET, POST, PUT, DELETE, OPTIONS, PATCH"
    Header always set Access-Control-Allow-Headers "Content-Type, Authorization, X-Requested-With"
    
    # Logging
    ErrorLog /opt/bitnami/apache/logs/api-dev-livrolog-error.log
    CustomLog /opt/bitnami/apache/logs/api-dev-livrolog-access.log combined
</VirtualHost>

# Web Frontend Virtual Host (dev.livrolog.com -> Docker container port 8080)
<VirtualHost *:80>
    ServerName dev.livrolog.com
    
    # Proxy configuration
    ProxyPreserveHost On
    ProxyPass / http://127.0.0.1:8080/
    ProxyPassReverse / http://127.0.0.1:8080/
    
    # Logging
    ErrorLog /opt/bitnami/apache/logs/dev-livrolog-error.log
    CustomLog /opt/bitnami/apache/logs/dev-livrolog-access.log combined
</VirtualHost>
VHOST_END

# Include virtual host in main configuration if not already included
if ! grep -q "livrolog-dev.conf" /opt/bitnami/apache/conf/httpd.conf; then
    echo "Include conf/vhosts/livrolog-dev.conf" | sudo tee -a /opt/bitnami/apache/conf/httpd.conf
fi

# Test Apache configuration
echo "Testing Apache configuration..."
if sudo /opt/bitnami/apache/bin/httpd -t; then
    echo "✅ Apache configuration test passed"
else
    echo "❌ Apache configuration test failed"
    exit 1
fi

# Restart Apache
echo "Restarting Apache..."
sudo pkill -f httpd 2>/dev/null || true
sleep 2
sudo /opt/bitnami/apache/bin/httpd &
sleep 5

# Check if Apache is running
if pgrep -f httpd > /dev/null; then
    echo "✅ Apache restarted successfully"
else
    echo "❌ Failed to start Apache"
    exit 1
fi

# Test the proxy configuration
echo "Testing proxy configuration..."

# Test API
echo "Testing API proxy (api.dev.livrolog.com)..."
API_RESULT=$(curl -s -w "HTTP:%{http_code}" -H "Host: api.dev.livrolog.com" http://127.0.0.1/healthz 2>/dev/null)
echo "API health test: $API_RESULT"

# Test Web
echo "Testing Web proxy (dev.livrolog.com)..."
WEB_RESULT=$(curl -s -o /dev/null -w "HTTP:%{http_code}" -H "Host: dev.livrolog.com" http://127.0.0.1/ 2>/dev/null)
echo "Web test: $WEB_RESULT"

echo ""
echo "✅ Apache proxy configuration completed!"
echo "🌐 Your API should now be accessible at: https://api.dev.livrolog.com"
echo "🌐 Your Web should now be accessible at: https://dev.livrolog.com"

SCRIPT_END

# Make the script executable
chmod +x /tmp/apache_config_script.sh

echo "Script created at /tmp/apache_config_script.sh"
echo ""
echo "To execute the Apache configuration, run:"
echo "ssh -o StrictHostKeyChecking=no -i ~/.ssh/livrolog-key.pem bitnami@35.170.25.86 'bash -s' < /tmp/apache_config_script.sh"
echo ""
echo "Or upload and execute on server:"
echo "scp -o StrictHostKeyChecking=no -i ~/.ssh/livrolog-key.pem /tmp/apache_config_script.sh bitnami@35.170.25.86:/tmp/"
echo "ssh -o StrictHostKeyChecking=no -i ~/.ssh/livrolog-key.pem bitnami@35.170.25.86 'bash /tmp/apache_config_script.sh'"