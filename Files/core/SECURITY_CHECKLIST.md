# Security Development Checklist

## ✅ Mandatory for Every Controller

### Input Validation
- [ ] Never use `$_GET`, `$_POST`, or `$_REQUEST` directly
- [ ] Always use `$request->input()` with validation
- [ ] Add type validation (string, integer, etc.)
- [ ] Implement length limits on all string inputs
- [ ] Use regex patterns for format validation
- [ ] Validate file uploads with proper MIME type checks
- [ ] Implement rate limiting for public endpoints

### Authorization Checks
- [ ] Verify user permissions before accessing resources
- [ ] Check ownership for user-specific resources
- [ ] Implement role-based access control (RBAC)
- [ ] Validate API keys and tokens
- [ ] Check CSRF tokens for state-changing operations

### Data Sanitization
- [ ] Sanitize user input with `strip_tags()`
- [ ] Escape special characters before output
- [ ] Validate and sanitize file paths
- [ ] Use parameterized queries (Eloquent ORM)
- [ ] Validate email addresses with proper format
- [ ] Sanitize phone numbers and identifiers

### Secure Coding Practices
- [ ] Type-hint Request objects: `public function store(Request $request)`
- [ ] Use Laravel validation rules
- [ ] Validate all parameters before database queries
- [ ] Use `findOrFail()` for resource lookups
- [ ] Implement proper error handling
- [ ] Never expose sensitive data in error messages

---

## ✅ Form Security

### CSRF Protection
- [ ] All forms include `@csrf` directive
- [ ] State-changing operations (POST, PUT, DELETE) are protected
- [ ] CSRF tokens are validated on server side
- [ ] Exception routes are properly documented and justified

### File Uploads
- [ ] Validate file types with MIME type checking
- [ ] Restrict file size limits
- [ ] Scan uploaded files for malware
- [ ] Store uploads outside web root
- [ ] Generate random filenames
- [ ] Validate image dimensions
- [ ] Use proper permissions (600 for sensitive files)

### Output Encoding
- [ ] Use `{{ }}` for Blade templates (auto-escaping)
- [ ] Use `{!! !!}` only when necessary and safe
- [ ] Encode special characters: `htmlspecialchars()`
- [ ] Escape JSON data properly
- [ ] Encode URLs: `urlencode()`

---

## ✅ Database Security

### Query Security
- [ ] Use Eloquent ORM or Query Builder
- [ ] NEVER use raw SQL with user input
- [ ] Use parameter binding for raw queries
- [ ] Validate foreign keys before queries
- [ ] Use database transactions for critical operations

### Data Protection
- [ ] Encrypt sensitive data at rest
- [ ] Hash passwords with `Hash::make()`
- [ ] Use Laravel's encryption for session data
- [ ] Implement field-level encryption for PII
- [ ] Mask sensitive data in logs

### Access Control
- [ ] Database users have minimal privileges
- [ ] Separate read/write database users
- [ ] No root/sa user for application
- [ ] Use connection pooling securely

---

## ✅ Session Security

### Session Configuration
- [ ] Use secure session drivers
- [ ] Implement session timeout
- [ ] Regenerate session IDs on login
- [ ] Use `csrf_token()` for AJAX requests
- [ ] Store sessions securely

### Cookie Security
- [ ] Set `HttpOnly` flag for sensitive cookies
- [ ] Set `Secure` flag for HTTPS
- [ ] Set `SameSite` attribute
- [ ] Use proper cookie scopes
- [ ] Implement cookie encryption

---

## ✅ API Security

### Authentication
- [ ] Implement OAuth 2.0 or JWT
- [ ] Use API key authentication where appropriate
- [ ] Implement token expiration
- [ ] Use refresh tokens
- [ ] Implement rate limiting

### Request Validation
- [ ] Validate request headers
- [ ] Check content types
- [ ] Implement request size limits
- [ ] Validate JSON schemas
- [ ] Sanitize API inputs

### Response Security
- [ ] Return proper HTTP status codes
- [ ] Don't expose sensitive data
- [ ] Implement API versioning
- [ ] Use HTTPS for all endpoints
- [ ] Implement CORS properly

---

## ✅ Logging and Monitoring

### Security Logging
- [ ] Log authentication attempts
- [ ] Log authorization failures
- [ ] Log suspicious activities
- [ ] Log API access
- [ ] Don't log sensitive data

