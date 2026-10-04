<?php
/**
 * WP_MS_Network_Command class
 *
 * @package WPMN
 * @since 1.3.0
 */

/**
 * Class for managing networks with WP-CLI.
 *
 * @since 1.3.0
 */
class WP_MS_Network_Command {

	/**
	 * Default fields to display for each object.
	 *
	 * @since 1.3.0
	 * @var string[]
	 */
	protected $obj_fields = array(
		'id',
		'domain',
		'path',
	);

	/**
	 * Add a network.
	 *
	 * <domain>
	 * : Domain for network
	 *
	 * <path>
	 * : Path for network
	 *
	 * --user=<id|login|email>
	 * : Set the WordPress user, this will be the administrator for the site and administrator for the network if network_admin is not provided.
	 *
	 * [--network_admin=<id|login|email>]
	 * : This will be the administrator for the network.
	 *
	 * [--site_name=<site_name>]
	 * : Name of the new network site
	 *
	 * [--network_name=<network_name>]
	 * : Name of the new network
	 *
	 * [--clone_network=<clone_network>]
	 * : ID of network to clone
	 *
	 * [--options_to_clone=<options_to_clone>]
	 * : Comma-separated network options to clone (requires --clone_network).
	 *
	 * @since 1.3.0
	 *
	 * @param string[]             $args Positional CLI arguments.
	 * @param array<string, mixed> $assoc_args Associative CLI arguments.
	 * @return void
	 */
	public function create( $args, $assoc_args ) {
		[ $domain, $path ] = $args;

		$assoc_args = wp_parse_args(
			$assoc_args, array(
				'network_admin'    => false,
				'site_name'        => false,
				'network_name'     => false,
				'clone_network'    => false,
				'options_to_clone' => false,
			)
		);

		if ( $assoc_args['network_admin'] ) {
			$users = new \WP_CLI\Fetchers\User();
			$user  = $users->get( $assoc_args['network_admin'] );
			if ( ! $user ) {
				WP_CLI::error( 'Super user does not exist.' );
			}
			$network_admin_id = $user->ID;
		} else {
			$network_admin_id = get_current_user_id();
		}

		$clone_network    = $assoc_args['clone_network'];
		$options_to_clone = false;
		if ( ! empty( $assoc_args['options_to_clone'] ) ) {
			$options_to_clone = array_map( 'trim', explode( ',', (string) $assoc_args['options_to_clone'] ) );
			$options_to_clone = array_values( array_diff( $options_to_clone, array( '' ) ) );
		}
		if ( false !== $options_to_clone && empty( $clone_network ) ) {
			WP_CLI::error( 'The --options_to_clone option requires --clone_network.' );
		}

		if ( ! empty( $clone_network ) && is_numeric( $clone_network ) && ! get_network( (int) $clone_network ) ) {
			WP_CLI::error( sprintf( "Clone network %s doesn't exist.", $clone_network ) );
		}

		$network_id = add_network(
			array(
				'domain'           => $domain,
				'path'             => $path,
				'site_name'        => $assoc_args['site_name'],
				'network_name'     => $assoc_args['network_name'],
				'user_id'          => get_current_user_id(),
				'network_admin_id' => $network_admin_id,
				'clone_network'    => $clone_network,
				'options_to_clone' => $options_to_clone,
			)
		);

		if ( is_wp_error( $network_id ) ) {
			WP_CLI::error( $network_id );

			return;
		}

		WP_CLI::success( sprintf( 'Created network %d.', $network_id ) );
	}

	/**
	 * Update a network.
	 *
	 * <id>
	 * : ID for network
	 *
	 * <domain>
	 * : Domain for network
	 *
	 * [--path=<path>]
	 * : Path for network
	 *
	 * @since 1.3.0
	 *
	 * @param string[]             $args Positional CLI arguments.
	 * @param array<string, mixed> $assoc_args Associative CLI arguments.
	 * @return void
	 */
	public function update( $args, $assoc_args ) {
		[ $id, $domain ] = $args;

		$defaults   = array(
			'path' => '',
		);
		$assoc_args = wp_parse_args( $assoc_args, $defaults );

		$network_id = update_network( (int) $id, $domain, $assoc_args['path'] );

		if ( is_wp_error( $network_id ) ) {
			WP_CLI::error( $network_id );
		}

		WP_CLI::success( sprintf( 'Updated network %d.', $id ) );
	}

