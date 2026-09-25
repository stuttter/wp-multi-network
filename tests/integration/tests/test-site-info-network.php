<?php
/**
 * Tests moving a site from WordPress's Site Info screen.
 *
 * @since NEXT
 */

require_once TESTS_PLUGIN_DIR . '/wp-multi-network/includes/classes/class-wp-ms-networks-admin.php';

class WPMN_Tests_SiteInfoNetwork extends WPMN_UnitTestCase {

	public function test_network_field_is_available_for_a_subsite() {
		$admin       = new WP_MS_Networks_Admin();
		$site_id     = $this->factory->blog->create();
		$destination = $this->factory->network->create();
		$user_id     = $this->factory->user->create();
		grant_super_admin( $user_id );
		wp_set_current_user( $user_id );

		ob_start();
		$admin->site_info_network_field( $site_id );
		$html = ob_get_clean();

		$this->assertStringContainsString( 'name="wpmn_network_id"', $html );
		$this->assertStringContainsString( '<option value="' . $destination . '"', $html );
	}

	public function test_primary_site_has_no_network_selector() {
		$admin   = new WP_MS_Networks_Admin();
		$user_id = $this->factory->user->create();
		grant_super_admin( $user_id );
		wp_set_current_user( $user_id );

		ob_start();
		$admin->site_info_network_field( get_main_site_id( get_main_network_id() ) );
		$html = ob_get_clean();

		$this->assertStringNotContainsString( 'name="wpmn_network_id"', $html );
		$this->assertStringContainsString( 'The primary site of a network cannot be moved.', $html );
	}

	public function test_saving_site_info_moves_the_site_and_redirects_to_sites() {
		$admin       = new WP_MS_Networks_Admin();
		$site_id     = $this->factory->blog->create();
		$destination = $this->factory->network->create();
		$user_id     = $this->factory->user->create();
		grant_super_admin( $user_id );
		wp_set_current_user( $user_id );

		$old_screen  = $GLOBALS['current_screen'] ?? null;
		$old_pagenow = $GLOBALS['pagenow'] ?? null;
		$old_request = $_REQUEST;
		$old_post    = $_POST;

		set_current_screen( 'site-info-network' );
		$GLOBALS['pagenow'] = 'site-info.php';
		$_REQUEST          = array(
			'action'   => 'update-site',
			'_wpnonce' => wp_create_nonce( 'edit-site' ),
		);
		$_POST             = array(
			'id'              => (string) $site_id,
			'wpmn_network_id' => (string) $destination,
		);

		try {
			$site = get_site( $site_id );
			$admin->move_site_after_info_update( $site, $site );
			$this->assertSame( $destination, (int) get_site( $site_id )->network_id );

			$redirect = $admin->redirect_after_site_info_move( 'site-info.php?update=updated&id=' . $site_id, 302 );
			$this->assertStringContainsString( 'sites.php?site_moved=1', $redirect );
		} finally {
			$_REQUEST                 = $old_request;
			$_POST                    = $old_post;
			$GLOBALS['current_screen'] = $old_screen;
			$GLOBALS['pagenow']       = $old_pagenow;
		}
	}

	public function test_expired_site_info_nonce_cannot_move_a_site() {
		$admin       = new WP_MS_Networks_Admin();
		$site_id     = $this->factory->blog->create();
		$destination = $this->factory->network->create();
		$user_id     = $this->factory->user->create();
		grant_super_admin( $user_id );
		wp_set_current_user( $user_id );

		$old_screen  = $GLOBALS['current_screen'] ?? null;
		$old_pagenow = $GLOBALS['pagenow'] ?? null;
		$old_request = $_REQUEST;
		$old_post    = $_POST;

		set_current_screen( 'site-info-network' );
		$GLOBALS['pagenow'] = 'site-info.php';
		$_REQUEST          = array(
			'action'   => 'update-site',
			'_wpnonce' => 'expired',
		);
		$_POST             = array(
			'id'              => (string) $site_id,
			'wpmn_network_id' => (string) $destination,
		);

		try {
			$site = get_site( $site_id );
			$admin->move_site_after_info_update( $site, $site );
			$this->assertSame( get_main_network_id(), (int) get_site( $site_id )->network_id );
			$redirect = $admin->redirect_after_site_info_move( 'site-info.php?update=updated&id=' . $site_id, 302 );
			$this->assertSame( 'site-info.php?update=updated&id=' . $site_id, $redirect );
		} finally {
			$_REQUEST                 = $old_request;
			$_POST                    = $old_post;
			$GLOBALS['current_screen'] = $old_screen;
			$GLOBALS['pagenow']       = $old_pagenow;
		}
	}

