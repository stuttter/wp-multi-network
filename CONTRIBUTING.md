# Contributing

Contributions are greatly appreciated. You can help in several ways:

* [File an issue.](https://github.com/stuttter/wp-multi-network/issues/new)
* [Open a pull-request.](https://github.com/stuttter/wp-multi-network/compare)
* [Translate some strings.](https://translate.wordpress.org/projects/wp-plugins/wp-multi-network)

## Requirements & Recommendations

When contributing code specifically, please keep the following in mind:

* Write code that is backward-compatible to PHP 5.2 and WordPress 4.9.
* Follow the [WordPress coding and documentation standards](https://make.wordpress.org/core/handbook/best-practices/coding-standards/).
* If applicable, provide integration tests for your changes.

This project provides workflows for:

* running integration tests (using PHPUnit)
* checking coding and documentation standards (using PHPCodeSniffer)
* static analysis (using PHPStan).

It is also integrated with GitHub Actions to ensure those always pass.

### PHPUnit and PHPCS Workflows

It is recommended to run integration tests and PHPCodeSniffer locally before committing, to check in advance that your changes do not cause unexpected issues. Here is how you can do that:

* `composer install`: Set up all plugin dependencies
* `vendor/bin/phpunit`: Run the integration tests.
* `vendor/bin/phpcs`: Check against the WordPress Coding Standards.

### Writing Integration Tests

* Integration tests go into the `tests/integration/tests` directory.
* File names must be prefixed with `test-`.
* Each test class must extend the `WPMN_UnitTestCase` class.
