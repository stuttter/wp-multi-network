# PHPUnit Testing Matrix for WP Multi-Network

This document explains the testing matrix configuration for the WP Multi-Network plugin, which ensures compatibility across multiple versions of WordPress, PHP, and PHPUnit.

## Overview

The testing matrix is designed to validate the plugin against various combinations of:

- **WordPress versions**: 5.5 (minimum) through latest
- **PHP versions**: 7.2 (minimum) through 8.3
- **PHPUnit versions**: 7, 8, and 9

## Version Compatibility Matrix

### PHPUnit Version Requirements

| PHPUnit Version | PHP Requirement | WordPress Compatibility | Configuration File |
|-----------------|-----------------|-------------------------|--------------------|
| PHPUnit 9.x     | PHP 7.3+        | All versions            | phpunit.xml.dist   |
| PHPUnit 8.x     | PHP 7.2+        | All versions            | phpunit.xml.legacy |
| PHPUnit 7.x     | PHP 7.1+        | WP 4.9 - 6.x            | phpunit.xml.legacy |

### WordPress Version Requirements

| WordPress Version | Minimum PHP | Recommended PHP |
|-------------------|-------------|-----------------|
| trunk             | 7.2.24      | 8.0+            |
| 6.4 - 6.9         | 7.2.24      | 8.0+            |
| 6.0 - 6.3         | 7.2.24      | 7.4+            |
| 5.9               | 7.2.24      | 7.4+            |
| 5.5 - 5.8         | 7.2.0       | 7.4+            |

## Test Matrix Configuration

The GitHub Actions workflow (`.github/workflows/phpunit-ci.yml`) runs tests across the following combinations:

### WordPress trunk (development)

- PHP 8.3 + PHPUnit 9

### WordPress 6.9

- PHP 8.3 + PHPUnit 9
- PHP 8.2 + PHPUnit 9

### WordPress 6.8

- PHP 8.3 + PHPUnit 9
- PHP 8.2 + PHPUnit 9
- PHP 8.1 + PHPUnit 9

### Latest WordPress (6.7)

- PHP 8.3 + PHPUnit 9
- PHP 8.2 + PHPUnit 9
- PHP 8.1 + PHPUnit 9
- PHP 8.0 + PHPUnit 9

### WordPress 6.4-6.6

- PHP 8.3 + PHPUnit 9 (WP 6.6)
- PHP 8.2 + PHPUnit 9 (WP 6.5)
- PHP 8.1 + PHPUnit 9 (WP 6.4)

### WordPress 6.0-6.3

- PHP 8.0 + PHPUnit 9 (WP 6.3)
- PHP 7.4 + PHPUnit 9 (WP 6.2)
- PHP 7.4 + PHPUnit 9 (WP 6.1)
- PHP 7.4 + PHPUnit 9 (WP 6.0)

### WordPress 5.9

- PHP 7.4 + PHPUnit 9
- PHP 7.3 + PHPUnit 9
- PHP 7.2 + PHPUnit 8

### Minimum Supported (WordPress 5.5)

- PHP 7.2 + PHPUnit 8

## Running Tests Locally

### Prerequisites

1. **Install dependencies:**

   ```bash
   composer install
   ```

2. **Set up the WordPress test environment:**

   ```bash
   bash bin/install-wp-tests.sh wordpress_test wp wp localhost latest
   ```

   Parameters:

   - `wordpress_test`: Database name
   - `wp`: Database user
   - `wp`: Database password
   - `localhost`: Database host
   - `latest`: WordPress version (or specific version like `6.4`)

### Running Tests

**With PHPUnit 9 (default):**

```bash
./vendor/bin/phpunit
```

**With PHPUnit 8 (using legacy config):**

```bash
composer require --dev phpunit/phpunit:^8.5
./vendor/bin/phpunit --configuration phpunit.xml.legacy
```

**With specific WordPress version:**

```bash
export WP_VERSION=6.4
bash bin/install-wp-tests.sh wordpress_test wp wp localhost $WP_VERSION
./vendor/bin/phpunit
```

### Running Specific Test Groups

Tests are organized using PHPUnit groups for easy filtering:

**Run all upload path tests:**

```bash
./vendor/bin/phpunit --group=upload
```

**Run tests by ticket number:**

```bash
./vendor/bin/phpunit --group=136
```

