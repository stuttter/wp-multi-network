<?php
/**
 * Tests for opt-in synchronization of blog and network switches.
 */

class WPMN_Tests_Blog_Network_Switch extends WPMN_UnitTestCase {

	public function test_blog_switch_does_not_switch_network_by_default() {
		$original_network_id = get_current_network_id();
		$network_id          = $this->factory->network->create();
		$blog_id             = $this->factory->blog->create( array( 'network_id' => $network_id ) );
		$depth               = $this->network_stack_depth();

		switch_to_blog( $blog_id );
		try {
			$this->assertSame( $original_network_id, get_current_network_id() );
			$this->assertSame( $depth, $this->network_stack_depth() );
		} finally {
			restore_current_blog();
		}
	}

	public function test_opt_in_switches_and_restores_nested_networks() {
		$original_network_id = get_current_network_id();
		$original_blog_id    = get_current_blog_id();
		$network_b           = $this->factory->network->create();
		$network_c           = $this->factory->network->create();
		$blog_b              = $this->factory->blog->create( array( 'network_id' => $network_b ) );
		$blog_c              = $this->factory->blog->create( array( 'network_id' => $network_c ) );
		$depth               = $this->network_stack_depth();
		$switcher            = new WP_MS_Blog_Network_Switcher();
		$switcher->add_hooks();

		try {
			switch_to_blog( $blog_b );
			$this->assertSame( (int) $network_b, get_current_network_id() );
			$this->assertSame( $depth + 1, $this->network_stack_depth() );

			switch_to_blog( $blog_c );
			$this->assertSame( (int) $network_c, get_current_network_id() );
			$this->assertSame( $depth + 2, $this->network_stack_depth() );

			// Returning to the original network is still a nested switch.
			switch_to_blog( $original_blog_id );
			$this->assertSame( $original_network_id, get_current_network_id() );
			$this->assertSame( $depth + 3, $this->network_stack_depth() );

			restore_current_blog();
			$this->assertSame( (int) $network_c, get_current_network_id() );
			restore_current_blog();
			$this->assertSame( (int) $network_b, get_current_network_id() );
			restore_current_blog();
			$this->assertSame( $original_network_id, get_current_network_id() );
			$this->assertSame( $depth, $this->network_stack_depth() );
		} finally {
			$switcher->remove_hooks();
			while ( is_multisite() && ms_is_switched() ) {
				restore_current_blog();
			}
		}
	}

	public function test_same_network_and_same_blog_do_not_create_network_frames() {
		$network_id = get_current_network_id();
		$blog_id    = $this->factory->blog->create( array( 'network_id' => $network_id ) );
		$depth      = $this->network_stack_depth();
		$switcher   = new WP_MS_Blog_Network_Switcher();
		$switcher->add_hooks();

		try {
			switch_to_blog( $blog_id );
			$this->assertSame( $network_id, get_current_network_id() );
			$this->assertSame( $depth, $this->network_stack_depth() );

			switch_to_blog( $blog_id );
			$this->assertSame( $network_id, get_current_network_id() );
			$this->assertSame( $depth, $this->network_stack_depth() );

			restore_current_blog();
			restore_current_blog();
			$this->assertSame( $depth, $this->network_stack_depth() );
		} finally {
			$switcher->remove_hooks();
			while ( ms_is_switched() ) {
				restore_current_blog();
			}
		}
	}

