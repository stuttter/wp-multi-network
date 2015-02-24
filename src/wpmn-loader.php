<?php

/**
 * WP Multi Network
 *
 * Created by David Dean
 * Refreshed by John James Jacoby for WordPress 3.0
 * Refreshed by Brian Layman for WordPress 3.1
 * Refreshed by John James Jacoby for WordPress 3.3
 * Refreshed by John James Jacoby for WordPress 3.5
 * Refreshed by David Dean for WordPress 3.6
 * Refreshed by John James Jacoby for WordPress 3.8
 *
 * @package WPMN
 * @subpackage Loader
 */

/**
 * Plugin Name: WP Multi-Network
 * Plugin URI:  http://wordpress.org/extend/plugins/wp-multi-network/
 * Description: Adds a Network Management UI for super admins in a WordPress Multisite environment
 * Version:     1.5.1
 * Author:      johnjamesjacoby, ddean, BrianLayman
 * Author URI:  http://jjj.me
 * Tags:        multi, networks, site, network, blog, domain, subdomain, path, multisite, MS
 * Network:     true
 * Text Domain: wp-multi-network
 */

// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) exit;

require_once( dirname( __FILE__ ) . '/wpmn-functions.php' );
require_once( dirname( __FILE__ ) . '/wpmn-actions.php'   );

if ( defined('WP_CLI') && WP_CLI ) {
	require_once( dirname( __FILE__ ) . '/includes/class-wp-cli.php' );
}

class WPMN_Loader {

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

		// Enable the holding network. Must be true to save orphaned blogs.
		if ( !defined( 'ENABLE_NETWORK_ZERO' ) ) {
			define( 'ENABLE_NETWORK_ZERO', false );
		}

		/**
		 * true = Redirect blogs from deleted network to holding network
		 *        instead of deleting them. Requires network zero above.
		 *
		 * false = Allow blogs belonging to deleted networks to be deleted.
		 */
		if ( !defined( 'RESCUE_ORPHANED_BLOGS' ) ) {
			define( 'RESCUE_ORPHANED_BLOGS', false );
		}

		define( 'NETWORKS_PER_PAGE', 10 );
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

		if ( is_network_admin() || is_admin() ) {
			require( $this->plugin_dir . 'wpmn-admin.php' );
			new WPMN_Admin();
		}
	}
}

/**
 * Hook loader into plugins_loaded
 *
 * @since 1.3
 */
function setup_multi_network() {
	new WPMN_Loader();
}
add_action( 'plugins_loaded', 'setup_multi_network' );
