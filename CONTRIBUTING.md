# Contributing

Contributions to WP Multi Network are much appreciated. You can help out in several ways:

* [File an issue.](https://github.com/stuttter/wp-multi-network/issues/new)
* [Open a pull-request.](https://github.com/stuttter/wp-multi-network/compare)
* [Translate the plugin.](https://translate.wordpress.org/projects/wp-plugins/wp-multi-network)

## Requirements & Recommendations

When contributing code to WP Multi Network, please keep the following in mind:

* Write code that is backward-compatible to PHP 5.2 and WordPress 4.9
* Follow the [WordPress coding and documentation standards](https://make.wordpress.org/core/handbook/best-practices/coding-standards/).
* If possible, provide integration tests for your changes.

WP Multi Network provides easy-to-use workflows for both running integration tests (using PHPUnit) and checking coding and documentation standards (using PHPCodeSniffer). The plugin is integrated with Travis-CI in order to ensure those always pass.

### PHPUnit and PHPCS Workflows

It is recommended to run integration tests and PHPCodeSniffer locally before committing, to check in advance that your changes do not cause unexpected issues. Here is how you can do that:

* After cloning the plugin, you need to set up its dependencies by running `composer install`.
* In order to run the integration tests, you need to run `vendor/bin/phpunit`.
* In order to check against the WordPress Coding Standards, you need to run `vendor/bin/phpcs`.

### Writing Integration Tests

Integration tests should go into the `tests/integration/tests` directory. Each test class should extend the `WPMN_UnitTestCase` class, and file names should be prefixed with `test-`.
