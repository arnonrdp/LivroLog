#!/bin/bash
# Apache Setup Script for LivroLog Docker Production
# Run on the production server as bitnami user with sudo

set -e

echo "🔧 Setting up Apache proxy configuration for LivroLog Docker..."

# Enable required Apache modules
echo "📦 Enabling Apache modules..."
sudo /opt/bitnami/apache2/bin/httpd -M | grep -q proxy_module || {
    echo "Enabling proxy module..."
    sudo echo "LoadModule proxy_module modules/mod_proxy.so" >> /opt/bitnami/apache2/conf/httpd.conf
}

sudo /opt/bitnami/apache2/bin/httpd -M | grep -q proxy_http_module || {
    echo "Enabling proxy_http module..."
    sudo echo "LoadModule proxy_http_module modules/mod_proxy_http.so" >> /opt/bitnami/apache2/conf/httpd.conf
}

sudo /opt/bitnami/apache2/bin/httpd -M | grep -q headers_module || {
    echo "Enabling headers module..."
    sudo echo "LoadModule headers_module modules/mod_headers.so" >> /opt/bitnami/apache2/conf/httpd.conf
}

sudo /opt/bitnami/apache2/bin/httpd -M | grep -q rewrite_module || {
    echo "Enabling rewrite module..."
    sudo echo "LoadModule rewrite_module modules/mod_rewrite.so" >> /opt/bitnami/apache2/conf/httpd.conf
}

# Copy virtual host configuration
echo "📝 Installing LivroLog virtual host..."
sudo cp /var/www/livrolog/apache-config/livrolog.conf /opt/bitnami/apache2/conf/vhosts/
sudo chown bitnami:daemon /opt/bitnami/apache2/conf/vhosts/livrolog.conf

# Backup existing default configuration
if [ -f "/opt/bitnami/apache2/conf/vhosts/sample-https-vhost.conf" ]; then
    sudo mv /opt/bitnami/apache2/conf/vhosts/sample-https-vhost.conf /opt/bitnami/apache2/conf/vhosts/sample-https-vhost.conf.backup
fi

# Test Apache configuration
echo "🧪 Testing Apache configuration..."
sudo /opt/bitnami/apache2/bin/httpd -t

# Reload Apache
echo "🔄 Reloading Apache..."
sudo /opt/bitnami/ctlscript.sh restart apache

echo "✅ Apache configuration completed successfully!"
echo "🌐 LivroLog should now be accessible via:"
echo "   - http://livrolog.com"
echo "   - https://livrolog.com"
echo "   - API: https://livrolog.com/api/"
echo "   - Health: https://livrolog.com/health"