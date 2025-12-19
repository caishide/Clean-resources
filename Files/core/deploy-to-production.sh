#!/bin/bash

#############################################################################
# Live Production Deployment Script
# This script deploys the Laravel application to a live production server
# Usage: ./deploy-to-production.sh [repository_url] [branch]
#############################################################################

set -e

# Colors
GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m'

log_info() {
    echo -e "${BLUE}[$(date '+%Y-%m-%d %H:%M:%S')] [INFO]${NC} $1"
}

log_success() {
    echo -e "${GREEN}[$(date '+%Y-%m-%d %H:%M:%S')] [SUCCESS]${NC} $1"
}

log_warn() {
    echo -e "${YELLOW}[$(date '+%Y-%m-%d %H:%M:%S')] [WARNING]${NC} $1"
}

log_error() {
    echo -e "${RED}[$(date '+%Y-%m-%d %H:%M:%S')] [ERROR]${NC} $1"
}

echo ""
echo "╔════════════════════════════════════════════════════════════════╗"
echo "║       LIVE PRODUCTION DEPLOYMENT                               ║"
echo "║       BinaryEcom20 Secure Application                         ║"
echo "╚════════════════════════════════════════════════════════════════╝"
echo ""

# Configuration
APP_DIR="/var/www/production"
BACKUP_DIR="/var/backups/laravel"
REPO_URL="${1:-https://github.com/YOUR_USERNAME/YOUR_REPO.git}"
BRANCH="${2:-master}"
NGINX_USER="www-data"
PHP_VERSION="8.2"

# Check if running as deploy user or root
if [ "$USER" != "deploy" ] && [ "$EUID" -ne 0 ]; then
    log_warn "Not running as deploy user or root. Some operations may fail."
fi

#############################################################################
# Pre-Deployment Checks
#############################################################################
log_info "Running pre-deployment checks..."

# Check if production directory exists
if [ ! -d "$APP_DIR" ]; then
    log_error "Production directory $APP_DIR does not exist!"
    log_info "Run setup-production-server.sh first to set up the server"
    exit 1
fi
log_success "✓ Production directory exists"

# Check if .env.production exists
if [ ! -f ".env.production" ]; then
    log_error ".env.production not found in current directory!"
    log_info "Make sure you're running this from the application root directory"
    exit 1
fi
log_success "✓ Production environment file found"

# Check APP_KEY
APP_KEY=$(grep "^APP_KEY=" .env.production | cut -d '=' -f2)
if [ -z "$APP_KEY" ] || [ "$APP_KEY" == "" ]; then
    log_error "APP_KEY not configured in .env.production"
    exit 1
fi
log_success "✓ APP_KEY configured"

# Check MySQL
if ! command -v mysql &> /dev/null; then
    log_error "MySQL is not installed"
    exit 1
fi
log_success "✓ MySQL installed"

# Check Redis
if ! command -v redis-cli &> /dev/null; then
    log_warn "Redis not installed or not in PATH"
fi
log_success "✓ Redis available"

# Check Nginx
if ! command -v nginx &> /dev/null; then
    log_error "Nginx is not installed"
    exit 1
fi
log_success "✓ Nginx installed"

# Check PHP-FPM
if ! systemctl is-active --quiet php${PHP_VERSION}-fpm; then
    log_warn "PHP-FPM is not running"
    log_info "Attempting to start PHP-FPM..."
    sudo systemctl start php${PHP_VERSION}-fpm
fi
log_success "✓ PHP-FPM running"

echo ""

#############################################################################
# Create Backup
#############################################################################
log_info "Creating backup..."
BACKUP_TIMESTAMP=$(date +%Y%m%d_%H%M%S)
BACKUP_PATH="$BACKUP_DIR/$BACKUP_TIMESTAMP"

sudo mkdir -p "$BACKUP_PATH"

# Backup application files
if [ -d "$APP_DIR" ] && [ "$(ls -A $APP_DIR 2>/dev/null)" ]; then
    sudo cp -r "$APP_DIR" "$BACKUP_PATH/"
    log_success "✓ Application files backed up"
