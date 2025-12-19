#!/bin/bash

###############################################################################
# Deployment Script for Binary Ecom Platform
#
# This script automates the deployment process including:
# - Dependency installation
# - Database migrations
# - Cache clearing and optimization
# - Permission setting
# - Health checks
###############################################################################

set -e  # Exit on any error

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Configuration
APP_ENV=${APP_ENV:-production}
APP_DEBUG=${APP_DEBUG:-false}
TIMESTAMP=$(date '+%Y-%m-%d %H:%M:%S')

echo -e "${GREEN}======================================${NC}"
echo -e "${GREEN}Binary Ecom Deployment Script${NC}"
echo -e "${GREEN}Started at: $TIMESTAMP${NC}"
echo -e "${GREEN}======================================${NC}"

# Step 1: Backup database before deployment
echo -e "\n${YELLOW}[1/10] Backing up database...${NC}"
if [ "$APP_ENV" = "production" ]; then
    php artisan backup:run --only-db --disable-notifications
    echo -e "${GREEN}✓ Database backup completed${NC}"
else
    echo -e "${YELLOW}⚠ Skipping backup (not production)${NC}"
fi

# Step 2: Update dependencies
echo -e "\n${YELLOW}[2/10] Installing dependencies...${NC}"
composer install --no-dev --optimize-autoloader
echo -e "${GREEN}✓ Dependencies installed${NC}"

# Step 3: Clear caches
echo -e "\n${YELLOW}[3/10] Clearing caches...${NC}"
php artisan config:clear
php artisan cache:clear
php artisan view:clear
php artisan route:clear
echo -e "${GREEN}✓ Caches cleared${NC}"

# Step 4: Run migrations
echo -e "\n${YELLOW}[4/10] Running database migrations...${NC}"
php artisan migrate --force
echo -e "${GREEN}✓ Migrations completed${NC}"

# Step 5: Seed database if needed
echo -e "\n${YELLOW}[5/10] Seeding database...${NC}"
if [ "$APP_ENV" = "production" ]; then
    echo -e "${YELLOW}⚠ Skipping seeding in production${NC}"
else
    php artisan db:seed --force
    echo -e "${GREEN}✓ Database seeded${NC}"
fi

# Step 6: Optimize for production
echo -e "\n${YELLOW}[6/10] Optimizing application...${NC}"
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan optimize
echo -e "${GREEN}✓ Application optimized${NC}"

# Step 7: Set permissions
echo -e "\n${YELLOW}[7/10] Setting permissions...${NC}"
chmod -R 755 .
chmod -R 777 storage
chmod -R 777 bootstrap/cache
echo -e "${GREEN}✓ Permissions set${NC}"

# Step 8: Link storage
echo -e "\n${YELLOW}[8/10] Linking storage...${NC}"
php artisan storage:link
echo -e "${GREEN}✓ Storage linked${NC}"

# Step 9: Run health checks
echo -e "\n${YELLOW}[9/10] Running health checks...${NC}"
php artisan health:check
echo -e "${GREEN}✓ Health checks passed${NC}"

# Step 10: Warm up caches
echo -e "\n${YELLOW}[10/10] Warming up caches...${NC}"
php artisan route:list
php artisan config:show
echo -e "${GREEN}✓ Caches warmed up${NC}"

# Final success message
TIMESTAMP=$(date '+%Y-%m-%d %H:%M:%S')
echo -e "\n${GREEN}======================================${NC}"
echo -e "${GREEN}Deployment completed successfully!${NC}"
echo -e "${GREEN}Completed at: $TIMESTAMP${NC}"
echo -e "${GREEN}======================================${NC}"

exit 0