	public function test_site_manager_without_network_management_cannot_move_a_site() {
		$admin       = new WP_MS_Networks_Admin();
		$site_id     = $this->factory->blog->create();
		$destination = $this->factory->network->create();
		$user_id     = $this->factory->user->create();
		grant_super_admin( $user_id );
		wp_set_current_user( $user_id );

		$old_screen  = $GLOBALS['current_screen'] ?? null;
		$old_pagenow = $GLOBALS['pagenow'] ?? null;
		$old_request = $_REQUEST;
		$old_post    = $_POST;

		set_current_screen( 'site-info-network' );
		$GLOBALS['pagenow'] = 'site-info.php';
		$_REQUEST          = array(
			'action'   => 'update-site',
			'_wpnonce' => wp_create_nonce( 'edit-site' ),
		);
		$_POST             = array(
			'id'              => (string) $site_id,
			'wpmn_network_id' => (string) $destination,
		);
		add_filter( 'wpms_has_user_global_access', '__return_false' );

		try {
			$this->assertFalse( current_user_can( 'manage_networks' ) );
			$site = get_site( $site_id );
			$admin->move_site_after_info_update( $site, $site );
			$this->assertSame( get_main_network_id(), (int) get_site( $site_id )->network_id );
		} finally {
			remove_filter( 'wpms_has_user_global_access', '__return_false' );
			$_REQUEST                 = $old_request;
			$_POST                    = $old_post;
			$GLOBALS['current_screen'] = $old_screen;
			$GLOBALS['pagenow']       = $old_pagenow;
		}
	}

	public function test_primary_site_cannot_be_moved_by_a_forged_network_field() {
		$admin       = new WP_MS_Networks_Admin();
		$site_id     = get_main_site_id( get_main_network_id() );
		$destination = $this->factory->network->create();
		$user_id     = $this->factory->user->create();
		grant_super_admin( $user_id );
		wp_set_current_user( $user_id );

		$old_screen  = $GLOBALS['current_screen'] ?? null;
		$old_pagenow = $GLOBALS['pagenow'] ?? null;
		$old_request = $_REQUEST;
		$old_post    = $_POST;

		set_current_screen( 'site-info-network' );
		$GLOBALS['pagenow'] = 'site-info.php';
		$_REQUEST          = array(
			'action'   => 'update-site',
			'_wpnonce' => wp_create_nonce( 'edit-site' ),
		);
		$_POST             = array(
			'id'              => (string) $site_id,
			'wpmn_network_id' => (string) $destination,
		);

		try {
			$site = get_site( $site_id );
			$admin->move_site_after_info_update( $site, $site );
			$this->assertSame( get_main_network_id(), (int) get_site( $site_id )->network_id );
			$redirect = $admin->redirect_after_site_info_move( 'site-info.php?update=updated&id=' . $site_id, 302 );
			$this->assertStringContainsString( 'site-info.php?update=updated', $redirect );
			$this->assertStringContainsString( 'wpmn_site_move_failed=1', $redirect );
		} finally {
			$_REQUEST                 = $old_request;
			$_POST                    = $old_post;
			$GLOBALS['current_screen'] = $old_screen;
			$GLOBALS['pagenow']       = $old_pagenow;
		}
	}
}
