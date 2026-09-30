<?php
/**
 * Command-level tests for upload repair plan validation and execution.
 *
 * @group upload
 * @group multisite
 * @ticket 245
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */

/**
 * Capture WP-CLI output without requiring a shell process in PHPUnit.
 */
class WP_CLI {
	public static $lines = array();
	public static $on_line = null;

	public static function add_command( $name, $class ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
	}

	public static function line( $message ) {
		self::$lines[] = $message;
		if ( is_callable( self::$on_line ) ) {
			call_user_func( self::$on_line, $message );
		}
	}

	public static function warning( $message ) {
		self::$lines[] = $message;
	}

	public static function error( $message ) {
		throw new RuntimeException( $message );
	}

	public static function halt( $code ) {
		throw new RuntimeException( 'WP-CLI halted with status ' . $code );
	}
}

require_once TESTS_PLUGIN_DIR . '/wp-multi-network/includes/classes/class-wp-ms-network-command.php';

/**
 * Keep request-scoped upload constants from other tests out of these cases.
 *
 * @group upload
 * @group multisite
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class WPMN_Tests_Upload_Repair_CLI extends WPMN_UnitTestCase {

	/**
	 * Subsite bootstrap can define a site-prefixed WP_CONTENT_URL.
	 */
	public function test_command_requires_main_site_bootstrap() {
		$site_id = $this->factory->blog->create();
		switch_to_blog( $site_id );
		try {
			$command = new WP_MS_Network_Command();
			$this->expectException( RuntimeException::class );
			$this->expectExceptionMessage( 'Run repair-uploads from a network main site' );
			$command->repair_uploads( array(), array( 'site-id' => $site_id ) );
		} finally {
			restore_current_blog();
		}
	}

	/**
	 * Removing an earlier site during pagination must not skip the next ID.
	 */
	public function test_dry_run_does_not_skip_site_after_earlier_deletion() {
		$first  = $this->factory->blog->create();
		$second = $this->factory->blog->create();
		$third  = $this->factory->blog->create();
		$deleted = false;
		WP_CLI::$lines = array();
		WP_CLI::$on_line = function ( $line ) use ( $first, &$deleted ) {
			$site = json_decode( ltrim( $line, ',' ), true );
			if ( ! $deleted && is_array( $site ) && isset( $site['site_id'] ) && $first === $site['site_id'] ) {
				wp_delete_site( $first );
				$deleted = true;
			}
		};
		try {
			$command = new WP_MS_Network_Command();
			$command->repair_uploads( array(), array( 'format' => 'json', 'batch-size' => 1 ) );
			$this->assertTrue( $deleted );
			$document = json_decode( implode( "\n", WP_CLI::$lines ), true );
			$this->assertIsArray( $document );
			$ids = array_column( $document['sites'], 'site_id' );
			$this->assertContains( $second, $ids );
			$this->assertContains( $third, $ids );
		} finally {
			WP_CLI::$on_line = null;
		}
	}

	/**
	 * Sites added after the scan starts belong in a later dry run.
	 */
	public function test_dry_run_excludes_site_created_mid_scan() {
		$first = $this->factory->blog->create();
		$new_site_id = 0;
		WP_CLI::$lines = array();
		WP_CLI::$on_line = function ( $line ) use ( $first, &$new_site_id ) {
			$site = json_decode( ltrim( $line, ',' ), true );
			if ( ! $new_site_id && is_array( $site ) && isset( $site['site_id'] ) && $first === $site['site_id'] ) {
				$new_site_id = $this->factory->blog->create();
			}
		};
		try {
			$command = new WP_MS_Network_Command();
			$command->repair_uploads( array(), array( 'format' => 'json', 'batch-size' => 1 ) );
			$this->assertGreaterThan( 0, $new_site_id );
			$document = json_decode( implode( "\n", WP_CLI::$lines ), true );
			$this->assertIsArray( $document );
			$this->assertNotContains( $new_site_id, array_column( $document['sites'], 'site_id' ) );
		} finally {
			WP_CLI::$on_line = null;
		}
	}

	/**
	 * Save the JSON produced by the actual command method.
	 *
	 * @param int $site_id Site to inspect.
	 * @return string Path to the saved plan.
	 */
	private function save_plan( $site_id ) {
		WP_CLI::$lines = array();
		$command       = new WP_MS_Network_Command();
		$command->repair_uploads( array(), array( 'site-id' => $site_id, 'format' => 'json' ) );
		$plan = implode( "\n", WP_CLI::$lines );
		$decoded = json_decode( $plan, true );
		$this->assertIsArray( $decoded );
		$this->assertSame( 'repairable', $decoded['sites'][0]['status'], $decoded['sites'][0]['reason'] );
		$file = tempnam( sys_get_temp_dir(), 'wpmn-plan-' );
		$this->assertNotFalse( $file );
		$this->assertNotFalse( file_put_contents( $file, $plan ) );
		return $file;
	}

	/**
	 * A saved single-site plan runs through command validation and execution.
	 */
	public function test_command_executes_saved_single_site_plan() {
		update_site_option( 'ms_files_rewriting', 0 );
		$site_id = $this->factory->blog->create();
		update_blog_option( $site_id, 'upload_path', 'wp-content/uploads/sites/' . $site_id );
		update_blog_option( $site_id, 'upload_url_path', WP_CONTENT_URL . '/uploads/sites/' . $site_id );
		$file = $this->save_plan( $site_id );
		$user = $this->factory->user->create( array( 'role' => 'administrator' ) );
		grant_super_admin( $user );
		wp_set_current_user( $user );

		try {
			$command = new WP_MS_Network_Command();
			$command->repair_uploads( array(), array( 'execute' => true, 'plan-file' => $file ) );
			$this->assertSame( '', get_blog_option( $site_id, 'upload_path' ) );
			$this->assertSame( '', get_blog_option( $site_id, 'upload_url_path' ) );
		} finally {
			unlink( $file );
		}
	}

	/**
	 * Execution requires an authenticated super administrator.
	 */
	public function test_command_refuses_execution_without_super_admin() {
		update_site_option( 'ms_files_rewriting', 0 );
		$site_id = $this->factory->blog->create();
		update_blog_option( $site_id, 'upload_path', 'wp-content/uploads/sites/' . $site_id );
		update_blog_option( $site_id, 'upload_url_path', WP_CONTENT_URL . '/uploads/sites/' . $site_id );
		$file = $this->save_plan( $site_id );
		wp_set_current_user( 0 );

		try {
			$command = new WP_MS_Network_Command();
			$this->expectException( RuntimeException::class );
			$this->expectExceptionMessage( 'Run this command as a super administrator' );
			$command->repair_uploads( array(), array( 'execute' => true, 'plan-file' => $file ) );
		} finally {
			unlink( $file );
			$this->assertSame( 'wp-content/uploads/sites/' . $site_id, get_blog_option( $site_id, 'upload_path' ) );
		}
	}

	/**
	 * A repeated site entry is rejected before any repair can begin.
	 */
	public function test_command_refuses_duplicate_site_entries() {
		$site_id = $this->factory->blog->create();
		$user    = $this->factory->user->create( array( 'role' => 'administrator' ) );
		grant_super_admin( $user );
		wp_set_current_user( $user );
		$entry = array(
			'site_id'     => $site_id,
			'network_id'  => (int) get_site( $site_id )->site_id,
			'status'      => 'repairable',
			'fingerprint' => str_repeat( 'a', 64 ),
		);
		$file = tempnam( sys_get_temp_dir(), 'wpmn-plan-' );
		$this->assertNotFalse( $file );
		$this->assertNotFalse( file_put_contents( $file, wp_json_encode( array( 'schema' => 1, 'sites' => array( $entry, $entry ) ) ) ) );

		try {
			$command = new WP_MS_Network_Command();
			$this->expectException( RuntimeException::class );
			$this->expectExceptionMessage( 'invalid or duplicate site entries' );
			$command->repair_uploads( array(), array( 'execute' => true, 'plan-file' => $file, 'all' => true ) );
		} finally {
			unlink( $file );
		}
	}

	/**
	 * Multi-site plans need an explicit --all confirmation.
	 */
	public function test_command_requires_all_for_multiple_sites() {
		$user = $this->factory->user->create( array( 'role' => 'administrator' ) );
		grant_super_admin( $user );
		wp_set_current_user( $user );
		$sites = array();
		foreach ( array( $this->factory->blog->create(), $this->factory->blog->create() ) as $site_id ) {
			$sites[] = array(
				'site_id'     => $site_id,
				'network_id'  => (int) get_site( $site_id )->site_id,
				'status'      => 'unchanged',
				'fingerprint' => '',
			);
		}
		$file = tempnam( sys_get_temp_dir(), 'wpmn-plan-' );
		$this->assertNotFalse( $file );
		$this->assertNotFalse( file_put_contents( $file, wp_json_encode( array( 'schema' => 1, 'sites' => $sites ) ) ) );

		try {
			$command = new WP_MS_Network_Command();
			$this->expectException( RuntimeException::class );
			$this->expectExceptionMessage( 'requires --all' );
			$command->repair_uploads( array(), array( 'execute' => true, 'plan-file' => $file ) );
		} finally {
			unlink( $file );
		}
	}

	/**
	 * Reject a saved cross-network repair before changing any site.
	 */
	public function test_command_refuses_cross_network_plan_before_execution() {
		$site_id = $this->factory->blog->create();
		$other   = $this->factory->network->create( array(
			'domain' => 'other.example.test',
			'path'   => '/',
		) );
		$user = $this->factory->user->create( array( 'role' => 'administrator' ) );
		grant_super_admin( $user );
		wp_set_current_user( $user );
		$file = tempnam( sys_get_temp_dir(), 'wpmn-plan-' );
		$this->assertNotFalse( $file );
		$plan = array(
			'schema' => 1,
			'sites'  => array(
				array(
					'site_id'     => $site_id,
					'network_id'  => $other,
					'status'      => 'repairable',
					'fingerprint' => str_repeat( 'a', 64 ),
				),
			),
		);
		$this->assertNotFalse( file_put_contents( $file, wp_json_encode( $plan ) ) );

		try {
			$command = new WP_MS_Network_Command();
			$this->expectException( RuntimeException::class );
			$this->expectExceptionMessage( 'repairable site from another network' );
			$command->repair_uploads( array(), array( 'execute' => true, 'plan-file' => $file ) );
		} finally {
			unlink( $file );
		}
	}
}
