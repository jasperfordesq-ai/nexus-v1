# Core and Helper Test Suite

## Overview

This directory contains PHPUnit tests for 30 Core and Helper classes in Project NEXUS.

## Test Coverage

### Core Tests (23 files)

1. **AdminAuthTest.php** - Admin authentication and authorization (pre-existing, 652 lines)
2. **ApiAuthTest.php** - API authentication
3. **ApiErrorCodesTest.php** - Standardized API error codes (390 lines, comprehensive)
4. **AudioUploaderTest.php** - Audio file upload handling
5. **AuthTest.php** - Core authentication helpers
6. **CsrfTest.php** - CSRF token protection (265 lines, comprehensive)
7. **DatabaseTest.php** - Database wrapper and PDO connection
8. **DatabaseWrapperTest.php** - Database wrapper utilities
9. **DefaultMenusTest.php** - Default menu generation
10. **EmailTemplateTest.php** - Email template rendering
11. **EnvTest.php** - Environment variable management
12. **MailerTest.php** - Email sending utilities
13. **MenuGeneratorTest.php** - Menu generation logic
14. **MenuManagerTest.php** - Menu management
15. **RateLimiterTest.php** - Rate limiting for logins and API
16. **RouterTest.php** - Request routing
17. **SearchServiceTest.php** - Search functionality
18. **SEOTest.php** - SEO meta tag management
19. **SimpleOAuthTest.php** - OAuth utilities
20. **SmartBlockRendererTest.php** - Block content rendering
21. **TenantContextTest.php** - Multi-tenant context management
22. **ValidatorTest.php** - Validation utilities (299 lines, comprehensive)
23. **VaultClientTest.php** - Secret management
24. **ViewTest.php** - View rendering

### Helper Tests (7 files)

1. **CorsHelperTest.php** - CORS header management
2. **IcsHelperTest.php** - iCalendar file generation
3. **ImageHelperTest.php** - Image manipulation
4. **NavigationConfigTest.php** - Navigation configuration
5. **SDGTest.php** - UN Sustainable Development Goals data (217 lines, comprehensive)
6. **TimeHelperTest.php** - Time formatting utilities (300 lines, comprehensive)
7. **UrlHelperTest.php** - URL safety and validation (280 lines, comprehensive)

## Running Tests

### All Core Tests
```bash
vendor/bin/phpunit tests/Core/ --no-coverage
```

### All Helper Tests
```bash
vendor/bin/phpunit tests/Helpers/ --no-coverage
```

### Specific Test File
```bash
vendor/bin/phpunit tests/Core/ValidatorTest.php --no-coverage
```

## Test Patterns

### Pure Utility Classes
Tests extend `TestCase` for pure utility classes (Validator, TimeHelper, UrlHelper, SDG, etc.)

### Database-Dependent Classes
Tests extend `DatabaseTestCase` for classes requiring database access (Auth, TenantContext, RateLimiter, etc.)

### External Services
Tests extend `TestCase` and mock external dependencies (Mailer, VaultClient, etc.)

## Key Features

- **SPDX Headers** - All files have required copyright headers
- **Comprehensive Coverage** - 5-15 tests per class, covering public methods, edge cases, and error handling
- **Type Safety** - Uses strict_types declaration
- **PHPDoc** - Descriptive class-level documentation with @covers annotations
- **Organized** - Tests grouped by functionality (structure, validation, edge cases)

## Notes

- Database connection errors in local environment are expected when DB is not running
- Tests use DatabaseTestCase transaction rollback for isolation
- Some tests verify method signatures and class structure for interfaces that may vary by implementation