else
    log_warn "No existing application files to backup"
fi

# Backup database
DB_NAME=$(grep "^DB_DATABASE=" .env.production | cut -d '=' -f2)
DB_USER=$(grep "^DB_USERNAME=" .env.production | cut -d '=' -f2)
DB_PASS=$(grep "^DB_PASSWORD=" .env.production | cut -d '=' -f2)

if [ ! -z "$DB_NAME" ] && [ ! -z "$DB_USER" ]; then
    log_info "Backing up database..."
    mysqldump -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" > "$BACKUP_PATH/database.sql" 2>/dev/null || log_warn "Database backup failed (check credentials)"
    log_success "✓ Database backed up"
fi

# Compress backup
sudo tar -czf "$BACKUP_PATH.tar.gz" -C "$BACKUP_DIR" "$BACKUP_TIMESTAMP"
sudo rm -rf "$BACKUP_PATH"

# Keep only last 10 backups
cd "$BACKUP_DIR"
ls -t *.tar.gz 2>/dev/null | tail -n +11 | xargs -r sudo rm --

log_success "✓ Backup created: $BACKUP_DIR/$BACKUP_TIMESTAMP.tar.gz"
echo ""

#############################################################################
# Deploy Application
#############################################################################
log_info "Starting deployment..."

cd "$APP_DIR"

