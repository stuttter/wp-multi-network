<?php
/**
 * Tests network management form authorization.
 *
 * @since NEXT
 */

require_once TESTS_PLUGIN_DIR . '/wp-multi-network/includes/classes/class-wp-ms-networks-admin.php';

class WPMN_Tests_AdminAuthorization extends WPMN_UnitTestCase {

	/**
	 * A manager without deletion rights cannot reuse an edit nonce to delete.
	 *
	 * @since NEXT
	 */
	public function test_manager_cannot_delete_network_with_edit_nonce() {
		$network_id = $this->factory->network->create(
			array(
				'domain' => 'protected.example.com',
				'path'   => '/',
			)
		);
		$user_id = $this->factory->user->create();
		$user    = get_user_by( 'id', $user_id );
		$user->add_cap( 'manage_networks' );

		wp_set_current_user( $user_id );
		add_filter( 'wpms_has_user_global_access', '__return_true' );
		add_filter( 'wp_die_handler', array( $this, 'get_exception_die_handler' ) );

		$this->assertTrue( current_user_can( 'manage_networks' ) );
		$this->assertFalse( current_user_can( 'delete_network', $network_id ) );

		$old_get     = $_GET;
		$old_post    = $_POST;
		$old_request = $_REQUEST;
		$_GET        = array( 'id' => $network_id );
		$_POST       = array(
			'action'       => 'delete',
			'network_edit' => wp_create_nonce( 'edit_network' ),
			'override'     => '1',
		);
		$_REQUEST    = array_merge( $_GET, $_POST );

		try {
			$admin = new WP_MS_Networks_Admin();
			$admin->route_save_handlers();
			$this->fail( 'The unauthorized delete request should have been denied.' );
		} catch ( RuntimeException $exception ) {
			$this->assertStringContainsString( 'permission', $exception->getMessage() );
		} finally {
			$_GET     = $old_get;
			$_POST    = $old_post;
			$_REQUEST = $old_request;
			remove_filter( 'wp_die_handler', array( $this, 'get_exception_die_handler' ) );
			remove_filter( 'wpms_has_user_global_access', '__return_true' );
		}

		$this->assertNotNull( get_network( $network_id ) );
	}

	/**
	 * Replaces wp_die() with an exception so authorization failures are testable.
	 *
	 * @since NEXT
	 * @return callable Exception handler.
	 */
	public function get_exception_die_handler() {
		return array( $this, 'throw_die_exception' );
	}

	/**
	 * Throws the wp_die() message as an exception.
	 *
	 * @since NEXT
	 * @param string $message Error message.
	 * @return void
	 */
	public function throw_die_exception( $message ) {
		throw new RuntimeException( wp_strip_all_tags( $message ) );
	}
}