	public function test_balanced_manual_network_switches_remain_owned_by_the_caller() {
		$original_network_id = get_current_network_id();
		$manual_network_id   = $this->factory->network->create();
		$blog_network_id     = $this->factory->network->create();
		$blog_id             = $this->factory->blog->create( array( 'network_id' => $blog_network_id ) );
		$depth               = $this->network_stack_depth();
		$switcher            = new WP_MS_Blog_Network_Switcher();
		$switcher->add_hooks();

		try {
			switch_to_network( $manual_network_id );
			switch_to_blog( $blog_id );
			$this->assertSame( (int) $blog_network_id, get_current_network_id() );

			switch_to_network( $original_network_id );
			$this->assertSame( $depth + 3, $this->network_stack_depth() );
			restore_current_network();
			$this->assertSame( (int) $blog_network_id, get_current_network_id() );

			restore_current_blog();
			$this->assertSame( (int) $manual_network_id, get_current_network_id() );
			$this->assertSame( $depth + 1, $this->network_stack_depth() );
			restore_current_network();
			$this->assertSame( $original_network_id, get_current_network_id() );
			$this->assertSame( $depth, $this->network_stack_depth() );
		} finally {
			$switcher->remove_hooks();
			while ( ms_is_switched() ) {
				restore_current_blog();
			}
			while ( $this->network_stack_depth() > $depth ) {
				restore_current_network();
			}
		}
	}

	public function test_missing_blog_and_network_do_not_push_network_frames() {
		$original_network_id = get_current_network_id();
		$network_id          = $this->factory->network->create();
		$blog_id             = $this->factory->blog->create( array( 'network_id' => $network_id ) );
		$depth               = $this->network_stack_depth();
		$switcher            = new WP_MS_Blog_Network_Switcher();
		$invalid_network_id = '999999999';
		$missing_network     = function( $site ) use ( $blog_id, &$invalid_network_id ) {
			if ( (int) $site->blog_id === (int) $blog_id ) {
				$site          = clone $site;
				$site->site_id = $invalid_network_id;
			}
			return $site;
		};
		$switcher->add_hooks();
		add_filter( 'get_site', $missing_network );

		try {
			switch_to_blog( $blog_id );
			$this->assertSame( $original_network_id, get_current_network_id() );
			$this->assertSame( $depth, $this->network_stack_depth() );
			restore_current_blog();

			$invalid_network_id = '0';
			switch_to_blog( $blog_id );
			$this->assertSame( $original_network_id, get_current_network_id() );
			$this->assertSame( $depth, $this->network_stack_depth() );
			restore_current_blog();

			remove_filter( 'get_site', $missing_network );
			// Core itself queries options for nonexistent sites on switch_to_blog().
			$switcher->switch_blog( 999999998, get_current_blog_id(), 'switch' );
			$this->assertSame( $original_network_id, get_current_network_id() );
			$this->assertSame( $depth, $this->network_stack_depth() );
			$switcher->switch_blog( get_current_blog_id(), 999999998, 'restore' );
		} finally {
			remove_filter( 'get_site', $missing_network );
			$switcher->remove_hooks();
			while ( ms_is_switched() ) {
				restore_current_blog();
			}
		}
	}

	public function test_unbalanced_manual_network_switch_is_not_restored_as_our_frame() {
		$original_network_id = get_current_network_id();
		$blog_network_id     = $this->factory->network->create();
		$manual_network_id   = $this->factory->network->create();
		$blog_id             = $this->factory->blog->create( array( 'network_id' => $blog_network_id ) );
		$depth               = $this->network_stack_depth();
		$switcher            = new WP_MS_Blog_Network_Switcher();
		$switcher->add_hooks();

		try {
			switch_to_blog( $blog_id );
			switch_to_network( $manual_network_id );
			restore_current_blog();

			// The caller broke nesting; do not pop its manual network frame.
			$this->assertSame( (int) $manual_network_id, get_current_network_id() );
			$this->assertSame( $depth + 2, $this->network_stack_depth() );

			restore_current_network();
			$this->assertSame( (int) $blog_network_id, get_current_network_id() );
			restore_current_network();
			$this->assertSame( $original_network_id, get_current_network_id() );
			$this->assertSame( $depth, $this->network_stack_depth() );
		} finally {
			$switcher->remove_hooks();
			while ( ms_is_switched() ) {
				restore_current_blog();
			}
			while ( $this->network_stack_depth() > $depth ) {
				restore_current_network();
			}
		}
	}

	private function network_stack_depth() {
		global $switched_network_stack;

		return is_array( $switched_network_stack ) ? count( $switched_network_stack ) : 0;
	}
}