	/**
	 * Delete a network.
	 *
	 * <id>
	 * : ID for network
	 *
	 * [--delete_blogs=<delete_blogs>]
	 * : Delete blogs in this network
	 *
	 * @since 1.3.0
	 *
	 * @param string[]             $args Positional CLI arguments.
	 * @param array<string, mixed> $assoc_args Associative CLI arguments.
	 * @return void
	 */
	public function delete( $args, $assoc_args ) {
		[ $id ] = $args;

		$assoc_args = wp_parse_args(
			$assoc_args, array(
				'delete_blogs' => false,
			)
		);

		$network_id = delete_network( (int) $id, $assoc_args['delete_blogs'] );

		if ( is_wp_error( $network_id ) ) {
			WP_CLI::error( $network_id );
		}

		WP_CLI::success( sprintf( 'Deleted network %d.', $id ) );
	}

	/**
	 * Move a site to another network.
	 *
	 * <site_id>
	 * : Site id to move
	 *
	 * <new_network_id>
	 * : New network id
	 *
	 * @subcommand move-site
	 *
	 * @since 1.3.0
	 *
	 * @param string[]             $args Positional CLI arguments.
	 * @param array<string, mixed> $assoc_args Associative CLI arguments.
	 * @return void
	 */
	public function move_site( $args, $assoc_args ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		[ $site_id, $new_network_id ] = $args;

		$network_id = move_site( (int) $site_id, (int) $new_network_id );

		if ( is_wp_error( $network_id ) ) {
			WP_CLI::error( $network_id );
		}

		WP_CLI::success( sprintf( 'Site %1$d has moved to network %2$d.', $site_id, $new_network_id ) );
	}

