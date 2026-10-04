<?php
/**
 * Conservative upload-path repair planning for existing multisite sites.
 *
 * @package WPMN
 */

/**
 * Plans repairs without changing WordPress options or the filesystem.
 */
class WP_MS_Upload_Repair {

	/**
	 * Inspect one site in its own blog and network context.
	 *
	 * @param int  $site_id Site ID.
	 * @param bool $allow_copied Accept verified target copies from an existing repair record.
	 * @return array<string, mixed> The plan and its current file inventory.
	 */
	public function inspect( $site_id, $allow_copied = false ) {
		$site = get_site( $site_id );
		if ( ! $site || ! get_network( (int) $site->site_id ) ) {
			return $this->result( $site_id, 0, 'manual', 'Site or network does not exist.' );
		}

		$network_id = (int) $site->site_id;
		if ( (int) get_current_network_id() !== $network_id ) {
			$plan                           = $this->result( $site_id, $network_id, 'manual', 'Run from this network main site to use its bootstrapped upload URL.' );
			$plan['stored_upload_path']     = (string) get_blog_option( $site_id, 'upload_path', '' );
			$plan['stored_upload_url_path'] = (string) get_blog_option( $site_id, 'upload_url_path', '' );
			return $plan;
		}
		if ( ! switch_to_network( $network_id, true ) ) {
			return $this->result( $site_id, $network_id, 'manual', 'Could not switch to the site network.' );
		}

		switch_to_blog( (int) $site_id );
		try {
			return $this->inspect_current_site( (int) $site_id, $network_id, $allow_copied, $site );
		} finally {
			restore_current_blog();
			restore_current_network();
		}
	}

	/**
	 * Return a plan suitable for saving as JSON, including file operations.
	 *
	 * @param array<string, mixed> $plan Full plan.
	 * @return array<string, mixed> Complete plan.
	 */
	public function public_plan( $plan ) {
		return $plan;
	}

