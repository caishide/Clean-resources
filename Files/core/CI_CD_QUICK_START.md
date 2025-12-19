# CI/CD Pipeline Quick Start Guide

## Overview

This document provides a quick start guide to set up and use the CI/CD pipeline for the Laravel application.

## 🚀 Quick Setup (5 Minutes)

### Step 1: Configure GitHub Secrets

Add these secrets to your GitHub repository:

**Production Environment**:
```bash
PRODUCTION_HOST=yourdomain.com
PRODUCTION_USER=deploy
PRODUCTION_SSH_KEY=your-ssh-private-key
PRODUCTION_PORT=22
SLACK_WEBHOOK=https://hooks.slack.com/...
```

**Staging Environment** (optional):
```bash
STAGING_HOST=staging.yourdomain.com
STAGING_USER=deploy
STAGING_SSH_KEY=your-ssh-private-key
STAGING_PORT=22
```

### Step 2: Set Up Production Server

Run this script on your production server:

```bash
#!/bin/bash
# Setup production server

# Create deployment user
sudo useradd -m -s /bin/bash deploy
sudo usermod -aG www-data deploy

# Create directories
sudo mkdir -p /var/www/production
sudo mkdir -p /var/backups/laravel
sudo chown -R deploy:deploy /var/www/production /var/backups/laravel

# Install dependencies
sudo apt update
sudo apt install -y php8.2-fpm php8.2-cli php8.2-mysql php8.2-xml php8.2-mbstring \
    php8.2-zip php8.2-curl php8.2-bcmath php8.2-gd php8.2-intl php8.2-redis \
    mysql-server redis-server nginx git curl

# Setup SSH for deploy user
mkdir -p ~/.ssh
cat > ~/.ssh/authorized_keys << 'EOF'
# Paste your public SSH key here
EOF
chmod 600 ~/.ssh/authorized_keys
chmod 700 ~/.ssh

echo "Server setup complete! Now add your SSH key to ~/.ssh/authorized_keys"
```

### Step 3: Deploy

The CI/CD pipeline will automatically:

1. Run tests on every push
2. Deploy to production on `master` branch
3. Send Slack notifications
4. Verify deployment with health checks

## 📋 Manual Deployment

### Deploy to Production

```bash
# Clone repository
git clone https://github.com/your-org/your-repo.git
cd your-repo

# Run deployment script
./scripts/deploy.sh production master
```

### Check Deployment Status

```bash
# Check health
curl https://yourdomain.com/api/health

# Check logs
tail -f storage/logs/laravel.log
```

### Rollback Deployment

```bash
# List available backups
ls -lt /var/backups/laravel/*.tar.gz | head -5

# Rollback to specific version
./scripts/deploy.sh rollback 20231219_143000
```

## 🔧 Common Tasks

### Update Environment Variables

```bash
# Edit .env file
nano /var/www/production/.env

# Clear and regenerate caches
cd /var/www/production
php artisan config:clear
php artisan config:cache
```

### Run Database Migrations

```bash
cd /var/www/production
php artisan migrate --force
```

### Clear Caches

```bash
cd /var/www/production
php artisan cache:clear
php artisan config:clear
php artisan view:clear
php artisan route:clear
```

### Monitor Logs

```bash
# Application logs
tail -f storage/logs/laravel.log

# Nginx logs
sudo tail -f /var/log/nginx/error.log

# PHP-FPM logs
sudo tail -f /var/log/php8.2-fpm.log
```

## 🔍 Health Checks

### Health Check Endpoints

| Endpoint | Description |
|----------|-------------|
| `/api/ping` | Simple ping test |
| `/api/health` | Basic health status |
| `/api/health/detailed` | Detailed information |

### Example Response

```json
{
  "status": "healthy",
  "timestamp": "2024-12-19T15:30:00.000000Z",
  "environment": "production",
  "checks": {
    "database": {
      "status": "ok",
      "message": "Database connection successful"
    },
    "cache": {
      "status": "ok",
      "message": "Cache is working correctly"
    }
  },
  "summary": {
    "total": 5,
    "passed": 5,
    "warnings": 0,
    "failed": 0
  }
}
```

## 🚨 Troubleshooting

### Deployment Failed

```bash
# Check deployment logs
cat /var/log/nginx/error.log

# Check service status
sudo systemctl status nginx
sudo systemctl status php8.2-fpm

# Restart services
sudo systemctl restart nginx
sudo systemctl restart php8.2-fpm
```

### Database Connection Failed

```bash
# Test database connection
php artisan tinker
DB::connection()->getPdo();

# Check MySQL status
sudo systemctl status mysql
```

### High Memory Usage

```bash
# Check memory usage
free -h
df -h

# Clear caches
php artisan cache:clear
php artisan config:clear
```

## 📞 Getting Help

### Slack Notifications

All deployments are notified via Slack:
- ✅ Deployment started
- ✅ Deployment successful
- ❌ Deployment failed
- ⚠️ Rollback performed

### Log Locations

| Service | Log Location |
|---------|--------------|
| Application | `storage/logs/laravel.log` |
| Nginx | `/var/log/nginx/error.log` |
| PHP-FPM | `/var/log/php8.2-fpm.log` |
| MySQL | `/var/log/mysql/error.log` |

### Emergency Contacts

- **DevOps Team**: devops@yourdomain.com
- **Emergency**: +1-555-0123

## 📚 Additional Resources

- **Full CI/CD Guide**: `CI_CD_DEPLOYMENT_GUIDE.md`
- **Security Documentation**: `COMPREHENSIVE_SECURITY_REMEDIATION_REPORT.md`
- **Laravel Docs**: https://laravel.com/docs
- **GitHub Actions**: https://docs.github.com/en/actions

---

## ✅ Pre-Deployment Checklist

Before deploying to production, verify:

- [ ] GitHub secrets configured
- [ ] Production server set up
- [ ] Database created and configured
- [ ] SSL certificate installed
- [ ] Environment variables configured
- [ ] Health check endpoint accessible
- [ ] Backup strategy in place
- [ ] Monitoring configured
- [ ] Team notified

## 🎯 Next Steps

1. ✅ Set up CI/CD pipeline
2. ✅ Configure health monitoring
3. ✅ Set up log aggregation
4. ✅ Configure alerts
5. ✅ Document deployment procedures
6. ✅ Train team on deployment process

---

**Need Help?** Check the full `CI_CD_DEPLOYMENT_GUIDE.md` or contact the DevOps team.
