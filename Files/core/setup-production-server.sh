#!/bin/bash

#############################################################################
# Production Server Setup Script
# This script sets up a production server for Laravel deployment
# Usage: sudo ./setup-production-server.sh
#############################################################################

set -e

# Colors
GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m'

log_info() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

log_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

log_warn() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

echo ""
echo "╔════════════════════════════════════════════════════════════════╗"
echo "║       PRODUCTION SERVER SETUP                                  ║"
echo "║       Laravel Application Server                               ║"
echo "╚════════════════════════════════════════════════════════════════╝"
echo ""

# Check if running as root
if [[ $EUID -ne 0 ]]; then
   log_error "This script must be run as root (use sudo)"
   exit 1
fi

log_info "Starting production server setup..."
echo ""

#############################################################################
# Update System
#############################################################################
log_info "Updating system packages..."
apt update && apt upgrade -y
log_success "System updated"
echo ""

#############################################################################
# Install Required Packages
#############################################################################
log_info "Installing required packages..."
apt install -y curl wget unzip software-properties-common apt-transport-https ca-certificates gnupg lsb-release
log_success "Required packages installed"
echo ""

#############################################################################
# Add PHP Repository
#############################################################################
log_info "Adding PHP repository..."
add-apt-repository ppa:ondrej/php -y
apt update
log_success "PHP repository added"
echo ""

#############################################################################
# Install PHP 8.2 and Extensions
#############################################################################
log_info "Installing PHP 8.2 and extensions..."
apt install -y \
    php8.2-fpm \
    php8.2-cli \
    php8.2-mysql \
    php8.2-xml \
    php8.2-mbstring \
    php8.2-zip \
    php8.2-curl \
    php8.2-bcmath \
    php8.2-gd \
    php8.2-intl \
    php8.2-redis \
    php8.2-mcrypt \
    php8.2-json \
    php8.2-tokenizer \
    php8.2-fileinfo \
    php8.2-dom \
    php8.2-filter
log_success "PHP 8.2 and extensions installed"
echo ""

#############################################################################
# Install MySQL
#############################################################################
log_info "Installing MySQL server..."
export DEBIAN_FRONTEND=noninteractive
apt install -y mysql-server
log_success "MySQL server installed"
echo ""

#############################################################################
# Install Redis
#############################################################################
log_info "Installing Redis server..."
apt install -y redis-server
systemctl enable redis-server
systemctl start redis-server
log_success "Redis server installed and started"
echo ""

#############################################################################
# Install Nginx
#############################################################################
log_info "Installing Nginx web server..."
apt install -y nginx
systemctl enable nginx
systemctl start nginx
log_success "Nginx installed and started"
echo ""

#############################################################################
# Install Composer
#############################################################################
log_info "Installing Composer..."
curl -sS https://getcomposer.org/installer | php
mv composer.phar /usr/local/bin/composer
chmod +x /usr/local/bin/composer
log_success "Composer installed"
echo ""

#############################################################################
# Install Node.js 18
#############################################################################
log_info "Installing Node.js 18..."
curl -fsSL https://deb.nodesource.com/setup_18.x | bash -
apt install -y nodejs
log_success "Node.js 18 installed"
echo ""

#############################################################################
# Install Git
#############################################################################
log_info "Installing Git..."
apt install -y git
log_success "Git installed"
echo ""

#############################################################################
# Install Supervisor
#############################################################################
log_info "Installing Supervisor..."
apt install -y supervisor
systemctl enable supervisor
systemctl start supervisor
log_success "Supervisor installed and started"
echo ""

#############################################################################
# Install Certbot for SSL
#############################################################################
log_info "Installing Certbot for SSL..."
apt install -y certbot python3-certbot-nginx
log_success "Certbot installed"
echo ""

#############################################################################
# Create Deploy User
#############################################################################
log_info "Creating deploy user..."
if ! id -u deploy &>/dev/null; then
    useradd -m -s /bin/bash deploy
    usermod -aG www-data deploy
    log_success "Deploy user created"
else
    log_warn "Deploy user already exists"
fi
echo ""

#############################################################################
# Create Application Directories
#############################################################################
log_info "Creating application directories..."
mkdir -p /var/www/production
mkdir -p /var/backups/laravel
chown -R deploy:deploy /var/www/production /var/backups/laravel
log_success "Application directories created"
echo ""