	/**
	 * Execute one saved plan after rechecking its live inputs.
	 *
	 * Existing files are never overwritten or removed. A site option keeps the
	 * original settings and a stable plan fingerprint for later rollback.
	 *
	 * @param array<string, mixed> $saved The saved dry-run plan.
	 * @return array<string, mixed>|WP_Error Result or refusal.
	 */
	public function execute( $saved ) {
		if ( ! isset( $saved['site_id'], $saved['network_id'], $saved['fingerprint'], $saved['status'] ) || ! is_int( $saved['site_id'] ) || ! is_int( $saved['network_id'] ) || ! is_string( $saved['fingerprint'] ) || ! preg_match( '/^[a-f0-9]{64}$/', $saved['fingerprint'] ) || 'repairable' !== $saved['status'] ) {
			return new WP_Error( 'upload_plan_invalid', 'The saved plan is not repairable.' );
		}
		$site_id = (int) $saved['site_id'];
		$site    = get_site( $site_id );
		if ( ! $site || (int) $site->site_id !== (int) $saved['network_id'] ) {
			return new WP_Error( 'upload_site_changed', 'The site or its network changed since the dry run.' );
		}
		if ( (int) get_current_network_id() !== (int) $saved['network_id'] ) {
			return new WP_Error( 'upload_network_context', 'Run from the site network main site before executing this plan.' );
		}

		$backup_key = 'wpmn_upload_repair_' . $saved['fingerprint'];
		if ( ! switch_to_network( (int) $site->site_id, true ) ) {
			return new WP_Error( 'upload_network_unavailable', 'Could not switch to the site network.' );
		}
		switch_to_blog( $site_id );
		try {
			$backup = get_option( $backup_key, false );
			if ( false !== $backup && ( ! is_array( $backup ) || ! isset( $backup['fingerprint'], $backup['status'], $backup['old_upload_path'], $backup['old_upload_url_path'], $backup['old_basedir'], $backup['target_basedir'], $backup['file_manifest_digest'], $backup['site_domain'], $backup['site_path'], $backup['site_registered'] ) || $backup['fingerprint'] !== $saved['fingerprint'] || ! in_array( $backup['status'], array( 'copying', 'complete' ), true ) || ! is_string( $backup['file_manifest_digest'] ) || ! preg_match( '/^[a-f0-9]{64}$/', $backup['file_manifest_digest'] ) || ( isset( $backup['temporary_file'] ) && ! is_string( $backup['temporary_file'] ) ) || $backup['site_domain'] !== $site->domain || $backup['site_path'] !== $site->path || $backup['site_registered'] !== $site->registered ) ) {
				return new WP_Error( 'upload_backup_invalid', 'The existing repair record needs manual inspection.' );
			}
			if ( is_array( $backup ) && isset( $backup['temporary_file'] ) ) {
				$cleaned = $this->discard_temporary_file( $backup, $backup_key, $site_id );
				if ( is_wp_error( $cleaned ) ) {
					return $cleaned;
				}
			}
			$pending_url = is_array( $backup ) && 'copying' === $backup['status'] && '' !== $backup['old_upload_url_path'] && '' === get_option( 'upload_path' ) && get_option( 'upload_url_path' ) === $backup['old_upload_url_path'];
			if ( is_array( $backup ) && ( 'complete' === $backup['status'] || $pending_url || ( '' === get_option( 'upload_path' ) && '' === get_option( 'upload_url_path' ) ) ) ) {
				$verified = $this->verify_completed_repair( $site_id, $backup, $pending_url );
				if ( is_wp_error( $verified ) ) {
					return $verified;
				}
				if ( $pending_url ) {
					if ( ! update_option( 'upload_url_path', '' ) ) {
						$restored = $this->restore_options( $backup );
						return new WP_Error( 'upload_option_failed', $restored ? 'Could not finish the corrected settings; original settings were restored.' : 'Could not finish or restore the upload settings; manual recovery is required.' );
					}
					$verified = $this->verify_completed_repair( $site_id, $backup );
					if ( is_wp_error( $verified ) ) {
						$restored = $this->restore_options( $backup );
						return new WP_Error( 'upload_verify_failed', $restored ? 'The corrected path did not verify; original settings were restored.' : 'The corrected path did not verify, and the original settings could not be restored.' );
					}
				}
				if ( 'copying' === $backup['status'] ) {
					$backup['status']         = 'complete';
					$backup['completed_at']   = current_time( 'mysql', true );
					$backup['verified_files'] = $verified['verified_files'];
					if ( ! update_option( $backup_key, $backup, false ) ) {
						return new WP_Error( 'upload_journal_failed', 'The site is repaired, but its repair record could not be completed.' );
					}
					return array(
						'site_id'       => $site_id,
						'status'        => 'recovered',
						'backup_option' => $backup_key,
					);
				}
				return array(
					'site_id'       => $site_id,
					'status'        => 'already-repaired',
					'backup_option' => $backup_key,
				);
			}

			$live = $this->inspect( $site_id, is_array( $backup ) );
			if ( 'repairable' !== $live['status'] || ! hash_equals( $saved['fingerprint'], $live['fingerprint'] ) ) {
				return new WP_Error( 'upload_plan_stale', 'Upload settings or files changed since the dry run.' );
			}
			$manifest = wp_json_encode( $live['files'] );
			if ( false === $manifest ) {
				return new WP_Error( 'upload_backup_failed', 'Could not encode the upload file inventory.' );
			}
			if ( is_array( $backup ) && ( $backup['old_upload_path'] !== $live['stored_upload_path'] || $backup['old_upload_url_path'] !== $live['stored_upload_url_path'] || $backup['old_basedir'] !== $live['effective_basedir'] || $backup['target_basedir'] !== $live['target_basedir'] || ! hash_equals( $backup['file_manifest_digest'], hash( 'sha256', $manifest ) ) ) ) {
				return new WP_Error( 'upload_backup_invalid', 'The copying record does not match the live repair plan.' );
			}

			if ( false === $backup ) {
				$backup = array(
					'fingerprint'          => $saved['fingerprint'],
					'created_at'           => current_time( 'mysql', true ),
					'old_upload_path'      => $live['stored_upload_path'],
					'old_upload_url_path'  => $live['stored_upload_url_path'],
					'old_basedir'          => $live['effective_basedir'],
					'target_basedir'       => $live['target_basedir'],
					'site_domain'          => $site->domain,
					'site_path'            => $site->path,
					'site_registered'      => $site->registered,
					'file_manifest_digest' => hash( 'sha256', $manifest ),
					'status'               => 'copying',
				);
				if ( ! add_option( $backup_key, $backup, '', false ) ) {
					return new WP_Error( 'upload_backup_failed', 'Could not save the original upload settings.' );
				}
			}

			foreach ( $live['files'] as $relative => $details ) {
				$source      = $live['effective_basedir'] . '/' . $relative;
				$destination = $live['target_basedir'] . '/' . $relative;
				if ( ! is_file( $source ) || hash_file( 'sha256', $source ) !== $details['hash'] || is_link( $destination ) ) {
					return new WP_Error( 'upload_file_changed', 'An upload file changed or the target was occupied during repair.' );
				}
				if ( file_exists( $destination ) ) {
					if ( ! is_file( $destination ) || hash_file( 'sha256', $destination ) !== $details['hash'] ) {
						return new WP_Error( 'upload_file_changed', 'A target file is occupied or differs from the original.' );
					}
					continue;
				}
				// Check before creation as well: wp_mkdir_p() would follow an existing
				// symlink below the uploads root and create directories outside it.
				$uploads_root = WP_CONTENT_DIR . '/uploads';
				if ( $this->has_symlink_component( dirname( $destination ), $uploads_root ) || ! wp_mkdir_p( dirname( $destination ) ) || $this->has_symlink_component( dirname( $destination ), $uploads_root ) ) {
					return new WP_Error( 'upload_target_unwritable', 'Could not create a safe target directory.' );
				}

				$copied = $this->copy_file_exclusively( $source, $destination, $details, $backup, $backup_key, $site_id );
				if ( is_wp_error( $copied ) ) {
					return $copied;
				}
			}

			$rechecked = $this->inspect( $site_id, true );
			if ( 'repairable' !== $rechecked['status'] || ! hash_equals( $saved['fingerprint'], $rechecked['fingerprint'] ) ) {
				return new WP_Error( 'upload_plan_stale', 'Upload settings or files changed during repair.' );
			}
			foreach ( $rechecked['files'] as $relative => $details ) {
				$destination = $live['target_basedir'] . '/' . $relative;
				if ( ! is_file( $destination ) || hash_file( 'sha256', $destination ) !== $details['hash'] ) {
					return new WP_Error( 'upload_copy_mismatch', 'A target file did not verify; original settings were left untouched.' );
				}
			}
			if ( ! update_option( 'upload_path', $live['proposed_upload_path'] ) || ( '' !== $live['stored_upload_url_path'] && ! update_option( 'upload_url_path', $live['proposed_upload_url_path'] ) ) ) {
				$restored = $this->restore_options( $backup );
				return new WP_Error( 'upload_option_failed', $restored ? 'Could not save the corrected settings; original settings were restored.' : 'Could not save or restore the upload settings; manual recovery is required.' );
			}
			$verified = wp_upload_dir( null, false, true );
			if ( $verified['basedir'] !== $live['target_basedir'] || $verified['baseurl'] !== $live['bootstrap_target_baseurl'] ) {
				$restored = $this->restore_options( $backup );
				return new WP_Error( 'upload_verify_failed', $restored ? 'The corrected path did not verify; original settings were restored.' : 'The corrected path did not verify, and the original settings could not be restored.' );
			}
			$backup['status']         = 'complete';
			$backup['completed_at']   = current_time( 'mysql', true );
			$backup['verified_files'] = count( $live['files'] );
			if ( ! update_option( $backup_key, $backup, false ) ) {
				return new WP_Error( 'upload_journal_failed', 'The site was repaired, but its repair record needs manual inspection.' );
			}
			return array(
				'site_id'            => $site_id,
				'status'             => 'repaired',
				'verified_files'     => count( $live['files'] ),
				'backup_option'      => $backup_key,
				'old_directory_kept' => $live['effective_basedir'],
			);
		} finally {
			restore_current_blog();
			restore_current_network();
		}
	}

