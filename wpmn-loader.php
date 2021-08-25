<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName
/**
 * Plugin loader class
 *
 * @package WPMN
 * @since 1.0.0
 *
 * @wordpress-plugin
 * Plugin Name:       WP Multi-Network
 * Description:       Provides a Network Management Interface for global administrators in WordPress Multisite installations.
 * Plugin URI:        https://wordpress.org/plugins/wp-multi-network/
 * Author:            Triple J Software, Inc.
 * Author URI:        https://jjj.software
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wp-multi-network
 * Network:           true
 * Requires at least: 4.9
 * Requires PHP:      5.2
 * Tested up to:      5.8
 * Version:           2.5.0
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Class responsible for initializing and loading plugin functionality.
 *
 * @since 1.3.0
 */
class WPMN_Loader {

	/**
	 * Plugin main file path.
	 *
	 * @since 1.3.0
	 * @var string
	 */
	public $file = '';

	/**
	 * Plugin main file path, relative to the plugins directory.
	 *
	 * @since 1.3.0
	 * @var string
	 */
	public $basename = '';

	/**
	 * URL to the plugin's wp-multi-network subdirectory.
	 *
	 * @since 1.3.0
	 * @var string
	 */
	public $plugin_url = '';

	/**
	 * Path to the plugin's wp-multi-network subdirectory.
	 *
	 * @since 1.3.0
	 * @var string
	 */
	public $plugin_dir = '';

	/**
	 * Version to use for the plugin assets.
	 *
	 * @since 1.3.0
	 * @var string
	 */
	public $asset_version = 202108250001;

	/**
	 * Network admin class instance.
	 *
	 * @since 1.3.0
	 * @var WP_MS_Networks_Admin
	 */
	public $admin;

	/**
	 * Network capabilities class instance.
	 *
	 * @since 2.3.0
	 * @var WP_MS_Networks_Capabilities
	 */
	private $capabilities;

	/**
	 * Network admin bar class instance.
	 *
	 * @since 2.3.0
	 * @var WP_MS_Networks_Admin_Bar
	 */
	private $admin_bar;

	/**
	 * Constructor.
	 *
	 * @since 1.3.0
	 */
	public function __construct() {
		$this->constants();
		$this->setup_globals();
		$this->includes();
	}

	/**
	 * Sets up constants used by the plugin if they are not already defined.
	 *
	 * @since 1.3.0
	 */
	private function constants() {
		if ( ! defined( 'RESCUE_ORPHANED_BLOGS' ) ) {
			define( 'RESCUE_ORPHANED_BLOGS', false );
		}

		if ( ! defined( 'WPMN_DEPRECATED' ) ) {
			define( 'WPMN_DEPRECATED', false );
		}
	}

	/**
	 * Sets up the global properties used by the plugin.
	 *
	 * @since 1.3.0
	 */
	private function setup_globals() {
		$this->file       = __FILE__;
		$this->basename   = plugin_basename( $this->file );
		$this->plugin_dir = plugin_dir_path( $this->file ) . 'wp-multi-network/';
		$this->plugin_url = plugin_dir_url( $this->file ) . 'wp-multi-network/';
	}

	/**
	 * Includes the required files to run the plugin.
	 *
	 * @since 1.3.0
	 */
	private function includes() {

		// Manual localization loading is no longer necessary since WP 4.6.
		if ( version_compare( $GLOBALS['wp_version'], '4.6', '<' ) ) {
			load_plugin_textdomain( 'wp-multi-network' );
		}

		require $this->plugin_dir . 'includes/compat.php';
		require $this->plugin_dir . 'includes/functions.php';

		require $this->plugin_dir . 'includes/classes/class-wp-ms-networks-capabilities.php';

		if ( is_blog_admin() || is_network_admin() ) {
			require $this->plugin_dir . 'includes/metaboxes/move-site.php';
			require $this->plugin_dir . 'includes/metaboxes/edit-network.php';

			require $this->plugin_dir . 'includes/classes/class-wp-ms-networks-admin.php';

			$this->admin = new WP_MS_Networks_Admin();
		}

		require $this->plugin_dir . 'includes/classes/class-wp-ms-networks-admin-bar.php';

		$this->capabilities = new WP_MS_Networks_Capabilities();
		$this->capabilities->add_hooks();

		$this->admin_bar = new WP_MS_Networks_Admin_Bar();

		if ( defined( 'WPMN_DEPRECATED' ) && ( true === WPMN_DEPRECATED ) ) {
			require $this->plugin_dir . 'includes/deprecated.php';
		}

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			require $this->plugin_dir . 'includes/classes/class-wp-ms-network-command.php';
		}

		// REST endpoint class only load 4.7+.
		if ( version_compare( $GLOBALS['wp_version'], '4.7', '>=' ) ) {
			require $this->plugin_dir . 'includes/classes/class-wp-ms-rest-networks-controller.php';
		}
	}
}

/**
 * Hooks loader into muplugins_loaded, in order to load early.
 *
 * @since 1.3.0
 */
function setup_multi_network() {
	wpmn();
}
add_action( 'muplugins_loaded', 'setup_multi_network' );

/**
 * Hook REST endpoints on rest_api_init
 *
 * @since 2.3.0
 */
function setup_multi_network_endpoints() {
	$controller = new WP_MS_REST_Networks_Controller();
	$controller->register_routes();
}
add_action( 'rest_api_init', 'setup_multi_network_endpoints', 99 );

/**
 * Returns the main WP Multi Network instance.
 *
 * It will be instantiated if not available yet.
 *
 * @since 1.7.0
 *
 * @return WPMN_Loader WP Multi Network instance to use.
 */
function wpmn() {
	static $wpmn = false;

	if ( false === $wpmn ) {
		$wpmn = new WPMN_Loader();
	}

	return $wpmn;
}
