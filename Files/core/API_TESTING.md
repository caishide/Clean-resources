# API Testing Guide

This document provides comprehensive information about API testing for the BinaryEcom20 project.

## Table of Contents

- [Overview](#overview)
- [Test Structure](#test-structure)
- [Running Tests Locally](#running-tests-locally)
- [CI/CD Integration](#cicd-integration)
- [Test Environments](#test-environments)
- [Writing New Tests](#writing-new-tests)
- [Troubleshooting](#troubleshooting)

## Overview

The BinaryEcom20 project uses **Newman** for API testing, which is a command-line companion for Postman. The test suite includes:

- ✅ **Integration Tests**: Full API endpoint testing with authentication
- ✅ **Security Tests**: SQL injection, XSS, and unauthorized access prevention
- ✅ **Performance Tests**: Load testing with Artillery
- ✅ **Automated CI/CD**: Runs on every push, PR, and daily schedule

## Test Structure

```
tests/postman/
├── BinaryEcom20.postman_collection.json    # Main test collection
└── environments/
    ├── local.json                          # Local development environment
    └── ci.json                             # CI/CD environment
```

### Test Categories

#### 🔓 Public Tests
- Health check endpoints
- Basic site functionality

#### 🔑 Authentication Tests
- User login
- Admin login
- Token validation

#### 🔐 Protected User Tests
- PV summary
- Points summary
- Bonus history
- Pending bonuses

#### 👑 Admin Tests
- Settlement management
- Admin-only endpoints
- Authorization checks

## Running Tests Locally

### Prerequisites

1. Install Newman globally:
   ```bash
   npm install -g newman
   ```

2. Ensure Laravel application is running:
   ```bash
   php artisan serve
   ```

### Quick Start

Run all API tests with default settings:
```bash
npm run test:api
```

Or use the helper script directly:
```bash
bash run-api-tests.sh
```

### Available Options

#### By Environment
```bash
# Run with local environment
npm run test:api:local

# Run with CI environment (for testing CI config)
npm run test:api:ci
```

#### By Reporter
```bash
# Generate HTML report
bash run-api-tests.sh --reporter html

# Generate JSON report
bash run-api-tests.sh --reporter json

# Generate all reports
npm run test:api:report
```

#### Verbose Mode
```bash
bash run-api-tests.sh --verbose
```

### Complete Example

```bash
# Run tests with CI environment and JSON report
npm run test:api:ci

# Or with all options
bash run-api-tests.sh --env ci --reporter all --verbose
```

## CI/CD Integration

The API tests run automatically in GitHub Actions with three main jobs:

### 1. API Integration Tests (`api-tests`)
- Runs on every push to `master` or `develop`
- Runs on every pull request to `master`
- Runs daily at 2 AM UTC (scheduled)
- **Timeout**: 30 minutes
- **Services**: MySQL 8.0, Redis 7

**Workflow**:
1. ✅ Checkout code
2. ✅ Setup PHP 8.2
3. ✅ Install Composer dependencies
4. ✅ Setup Node.js and Newman
5. ✅ Configure Laravel testing environment
6. ✅ Run database migrations
7. ✅ Seed test data
8. ✅ Start Laravel server
9. ✅ Run Newman tests
10. ✅ Upload test reports
11. ✅ Comment PR with results

### 2. Performance Tests (`performance-tests`)
- Runs on `master` branch or scheduled
- Uses Artillery for load testing
- **Timeout**: 20 minutes

**Tests**:
- API health check under load
- User login flow under load
- Concurrent request handling

### 3. Security Tests (`security-tests`)
- Runs on `master` branch or scheduled
- Tests for common vulnerabilities
- **Timeout**: 20 minutes

**Security Checks**:
- SQL injection prevention
- XSS protection
- Unauthorized access prevention
- Admin endpoint protection

## Test Environments

### Local Environment (`local.json`)
```json
{
  "base_url": "http://localhost",
  "api_url": "http://localhost/api",
  "test_username": "user_79lgvw",
  "test_password": "test123",
  "admin_username": "58462822@qq.com",
  "admin_password": "admin123"
}
```

### CI Environment (`ci.json`)
```json
{
  "base_url": "http://localhost:8000",
  "api_url": "http://localhost:8000/api",
  "test_username": "user_79lgvw",
  "test_password": "test123",
  "admin_username": "58462822@qq.com",
  "admin_password": "admin123"
}
```

## Writing New Tests

### Adding a New Test to Collection

1. **Open Postman** and load `BinaryEcom20.postman_collection.json`

2. **Create a new request**:
   ```javascript
   // Request: GET /api/user/profile
   // Headers: Authorization: Bearer {{user_token}}

   pm.test('Status 200', () => {
     pm.response.to.have.status(200);
   });

   pm.test('Has user data', () => {
     const json = pm.response.json();
     pm.expect(json.status).to.equal('success');
     pm.expect(json.data).to.have.property('username');
   });
   ```

3. **Save the collection** and export to `tests/postman/`

### Adding a New Test Category

1. **Edit** `BinaryEcom20.postman_collection.json`

2. **Add a new folder**:
   ```json
   {
     "name": "🆕 New Category",
     "item": [
       // ... your tests here
     ]
   }
   ```

3. **Export updated collection**

### Environment Variables

Use these patterns in your tests:

- `{{base_url}}` - Base URL
- `{{api_url}}` - API base URL
- `{{user_token}}` - User authentication token
- `{{admin_token}}` - Admin authentication token

### Dynamic Token Assignment

From a login test:
```javascript
pm.test('Has token', () => {
  const json = pm.response.json();
  pm.expect(json.status).to.equal('success');
  pm.environment.set('user_token', json.data.token);
});
```

## Troubleshooting

### Common Issues

#### 1. Server Not Running
```
Error: connect ECONNREFUSED 127.0.0.1:8000
```
**Solution**: Start the Laravel server
```bash
php artisan serve
```

#### 2. Newman Not Found
```
Error: newman: command not found
```
**Solution**: Install Newman globally
```bash
npm install -g newman
```

#### 3. Database Connection Failed
```
SQLSTATE[HY000] [2002] Connection refused
```
**Solution**: Check MySQL is running
```bash
# Check MySQL status
mysqladmin ping

# Or restart MySQL
sudo systemctl restart mysql
```

#### 4. Tests Failing in CI but Passing Locally

**Check**:
- Environment variables match CI config
- Server is properly started in CI
- Database is migrated and seeded
- All dependencies are installed

**Debug**:
```bash
# Run with verbose output
bash run-api-tests.sh --verbose

# Check CI logs in GitHub Actions
```

#### 5. Token Authentication Failing

**Check**:
- Test users exist in database
- Passwords match environment variables
- Sanctum is properly configured
- API routes are registered

**Debug**:
```bash
# Test login manually
curl -X POST http://localhost:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"username":"user_79lgvw","password":"test123"}'
```

### Viewing Detailed Reports

After running tests, view the detailed JSON report:
```bash
# View JSON report
cat newman-report.json | jq

# View HTML report (if generated)
open newman-report.html  # macOS
xdg-open newman-report.html  # Linux
```

### CI/CD Specific Debugging

1. **Check workflow logs** in GitHub Actions
2. **Download artifacts** for detailed reports
3. **Review PR comments** for quick test summaries

## Best Practices

1. ✅ **Always test authentication** before protected endpoints
2. ✅ **Use environment variables** for URLs and credentials
3. ✅ **Test both success and failure** scenarios
4. ✅ **Include response time assertions** for performance
5. ✅ **Test admin authorization** separately
6. ✅ **Validate data structures** in responses
7. ✅ **Clean up test data** after tests
8. ✅ **Document new test scenarios**

## Additional Resources

- [Newman Documentation](https://github.com/postmanlabs/newman)
- [Postman Collection Format](https://schema.getpostman.com/)
- [Artillery Documentation](https://artillery.io/)
- [GitHub Actions Workflows](.github/workflows/)

## Support

For issues or questions:
1. Check this guide
2. Review CI/CD logs
3. Test locally with verbose mode
4. Open an issue with:
   - Test command used
   - Full error output
   - Environment details
   - Steps to reproduce