	/**
	 * Verify corrected options and every copied file before trusting a journal.
	 *
	 * @param int                  $site_id Site ID.
	 * @param array<string, mixed> $backup Saved repair record.
	 * @param bool                 $pending_url Accept the original URL option after the path option was corrected.
	 * @return array<string, int>|WP_Error Verified file count or refusal.
	 */
	private function verify_completed_repair( $site_id, $backup, $pending_url = false ) {
		$verified     = wp_upload_dir( null, false, true );
		$target       = WP_CONTENT_DIR . '/uploads/sites/' . $site_id;
		$upload_url   = get_option( 'upload_url_path' );
		$expected_url = $pending_url ? $backup['old_upload_url_path'] . '/sites/' . $site_id : $this->default_upload_url() . '/sites/' . $site_id;
		if ( '' !== get_option( 'upload_path' ) || ( $pending_url ? $backup['old_upload_url_path'] !== $upload_url : '' !== $upload_url ) || $target !== $verified['basedir'] || $expected_url !== $verified['baseurl'] ) {
			return new WP_Error( 'upload_repair_changed', 'The corrected upload settings changed since repair.' );
		}
		if ( $backup['old_basedir'] !== $target . '/sites/' . $site_id || $backup['target_basedir'] !== $target ) {
			return new WP_Error( 'upload_backup_invalid', 'The repair record has unexpected directories.' );
		}
		$copied = $this->inventory( $target . '/sites/' . $site_id, $target, true );
		if ( is_wp_error( $copied ) || $copied['collisions'] ) {
			return new WP_Error( 'upload_repair_changed', 'A copied file or the retained original changed since repair.' );
		}
		$manifest = wp_json_encode( $copied['files'] );
		if ( false === $manifest || ! hash_equals( $backup['file_manifest_digest'], hash( 'sha256', $manifest ) ) ) {
			return new WP_Error( 'upload_repair_changed', 'A copied file or the retained original changed since repair.' );
		}
		foreach ( $copied['files'] as $relative => $details ) {
			$destination = $target . '/' . $relative;
			if ( ! is_file( $destination ) || is_link( $destination ) || filesize( $destination ) !== $details['size'] || hash_file( 'sha256', $destination ) !== $details['hash'] ) {
				return new WP_Error( 'upload_repair_changed', 'A copied file or the retained original changed since repair.' );
			}
		}
		return array( 'verified_files' => count( $copied['files'] ) );
	}

