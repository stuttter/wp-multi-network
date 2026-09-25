<?php
/**
 * Tests for assigning subsites from the Edit Network screen.
 *
 * @since NEXT
 */

require_once TESTS_PLUGIN_DIR . '/wp-multi-network/includes/classes/class-wp-ms-networks-admin.php';
require_once TESTS_PLUGIN_DIR . '/wp-multi-network/includes/metaboxes/edit-network.php';

class WPMN_Tests_SiteAssignment extends WPMN_UnitTestCase {
	/**
	 * The root is visible, but only subsites get move controls.
	 *
	 * @since NEXT
	 */
	public function test_metabox_keeps_primary_site_out_of_move_list() {
		$network_id = get_main_network_id();
		$root_id    = get_main_site_id( $network_id );
		$site_id    = $this->factory->blog->create();

		ob_start();
		wpmn_edit_network_assign_sites_metabox( get_network( $network_id ) );
		$html = ob_get_clean();

		$this->assertStringContainsString( 'Primary site', $html );
		$this->assertStringContainsString( 'value="' . $site_id . '"', $html );
		$this->assertStringNotContainsString( 'name="move_sites[]" value="' . $root_id . '"', $html );
	}

	/**
	 * Search results offer subsites without offering another network's root.
	 *
	 * @since NEXT
	 */
	public function test_available_site_search_excludes_network_roots() {
		$other_id = $this->factory->network->create(
			array(
				'domain' => 'searchable-root.example.com',
				'path'   => '/',
			)
		);
		$root_id  = $this->factory->blog->create(
			array(
				'domain'  => 'searchable-root.example.com',
				'path'    => '/',
				'site_id' => $other_id,
			)
		);
		$site_id  = $this->factory->blog->create(
			array(
				'domain'  => 'searchable-subsite.example.com',
				'site_id' => $other_id,
			)
		);
		update_network_option( $other_id, 'main_site', $root_id );

		$old_get = $_GET;
		$_GET    = array( 'available_site_search' => 'searchable' );
		try {
			ob_start();
			wpmn_edit_network_assign_sites_metabox( get_network( get_main_network_id() ) );
			$html = ob_get_clean();
		} finally {
			$_GET = $old_get;
		}

		$this->assertStringContainsString( 'name="move_here_site_id" value="' . $site_id . '"', $html );
		$this->assertStringNotContainsString( 'name="move_here_site_id" value="' . $root_id . '"', $html );
	}

	/**
	 * Moving out requires a real destination; network roots cannot be moved.
	 *
	 * @since NEXT
	 */
	public function test_outgoing_moves_require_a_named_network_and_preserve_roots() {
		$source_id      = get_main_network_id();
		$root_id        = get_main_site_id( $source_id );
		$site_id        = $this->factory->blog->create();
		$destination_id = $this->factory->network->create();

		$this->run_assignment( $source_id, array( $site_id, $root_id ), 0 );
		$this->assertSame( $source_id, (int) get_site( $site_id )->network_id );

		$this->run_assignment( $source_id, array( $site_id, $root_id ), $destination_id );
		$this->assertSame( $destination_id, (int) get_site( $site_id )->network_id );
		$this->assertSame( $source_id, (int) get_site( $root_id )->network_id );
	}

	/**
	 * An eligible subsite can come in, but another network's root cannot.
	 *
	 * @since NEXT
	 */
	public function test_incoming_move_rejects_other_network_root() {
		$source_id = get_main_network_id();
		$other_id  = $this->factory->network->create(
			array(
				'domain' => 'fixed-root.example.com',
				'path'   => '/',
			)
		);
		$root_id   = $this->factory->blog->create(
			array(
				'domain'  => 'fixed-root.example.com',
				'path'    => '/',
				'site_id' => $other_id,
			)
		);
		$site_id   = $this->factory->blog->create( array( 'site_id' => $other_id ) );
		update_network_option( $other_id, 'main_site', $root_id );

		$this->run_assignment( $source_id, array(), 0, $root_id );
		$this->assertSame( $other_id, (int) get_site( $root_id )->network_id );

		$this->run_assignment( $source_id, array(), 0, $site_id );
		$this->assertSame( $source_id, (int) get_site( $site_id )->network_id );
	}

	/**
	 * Invoke the protected save step without redirecting the test request.
	 *
	 * @since NEXT
	 *
	 * @param int   $network_id     Edited network ID.
	 * @param int[] $outgoing       Site IDs to move out.
	 * @param int   $destination_id Destination network ID.
	 * @param int   $incoming_id    Site ID to move in.
	 * @return void
	 */
	private function run_assignment( $network_id, $outgoing, $destination_id, $incoming_id = 0 ) {
		$old_get  = $_GET;
		$old_post = $_POST;
		$_GET     = array( 'id' => $network_id );
		$_POST    = array(
			'move_sites'        => $outgoing,
			'move_to_network'   => $destination_id,
			'move_here_site_id' => $incoming_id,
		);

		try {
			$method = new ReflectionMethod( 'WP_MS_Networks_Admin', 'handle_reassign_sites' );
			$method->setAccessible( true );
			$method->invoke( new WP_MS_Networks_Admin() );
		} finally {
			$_GET  = $old_get;
			$_POST = $old_post;
		}
	}
}
