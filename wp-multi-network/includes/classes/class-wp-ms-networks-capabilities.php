<?php
/**
 * WP_MS_Networks_Capabilities class
 *
 * @package WPMN
 * @since 2.3.0
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Class responsible for managing capabilities.
 *
 * @since 2.3.0
 */
class WP_MS_Networks_Capabilities {

	/**
	 * Adds hooks for networks capabilities.
	 *
	 * @since 2.3.0
	 */
	public function add_hooks() {
		add_filter( 'map_meta_cap', array( $this, 'map_meta_cap' ), 10, 4 );
	}

	/**
	 * Ensures only global administrators have access to global capabilities.
	 *
	 * @since 2.3.0
	 *
	 * @param array  $caps    Array of required capabilities.
	 * @param string $cap     Capability to map.
	 * @param int    $user_id User ID.
	 * @param array  $args    Additional context for the capability check.
	 * @return array Filtered required capabilities.
	 */
	public function map_meta_cap( $caps, $cap, $user_id, $args ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed

		// Map our meta capabilities to primitive capabilities first.
		switch ( $cap ) {
			case 'edit_network':
				$caps = array( 'manage_networks' );
				break;
			case 'delete_network':
				$caps = array( 'delete_networks' );
				break;
		}

		// Check for primitive global capabilities.
		$required_global_caps = array_intersect( $caps, $this->get_global_capabilities() );
		if ( empty( $required_global_caps ) ) {
			return $caps;
		}

		// Check if the user has access to global capabilities.
		if ( ! $this->has_user_global_access( $user_id ) ) {
			$caps[] = 'do_not_allow';
		}

		return $caps;
	}

	/**
	 * Gets the primitive capabilities that should only be granted to global administrators.
	 *
	 * @since 2.3.0
	 *
	 * @return array List of primitive global capabilities.
	 */
	private function get_global_capabilities() {
		$global_capabilities = array(
			'manage_networks',
			'list_networks',
			'create_networks',
			'delete_networks',
		);

		/**
		 * Filters the primitive capabilities that should only be available to global administrators.
		 *
		 * @since 2.3.0
		 *
		 * @param array $global_capabilities List of primitive global capabilities.
		 */
		return apply_filters( 'wpms_global_capabilities', $global_capabilities );
	}

	/**
	 * Checks whether a given user has global access.
	 *
	 * @since 2.3.0
	 *
	 * @param int $user_id User ID to check for global administrator permissions.
	 * @return bool True if the user has global access, false otherwise.
	 */
	private function has_user_global_access( $user_id ) {

		/**
		 * Filters whether a given user should be granted global administrator capabilities.
		 *
		 * By default, global access is available to all network administrators.
		 *
		 * @since 2.3.0
		 *
		 * @param bool $has_global_access Whether the user should have global access.
		 * @param int  $user_id           ID of the user checked.
		 */
		return apply_filters( 'wpms_has_user_global_access', is_super_admin( $user_id ), $user_id );
	}
}
