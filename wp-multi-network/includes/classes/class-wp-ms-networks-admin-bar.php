<?php
/**
 * WP_MS_Networks_Admin_Bar class
 *
 * @package WPMN
 * @since 2.2.0
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Class for integrating with the admin bar.
 *
 * @since 2.2.0
 */
class WP_MS_Networks_Admin_Bar {

	/**
	 * Constructor.
	 *
	 * Hooks in the necessary methods.
	 *
	 * @since 2.2.0
	 */
	public function __construct() {
		add_action( 'admin_bar_menu', array( $this, 'admin_bar' ), 20 );

		add_action( 'admin_print_styles', array( $this, 'admin_print_styles' ) );
		add_action( 'wp_print_styles', array( $this, 'admin_print_styles' ) );
	}

	/**
	 * Adds networking icon to admin bar network menu item.
	 *
	 * This is done inline to avoid registering a separate CSS file for just an
	 * icon in the menu bar.
	 *
	 * @since 2.2.0
	 */
	public function admin_print_styles() {
		?>
		<style type="text/css">
			#wpadminbar #wp-admin-bar-my-networks > .ab-item:first-child:before {
				content: "\f325";
				top: 3px;
			}
		</style>
		<?php
	}

	/**
	 * Outputs the admin bar menu items.
	 *
	 * @since 2.2.0
	 *
	 * @param WP_Admin_Bar $wp_admin_bar Admin bar instance.
	 */
	public function admin_bar( $wp_admin_bar ) {

		// Bail if logged out user or single site mode.
		if ( ! is_user_logged_in() || ! is_multisite() ) {
			return;
		}

		$networks = user_has_networks();

		// Bail if user does not have networks or they can't manage networks.
		if ( empty( $networks ) || ! current_user_can( 'manage_networks' ) ) {
			return;
		}

		$wp_admin_bar->add_menu( array(
			'id'    => 'my-networks',
			'title' => __( 'My Networks', 'wp-multi-network' ),
			'href'  => network_admin_url( 'admin.php?page=networks' ),
			'meta'  => array(
				'class' => 'networks-parent',
			),
		) );

		foreach ( $networks as $network_id ) {
			$network = get_network( $network_id );
			if ( ! $network ) {
				continue;
			}

			switch_to_network( $network_id );

			if ( ! current_user_can( 'manage_network' ) ) {
				restore_current_network();
				continue;
			}

			$wp_admin_bar->add_group( array(
				'parent' => 'my-networks',
				'id'     => 'group-network-admin-' . $network_id,
			) );

			$wp_admin_bar->add_menu( array(
				'parent' => 'group-network-admin-' . $network_id,
				'id'     => 'network-admin-' . $network_id,
				'title'  => $network->site_name,
				'href'   => network_admin_url(),
			) );

			$wp_admin_bar->add_menu( array(
				'parent' => 'network-admin-' . $network_id,
				'id'     => 'network-admin-d',
				// phpcs:ignore WordPress.WP.I18n.MissingArgDomain
				'title'  => __( 'Dashboard' ),
				'href'   => network_admin_url(),
			) );

			if ( current_user_can( 'manage_sites' ) ) {
				$wp_admin_bar->add_menu( array(
					'parent' => 'network-admin-' . $network_id,
					'id'     => 'network-admin-s' . $network_id,
					// phpcs:ignore WordPress.WP.I18n.MissingArgDomain
					'title'  => __( 'Sites' ),
					'href'   => network_admin_url( 'sites.php' ),
				) );
			}

			if ( current_user_can( 'manage_network_users' ) ) {
				$wp_admin_bar->add_menu( array(
					'parent' => 'network-admin-' . $network_id,
					'id'     => 'network-admin-u' . $network_id,
					// phpcs:ignore WordPress.WP.I18n.MissingArgDomain
					'title'  => __( 'Users' ),
					'href'   => network_admin_url( 'users.php' ),
				) );
			}

			if ( current_user_can( 'manage_network_themes' ) ) {
				$wp_admin_bar->add_menu( array(
					'parent' => 'network-admin-' . $network_id,
					'id'     => 'network-admin-t' . $network_id,
					// phpcs:ignore WordPress.WP.I18n.MissingArgDomain
					'title'  => __( 'Themes' ),
					'href'   => network_admin_url( 'themes.php' ),
				) );
			}

			if ( current_user_can( 'manage_network_plugins' ) ) {
				$wp_admin_bar->add_menu( array(
					'parent' => 'network-admin-' . $network_id,
					'id'     => 'network-admin-p' . $network_id,
					// phpcs:ignore WordPress.WP.I18n.MissingArgDomain
					'title'  => __( 'Plugins' ),
					'href'   => network_admin_url( 'plugins.php' ),
				) );
			}

			if ( current_user_can( 'manage_network_options' ) ) {
				$wp_admin_bar->add_menu( array(
					'parent' => 'network-admin-' . $network_id,
					'id'     => 'network-admin-o' . $network_id,
					// phpcs:ignore WordPress.WP.I18n.MissingArgDomain
					'title'  => __( 'Settings' ),
					'href'   => network_admin_url( 'settings.php' ),
				) );
			}

			restore_current_network();
		}
	}
}
