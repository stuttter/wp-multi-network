<?php

/**
 * Plugin Name: WP Multi-Network
 * Plugin URI:  https://wordpress.org/plugins/wp-multi-network/
 * Description: A Network Management UI for global administrators in WordPress Multisite
 * Author:      johnjamesjacoby, ddean, BrianLayman, rmccue
 * Author URI:  https://jjj.blog
 * Tags:        blog, domain, mapping, multisite, network, networks, path, site, subdomain
 * Network:     true
 * License:     GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Version:     2.2.0
 * Text Domain: wp-multi-network
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
	public $asset_version = 201805110004;

	/**
	 * @var WP_MS_Networks_Admin|null Admin class instance
	 */
	public $admin = null;

    /**
     * @var WP_MS_Networks_Admin_Bar|null Admin Bar class instance
     */
    private $admin_bar;

    /**
	 * Load WP Multi Network
	 *
	 * @since 1.3.0
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
	 * @since 1.3.0
	 * @access private
	 */
	private function constants() {

		/**
		 * false = Delete sites when deleting networks (default)
		 *
		 * true = Prevent sites from being deleted when their networks are
		 *        deleted. Sets them to site_id 0 instead.
		 */
		if ( ! defined( 'RESCUE_ORPHANED_BLOGS' ) ) {
			define( 'RESCUE_ORPHANED_BLOGS', false );
		}

		// Don't load deprecated functions
		if ( ! defined( 'WPMN_DEPRECATED' ) ) {
			define( 'WPMN_DEPRECATED', false );
		}
	}

	/**
	 * Set some globals
	 *
	 * @since 1.3.0
	 * @access private
	 *
	 * @uses plugin_dir_path() To generate bbPress plugin path
	 * @uses plugin_dir_url() To generate bbPress plugin url
	 */
	private function setup_globals() {
		$this->file       = __FILE__;
		$this->basename   = plugin_basename( $this->file );
		$this->plugin_dir = plugin_dir_path( $this->file ) . 'wp-multi-network/';
		$this->plugin_url = plugin_dir_url ( $this->file ) . 'wp-multi-network/';
	}

	/**
	 * Include the required files
	 *
	 * @since 1.3.0
	 * @access private
	 *
	 * @uses is_network_admin() To only include admin code when needed
	 */
	private function includes() {

		// Functions & Core Compatibility
		require $this->plugin_dir . 'includes/compat.php';
		require $this->plugin_dir . 'includes/functions.php';

		// WordPress Admin
		if ( is_blog_admin() || is_network_admin() ) {

			// Metaboxes
			require $this->plugin_dir . 'includes/metaboxes/move-site.php';
			require $this->plugin_dir . 'includes/metaboxes/edit-network.php';

			// Admin class
			require $this->plugin_dir . 'includes/classes/class-wp-ms-networks-admin.php';

			// Localization
			load_plugin_textdomain( 'wp-multi-network' );

			// Setup the network admin
			$this->admin = new WP_MS_Networks_Admin();
		}

        // Admin Bar class
        require $this->plugin_dir . 'includes/classes/class-wp-ms-networks-admin-bar.php';

        // Localization
        load_plugin_textdomain( 'wp-multi-network', false, dirname( $this->basename ) . '/languages/' );

        // Setup the network admin bar
        $this->admin_bar = new WP_MS_Networks_Admin_bar();

		// Deprecated functions & classes
		if ( defined( 'WPMN_DEPRECATED' ) && ( true === WPMN_DEPRECATED ) ) {
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
 * @since 1.3.0
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
