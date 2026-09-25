<?php
/**
 * Tests the Sites-list bulk Move action.
 *
 * @since NEXT
 */

require_once TESTS_PLUGIN_DIR . '/wp-multi-network/includes/classes/class-wp-ms-networks-admin.php';

class WPMN_Tests_BulkMoveSites extends WPMN_UnitTestCase {
	public function test_native_bulk_action_is_not_intercepted_before_core_checks_its_nonce() {
		$admin       = new WP_MS_Networks_Admin();
		$site_id     = $this->factory->blog->create();
		$destination = $this->factory->network->create();
		$old_post    = $_POST;
		$_POST       = array(
			'action'              => 'wpmn_bulk_move',
			'site_ids'            => array( $site_id ),
			'destination_network' => $destination,
		);

		try {
			$admin->route_save_handlers();
			$this->assertSame( get_main_network_id(), (int) get_site( $site_id )->network_id );
		} finally {
			$_POST = $old_post;
		}
	}

	public function test_bottom_bulk_move_action_is_promoted_for_core() {
		$admin       = new WP_MS_Networks_Admin();
		$old_screen  = $GLOBALS['current_screen'] ?? null;
		$old_pagenow = $GLOBALS['pagenow'] ?? null;
		$old_post    = $_POST;

		set_current_screen( 'sites-network' );
		$GLOBALS['pagenow'] = 'sites.php';
		$_POST             = array(
			'action'  => '-1',
			'action2' => 'wpmn_bulk_move',
		);

		try {
			$admin->normalize_bottom_bulk_move_action();
			$this->assertSame( 'wpmn_bulk_move', $_POST['action'] );
		} finally {
			$GLOBALS['current_screen'] = $old_screen;
			$GLOBALS['pagenow']       = $old_pagenow;
			$_POST                    = $old_post;
		}
	}

	public function test_bulk_action_requires_network_management() {
		$admin = new WP_MS_Networks_Admin();
		wp_set_current_user( 0 );

		$actions = $admin->add_bulk_move_action( array() );
		$this->assertArrayNotHasKey( 'wpmn_bulk_move', $actions );

		$user_id = $this->factory->user->create();
		grant_super_admin( $user_id );
		wp_set_current_user( $user_id );

		$actions = $admin->add_bulk_move_action( array() );
		$this->assertArrayHasKey( 'wpmn_bulk_move', $actions );
		$this->assertSame( 'Move', $actions['wpmn_bulk_move'] );
	}

	public function test_preview_omits_the_source_network_from_destinations() {
		$admin       = new WP_MS_Networks_Admin();
		$site_id     = $this->factory->blog->create();
		$destination = $this->factory->network->create();
		$user_id     = $this->factory->user->create();
		grant_super_admin( $user_id );
		wp_set_current_user( $user_id );

		$old_get     = $_GET;
		$old_request = $_REQUEST;
		$_GET['site_ids']     = (string) $site_id;
		$_GET['_wpnonce']     = wp_create_nonce( 'wpmn_bulk_move_preview' );
		$_REQUEST['_wpnonce'] = $_GET['_wpnonce'];
		$preview_method      = new ReflectionMethod( WP_MS_Networks_Admin::class, 'page_bulk_move_sites' );
		$preview_method->setAccessible( true );

		try {
			ob_start();
			$preview_method->invoke( $admin );
			$html = ob_get_clean();
		} finally {
			$_GET     = $old_get;
			$_REQUEST = $old_request;
		}

		$this->assertStringContainsString( '<option value="' . $destination . '">', $html );
		$this->assertStringNotContainsString( '<option value="' . get_main_network_id() . '">', $html );
		$this->assertStringContainsString( '<li>' . get_site( $site_id )->domain . get_site( $site_id )->path . '</li>', $html );
		$this->assertStringContainsString( 'Moving sites does not change their addresses.', $html );
	}

	public function test_bulk_move_moves_multiple_subsites() {
		$admin       = new WP_MS_Networks_Admin();
		$first_id    = $this->factory->blog->create();
		$second_id   = $this->factory->blog->create();
		$destination = $this->factory->network->create(
			array(
				'domain' => 'destination.example.test',
				'path'   => '/',
			)
		);

		$result = $admin->bulk_move_sites_to_network( array( $first_id, $second_id ), $destination );

		$this->assertSame( array( 'moved' => 2, 'skipped' => 0, 'failed' => 0 ), $result );
		$this->assertSame( $destination, (int) get_site( $first_id )->network_id );
		$this->assertSame( $destination, (int) get_site( $second_id )->network_id );
	}

	public function test_bulk_move_rejects_primary_site_before_moving_anything() {
		$admin       = new WP_MS_Networks_Admin();
		$site_id     = $this->factory->blog->create();
		$primary_id  = get_main_site_id( get_main_network_id() );
		$destination = $this->factory->network->create();

		$result = $admin->bulk_move_sites_to_network( array( $site_id, $primary_id ), $destination );

		$this->assertWPError( $result );
		$this->assertSame( 'bulk_move_primary', $result->get_error_code() );
		$this->assertSame( get_main_network_id(), (int) get_site( $site_id )->network_id );
	}

	public function test_bulk_move_requires_a_real_destination() {
		$admin   = new WP_MS_Networks_Admin();
		$site_id = $this->factory->blog->create();

		$result = $admin->bulk_move_sites_to_network( array( $site_id ), 0 );

		$this->assertWPError( $result );
		$this->assertSame( 'bulk_move_destination', $result->get_error_code() );
		$this->assertSame( get_main_network_id(), (int) get_site( $site_id )->network_id );
	}

	public function test_bulk_move_rejects_missing_site_before_moving_anything() {
		$admin       = new WP_MS_Networks_Admin();
		$site_id     = $this->factory->blog->create();
		$destination = $this->factory->network->create();

		$result = $admin->bulk_move_sites_to_network( array( $site_id, 999999 ), $destination );

		$this->assertWPError( $result );
		$this->assertSame( 'bulk_move_missing', $result->get_error_code() );
		$this->assertSame( get_main_network_id(), (int) get_site( $site_id )->network_id );
	}

	public function test_bulk_move_skips_sites_already_in_destination() {
		$admin       = new WP_MS_Networks_Admin();
		$site_id     = $this->factory->blog->create();
		$destination = get_main_network_id();

		$result = $admin->bulk_move_sites_to_network( array( $site_id ), $destination );

		$this->assertSame( array( 'moved' => 0, 'skipped' => 1, 'failed' => 0 ), $result );
	}
}
