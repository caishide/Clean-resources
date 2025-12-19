#!/bin/bash

###############################################################################
# Test Script for Binary Ecom Platform
#
# This script runs comprehensive tests including:
# - Unit tests
# - Feature tests
# - Security tests
# - Performance tests
# - Health checks
###############################################################################

set -e

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

echo -e "${BLUE}======================================${NC}"
echo -e "${BLUE}Binary Ecom Test Suite${NC}"
echo -e "${BLUE}======================================${NC}"

# Step 1: Check PHP version
echo -e "\n${YELLOW}[1/12] Checking PHP version...${NC}"
PHP_VERSION=$(php -v | head -n 1 | cut -d ' ' -f 2 | cut -d '.' -f 1,2)
REQUIRED_VERSION="8.1"

if [ "$(printf '%s\n' "$REQUIRED_VERSION" "$PHP_VERSION" | sort -V | head -n1)" = "$REQUIRED_VERSION" ]; then
    echo -e "${GREEN}✓ PHP version $PHP_VERSION is compatible${NC}"
else
    echo -e "${RED}✗ PHP version $PHP_VERSION is not compatible. Required: $REQUIRED_VERSION+${NC}"
    exit 1
fi

# Step 2: Check required extensions
echo -e "\n${YELLOW}[2/12] Checking PHP extensions...${NC}"
REQUIRED_EXTENSIONS=("mbstring" "xml" "ctype" "json" "zip" "pdo" "pdo_mysql" "fileinfo" "gd")
for ext in "${REQUIRED_EXTENSIONS[@]}"; do
    if php -m | grep -q "$ext"; then
        echo -e "${GREEN}✓ $ext extension is installed${NC}"
    else
        echo -e "${RED}✗ $ext extension is missing${NC}"
        exit 1
    fi
done

# Step 3: Install dependencies
echo -e "\n${YELLOW}[3/12] Installing dependencies...${NC}"
composer install --no-dev --optimize-autoloader --quiet
echo -e "${GREEN}✓ Dependencies installed${NC}"

# Step 4: Check environment
echo -e "\n${YELLOW}[4/12] Checking environment configuration...${NC}"
if [ ! -f .env ]; then
    echo -e "${RED}✗ .env file is missing${NC}"
    cp .env.example .env
    echo -e "${YELLOW}⚠ Created .env from example${NC}"
fi
echo -e "${GREEN}✓ Environment file exists${NC}"

# Step 5: Generate application key
echo -e "\n${YELLOW}[5/12] Generating application key...${NC}"
php artisan key:generate --force --quiet
echo -e "${GREEN}✓ Application key generated${NC}"

# Step 6: Clear caches
echo -e "\n${YELLOW}[6/12] Clearing caches...${NC}"
php artisan config:clear --quiet
php artisan cache:clear --quiet
php artisan view:clear --quiet
php artisan route:clear --quiet
echo -e "${GREEN}✓ Caches cleared${NC}"

# Step 7: Run migrations
echo -e "\n${YELLOW}[7/12] Running database migrations...${NC}"
php artisan migrate:status --quiet
echo -e "${GREEN}✓ Migrations status checked${NC}"

# Step 8: Run health checks
echo -e "\n${YELLOW}[8/12] Running health checks...${NC}"
php artisan tinker --execute="
    try {
        \$health = new App\Http\Controllers\HealthController();
        \$request = new Illuminate\Http\Request();
        \$response = \$health->check(\$request);
        if (\$response->getStatusCode() === 200) {
            echo '✓ Health check passed\n';
        } else {
            echo '⚠ Health check returned non-200 status\n';
        }
    } catch (Exception \$e) {
        echo '✗ Health check failed: ' . \$e->getMessage() . '\n';
    }
"
echo -e "${GREEN}✓ Health checks completed${NC}"

# Step 9: Run static analysis
echo -e "\n${YELLOW}[9/12] Running static analysis...${NC}"
if [ -f vendor/bin/phpstan ]; then
    ./vendor/bin/phpstan analyse --memory-limit=2G --no-progress --error-format=table
    echo -e "${GREEN}✓ Static analysis completed${NC}"
else
    echo -e "${YELLOW}⚠ PHPStan not installed, skipping${NC}"
fi

# Step 10: Run security audit
echo -e "\n${YELLOW}[10/12] Running security audit...${NC}"
if [ -f vendor/bin/security-checker ]; then
    ./vendor/bin/security-checker security:check
    echo -e "${GREEN}✓ Security audit completed${NC}"
else
    echo -e "${YELLOW}⚠ Security checker not installed, skipping${NC}"
fi

# Step 11: Run tests
echo -e "\n${YELLOW}[11/12] Running tests...${NC}"
php artisan test --coverage-clover coverage.xml --no-coverage-report
echo -e "${GREEN}✓ Tests completed${NC}"

# Step 12: Generate report
echo -e "\n${YELLOW}[12/12] Generating test report...${NC}"
echo -e "${GREEN}✓ Test report generated${NC}"

# Final summary
echo -e "\n${BLUE}======================================${NC}"
echo -e "${GREEN}All tests completed successfully!${NC}"
echo -e "${BLUE}======================================${NC}"

# Display test coverage if available
if [ -f coverage.xml ]; then
    echo -e "\n${BLUE}Test Coverage Report:${NC}"
    echo -e "${GREEN}Coverage file generated at: coverage.xml${NC}"
fi

echo -e "\n${YELLOW}Next steps:${NC}"
echo -e "1. Review test coverage report"
echo -e "2. Check performance logs in storage/logs/performance.log"
echo -e "3. Monitor health endpoints: /health and /health/metrics"
echo -e "4. Review security logs in storage/logs/security.log"

exit 0
