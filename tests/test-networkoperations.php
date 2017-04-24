<?php

class WPMN_Tests_NetworkOperations extends WP_UnitTestCase {
	public function test_network_exists() {
		$network = $this->factory->network->create();
		$this->assertTrue( network_exists( $network ) !== false );
	}

	public function test_network_exists_with_invalid_id() {
		$this->assertFalse( network_exists( -1 ), 'Network IDs must be positive' );
	}

	public function test_move_site() {
		// Grab some example data
		$site_id = $this->factory->blog->create();
		$site = $this->factory->blog->get_object_by_id( $site_id );

		$other_network_id = $this->factory->network->create( array( 'domain' => 'example.com', 'path' => '/', ) );
		$other_network = $this->factory->network->get_object_by_id( $other_network_id );

		// Check we start out in the main network
		$this->assertEquals( 1, $site->site_id, 'Site should be created in main network by default' );

		// Move the site to the other network
		$result = move_site( $site_id, $other_network_id );
		$this->assertFalse( is_bool( $result ), 'Site should be moved without bailing' );
		$this->assertFalse( is_wp_error( $result ), 'Site should be moved without error' );

		// Reload site data
		$site = $this->factory->blog->get_object_by_id( $site_id );

		$this->assertEquals( $other_network_id, $site->site_id, 'Site should be in other network after move' );

		// Move it back
		$result = move_site( $site_id, 1 );
		$this->assertFalse( is_bool( $result ), 'Site should be moved without bailing' );
		$this->assertFalse( is_wp_error( $result ), 'Site should be moved without error' );

		// Reload site data again
		$site = $this->factory->blog->get_object_by_id( $site_id );
		$this->assertEquals( 1, $site->site_id, 'Site should be back in main network' );
	}

	public function test_switch_site() {
		// Site first site and network
		$network_id = $this->factory->network->create( array( 'domain' => 'wordpress.com', 'path' => '/', ) );
		$site_id = $this->factory->blog->create( array( 'site_id' => $network_id ) );

		// Site second site and network
		$other_network_id = $this->factory->network->create( array( 'domain' => 'example.com', 'path' => '/', ) );
		$site_id_diffent_network = $this->factory->blog->create( array( 'site_id' => $other_network_id ) );

		// Assert default network is 1
		$this->assertEquals( 1, get_current_network_id(), 'Network should start as 1' );

		// Switch to first site
		switch_to_blog( $site_id );
		$this->assertEquals( $network_id, get_current_network_id(), 'Network should change to '. $network_id );
		
		// Switch to second site
		switch_to_blog( $site_id_diffent_network );
		$this->assertEquals( $other_network_id, get_current_network_id(), 'Network should change to '. $other_network_id );
		// Switch to first site
		restore_current_blog();

		$this->assertEquals( $network_id, get_current_network_id(), 'Network should change to '. $network_id );

		// Restore default site / network
		restore_current_blog();

		$this->assertEquals( 1, get_current_network_id(), 'Network should end as 1' );

	}

	public function test_switch_to_network() {
		global $current_site;

		$other_network_id = $this->factory->network->create();

		// Check we're on the main network first
		$this->assertEquals( 1, $current_site->id, 'Current network should be main network before switching' );

		$this->assertTrue( switch_to_network( $other_network_id ), 'Switching network should work' );

		$this->assertEquals( $other_network_id, $current_site->id, 'Switching network should switch the network' );

		$this->assertTrue( restore_current_network(), 'Switching back should work' );

		$this->assertEquals( 1, $current_site->id, 'Switching back should switch the network' );
	}
}
