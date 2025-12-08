<?php
/**
 * Tests for upload path handling.
 *
 * @group upload
 * @group multisite
 * @ticket 136
 */

class WPMN_Tests_Upload_Paths extends WPMN_UnitTestCase {

	/**
	 * Shared network ID for tests to reduce redundant network creation.
	 *
	 * @var int
	 */
	protected static $network_id;

	/**
	 * Set up before class to create a shared test network.
	 */
	public static function wpSetUpBeforeClass() {
		self::$network_id = add_network(
			array(
				'domain'       => 'shared-test.example',
				'path'         => '/',
				'site_name'    => 'Shared Test Network',
				'network_name' => 'Shared Test Network',
			)
		);
	}

	/**
	 * Clean up after class.
	 */
	public static function wpTearDownAfterClass() {
		if ( ! empty( self::$network_id ) ) {
			delete_network( self::$network_id, true );
		}
	}

	/**
	 * Helper method to assert upload path has no duplicate site-specific directories.
	 *
	 * @param int    $site_id     The site ID to check.
	 * @param string $upload_path The upload path to verify.
	 * @param string $message     Custom assertion message.
	 */
	protected function assertUploadPathNoDuplicates( $site_id, $upload_path, $message = '' ) {
		if ( empty( $upload_path ) ) {
			return;
		}

		$site_suffix = '/sites/' . $site_id;
		$count       = substr_count( $upload_path, $site_suffix );

		if ( empty( $message ) ) {
			$message = "Upload path should not contain duplicate site-specific directories for site {$site_id}";
		}

		$this->assertLessThanOrEqual( 1, $count, $message );

		// If the path contains the suffix, it should appear exactly once.
		if ( false !== strpos( $upload_path, $site_suffix ) ) {
			$this->assertEquals( 1, $count, "Site-specific directory should appear exactly once in upload path for site {$site_id}" );
		}
	}

	/**
	 * Test that upload paths are correctly set when creating a new network.
	 *
	 * @group upload-paths
	 * @ticket 136
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
		$this->assertUploadPathNoDuplicates( $main_site_id, $upload_path, 'Upload path should not contain duplicate site-specific directories' );
	}

	/**
	 * Test upload path when ms_files_rewriting is enabled.
	 *
	 * @group upload-paths
	 * @group files-rewriting
	 * @ticket 136
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
		$this->assertUploadPathNoDuplicates( $main_site_id, $upload_path, 'Upload path should not contain duplicate site-specific directories with files rewriting' );

		// Clean up.
		update_site_option( 'ms_files_rewriting', 0 );
	}

	/**
	 * Test upload path in multisite environment.
	 *
	 * @group upload-paths
	 * @group multisite
	 * @ticket 136
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

		// In multisite, the path should use /sites/ directory if present.
		if ( ! empty( $upload_path ) && false !== strpos( $upload_path, '/sites/' ) ) {
			$this->assertStringContainsString( '/sites/' . $main_site_id, $upload_path, 'Upload path should contain /sites/{blog_id} format in multisite' );
		}

		// Ensure no duplication.
		$this->assertUploadPathNoDuplicates( $main_site_id, $upload_path, 'Site-specific path should appear exactly once' );
	}

	/**
	 * Test that upload_path is set correctly without files rewriting.
	 *
	 * @group upload-paths
	 * @group no-files-rewriting
	 * @ticket 136
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
				$this->assertUploadPathNoDuplicates( $main_site_id, $upload_path, 'Site-specific directory should not be duplicated' );
			}
		}
	}

	/**
	 * Test upload path structure for consistency.
	 *
	 * @group upload-paths
	 * @group path-structure
	 * @ticket 136
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
			$path_without_protocol = preg_replace( '#^[a-zA-Z]+://#i', '', $upload_path );
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
	 *
	 * @group upload-paths
	 * @group path-preservation
	 * @ticket 136
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

		// Verify no duplication.
		$this->assertUploadPathNoDuplicates( $main_site_id, $initial_upload_path, 'Initial upload path should not have duplicated site-specific directory' );

		// The path should be preserved and not contain any doubled segments.
		if ( ! empty( $initial_upload_path ) ) {
			$this->assertStringNotContainsString( '/sites/' . $main_site_id . '/sites/' . $main_site_id, $initial_upload_path, 'Upload path should never contain doubled site-specific directories' );
		}
	}

	/**
	 * Test upload path with subdirectory installation.
	 *
	 * @group upload-paths
	 * @group subdirectory
	 * @ticket 136
	 */
	public function test_upload_path_subdirectory_install() {
		// Create a network with a subdirectory path.
		$network_id = add_network(
			array(
				'domain'       => 'subdir.example.com',
				'path'         => '/subdir/',
				'site_name'    => 'Subdirectory Network',
				'network_name' => 'Subdirectory Network',
			)
		);

		$this->assertNotWPError( $network_id, 'Network with subdirectory should be created' );

		$main_site_id = get_main_site_for_network( $network_id );
		$upload_path  = get_blog_option( $main_site_id, 'upload_path' );

		// Verify path doesn't have duplicates.
		$this->assertUploadPathNoDuplicates( $main_site_id, $upload_path, 'Subdirectory network upload path should not duplicate site-specific directories' );
	}

