<?php
/**
 * Tests for the Edit Network site-assignment metabox.
 */

require_once TESTS_PLUGIN_DIR . '/wp-multi-network/includes/metaboxes/edit-network.php';

class WPMN_Tests_SiteAssignmentMetabox extends WPMN_UnitTestCase {
	public function test_small_lists_show_site_addresses_without_blog_names() {
		$network_id = $this->factory->network->create();
		$site_id    = $this->factory->blog->create(
			array(
				'domain' => 'available.example.test',
				'path'   => '/sample/',
			)
		);
		update_blog_option( $site_id, 'blogname', 'A name not used in the list' );

		ob_start();
		wpmn_edit_network_assign_sites_metabox( get_network( $network_id ) );
		$html = ob_get_clean();

		$this->assertStringContainsString( 'name="from[]"', $html );
		$this->assertStringContainsString( 'name="to[]"', $html );
		$this->assertStringContainsString( 'available.example.test/sample/', $html );
		$this->assertStringNotContainsString( 'A name not used in the list', $html );
	}

	public function test_too_many_assigned_sites_hide_both_lists() {
		$network_id = get_main_network_id();
		$site       = get_site( get_main_site_id( $network_id ) );
		$filter     = static function ( $sites, $query ) use ( $network_id, $site ) {
			if ( (int) $query->query_vars['network_id'] === $network_id && 26 === (int) $query->query_vars['number'] ) {
				return array_fill( 0, 26, $site );
			}

			return $sites;
		};
		add_filter( 'sites_pre_query', $filter, 10, 2 );

		try {
			ob_start();
			wpmn_edit_network_assign_sites_metabox( get_network( $network_id ) );
			$html = ob_get_clean();
		} finally {
			remove_filter( 'sites_pre_query', $filter, 10 );
		}

		$this->assertStringNotContainsString( 'name="from[]"', $html );
		$this->assertStringNotContainsString( 'name="to[]"', $html );
		$this->assertStringContainsString( network_admin_url( 'sites.php' ), $html );
		$this->assertStringContainsString( 'Move bulk action', $html );
	}

	public function test_twenty_five_assigned_sites_keep_the_lists() {
		$network_id = get_main_network_id();
		$site       = get_site( get_main_site_id( $network_id ) );
		$filter     = static function ( $sites, $query ) use ( $network_id, $site ) {
			if ( (int) $query->query_vars['network_id'] === $network_id && 26 === (int) $query->query_vars['number'] ) {
				return array_fill( 0, 25, $site );
			}

			return $sites;
		};
		add_filter( 'sites_pre_query', $filter, 10, 2 );

		try {
			ob_start();
			wpmn_edit_network_assign_sites_metabox( get_network( $network_id ) );
			$html = ob_get_clean();
		} finally {
			remove_filter( 'sites_pre_query', $filter, 10 );
		}

		$this->assertStringContainsString( 'name="from[]"', $html );
		$this->assertStringContainsString( 'name="to[]"', $html );
		$this->assertStringNotContainsString( 'There are too many sites', $html );
	}

	public function test_too_many_available_sites_hide_both_lists() {
		$network_id = get_main_network_id();
		$site       = get_site( get_main_site_id( $network_id ) );
		$filter     = static function ( $sites, $query ) use ( $site ) {
			if ( ! empty( $query->query_vars['network__not_in'] ) && 26 === (int) $query->query_vars['number'] ) {
				return array_fill( 0, 26, $site );
			}

			return $sites;
		};
		add_filter( 'sites_pre_query', $filter, 10, 2 );

		try {
			ob_start();
			wpmn_edit_network_assign_sites_metabox( get_network( $network_id ) );
			$html = ob_get_clean();
		} finally {
			remove_filter( 'sites_pre_query', $filter, 10 );
		}

		$this->assertStringNotContainsString( 'name="from[]"', $html );
		$this->assertStringNotContainsString( 'name="to[]"', $html );
		$this->assertStringContainsString( network_admin_url( 'sites.php' ), $html );
	}
}
