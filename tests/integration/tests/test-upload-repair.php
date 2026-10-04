<?php
/**
 * Tests for the conservative upload repair planner and executor.
 *
 * @group upload
 * @group multisite
 * @ticket 245
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */

require_once TESTS_PLUGIN_DIR . '/wp-multi-network/includes/classes/class-wp-ms-upload-repair.php';

/**
 * Keep request-scoped upload constants from other tests out of these cases.
 *
 * @group upload
 * @group multisite
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class WPMN_Tests_Upload_Repair extends WPMN_UnitTestCase {

	/**
	 * Restore request paths omitted from isolated PHPUnit processes.
	 */
	public function set_up() {
		parent::set_up();
		$_SERVER['SCRIPT_FILENAME'] = ABSPATH . 'index.php';
		$_SERVER['REQUEST_URI']     = '/';
		$_SERVER['PHP_SELF']        = '/index.php';
	}

	/**
	 * Create a site with the known doubled modern upload options.
	 *
	 * @return int Site ID.
	 */
	private function create_doubled_site() {
		update_site_option( 'ms_files_rewriting', 0 );
		$site_id = $this->factory->blog->create();
		$suffix  = '/sites/' . $site_id;
		update_blog_option( $site_id, 'upload_path', 'wp-content/uploads' . $suffix );
		update_blog_option( $site_id, 'upload_url_path', WP_CONTENT_URL . '/uploads' . $suffix );
		return $site_id;
	}

	/**
	 * A dry run must identify the exact known case without changing options.
	 */
	public function test_dry_run_is_read_only_and_repairable() {
		$site_id = $this->create_doubled_site();
		$repair  = new WP_MS_Upload_Repair();
		$before  = get_blog_option( $site_id, 'upload_path' );
		$plan    = $repair->inspect( $site_id );

		$this->assertSame( 'repairable', $plan['status'], $plan['reason'] );
		$this->assertSame( $before, get_blog_option( $site_id, 'upload_path' ) );
		$this->assertSame( WP_CONTENT_DIR . '/uploads/sites/' . $site_id . '/sites/' . $site_id, $plan['effective_basedir'] );
		$this->assertSame( WP_CONTENT_DIR . '/uploads/sites/' . $site_id, $plan['target_basedir'] );
		$this->assertNotEmpty( $plan['fingerprint'] );
		$this->assertSame( array(), $repair->public_plan( $plan )['files'] );
	}

	/**
	 * Empty doubled directories need only option changes; the backup is retained.
	 */
	public function test_execute_without_files_saves_backup_and_verifies() {
		$site_id = $this->create_doubled_site();
		$repair  = new WP_MS_Upload_Repair();
		$plan    = $repair->public_plan( $repair->inspect( $site_id ) );
		$this->assertSame( 'repairable', $plan['status'], $plan['reason'] );

		$result = $repair->execute( $plan );
		$this->assertIsArray( $result );
		$this->assertSame( 'repaired', $result['status'] );
		$this->assertSame( '', get_blog_option( $site_id, 'upload_path' ) );
		$this->assertSame( '', get_blog_option( $site_id, 'upload_url_path' ) );
		$this->assertSame( 'unchanged', $repair->inspect( $site_id )['status'] );

		switch_to_blog( $site_id );
		$backup = get_option( $result['backup_option'] );
		restore_current_blog();
		$this->assertSame( 'complete', $backup['status'] );
		$this->assertSame( 'wp-content/uploads/sites/' . $site_id, $backup['old_upload_path'] );
		$this->assertSame( 'already-repaired', $repair->execute( $plan )['status'] );
		update_blog_option( $site_id, 'upload_path', 'wp-content/custom-uploads' );
		$changed = $repair->execute( $plan );
		$this->assertWPError( $changed );
		$this->assertSame( 'upload_repair_changed', $changed->get_error_code() );
	}

	/**
	 * Concurrent execution for one site must stop before journal or option changes.
	 */
	public function test_execute_refuses_concurrent_site_repair() {
		$site_id = $this->create_doubled_site();
		$repair  = new WP_MS_Upload_Repair();
		$plan    = $repair->public_plan( $repair->inspect( $site_id ) );
		$target  = WP_CONTENT_DIR . '/uploads/sites/' . $site_id;
		$this->assertTrue( wp_mkdir_p( $target ) );
		$lock = fopen( $target . '/.wpmn-upload-repair.lock', 'c' );
		$this->assertIsResource( $lock );
		$this->assertTrue( flock( $lock, LOCK_EX | LOCK_NB ) );
		try {
			$result = $repair->execute( $plan );
			$this->assertWPError( $result );
			$this->assertSame( 'upload_repair_locked', $result->get_error_code() );
			$this->assertSame( $plan['stored_upload_path'], get_blog_option( $site_id, 'upload_path' ) );
			$this->assertFalse( get_blog_option( $site_id, 'wpmn_upload_repair_' . $plan['fingerprint'], false ) );
		} finally {
			flock( $lock, LOCK_UN );
			fclose( $lock );
		}

		$this->assertSame( 'repaired', $repair->execute( $plan )['status'] );
	}

	/**
	 * A lock path must not follow a link outside the upload directory.
	 */
	public function test_execute_refuses_symlinked_lock_path() {
		$site_id = $this->create_doubled_site();
		$repair  = new WP_MS_Upload_Repair();
		$plan    = $repair->public_plan( $repair->inspect( $site_id ) );
		$target  = WP_CONTENT_DIR . '/uploads/sites/' . $site_id;
		$path    = $target . '/.wpmn-upload-repair.lock';
		$outside = tempnam( sys_get_temp_dir(), 'wpmn-lock-' );
		$this->assertTrue( wp_mkdir_p( $target ) );
		$this->assertNotFalse( $outside );
		$this->assertNotFalse( file_put_contents( $outside, 'outside lock target' ) );
		try {
			if ( file_exists( $path ) || is_link( $path ) ) {
				$this->assertTrue( unlink( $path ) );
			}
			if ( ! symlink( $outside, $path ) ) {
				$this->markTestSkipped( 'The test environment cannot create symlinks.' );
			}
			$result = $repair->execute( $plan );
			$this->assertWPError( $result );
			$this->assertSame( 'upload_lock_failed', $result->get_error_code() );
			$this->assertSame( $plan['stored_upload_path'], get_blog_option( $site_id, 'upload_path' ) );
			$this->assertFalse( get_blog_option( $site_id, 'wpmn_upload_repair_' . $plan['fingerprint'], false ) );
			$this->assertSame( 'outside lock target', file_get_contents( $outside ) );
		} finally {
			if ( is_link( $path ) ) {
				unlink( $path );
			}
			if ( file_exists( $outside ) ) {
				unlink( $outside );
			}
		}
	}

	/**
	 * A failed second option write must restore both old values and remain resumable.
	 */
	public function test_failed_upload_url_option_write_restores_and_resumes() {
		$site_id = $this->create_doubled_site();
		$repair  = new WP_MS_Upload_Repair();
		$plan    = $repair->public_plan( $repair->inspect( $site_id ) );
		$this->assertSame( 'repairable', $plan['status'], $plan['reason'] );

		$block_url = static function ( $new_value, $old_value ) {
			return '' === $new_value ? $old_value : $new_value;
		};
		$install_block = static function () use ( $block_url ) {
			add_filter( 'pre_update_option_upload_url_path', $block_url, 10, 2 );
		};
		add_action( 'update_option_upload_path', $install_block );
		try {
			$result = $repair->execute( $plan );
			$this->assertWPError( $result );
			$this->assertSame( 'upload_option_failed', $result->get_error_code() );
			$this->assertSame( $plan['stored_upload_path'], get_blog_option( $site_id, 'upload_path' ) );
			$this->assertSame( $plan['stored_upload_url_path'], get_blog_option( $site_id, 'upload_url_path' ) );
			$backup_key = 'wpmn_upload_repair_' . $plan['fingerprint'];
			$this->assertSame( 'copying', get_blog_option( $site_id, $backup_key )['status'] );
		} finally {
			remove_action( 'update_option_upload_path', $install_block );
			remove_filter( 'pre_update_option_upload_url_path', $block_url, 10 );
		}

		$this->assertSame( 'repaired', $repair->execute( $plan )['status'] );
		$this->assertSame( '', get_blog_option( $site_id, 'upload_path' ) );
		$this->assertSame( '', get_blog_option( $site_id, 'upload_url_path' ) );
	}

	/**
	 * Core derives a relative upload path URL from the site's own siteurl.
	 */
	public function test_default_upload_url_uses_site_address_for_references() {
		$site_id = $this->create_doubled_site();
		update_blog_option( $site_id, 'upload_url_path', '' );
		switch_to_blog( $site_id );
		$old_url = trailingslashit( get_option( 'siteurl' ) ) . 'wp-content/uploads/sites/' . $site_id . '/sites/' . $site_id;
		$this->factory->post->create( array( 'post_content' => $old_url . '/image.png' ) );
		restore_current_blog();
		$repair = new WP_MS_Upload_Repair();
		$plan   = $repair->public_plan( $repair->inspect( $site_id ) );

		$this->assertSame( 'repairable', $plan['status'], $plan['reason'] );
		$this->assertSame( '', $plan['stored_upload_url_path'] );
		$this->assertSame( $old_url, $plan['effective_baseurl'] );
		$this->assertSame( 1, $plan['references']['posts'] );
		$this->assertSame( 'wp-content/uploads/sites/' . $site_id, get_blog_option( $site_id, 'upload_path' ) );

		$reference_queries = 0;
		$count_references  = static function( $query ) use ( &$reference_queries ) {
			if ( preg_match( '/SELECT COUNT\(\*\).*\b(?:post_content|guid|meta_value|option_value|comment_content)\b.*\bLIKE\b/i', $query ) ) {
				++$reference_queries;
			}
			return $query;
		};
		add_filter( 'query', $count_references );
		try {
			$this->assertSame( 'repaired', $repair->execute( $plan )['status'] );
		} finally {
			remove_filter( 'query', $count_references );
		}
		$this->assertSame( 0, $reference_queries );
		$this->assertSame( '', get_blog_option( $site_id, 'upload_path' ) );
		$this->assertSame( '', get_blog_option( $site_id, 'upload_url_path' ) );
	}

	/**
	 * Upload planning must use Core's raw content URL rather than request scheme.
	 */
	public function test_upload_url_uses_raw_content_url_when_request_scheme_differs() {
		$original_https  = isset( $_SERVER['HTTPS'] ) ? $_SERVER['HTTPS'] : null;
		$_SERVER['HTTPS'] = 'on';
		try {
			$site_id = $this->create_doubled_site();
			$this->assertNotSame( WP_CONTENT_URL . '/uploads', content_url( 'uploads' ) );
			$repair = new WP_MS_Upload_Repair();
			$plan   = $repair->public_plan( $repair->inspect( $site_id ) );
			$this->assertSame( 'repairable', $plan['status'], $plan['reason'] );
			$this->assertSame( WP_CONTENT_URL . '/uploads/sites/' . $site_id, $plan['bootstrap_target_baseurl'] );
			$this->assertSame( 'repaired', $repair->execute( $plan )['status'] );
		} finally {
			if ( null === $original_https ) {
				unset( $_SERVER['HTTPS'] );
			} else {
				$_SERVER['HTTPS'] = $original_https;
			}
		}
	}

	/**
	 * An absolute known path can double the directory while leaving the URL correct.
	 */
	public function test_absolute_upload_path_with_empty_url_is_repairable() {
		$site_id = $this->create_doubled_site();
		update_blog_option( $site_id, 'upload_path', WP_CONTENT_DIR . '/uploads/sites/' . $site_id );
		update_blog_option( $site_id, 'upload_url_path', '' );
		$repair = new WP_MS_Upload_Repair();
		$plan   = $repair->public_plan( $repair->inspect( $site_id ) );

		$this->assertSame( 'repairable', $plan['status'], $plan['reason'] );
		$this->assertSame( WP_CONTENT_URL . '/uploads/sites/' . $site_id, $plan['effective_baseurl'] );
		$this->assertSame( 0, array_sum( $plan['references'] ) );
		$this->assertSame( 'repaired', $repair->execute( $plan )['status'] );
		$this->assertSame( '', get_blog_option( $site_id, 'upload_path' ) );
		$this->assertSame( '', get_blog_option( $site_id, 'upload_url_path' ) );
	}

	/**
	 * A plan must be refused if the site changes before execution.
	 */
	public function test_execute_refuses_stale_plan() {
		$site_id = $this->create_doubled_site();
		$repair  = new WP_MS_Upload_Repair();
		$plan    = $repair->public_plan( $repair->inspect( $site_id ) );
		$this->assertSame( 'repairable', $plan['status'], $plan['reason'] );

		update_blog_option( $site_id, 'upload_url_path', 'https://cdn.example.test/custom' );
		$result = $repair->execute( $plan );
		$this->assertWPError( $result );
		$this->assertSame( 'upload_plan_stale', $result->get_error_code() );
		$this->assertSame( 'wp-content/uploads/sites/' . $site_id, get_blog_option( $site_id, 'upload_path' ) );
	}

	/**
	 * A site's address is part of the identity of a saved repair plan.
	 */
	public function test_execute_refuses_changed_site_address() {
		$site_id = $this->create_doubled_site();
		$repair  = new WP_MS_Upload_Repair();
		$plan    = $repair->public_plan( $repair->inspect( $site_id ) );
		$this->assertSame( 'repairable', $plan['status'], $plan['reason'] );
		$this->assertNotWPError( wp_update_site( $site_id, array( 'path' => '/changed-address/' ) ) );

		$result = $repair->execute( $plan );
		$this->assertWPError( $result );
		$this->assertSame( 'upload_plan_stale', $result->get_error_code() );
		$this->assertSame( 'wp-content/uploads/sites/' . $site_id, get_blog_option( $site_id, 'upload_path' ) );
	}

	/**
	 * Custom upload bases must not be treated as the known default-path bug.
	 */
	public function test_custom_path_requires_manual_review() {
		$site_id = $this->create_doubled_site();
		update_blog_option( $site_id, 'upload_path', 'wp-content/custom/sites/' . $site_id );
		$repair = new WP_MS_Upload_Repair();
		$plan   = $repair->inspect( $site_id );
		$this->assertSame( 'manual', $plan['status'] );
	}

	/**
	 * A numeric-prefix site ID in a custom base is not this site's suffix.
	 */
	public function test_custom_path_with_numeric_prefix_is_unchanged() {
		$site_id = $this->create_doubled_site();
		update_blog_option( $site_id, 'upload_path', 'wp-content/custom/sites/' . $site_id . '0' );
		update_blog_option( $site_id, 'upload_url_path', 'https://media.example.test/sites/' . $site_id . '0' );
		$repair = new WP_MS_Upload_Repair();
		$this->assertSame( 'unchanged', $repair->inspect( $site_id )['status'] );
	}

	/**
	 * A custom upload URL is not the known default-layout bug.
	 */
	public function test_custom_upload_url_requires_manual_review() {
		$site_id = $this->create_doubled_site();
		update_blog_option( $site_id, 'upload_url_path', 'https://media.example.test/sites/' . $site_id );
		$repair = new WP_MS_Upload_Repair();
		$this->assertSame( 'manual', $repair->inspect( $site_id )['status'] );
	}

	/**
	 * A request-scoped upload path constant must prevent automatic repair.
	 */
	public function test_uploads_constant_requires_manual_review() {
		if ( defined( 'UPLOADS' ) ) {
			$this->markTestSkipped( 'UPLOADS was already defined by the test bootstrap.' );
		}
		$site_id = $this->create_doubled_site();
		define( 'UPLOADS', 'custom/uploads' );
		$repair = new WP_MS_Upload_Repair();
		$this->assertSame( 'manual', $repair->inspect( $site_id )['status'] );
	}

	/**
	 * Legacy per-blog upload constants also require manual review.
	 */
	public function test_bloguploaddir_constant_requires_manual_review() {
		if ( defined( 'BLOGUPLOADDIR' ) ) {
			$this->markTestSkipped( 'BLOGUPLOADDIR was already defined by the test bootstrap.' );
		}
		$site_id = $this->create_doubled_site();
		define( 'BLOGUPLOADDIR', WP_CONTENT_DIR . '/custom/uploads' );
		$repair = new WP_MS_Upload_Repair();
		$this->assertSame( 'manual', $repair->inspect( $site_id )['status'] );
	}

	/**
	 * Legacy rewriting and filtered upload locations need manual review.
	 */
	public function test_nonstandard_upload_configuration_requires_manual_review() {
		$site_id = $this->create_doubled_site();
		$repair  = new WP_MS_Upload_Repair();

		update_site_option( 'ms_files_rewriting', 1 );
		$this->assertSame( 'manual', $repair->inspect( $site_id )['status'] );
		update_site_option( 'ms_files_rewriting', 0 );

		$filter = static function( $uploads ) {
			return $uploads;
		};
		add_filter( 'upload_dir', $filter );
		try {
			$this->assertSame( 'manual', $repair->inspect( $site_id )['status'] );
		} finally {
			remove_filter( 'upload_dir', $filter );
		}

		$option_filter = static function( $value ) {
			return $value;
		};
		add_filter( 'pre_option_upload_path', $option_filter );
		try {
			$this->assertSame( 'manual', $repair->inspect( $site_id )['status'] );
		} finally {
			remove_filter( 'pre_option_upload_path', $option_filter );
		}
	}

	/**
	 * The network recorded in the plan must still own the site at execution.
	 */
	public function test_execute_refuses_site_moved_to_another_network() {
		$site_id    = $this->create_doubled_site();
		$repair     = new WP_MS_Upload_Repair();
		$plan       = $repair->public_plan( $repair->inspect( $site_id ) );
		$network_id = $this->factory->network->create( array(
			'domain' => 'other.example.test',
			'path'   => '/',
		) );
		$this->assertSame( 'repairable', $plan['status'], $plan['reason'] );
		$this->assertNotWPError( move_site( $site_id, $network_id ) );

		$result = $repair->execute( $plan );
		$this->assertWPError( $result );
		$this->assertSame( 'upload_site_changed', $result->get_error_code() );
		$this->assertSame( 'wp-content/uploads/sites/' . $site_id, get_blog_option( $site_id, 'upload_path' ) );
	}

	/**
	 * A prefixed secondary network must not borrow the current network's URL.
	 */
	public function test_inspect_prefixed_secondary_network_preserves_context() {
		$network_id = $this->factory->network->create( array(
			'domain' => 'example.test',
			'path'   => '/department/',
		) );
		$site_id = $this->factory->blog->create( array(
			'network_id' => $network_id,
			'domain'     => 'example.test',
			'path'       => '/department/sample/',
		) );
		update_network_option( $network_id, 'ms_files_rewriting', 0 );
		update_blog_option( $site_id, 'upload_path', 'wp-content/uploads/sites/' . $site_id );
		$original_network_id = get_current_network_id();
		$this->assertTrue( switch_to_network( $network_id, true ) );
		$expected_url = content_url( 'uploads' ) . '/sites/' . $site_id;
		$this->assertTrue( restore_current_network() );
		update_blog_option( $site_id, 'upload_url_path', $expected_url );

		$repair = new WP_MS_Upload_Repair();
		$other_network_plan = $repair->inspect( $site_id );
		$this->assertSame( 'manual', $other_network_plan['status'] );
		$this->assertSame( 'wp-content/uploads/sites/' . $site_id, $other_network_plan['stored_upload_path'] );
		$this->assertSame( $expected_url, $other_network_plan['stored_upload_url_path'] );
		$this->assertTrue( switch_to_network( $network_id, true ) );
		try {
			$plan = $repair->inspect( $site_id );
			$this->assertSame( 'repairable', $plan['status'], $plan['reason'] );
			$this->assertSame( $network_id, $plan['network_id'] );
			$this->assertSame( $expected_url . '/sites/' . $site_id, $plan['effective_baseurl'] );
		} finally {
			$this->assertTrue( restore_current_network() );
		}
		$this->assertSame( $original_network_id, get_current_network_id() );
	}

	/**
	 * A domain-based subsite must use its own network and site upload URL.
	 */
	public function test_domain_based_subsite_repairs_under_its_network() {
		$network_id = $this->factory->network->create( array(
			'domain' => 'tenant.example.test',
			'path'   => '/',
		) );
		$site_id = $this->factory->blog->create( array(
			'network_id' => $network_id,
			'domain'     => 'media.tenant.example.test',
			'path'       => '/',
		) );
		update_network_option( $network_id, 'ms_files_rewriting', 0 );
		$this->assertTrue( switch_to_network( $network_id, true ) );
		try {
			switch_to_blog( $site_id );
			$expected_url = content_url( 'uploads' ) . '/sites/' . $site_id;
			restore_current_blog();
			update_blog_option( $site_id, 'upload_path', 'wp-content/uploads/sites/' . $site_id );
			update_blog_option( $site_id, 'upload_url_path', $expected_url );
			$repair = new WP_MS_Upload_Repair();
			$plan   = $repair->public_plan( $repair->inspect( $site_id ) );
			$this->assertSame( 'repairable', $plan['status'], $plan['reason'] );
			$this->assertSame( 'repaired', $repair->execute( $plan )['status'] );
			$this->assertSame( '', get_blog_option( $site_id, 'upload_path' ) );
		} finally {
			$this->assertTrue( restore_current_network() );
		}
	}

	/**
	 * Cloning a modern network must not make its sites ineligible for repair.
	 */
	public function test_cloned_modern_network_site_can_be_repaired() {
		global $wpdb;

		$user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		grant_super_admin( $user_id );
		$suppress_errors = $wpdb->suppress_errors( true );
		try {
			$source_id = $this->factory->network->create( array(
				'domain' => 'source-repair.example.test',
				'path'   => '/',
			) );
			update_network_option( $source_id, 'ms_files_rewriting', 0 );
			$clone_id = add_network( array(
				'domain'           => 'clone-repair.example.test',
				'path'             => '/',
				'clone_network'    => $source_id,
				'user_id'          => $user_id,
				'network_admin_id' => $user_id,
			) );
			$this->assertNotWPError( $clone_id );
			$site_id = $this->factory->blog->create( array(
				'network_id' => $clone_id,
				'domain'     => 'clone-repair.example.test',
				'path'       => '/secondary/',
			) );
		} finally {
			// WordPress 6.9 transient cleanup emits a MySQL 8 self-join error
			// while populating a new site; keep it out of this isolated fixture.
			$wpdb->suppress_errors( $suppress_errors );
		}
		$this->assertTrue( switch_to_network( $clone_id, true ) );
		try {
			switch_to_blog( $site_id );
			$expected_url = content_url( 'uploads' ) . '/sites/' . $site_id;
			restore_current_blog();
			update_blog_option( $site_id, 'upload_path', 'wp-content/uploads/sites/' . $site_id );
			update_blog_option( $site_id, 'upload_url_path', $expected_url );
			$repair = new WP_MS_Upload_Repair();
			$plan   = $repair->public_plan( $repair->inspect( $site_id ) );
			$this->assertSame( 'repairable', $plan['status'], $plan['reason'] );
			$this->assertSame( 'repaired', $repair->execute( $plan )['status'] );
		} finally {
			$this->assertTrue( restore_current_network() );
		}
	}

	/**
	 * A saved secondary-network plan cannot run under another network.
	 */
	public function test_execute_refuses_wrong_network_bootstrap() {
		$site_id = $this->create_doubled_site();
		$repair  = new WP_MS_Upload_Repair();
		$plan    = $repair->public_plan( $repair->inspect( $site_id ) );
		$other   = $this->factory->network->create( array(
			'domain' => 'other.example.test',
			'path'   => '/',
		) );
		$this->assertSame( 'repairable', $plan['status'], $plan['reason'] );
		$this->assertTrue( switch_to_network( $other, true ) );
		try {
			$result = $repair->execute( $plan );
			$this->assertWPError( $result );
			$this->assertSame( 'upload_network_context', $result->get_error_code() );
		} finally {
			$this->assertTrue( restore_current_network() );
		}
		$this->assertSame( 'wp-content/uploads/sites/' . $site_id, get_blog_option( $site_id, 'upload_path' ) );
	}

	/**
	 * Planning should not depend on the blog active when the command starts.
	 */
	public function test_inspect_from_subsite_context_uses_network_upload_url() {
		$site_id = $this->create_doubled_site();
		$repair  = new WP_MS_Upload_Repair();
		$root    = $repair->inspect( $site_id );
		$this->assertSame( 'repairable', $root['status'], $root['reason'] );

		switch_to_blog( $site_id );
		try {
			$subsite = $repair->inspect( $site_id );
			$this->assertSame( 'repairable', $subsite['status'], $subsite['reason'] );
			$this->assertSame( $root['fingerprint'], $subsite['fingerprint'] );
		} finally {
			restore_current_blog();
		}
	}

	/**
	 * A file added to the target after planning must not be overwritten.
	 */
	public function test_execute_refuses_late_target_collision() {
		$site_id    = $this->create_doubled_site();
		$source_dir = WP_CONTENT_DIR . '/uploads/sites/' . $site_id . '/sites/' . $site_id;
		$target_dir = WP_CONTENT_DIR . '/uploads/sites/' . $site_id;
		$name       = 'repair-late-collision-' . wp_generate_password( 12, false ) . '.txt';
		$this->assertTrue( wp_mkdir_p( $source_dir ) );
		$this->assertNotFalse( file_put_contents( $source_dir . '/' . $name, 'source' ) );

		try {
			$repair = new WP_MS_Upload_Repair();
			$plan   = $repair->public_plan( $repair->inspect( $site_id ) );
			$this->assertSame( 'repairable', $plan['status'], $plan['reason'] );
			$this->assertNotFalse( file_put_contents( $target_dir . '/' . $name, 'new target' ) );

			$result = $repair->execute( $plan );
			$this->assertWPError( $result );
			$this->assertSame( 'upload_plan_stale', $result->get_error_code() );
			$this->assertSame( 'new target', file_get_contents( $target_dir . '/' . $name ) );
			$this->assertSame( 'wp-content/uploads/sites/' . $site_id, get_blog_option( $site_id, 'upload_path' ) );
		} finally {
			unlink( $source_dir . '/' . $name );
			if ( file_exists( $target_dir . '/' . $name ) ) {
				unlink( $target_dir . '/' . $name );
			}
		}
	}

	/**
	 * Files are copied and verified, but the doubled originals remain available.
	 */
	public function test_execute_copies_files_without_deleting_originals() {
		$site_id    = $this->create_doubled_site();
		$source_dir = WP_CONTENT_DIR . '/uploads/sites/' . $site_id . '/sites/' . $site_id;
		$target_dir = WP_CONTENT_DIR . '/uploads/sites/' . $site_id;
		$name       = 'repair-test-' . wp_generate_password( 12, false ) . '.txt';
		$this->assertTrue( wp_mkdir_p( $source_dir ) );
		$this->assertNotFalse( file_put_contents( $source_dir . '/' . $name, 'source media' ) );
		$this->assertTrue( chmod( $source_dir . '/' . $name, 0640 ) );

		try {
			$repair = new WP_MS_Upload_Repair();
			$plan   = $repair->public_plan( $repair->inspect( $site_id ) );
			$this->assertSame( 'repairable', $plan['status'], $plan['reason'] );
			$this->assertSame( 1, $plan['file_count'] );
			$this->assertSame( hash( 'sha256', 'source media' ), $plan['files'][ $name ]['hash'] );
			$result = $repair->execute( $plan );
			$this->assertIsArray( $result );
			$this->assertSame( 'repaired', $result['status'] );
			$this->assertSame( 'source media', file_get_contents( $source_dir . '/' . $name ) );
			$this->assertSame( 'source media', file_get_contents( $target_dir . '/' . $name ) );
			$this->assertSame( fileperms( $source_dir . '/' . $name ) & 0777, fileperms( $target_dir . '/' . $name ) & 0777 );
		} finally {
			if ( file_exists( $source_dir . '/' . $name ) ) {
				unlink( $source_dir . '/' . $name );
			}
			if ( file_exists( $target_dir . '/' . $name ) ) {
				unlink( $target_dir . '/' . $name );
			}
		}
	}

	/**
	 * Advisory URL-reference writes after journaling must not stale the copy.
	 */
	public function test_reference_count_drift_during_copy_does_not_stale_plan() {
		$site_id    = $this->create_doubled_site();
		$source_dir = WP_CONTENT_DIR . '/uploads/sites/' . $site_id . '/sites/' . $site_id;
		$target_dir = WP_CONTENT_DIR . '/uploads/sites/' . $site_id;
		$name       = 'repair-reference-' . wp_generate_password( 12, false ) . '.txt';
		$this->assertTrue( wp_mkdir_p( $source_dir ) );
		$this->assertNotFalse( file_put_contents( $source_dir . '/' . $name, 'reference media' ) );

		$repair     = new WP_MS_Upload_Repair();
		$plan       = $repair->public_plan( $repair->inspect( $site_id ) );
		$backup_key = 'wpmn_upload_repair_' . $plan['fingerprint'];
		$added_post = false;
		$on_journal = function ( $option ) use ( $site_id, $backup_key, $plan, &$added_post ) {
			if ( $option !== $backup_key ) {
				return;
			}
			$added_post = true;
			switch_to_blog( $site_id );
			try {
				$this->factory->post->create( array( 'post_content' => $plan['effective_baseurl'] . '/image.png' ) );
			} finally {
				restore_current_blog();
			}
		};
		add_action( 'added_option', $on_journal );
		try {
			$this->assertSame( 'repairable', $plan['status'], $plan['reason'] );
			$result = $repair->execute( $plan );
			$this->assertTrue( $added_post );
			$this->assertIsArray( $result );
			$this->assertSame( 'repaired', $result['status'] );
			$this->assertSame( 'reference media', file_get_contents( $target_dir . '/' . $name ) );
		} finally {
			remove_action( 'added_option', $on_journal );
			if ( file_exists( $source_dir . '/' . $name ) ) {
				unlink( $source_dir . '/' . $name );
			}
			if ( file_exists( $target_dir . '/' . $name ) ) {
				unlink( $target_dir . '/' . $name );
			}
		}
	}

	/**
	 * A completed record must not hide later damage to a copied file.
	 */
	public function test_completed_repair_detects_changed_target_file() {
		$site_id    = $this->create_doubled_site();
		$source_dir = WP_CONTENT_DIR . '/uploads/sites/' . $site_id . '/sites/' . $site_id;
		$target_dir = WP_CONTENT_DIR . '/uploads/sites/' . $site_id;
		$name       = 'repair-complete-' . wp_generate_password( 12, false ) . '.txt';
		$this->assertTrue( wp_mkdir_p( $source_dir ) );
		$this->assertNotFalse( file_put_contents( $source_dir . '/' . $name, 'original' ) );

		try {
			$repair = new WP_MS_Upload_Repair();
			$plan   = $repair->public_plan( $repair->inspect( $site_id ) );
			$this->assertSame( 'repaired', $repair->execute( $plan )['status'] );
			$this->assertNotFalse( file_put_contents( $target_dir . '/' . $name, 'changed' ) );

			$result = $repair->execute( $plan );
			$this->assertWPError( $result );
			$this->assertSame( 'upload_repair_changed', $result->get_error_code() );
		} finally {
			unlink( $source_dir . '/' . $name );
			if ( file_exists( $target_dir . '/' . $name ) ) {
				unlink( $target_dir . '/' . $name );
			}
		}
	}

	/**
	 * Recovery must not trust a journal when its copied target file is missing.
	 */
	public function test_recovery_detects_missing_target_file_in_every_option_state() {
		$site_id    = $this->create_doubled_site();
		$source_dir = WP_CONTENT_DIR . '/uploads/sites/' . $site_id . '/sites/' . $site_id;
		$target_dir = WP_CONTENT_DIR . '/uploads/sites/' . $site_id;
		$name       = 'repair-missing-' . wp_generate_password( 12, false ) . '.txt';
		$this->assertTrue( wp_mkdir_p( $source_dir ) );
		$this->assertNotFalse( file_put_contents( $source_dir . '/' . $name, 'missing target' ) );

		try {
			$repair     = new WP_MS_Upload_Repair();
			$plan       = $repair->public_plan( $repair->inspect( $site_id ) );
			$site       = get_site( $site_id );
			$backup_key = 'wpmn_upload_repair_' . $plan['fingerprint'];
			$backup     = array(
				'fingerprint'          => $plan['fingerprint'],
				'old_upload_path'      => $plan['stored_upload_path'],
				'old_upload_url_path'  => $plan['stored_upload_url_path'],
				'old_basedir'          => $plan['effective_basedir'],
				'target_basedir'       => $plan['target_basedir'],
				'site_domain'          => $site->domain,
				'site_path'            => $site->path,
				'site_registered'      => $site->registered,
				'file_manifest_digest' => hash( 'sha256', wp_json_encode( $plan['files'] ) ),
				'status'               => 'copying',
			);
			switch_to_blog( $site_id );
			$this->assertTrue( add_option( $backup_key, $backup, '', 'no' ) );
			restore_current_blog();

			$states = array(
				array( '', $plan['stored_upload_url_path'], 'copying' ),
				array( '', '', 'copying' ),
				array( '', '', 'complete' ),
			);
			foreach ( $states as $state ) {
				list( $path, $url, $status ) = $state;
				update_blog_option( $site_id, 'upload_path', $path );
				update_blog_option( $site_id, 'upload_url_path', $url );
				$backup['status'] = $status;
				update_blog_option( $site_id, $backup_key, $backup );

				$result = $repair->execute( $plan );
				$this->assertWPError( $result );
				$this->assertSame( 'upload_repair_changed', $result->get_error_code() );
				$this->assertSame( $path, get_blog_option( $site_id, 'upload_path' ) );
				$this->assertSame( $url, get_blog_option( $site_id, 'upload_url_path' ) );
				$this->assertSame( $status, get_blog_option( $site_id, $backup_key )['status'] );
			}
		} finally {
			if ( file_exists( $source_dir . '/' . $name ) ) {
				unlink( $source_dir . '/' . $name );
			}
			if ( file_exists( $target_dir . '/' . $name ) ) {
				unlink( $target_dir . '/' . $name );
			}
		}
	}

	/**
	 * An existing file in a target parent directory is a dry-run collision.
	 */
	public function test_nested_target_parent_file_requires_manual_review() {
		$site_id      = $this->create_doubled_site();
		$target_dir   = WP_CONTENT_DIR . '/uploads/sites/' . $site_id;
		$source_file  = $target_dir . '/sites/' . $site_id . '/2024/09/image.txt';
		$target_block = $target_dir . '/2024';
		$this->assertTrue( wp_mkdir_p( dirname( $source_file ) ) );
		$this->assertNotFalse( file_put_contents( $source_file, 'source' ) );
		$this->assertNotFalse( file_put_contents( $target_block, 'blocker' ) );

		try {
			$repair = new WP_MS_Upload_Repair();
			$plan   = $repair->inspect( $site_id );
			$this->assertSame( 'manual', $plan['status'] );
			$this->assertSame( 'blocker', file_get_contents( $target_block ) );
		} finally {
			unlink( $source_file );
			unlink( $target_block );
			rmdir( dirname( $source_file ) );
			rmdir( dirname( dirname( $source_file ) ) );
		}
	}

	/**
	 * A linked directory within the source must not be silently omitted.
	 */
	public function test_source_symlink_directory_requires_manual_review() {
		$site_id     = $this->create_doubled_site();
		$source_dir  = WP_CONTENT_DIR . '/uploads/sites/' . $site_id . '/sites/' . $site_id;
		$outside_dir = sys_get_temp_dir() . '/wpmn-source-link-' . wp_generate_password( 12, false );
		$link        = $source_dir . '/external';
		$this->assertTrue( wp_mkdir_p( $source_dir ) );
		$this->assertTrue( wp_mkdir_p( $outside_dir ) );
		$this->assertNotFalse( file_put_contents( $outside_dir . '/media.txt', 'external media' ) );
		try {
			if ( ! symlink( $outside_dir, $link ) ) {
				$this->markTestSkipped( 'The test environment cannot create symlinks.' );
			}
			$repair = new WP_MS_Upload_Repair();
			$plan   = $repair->inspect( $site_id );
			$this->assertSame( 'manual', $plan['status'] );
			$this->assertSame( 'external media', file_get_contents( $outside_dir . '/media.txt' ) );
		} finally {
			if ( is_link( $link ) ) {
				unlink( $link );
			}
			unlink( $outside_dir . '/media.txt' );
			rmdir( $outside_dir );
		}
	}

	/**
	 * Existing target files are never overwritten, even if a name matches.
	 */
	public function test_collision_requires_manual_review() {
		$site_id    = $this->create_doubled_site();
		$source_dir = WP_CONTENT_DIR . '/uploads/sites/' . $site_id . '/sites/' . $site_id;
		$target_dir = WP_CONTENT_DIR . '/uploads/sites/' . $site_id;
		$name       = 'repair-collision-' . wp_generate_password( 12, false ) . '.txt';
		$this->assertTrue( wp_mkdir_p( $source_dir ) );
		$this->assertNotFalse( file_put_contents( $source_dir . '/' . $name, 'source' ) );
		$this->assertNotFalse( file_put_contents( $target_dir . '/' . $name, 'target' ) );

		try {
			$repair = new WP_MS_Upload_Repair();
			$plan   = $repair->inspect( $site_id );
			$this->assertSame( 'manual', $plan['status'] );
			$this->assertContains( $name, $plan['collisions'] );
			$this->assertSame( 'target', file_get_contents( $target_dir . '/' . $name ) );
		} finally {
			if ( file_exists( $source_dir . '/' . $name ) ) {
				unlink( $source_dir . '/' . $name );
			}
			if ( file_exists( $target_dir . '/' . $name ) ) {
				unlink( $target_dir . '/' . $name );
			}
		}
	}

	/**
	 * A nested target symlink must be rejected before mkdir can follow it.
	 */
	public function test_execute_does_not_create_directories_through_target_symlink() {
		$site_id      = $this->create_doubled_site();
		$target_dir   = WP_CONTENT_DIR . '/uploads/sites/' . $site_id;
		$source_dir   = $target_dir . '/sites/' . $site_id;
		$outside_dir  = sys_get_temp_dir() . '/wpmn-repair-' . wp_generate_password( 12, false );
		$source_file  = $source_dir . '/2024/09/image.txt';
		$target_link  = $target_dir . '/2024';
		$this->assertTrue( wp_mkdir_p( dirname( $source_file ) ) );
		$this->assertNotFalse( file_put_contents( $source_file, 'source media' ) );
		$this->assertTrue( wp_mkdir_p( $outside_dir ) );

		try {
			$repair = new WP_MS_Upload_Repair();
			$plan   = $repair->public_plan( $repair->inspect( $site_id ) );
			$this->assertSame( 'repairable', $plan['status'], $plan['reason'] );
			if ( ! symlink( $outside_dir, $target_link ) ) {
				$this->markTestSkipped( 'The test environment cannot create symlinks.' );
			}

			$result = $repair->execute( $plan );
			$this->assertWPError( $result );
			$this->assertSame( 'upload_plan_stale', $result->get_error_code() );
			$this->assertFileDoesNotExist( $outside_dir . '/09' );
			$this->assertSame( 'wp-content/uploads/sites/' . $site_id, get_blog_option( $site_id, 'upload_path' ) );
		} finally {
			if ( is_link( $target_link ) ) {
				unlink( $target_link );
			}
			if ( file_exists( $source_file ) ) {
				unlink( $source_file );
			}
			if ( is_dir( $source_dir . '/2024/09' ) ) {
				rmdir( $source_dir . '/2024/09' );
			}
			if ( is_dir( $source_dir . '/2024' ) ) {
				rmdir( $source_dir . '/2024' );
			}
			if ( is_dir( $outside_dir ) ) {
				rmdir( $outside_dir );
			}
		}
	}

	/**
	 * A journaled partial copy can resume without replacing its verified file.
	 */
	public function test_execute_resumes_a_verified_partial_copy() {
		$site_id    = $this->create_doubled_site();
		$source_dir = WP_CONTENT_DIR . '/uploads/sites/' . $site_id . '/sites/' . $site_id;
		$target_dir = WP_CONTENT_DIR . '/uploads/sites/' . $site_id;
		$name       = 'repair-resume-' . wp_generate_password( 12, false ) . '.txt';
		$this->assertTrue( wp_mkdir_p( $source_dir ) );
		$this->assertNotFalse( file_put_contents( $source_dir . '/' . $name, 'resume media' ) );

		try {
			$repair = new WP_MS_Upload_Repair();
			$plan   = $repair->public_plan( $repair->inspect( $site_id ) );
			$this->assertSame( 'repairable', $plan['status'], $plan['reason'] );
			$backup_key = 'wpmn_upload_repair_' . $plan['fingerprint'];
			$site       = get_site( $site_id );
			$this->assertNotFalse( file_put_contents( $target_dir . '/' . $name, 'resume media' ) );
			switch_to_blog( $site_id );
			$this->assertTrue( add_option( $backup_key, array(
				'fingerprint' => $plan['fingerprint'],
				'old_upload_path' => $plan['stored_upload_path'],
				'old_upload_url_path' => $plan['stored_upload_url_path'],
				'old_basedir' => $plan['effective_basedir'],
				'target_basedir' => $plan['target_basedir'],
				'site_domain' => $site->domain,
				'site_path' => $site->path,
				'site_registered' => $site->registered,
				'file_manifest_digest' => hash( 'sha256', wp_json_encode( array(
					$name => array(
						'size' => strlen( 'resume media' ),
						'hash' => hash( 'sha256', 'resume media' ),
					),
				) ) ),
				'status' => 'copying',
			), '', 'no' ) );
			restore_current_blog();

			$reference_queries = 0;
			$count_references  = static function( $query ) use ( &$reference_queries ) {
				if ( preg_match( '/SELECT COUNT\(\*\).*\b(?:post_content|guid|meta_value|option_value|comment_content)\b.*\bLIKE\b/i', $query ) ) {
					++$reference_queries;
				}
				return $query;
			};
			add_filter( 'query', $count_references );
			try {
				$fresh = $repair->inspect( $site_id );
			} finally {
				remove_filter( 'query', $count_references );
			}
			$this->assertSame( 'repairable', $fresh['status'], $fresh['reason'] );
			$this->assertSame( $plan['fingerprint'], $fresh['fingerprint'] );
			$this->assertSame( $plan['references'], $fresh['references'] );
			$this->assertSame( 5, $reference_queries );
			$result = $repair->execute( $plan );
			$this->assertIsArray( $result );
			$this->assertSame( 'repaired', $result['status'] );
			$this->assertSame( '', get_blog_option( $site_id, 'upload_path' ) );
		} finally {
			if ( file_exists( $source_dir . '/' . $name ) ) {
				unlink( $source_dir . '/' . $name );
			}
			if ( file_exists( $target_dir . '/' . $name ) ) {
				unlink( $target_dir . '/' . $name );
			}
		}
	}

	/**
	 * A journaled temporary file from an interrupted copy is removed and retried.
	 */
	public function test_execute_resumes_after_interrupted_temporary_copy() {
		$site_id    = $this->create_doubled_site();
		$source_dir = WP_CONTENT_DIR . '/uploads/sites/' . $site_id . '/sites/' . $site_id;
		$target_dir = WP_CONTENT_DIR . '/uploads/sites/' . $site_id;
		$name       = 'repair-interrupted-copy-' . wp_generate_password( 12, false ) . '.txt';
		$this->assertTrue( wp_mkdir_p( $source_dir ) );
		$this->assertNotFalse( file_put_contents( $source_dir . '/' . $name, 'complete media' ) );

		try {
			$repair     = new WP_MS_Upload_Repair();
			$plan       = $repair->public_plan( $repair->inspect( $site_id ) );
			$site       = get_site( $site_id );
			$backup_key = 'wpmn_upload_repair_' . $plan['fingerprint'];
			$temporary  = tempnam( $target_dir, '.wpmn-upload-repair-' );
			$this->assertSame( 'repairable', $plan['status'], $plan['reason'] );
			$this->assertNotFalse( $temporary );
			$this->assertNotFalse( file_put_contents( $temporary, 'partial' ) );
			switch_to_blog( $site_id );
			$this->assertTrue( add_option( $backup_key, array(
				'fingerprint'          => $plan['fingerprint'],
				'old_upload_path'      => $plan['stored_upload_path'],
				'old_upload_url_path'  => $plan['stored_upload_url_path'],
				'old_basedir'          => $plan['effective_basedir'],
				'target_basedir'       => $plan['target_basedir'],
				'site_domain'          => $site->domain,
				'site_path'            => $site->path,
				'site_registered'      => $site->registered,
				'file_manifest_digest' => hash( 'sha256', wp_json_encode( $plan['files'] ) ),
				'status'               => 'copying',
				'temporary_file'       => $temporary,
			), '', 'no' ) );
			restore_current_blog();

			$result = $repair->execute( $plan );
			$this->assertIsArray( $result );
			$this->assertSame( 'repaired', $result['status'] );
			$this->assertFileDoesNotExist( $temporary );
			$this->assertSame( 'complete media', file_get_contents( $target_dir . '/' . $name ) );
			$this->assertSame( '', get_blog_option( $site_id, 'upload_path' ) );
			$this->assertArrayNotHasKey( 'temporary_file', get_blog_option( $site_id, $backup_key ) );
		} finally {
			if ( file_exists( $temporary ) ) {
				unlink( $temporary );
			}
			if ( file_exists( $source_dir . '/' . $name ) ) {
				unlink( $source_dir . '/' . $name );
			}
			if ( file_exists( $target_dir . '/' . $name ) ) {
				unlink( $target_dir . '/' . $name );
			}
		}
	}

	/**
	 * A tampered journal cannot delete a temporary-looking file outside its target.
	 */
	public function test_execute_refuses_unsafe_journaled_temporary_path() {
		$site_id    = $this->create_doubled_site();
		$repair     = new WP_MS_Upload_Repair();
		$plan       = $repair->public_plan( $repair->inspect( $site_id ) );
		$site       = get_site( $site_id );
		$backup_key = 'wpmn_upload_repair_' . $plan['fingerprint'];
		$name       = '.wpmn-upload-repair-' . wp_generate_password( 12, false, false );
		$outside    = WP_CONTENT_DIR . '/' . $name;
		$temporary  = $plan['target_basedir'] . '/../../../' . $name;
		$this->assertNotFalse( file_put_contents( $outside, 'do not delete' ) );

		try {
			switch_to_blog( $site_id );
			$this->assertTrue( add_option( $backup_key, array(
				'fingerprint'          => $plan['fingerprint'],
				'old_upload_path'      => $plan['stored_upload_path'],
				'old_upload_url_path'  => $plan['stored_upload_url_path'],
				'old_basedir'          => $plan['effective_basedir'],
				'target_basedir'       => $plan['target_basedir'],
				'site_domain'          => $site->domain,
				'site_path'            => $site->path,
				'site_registered'      => $site->registered,
				'file_manifest_digest' => hash( 'sha256', wp_json_encode( $plan['files'] ) ),
				'status'               => 'copying',
				'temporary_file'       => $temporary,
			), '', 'no' ) );
			restore_current_blog();

			$result = $repair->execute( $plan );
			$this->assertWPError( $result );
			$this->assertSame( 'upload_temporary_invalid', $result->get_error_code() );
			$this->assertSame( 'do not delete', file_get_contents( $outside ) );
		} finally {
			if ( file_exists( $outside ) ) {
				unlink( $outside );
			}
		}
	}

	/**
	 * A damaged copying record must not supply wrong rollback values.
	 */
	public function test_execute_refuses_corrupt_copying_record() {
		$site_id    = $this->create_doubled_site();
		$repair     = new WP_MS_Upload_Repair();
		$plan       = $repair->public_plan( $repair->inspect( $site_id ) );
		$site       = get_site( $site_id );
		$backup_key = 'wpmn_upload_repair_' . $plan['fingerprint'];
		$backup     = array(
			'fingerprint'          => $plan['fingerprint'],
			'old_upload_path'      => $plan['stored_upload_path'],
			'old_upload_url_path'  => $plan['stored_upload_url_path'],
			'old_basedir'          => $plan['effective_basedir'],
			'target_basedir'       => $plan['target_basedir'],
			'site_domain'          => $site->domain,
			'site_path'            => $site->path,
			'site_registered'      => $site->registered,
			'file_manifest_digest' => str_repeat( 'a', 64 ),
			'status'               => 'copying',
		);
		switch_to_blog( $site_id );
		$this->assertTrue( add_option( $backup_key, $backup, '', 'no' ) );
		restore_current_blog();

		$result = $repair->execute( $plan );
		$this->assertWPError( $result );
		$this->assertSame( 'upload_backup_invalid', $result->get_error_code() );

		$backup['file_manifest_digest'] = hash( 'sha256', wp_json_encode( array() ) );
		$backup['old_upload_path']      = 'wp-content/incorrect';
		update_blog_option( $site_id, $backup_key, $backup );
		$result = $repair->execute( $plan );
		$this->assertWPError( $result );
		$this->assertSame( 'upload_backup_invalid', $result->get_error_code() );
		$this->assertSame( 'wp-content/uploads/sites/' . $site_id, get_blog_option( $site_id, 'upload_path' ) );
	}

	/**
	 * An interrupted journal can finish after options and copies were saved.
	 */
	public function test_execute_finishes_verified_post_option_interruption() {
		$site_id    = $this->create_doubled_site();
		$source_dir = WP_CONTENT_DIR . '/uploads/sites/' . $site_id . '/sites/' . $site_id;
		$target_dir = WP_CONTENT_DIR . '/uploads/sites/' . $site_id;
		$name       = 'repair-interrupted-' . wp_generate_password( 12, false ) . '.txt';
		$this->assertTrue( wp_mkdir_p( $source_dir ) );
		$this->assertNotFalse( file_put_contents( $source_dir . '/' . $name, 'original media' ) );

		try {
			$repair = new WP_MS_Upload_Repair();
			$plan   = $repair->public_plan( $repair->inspect( $site_id ) );
			$site   = get_site( $site_id );
			$this->assertSame( 'repairable', $plan['status'], $plan['reason'] );
			$this->assertNotFalse( file_put_contents( $target_dir . '/' . $name, 'original media' ) );
			$backup_key = 'wpmn_upload_repair_' . $plan['fingerprint'];
			$backup     = array(
				'fingerprint'          => $plan['fingerprint'],
				'old_upload_path'      => $plan['stored_upload_path'],
				'old_upload_url_path'  => $plan['stored_upload_url_path'],
				'old_basedir'          => $plan['effective_basedir'],
				'target_basedir'       => $plan['target_basedir'],
				'site_domain'          => $site->domain,
				'site_path'            => $site->path,
				'site_registered'      => $site->registered,
				'file_manifest_digest' => hash( 'sha256', wp_json_encode( $plan['files'] ) ),
				'status'               => 'copying',
			);
			switch_to_blog( $site_id );
			$this->assertTrue( add_option( $backup_key, $backup, '', 'no' ) );
			restore_current_blog();
			update_blog_option( $site_id, 'upload_path', '' );
			update_blog_option( $site_id, 'upload_url_path', '' );
			$this->assertNotFalse( file_put_contents( $target_dir . '/' . $name, 'corrupted' ) );
			$rejected = $repair->execute( $plan );
			$this->assertWPError( $rejected );
			$this->assertSame( 'upload_repair_changed', $rejected->get_error_code() );
			$this->assertSame( 'copying', get_blog_option( $site_id, $backup_key )['status'] );
			$this->assertNotFalse( file_put_contents( $target_dir . '/' . $name, 'original media' ) );
			$backup['site_domain'] = 'changed.example';
			update_blog_option( $site_id, $backup_key, $backup );
			$rejected = $repair->execute( $plan );
			$this->assertWPError( $rejected );
			$this->assertSame( 'upload_backup_invalid', $rejected->get_error_code() );
			$backup['site_domain'] = $site->domain;
			update_blog_option( $site_id, $backup_key, $backup );

			$result = $repair->execute( $plan );
			$this->assertIsArray( $result );
			$this->assertSame( 'recovered', $result['status'] );
			$this->assertSame( 'complete', get_blog_option( $site_id, $backup_key )['status'] );
		} finally {
			unlink( $source_dir . '/' . $name );
			if ( file_exists( $target_dir . '/' . $name ) ) {
				unlink( $target_dir . '/' . $name );
			}
		}
	}

	/**
	 * A crash between option writes completes the pending URL correction.
	 */
	public function test_execute_finishes_partial_option_update() {
		$site_id    = $this->create_doubled_site();
		$source_dir = WP_CONTENT_DIR . '/uploads/sites/' . $site_id . '/sites/' . $site_id;
		$target_dir = WP_CONTENT_DIR . '/uploads/sites/' . $site_id;
		$name       = 'repair-option-interrupted-' . wp_generate_password( 12, false ) . '.txt';
		$this->assertTrue( wp_mkdir_p( $source_dir ) );
		$this->assertNotFalse( file_put_contents( $source_dir . '/' . $name, 'original media' ) );

		try {
			$repair     = new WP_MS_Upload_Repair();
			$plan       = $repair->public_plan( $repair->inspect( $site_id ) );
			$site       = get_site( $site_id );
			$backup_key = 'wpmn_upload_repair_' . $plan['fingerprint'];
			$this->assertSame( 'repairable', $plan['status'], $plan['reason'] );
			$this->assertNotFalse( file_put_contents( $target_dir . '/' . $name, 'original media' ) );
			switch_to_blog( $site_id );
			$this->assertTrue( add_option( $backup_key, array(
				'fingerprint'          => $plan['fingerprint'],
				'old_upload_path'      => $plan['stored_upload_path'],
				'old_upload_url_path'  => $plan['stored_upload_url_path'],
				'old_basedir'          => $plan['effective_basedir'],
				'target_basedir'       => $plan['target_basedir'],
				'site_domain'          => $site->domain,
				'site_path'            => $site->path,
				'site_registered'      => $site->registered,
				'file_manifest_digest' => hash( 'sha256', wp_json_encode( $plan['files'] ) ),
				'status'               => 'copying',
			), '', 'no' ) );
			restore_current_blog();
			update_blog_option( $site_id, 'upload_path', '' );

			$result = $repair->execute( $plan );
			$this->assertIsArray( $result );
			$this->assertSame( 'recovered', $result['status'] );
			$this->assertSame( '', get_blog_option( $site_id, 'upload_path' ) );
			$this->assertSame( '', get_blog_option( $site_id, 'upload_url_path' ) );
			$this->assertSame( 'complete', get_blog_option( $site_id, $backup_key )['status'] );
		} finally {
			if ( file_exists( $source_dir . '/' . $name ) ) {
				unlink( $source_dir . '/' . $name );
			}
			if ( file_exists( $target_dir . '/' . $name ) ) {
				unlink( $target_dir . '/' . $name );
			}
		}
	}
}