	/**
	 * Plan or execute repairs for known doubled modern upload paths.
	 *
	 * With no flags, this command inspects every site without writing anything.
	 * Save `--format=json` output to a file before using `--execute`.
	 * Execution requires a super administrator and never deletes old files.
	 *
	 * [--site-id=<id>]
	 * : Inspect only one site.
	 *
	 * [--network-id=<id>]
	 * : Inspect only sites in one network.
	 *
	 * [--batch-size=<number>]
	 * : Number of sites to query at once. Default 100, maximum 500.
	 *
	 * [--format=<table|json>]
	 * : Dry-run output format. Default table. JSON is required for execution.
	 *
	 * [--execute]
	 * : Apply a previously saved JSON plan after rechecking every site.
	 *
	 * [--plan-file=<path>]
	 * : Saved JSON dry-run output required with --execute.
	 *
	 * [--all]
	 * : Confirm execution of a plan containing more than one site.
	 *
	 * @subcommand repair-uploads
	 *
	 * @param string[]             $args Positional CLI arguments.
	 * @param array<string, mixed> $assoc_args Associative CLI arguments.
	 * @return void
	 */
	public function repair_uploads( $args, $assoc_args ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		global $wpdb;
		// Core defines WP_CONTENT_URL during bootstrap. With --url set to a
		// subsite, that constant can include the subsite path even after blog
		// switching, making otherwise identical repair plans disagree.
		if ( (int) get_current_blog_id() !== (int) get_main_site_id( get_current_network_id() ) ) {
			WP_CLI::error( 'Run repair-uploads from a network main site using --url=<main-site-url>.' );
		}
		require_once __DIR__ . '/class-wp-ms-upload-repair.php';
		$repair = new WP_MS_Upload_Repair();
		if ( isset( $assoc_args['execute'] ) ) {
			$this->execute_upload_repairs( $repair, $assoc_args );
			return;
		}

		$format     = isset( $assoc_args['format'] ) ? $assoc_args['format'] : 'table';
		$network_id = isset( $assoc_args['network-id'] ) ? absint( $assoc_args['network-id'] ) : 0;
		$site_id    = isset( $assoc_args['site-id'] ) ? absint( $assoc_args['site-id'] ) : 0;
		$batch_size = isset( $assoc_args['batch-size'] ) ? absint( $assoc_args['batch-size'] ) : 100;
		if ( ! in_array( $format, array( 'table', 'json' ), true ) || ! $batch_size || $batch_size > 500 ) {
			WP_CLI::error( 'Use --format=table|json and a batch size from 1 to 500.' );
		}
		if ( isset( $assoc_args['network-id'] ) && ( ! $network_id || ! get_network( $network_id ) ) ) {
			WP_CLI::error( 'The requested network does not exist.' );
		}
		if ( isset( $assoc_args['site-id'] ) && ( ! $site_id || ! get_site( $site_id ) ) ) {
			WP_CLI::error( 'The requested site does not exist.' );
		}

		if ( 'json' === $format ) {
			WP_CLI::line( '{"schema":1,"sites":[' );
		} else {
			WP_CLI::line( "site_id\tnetwork_id\tstatus\tfiles\treferences\treason" );
		}
		$first   = true;
		$cursor  = 0;
		$last_id = $wpdb->get_var( "SELECT MAX(blog_id) FROM {$wpdb->blogs}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Snapshot the highest site ID before a potentially long dry run.
		if ( $wpdb->last_error ) {
			WP_CLI::error( 'Could not establish the upload repair dry-run site boundary.' );
		}
		$last_id = (int) $last_id;
		do {
			$sql    = "SELECT blog_id FROM {$wpdb->blogs} WHERE blog_id > %d AND blog_id <= %d";
			$params = array( $cursor, $last_id );
			if ( $network_id ) {
				$sql     .= ' AND site_id = %d';
				$params[] = $network_id;
			}
			if ( $site_id ) {
				$sql     .= ' AND blog_id = %d';
				$params[] = $site_id;
			}
			$sql     .= ' ORDER BY blog_id ASC LIMIT %d';
			$params[] = $batch_size;
			$site_ids = $wpdb->get_col( $wpdb->prepare( $sql, $params ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- Keyset pagination keeps a long dry run from skipping sites after deletion; every variable is prepared.
			if ( $wpdb->last_error ) {
				WP_CLI::error( 'Could not list sites for the upload repair dry run.' );
			}
			foreach ( $site_ids as $listed_id ) {
				$cursor = (int) $listed_id;
				$plan   = $repair->public_plan( $repair->inspect( $cursor ) );
				if ( 'json' === $format ) {
					$encoded = wp_json_encode( $plan );
					if ( false === $encoded ) {
						WP_CLI::error( 'Could not encode a site repair plan.' );
					}
					WP_CLI::line( ( $first ? '' : ',' ) . $encoded );
				} else {
					$references = array_sum( $plan['references'] );
					WP_CLI::line( implode( "\t", array( $plan['site_id'], $plan['network_id'], $plan['status'], $plan['file_count'], $references, $plan['reason'] ) ) );
				}
				$first = false;
			}
			$site_count = count( $site_ids );
		} while ( $site_count === $batch_size && ! $site_id );
		if ( 'json' === $format ) {
			WP_CLI::line( ']}' );
		}
	}

	/**
	 * Apply a saved dry-run file, leaving refused sites unchanged.
	 *
	 * @param WP_MS_Upload_Repair  $repair Repair planner.
	 * @param array<string, mixed> $assoc_args Command flags.
	 * @return void
	 */
	private function execute_upload_repairs( $repair, $assoc_args ) {
		if ( ! is_super_admin() ) {
			WP_CLI::error( 'Run this command as a super administrator with --user.' );
		}
		if ( isset( $assoc_args['site-id'] ) || isset( $assoc_args['network-id'] ) ) {
			WP_CLI::error( 'Execution scope comes from the saved plan. Filter sites when creating the dry run.' );
		}
		if ( empty( $assoc_args['plan-file'] ) || ! is_file( $assoc_args['plan-file'] ) || is_link( $assoc_args['plan-file'] ) || ! is_readable( $assoc_args['plan-file'] ) ) {
			WP_CLI::error( 'Execution requires a readable --plan-file from --format=json.' );
		}
		$plan_size = filesize( $assoc_args['plan-file'] );
		if ( false === $plan_size || $plan_size > 100 * MB_IN_BYTES ) {
			WP_CLI::error( 'The saved plan is too large to load safely.' );
		}
		$plan_json = file_get_contents( $assoc_args['plan-file'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents,WordPressVIPMinimum.Performance.FetchingRemoteData.FileGetContentsUnknown -- Local operator-supplied plan.
		if ( false === $plan_json ) {
			WP_CLI::error( 'Could not read the saved plan.' );
			return;
		}
		$document = json_decode( $plan_json, true );
		if ( ! is_array( $document ) || ! isset( $document['schema'], $document['sites'] ) || 1 !== $document['schema'] || ! is_array( $document['sites'] ) ) {
			WP_CLI::error( 'The saved plan has an invalid schema.' );
		}
		if ( count( $document['sites'] ) > 1 && ! isset( $assoc_args['all'] ) ) {
			WP_CLI::error( 'A multi-site plan requires --all for execution.' );
		}
		// Validate every entry before the first site can be changed.
		$seen = array();
		foreach ( $document['sites'] as $plan ) {
			if ( ! is_array( $plan ) || ! isset( $plan['site_id'], $plan['network_id'], $plan['status'], $plan['fingerprint'] ) || ! is_int( $plan['site_id'] ) || $plan['site_id'] < 1 || ! is_int( $plan['network_id'] ) || $plan['network_id'] < 0 || ! is_string( $plan['status'] ) || ! is_string( $plan['fingerprint'] ) || ! in_array( $plan['status'], array( 'repairable', 'manual', 'unchanged' ), true ) || ( 'repairable' === $plan['status'] && $plan['network_id'] < 1 ) || isset( $seen[ $plan['site_id'] ] ) ) {
				WP_CLI::error( 'The saved plan has invalid or duplicate site entries.' );
			}
			if ( 'repairable' === $plan['status'] && ( ! is_string( $plan['fingerprint'] ) || ! preg_match( '/^[a-f0-9]{64}$/', $plan['fingerprint'] ) ) ) {
				WP_CLI::error( 'The saved plan has an invalid fingerprint.' );
			}
			if ( 'repairable' === $plan['status'] && (int) get_current_network_id() !== $plan['network_id'] ) {
				WP_CLI::error( 'The saved plan includes a repairable site from another network. Run from that network main site.' );
			}
			$seen[ $plan['site_id'] ] = true;
		}
		$failures = 0;
		foreach ( $document['sites'] as $plan ) {
			if ( 'repairable' !== $plan['status'] ) {
				WP_CLI::line( sprintf( 'Site %d: %s; unchanged.', $plan['site_id'], $plan['status'] ) );
				continue;
			}
			$result = $repair->execute( $plan );
			if ( is_wp_error( $result ) ) {
				++$failures;
				WP_CLI::warning( sprintf( 'Site %d: %s', $plan['site_id'], $result->get_error_message() ) );
				continue;
			}
			WP_CLI::line( sprintf( 'Site %d: %s; backup option %s; old files retained.', $result['site_id'], $result['status'], $result['backup_option'] ) );
		}
		if ( $failures ) {
			WP_CLI::halt( 1 );
		}
	}

	/**
	 * List all networks.
	 *
	 * [--fields=<fields>]
	 * : Limit the output to specific row fields.
	 *
	 * [--format=<format>]
	 * : Accepted values: table, csv, json, count. Default: table
	 *
	 * ## AVAILABLE FIELDS
	 *
	 * These fields will be displayed by default for each term:
	 *
	 * * id
	 * * domain
	 * * path
	 *
	 * @subcommand list
	 *
	 * @since 1.3.0
	 *
	 * @param string[]             $args Positional CLI arguments.
	 * @param array<string, mixed> $assoc_args Associative CLI arguments.
	 * @return void
	 */
	public function list_( $args, $assoc_args ) {
		/** @var WP_Network[] $items */ // phpcs:ignore Generic.Commenting.DocComment.MissingShort
		$items = get_networks();

		$formatter = $this->get_formatter( $assoc_args );
		$formatter->display_items( $items );
	}

	/**
	 * Network activate or deactivate a plugin.
	 *
	 * <activate|deactivate>
	 * : Action to perform
	 *
	 * <plugin_name>
	 * : Plugin to activate for the network
	 *
	 * --network_id=<network_id>
	 * : Id of the network to activate on
	 *
	 * [--network]
	 * : If set, the plugin will be activated for the entire multisite network.
	 *
	 * [--all]
	 * : If set, all plugins will be activated.
	 *
	 * @since 1.3.0
	 *
	 * @param string[]             $args Positional CLI arguments.
	 * @param array<string, mixed> $assoc_args Associative CLI arguments.
	 * @return void
	 */
	public function plugin( $args, $assoc_args ) {
		$fetchers_plugin = new \WP_CLI\Fetchers\Plugin();
		$action          = array_shift( $args );

		if ( ! in_array( $action, array( 'activate', 'deactivate' ), true ) ) {
			WP_CLI::error( sprintf( '%s is not a supported action.', $action ) );
		}

		$network_wide = \WP_CLI\Utils\get_flag_value( $assoc_args, 'network' );
		$all          = \WP_CLI\Utils\get_flag_value( $assoc_args, 'all', false );

		$needing_activation = count( $args );
		$assoc_args         = wp_parse_args(
			$assoc_args, array(
				'network_id' => false,
			)
		);
		$network_id         = $assoc_args['network_id'];
		if ( get_network( $network_id ) ) {
			switch_to_network( $network_id );
			if ( $all ) {
				$args = array_map(
					function ( $file ) {
						return \WP_CLI\Utils\get_plugin_name( $file );
					}, array_keys( get_plugins() )
				);
			}
			foreach ( $fetchers_plugin->get_many( $args ) as $plugin ) {
				$status = $this->get_status( $plugin->file );
				if ( $all && in_array( $status, array( 'active', 'active-network' ), true ) ) {
					--$needing_activation;
					continue;
				}

				// Network-active is the highest level of activation status.
				if ( 'active-network' === $status ) {
					WP_CLI::warning( "Plugin '{$plugin->name}' is already network active." );
					continue;
				}

				// Don't reactivate active plugins, but do let them become network-active.
				if ( ! $network_wide && 'active' === $status ) {
					WP_CLI::warning( "Plugin '{$plugin->name}' is already active." );
					continue;
				}

				// Plugins need to be deactivated before being network activated.
				if ( $network_wide && 'active' === $status ) {
					deactivate_plugins( $plugin->file, false, false );
				}
				if ( 'activate' === $action ) {
					activate_plugins( $plugin->file, '', $network_wide );
				} else {
					deactivate_plugins( $plugin->file, false, $network_wide );
				}

				$this->active_output( $plugin->name, $plugin->file, $network_wide, 'activate' );
			}
			restore_current_network();
		} else {
			WP_CLI::error( sprintf( "Network %s doesn't exist.", $network_id ) );
		}
	}

	/**
	 * Gets the formatter object based on supplied parameters.
	 *
	 * @since 1.3.0
	 *
	 * @param array<string, mixed> $assoc_args Associative CLI arguments. Passed by reference.
	 * @return WP_CLI\Formatter WP-CLI formatter instance.
	 */
	protected function get_formatter( &$assoc_args ) {
		return new WP_CLI\Formatter( $assoc_args, $this->obj_fields, 'wp-multi-network' );
	}

	/**
	 * Checks whether a given plugin is active for the given context.
	 *
	 * @since 1.3.0
	 *
	 * @param string $file         Plugin main file path relative to the plugins directory.
	 * @param bool   $network_wide Whether to check network-wide or not.
	 * @return bool True if the plugin is active for the given context, false otherwise.
	 */
	private function check_active( $file, $network_wide ) {
		$required = $network_wide ? 'active-network' : 'active';

		return $required === $this->get_status( $file );
	}

	/**
	 * Gets the activation status for a given plugin.
	 *
	 * @since 1.3.0
	 *
	 * @param string $file Plugin main file path relative to the plugins directory.
	 * @return string Plugin activation status. Either 'active', 'active-network', or 'inactive'.
	 */
	protected function get_status( $file ) {
		if ( is_plugin_active_for_network( $file ) ) {
			return 'active-network';
		}

		if ( is_plugin_active( $file ) ) {
			return 'active';
		}

		return 'inactive';
	}

	/**
	 * Outputs the result of a plugin activation operation.
	 *
	 * @since 1.3.0
	 *
	 * @param string $name         Plugin name.
	 * @param string $file         Plugin main file path relative to the plugins directory.
	 * @param bool   $network_wide Whether to check network-wide or not.
	 * @param string $action       Action performed.
	 * @return void
	 */
	private function active_output( $name, $file, $network_wide, $action ) {
		$network_wide = $network_wide || ( is_multisite() && is_network_only_plugin( $file ) );

		$check = $this->check_active( $file, $network_wide );

		if ( ( 'activate' === $action ) ? $check : ! $check ) {
			if ( $network_wide ) {
				WP_CLI::success( "Plugin '{$name}' network {$action}d." );
			} else {
				WP_CLI::success( "Plugin '{$name}' {$action}d." );
			}
		} else {
			WP_CLI::warning( "Could not {$action} the '{$name}' plugin." );
		}
	}
}

WP_CLI::add_command( 'wp-multi-network', 'WP_MS_Network_Command' );
