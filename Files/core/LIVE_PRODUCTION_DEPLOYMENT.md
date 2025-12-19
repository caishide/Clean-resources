# 🚀 Live Production Deployment Guide

## Current Status
- **Development Environment**: `/www/wwwroot/binaryecom20/Files/core`
- **Production Environment**: NOT YET CONFIGURED
- **Deployment Status**: Ready for production server setup

---

## Prerequisites for Live Production Deployment

You need access to a **real production server** with:

### Server Requirements
- Ubuntu 20.04+ or similar Linux distribution
- Root or sudo access
- Public IP address or domain name
- Minimum 2GB RAM, 2 CPU cores, 20GB disk space

### Software to Install
- PHP 8.2+
- MySQL 8.0+ or MariaDB 10.6+
- Redis 6+
- Nginx 1.18+
- Composer
- Node.js 18+
- Git

---

## Method 1: Automated Production Server Setup

### Step 1: Run Production Server Setup Script

**On your production server** (as root or with sudo):

```bash
#!/bin/bash
# Production Server Setup Script

echo "=== PRODUCTION SERVER SETUP ==="

# Update system
apt update && apt upgrade -y

# Install required packages
apt install -y curl wget unzip software-properties-common

# Add PHP repository
add-apt-repository ppa:ondrej/php -y
apt update

# Install PHP 8.2 and extensions
apt install -y php8.2-fpm php8.2-cli php8.2-mysql php8.2-xml \
    php8.2-mbstring php8.2-zip php8.2-curl php8.2-bcmath \
    php8.2-gd php8.2-intl php8.2-redis php8.2-mcrypt \
    php8.2-json php8.2-tokenizer

# Install MySQL
apt install -y mysql-server

# Secure MySQL installation
mysql_secure_installation

# Install Redis
apt install -y redis-server

# Install Nginx
apt install -y nginx

# Install Composer
curl -sS https://getcomposer.org/installer | php
mv composer.phar /usr/local/bin/composer
chmod +x /usr/local/bin/composer

# Install Node.js 18
curl -fsSL https://deb.nodesource.com/setup_18.x | bash -
apt install -y nodejs

# Install Git
apt install -y git

# Install Supervisor for queue workers
apt install -y supervisor

# Install Certbot for SSL
apt install -y certbot python3-certbot-nginx

# Create deploy user
useradd -m -s /bin/bash deploy
usermod -aG www-data deploy

# Create application directories
mkdir -p /var/www/production
mkdir -p /var/backups/laravel
chown -R deploy:deploy /var/www/production /var/backups/laravel

echo "=== SERVER SETUP COMPLETE ==="
echo "Next steps:"
echo "1. Set up SSH keys for deploy user"
echo "2. Configure MySQL database"
echo "3. Configure Nginx virtual host"
echo "4. Deploy application"
```

Save as `setup-production-server.sh` and run:
```bash
chmod +x setup-production-server.sh
sudo ./setup-production-server.sh
```

---

## Method 2: Manual Production Server Setup

### Step 1: Create Deploy User and Directories

```bash
# Create deploy user
sudo useradd -m -s /bin/bash deploy
sudo usermod -aG www-data deploy

# Create directories
sudo mkdir -p /var/www/production
sudo mkdir -p /var/backups/laravel
sudo chown -R deploy:deploy /var/www/production /var/backups/laravel
```

### Step 2: Set up SSH Keys

**On your local machine**:
```bash
# Generate SSH key (if you don't have one)
ssh-keygen -t rsa -b 4096 -C "your_email@example.com"

# Copy public key to production server
ssh-copy-id deploy@YOUR_PRODUCTION_SERVER_IP
```

**Or manually**:
```bash
# Copy ~/.ssh/id_rsa.pub to server's ~/.ssh/authorized_keys
```

### Step 3: Configure MySQL Database

