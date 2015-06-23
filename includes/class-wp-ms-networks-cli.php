<?php

/**
 * Network Management Command
 */
class WPMN_Command extends WP_CLI_Command {

	/**
	 * @var array Default fields to display for each object.
	 */
	protected $obj_fields = array(
		"id",
		"domain",
		"path"
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
	 * [--site_name=<site_name>]
	 * : Name of new network
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

		$defaults   = array( 'site_name' => false, 'clone_network' => false, 'options_to_clone' => false );
		$assoc_args = wp_parse_args( $assoc_args, $defaults );

		$clone_network = $assoc_args['clone_network'];
		$options_to_clone = false;

		if ( ! empty( $clone_network ) && !network_exists( $clone_network ) ) {
			WP_CLI::error( sprintf( __( "Clone network %s doesn't exist.", 'wp-multi-network' ), $clone_network ) );

			if ( ! empty( $assoc_args['options_to_clone'] ) ) {
				$options_to_clone = explode( ",", $assoc_args['options_to_clone'] );
			}
		}

		$network_id = add_network( $domain, $path, $assoc_args['site_name'], $clone_network, $options_to_clone );

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
	 * Get Formatter object based on supplied parameters.
	 *
	 * @param array $assoc_args Parameters passed to command. Determines formatting.
	 *
	 * @return WP_CLI\Formatter
	 */
	protected function get_formatter( &$assoc_args ) {
		return new WP_CLI\Formatter( $assoc_args, $this->obj_fields, 'wp-multi-network' );
	}

}

WP_CLI::add_command( 'wp-multi-network', 'WPMN_Command' );
