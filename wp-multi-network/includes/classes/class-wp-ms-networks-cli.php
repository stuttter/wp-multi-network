<?php

/**
 * Network Management Command
 */
class WP_MS_Network_Command extends WP_CLI_Command {

	/**
	 * @var array Default fields to display for each object.
	 */
	protected $obj_fields = array(
		'id',
		'domain',
		'path'
	);

	/**
	 * Add a Network
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
	 * : Options to clone to new network
	 *
	 */
	public function create( $args, $assoc_args ) {

		list( $domain, $path ) = $args;

		$assoc_args = wp_parse_args( $assoc_args, array(
			'network_admin'    => false,
			'site_name'        => false,
			'network_name'     => false,
			'clone_network'    => false,
			'options_to_clone' => false
		) );

		if ( $assoc_args['network_admin'] ) {
			$users = new \WP_CLI\Fetchers\User();
			$user = $users->get( $assoc_args['network_admin'] );
			if ( ! $user ) {
				return new WP_Error( 'network_super_admin', __( 'Super user does not exist.', 'wp-multi-network' ) );
			}
			$network_admin_id = $user->ID;
		} else {
			$network_admin_id = get_current_user_id();
		}

		$clone_network    = $assoc_args['clone_network'];
		$options_to_clone = false;

		if ( ! empty( $clone_network ) && ! get_network( $clone_network ) ) {
			WP_CLI::error( sprintf( __( "Clone network %s doesn't exist.", 'wp-multi-network' ), $clone_network ) );

			if ( ! empty( $assoc_args['options_to_clone'] ) ) {
				$options_to_clone = explode( ",", $assoc_args['options_to_clone'] );
			}
		}

		// Add the network
		$network_id = add_network( array(
			'domain'           => $domain,
			'path'             => $path,
			'site_name'        => $assoc_args['site_name'],
			'network_name'     => $assoc_args['network_name'],
			'user_id'          => get_current_user_id(),
			'network_admin_id'    => $network_admin_id,
			'clone_network'    => $clone_network,
			'options_to_clone' => $options_to_clone
		) );

		if ( is_wp_error( $network_id ) ) {
			WP_CLI::error( $network_id );
		}

		WP_CLI::success( sprintf( __( 'Created network %d.', 'wp-multi-network' ), $network_id ) );
	}

	/**
	 * Update a Network
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
	 */
	public function update( $args, $assoc_args ) {

		list( $id, $domain ) = $args;

		$defaults   = array( 'path' => '' );
		$assoc_args = wp_parse_args( $assoc_args, $defaults );

		$network_id = update_network( $id, $domain, $assoc_args['path'] );

		if ( is_wp_error( $network_id ) ) {
			WP_CLI::error( $network_id );
		}

		WP_CLI::success( sprintf( __( 'Updated network %d.', 'wp-multi-network' ), $id ) );

	}

	/**
	 * Delete a Network
	 *
	 * <id>
	 * : ID for network
	 *
	 * [--delete_blogs=<delete_blogs>]
	 * : Delete blogs in this network
	 *
	 */
	public function delete( $args, $assoc_args ) {

		list( $id ) = $args;

		$defaults   = array( 'delete_blogs' => false );
		$assoc_args = wp_parse_args( $assoc_args, $defaults );

		$network_id = delete_network( $id, $assoc_args['delete_blogs'] );

		if ( is_wp_error( $network_id ) ) {
			WP_CLI::error( $network_id );
		}

		WP_CLI::success( sprintf( __( 'Deleted network %d.', 'wp-multi-network' ), $id ) );
	}

	/**
	 * Move to blog to another network
	 *
	 * <site_id>
	 * : Site id to move
	 *
	 * <new_network_id>
	 * : New network id
	 *
	 * @subcommand move-site
	 */
	public function move_site( $args, $assoc_args ) {

		list( $site_id, $new_network_id ) = $args;

		$network_id = move_site( $site_id, $new_network_id );

		if ( is_wp_error( $network_id ) ) {
			WP_CLI::error( $network_id );
		}

		WP_CLI::success( sprintf( __( 'Blog %d has moved to network %d.', 'wp-multi-network' ), $site_id, $new_network_id ) );
	}