	/**
	 * Restore and verify the pre-repair options without touching either directory.
	 *
	 * @param array<string, mixed> $backup Stored original settings.
	 * @return bool Whether both values match their originals.
	 */
	private function restore_options( $backup ) {
		update_option( 'upload_url_path', $backup['old_upload_url_path'] );
		update_option( 'upload_path', $backup['old_upload_path'] );
		return get_option( 'upload_path' ) === $backup['old_upload_path'] && get_option( 'upload_url_path' ) === $backup['old_upload_url_path'];
	}

	/**
	 * Inspect a site after both contexts have been switched.
	 *
	 * @param int     $site_id Site ID.
	 * @param int     $network_id Network ID.
	 * @param bool    $allow_copied Accept verified target copies from an existing repair record.
	 * @param WP_Site $site Current site record.
	 * @return array<string, mixed> Plan.
	 */
	private function inspect_current_site( $site_id, $network_id, $allow_copied, $site ) {
		$stored_path                    = (string) get_option( 'upload_path', '' );
		$stored_url                     = (string) get_option( 'upload_url_path', '' );
		$plan                           = $this->result( $site_id, $network_id, 'unchanged', 'No known doubled upload path.' );
		$plan['stored_upload_path']     = $stored_path;
		$plan['stored_upload_url_path'] = $stored_url;

		if ( get_network_option( $network_id, 'ms_files_rewriting' ) ) {
			return $this->with_status( $plan, 'manual', 'Legacy files rewriting needs a site-specific migration.' );
		}
		if ( defined( 'UPLOADS' ) || defined( 'BLOGUPLOADDIR' ) ) {
			return $this->with_status( $plan, 'manual', 'Request-scoped upload constants affect this site.' );
		}
		if ( has_filter( 'upload_dir' ) ) {
			return $this->with_status( $plan, 'manual', 'An upload_dir filter may change the effective location.' );
		}
		foreach ( array( 'upload_path', 'upload_url_path' ) as $option ) {
			if ( has_filter( 'pre_option_' . $option ) || has_filter( 'option_' . $option ) || has_filter( 'pre_update_option_' . $option ) ) {
				return $this->with_status( $plan, 'manual', 'An upload option filter may change the stored or effective location.' );
			}
		}
		if ( ! is_multisite() ) {
			return $this->with_status( $plan, 'manual', 'The modern /sites/ layout is not active.' );
		}

		$suffix        = '/sites/' . $site_id;
		$default_path  = WP_CONTENT_DIR . '/uploads';
		$default_url   = $this->default_upload_url();
		$expected_path = $default_path . $suffix;
		$expected_url  = $default_url . $suffix;
		$site_url      = untrailingslashit( (string) get_option( 'siteurl' ) ) . '/wp-content/uploads' . $suffix;
		$known_paths   = array( 'wp-content/uploads' . $suffix, $expected_path );
		$known_urls    = array( '', $expected_url, $site_url );

		if ( ! in_array( $stored_path, $known_paths, true ) ) {
			$site_suffix_pattern = '~' . preg_quote( $suffix, '~' ) . '(?:/|$)~';
			if ( preg_match( $site_suffix_pattern, $stored_path ) || preg_match( $site_suffix_pattern, $stored_url ) ) {
				return $this->with_status( $plan, 'manual', 'A custom upload base contains the site suffix.' );
			}
			return $plan;
		}
		if ( ! in_array( $stored_url, $known_urls, true ) ) {
			return $this->with_status( $plan, 'manual', 'The stored upload URL is custom.' );
		}

		// Do not create the uploads directory; refresh Core's per-site cache.
		$uploads                   = wp_upload_dir( null, false, true );
		$plan['effective_basedir'] = $uploads['basedir'];
		$plan['effective_baseurl'] = $uploads['baseurl'];
		$old_path                  = $expected_path . $suffix;
		// Core derives a relative upload_path URL from the site's siteurl,
		// even when WP_CONTENT_URL remains bound to the CLI main site.
		$old_url = '' !== $stored_url
			? $stored_url . $suffix
			: ( 'wp-content/uploads' . $suffix === $stored_path ? $site_url . $suffix : $expected_url );
		if ( $old_path !== $uploads['basedir'] || $old_url !== $uploads['baseurl'] ) {
			return $this->with_status( $plan, 'manual', 'The effective path or URL differs from the known doubled layout.' );
		}

		$plan['proposed_upload_path']     = '';
		$plan['proposed_upload_url_path'] = '';
		$plan['target_basedir']           = $expected_path;
		$plan['bootstrap_target_baseurl'] = $expected_url;

		if ( $this->has_symlink_component( $old_path, $default_path ) || $this->has_symlink_component( $expected_path, $default_path ) ) {
			return $this->with_status( $plan, 'manual', 'A symlink occurs in the upload path.' );
		}
		$plan['target_writable'] = $this->target_is_writable( $expected_path );
		if ( ! $plan['target_writable'] ) {
			return $this->with_status( $plan, 'manual', 'The target upload directory or its nearest existing parent is not writable.' );
		}

		$inventory = $this->inventory( $old_path, $expected_path, $allow_copied );
		if ( is_wp_error( $inventory ) ) {
			return $this->with_status( $plan, 'manual', $inventory->get_error_message() );
		}
		$plan['files']      = $inventory['files'];
		$plan['file_count'] = count( $inventory['files'] );
		$plan['collisions'] = $inventory['collisions'];
		$references         = $old_url === $expected_url
			? array_fill_keys( array( 'posts', 'postmeta', 'options', 'comments', 'commentmeta' ), 0 )
			: $this->reference_counts( $old_url );
		if ( is_wp_error( $references ) ) {
			return $this->with_status( $plan, 'manual', $references->get_error_message() );
		}
		$plan['references'] = $references;
		if ( $plan['collisions'] ) {
			if ( ! $allow_copied ) {
				$resumable = $this->inspect_current_site( $site_id, $network_id, true, $site );
				if ( 'repairable' === $resumable['status'] && $this->has_matching_copying_record( $resumable, $site ) ) {
					return $resumable;
				}
			}
			return $this->with_status( $plan, 'manual', 'Files already exist at one or more target paths.' );
		}

		$plan['status']   = 'repairable';
		$plan['reason']   = 'Known doubled modern upload path.';
		$fingerprint_data = wp_json_encode( array(
			$site_id,
			$network_id,
			$site->domain,
			$site->path,
			$site->registered,
			$stored_path,
			$stored_url,
			$uploads['basedir'],
			$uploads['baseurl'],
			$expected_path,
			$expected_url,
			$plan['target_writable'],
			$inventory['files'],
		) );
		if ( false === $fingerprint_data ) {
			return $this->with_status( $plan, 'manual', 'Could not encode the repair plan.' );
		}
		$plan['fingerprint'] = hash( 'sha256', $fingerprint_data );
		return $plan;
	}

