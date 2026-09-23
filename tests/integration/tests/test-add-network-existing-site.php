<?php
/**
 * Tests for adding a network with an existing site as root site.
 *
 * @since NEXT
 */

class WPMN_Tests_AddNetworkExistingSite extends WPMN_UnitTestCase {

	/**
	 * Test adding a network with an existing site as root site.
	 *
	 * @since NEXT
	 */
	public function test_add_network_with_existing_site() {

		// Create a test user.
		$user_id = $this->factory->user->create(
			array(
				'role' => 'administrator',
			)
		);
		grant_super_admin( $user_id );

		// Create a site to use as the existing root site.
		$site_id = $this->factory->blog->create(
			array(
				'domain' => 'existing-site.example.com',
				'path'   => '/',
			)
		);

		// Create a network using the existing site.
		$network_id = add_network(
			array(
				'domain'           => 'existing-site.example.com',
				'path'             => '/',
				'site_name'        => 'Existing Site',
				'network_name'     => 'Test Network',
				'user_id'          => $user_id,
				'network_admin_id' => $user_id,
				'existing_blog_id' => $site_id,
			)
		);

		// Verify network was created successfully.
		$this->assertNotWPError( $network_id, 'Network should be created successfully' );
		$this->assertIsInt( $network_id, 'Network ID should be an integer' );

		// Verify the site was moved to the new network.
		$site = get_site( $site_id );
		$this->assertEquals( $network_id, (int) $site->network_id, 'Site should be in the new network' );

		// Verify the site is the main site of the new network.
		$main_site_id = get_network_option( $network_id, 'main_site' );
		$this->assertEquals( $site_id, (int) $main_site_id, 'Site should be the main site of the new network' );
	}

	/**
	 * Test adding a network with a nonexistent site returns an error.
	 *
	 * @since NEXT
	 */
	public function test_add_network_with_nonexistent_site() {

		// Create a test user.
		$user_id = $this->factory->user->create(
			array(
				'role' => 'administrator',
			)
		);
		grant_super_admin( $user_id );

		// Attempt to create a network with a nonexistent site ID.
		$result = add_network(
			array(
				'domain'           => 'nonexistent.example.com',
				'path'             => '/',
				'site_name'        => 'Test Site',
				'network_name'     => 'Test Network',
				'user_id'          => $user_id,
				'network_admin_id' => $user_id,
				'existing_blog_id' => 999999,
			)
		);

		$this->assertWPError( $result, 'Should return WP_Error for nonexistent site' );
		$this->assertEquals( 'blog_not_exist', $result->get_error_code(), 'Error code should be blog_not_exist' );
	}

	/**
	 * Test adding a network with a main site of another network returns an error.
	 *
	 * @since NEXT
	 */
	public function test_add_network_with_main_site() {

		// Create a test user.
		$user_id = $this->factory->user->create(
			array(
				'role' => 'administrator',
			)
		);
		grant_super_admin( $user_id );

		// Create a network with add_network() so it has a real main site.
		$first_network_id = add_network(
			array(
				'domain'           => 'first-network.example.com',
				'path'             => '/',
				'site_name'        => 'First Network Site',
				'network_name'     => 'First Network',
				'user_id'          => $user_id,
				'network_admin_id' => $user_id,
			)
		);

		// Get the main site of the first network.
		$main_site_id = get_main_site_id( $first_network_id );

		// Attempt to create a second network using the main site.
		$result = add_network(
			array(
				'domain'           => 'second-network.example.com',
				'path'             => '/',
				'site_name'        => 'Test Site',
				'network_name'     => 'Second Network',
				'user_id'          => $user_id,
				'network_admin_id' => $user_id,
				'existing_blog_id' => $main_site_id,
			)
		);

		$this->assertWPError( $result, 'Should return WP_Error when using a main site' );
		$this->assertEquals( 'blog_is_main_site', $result->get_error_code(), 'Error code should be blog_is_main_site' );
	}

	/**
	 * Test adding a network without existing_blog_id creates a new site.
	 *
	 * @since NEXT
	 */
	public function test_add_network_without_existing_site() {

		// Create a test user.
		$user_id = $this->factory->user->create(
			array(
				'role' => 'administrator',
			)
		);
		grant_super_admin( $user_id );

		// Create a network without existing_blog_id.
		$network_id = add_network(
			array(
				'domain'           => 'site-test.example.com',
				'path'             => '/',
				'site_name'        => 'Test Site',
				'network_name'     => 'Test Network',
				'user_id'          => $user_id,
				'network_admin_id' => $user_id,
			)
		);

		// Verify network was created successfully.
		$this->assertNotWPError( $network_id, 'Network should be created successfully' );
		$this->assertIsInt( $network_id, 'Network ID should be an integer' );

		// Verify a new site was created as the main site.
		$main_site_id = get_network_option( $network_id, 'main_site' );
		$this->assertNotEmpty( $main_site_id, 'A main site should have been created' );

		$main_site = get_site( $main_site_id );
		$this->assertNotNull( $main_site, 'Main site should exist' );
		$this->assertEquals( 'site-test.example.com', $main_site->domain, 'Main site should have the network domain' );
	}
}