```bash
# Connect to MySQL
sudo mysql -u root -p

# Create database and user
CREATE DATABASE binaryecom20_production CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'binaryecom_user'@'localhost' IDENTIFIED BY 'SecureP@ssw0rd2025!';
GRANT ALL PRIVILEGES ON binaryecom20_production.* TO 'binaryecom_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

### Step 4: Configure Nginx

Create `/etc/nginx/sites-available/yourdomain.com`:

```nginx
server {
    listen 80;
    server_name yourdomain.com www.yourdomain.com;
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    server_name yourdomain.com www.yourdomain.com;
    root /var/www/production/public;
    index index.php;

    # SSL Configuration
    ssl_certificate /etc/letsencrypt/live/yourdomain.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/yourdomain.com/privkey.pem;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers HIGH:!aNULL:!MD5;
    ssl_prefer_server_ciphers on;

    # Security Headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;
    add_header Content-Security-Policy "default-src 'self'" always;

    # PHP Configuration
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
    }

    # Public directory
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # Health check endpoint
    location /health {
        access_log off;
        return 200 "healthy\n";
        add_header Content-Type text/plain;
    }

    # Deny sensitive files
    location ~ /\. {
        deny all;
    }

    location ~ /(vendor|storage|bootstrap)/ {
        deny all;
    }
}
```

Enable the site:
```bash
sudo ln -s /etc/nginx/sites-available/yourdomain.com /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

### Step 5: Set up SSL Certificate

```bash
# Obtain Let's Encrypt certificate
sudo certbot --nginx -d yourdomain.com -d www.yourdomain.com

# Test auto-renewal
sudo certbot renew --dry-run
```

---

## Method 3: Deploy Application to Production

### Option A: Via GitHub Actions (Recommended)

**Step 1: Configure GitHub Secrets**

In your GitHub repository:
1. Go to Settings → Secrets and variables → Actions
2. Click "New repository secret"
3. Add these secrets:

```
PRODUCTION_HOST=yourdomain.com
PRODUCTION_USER=deploy
PRODUCTION_SSH_KEY=-----BEGIN RSA PRIVATE KEY-----
    YOUR_PRIVATE_KEY_HERE
    -----END RSA PRIVATE KEY-----
PRODUCTION_PORT=22
SLACK_WEBHOOK=https://hooks.slack.com/services/YOUR/SLACK/WEBHOOK
NOTIFICATION_EMAIL=admin@yourdomain.com
```

**Step 2: Push to Master**

```bash
# On your local machine
git add .
git commit -m "Deploy security fixes to production

- 37 security vulnerabilities fixed
- 99 security tests implemented
- Production environment configured
- CI/CD pipeline ready

🚀 Ready for production deployment"
git push origin master
```

**Step 3: Monitor Deployment**

1. Go to GitHub repository → Actions tab
2. Watch "CI/CD Pipeline" workflow
3. All stages must turn green ✅

---

### Option B: Manual Deployment via SSH

**Step 1: Connect to Production Server**

```bash
# Login as deploy user
ssh deploy@YOUR_PRODUCTION_SERVER_IP
```

**Step 2: Deploy Application**

```bash
# Navigate to production directory
cd /var/www/production

# If repository not cloned yet:
git clone https://github.com/YOUR_USERNAME/YOUR_REPO.git .
git checkout master

# Copy production environment file
cp .env.production .env

# Run deployment script
./scripts/deploy.sh production master

# Or manually:
# git pull origin master
# composer install --no-dev --optimize-autoloader
# php artisan migrate --force
# php artisan config:cache
# php artisan route:cache
# php artisan view:cache
# sudo systemctl reload php8.2-fpm
# sudo systemctl reload nginx
```

**Step 3: Set Permissions**

```bash
# Set ownership
sudo chown -R www-data:www-data /var/www/production

# Set directory permissions
sudo find /var/www/production -type d -exec chmod 755 {} \;

# Set file permissions
sudo find /var/www/production -type f -exec chmod 644 {} \;

# Make storage writable
sudo chmod -R 775 /var/www/production/storage
sudo chmod -R 775 /var/www/production/bootstrap/cache
```

---

### Option C: Via SCP/Rsync

**Step 1: Create Deployment Package**

On your local machine:
```bash
# Create deployment archive
tar -czf deployment-$(date +%Y%m%d-%H%M%S).tar.gz \
    --exclude='.git' \
    --exclude='node_modules' \
    --exclude='.env' \
    --exclude='storage/logs/*' \
    --exclude='storage/framework/cache/*' \
    --exclude='storage/framework/sessions/*' \
    --exclude='storage/framework/views/*' \
    .

# Copy to production server
scp deployment-*.tar.gz deploy@YOUR_PRODUCTION_SERVER_IP:/var/www/production/
```

**Step 2: Deploy on Server**