	/**
	 * Trust verified target copies only when a matching repair journal exists.
	 *
	 * @param array<string, mixed> $plan Candidate plan with verified copies.
	 * @param WP_Site              $site Site being inspected.
	 * @return bool Whether the copying record matches the current plan.
	 */
	private function has_matching_copying_record( $plan, $site ) {
		$backup = get_option( 'wpmn_upload_repair_' . $plan['fingerprint'], false );
		if ( ! is_array( $backup ) || ! isset( $backup['fingerprint'], $backup['status'], $backup['old_upload_path'], $backup['old_upload_url_path'], $backup['old_basedir'], $backup['target_basedir'], $backup['file_manifest_digest'], $backup['site_domain'], $backup['site_path'], $backup['site_registered'] ) || 'copying' !== $backup['status'] || $plan['fingerprint'] !== $backup['fingerprint'] || ! is_string( $backup['file_manifest_digest'] ) ) {
			return false;
		}
		$manifest = wp_json_encode( $plan['files'] );
		return false !== $manifest
			&& $backup['old_upload_path'] === $plan['stored_upload_path']
			&& $backup['old_upload_url_path'] === $plan['stored_upload_url_path']
			&& $backup['old_basedir'] === $plan['effective_basedir']
			&& $backup['target_basedir'] === $plan['target_basedir']
			&& $backup['site_domain'] === $site->domain
			&& $backup['site_path'] === $site->path
			&& $backup['site_registered'] === $site->registered
			&& hash_equals( $backup['file_manifest_digest'], hash( 'sha256', $manifest ) );
	}

