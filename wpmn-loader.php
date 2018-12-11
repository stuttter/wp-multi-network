<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName
/**
 * Plugin loader class
 *
 * @package WPMN
 * @since 1.0.0
 *
 * @wordpress-plugin
 * Plugin Name: WP Multi-Network
 * Plugin URI:  https://wordpress.org/plugins/wp-multi-network/
 * Description: A Network Management UI for global administrators in WordPress Multisite
 * Author:      johnjamesjacoby, ddean, BrianLayman, rmccue
 * Author URI:  https://jjj.blog
 * Tags:        blog, domain, mapping, multisite, network, networks, path, site, subdomain
 * Network:     true
 * License:     GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Version:     2.2.1
 * Text Domain: wp-multi-network
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
	public $asset_version = 201805110004;

	/**
	 * Network admin class instance.
	 *
	 * @since 1.3.0
	 * @var WP_MS_Networks_Admin
	 */
	public $admin;

	/**
	 * Network admin bar class instance.
	 *
	 * @since 2.3.0
	 * @var WP_MS_Networks_Admin_Bar
	 */
	private $admin_bar;

	/**
	 * Main WP Multi Network Loader instance.
	 *
	 * @since 2.3.0
	 *
	 * @static object $instance
	 * @see wpmn()
	 *
	 * @return WPMN_Loader|null The one true WP Multi Network Loader instance.
	 */
	public static function instance() {

		// Store the instance locally to avoid private static replication.
		static $instance = null;

		// Only run these methods if they haven't been run previously.
		if ( null === $instance ) {
			$instance = new WPMN_Loader();
			$instance->constants();
			$instance->setup_globals();
			$instance->includes();
		}

		// Always return the instance.
		return $instance;

	}

	/**
	 * Constructor.
	 *
	 * @since 1.3.0
	 */
	public function __construct() {
		// Do nothing here.
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

		if ( is_blog_admin() || is_network_admin() ) {
			require $this->plugin_dir . 'includes/metaboxes/move-site.php';
			require $this->plugin_dir . 'includes/metaboxes/edit-network.php';

			require $this->plugin_dir . 'includes/classes/class-wp-ms-networks-admin.php';

			$this->admin = new WP_MS_Networks_Admin();
		}

		require $this->plugin_dir . 'includes/classes/class-wp-ms-networks-admin-bar.php';

		$this->admin_bar = new WP_MS_Networks_Admin_bar();

		if ( defined( 'WPMN_DEPRECATED' ) && ( true === WPMN_DEPRECATED ) ) {
			require $this->plugin_dir . 'includes/deprecated.php';
		}

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			require $this->plugin_dir . 'includes/classes/class-wp-ms-network-command.php';
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
 * Returns the main WP Multi Network instance.
 *
 * It will be instantiated if not available yet.
 *
 * @since 1.7.0
 *
 * @return WPMN_Loader|null The WP Multi Network instance.
 */
function wpmn() {
	return WPMN_Loader::instance();
}