### Log Protection
- [ ] Secure log files (proper permissions)
- [ ] Implement log rotation
- [ ] Use separate log channels
- [ ] Monitor log file sizes
- [ ] Implement log shipping for SIEM

### Monitoring
- [ ] Set up alerts for security events
- [ ] Monitor failed login attempts
- [ ] Track unusual patterns
- [ ] Implement anomaly detection
- [ ] Monitor API usage

---

## ✅ Error Handling

### Secure Error Messages
- [ ] Don't expose stack traces in production
- [ ] Don't reveal system information
- [ ] Use generic error messages for users
- [ ] Log detailed errors server-side
- [ ] Implement custom error pages

### Exception Handling
- [ ] Catch specific exceptions
- [ ] Log exceptions with context
- [ ] Don't expose sensitive data
- [ ] Return proper error codes
- [ ] Implement graceful degradation

---

## ✅ Third-Party Integration

### External API Calls
- [ ] Validate API responses
- [ ] Implement timeouts
- [ ] Use HTTPS for all calls
- [ ] Validate SSL certificates
- [ ] Implement retry logic with backoff

### Webhooks
- [ ] Verify webhook signatures
- [ ] Implement replay protection
- [ ] Validate payload data
- [ ] Use HTTPS endpoints
- [ ] Implement rate limiting

---

## ✅ File System Security

### Path Security
- [ ] Validate file paths
- [ ] Prevent directory traversal
- [ ] Use basename() for file names
- [ ] Implement proper permissions
- [ ] Store sensitive files outside web root

### Access Control
- [ ] Implement proper file permissions
- [ ] Use ACLs where necessary
- [ ] Regular permission audits
- [ ] Secure backup files
- [ ] Implement file integrity monitoring

---

## ✅ Configuration Security

### Environment Configuration
- [ ] Use `.env` files (not committed)
- [ ] Never commit secrets
- [ ] Use environment variables
- [ ] Implement secret rotation
- [ ] Use secure key generation

### Server Configuration
- [ ] Use HTTPS everywhere
- [ ] Implement HSTS headers
- [ ] Configure secure headers
- [ ] Disable directory listing
- [ ] Implement rate limiting
- [ ] Use secure ciphers

---

## Review Checklist

### Before Committing Code
- [ ] All inputs validated
- [ ] No direct `$_GET/$_POST/$_REQUEST` usage
- [ ] All database queries use ORM/parameter binding
- [ ] CSRF protection in place
- [ ] Logging implemented
- [ ] Error handling secure
- [ ] No sensitive data in comments
- [ ] Dependencies updated

### Before Deploying
- [ ] Security testing completed
- [ ] Penetration testing done
- [ ] Vulnerability scan passed
- [ ] Security headers configured
- [ ] SSL certificate valid
- [ ] Backup strategy tested
- [ ] Monitoring alerts configured
- [ ] Incident response plan ready

---

## Security Testing Commands

### Test Input Validation
```bash
# Test with special characters
curl -X POST http://example.com/api -d "param=test<script>"

# Test with long strings
curl -X POST http://example.com/api -d "param=$(python3 -c 'print("A"*10000)')"
```

### Test CSRF Protection
```bash
# Remove CSRF token and test
curl -X POST http://example.com/form -d "field=value"
```

### Test SQL Injection
```bash
# Test with SQL injection payload
curl -X GET "http://example.com/search?q=' OR '1'='1"
```

---

## Emergency Response

### If Vulnerability Found
1. **DO NOT PANIC**
2. Assess severity and impact
3. Implement temporary fix (WAF rules, rate limiting)
4. Notify security team
5. Patch vulnerability
6. Test fix thoroughly
7. Deploy to production
8. Monitor for exploitation attempts
9. Document incident
10. Review and improve processes

### Contact Information
- **Security Team:** security@example.com
- **Emergency:** +1-XXX-XXX-XXXX
- **On-Call:** Available 24/7

---

## Resources

### Documentation
- [Laravel Security](https://laravel.com/docs/master/security)
- [OWASP Top 10](https://owasp.org/www-project-top-ten/)
- [PHP Security Best Practices](https://php.net/manual/en/security.php)

### Tools
- [Laravel Security Checker](https://github.com/sensiolabs/security-checker)
- [SonarQube](https://www.sonarqube.org/)
- [OWASP ZAP](https://www.zaproxy.org/)

---

**Remember:** Security is everyone's responsibility. When in doubt, ask the security team!

**Last Updated:** December 19, 2025
**Version:** 1.0