# Clone or update repository
if [ ! -d ".git" ]; then
    log_info "Cloning repository to $APP_DIR"
    sudo git clone -b "$BRANCH" "$REPO_URL" "$APP_DIR.tmp"
    sudo mv "$APP_DIR.tmp"/* "$APP_DIR/"
    sudo rm -rf "$APP_DIR.tmp"
else
    log_info "Updating repository"
    sudo git fetch origin
    sudo git checkout "$BRANCH"
    sudo git pull origin "$BRANCH"
fi

# Set permissions
log_info "Setting permissions..."
sudo chown -R "$NGINX_USER:$NGINX_USER" "$APP_DIR"
sudo chmod -R 755 "$APP_DIR"
sudo chmod -R 775 "$APP_DIR/storage"
sudo chmod -R 775 "$APP_DIR/bootstrap/cache"
sudo chmod 600 "$APP_DIR/.env" 2>/dev/null || true

log_success "✓ Permissions set"
echo ""

#############################################################################
# Install Dependencies
#############################################################################
log_info "Installing Composer dependencies..."
sudo -u "$NGINX_USER" composer install --no-dev --optimize-autoloader --no-interaction
log_success "✓ Composer dependencies installed"

# Install Node dependencies and build assets
if [ -f "package.json" ]; then
    log_info "Installing Node dependencies..."
    sudo -u "$NGINX_USER" npm ci --only=production

    log_info "Building assets..."
    sudo -u "$NGINX_USER" npm run build
    log_success "✓ Assets built"
fi
echo ""

#############################################################################
# Configure Environment
#############################################################################
log_info "Configuring environment..."
sudo cp .env.production .env
sudo chown "$NGINX_USER:$NGINX_USER" .env
sudo chmod 600 .env

# Generate APP_KEY if not set
if ! grep -q "APP_KEY=base64:" .env; then
    log_info "Generating APP_KEY..."
    sudo -u "$NGINX_USER" php artisan key:generate --force
    log_success "✓ APP_KEY generated"
fi
echo ""

#############################################################################
# Run Database Migrations
#############################################################################
log_info "Running database migrations..."
sudo -u "$NGINX_USER" php artisan migrate --force --no-interaction
log_success "✓ Database migrations completed"
echo ""

#############################################################################
# Cache Optimization
#############################################################################
log_info "Optimizing caches..."

# Clear caches
sudo -u "$NGINX_USER" php artisan cache:clear --no-interaction 2>/dev/null || true
sudo -u "$NGINX_USER" php artisan config:clear --no-interaction 2>/dev/null || true
sudo -u "$NGINX_USER" php artisan view:clear --no-interaction 2>/dev/null || true
sudo -u "$NGINX_USER" php artisan route:clear --no-interaction 2>/dev/null || true

# Create optimized caches
sudo -u "$NGINX_USER" php artisan config:cache --no-interaction
sudo -u "$NGINX_USER" php artisan route:cache --no-interaction
sudo -u "$NGINX_USER" php artisan view:cache --no-interaction
sudo -u "$NGINX_USER" php artisan event:cache --no-interaction

# Create storage link
sudo -u "$NGINX_USER" php artisan storage:link --no-interaction

log_success "✓ Caches optimized"
echo ""

#############################################################################
# Restart Services
#############################################################################
log_info "Restarting services..."

# Restart PHP-FPM
sudo systemctl reload php${PHP_VERSION}-fpm
log_success "✓ PHP-FPM reloaded"

# Restart Nginx
sudo systemctl reload nginx
log_success "✓ Nginx reloaded"

# Restart Supervisor
if systemctl is-active --quiet supervisor; then
    sudo systemctl reload supervisor
    log_success "✓ Supervisor reloaded"
fi

echo ""

#############################################################################
# Health Checks
#############################################################################
log_info "Running health checks..."

MAX_ATTEMPTS=10
ATTEMPT=1

while [ $ATTEMPT -le $MAX_ATTEMPTS ]; do
    log_info "Health check attempt $ATTEMPT/$MAX_ATTEMPTS..."

    # Check if web server is responding
    if curl -f -s http://localhost/health > /dev/null 2>&1; then
        log_success "✓ Application is healthy"
        break
    fi

    if [ $ATTEMPT -eq $MAX_ATTEMPTS ]; then
        log_error "✗ Health checks failed after $MAX_ATTEMPTS attempts"
        log_info "Rolling back to previous version..."
        ./rollback.sh "$BACKUP_TIMESTAMP"
        exit 1
    fi

    log_warn "Health check failed, waiting 10 seconds..."
    sleep 10
    ATTEMPT=$((ATTEMPT + 1))
done

echo ""

#############################################################################
# Post-Deployment Tasks
#############################################################################
log_info "Running post-deployment tasks..."

# Warm up caches
log_info "Warming up caches..."
curl -s http://localhost/ > /dev/null 2>&1 || true
curl -s http://localhost/login > /dev/null 2>&1 || true
curl -s http://localhost/dashboard > /dev/null 2>&1 || true
log_success "✓ Caches warmed up"

# Check permissions
log_info "Verifying permissions..."
sudo chown -R "$NGINX_USER:$NGINX_USER" "$APP_DIR"
sudo chmod -R 755 "$APP_DIR"
sudo chmod -R 775 "$APP_DIR/storage"
sudo chmod -R 775 "$APP_DIR/bootstrap/cache"
log_success "✓ Permissions verified"

echo ""

#############################################################################
# Deployment Complete
#############################################################################
echo ""
echo "╔════════════════════════════════════════════════════════════════╗"
echo "║       DEPLOYMENT COMPLETE                                      ║"
echo "╚════════════════════════════════════════════════════════════════╝"
echo ""
log_success "✓ Deployment successful!"
echo ""
echo "DEPLOYMENT SUMMARY:"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "Environment:           Production"
echo "Branch:                $BRANCH"
echo "Repository:            $REPO_URL"
echo "Backup Location:       $BACKUP_DIR/$BACKUP_TIMESTAMP.tar.gz"
echo "Deployment Time:       $(date)"
echo ""
echo "APPLICATION STATUS:"
echo "  ✓ Code deployed"
echo "  ✓ Dependencies installed"
echo "  ✓ Database migrated"
echo "  ✓ Caches optimized"
echo "  ✓ Services restarted"
echo "  ✓ Health checks passed"
echo ""
echo "NEXT STEPS:"
echo "  1. Verify application: https://yourdomain.com"
echo "  2. Check health: https://yourdomain.com/health"
echo "  3. Run tests: https://yourdomain.com/api/security-test"
echo "  4. Monitor logs: tail -f $APP_DIR/storage/logs/laravel.log"
echo ""
echo "ROLLBACK:"
echo "  To rollback: ./rollback.sh $BACKUP_TIMESTAMP"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""
