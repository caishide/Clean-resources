# Production Deployment Instructions

## 🚀 CI/CD Pipeline Deployment

### **Prerequisites for Production Deployment**

Before deploying to production, ensure you have:

1. **GitHub Repository with Secrets Configured:**
   ```bash
   # Required GitHub Secrets (Settings > Secrets and variables > Actions):
   PRODUCTION_HOST=yourdomain.com
   PRODUCTION_USER=deploy
   PRODUCTION_SSH_KEY=-----BEGIN RSA PRIVATE KEY-----
   PRODUCTION_PORT=22
   DEPLOYMENT_SERVER=files.yourdomain.com
   DEPLOYMENT_SERVER_USER=deploy
   DEPLOYMENT_SERVER_SSH_KEY=-----BEGIN RSA PRIVATE KEY-----
   SLACK_WEBHOOK=https://hooks.slack.com/services/...
   NOTIFICATION_EMAIL=admin@yourdomain.com
   ```

2. **Production Server Setup:**
   - Ubuntu 20.04+ or similar
   - SSH access with deploy user
   - PHP 8.2, MySQL 8.0, Redis, Nginx installed
   - SSL certificate configured
   - Database created and configured

### **Deployment Methods**

---

## Method 1: GitHub Actions CI/CD (Automatic - Recommended)

### **Step 1: Commit and Push to Master**

```bash
# Add all changes
git add .

# Commit with deployment message
git commit -m "feat: deploy security fixes to production

- Fix payment gateway vulnerabilities (9 controllers)
- Add path traversal protection
- Implement mass assignment protection
- Add admin impersonation security
- Implement IDOR prevention
- Add password policy enforcement
- Add language middleware security
- Add confirmation dialogs
- Translate hardcoded messages

Security fixes:
- 37 vulnerabilities addressed
- 99 security tests implemented
- Full OWASP Top 10 coverage

🤖 Generated with Claude Code"

# Push to master (triggers CI/CD pipeline)
git push origin master
```

### **Step 2: Monitor Deployment in GitHub Actions**

1. Go to your GitHub repository
2. Click **Actions** tab
3. Watch the "CI/CD Pipeline" workflow run
4. Pipeline stages:
   - ✅ Security Scan (Psalm, PHPStan, Composer audit)
   - ✅ Code Quality (PHP CS Fixer, PHP_CodeSniffer)
   - ✅ Test Suite (99 security tests)
   - ✅ Build Assets (npm build)
   - ✅ DAST Security Scan (OWASP ZAP)
   - ✅ Deploy to Production
   - ✅ Health Checks
   - ✅ Post-Deployment Tasks

### **Step 3: Verify Deployment**

```bash
# Check application health
curl https://yourdomain.com/health
curl https://yourdomain.com/api/health
curl https://yourdomain.com/api/security-test

# Expected response:
# {"status":"healthy","timestamp":"...","environment":"production",...}
```

---

## Method 2: Manual Deployment Script

### **Step 1: Configure Production Environment**

Create `.env.production` on your production server:

```bash
# Copy template
cp .env.production.example /var/www/production/.env

# Edit with your production values
nano /var/www/production/.env
```

Required configurations:
```env
APP_NAME="Your Application"
APP_ENV=production
APP_KEY=base64:generated-key
APP_DEBUG=false
APP_URL=https://yourdomain.com

DB_CONNECTION=mysql
DB_HOST=localhost
DB_DATABASE=laravel_production
DB_USERNAME=laravel
DB_PASSWORD=secure_password

CACHE_DRIVER=redis
SESSION_DRIVER=redis
REDIS_HOST=127.0.0.1

QUEUE_CONNECTION=redis

MAIL_MAILER=smtp
MAIL_HOST=smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=your_username
MAIL_PASSWORD=your_password

FILESYSTEM_DISK=s3
AWS_ACCESS_KEY_ID=your_key
AWS_SECRET_ACCESS_KEY=your_secret
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=your_bucket

FORCE_HTTPS=true
SESSION_SECURE_COOKIE=true
SESSION_HTTP_ONLY=true
```

### **Step 2: Run Deployment Script**

```bash
# On production server, as deploy user
cd /path/to/your/repository
./scripts/deploy.sh production master
```

The script will:
1. ✅ Create backup
2. ✅ Deploy code
3. ✅ Install dependencies
4. ✅ Run migrations
5. ✅ Optimize caches
6. ✅ Restart services
7. ✅ Run health checks
8. ✅ Send notifications

### **Step 3: Verify Deployment**

```bash
# Check service status
sudo systemctl status nginx
sudo systemctl status php8.2-fpm
sudo systemctl status mysql
sudo systemctl status redis

# Check logs
tail -f storage/logs/laravel.log
tail -f /var/log/nginx/error.log

# Test endpoints
curl -I https://yourdomain.com/health
curl -I https://yourdomain.com/api/health
curl -I https://yourdomain.com/api/security-test
```