	/**
	 * Inventory files without following links or creating directories.
	 *
	 * @param string $source Existing doubled directory.
	 * @param string $target Correct single-suffix directory.
	 * @param bool   $allow_copied Accept verified target copies from an existing repair record.
	 * @return array<string, mixed>|WP_Error Inventory or reason to stop.
	 */
	private function inventory( $source, $target, $allow_copied = false ) {
		$files      = array();
		$collisions = array();
		if ( ! file_exists( $source ) ) {
			return array(
				'files'      => $files,
				'collisions' => $collisions,
			);
		}
		if ( ! is_dir( $source ) || ! is_readable( $source ) ) {
			return new WP_Error( 'upload_source_unavailable', 'The doubled upload directory is unavailable.' );
		}
		if ( file_exists( $target ) && ! is_dir( $target ) ) {
			return new WP_Error( 'upload_target_unavailable', 'The target upload path is not a directory.' );
		}

		try {
			$iterator = new RecursiveIteratorIterator(
				new RecursiveDirectoryIterator( $source, FilesystemIterator::SKIP_DOTS ),
				RecursiveIteratorIterator::LEAVES_ONLY
			);
			foreach ( $iterator as $file ) {
				$path = $file->getPathname();
				if ( $file->isLink() || ! $file->isFile() || ! $file->isReadable() ) {
					return new WP_Error( 'upload_file_unavailable', 'The doubled upload directory contains a link or unreadable entry.' );
				}
				$relative    = substr( $path, strlen( $source ) + 1 );
				$destination = $target . '/' . $relative;
				$hash        = hash_file( 'sha256', $path );
				if ( false === $hash ) {
					return new WP_Error( 'upload_hash_failed', 'Could not hash an upload file.' );
				}
				if ( 0 === strpos( $destination, $source . '/' ) || $this->has_blocking_parent( $destination, $target ) || is_link( $destination ) || ( file_exists( $destination ) && ( ! $allow_copied || ! is_file( $destination ) || hash_file( 'sha256', $destination ) !== $hash ) ) ) {
					$collisions[] = $relative;
				}
				$files[ $relative ] = array(
					'size' => $file->getSize(),
					'hash' => $hash,
				);
			}
		} catch ( Exception $error ) {
			return new WP_Error( 'upload_inventory_failed', 'Could not read the doubled upload directory.' );
		}
		ksort( $files );
		return array(
			'files'      => $files,
			'collisions' => $collisions,
		);
	}

	/**
	 * Detect a file or link where a target file needs a directory.
	 *
	 * @param string $destination Target file path.
	 * @param string $target      Root target directory.
	 * @return bool Whether an existing parent blocks safe creation.
	 */
	private function has_blocking_parent( $destination, $target ) {
		$parent = dirname( $destination );
		while ( $parent !== $target ) {
			if ( is_link( $parent ) || ( file_exists( $parent ) && ! is_dir( $parent ) ) ) {
				return true;
			}
			$next = dirname( $parent );
			if ( $next === $parent ) {
				return true;
			}
			$parent = $next;
		}
		return false;
	}

	/**
	 * Count stored references requiring manual attention after a repair.
	 *
	 * @param string $old_url Doubled upload URL.
	 * @return array<string, int>|WP_Error Counts by storage surface or query failure.
	 */
	private function reference_counts( $old_url ) {
		global $wpdb;
		$like    = '%' . $wpdb->esc_like( $old_url ) . '%';
		$counts  = array();
		$queries = array(
			'posts'       => $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_content LIKE %s OR guid LIKE %s", $like, $like ),
			'postmeta'    => $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_value LIKE %s", $like ),
			'options'     => $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_value LIKE %s", $like ),
			'comments'    => $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_content LIKE %s", $like ),
			'commentmeta' => $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->commentmeta} WHERE meta_value LIKE %s", $like ),
		);
		foreach ( $queries as $surface => $query ) {
			$count = $wpdb->get_var( $query ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- Query was prepared above for this surface.
			if ( $wpdb->last_error || null === $count ) {
				return new WP_Error( 'upload_reference_query_failed', 'Could not inspect stored upload URLs.' );
			}
			$counts[ $surface ] = (int) $count;
		}
		return $counts;
	}

