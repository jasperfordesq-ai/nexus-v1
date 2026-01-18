# API Testing Suite - Quick Reference

## Quick Start

```bash
# Run all API tests
php tests/run-api-tests.php

# Run specific suite
php tests/run-api-tests.php --suite=auth

# Run with verbose output
php tests/run-api-tests.php --verbose

# Generate coverage report
php tests/run-api-tests.php --coverage
```

## What's Included

### ✅ Implemented Tests (84 tests, 8 controllers)

1. **AuthController** (8 tests) - Login, logout, session management
2. **CoreApiController** (13 tests) - Members, listings, messages, notifications
3. **SocialApiController** (13 tests) - Likes, comments, feed posts
4. **WalletApiController** (6 tests) - Balance, transfers, transactions
5. **GamificationApiController** (13 tests) - Rewards, challenges, badges
6. **AiApiController** (15 tests) - AI chat, content generation
7. **PushApiController** (8 tests) - Push notifications, subscriptions
8. **WebAuthnApiController** (8 tests) - Passwordless authentication

### Test Structure

```
tests/
├── Controllers/
│   └── Api/
│       ├── ApiTestCase.php              # Base test class with helpers
│       ├── AuthControllerTest.php       # ✅ 8 tests
│       ├── CoreApiControllerTest.php    # ✅ 13 tests
│       ├── SocialApiControllerTest.php  # ✅ 13 tests
│       ├── WalletApiControllerTest.php  # ✅ 6 tests (existing)
│       ├── GamificationApiControllerTest.php  # ✅ 13 tests
│       ├── AiApiControllerTest.php      # ✅ 15 tests
│       ├── PushApiControllerTest.php    # ✅ 8 tests
│       └── WebAuthnApiControllerTest.php # ✅ 8 tests
├── run-api-tests.php                    # Test runner script
├── bootstrap.php                        # Test bootstrap
├── TestCase.php                         # Base utilities
└── DatabaseTestCase.php                 # Database utilities
```

## Available Test Suites

| Suite | Coverage | Endpoints |
|-------|----------|-----------|
| `auth` | 100% | 8 |
| `core` | 100% | 13 |
| `social` | 100% | 13 |
| `wallet` | 100% | 6 |
| `gamification` | 100% | 13 |
| `ai` | 100% | 15 |
| `push` | 100% | 8 |
| `webauthn` | 100% | 8 |
| **TOTAL** | **49%** | **84/173** |

## Running Tests

### Basic Commands

```bash
# All API tests
php tests/run-api-tests.php

# Specific suite
php tests/run-api-tests.php --suite=wallet

# With filter
php tests/run-api-tests.php --filter=testTransfer

# Stop on failure
php tests/run-api-tests.php --stop-on-failure
```

### Using PHPUnit Directly

```bash
# Run all API tests
vendor/bin/phpunit tests/Controllers/Api/

# Run specific test class
vendor/bin/phpunit tests/Controllers/Api/AuthControllerTest.php

# Run with coverage
vendor/bin/phpunit --coverage-html coverage/html tests/Controllers/Api/
```

## Writing Tests

### Basic Test Structure

```php
<?php

namespace Tests\Controllers\Api;

class MyApiTest extends ApiTestCase
{
    public function testGetEndpoint(): void
    {
        $response = $this->get('/api/endpoint', ['param' => 'value']);

        $this->assertEquals('GET', $response['method']);
        $this->assertArrayHasKey('param', $response['data']);
    }

    public function testPostEndpoint(): void
    {
        $response = $this->post('/api/endpoint', [
            'field' => 'value'
        ]);

        $this->assertEquals('POST', $response['method']);
        $this->assertArrayHasKey('field', $response['data']);
    }
}
```

### Available Helper Methods

```php
// HTTP requests
$this->get('/api/endpoint', $params);
$this->post('/api/endpoint', $data);
$this->put('/api/endpoint', $data);
$this->delete('/api/endpoint', $data);

// Assertions
$this->assertSuccess($response);
$this->assertError($response);
$this->assertJsonStructure(['id', 'name'], $data);
$this->assertArrayHasKeys(['key1', 'key2'], $array);

// Test data
$user = $this->createUser(['balance' => 100]);
$this->cleanupUser($user['id']);
```

## Test Data

- **Test Tenant ID:** `self::$testTenantId`
- **Test User ID:** `self::$testUserId`
- **Test User Email:** `self::$testUserEmail`
- **Test Auth Token:** `self::$testAuthToken`

All requests are automatically authenticated with test credentials.

## Configuration

### Environment

Tests use `.env.testing` configuration:
- Database: `nexus_test`
- Environment: `testing`
- Cache/Session: `array` driver

### PHPUnit Configuration

See [phpunit.xml](../phpunit.xml) for detailed configuration:
- Bootstrap: `tests/bootstrap.php`
- Test suites: Unit, Integration, Feature, Models, Services, Controllers
- Coverage output: `coverage/html/`

## Documentation

- **[API Testing Guide](../docs/API_TESTING_GUIDE.md)** - Complete testing guide
- **[API Test Matrix](../docs/API_TEST_MATRIX.md)** - Endpoint coverage matrix
- **[PHPUnit Docs](https://phpunit.de/)** - PHPUnit documentation

## Coverage Report

Generate and view coverage:

```bash
# Generate coverage
php tests/run-api-tests.php --coverage

# View HTML report
open coverage/html/index.html
```

## Troubleshooting

### Database Issues

```bash
# Check database
mysql -e "SHOW DATABASES LIKE 'nexus_test';"

# Create test database
mysql -e "CREATE DATABASE IF NOT EXISTS nexus_test;"
```

### Permission Issues

```bash
# Fix permissions
chmod -R 755 tests/
chmod -R 755 coverage/
```

### Clean Cache

```bash
# Clear PHPUnit cache
rm -rf .phpunit.cache/

# Clear test data
php tests/debug/clear-cache.php
```

## Next Steps

### Phase 2 - Additional Controllers (Coming Soon)

- Feed Management (3 endpoints)
- Events & Volunteering (3 endpoints)
- Leaderboard & Achievements (5 endpoints)
- Recommendations (4 endpoints)
- Menu Management (5 endpoints)
- Polls & Goals (5 endpoints)

### Phase 3 - Admin & Advanced (Planned)

- Layout Builder (10 endpoints)
- GDPR & Privacy (3 endpoints)
- Admin API (25 endpoints)

## Support

For help:
1. Check [API_TESTING_GUIDE.md](../docs/API_TESTING_GUIDE.md)
2. Review existing test examples
3. Contact development team

---

**Last Updated:** January 12, 2026
**Version:** 1.0.0