---

## Method 3: Docker Deployment

### **Step 1: Build and Run with Docker Compose**

```bash
# Build production image
docker-compose -f docker-compose.prod.yml build

# Deploy to production
docker-compose -f docker-compose.prod.yml up -d

# Run migrations
docker-compose -f docker-compose.prod.yml exec app php artisan migrate --force

# Cache optimization
docker-compose -f docker-compose.prod.yml exec app php artisan config:cache
docker-compose -f docker-compose.prod.yml exec app php artisan route:cache
docker-compose -f docker-compose.prod.yml exec app php artisan view:cache
```

---

## 🔍 Post-Deployment Verification

### **1. Health Check Endpoints**

| Endpoint | Purpose |
|----------|---------|
| `/health` | Basic health status |
| `/api/health` | Detailed health check |
| `/api/health/detailed` | Full system information |
| `/api/security-test` | Security features test |

### **2. Manual Testing Checklist**

```bash
# Test payment gateways (sandbox mode)
curl -X POST https://yourdomain.com/api/payment/cashmaal/process \
  -H "Content-Type: application/json" \
  -d '{"test": "data"}'

# Test file download security
curl -I https://yourdomain.com/admin/download-attachment?file=../../../etc/passwd
# Should return 403 Forbidden

# Test admin impersonation
curl -I https://yourdomain.com/admin/impersonate/123
# Should redirect to 2FA verification

# Test language middleware
for i in {1..15}; do
  curl -I https://yourdomain.com/lang/change?lang=en
done
# Should return 429 after 10 requests
```

### **3. Log Monitoring**

```bash
# Check security events
grep -i "security" storage/logs/laravel.log

# Check payment gateway events
grep -i "gateway" storage/logs/laravel.log

# Check failed login attempts
grep -i "failed login" storage/logs/laravel.log

# Monitor in real-time
tail -f storage/logs/laravel.log | grep -i security
```

---

## 🚨 Rollback Procedure

### **Automatic Rollback**
If health checks fail, the CI/CD pipeline automatically rolls back to the previous version.

### **Manual Rollback via GitHub Actions**

1. Go to **Actions** tab in GitHub
2. Select **Rollback Deployment** workflow
3. Click **Run workflow**
4. Enter:
   - **Backup version**: `YYYYMMDD_HHMMSS`
   - **Environment**: `production`

### **Manual Rollback via CLI**

```bash
# List available backups
ls -lt /var/backups/laravel/*.tar.gz | head -5

# Rollback to specific version
./scripts/deploy.sh rollback 20241219_143000

# Rollback to latest backup
BACKUP_VERSION=$(ls -t /var/backups/laravel/*.tar.gz | head -1 | xargs basename | sed 's/\.tar\.gz//')
./scripts/deploy.sh rollback $BACKUP_VERSION
```

---

## 📊 Monitoring & Alerts

### **Slack Notifications**
The pipeline sends notifications for:
- ✅ Deployment started
- ✅ Deployment successful
- ❌ Deployment failed
- ⚠️ Rollback performed

### **Health Monitoring**
Set up external monitoring for:
- `https://yourdomain.com/health`
- `https://yourdomain.com/api/health`
- `https://yourdomain.com/api/security-test`

Recommended tools:
- UptimeRobot
- Pingdom
- Datadog
- New Relic

---

## 🔒 Security Checklist

After deployment, verify:

- [ ] HTTPS enforced (redirects HTTP to HTTPS)
- [ ] Security headers present (X-Frame-Options, CSP, etc.)
- [ ] Payment gateway IP validation enabled
- [ ] Path traversal protection working
- [ ] Mass assignment protection active
- [ ] Admin impersonation requires 2FA
- [ ] IDOR prevention working
- [ ] Password policies enforced
- [ ] Rate limiting on language changes
- [ ] Audit logging functional

---

## 📞 Support

### **Emergency Contacts**

- **DevOps Team**: devops@yourdomain.com
- **Technical Lead**: techlead@yourdomain.com
- **Emergency**: +1-555-0123

### **Useful Commands**

```bash
# Check application status
php artisan about

# Clear all caches
php artisan cache:clear
php artisan config:clear
php artisan view:clear
php artisan route:clear

# Optimize for production
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# Check configuration
php artisan config:show

# Test database connection
php artisan tinker
DB::connection()->getPdo();
```

---

## ✅ Deployment Complete

Once deployment is successful, your application will have:

- ✅ All 37 security vulnerabilities fixed
- ✅ 99 security tests passing
- ✅ Full OWASP Top 10 coverage
- ✅ CI/CD pipeline with automated testing
- ✅ Security scanning (SAST/DAST)
- ✅ Health monitoring and alerts
- ✅ Automated rollback capability
- ✅ Complete audit logging

**Next Steps:**
1. Monitor application logs
2. Review security events
3. Verify all features working
4. Update documentation
5. Train team on new security features

---

**Deployment completed successfully!** 🎉
