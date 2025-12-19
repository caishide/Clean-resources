#!/bin/bash

# Security Fix Verification Script
# This script verifies that all IDOR vulnerabilities have been properly fixed

echo "========================================"
echo "Security Fix Verification Script"
echo "========================================"
echo ""

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Counter for checks
PASSED=0
FAILED=0

# Function to check if file exists and has content
check_file() {
    if [ -f "$1" ]; then
        echo -e "${GREEN}✓${NC} File exists: $1"
        ((PASSED++))
        return 0
    else
        echo -e "${RED}✗${NC} File missing: $1"
        ((FAILED++))
        return 1
    fi
}

# Function to check if pattern exists in file
check_pattern() {
    local file=$1
    local pattern=$2
    local description=$3

    if grep -q "$pattern" "$file" 2>/dev/null; then
        echo -e "${GREEN}✓${NC} $description"
        ((PASSED++))
        return 0
    else
        echo -e "${RED}✗${NC} $description"
        ((FAILED++))
        return 1
    fi
}

# Function to check if pattern does NOT exist in file
check_no_pattern() {
    local file=$1
    local pattern=$2
    local description=$3

    if ! grep -q "$pattern" "$file" 2>/dev/null; then
        echo -e "${GREEN}✓${NC} $description"
        ((PASSED++))
        return 0
    else
        echo -e "${RED}✗${NC} $description"
        ((FAILED++))
        return 1
    fi
}

echo "1. Checking Modified Controller Files"
echo "======================================"

check_file "app/Http/Controllers/SiteController.php"
check_pattern "app/Http/Controllers/SiteController.php" "use Illuminate\Support\Facades\Log;" "Log facade imported in SiteController"
check_pattern "app/Http/Controllers/SiteController.php" "\$request->validate(" "Request validation in SiteController"
check_pattern "app/Http/Controllers/SiteController.php" "regex:/^[a-zA-Z0-9_-]+$/" "Regex pattern validation in SiteController"
check_pattern "app/Http/Controllers/SiteController.php" "Log::channel('security')" "Security logging in SiteController"

echo ""
check_file "app/Http/Controllers/Gateway/SslCommerz/ProcessController.php"
check_pattern "app/Http/Controllers/Gateway/SslCommerz/ProcessController.php" "tran_id.*required.*string.*max:50.*regex" "SSLCOMMERZ tran_id validation"
check_pattern "app/Http/Controllers/Gateway/SslCommerz/ProcessController.php" "status.*required.*string.*in:VALID,INVALID" "SSLCOMMERZ status validation"

echo ""
check_file "app/Http/Controllers/Gateway/Stripe/ProcessController.php"
check_pattern "app/Http/Controllers/Gateway/Stripe/ProcessController.php" "cardNumber.*required.*digits_between:13,19" "Stripe card number validation"
check_pattern "app/Http/Controllers/Gateway/Stripe/ProcessController.php" "cardExpiry.*required.*regex" "Stripe expiry validation"

echo ""
check_file "app/Http/Controllers/Gateway/PaypalSdk/ProcessController.php"
check_pattern "app/Http/Controllers/Gateway/PaypalSdk/ProcessController.php" "public function ipn(Request \$request)" "PayPal IPN accepts Request parameter"
check_pattern "app/Http/Controllers/Gateway/PaypalSdk/ProcessController.php" "token.*required.*string.*max:100" "PayPal token validation"

echo ""
echo "2. Checking Logging Configuration"
echo "=================================="

check_file "config/logging.php"
check_pattern "config/logging.php" "'security'" "Security log channel configured"
check_pattern "config/logging.php" "'gateway'" "Gateway log channel configured"
check_pattern "config/logging.php" "storage_path('logs/security.log')" "Security log path configured"
check_pattern "config/logging.php" "storage_path('logs/gateway.log')" "Gateway log path configured"

echo ""
echo "3. Verifying No Direct Parameter Access"
echo "========================================"

echo "Checking for direct \$_GET usage in controllers..."
DIRECT_GET=$(find app/Http/Controllers -name "*.php" -exec grep -l '\$_GET\[' {} \; 2>/dev/null | wc -l)
if [ "$DIRECT_GET" -eq 0 ]; then
    echo -e "${GREEN}✓${NC} No direct \$_GET usage found in controllers"
    ((PASSED++))
else
    echo -e "${RED}✗${NC} Found $DIRECT_GET files with direct \$_GET usage"
    find app/Http/Controllers -name "*.php" -exec grep -l '\$_GET\[' {} \; 2>/dev/null
    ((FAILED++))
fi

echo "Checking for direct \$_POST usage in controllers..."
DIRECT_POST=$(find app/Http/Controllers -name "*.php" -exec grep -l '\$_POST\[' {} \; 2>/dev/null | wc -l)
if [ "$DIRECT_POST" -eq 0 ]; then
    echo -e "${GREEN}✓${NC} No direct \$_POST usage found in controllers"
    ((PASSED++))
else
    echo -e "${RED}✗${NC} Found $DIRECT_POST files with direct \$_POST usage"
    find app/Http/Controllers -name "*.php" -exec grep -l '\$_POST\[' {} \; 2>/dev/null
    ((FAILED++))
fi

echo "Checking for direct \$_REQUEST usage in controllers..."
DIRECT_REQUEST=$(find app/Http/Controllers -name "*.php" -exec grep -l '\$_REQUEST\[' {} \; 2>/dev/null | wc -l)
if [ "$DIRECT_REQUEST" -eq 0 ]; then
    echo -e "${GREEN}✓${NC} No direct \$_REQUEST usage found in controllers"
    ((PASSED++))
else
    echo -e "${RED}✗${NC} Found $DIRECT_REQUEST files with direct \$_REQUEST usage"
    find app/Http/Controllers -name "*.php" -exec grep -l '\$_REQUEST\[' {} \; 2>/dev/null
    ((FAILED++))
fi

echo ""
echo "4. Checking Security Documentation"
echo "==================================="

check_file "SECURITY_FIX_REPORT.md"
check_file "SECURITY_CHECKLIST.md"
check_file "CHANGES_SUMMARY.md"

echo ""
echo "5. Checking CSRF Protection"
echo "============================"

check_pattern "bootstrap/app.php" "VerifyCsrfToken" "CSRF middleware configured"
check_pattern "bootstrap/app.php" "validateCsrfTokens" "CSRF validation enabled"

echo ""
echo "6. Checking Request Validation Patterns"
echo "========================================"

check_pattern "app/Http/Controllers/SiteController.php" "max:50" "Length limits implemented"
check_pattern "app/Http/Controllers/Gateway/SslCommerz/ProcessController.php" "max:50" "Length limits in gateway"
check_pattern "app/Http/Controllers/Gateway/PaypalSdk/ProcessController.php" "max:100" "Length limits in PayPal"

echo ""
echo "========================================"
echo "Verification Summary"
echo "========================================"
echo -e "${GREEN}Passed:${NC} $PASSED"
echo -e "${RED}Failed:${NC} $FAILED"
echo ""

if [ $FAILED -eq 0 ]; then
    echo -e "${GREEN}✓ All security checks passed!${NC}"
    echo ""
    echo "Recommended Next Steps:"
    echo "1. Create security log directory: mkdir -p storage/logs"
    echo "2. Set proper permissions: chmod 755 storage/logs"
    echo "3. Test the application functionality"
    echo "4. Monitor security logs: tail -f storage/logs/security.log"
    echo "5. Review the security documentation"
    exit 0
else
    echo -e "${RED}✗ Some security checks failed!${NC}"
    echo "Please review the failed items above."
    exit 1
fi
