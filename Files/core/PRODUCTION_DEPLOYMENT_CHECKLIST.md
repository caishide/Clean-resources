# 🚀 Production Deployment Checklist

## Pre-Deployment Requirements

### 1. GitHub Repository Setup

**Configure GitHub Secrets** (Repository Settings → Secrets and variables → Actions):

```
PRODUCTION_HOST=yourdomain.com
PRODUCTION_USER=deploy
PRODUCTION_SSH_KEY=-----BEGIN RSA PRIVATE KEY-----
PRODUCTION_PORT=22
DEPLOYMENT_SERVER=files.yourdomain.com
DEPLOYMENT_SERVER_USER=deploy
DEPLOYMENT_SERVER_SSH_KEY=-----BEGIN RSA PRIVATE KEY-----
SLACK_WEBHOOK=https://hooks.slack.com/services/YOUR/SLACK/WEBHOOK
NOTIFICATION_EMAIL=admin@yourdomain.com
```

### 2. Production Server Requirements

**Server Specifications:**
- Ubuntu 20.04+ or similar
- 2GB+ RAM, 2+ CPU cores
- 20GB+ disk space

**Software Installed:**
```bash
# Update system
sudo apt update && sudo apt upgrade -y

# Install PHP 8.2
sudo apt install -y php8.2-fpm php8.2-cli php8.2-mysql php8.2-xml \
    php8.2-mbstring php8.2-zip php8.2-curl php8.2-bcmath \
    php8.2-gd php8.2-intl php8.2-redis php8.2-mcrypt

# Install MySQL
sudo apt install -y mysql-server

# Install Redis
sudo apt install -y redis-server

# Install Nginx
sudo apt install -y nginx

# Install Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer

# Install Node.js 18
curl -fsSL https://deb.nodesource.com/setup_18.x | sudo -E bash -
sudo apt install -y nodejs

# Install Git
sudo apt install -y git

# Install Supervisor (for queues)
sudo apt install -y supervisor
```

**Create Deploy User:**
```bash
sudo useradd -m -s /bin/bash deploy
sudo usermod -aG www-data deploy
sudo mkdir -p /var/www/production
sudo mkdir -p /var/backups/laravel
sudo chown -R deploy:deploy /var/www/production /var/backups/laravel
```

**Setup SSH:**
```bash
# On your local machine, copy SSH key to server
ssh-copy-id deploy@yourdomain.com

# Or manually:
# Copy your public key to server's ~/.ssh/authorized_keys
```

### 3. Database Setup

```bash
# Connect to MySQL
sudo mysql -u root -p

# Create database and user
CREATE DATABASE laravel_production CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'laravel'@'localhost' IDENTIFIED BY 'secure_password_here';
GRANT ALL PRIVILEGES ON laravel_production.* TO 'laravel'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

### 4. SSL Certificate (Let's Encrypt)

```bash
# Install Certbot
sudo apt install -y certbot python3-certbot-nginx

# Obtain certificate
sudo certbot --nginx -d yourdomain.com -d www.yourdomain.com

# Auto-renewal
sudo crontab -e
# Add line:
0 12 * * * /usr/bin/certbot renew --quiet
```

### 5. Environment Configuration

**Create production .env:**
```bash
# On production server
cd /var/www/production
cp .env.production.example .env

# Edit with your values
nano .env
```

**Generate APP_KEY:**
```bash
php artisan key:generate
```

### 6. Nginx Configuration

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

    # SSL
    ssl_certificate /etc/letsencrypt/live/yourdomain.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/yourdomain.com/privkey.pem;
    ssl_protocols TLSv1.2 TLSv1.3;

    # Security Headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;
    add_header Content-Security-Policy "default-src 'self'" always;

    # PHP
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # Health check
    location /health {
        access_log off;
        return 200 "healthy\n";
        add_header Content-Type text/plain;
    }

    # Deny sensitive files
    location ~ /\. {
        deny all;
    }
}
```

Enable site:
```bash
sudo ln -s /etc/nginx/sites-available/yourdomain.com /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

---

## Deployment Methods

### Method 1: GitHub Actions (Automatic - RECOMMENDED)

**Step 1: Push to master**
```bash
git add .
git commit -m "Deploy security fixes to production"
git push origin master
```

**Step 2: Monitor in GitHub Actions**
- Go to repository Actions tab
- Watch "CI/CD Pipeline" workflow
- All stages must turn green ✅

**Step 3: Verify deployment**
```bash
curl https://yourdomain.com/health
curl https://yourdomain.com/api/health
curl https://yourdomain.com/api/security-test
```

---

### Method 2: Manual Deployment Script

**Step 1: On production server**
```bash
# Login as deploy user
ssh deploy@yourdomain.com

# Navigate to app directory
cd /var/www/production

# Pull latest code
git pull origin master

# Run deployment script
./scripts/deploy.sh production master
```

**Step 2: Verify**
```bash
# Check service status
sudo systemctl status nginx
sudo systemctl status php8.2-fpm
sudo systemctl status mysql

# Test endpoints
curl -I https://yourdomain.com/health
```

---

### Method 3: Docker Deployment

**Step 1: Build and deploy**
```bash
# On production server
cd /var/www/production

# Build containers
docker-compose -f docker-compose.prod.yml build

