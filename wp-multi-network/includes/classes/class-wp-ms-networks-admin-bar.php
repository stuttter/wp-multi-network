<?php
/**
 * WP Multi Network Admin Bar
 *
 * @package WPMN
 * @subpackage Admin
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

/**
 * Admin Bar Menu
 *
 * @since 2.2
 */
class WP_MS_Networks_Admin_Bar {

    /**
     * Hook methods in
     *
     * @since 2.2.0
     */
    public function __construct() {

        // Menus
        add_action( 'admin_bar_menu', array( $this, 'admin_bar' ), 20 );

        // Styling & Scripting
        add_action( 'admin_print_styles', array( $this , 'admin_print_styles' ) );
        add_action( 'wp_print_styles',    array( $this , 'admin_print_styles' ) );
    }

    /**
     * Adds networking icon to admin bar network menu item.
	 *
	 * This is done inline to avoid registering a separate CSS file for just an
	 * icon in the menu bar.
	 *
	 * @since 2.2.0
     */
    public function admin_print_styles() { ?>
        <style type="text/css">
            #wpadminbar #wp-admin-bar-my-networks > .ab-item:first-child:before {
                content: "\f325";
                top: 3px;
            }
        </style>
        <?php
    }

    /**
	 * Output the admin bar menu items
	 *
	 * @since 2.2.0
	 *
     * @param WP_Admin_Bar $wp_admin_bar
     */
    public function admin_bar( $wp_admin_bar ) {

        // Don't show for logged out users or single site mode.
        if ( ! is_user_logged_in() || ! is_multisite() ) {
            return;
        }

		// Get user networks
        $networks = user_has_networks();

        // Bail if user does not have networks or they're not a global admin.
        if ( empty( $networks ) || ! is_global_admin() ) {
            return;
        }

		// Add the root menu
        $wp_admin_bar->add_menu( array(
            'id'    => 'my-networks',
            'title' => __( 'My Networks' ),
            'href'  => network_admin_url( 'admin.php?page=networks' ),
            'meta'  => array( 'class' => 'networks-parent' ),
        ) );

		// Loop through all networks
        foreach ( $networks as $network_id ) {

			// Get the network and switch to it
            $network = get_network( $network_id );
            switch_to_network( $network_id );

			// Add the root group
            $wp_admin_bar->add_group( array(
                'parent' => 'my-networks',
                'id'     => 'group-network-admin-' . $network_id,
            ) );

            $wp_admin_bar->add_menu( array(
                'parent' => 'group-network-admin-' . $network_id,
                'id'     => 'network-admin-' . $network_id,
                'title'  => $network->site_name,
                'href'   => network_admin_url()
            ) );

            $wp_admin_bar->add_menu( array(
                'parent' => 'network-admin-' . $network_id,
                'id'     => 'network-admin-d',
                'title'  => __( 'Dashboard' ),
                'href'   => network_admin_url(),
            ) );
            $wp_admin_bar->add_menu( array(
                'parent' => 'network-admin-' . $network_id,
                'id'     => 'network-admin-s' . $network_id,
                'title'  => __( 'Sites' ),
                'href'   => network_admin_url( 'sites.php' ),
            ) );
            $wp_admin_bar->add_menu( array(
                'parent' => 'network-admin-' . $network_id,
                'id'     => 'network-admin-u' . $network_id,
                'title'  => __( 'Users' ),
                'href'   => network_admin_url( 'users.php' ),
            ) );
            $wp_admin_bar->add_menu( array(
                'parent' => 'network-admin-' . $network_id,
                'id'     => 'network-admin-t' . $network_id,
                'title'  => __( 'Themes' ),
                'href'   => network_admin_url( 'themes.php' ),
            ) );
            $wp_admin_bar->add_menu( array(
                'parent' => 'network-admin-' . $network_id,
                'id'     => 'network-admin-p' . $network_id,
                'title'  => __( 'Plugins' ),
                'href'   => network_admin_url( 'plugins.php' ),
            ) );
            $wp_admin_bar->add_menu( array(
                'parent' => 'network-admin-' . $network_id,
                'id'     => 'network-admin-o' . $network_id,
                'title'  => __( 'Settings' ),
                'href'   => network_admin_url( 'settings.php' ),
            ) );

			// Restore the current network
            restore_current_network();
        }
    }
}
