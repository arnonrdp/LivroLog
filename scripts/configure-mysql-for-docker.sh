#!/bin/bash
# Configure MySQL/MariaDB to accept Docker container connections
# Run this script on the production server as root or with sudo

set -e

echo "🔧 Configuring MySQL/MariaDB for Docker container access..."

# 1) Detect MySQL/MariaDB installation type
if [ -d "/opt/bitnami/mariadb" ]; then
    echo "📍 Detected Bitnami MariaDB installation"
    MYSQL_TYPE="bitnami"
    CONFIG_FILE="/opt/bitnami/mariadb/conf/my.cnf"
    RESTART_CMD="sudo /opt/bitnami/ctlscript.sh restart mariadb"
elif [ -f "/etc/mysql/mysql.conf.d/mysqld.cnf" ]; then
    echo "📍 Detected standard MySQL/MariaDB installation (Debian/Ubuntu)"
    MYSQL_TYPE="standard"
    CONFIG_FILE="/etc/mysql/mysql.conf.d/mysqld.cnf"
    RESTART_CMD="sudo systemctl restart mysql || sudo service mysql restart"
elif [ -f "/etc/mysql/my.cnf" ]; then
    echo "📍 Detected MySQL/MariaDB with /etc/mysql/my.cnf"
    MYSQL_TYPE="standard"
    CONFIG_FILE="/etc/mysql/my.cnf"
    RESTART_CMD="sudo systemctl restart mysql || sudo service mysql restart"
else
    echo "❌ Could not detect MySQL/MariaDB installation"
    exit 1
fi

echo "Using config file: $CONFIG_FILE"

# 2) Check current listeners
echo "🔍 Current MySQL listeners:"
sudo ss -ltnp | grep :3306 || sudo netstat -plnt | grep :3306 || echo "No MySQL listeners found"

# 3) Configure bind-address and remove skip-networking
echo "⚙️  Configuring MySQL to listen on 0.0.0.0:3306..."

if [ "$MYSQL_TYPE" = "bitnami" ]; then
    # Bitnami configuration
    sudo sed -i 's/^\s*bind-address\s*=.*/bind-address=0.0.0.0/' "$CONFIG_FILE" || true
    sudo sed -i 's/^\s*skip-networking/# skip-networking/' "$CONFIG_FILE" || true
    
    # Add bind-address if not present
    if ! grep -q "bind-address" "$CONFIG_FILE"; then
        echo "bind-address=0.0.0.0" | sudo tee -a "$CONFIG_FILE"
    fi
else
    # Standard installation
    sudo sed -i 's/^\s*bind-address\s*=.*/bind-address = 0.0.0.0/' /etc/mysql/mysql.conf.d/mysqld.cnf || true
    sudo sed -i 's/^\s*bind-address\s*=.*/bind-address = 0.0.0.0/' /etc/mysql/my.cnf || true
    sudo sed -i 's/^\s*skip-networking/# skip-networking/' /etc/mysql/mysql.conf.d/mysqld.cnf || true
    sudo sed -i 's/^\s*skip-networking/# skip-networking/' /etc/mysql/my.cnf || true
fi

# 4) Configure firewall for Docker bridge network
echo "🛡️ Configuring UFW firewall for Docker bridge network..."
sudo ufw allow from 172.17.0.0/16 to any port 3306 proto tcp

# 5) Restart MySQL/MariaDB
echo "🔄 Restarting MySQL/MariaDB..."
eval "$RESTART_CMD"

# 6) Wait for service to be ready
echo "⏳ Waiting for MySQL/MariaDB to start..."
sleep 3

# 7) Verify configuration
echo "✅ Verification:"
echo "MySQL listeners after restart:"
sudo ss -ltnp | grep :3306 || sudo netstat -plnt | grep :3306 || echo "❌ MySQL not listening on 3306"

echo "UFW rules:"
sudo ufw status numbered

echo ""
echo "✅ MySQL/MariaDB configuration completed!"
echo ""
echo "🔧 Configuration applied:"
echo "  • bind-address = 0.0.0.0 (listens on all interfaces)"
echo "  • skip-networking commented out (allows network connections)"
echo "  • UFW rule: allow from 172.17.0.0/16 to port 3306"
echo ""
echo "🚀 Docker containers can now connect to MySQL/MariaDB!"