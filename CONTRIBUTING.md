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
* checking coding and documentation standards (using PHPCodeSniffer).
* static analysis (using PHPStan).

It is also integrated with GitHub Actions to ensure those always pass.

### PHPUnit and PHPCS Workflows

It is recommended to run integration tests and PHPCodeSniffer locally before committing, to check in advance that your changes do not cause unexpected issues. Here is how you can do that:

* `composer install`: Set up all plugin dependencies
* `vendor/bin/phpunit`: Run the integration tests.
* `vendor/bin/phpcs`: Check against the WordPress Coding Standards.

### JavaScript and CSS Build Pipeline

This plugin uses [@wordpress/scripts](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-scripts/) for building, linting, and minifying JavaScript and CSS assets. The source files are located in the `src/` directory, and both source and minified versions are output to `wp-multi-network/assets/`.

To work with JavaScript and CSS:

* `npm install`: Install Node.js dependencies (Node.js 20+ required)
* `npm run build`: Build both source and minified production-ready assets
* `npm run start`: Start development mode with file watching and hot reload
* `npm run lint:js`: Lint JavaScript files
* `npm run lint:css`: Lint CSS files
* `npm run format`: Auto-format JavaScript files

The build process generates:
* **Source files**: `wp-multi-network.js`, `wp-multi-network.css`, `wp-multi-network-rtl.css` (unminified, for debugging)
* **Minified files**: `wp-multi-network.min.js`, `wp-multi-network.min.css`, `wp-multi-network-rtl.min.css` (production)

The plugin automatically loads source files when `WP_SCRIPT_DEBUG` is enabled, otherwise it loads minified versions.

**Important:** Always edit source files in the `src/` directory, not the built files in `wp-multi-network/assets/`. The built files are automatically generated from the source files and should not be edited directly.

### Writing Integration Tests

* Integration tests go into the `tests/integration/tests` directory.
* File names must be prefixed with `test-`.
* Each test class must extend the `WPMN_UnitTestCase` class.