# Start services
docker-compose -f docker-compose.prod.yml up -d

# Run migrations
docker-compose -f docker-compose.prod.yml exec app php artisan migrate --force

# Optimize caches
docker-compose -f docker-compose.prod.yml exec app php artisan config:cache
```

---

## Post-Deployment Verification

### 1. Health Checks

```bash
# Test all endpoints
curl https://yourdomain.com/health
curl https://yourdomain.com/api/health
curl https://yourdomain.com/api/health/detailed
curl https://yourdomain.com/api/security-test

# Expected: {"status":"healthy",...}
```

### 2. Security Tests

```bash
# Test path traversal protection (should return 403)
curl -I "https://yourdomain.com/admin/download-attachment?file=../../../etc/passwd"

# Test rate limiting (after 10 requests, should get 429)
for i in {1..12}; do
  curl -I "https://yourdomain.com/lang/change?lang=en"
done

# Test admin impersonation (should redirect to 2FA)
curl -I "https://yourdomain.com/admin/impersonate/123"
```

### 3. Database Verification

```bash
# Connect to application
php artisan tinker

# Test database connection
DB::connection()->getPdo();
# Should return: PDO object

# Check migrations
DB::table('migrations')->orderBy('batch', 'desc')->limit(5)->get();
# Should show recent migrations
```

### 4. Log Monitoring

```bash
# Check application logs
tail -f /var/www/production/storage/logs/laravel.log

# Check Nginx logs
sudo tail -f /var/log/nginx/error.log

# Check PHP-FPM logs
sudo tail -f /var/log/php8.2-fpm.log

# Check security events
grep -i security /var/www/production/storage/logs/laravel.log
```

---

## Rollback Procedure

### Automatic Rollback
- Triggers if health checks fail
- Restores previous version automatically
- Sends Slack notification

### Manual Rollback (GitHub Actions)

1. Go to GitHub → Actions
2. Click "Rollback Deployment"
3. Click "Run workflow"
4. Enter:
   - Backup version: `20241219_121337`
   - Environment: `production`
5. Click "Run workflow"

### Manual Rollback (CLI)

```bash
# On production server
cd /var/www/production

# List backups
ls -lt /var/backups/laravel/*.tar.gz | head -5

# Rollback to specific version
./scripts/deploy.sh rollback 20241219_121337

# Verify rollback
curl https://yourdomain.com/health
```

---

## Monitoring Setup

### 1. External Uptime Monitoring

**UptimeRobot:**
1. Create account at uptimerobot.com
2. Add monitors:
   - https://yourdomain.com/health
   - https://yourdomain.com/api/health
   - https://yourdomain.com/api/security-test

**Pingdom:**
1. Create account at pingdom.com
2. Add HTTP checks for health endpoints

### 2. Log Monitoring

**Setup log rotation:**
```bash
sudo nano /etc/logrotate.d/laravel
```

Add:
```
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

### 3. Performance Monitoring

**New Relic (Optional):**
1. Sign up at newrelic.com
2. Install New Relic PHP agent
3. Configure in .env:
```
NEW_RELIC_LICENSE_KEY=your_license_key
NEW_RELIC_APP_NAME=Your Application
```

---

## Troubleshooting

### Deployment Failed

**Check logs:**
```bash
# GitHub Actions logs (if using CI/CD)
# Or on production server:
tail -f /var/log/nginx/error.log
tail -f storage/logs/laravel.log
```

**Common issues:**
1. **Permission errors**: Fix with `sudo chown -R deploy:deploy /var/www/production`
2. **Missing dependencies**: Run `composer install`
3. **Database errors**: Check .env database credentials
4. **Cache issues**: Run `php artisan cache:clear`

### Database Connection Failed

```bash
# Test connection
php artisan tinker
DB::connection()->getPdo();

# Check MySQL status
sudo systemctl status mysql

# Check database credentials in .env
cat .env | grep DB_
```

### High Memory Usage

```bash
# Check memory
free -h
df -h

# Clear caches
php artisan cache:clear
php artisan config:clear
php artisan view:clear

# Restart services
sudo systemctl reload php8.2-fpm
```

---

## Security Checklist

After deployment, verify:

- [ ] HTTPS enforced (HTTP redirects to HTTPS)
- [ ] Security headers present (run: curl -I https://yourdomain.com)
- [ ] APP_DEBUG=false in .env
- [ ] APP_KEY generated and set
- [ ] Database credentials secured
- [ ] File permissions correct (755 for directories, 644 for files)
- [ ] Storage directories writable (775)
- [ ] .env file not accessible via web
- [ ] Sensitive files (.env, composer.json) not in web root

---

## Emergency Contacts

- **DevOps Team**: devops@yourdomain.com
- **Technical Lead**: techlead@yourdomain.com
- **Emergency**: +1-555-0123

---

## Success Criteria

Deployment is successful when:

✅ All health check endpoints return 200 OK
✅ Security tests return expected responses
✅ All 99 security tests pass
✅ No errors in application logs
✅ Database connection working
✅ Cache functioning (Redis)
✅ File uploads working
✅ Payment gateways operational (sandbox mode)
✅ Admin panel accessible
✅ User registration/login working

---

**Deployment checklist complete! Your application is ready for production.** 🎉

---

*Last updated: December 19, 2025*
