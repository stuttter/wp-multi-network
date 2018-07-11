<?php
/**
 * Integration tests bootstrap file for PHPUnit.
 */

// Disable xdebug backtrace.
if ( function_exists( 'xdebug_disable' ) ) {
	xdebug_disable();
}

// Set path to the plugin.
define( 'TESTS_PLUGIN_DIR', dirname( dirname( dirname( dirname( __FILE__ ) ) ) ) );

// Detect where the WordPress core test suite is located.
if ( false !== getenv( 'WP_TESTS_DIR' ) ) {
	$_tests_dir = getenv( 'WP_TESTS_DIR' );
} elseif ( false !== getenv( 'WP_DEVELOP_DIR' ) ) {
	$_tests_dir = getenv( 'WP_DEVELOP_DIR' ) . '/tests/phpunit';
} elseif ( file_exists( '/tmp/wordpress-tests-lib/includes/bootstrap.php' ) ) {
	$_tests_dir = '/tmp/wordpress-tests-lib';
} else {
	$_tests_dir = dirname( dirname( dirname( dirname( TESTS_PLUGIN_DIR ) ) ) ) . '/tests/phpunit';
}

// Bail if the WordPress core test suite was not found.
if ( ! file_exists( $_tests_dir . '/includes/functions.php' ) ) {
	echo "The WordPress core test suite could not be located, please provide its path via WP_TESTS_DIR." . PHP_EOL;
	exit( 1 );
}

// Give access to the tests_add_filter() function.
require_once $_tests_dir . '/includes/functions.php';

/**
 * Manually load the plugin.
 */
function _manually_load_plugin() {
	if ( class_exists( 'WPMN_Loader' ) ) {
		return;
	}

	require TESTS_PLUGIN_DIR . '/wpmn-loader.php';
}
tests_add_filter( 'muplugins_loaded', '_manually_load_plugin', 0 );

// Start up the WordPress core test suite.
require $_tests_dir . '/includes/bootstrap.php';

// Load the testcase base class.
require_once dirname( __FILE__ ) . '/testcase.php';
