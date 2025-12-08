<?php
/**
 * Tests for upload path handling.
 */

class WPMN_Tests_Upload_Paths extends WPMN_UnitTestCase {

	/**
	 * Test that upload paths are correctly set when creating a new network.
	 */
	public function test_upload_path_without_duplication() {
		// Create a new network.
		$network_id = add_network(
			array(
				'domain'       => 'example.org',
				'path'         => '/',
				'site_name'    => 'Test Network',
				'network_name' => 'Test Network',
			)
		);

		$this->assertNotWPError( $network_id, 'Network should be created successfully' );

		// Get the main site for the network.
		$main_site_id = get_main_site_for_network( $network_id );
		$this->assertNotEmpty( $main_site_id, 'Main site should exist for the network' );

		// Get the upload path.
		$upload_path = get_blog_option( $main_site_id, 'upload_path' );

		// Check that the path doesn't contain duplicate site-specific directories.
		$site_suffix = '/sites/' . $main_site_id;
		$count       = substr_count( $upload_path, $site_suffix );

		$this->assertLessThanOrEqual( 1, $count, 'Upload path should not contain duplicate site-specific directories' );

		// If the path contains the suffix, it should only appear once.
		if ( false !== strpos( $upload_path, $site_suffix ) ) {
			$this->assertEquals( 1, $count, 'Site-specific directory should appear exactly once in upload path' );
		}
	}

	/**
	 * Test upload path when ms_files_rewriting is enabled.
	 */
	public function test_upload_path_with_files_rewriting() {
		// Enable files rewriting.
		update_site_option( 'ms_files_rewriting', 1 );

		// Create a new network.
		$network_id = add_network(
			array(
				'domain'       => 'example.net',
				'path'         => '/',
				'site_name'    => 'Test Network with Rewriting',
				'network_name' => 'Test Network with Rewriting',
			)
		);

		$this->assertNotWPError( $network_id, 'Network should be created successfully with files rewriting' );

		// Get the main site for the network.
		$main_site_id = get_main_site_for_network( $network_id );
		$this->assertNotEmpty( $main_site_id, 'Main site should exist for the network' );

		// With files rewriting enabled, the upload path may not be set or may be empty.
		$upload_path = get_blog_option( $main_site_id, 'upload_path' );

		// The behavior with files rewriting may vary, but it should not contain duplicates.
		if ( ! empty( $upload_path ) ) {
			$site_suffix = '/sites/' . $main_site_id;
			$count       = substr_count( $upload_path, $site_suffix );
			$this->assertLessThanOrEqual( 1, $count, 'Upload path should not contain duplicate site-specific directories with files rewriting' );
		}

		// Clean up.
		update_site_option( 'ms_files_rewriting', 0 );
	}

	/**
	 * Test upload path in multisite environment.
	 */
	public function test_upload_path_multisite() {
		// Verify we're in a multisite environment.
		$this->assertTrue( is_multisite(), 'Tests should run in multisite environment' );

		// Create a new network.
		$network_id = add_network(
			array(
				'domain'       => 'multisite.test',
				'path'         => '/',
				'site_name'    => 'Multisite Test',
				'network_name' => 'Multisite Test',
			)
		);

		$this->assertNotWPError( $network_id, 'Network should be created in multisite environment' );

		// Get the main site for the network.
		$main_site_id = get_main_site_for_network( $network_id );
		$this->assertNotEmpty( $main_site_id, 'Main site should exist' );

		// Get the upload path.
		$upload_path = get_blog_option( $main_site_id, 'upload_path' );

		// In multisite, the path should use /sites/ prefix if present.
		if ( ! empty( $upload_path ) && false !== strpos( $upload_path, '/sites/' ) ) {
			$this->assertStringContainsString( '/sites/' . $main_site_id, $upload_path, 'Upload path should contain /sites/{blog_id} format in multisite' );

			// Ensure no duplication.
			$count = substr_count( $upload_path, '/sites/' . $main_site_id );
			$this->assertEquals( 1, $count, 'Site-specific path should appear exactly once' );
		}
	}

