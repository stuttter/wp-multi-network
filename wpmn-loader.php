<?php

/**
 * Plugin Name: WP Multi-Network
 * Plugin URI:  https://wordpress.org/plugins/wp-multi-network/
 * Description: A Network Management UI for global administrators in WordPress Multisite
 * Version:     1.7.1
 * Author:      johnjamesjacoby, ddean, BrianLayman, rmccue, MaximeCulea
 * Author URI:  http://jjj.me
 * Tags:        blog, domain, mapping, multisite, network, networks, path, site, subdomain
 * Network:     true
 * Text Domain: wp-multi-network
 * Domain Path: /languages
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

class WPMN_Loader {

	/**
	 * @var string Base file
	 */
	public $file = '';

	/**
	 * @var string Plugin base file
	 */
	public $basename = '';

	/**
	 * @var string Plugin URL
	 */
	public $plugin_url = '';

	/**
	 * @var string Plugin directory
	 */
	public $plugin_dir = '';

	/**
	 * @var string Asset version
	 */
	public $asset_version = 201510070001;

	/**
	 * Load WP Multi Network
	 *
	 * @since 1.3
	 * @access public
	 *
	 * @uses WPMN_Loader::constants() To setup some constants
	 * @uses WPMN_Loader::setup_globals() To setup some globals
	 * @uses WPMN_Loader::includes() To include the required files
	 */
	public function __construct() {
		$this->constants();
		$this->setup_globals();
		$this->includes();
	}

	/**
	 * Set some constants
	 *
	 * @since 1.3
	 * @access private
	 */
	private function constants() {

		/**
		 * true = Redirect blogs from deleted network to holding network
		 *        instead of deleting them. Requires network zero above.
		 *
		 * false = Allow blogs belonging to deleted networks to be deleted.
		 */
		if ( ! defined( 'RESCUE_ORPHANED_BLOGS' ) ) {
			define( 'RESCUE_ORPHANED_BLOGS', false );
		}
	}

	/**
	 * Set some globals
	 *
	 * @since 1.3
	 * @access private
	 *
	 * @uses plugin_dir_path() To generate bbPress plugin path
	 * @uses plugin_dir_url() To generate bbPress plugin url
	 */
	private function setup_globals() {
		$this->file       = __FILE__;
		$this->basename   = plugin_basename( $this->file );
		$this->plugin_dir = plugin_dir_path( $this->file );
		$this->plugin_url = plugin_dir_url ( $this->file );
	}

	/**
	 * Include the required files
	 *
	 * @since 1.3
	 * @access private
	 *
	 * @uses is_network_admin() To only include admin code when needed
	 */
	private function includes() {

		// Functions & Core Compatibility
		require $this->plugin_dir . 'includes/compat.php';
		require $this->plugin_dir . 'includes/functions.php';

		// WordPress Admin
		if ( is_network_admin() || is_admin() ) {

			// Metaboxes
			require $this->plugin_dir . 'includes/metaboxes/move-site.php';
			require $this->plugin_dir . 'includes/metaboxes/edit-network.php';

			// Admin class
			require $this->plugin_dir . 'includes/classes/class-wp-ms-networks-admin.php';

			// Localization
			load_plugin_textdomain( 'wp-multi-network', false, dirname( $this->basename ) . '/languages/' );

			// Setup the network admin
			new WP_MS_Networks_Admin();
		}

		// Deprecated functions & classes
		if ( defined( 'WPMN_DEPRECATED' ) && WPMN_DEPRECATED ) {
			require $this->plugin_dir . 'includes/deprecated.php';
		}

		// Command line
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			require $this->plugin_dir . 'includes/classes/class-wp-ms-networks-cli.php';
		}
	}
}

/**
 * Hook loader into plugins_loaded
 *
 * @since 1.3
 */
function setup_multi_network() {
	wpmn();
}
add_action( 'muplugins_loaded', 'setup_multi_network' );

/**
 * Return the main WP Multi Network object
 *
 * @since 1.7.0
 *
 * @staticvar boolean $wpmn
 * @return WPMN_Loader
 */
function wpmn() {
	static $wpmn = false;

	if ( false === $wpmn ) {
		$wpmn = new WPMN_Loader();
	}

	return $wpmn;
}