```bash
# SSH to production
ssh deploy@YOUR_PRODUCTION_SERVER_IP

# Extract and deploy
cd /var/www/production
tar -xzf deployment-*.tar.gz --strip-components=1
cp .env.production .env
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
sudo systemctl reload php8.2-fpm
sudo systemctl reload nginx

# Clean up
rm deployment-*.tar.gz
```

---

## Step 4: Verify Production Deployment

### Run Health Checks

```bash
# Check application health
curl https://yourdomain.com/health
# Expected: {"status":"healthy",...}

curl https://yourdomain.com/api/health
# Expected: Detailed health information

curl https://yourdomain.com/api/health/detailed
# Expected: Full system information

curl https://yourdomain.com/api/security-test
# Expected: Security features test results
```

### Check Service Status

```bash
# Check Nginx
sudo systemctl status nginx

# Check PHP-FPM
sudo systemctl status php8.2-fpm

# Check MySQL
sudo systemctl status mysql

# Check Redis
sudo systemctl status redis
```

### Test Key Endpoints

```bash
# Test homepage
curl -I https://yourdomain.com/

# Test login page
curl -I https://yourdomain.com/login

# Test admin panel
curl -I https://yourdomain.com/admin

# Test API
curl -I https://yourdomain.com/api/user
```

---

## Step 5: Post-Deployment Tasks

### Set up Monitoring

```bash
# Install logrotate
sudo nano /etc/logrotate.d/laravel

# Add:
/var/www/production/storage/logs/*.log {
    daily
    missingok
    rotate 14
    compress
    notifempty
    create 0644 www-data www-data
    postrotate
        systemctl reload php8.2-fpm
    endscript
}
```

### Configure External Monitoring

**UptimeRobot**:
1. Create account at uptimerobot.com
2. Add monitors:
   - https://yourdomain.com/health
   - https://yourdomain.com/api/health

**Pingdom**:
1. Create account at pingdom.com
2. Add HTTP checks for health endpoints

---

## Emergency Rollback

### Automatic Rollback
- Triggers if health checks fail
- Restores previous version automatically

### Manual Rollback

**Via GitHub Actions**:
1. GitHub → Actions → Rollback Deployment
2. Enter backup version (YYYYMMDD_HHMMSS)
3. Select environment: production
4. Run workflow

**Via CLI**:
```bash
# List backups
ls -lt /var/backups/laravel/*.tar.gz | head -5

# Rollback
./scripts/deploy.sh rollback 20241219_143000
```

---

## Troubleshooting

### Database Connection Failed

```bash
# Test MySQL connection
mysql -u binaryecom_user -p binaryecom20_production

# Check MySQL status
sudo systemctl status mysql

# Check .env database settings
cat .env | grep DB_
```

### Permission Errors

```bash
# Fix ownership
sudo chown -R www-data:www-data /var/www/production

# Fix permissions
sudo chmod -R 755 /var/www/production
sudo chmod -R 775 /var/www/production/storage
sudo chmod -R 775 /var/www/production/bootstrap/cache
```

### Nginx 502 Bad Gateway

```bash
# Check PHP-FPM status
sudo systemctl status php8.2-fpm

# Check Nginx error log
sudo tail -f /var/log/nginx/error.log

# Check PHP-FPM error log
sudo tail -f /var/log/php8.2-fpm.log
```

### Application Errors

```bash
# Check Laravel log
tail -f storage/logs/laravel.log

# Clear caches
php artisan cache:clear
php artisan config:clear
php artisan view:clear
```

---

## Production Deployment Checklist

- [ ] Production server set up (Ubuntu 20.04+)
- [ ] PHP 8.2+ installed with all extensions
- [ ] MySQL 8.0+ configured with database
- [ ] Redis installed and configured
- [ ] Nginx installed and configured
- [ ] SSL certificate obtained (Let's Encrypt)
- [ ] Deploy user created with SSH access
- [ ] Application directories created with correct permissions
- [ ] GitHub Secrets configured (if using CI/CD)
- [ ] Application deployed
- [ ] Health checks passing
- [ ] External monitoring configured
- [ ] Log rotation configured
- [ ] Backup strategy in place

---

## Support Contacts

- **DevOps Team**: devops@yourdomain.com
- **Technical Lead**: techlead@yourdomain.com
- **Emergency**: +1-555-0123

---

**IMPORTANT**: You need to have a **real production server** with public IP/domain to deploy. The current environment is for development only.

---

*Generated: December 19, 2025*
