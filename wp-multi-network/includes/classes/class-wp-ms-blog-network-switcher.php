<?php
/**
 * Opt-in synchronization of the current network with blog switches.
 *
 * @package WPMN
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Pairs network context changes with WordPress's blog-switch stack.
 *
 * This is deliberately not enabled by default. A blog switch normally leaves
 * the current network unchanged, and other plugins may rely on that behavior.
 */
class WP_MS_Blog_Network_Switcher {

	/**
	 * One frame for every blog switch observed by this handler.
	 *
	 * @var array<int, array{previous_network_id:int, target_network_id:int, stack_depth:int, switched:bool}>
	 */
	private $frames = array();

	/**
	 * Registers the blog-switch handler.
	 *
	 * @return void
	 */
	public function add_hooks() {
		// Run before Core's role-switch callback and other ordinary switch_blog listeners.
		add_action( 'switch_blog', array( $this, 'switch_blog' ), 0, 3 );
	}

	/**
	 * Unregisters the handler.
	 *
	 * @return void
	 */
	public function remove_hooks() {
		remove_action( 'switch_blog', array( $this, 'switch_blog' ), 0 );
	}

	/**
	 * Switches the network for a blog switch, then restores only its own frame.
	 *
	 * @param int    $new_blog_id  Newly current blog ID.
	 * @param int    $prev_blog_id Previously current blog ID.
	 * @param string $context      Either 'switch' or 'restore'.
	 * @return void
	 */
	public function switch_blog( $new_blog_id, $prev_blog_id, $context ) {
		global $switched_network_stack;

		if ( 'restore' === $context ) {
			if ( empty( $this->frames ) ) {
				return;
			}

			$frame = array_pop( $this->frames );
			if ( ! $frame['switched'] ) {
				return;
			}

			// A manually opened network frame belongs to its caller, not to us.
			$stack = is_array( $switched_network_stack ) ? $switched_network_stack : array();
			$top   = end( $stack );
			if (
				count( $stack ) !== $frame['stack_depth'] + 1 ||
				! is_object( $top ) ||
				! isset( $top->id, $top->domain ) ||
				(int) $top->id !== $frame['previous_network_id'] ||
				get_current_network_id() !== $frame['target_network_id']
			) {
				return;
			}

			restore_current_network();
			return;
		}

		if ( 'switch' !== $context ) {
			return;
		}

		$previous_network_id = get_current_network_id();
		$stack_depth         = is_array( $switched_network_stack ) ? count( $switched_network_stack ) : 0;
		$frame_index         = count( $this->frames );
		$this->frames[]      = array(
			'previous_network_id' => $previous_network_id,
			'target_network_id'   => $previous_network_id,
			'stack_depth'         => $stack_depth,
			'switched'            => false,
		);

		$site = get_site( $new_blog_id );
		if ( ! $site instanceof WP_Site ) {
			return;
		}

		$target_network_id = (int) $site->site_id;
		if ( $target_network_id <= 0 || $target_network_id === $previous_network_id ) {
			return;
		}

		// Validate before switch_to_network() can push a frame onto its stack.
		if ( ! switch_to_network( $target_network_id, true ) ) {
			return;
		}

		$this->frames[ $frame_index ] = array(
			'previous_network_id' => $previous_network_id,
			'target_network_id'   => $target_network_id,
			'stack_depth'         => $stack_depth,
			'switched'            => true,
		);
	}
}
