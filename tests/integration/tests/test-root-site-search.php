<?php
/**
 * Tests for finding sites eligible to become a network's root site.
 *
 * @since NEXT
 */

require_once TESTS_PLUGIN_DIR . '/wp-multi-network/includes/classes/class-wp-ms-networks-admin.php';

class WPMN_Tests_RootSiteSearch extends WPMN_UnitTestCase {

	/**
	 * Search matches domain and path, but never offers a network's main site.
	 *
	 * @since NEXT
	 */
	public function test_search_excludes_network_main_sites() {
		$eligible_id = $this->factory->blog->create(
			array(
				'domain' => 'candidate.example.com',
				'path'   => '/searchable-path/',
			)
		);

		$network_id = $this->factory->network->create(
			array(
				'domain' => 'root.example.com',
				'path'   => '/',
			)
		);
		$root_id    = $this->factory->blog->create(
			array(
				'domain'  => 'root.example.com',
				'path'    => '/',
				'site_id' => $network_id,
			)
		);
		update_network_option( $network_id, 'main_site', $root_id );

		$admin = new WP_MS_Networks_Admin();
		update_blog_option( $eligible_id, 'blogname', 'Candidate Site' );

		$this->assertSame(
			array( array( 'id' => $eligible_id, 'url' => 'candidate.example.com/searchable-path/', 'domain' => 'candidate.example.com', 'path' => '/searchable-path/' ) ),
			$admin->find_eligible_root_sites( 'candidate' )
		);
		$this->assertSame(
			array( array( 'id' => $eligible_id, 'url' => 'candidate.example.com/searchable-path/', 'domain' => 'candidate.example.com', 'path' => '/searchable-path/' ) ),
			$admin->find_eligible_root_sites( 'searchable' )
		);
		$this->assertSame( array(), $admin->find_eligible_root_sites( 'root.example.com' ) );
		$this->assertSame( 'Candidate Site', $admin->get_eligible_root_site_name( $eligible_id ) );
		$this->assertWPError( $admin->get_eligible_root_site_name( $root_id ) );
		$this->assertWPError( $admin->get_eligible_root_site_name( 999999 ) );
	}

	/**
	 * Short terms are rejected before querying the sites table.
	 *
	 * @since NEXT
	 */
	public function test_search_requires_three_characters() {
		$admin = new WP_MS_Networks_Admin();

		$this->assertSame( array(), $admin->find_eligible_root_sites( 'ab' ) );
	}
}