**Run specific test groups:**

```bash
# Files rewriting tests
./vendor/bin/phpunit --group=files-rewriting

# Multisite configuration tests
./vendor/bin/phpunit --group=multisite

# Subdirectory installation tests
./vendor/bin/phpunit --group=subdirectory
```

**Run a single test class:**

```bash
./vendor/bin/phpunit tests/integration/tests/test-upload-paths.php
```

**Run a specific test method:**

```bash
./vendor/bin/phpunit --filter test_upload_path_without_duplication
```

**Verbose and debug output:**

```bash
./vendor/bin/phpunit --group=upload --verbose
./vendor/bin/phpunit --group=upload --debug
```

### Testing with Docker

You can test different PHP versions using Docker:

```bash
# PHP 8.3
docker run --rm -v $(pwd):/app -w /app php:8.3-cli bash -c "composer install && ./vendor/bin/phpunit"

# PHP 8.0
docker run --rm -v $(pwd):/app -w /app php:8.0-cli bash -c "composer install && ./vendor/bin/phpunit"

# PHP 7.4
docker run --rm -v $(pwd):/app -w /app php:7.4-cli bash -c "composer install && ./vendor/bin/phpunit"
```

## Configuration Files

### phpunit.xml.dist (PHPUnit 9+)

Modern PHPUnit configuration with:

- Updated XML schema for PHPUnit 9.6
- `<coverage>` element for code coverage
- Removed deprecated attributes (`convertErrorsToExceptions`, `syntaxCheck`, etc.)

### phpunit.xml.legacy (PHPUnit 7-8)

Legacy PHPUnit configuration with:

- XML schema for PHPUnit 8.5
- `<filter><whitelist>` for code coverage
- `<logging>` element for reports
- Preserved deprecated attributes for compatibility

### Bootstrap Configuration

The test bootstrap (`tests/integration/includes/bootstrap.php`) automatically:

1. Detects the WordPress test suite location via `WP_TESTS_DIR` or `WP_DEVELOP_DIR`
2. Loads the plugin before WordPress initializes
3. Sets up multisite testing environment

## CI/CD Workflow

The GitHub Actions workflow automatically:

1. Sets up the specified PHP version
2. Installs the appropriate PHPUnit version
3. Installs the WordPress test suite
4. Selects the correct PHPUnit configuration file
5. Runs the test suite
6. Reports results

### Matrix Strategy

The workflow uses `fail-fast: false` to ensure all matrix jobs run even if one fails, providing comprehensive test coverage feedback.

## Troubleshooting

### PHPUnit Version Conflicts

If you encounter version conflicts:

```bash
# Remove existing PHPUnit
composer remove --dev phpunit/phpunit

# Install specific version
composer require --dev phpunit/phpunit:^9.6
```

### WordPress Test Suite Not Found

Ensure `WP_TESTS_DIR` is set correctly:

```bash
export WP_TESTS_DIR=/tmp/wordpress-tests-lib
bash bin/install-wp-tests.sh wordpress_test wp wp localhost latest
```

### Database Connection Issues

Verify your database credentials:

```bash
mysql -u wp -pwp -e "CREATE DATABASE IF NOT EXISTS wordpress_test;"
```

## Adding New Test Versions

To add support for new WordPress, PHP, or PHPUnit versions:

1. Update the matrix in `.github/workflows/phpunit-ci.yml`
2. Add a new entry under `matrix.include`
3. Ensure PHPUnit version compatibility with PHP version
4. Test locally before committing

Example:

```yaml
- wordpress: "6.8"
  php: "8.4"
  phpunit: "9"
  wp-version: "6.8"
```

## Best Practices

1. **Always test locally** before pushing to ensure tests pass
2. **Keep PHPUnit versions up to date** within compatibility constraints
3. **Test edge cases** with minimum and maximum supported versions
4. **Monitor WordPress releases** for new compatibility requirements
5. **Update documentation** when changing test configurations

## References

- [WordPress PHPUnit Tests](https://make.wordpress.org/core/handbook/testing/automated-testing/phpunit/)
- [PHPUnit Documentation](https://phpunit.de/documentation.html)
- [WordPress Version Archive](https://wordpress.org/download/releases/)
- [PHP Supported Versions](https://www.php.net/supported-versions.php)