	/**
	 * Test that upload_path is set correctly without files rewriting.
	 */
	public function test_upload_path_without_files_rewriting() {
		// Ensure files rewriting is disabled.
		update_site_option( 'ms_files_rewriting', 0 );

		// Create a new network.
		$network_id = add_network(
			array(
				'domain'       => 'no-rewrite.test',
				'path'         => '/',
				'site_name'    => 'No Rewrite Test',
				'network_name' => 'No Rewrite Test',
			)
		);

		$this->assertNotWPError( $network_id, 'Network should be created without files rewriting' );

		// Get the main site for the network.
		$main_site_id = get_main_site_for_network( $network_id );
		$this->assertNotEmpty( $main_site_id, 'Main site should exist' );

		// Get the upload path and URL.
		$upload_path     = get_blog_option( $main_site_id, 'upload_path' );
		$upload_url_path = get_blog_option( $main_site_id, 'upload_url_path' );

		// Without files rewriting in WordPress > 3.7, paths should be set.
		global $wp_version;
		if ( version_compare( $wp_version, '3.7', '>' ) ) {
			$this->assertNotEmpty( $upload_path, 'Upload path should be set without files rewriting in WP > 3.7' );

			// Check for proper path structure.
			$this->assertStringContainsString( '/uploads', $upload_path, 'Upload path should contain /uploads' );

			// Verify no duplication of site-specific path.
			if ( defined( 'MULTISITE' ) && MULTISITE ) {
				$site_suffix = '/sites/' . $main_site_id;
				if ( false !== strpos( $upload_path, $site_suffix ) ) {
					$count = substr_count( $upload_path, $site_suffix );
					$this->assertEquals( 1, $count, 'Site-specific directory should not be duplicated' );
				}
			}
		}
	}

	/**
	 * Test upload path structure for consistency.
	 */
	public function test_upload_path_structure() {
		// Create a new network.
		$network_id = add_network(
			array(
				'domain'       => 'structure.test',
				'path'         => '/',
				'site_name'    => 'Structure Test',
				'network_name' => 'Structure Test',
			)
		);

		$this->assertNotWPError( $network_id, 'Network should be created successfully' );

		// Get the main site for the network.
		$main_site_id = get_main_site_for_network( $network_id );
		$upload_path  = get_blog_option( $main_site_id, 'upload_path' );

		if ( ! empty( $upload_path ) ) {
			// Path should not have consecutive slashes (except in protocol).
			$path_without_protocol = preg_replace( '#^[a-z]+://#', '', $upload_path );
			$this->assertStringNotContainsString( '//', $path_without_protocol, 'Upload path should not contain consecutive slashes' );

			// Path should not end with a slash.
			$this->assertStringEndsNotWith( '/', $upload_path, 'Upload path should not end with a slash' );

			// If it contains /sites/, it should be followed by a number.
			if ( false !== strpos( $upload_path, '/sites/' ) ) {
				$this->assertMatchesRegularExpression( '#/sites/\d+#', $upload_path, 'Sites path should be followed by a numeric blog ID' );
			}
		}
	}

	/**
	 * Test that existing correct paths are not overwritten.
	 */
	public function test_upload_path_preservation() {
		// Disable files rewriting to ensure the code path is exercised.
		update_site_option( 'ms_files_rewriting', 0 );

		// Create a new network.
		$network_id = add_network(
			array(
				'domain'       => 'preserve.test',
				'path'         => '/',
				'site_name'    => 'Preserve Test',
				'network_name' => 'Preserve Test',
			)
		);

		$this->assertNotWPError( $network_id, 'Network should be created successfully' );

		// Get the main site for the network.
		$main_site_id = get_main_site_for_network( $network_id );

		// Get the initial upload path.
		$initial_upload_path = get_blog_option( $main_site_id, 'upload_path' );

		// If a path was set and contains the site-specific directory, verify it's correct.
		if ( ! empty( $initial_upload_path ) && false !== strpos( $initial_upload_path, '/sites/' . $main_site_id ) ) {
			// Verify no duplication.
			$count = substr_count( $initial_upload_path, '/sites/' . $main_site_id );
			$this->assertEquals( 1, $count, 'Initial upload path should not have duplicated site-specific directory' );

			// The path should be preserved and not contain any doubled segments.
			$this->assertStringNotContainsString( '/sites/' . $main_site_id . '/sites/' . $main_site_id, $initial_upload_path, 'Upload path should never contain doubled site-specific directories' );
		}
	}
}