	/**
	 * List all networks.
	 *
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
	 */
	public function list_( $args, $assoc_args ) {
		$items     = get_networks();
		$formatter = $this->get_formatter( $assoc_args );
		$formatter->display_items( $items );
	}
	
    /**
     * Network activate or deactivate a plugin
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
     * @param array $args
     * @param array $assoc_args
     */
    public function plugin( $args, $assoc_args ) {
        $this->fetcher = new \WP_CLI\Fetchers\Plugin;
        $action        = array_shift( $args );
        if ( ! in_array( $action, array( 'activate', 'deactivate' ), true ) ) {
            WP_CLI::error( sprintf( __( '%s is not a supported action.', 'wp-multi-network' ), $action ) );
        }
        $network_wide = \WP_CLI\Utils\get_flag_value( $assoc_args, 'network' );
        $all          = \WP_CLI\Utils\get_flag_value( $assoc_args, 'all', false );


        $needing_activation = count( $args );
        $assoc_args         = wp_parse_args( $assoc_args, array(
            'network_id' => false,
        ) );
        $network_id         = $assoc_args['network_id'];
        if ( get_network( $network_id ) ) {
            switch_to_network( $network_id );
            if ( $all ) {
                $args = array_map( function ( $file ) {
                    return \WP_CLI\Utils\get_plugin_name( $file );
                }, array_keys( get_plugins() ) );
            }
            foreach ( $this->fetcher->get_many( $args ) as $plugin ) {
                $status = $this->get_status( $plugin->file );
                if ( $all && in_array( $status, array( 'active', 'active-network' ), true ) ) {
                    $needing_activation --;
                    continue;
                }
                // Network-active is the highest level of activation status
                if ( 'active-network' === $status ) {
                    WP_CLI::warning( "Plugin '{$plugin->name}' is already network active." );
                    continue;
                }
                // Don't reactivate active plugins, but do let them become network-active
                if ( ! $network_wide && 'active' === $status ) {
                    WP_CLI::warning( "Plugin '{$plugin->name}' is already active." );
                    continue;
                }

                // Plugins need to be deactivated before being network activated
                if ( $network_wide && 'active' === $status ) {
                    deactivate_plugins( $plugin->file, false, false );
                }
                if ( 'activate' === $action ) {
                    activate_plugins( $plugin->file, '', $network_wide );
                } else {
                    deactivate_plugins( $plugin->file, '', $network_wide );
                }

                $this->active_output( $plugin->name, $plugin->file, $network_wide, "activate" );
            }
            restore_current_network();
        } else {
            WP_CLI::error( sprintf( __( "Network %s doesn't exist.", 'wp-multi-network' ), $network_id ) );
        }
    }

	/**
	 * Get Formatter object based on supplied parameters.
	 *
	 * @param array $assoc_args Parameters passed to command. Determines formatting.
	 *
	 * @return WP_CLI\Formatter
	 */
	protected function get_formatter( &$assoc_args ) {
		return new WP_CLI\Formatter( $assoc_args, $this->obj_fields, 'wp-multi-network' );
	}

    /* PRIVATES */

    private function check_active( $file, $network_wide ) {
        $required = $network_wide ? 'active-network' : 'active';

        return $required === $this->get_status( $file );
    }

    protected function get_status( $file ) {
        if ( is_plugin_active_for_network( $file ) ) {
            return 'active-network';
        }

        if ( is_plugin_active( $file ) ) {
            return 'active';
        }

        return 'inactive';
    }

    private function active_output( $name, $file, $network_wide, $action ) {
        $network_wide = $network_wide || ( is_multisite() && is_network_only_plugin( $file ) );

        $check = $this->check_active( $file, $network_wide );

        if ( ( $action === 'activate') ? $check : ! $check ) {
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