#############################################################################
# Configure PHP-FPM
#############################################################################
log_info "Configuring PHP-FPM..."
# Optimize PHP-FPM settings
sed -i 's/pm.max_children = 5/pm.max_children = 50/' /etc/php/8.2/fpm/pool.d/www.conf
sed -i 's/pm.start_servers = 2/pm.start_servers = 5/' /etc/php/8.2/fpm/pool.d/www.conf
sed -i 's/pm.min_spare_servers = 1/pm.min_spare_servers = 5/' /etc/php/8.2/fpm/pool.d/www.conf
sed -i 's/pm.max_spare_servers = 3/pm.max_spare_servers = 35/' /etc/php/8.2/fpm/pool.d/www.conf
systemctl reload php8.2-fpm
log_success "PHP-FPM configured"
echo ""

#############################################################################
# Configure MySQL Security
#############################################################################
log_warn "MySQL secure installation required"
log_info "Please run: mysql_secure_installation"
log_info "Set root password, remove anonymous users, disallow root login remotely"
echo ""

#############################################################################
# Create Database Script
#############################################################################
log_info "Creating database setup script..."
cat > /tmp/setup-database.sql << 'EOF'
-- Create database and user
CREATE DATABASE IF NOT EXISTS binaryecom20_production CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Create user (change password!)
CREATE USER 'binaryecom_user'@'localhost' IDENTIFIED BY 'SecureP@ssw0rd2025!';

-- Grant privileges
GRANT ALL PRIVILEGES ON binaryecom20_production.* TO 'binaryecom_user'@'localhost';

-- Flush privileges
FLUSH PRIVILEGES;
EOF

log_success "Database setup script created at /tmp/setup-database.sql"
log_info "Run: mysql -u root -p < /tmp/setup-database.sql"
echo ""

#############################################################################
# Firewall Configuration
#############################################################################
log_info "Configuring UFW firewall..."
ufw allow OpenSSH
ufw allow 'Nginx Full'
ufw --force enable
log_success "Firewall configured"
echo ""

#############################################################################
# Setup Complete
#############################################################################
echo ""
echo "╔════════════════════════════════════════════════════════════════╗"
echo "║       PRODUCTION SERVER SETUP COMPLETE                         ║"
echo "╚════════════════════════════════════════════════════════════════╝"
echo ""
log_success "Production server setup completed!"
echo ""
echo "NEXT STEPS:"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""
echo "1. Set up SSH keys for deploy user:"
echo "   - On your local machine: ssh-copy-id deploy@SERVER_IP"
echo ""
echo "2. Configure MySQL database:"
echo "   mysql -u root -p < /tmp/setup-database.sql"
echo ""
echo "3. Create Nginx virtual host:"
echo "   - See LIVE_PRODUCTION_DEPLOYMENT.md for configuration"
echo ""
echo "4. Obtain SSL certificate:"
echo "   certbot --nginx -d yourdomain.com"
echo ""
echo "5. Deploy application:"
echo "   - Via GitHub Actions (recommended)"
echo "   - Or manual: ./scripts/deploy.sh production master"
echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""
echo "INSTALLED SOFTWARE:"
echo "  ✓ PHP 8.2 with all extensions"
echo "  ✓ MySQL 8.0"
echo "  ✓ Redis 6+"
echo "  ✓ Nginx"
echo "  ✓ Composer"
echo "  ✓ Node.js 18"
echo "  ✓ Git"
echo "  ✓ Supervisor"
echo "  ✓ Certbot (SSL)"
echo ""
echo "CONFIGURED USERS:"
echo "  ✓ deploy (application deployment)"
echo ""
echo "CONFIGURED DIRECTORIES:"
echo "  ✓ /var/www/production (application root)"
echo "  ✓ /var/backups/laravel (backup location)"
echo ""
echo "SERVICES ENABLED:"
echo "  ✓ nginx (started and enabled)"
echo "  ✓ php8.2-fpm (started and enabled)"
echo "  ✓ redis-server (started and enabled)"
echo "  ✓ supervisor (started and enabled)"
echo ""
log_success "Server is ready for Laravel application deployment!"
echo ""
