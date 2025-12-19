# CI/CD Deployment Guide

## Table of Contents

1. [Overview](#overview)
2. [CI/CD Pipeline Architecture](#cicd-pipeline-architecture)
3. [Setup Instructions](#setup-instructions)
4. [Deployment Workflow](#deployment-workflow)
5. [Security Scanning](#security-scanning)
6. [Environment Configuration](#environment-configuration)
7. [Health Checks](#health-checks)
8. [Rollback Procedure](#rollback-procedure)
9. [Monitoring & Alerts](#monitoring--alerts)
10. [Troubleshooting](#troubleshooting)

---

## Overview

This document describes the comprehensive CI/CD (Continuous Integration/Continuous Deployment) pipeline implemented for the Laravel application. The pipeline automates the entire process from code commit to production deployment, including testing, security scanning, and deployment verification.

### Key Features

- **Automated Testing**: Unit tests, feature tests, security tests
- **Security Scanning**: SAST, DAST, dependency vulnerability scanning
- **Code Quality Checks**: Static analysis, code style enforcement
- **Multi-Environment Support**: Development, staging, production
- **Automated Rollback**: Quick rollback on deployment failure
- **Health Monitoring**: Real-time health checks and alerts
- **Docker Support**: Containerized deployment options

---

## CI/CD Pipeline Architecture

### Pipeline Stages

```mermaid
graph TD
    A[Code Commit] --> B[Security Scan]
    A --> C[Code Quality Check]
    B --> D[Unit & Feature Tests]
    C --> D
    D --> E[Build Assets]
    E --> F[DAST Security Scan]
    F --> G[Deploy to Staging]
    G --> H[Staging Tests]
    H --> I[Manual Approval]
    I --> J[Deploy to Production]
    J --> K[Health Checks]
    K --> L[Post-Deployment Tasks]
```

### Workflow Files

| File | Purpose | Trigger |
|------|---------|---------|
| `.github/workflows/ci-cd.yml` | Main CI/CD pipeline | Push, PR |
| `.github/workflows/rollback.yml` | Rollback workflow | Manual |

---

## Setup Instructions

### 1. Repository Configuration

#### Required Secrets

Configure the following secrets in your GitHub repository:

```bash
# Staging Environment
STAGING_HOST=staging.yourdomain.com
STAGING_USER=deploy
STAGING_SSH_KEY=-----BEGIN RSA PRIVATE KEY-----
STAGING_PORT=22

# Production Environment
PRODUCTION_HOST=yourdomain.com
PRODUCTION_USER=deploy
PRODUCTION_SSH_KEY=-----BEGIN RSA PRIVATE KEY-----
PRODUCTION_PORT=22
DEPLOYMENT_SERVER=files.yourdomain.com

# Notifications
SLACK_WEBHOOK=https://hooks.slack.com/services/...
NOTIFICATION_EMAIL=admin@yourdomain.com

# Deployment Server
DEPLOYMENT_SERVER_HOST=files.yourdomain.com
DEPLOYMENT_SERVER_USER=deploy
DEPLOYMENT_SERVER_SSH_KEY=-----BEGIN RSA PRIVATE KEY-----
```

#### Repository Settings

1. **Branch Protection Rules**:
   - Enable "Require status checks to pass before merging"
   - Enable "Require branches to be up to date before merging"
   - Enable "Require review from Code Owners"

2. **Environments**:
   - Create `staging` environment with required reviewers
   - Create `production` environment with required reviewers and wait timer

### 2. Server Setup

#### Staging Server

```bash
# Create deployment user
sudo useradd -m -s /bin/bash deploy
sudo usermod -aG www-data deploy

# Setup SSH key
sudo mkdir -p /home/deploy/.ssh
sudo cp ~/.ssh/id_rsa.pub /home/deploy/.ssh/authorized_keys
sudo chown -R deploy:deploy /home/deploy/.ssh
sudo chmod 700 /home/deploy/.ssh
sudo chmod 600 /home/deploy/.ssh/authorized_keys

# Create application directory
sudo mkdir -p /var/www/staging
sudo chown deploy:deploy /var/www/staging

# Create backup directory
sudo mkdir -p /var/backups/laravel
sudo chown deploy:deploy /var/backups/laravel

# Install required packages
sudo apt update
sudo apt install -y php8.2-fpm php8.2-cli php8.2-mysql php8.2-xml \
    php8.2-mbstring php8.2-zip php8.2-curl php8.2-bcmath \
    php8.2-gd php8.2-intl php8.2-redis \
    mysql-server redis-server nginx \
    supervisor git curl unzip
```

#### Production Server

Repeat the same steps as staging, but use `/var/www/production` as the application directory.

### 3. Database Setup

```bash
# MySQL setup
mysql -u root -p

CREATE DATABASE laravel_production;
CREATE USER 'laravel'@'localhost' IDENTIFIED BY 'secure_password';
GRANT ALL PRIVILEGES ON laravel_production.* TO 'laravel'@'localhost';
FLUSH PRIVILEGES;
```

### 4. SSL Certificate Setup

```bash
# Using Let's Encrypt
sudo apt install -y certbot python3-certbot-nginx

sudo certbot --nginx -d yourdomain.com
sudo certbot --nginx -d api.yourdomain.com
sudo certbot --nginx -d staging.yourdomain.com

# Auto-renewal
sudo crontab -e
# Add: 0 12 * * * /usr/bin/certbot renew --quiet
```

### 5. CI/CD Configuration

#### Make Scripts Executable

```bash
chmod +x scripts/deploy.sh
chmod +x scripts/health-check.php
```

#### Configure Nginx

Create `/etc/nginx/sites-available/yourdomain.com`:

```nginx
server {
    listen 80;
    listen [::]:80;
    server_name yourdomain.com;
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name yourdomain.com;

    root /var/www/production/public;
    index index.php;

    # SSL Configuration
    ssl_certificate /etc/letsencrypt/live/yourdomain.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/yourdomain.com/privkey.pem;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers HIGH:!aNULL:!MD5;
    ssl_prefer_server_ciphers on;
    ssl_session_cache shared:SSL:10m;
    ssl_session_timeout 10m;

    # Security Headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;
    add_header Content-Security-Policy "default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline'; img-src 'self' data: https:; font-src 'self' data:;" always;

    # PHP-FPM Configuration
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

    # Static files
    location ~* \.(css|js|png|jpg|jpeg|gif|ico|svg|woff|woff2|ttf|eot)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
        try_files $uri =404;
    }

    # Health check endpoint
    location /health {
        access_log off;
        return 200 "healthy\n";
        add_header Content-Type text/plain;
    }

    # Security - Deny access to sensitive files
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

---

## Deployment Workflow

### Automated Deployment

The CI/CD pipeline automatically triggers on:

1. **Push to `develop` branch**:
   - Runs full test suite
   - Builds assets
   - Deploys to staging
   - Runs smoke tests
   - Sends notification

2. **Push to `master` branch**:
   - Runs all checks
   - Deploys to production
   - Runs health checks
   - Sends notification

### Manual Deployment

To deploy manually:

```bash
# Using deployment script
./scripts/deploy.sh production master

# Rollback to specific version
./scripts/deploy.sh rollback 20231219_143000
```

### Deployment Steps

1. **Pre-Deployment**:
   - Create backup
   - Verify environment variables
   - Check disk space

2. **Code Deployment**:
   - Clone/update repository
   - Install dependencies
   - Build assets

3. **Database Migration**:
   - Run migrations
   - Seed data (if needed)

4. **Cache Optimization**:
   - Clear old caches
   - Generate optimized caches

5. **Service Restart**:
   - Reload PHP-FPM
   - Reload Nginx
   - Restart queue workers

6. **Post-Deployment**:
   - Run health checks
   - Warm caches
   - Send notifications

---

## Security Scanning

### 1. Static Application Security Testing (SAST)

**Tools**:
- **Psalm**: Static analysis
- **PHPStan**: Static analysis
- **PHP CS Fixer**: Code style
- **PHP_CodeSniffer**: Code standards

**Configuration**: `.github/workflows/ci-cd.yml`

**Run Manually**:
```bash
# Psalm
./vendor/bin/psalm

# PHPStan
./vendor/bin/phpstan analyse

# Code style check
./vendor/bin/php-cs-fixer fix --dry-run --diff

# Security audit
composer audit
```

### 2. Dependency Vulnerability Scanning

```bash
# Composer audit
composer audit --format=json

# npm audit
npm audit --audit-level high
```

### 3. Dynamic Application Security Testing (DAST)

**Tool**: OWASP ZAP Baseline Scan

**Configuration**: `.zap/rules.tsv`

**Run in CI/CD**:
- Automatically runs after staging deployment
- Scans all endpoints for common vulnerabilities

**Run Manually**:
```bash
# Start application
docker-compose up -d

# Run ZAP scan
docker run -t owasp/zap2docker-stable zap-baseline.py \
  -t http://localhost:8000 \
  -r zap-report.html
```

### 4. Security Checks in Pipeline

1. **Pre-Deployment**:
   - Dependency vulnerability scan
   - Static code analysis
   - Secret detection

2. **Post-Deployment**:
   - DAST scan (staging only)
   - SSL/TLS configuration check
   - Security headers verification

---

## Environment Configuration

### 1. Environment Files

| File | Purpose |
|------|---------|
| `.env` | Local development |
| `.env.staging` | Staging environment |
| `.env.production` | Production environment |
| `.env.example` | Template for new environments |

### 2. Required Environment Variables

#### Production Variables

```env
APP_NAME="Your Application"
APP_ENV=production
APP_KEY=base64:...
APP_DEBUG=false
APP_URL=https://yourdomain.com

DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=laravel
DB_USERNAME=laravel
DB_PASSWORD=secure_password

CACHE_DRIVER=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis

REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

FORCE_HTTPS=true
SESSION_SECURE_COOKIE=true
SESSION_HTTP_ONLY=true
```

### 3. Configuration Management

#### Using GitHub Secrets

All sensitive data should be stored in GitHub Secrets:

```yaml
env:
  PRODUCTION_HOST: ${{ secrets.PRODUCTION_HOST }}
  PRODUCTION_USER: ${{ secrets.PRODUCTION_USER }}
  PRODUCTION_SSH_KEY: ${{ secrets.PRODUCTION_SSH_KEY }}
```

#### Using Server-Side Configuration

For server-specific settings:

```bash
# Store in /var/www/production/.env
# (never commit to repository)
```

---

## Health Checks

### 1. Health Check Endpoints

| Endpoint | Description |
|----------|-------------|
| `/api/ping` | Simple ping test |
| `/api/health` | Basic health status |
| `/api/health/detailed` | Detailed health information |

### 2. Health Check Implementation

**Controller**: `app/Http/Controllers/HealthController.php`

**Features**:
- Database connectivity
- Cache functionality
- Storage accessibility
- Security configuration
- Queue workers status
- SSL/TLS configuration

### 3. Automated Health Checks

**In CI/CD Pipeline**:

```yaml
- name: Verify deployment
  run: |
    sleep 10
    curl -f https://yourdomain.com/health || exit 1
    curl -f https://yourdomain.com/api/health || exit 1
```

**External Monitoring**:

Add to monitoring system (e.g., UptimeRobot, Pingdom):

```
https://yourdomain.com/api/health
```

### 4. Health Check Script

**Location**: `scripts/health-check.php`

**Usage**:
```bash
# JSON format
php scripts/health-check.php

# Text format
php scripts/health-check.php?format=text

# HTML format
php scripts/health-check.php?format=html
```

---

## Rollback Procedure

### 1. Automatic Rollback

Triggers automatically when:
- Health checks fail
- Deployment verification fails

### 2. Manual Rollback

#### Using GitHub Actions

1. Go to **Actions** tab
2. Select **Rollback Deployment** workflow
3. Click **Run workflow**
4. Enter:
   - **Backup version**: `YYYYMMDD_HHMMSS`
   - **Environment**: `staging` or `production`

#### Using CLI

```bash
# Rollback to specific backup
./scripts/deploy.sh rollback 20231219_143000

# Rollback latest deployment
BACKUP_VERSION=$(ls -t /var/backups/laravel/*.tar.gz | head -1 | xargs basename | sed 's/\.tar\.gz//')
./scripts/deploy.sh rollback $BACKUP_VERSION
```

### 3. Rollback Steps

1. Create emergency backup
2. Stop services
3. Extract backup
4. Set permissions
5. Start services
6. Verify health

### 4. Rollback Notification

Slack notification is sent on:
- Rollback start
- Rollback completion
- Rollback failure

---

## Monitoring & Alerts

### 1. Health Monitoring

**Continuous Checks**:
- Application health endpoint
- Database connectivity
- Cache performance
- Queue workers status

**Monitoring Tools**:
- GitHub Actions (CI/CD)
- External uptime monitors
- Custom health checks

### 2. Log Monitoring

**Log Locations**:
- Application: `storage/logs/laravel.log`
- Nginx: `/var/log/nginx/error.log`
- PHP-FPM: `/var/log/php8.2-fpm.log`
- MySQL: `/var/log/mysql/error.log`

**Log Aggregation**:
```bash
# Tail logs in real-time
tail -f storage/logs/laravel.log

# Search for errors
grep -i error storage/logs/laravel.log

# Monitor security events
grep -i security storage/logs/laravel.log
```

### 3. Performance Monitoring

**Metrics to Monitor**:
- Response time
- Memory usage
- CPU utilization
- Disk space
- Database performance

**Tools**:
- New Relic
- Datadog
- Prometheus + Grafana
- Laravel Telescope (development)

### 4. Alert Configuration

#### Slack Alerts

Configure webhook in GitHub Secrets:

```yaml
- name: Notify success
  uses: 8398a7/action-slack@v3
  with:
    status: success
    text: 'Deployment successful'
  env:
    SLACK_WEBHOOK_URL: ${{ secrets.SLACK_WEBHOOK }}
```

#### Email Alerts

Configure SMTP in environment:

```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your_email@gmail.com
MAIL_PASSWORD=your_app_password
```

---

## Troubleshooting

### 1. Common Issues

#### Deployment Fails

**Symptoms**:
- Deployment workflow fails
- Application not accessible

**Diagnosis**:
```bash
# Check deployment logs
cat /var/log/nginx/error.log
tail -f storage/logs/laravel.log

# Check service status
sudo systemctl status nginx
sudo systemctl status php8.2-fpm
sudo systemctl status mysql
```

**Solutions**:
- Verify environment variables
- Check file permissions
- Restart services
- Check disk space

#### Database Connection Fails

**Symptoms**:
- Database errors in logs
- Application returns 500 errors

**Diagnosis**:
```bash
# Test database connection
php artisan tinker
DB::connection()->getPdo();

# Check MySQL status
sudo systemctl status mysql
```

**Solutions**:
- Verify database credentials
- Check MySQL service
- Run migrations manually

#### Cache Issues

**Symptoms**:
- Changes not reflecting
- Old configuration values

**Diagnosis**:
```bash
# Check cache status
php artisan config:show

# View cached files
ls -la bootstrap/cache/
```

**Solutions**:
```bash
# Clear all caches
php artisan cache:clear
php artisan config:clear
php artisan view:clear
php artisan route:clear

# Regenerate caches
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### 2. Debug Mode

**Enable in staging**:
```env
APP_DEBUG=true
LOG_LEVEL=debug
```

**Disable in production**:
```env
APP_DEBUG=false
LOG_LEVEL=info
```

### 3. Recovery Procedures

#### Complete Application Recovery

```bash
# 1. Rollback to last working version
./scripts/deploy.sh rollback 20231219_143000

# 2. Verify health
curl -f https://yourdomain.com/api/health

# 3. Check logs for root cause
grep -i error storage/logs/laravel.log

# 4. Fix issues
# (specific to error found)

# 5. Redeploy
./scripts/deploy.sh production master
```

#### Database Recovery

```bash
# Restore from backup
BACKUP_FILE="/var/backups/laravel/20231219_143000/database.sql"
mysql -u laravel -p laravel < $BACKUP_FILE
```

---

## Best Practices

### 1. Deployment

- ✅ Always deploy during off-peak hours
- ✅ Test in staging first
- ✅ Monitor health checks after deployment
- ✅ Keep backup of previous version
- ✅ Document changes

### 2. Security

- ✅ Keep dependencies updated
- ✅ Run security scans regularly
- ✅ Use HTTPS in all environments
- ✅ Enable security headers
- ✅ Review logs for suspicious activity

### 3. Monitoring

- ✅ Set up health check monitoring
- ✅ Monitor disk space and memory
- ✅ Set up log aggregation
- ✅ Configure alerts
- ✅ Regular backup verification

### 4. CI/CD

- ✅ All tests must pass before merge
- ✅ Use feature branches
- ✅ Require code review
- ✅ Test in staging before production
- ✅ Document deployment procedures

---

## Support & Maintenance

### Regular Tasks

**Daily**:
- Check deployment status
- Review error logs
- Monitor health checks

**Weekly**:
- Review security alerts
- Check disk space
- Verify backups

**Monthly**:
- Update dependencies
- Review security scan results
- Update documentation
- Performance review

### Contact Information

- **DevOps Team**: devops@yourdomain.com
- **Technical Lead**: techlead@yourdomain.com
- **Emergency Contact**: +1-555-0123

### Additional Resources

- [Laravel Documentation](https://laravel.com/docs)
- [GitHub Actions Documentation](https://docs.github.com/en/actions)
- [Docker Documentation](https://docs.docker.com)
- [OWASP Security Guidelines](https://owasp.org/www-project-top-ten/)

---

**Last Updated**: December 2024
**Version**: 1.0