	/**
	 * Check each existing component below a trusted root.
	 *
	 * @param string $path Path to inspect.
	 * @param string $root Trusted root. A deployment may symlink this root or one of its parents.
	 * @return bool Whether a component is a symlink.
	 * @phpstan-impure
	 */
	private function has_symlink_component( $path, $root ) {
		$part = $path;
		while ( $root !== $part && '/' !== $part && '.' !== $part ) {
			if ( is_link( $part ) ) {
				return true;
			}
			$parent = dirname( $part );
			if ( $parent === $part ) {
				break;
			}
			$part = $parent;
		}
		return false;
	}

	/**
	 * Copy through a journaled temporary file, then publish without overwriting.
	 *
	 * @param string               $source Source file.
	 * @param string               $destination Final target file.
	 * @param array<string, mixed> $details Expected size and hash.
	 * @param array<string, mixed> $backup Repair journal, passed by reference.
	 * @param string               $backup_key Repair journal option name.
	 * @param int                  $site_id Site ID.
	 * @return true|WP_Error Whether the verified file was published.
	 */
	private function copy_file_exclusively( $source, $destination, $details, &$backup, $backup_key, $site_id ) {
		$input = @fopen( $source, 'rb' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged,WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Verified local upload path; return an explicit error below.
		if ( false === $input ) {
			return new WP_Error( 'upload_copy_failed', 'Could not open an upload file for copying.' );
		}
		$temporary = dirname( $destination ) . '/.wpmn-upload-repair-' . wp_generate_password( 32, false, false );
		if ( file_exists( $temporary ) || is_link( $temporary ) ) {
			fclose( $input );
			return new WP_Error( 'upload_copy_failed', 'Could not reserve a temporary upload file.' );
		}
		$backup['temporary_file'] = $temporary;
		if ( ! update_option( $backup_key, $backup, false ) ) {
			fclose( $input );
			unset( $backup['temporary_file'] );
			return new WP_Error( 'upload_journal_failed', 'Could not journal a temporary upload copy.' );
		}
		$output = @fopen( $temporary, 'xb' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged,WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Exclusive creation follows a journal write and prevents replacement.
		if ( false === $output ) {
			fclose( $input );
			return $this->temporary_copy_error( $backup, $backup_key, $site_id, 'Could not open a temporary upload file.' );
		}
		$bytes = stream_copy_to_stream( $input, $output );
		fclose( $input );
		fclose( $output );
		if ( $bytes !== $details['size'] || hash_file( 'sha256', $temporary ) !== $details['hash'] ) {
			return $this->temporary_copy_error( $backup, $backup_key, $site_id, 'A copied file did not verify; the original was left untouched.', 'upload_copy_mismatch' );
		}
		$permissions = fileperms( $source );
		if ( false === $permissions || ! chmod( $temporary, $permissions & 0777 ) ) { // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.chmod_chmod -- Preserve the verified source media permissions before atomic publication.
			return $this->temporary_copy_error( $backup, $backup_key, $site_id, 'Could not preserve upload file permissions.' );
		}
		if ( ! @link( $temporary, $destination ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged,WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_link -- A same-directory hard link publishes atomically without overwriting; an explicit error follows.
			if ( ! is_file( $destination ) || is_link( $destination ) || hash_file( 'sha256', $destination ) !== $details['hash'] ) {
				$message = file_exists( $destination ) || is_link( $destination ) ? 'A target file was occupied during repair.' : 'The target filesystem does not support atomic upload publication.';
				return $this->temporary_copy_error( $backup, $backup_key, $site_id, $message, 'upload_file_changed' );
			}
		}
		$cleaned = $this->discard_temporary_file( $backup, $backup_key, $site_id );
		if ( is_wp_error( $cleaned ) ) {
			return $cleaned;
		}
		return true;
	}

	/**
	 * Remove a journaled temporary copy after a failed copy.
	 *
	 * @param array<string, mixed> $backup Repair journal, passed by reference.
	 * @param string               $backup_key Repair journal option name.
	 * @param int                  $site_id Site ID.
	 * @param string               $message Error message.
	 * @param string               $code Error code.
	 * @return WP_Error Copy error, or a cleanup error when cleanup failed.
	 */
	private function temporary_copy_error( &$backup, $backup_key, $site_id, $message, $code = 'upload_copy_failed' ) {
		$cleaned = $this->discard_temporary_file( $backup, $backup_key, $site_id );
		return is_wp_error( $cleaned ) ? $cleaned : new WP_Error( $code, $message );
	}

	/**
	 * Remove only the temporary file recorded by a validated repair journal.
	 *
	 * @param array<string, mixed> $backup Repair journal, passed by reference.
	 * @param string               $backup_key Repair journal option name.
	 * @param int                  $site_id Site ID.
	 * @return true|WP_Error Whether the journal and filesystem were cleaned.
	 */
	private function discard_temporary_file( &$backup, $backup_key, $site_id ) {
		$temporary     = $backup['temporary_file'];
		$expected_root = WP_CONTENT_DIR . '/uploads/sites/' . $site_id;
		$prefix        = trailingslashit( $expected_root );
		$relative      = 0 === strpos( $temporary, $prefix ) ? substr( $temporary, strlen( $prefix ) ) : '';
		$parts         = explode( '/', $relative );
		if ( $backup['target_basedir'] !== $expected_root || '' === $relative || in_array( '.', $parts, true ) || in_array( '..', $parts, true ) || 0 !== strpos( basename( $temporary ), '.wpmn-upload-repair-' ) || $this->has_symlink_component( dirname( $temporary ), WP_CONTENT_DIR . '/uploads' ) || is_link( $temporary ) || ( file_exists( $temporary ) && ! is_file( $temporary ) ) ) {
			return new WP_Error( 'upload_temporary_invalid', 'The repair journal has an unsafe temporary file.' );
		}
		if ( file_exists( $temporary ) && ! unlink( $temporary ) ) { // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_unlink -- The validated journal identifies this exact temporary file.
			return new WP_Error( 'upload_temporary_failed', 'Could not remove an interrupted temporary upload copy.' );
		}
		unset( $backup['temporary_file'] );
		if ( ! update_option( $backup_key, $backup, false ) ) {
			return new WP_Error( 'upload_journal_failed', 'Could not clear the temporary upload journal.' );
		}
		return true;
	}

	/**
	 * Check the existing destination or the parent that would create it.
	 *
	 * @param string $target Planned upload directory.
	 * @return bool Whether the current CLI user can write there.
	 */
	private function target_is_writable( $target ) {
		$path = $target;
		while ( ! file_exists( $path ) ) {
			$parent = dirname( $path );
			if ( $parent === $path ) {
				return false;
			}
			$path = $parent;
		}
		return is_dir( $path ) && is_writable( $path ); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_is_writable -- Planned local upload directory.
	}

	/**
	 * Create a compact result for unsupported or unchanged sites.
	 *
	 * @param int    $site_id Site ID.
	 * @param int    $network_id Network ID.
	 * @param string $status Status.
	 * @param string $reason Explanation.
	 * @return array<string, mixed> Result.
	 */
	private function result( $site_id, $network_id, $status, $reason ) {
		return array(
			'site_id'     => (int) $site_id,
			'network_id'  => (int) $network_id,
			'status'      => $status,
			'reason'      => $reason,
			'file_count'  => 0,
			'collisions'  => array(),
			'references'  => array(),
			'fingerprint' => '',
			'files'       => array(),
		);
	}

	/**
	 * Set a refusal reason without discarding gathered evidence.
	 *
	 * @param array<string, mixed> $plan Plan.
	 * @param string               $status Status.
	 * @param string               $reason Reason.
	 * @return array<string, mixed> Plan.
	 */
	private function with_status( $plan, $status, $reason ) {
		$plan['status'] = $status;
		$plan['reason'] = $reason;
		return $plan;
	}

	/**
	 * Match Core's raw WP_CONTENT_URL behavior in _wp_upload_dir().
	 *
	 * The content_url() function changes the scheme for the current request, while Core's
	 * upload calculation concatenates the constant without scheme changes.
	 *
	 * @return string Default upload URL before a multisite suffix.
	 */
	private function default_upload_url() {
		$content_url = defined( 'WP_CONTENT_URL' ) ? constant( 'WP_CONTENT_URL' ) : content_url();
		return is_string( $content_url ) ? $content_url . '/uploads' : content_url( 'uploads' );
	}
}