	/**
	 * Test upload path consistency across multiple sites in the same network.
	 *
	 * @group upload-paths
	 * @group multiple-sites
	 * @ticket 136
	 */
	public function test_upload_path_multiple_sites() {
		if ( empty( self::$network_id ) ) {
			$this->markTestSkipped( 'Shared network not available' );
		}

		// Create multiple sites in the same network.
		$site_ids = array();
		for ( $i = 0; $i < 3; $i++ ) {
			switch_to_network( self::$network_id );
			$site_id = wpmu_create_blog(
				'site' . $i . '.shared-test.example',
				'/',
				'Test Site ' . $i,
				get_current_user_id(),
				array(),
				self::$network_id
			);
			restore_current_network();

			if ( ! is_wp_error( $site_id ) ) {
				$site_ids[] = $site_id;
			}
		}

		// Verify each site has proper upload path without duplication.
		foreach ( $site_ids as $site_id ) {
			$upload_path = get_blog_option( $site_id, 'upload_path' );
			$this->assertUploadPathNoDuplicates( $site_id, $upload_path, "Site {$site_id} upload path should not have duplicate site-specific directories" );
		}
	}

	/**
	 * Test upload URL path consistency with upload path.
	 *
	 * @group upload-paths
	 * @group upload-urls
	 * @ticket 136
	 */
	public function test_upload_url_path_consistency() {
		$network_id = add_network(
			array(
				'domain'       => 'url-test.example',
				'path'         => '/',
				'site_name'    => 'URL Test Network',
				'network_name' => 'URL Test Network',
			)
		);

		$this->assertNotWPError( $network_id, 'Network should be created' );

		$main_site_id    = get_main_site_for_network( $network_id );
		$upload_path     = get_blog_option( $main_site_id, 'upload_path' );
		$upload_url_path = get_blog_option( $main_site_id, 'upload_url_path' );

		// If both paths are set, they should have consistent site-specific segments.
		if ( ! empty( $upload_path ) && ! empty( $upload_url_path ) ) {
			$path_suffix = '/sites/' . $main_site_id;
			$path_has_suffix = false !== strpos( $upload_path, $path_suffix );
			$url_has_suffix  = false !== strpos( $upload_url_path, $path_suffix );

			// Both should either have or not have the suffix.
			$this->assertEquals( $path_has_suffix, $url_has_suffix, 'Upload path and URL path should have consistent site-specific segments' );

			// Neither should have duplicates.
			if ( $path_has_suffix ) {
				$this->assertUploadPathNoDuplicates( $main_site_id, $upload_path, 'Upload path should not have duplicate site-specific directories' );
				$this->assertUploadPathNoDuplicates( $main_site_id, $upload_url_path, 'Upload URL path should not have duplicate site-specific directories' );
			}
		}
	}

	/**
	 * Test upload path with network-level cloning.
	 *
	 * @group upload-paths
	 * @group network-cloning
	 * @ticket 136
	 */
	public function test_upload_path_with_network_cloning() {
		// Create a source network.
		$source_network_id = add_network(
			array(
				'domain'       => 'source.example.com',
				'path'         => '/',
				'site_name'    => 'Source Network',
				'network_name' => 'Source Network',
			)
		);

		$this->assertNotWPError( $source_network_id, 'Source network should be created' );

		// Create a cloned network.
		$cloned_network_id = add_network(
			array(
				'domain'        => 'cloned.example.com',
				'path'          => '/',
				'site_name'     => 'Cloned Network',
				'network_name'  => 'Cloned Network',
				'clone_network' => $source_network_id,
			)
		);

		$this->assertNotWPError( $cloned_network_id, 'Cloned network should be created' );

		// Get upload paths for both networks.
		$source_site_id = get_main_site_for_network( $source_network_id );
		$cloned_site_id = get_main_site_for_network( $cloned_network_id );

		$source_upload_path = get_blog_option( $source_site_id, 'upload_path' );
		$cloned_upload_path = get_blog_option( $cloned_site_id, 'upload_path' );

		// Both should have proper paths without duplication.
		$this->assertUploadPathNoDuplicates( $source_site_id, $source_upload_path, 'Source network upload path should not have duplicates' );
		$this->assertUploadPathNoDuplicates( $cloned_site_id, $cloned_upload_path, 'Cloned network upload path should not have duplicates' );
	}
}
