<?php

/**
 * WP Multi Network Functions
 *
 * @package Plugins/Network/Functions
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'wp_should_rescue_orphaned_sites' ) ) :
/**
 * Should sites from deleted networks be assigned to network 0?
 *
 * @since 2.0.0
 *
 * @return bool
 */
function wp_should_rescue_orphaned_sites() {
	$should = defined( 'RESCUE_ORPHANED_BLOGS' ) && ( true === RESCUE_ORPHANED_BLOGS );
	return (bool) apply_filters( 'wp_should_rescue_orphaned_sites', $should );
}
endif;

if ( ! function_exists( 'network_exists' ) ) :
/**
 * Check to see if a network exists.
 *
 * @since 1.3
 *
 * @param integer $network_id ID of network to verify
 */
function network_exists( $network_id ) {
	return ( null !== get_network( $network_id ) );
}
endif;

if ( ! function_exists( 'user_has_networks' ) ) :
/**
 * Return array of networks for which user is an admin, or FALSE if none
 *
 * @since 1.3
 * @return array | FALSE
 */
function user_has_networks( $user_id = 0 ) {
	global $wpdb;

	// Use current user
	if ( empty( $user_id ) ) {
		$user_info  = wp_get_current_user();
		$user_id    = $user_info->ID;
		$user_login = $user_info->user_login;

	// Use passed user ID
	} else {
		$user_id    = (int) $user_id;
		$user_info  = get_userdata( $user_id );
		$user_login = $user_info->user_login;
	}

	// This filter can be used to short-circuit the process of retrieving network IDs of a user
	$my_networks = apply_filters( 'networks_pre_user_is_network_admin', null, $user_id );
	if ( null !== $my_networks ) {
		if ( empty( $my_networks ) ) {
			$my_networks = false;
		}
		return apply_filters( 'networks_user_is_network_admin', $my_networks, $user_id );
	}

	// Setup the networks array
	$my_networks = array();

	// If multisite, get some site meta
	if ( is_multisite() ) {

		// Get the network admins
		// @todo Use get_networks()
		$sql         = "SELECT site_id FROM {$wpdb->sitemeta} WHERE meta_key = %s AND meta_value LIKE %s";
		$query       = $wpdb->prepare( $sql, 'site_admins', '%"' . $user_login . '"%' );
		$my_networks = array_map( 'intval', $wpdb->get_col( $query ) );
	}

	// If there are no networks, return false
	if ( empty( $my_networks ) ) {
		$my_networks = false;
	}

	return apply_filters( 'networks_user_is_network_admin', $my_networks, $user_id );
}
endif;

if ( ! function_exists( 'get_main_site_for_network' ) ) :
/**
 * Get main site for a network
 *
 * @param int|WP_Network $network Network ID or object, null for current network
 * @return int Main site ("blog" in old terminology) ID
 */
function get_main_site_for_network( $network = null ) {

	// Get network
	$network = get_network( $network );

	// Network not found
	if ( empty( $network ) ) {
		return false;
	}

	// Use object site ID if a blog is set
	if ( ! empty( $network->blog_id ) ) {
		$primary_id = $network->blog_id;

	// Look for cached value
	} else {

		// Cache key format found in ms_load_current_site_and_network()
		$primary_id = wp_cache_get( "network:{$network->id}:main_site", 'site-options' );

		// Primary site ID not cached, so try to get it from all sites
		if ( false === $primary_id ) {

			// Look for sites
			$sites = get_sites( array(
				'network_id' => $network->id,
				'domain'     => $network->domain,
				'path'       => $network->path,
				'fields'     => 'ids',
				'number'     => 1
			) );

			// Get primary ID
			$primary_id = ! empty( $sites )
				? reset( $sites )
				: 0;

			// Only cache if value is found
			if ( ! empty( $primary_id ) ) {
				wp_cache_add( "network:{$network->id}:main_site", $primary_id, 'site-options' );
			}
		}
	}

	return (int) $primary_id;
}
endif;

if ( ! function_exists( 'is_main_site_for_network' ) ) :
/**
 * Is a site the main site for it's network?
 *
 * @since 1.7.0
 *
 * @param  int  $site_id
 *
 * @return boolean
 */
function is_main_site_for_network( $site_id ) {

	// Get main site for network
	$site = get_site( $site_id );
	$main = get_main_site_for_network( $site->network_id );

	// Bail if no site or network was found
	if ( empty( $main ) ) {
		return false;
	}

	// Compare & return
	return ( (int) $main === (int) $site_id );
}
endif;

if ( ! function_exists( 'get_network_name' ) ) :
/**
 * Get name of the current network
 *
 * @return string
 */
function get_network_name() {
	global $current_site;

	$site_name = get_site_option( 'site_name' );
	if ( ! $site_name ) {
		$site_name = ucfirst( $current_site->domain );
	}

	return $site_name;
}
endif;

if ( ! function_exists( 'switch_to_network' ) ) :
/**
 * Problem: the various *_site_options() functions operate only on the current network
 * Workaround: change the current network
 *
 * @since 1.3
 * @param integer $new_network ID of network to manipulate
 */
function switch_to_network( $new_network = 0, $validate = false ) {
	global $wpdb, $switched_network, $switched_network_stack, $current_site;

	// Default to the current network ID
	if ( empty( $new_network ) ) {
		$new_network = $current_site->id;
	}

	// Bail if network does not exist
	if ( ( true == $validate ) && ! get_network( $new_network ) ) {
		return false;
	}

	// Maybe initialize the network switching stack global
	if ( empty( $switched_network_stack ) ) {
		$switched_network_stack = array();
	}

	// Tack the current network at the end of the stack
	array_push( $switched_network_stack, $current_site );

	/**
	 * If we're switching to the same network ID that we're on,
	 * set the right vars, do the associated actions, but skip
	 * the extra unnecessary work.
	 */
	if ( $current_site->id === $new_network ) {
		do_action( 'switch_network', $current_site->id, $current_site->id );
		$switched_network = true;
		return true;
	}

	// Switch the current site over
	$prev_site_id = $current_site->id;
	$current_site = get_network( $new_network );

	// Maybe populate network's main site.
	if ( ! isset( $current_site->blog_id ) ) {
		$current_site->blog_id = get_main_site_for_network( $current_site );
	}

	// Maybe populate network's name
	if ( ! isset( $current_site->site_name ) ) {
		$current_site->site_name = get_network_name();
	}

	// Update network globals
	$wpdb->siteid       = $current_site->id;
	$GLOBALS['site_id'] = $current_site->id;
	$GLOBALS['domain']  = $current_site->domain;

	do_action( 'switch_network', $current_site->id, $prev_site_id );

	$switched_network = true;

	return true;
}
endif;

if ( ! function_exists( 'restore_current_network' ) ) :
/**
 * Return to the current network
 *
 * @since 1.3
 */
function restore_current_network() {
	global $wpdb, $switched_network, $switched_network_stack, $current_site;

	// Bail if not switched
	if ( true !== $switched_network ) {
		return false;
	}

	// Bail if no stack
	if ( ! is_array( $switched_network_stack ) ) {
		return false;
	}

	// Get the last network in the stack
	$new_network = array_pop( $switched_network_stack );

	/**
	 * If we're restoring to the same network ID that we're on,
	 * set the right vars, do the associated actions, but skip
	 * the extra unnecessary work.
	 */
	if ( $new_network->id == $current_site->id ) {
		do_action( 'switch_network', $current_site->id, $current_site->id );
		$switched_network = ( ! empty( $switched_network_stack ) );
		return true;
	}

	// Save the previous network ID for action at the end
	$prev_network_id    = $current_site->id;

	// Restore network globals
	$current_site       = $new_network;
	$wpdb->siteid       = $new_network->id;
	$GLOBALS['site_id'] = $new_network->id;
	$GLOBALS['domain']  = $new_network->domain;

	do_action( 'switch_network', $new_network->id, $prev_network_id );

	$switched_network = ! empty( $switched_network_stack );

	return true;
}
endif;

if ( ! function_exists( '_wp_update_network_counts' ) ) :
/**
 * Do not use directly.
 *
 * This will go away once wp_update_network_counts() no longer sucks.
 *
 * @since 2.2.0
 *
 * @param int $network_id
 */
function _wp_update_network_counts( $network_id ) {
	switch_to_network( $network_id );
	wp_update_network_site_counts();
	wp_update_network_user_counts();
	restore_current_network();
}
endif;

if ( ! function_exists( 'insert_network' ) ) :
/**
 * Store basic network info in the sites table.
 *
 * This function creates a row in the wp_site table and returns
 * the new network ID. It is the first step in creating a new network.
 *
 * @since 2.2.0
 *
 * @global wpdb $wpdb WordPress database abstraction object.
 *
 * @param string $domain  The domain of the new network.
 * @param string $path    The path of the new network.

 * @return int|false The ID of the new row
 */
function insert_network( $domain, $path = '/' ) {
	global $wpdb;

	$path   = trailingslashit( $path );
	$result = $wpdb->insert( $wpdb->site, array( 'domain' => $domain, 'path' => $path ) );
	if ( empty( $result ) || is_wp_error( $result ) ) {
		return false;
	}

	$network_id = (int) $wpdb->insert_id;

	clean_network_cache( $network_id );

	return $network_id;
}
endif;

if ( ! function_exists( 'add_network' ) ) :
/**
 * Add a new network
 *
 * @since 1.3
 *
 * @param array $args  {
 *     Array of arguments.
 *     @type string  $domain           Domain name for new network - for VHOST=no,
 *                                     this should be FQDN, otherwise domain only.
 *     @type string  $path             Path to root of network hierarchy - should
 *                                     be '/' unless WP is cohabiting with another
 *                                     product on a domain.
 *     @type string  $site_name        Name of the root blog to be created on
 *                                     the new network.
 *
 *     @type string  $network_name     Name of the new network.
 *
 *     @type integer $user_id          ID of the user to add as the site owner.
 *                                     Defaults to current user ID.
 *
 *     @type integer $network_admin_id    ID of the user to add as the network administrator.
 *                                     Defaults to current user ID.
 *
 *     @type array   $meta             Array of metadata to save to this network.
 *                                     Defaults to array( 'public' => false ).
 *     @type integer $clone_network    ID of network whose networkmeta values are
 *                                     to be copied - default NULL.
 *     @type array   $options_to_clone Override default network meta options to copy
 *                                     when cloning - default NULL.
 * }
 *
 * @return integer|WP_Error ID of newly created network
 */
function add_network( $args = array() ) {
	global $wpdb, $wp_version, $wp_db_version;

	// Backward compatibility with old method of passing arguments
	if ( ! is_array( $args ) || func_num_args() > 1 ) {
		_deprecated_argument( __METHOD__, '1.7.0', sprintf( __( 'Arguments passed to %1$s should be in an associative array. See the inline documentation at %2$s for more details.', 'wp-multi-network' ), __METHOD__, __FILE__ ) );

		// Juggle function parameters
		$func_args     = func_get_args();
		$old_args_keys = array(
			0 => 'domain',
			1 => 'path',
			2 => 'site_name',
			3 => 'clone_network',
			4 => 'options_to_clone'
		);

		// Reset array
		$args = array();

		// Rejig args
		foreach ( $old_args_keys as $arg_num => $arg_key ) {
			if ( isset( $func_args[ $arg_num ] ) ) {
				$args[ $arg_key ] = $func_args[ $arg_num ];
			}
		}
	}

	// Get current user ID to pass into args
	$current_user_id = get_current_user_id();

	// Default site meta
	$default_site_meta = array(
		'public' => get_option( 'blog_public', false )
	);

	// Default network meta
	$default_network_meta = array(
		'wpmu_upgrade_site'  => $wp_db_version,
		'initial_db_version' => $wp_db_version
	);

	// Parse args
	$r = wp_parse_args( $args, array(

		// Site & Network
		'domain'           => '',
		'path'             => '/',

		// Site
		'site_name'        => __( 'New Network Site', 'wp-multi-network' ),
		'user_id'          => $current_user_id,
		'meta'             => $default_site_meta,

		// Network
		'network_name'     => __( 'New Network', 'wp-multi-network' ),
		'network_admin_id' => $current_user_id,
		'network_meta'     => $default_network_meta,
		'clone_network'    => false,
		'options_to_clone' => array_keys( network_options_to_copy() )
	) );

	// Bail if no user with this ID for site
	if ( empty( $r['user_id'] ) || ! get_userdata( $r['user_id'] ) ) {
		return new WP_Error( 'network_user', __( 'User does not exist.', 'wp-multi-network' ) );
	}

	// Bail if no user with this ID for network
	if ( empty( $r['network_admin_id'] ) || ! get_userdata( $r['network_admin_id'] ) ) {
		return new WP_Error( 'network_super_admin', __( 'User does not exist.', 'wp-multi-network' ) );
	}

	// Permissive sanitization for super admin usage
	$r['domain'] = str_replace( ' ', '', strtolower( $r['domain'] ) );
	$r['path']   = str_replace( ' ', '', strtolower( $r['path']   ) );

	// Check for existing network
	$networks = get_networks( array(
		'domain' => $r['domain'],
		'path'   => $r['path'],
		'number' => '1'
	) );

	// Bail if network already exists
	if ( ! empty( $networks ) ) {
		return new WP_Error( 'network_exists', __( 'Network already exists.', 'wp-multi-network' ) );
	}

	// Insert new network
	$new_network_id = insert_network( $r['domain'], $r['path'] );

	// Bail if no network was inserted
	if ( empty( $new_network_id ) ) {
		return false;
	}

	// Set the installing constant
	if ( ! defined( 'WP_INSTALLING' ) ) {
		define( 'WP_INSTALLING', true );
	}

	// Switch to the new network so counts are properly bumped
	switch_to_network( $new_network_id );

	// Ensure upload constants are envoked
	ms_upload_constants();

	// Create the site for the root of this network
	$new_blog_id = wpmu_create_blog(
		$r['domain'],
		$r['path'],
		$r['site_name'],
		$r['user_id'],
		$r['meta'],
		$new_network_id
	);

	// Maybe add user as network admin
	grant_super_admin( $r['network_admin_id'] );

	// Switch back to the current network, to avoid any issues
	restore_current_network();

	// Bail if blog could not be created
	if ( is_wp_error( $new_blog_id ) ) {
		return $new_blog_id;
	}

	// Make sure network has a name
	if ( empty( $r['network_meta']['site_name'] ) ) {
		$r['network_meta']['site_name'] = ! empty( $r['network_name'] )
			? $r['network_name']
			: $r['site_name'];
	}

	// Additional new network meta
	foreach ( $r['network_meta'] as $key => $value ) {
		update_network_option( $new_network_id, $key, $value );
	}

	/**
	 * Fix upload_path for main sites on secondary networks
	 * This applies only to new installs (WP 3.5+)
	 */

	// Switch to network (if set & exists)
	$use_files_rewriting = defined( 'SITE_ID_CURRENT_SITE' ) && get_network( SITE_ID_CURRENT_SITE )
		? get_network_option( SITE_ID_CURRENT_SITE, 'ms_files_rewriting' )
		: get_site_option( 'ms_files_rewriting' );

	// Create the upload_path and upload_url_path values
	if ( empty( $use_files_rewriting ) && version_compare( $wp_version, '3.7', '<' ) ) {

		// WP_CONTENT_URL is locked to the current site and can't be overridden,
		// so we have to replace the hostname the hard way
		$current_siteurl = get_option( 'siteurl' );
		$new_siteurl     = untrailingslashit( get_blogaddress_by_id( $new_blog_id ) );
		$upload_url      = str_replace( $current_siteurl, $new_siteurl, WP_CONTENT_URL );
		$upload_url      = $upload_url . '/uploads';

		$upload_dir = WP_CONTENT_DIR;
		if ( 0 === strpos( $upload_dir, ABSPATH ) ) {
			$upload_dir = substr( $upload_dir, strlen( ABSPATH ) );
		}
		$upload_dir .= '/uploads';

		if ( defined( 'MULTISITE' ) ) {
			$ms_dir = '/sites/' . $new_blog_id;
		} else {
			$ms_dir = '/' . $new_blog_id;
		}

		$upload_dir .= $ms_dir;
		$upload_url .= $ms_dir;

		update_blog_option( $new_blog_id, 'upload_path',     $upload_dir );
		update_blog_option( $new_blog_id, 'upload_url_path', $upload_url );
	}

	/**
	 * Clone network meta from an existing network.
	 *
	 * We currently use the _options() API to get cache integration for free,
	 * but it may be better to read & write directly to $wpdb->sitemeta.
	 */
	if ( ! empty( $r['clone_network'] ) && get_network( $r['clone_network'] ) ) {

		// Temporary array
		$options_cache = array();

		// Old network
		foreach ( $r['options_to_clone'] as $option ) {
			$options_cache[ $option ] = get_network_option( $r['clone_network'], $option );
		}

		// New network
		foreach ( $r['options_to_clone'] as $option ) {

			// Skip if option isn't available to copy
			if ( ! isset( $options_cache[ $option ] ) ) {
				continue;
			}

			// Fix for bug that prevents writing the ms_files_rewriting
			// value for new networks.
			if ( 'ms_files_rewriting' === $option ) {
				$wpdb->insert( $wpdb->sitemeta, array(
					'site_id'    => $new_network_id,
					'meta_key'   => $option,
					'meta_value' => $options_cache[ $option ]
				) );
			} else {
				update_network_option( $new_network_id, $option, $options_cache[ $option ] );
			}
		}
	}

	// Update counts
	_wp_update_network_counts( $new_network_id );

	// Clean network cache
	clean_network_cache( $new_network_id );

	do_action( 'add_network', $new_network_id, $r );

	return $new_network_id;
}
endif;

if ( ! function_exists( 'update_network' ) ) :
/**
 * Modify the domain/path of a network, and update all of its blogs
 *
 * @since 1.3
 *
 * @param integer $id ID of network to modify
 * @param string $domain New domain for network
 * @param string $path New path for network
 */
function update_network( $id, $domain, $path = '' ) {
	global $wpdb;

	// Get network
	$network = get_network( $id );

	// Bail if network not found
	if ( empty( $network ) ) {
		return new WP_Error( 'network_not_exist', __( 'Network does not exist.', 'wp-multi-network' ) );
	}

	// Get main site for this network
	$site_id = get_main_site_for_network( $id );
	$path    = wp_sanitize_site_path( $path );

	// Bail if site URL is invalid
	if ( ! wp_validate_site_url( $domain, $path, $site_id ) ) {
		return new WP_Error( 'blog_bad', sprintf( __( 'The site "%s" is invalid, not available, or already exists.', 'wp-multi-network' ), $domain . $path ) );
	}

	// Set the arrays for updating the db
	$where  = array( 'id' => $network->id );
	$update = array(
		'domain' => $domain,
		'path'   => $path
	);

	// Attempt to update the network
	$update_result = $wpdb->update( $wpdb->site, $update, $where );

	// Bail if update failed
	if ( is_wp_error( $update_result ) ) {
		return new WP_Error( 'network_not_updated', __( 'Network could not be updated.', 'wp-multi-network' ) );
	}

	$path      = ! empty( $path ) ? $path : $network->path;
	$full_path = untrailingslashit( $domain . $path );
	$old_path  = untrailingslashit( $network->domain . $network->path );

	// Also update any associated blogs
	$sites = get_sites( array(
		'network_id' => $network->id
	) );

	// Sites found
	if ( ! empty( $sites ) ) {

		// Loop through sites and update domain/path
		foreach ( $sites as $site ) {

			// Empty update array
			$update = array();

			// Updating domain
			if ( $network->domain !== $domain ) {
				$update['domain'] = str_replace( $network->domain, $domain, $site->domain );
			}

			// Updating path
			if ( $network->path !== $path ) {
				$search         = sprintf( '|^%s|', preg_quote( $network->path, '|' ) );
				$update['path'] = preg_replace( $search, $path, $site->path, 1 );
			}

			// Skip if not updating
			if ( empty( $update ) ) {
				continue;
			}

			// Update blogs table
			$where = array( 'blog_id' => (int) $site->id );
			$wpdb->update( $wpdb->blogs, $update, $where );

			// Fix options table values
			$option_table = $wpdb->get_blog_prefix( $site->id ) . 'options';

			// Loop through options and correct a few of them
			foreach ( network_options_list() as $option_name ) {

				// Query
				$sql   = "SELECT * FROM {$option_table} WHERE option_name = %s";
				$prep  = $wpdb->prepare( $sql, $option_name );
				$value = $wpdb->get_row( $prep );

				// Update if value exists
				if ( ! empty( $value ) && ( false !== strpos( $value->option_value, $old_path ) ) ) {
					$new_value = str_replace( $old_path, $full_path, $value->option_value );
					update_blog_option( $site->id, $option_name, $new_value );
				}
			}

			// Refresh blog details
			refresh_blog_details( $site->id );
		}
	}

	// Update counts
	_wp_update_network_counts( $network->id );

	// Update network cache
	clean_network_cache( $network->id );

	// Network updated
	do_action( 'update_network', $network->id, array(
		'domain' => $network->domain,
		'path'   => $network->path
	) );

	// Network updated
	return true;
}
endif;

if ( ! function_exists( 'delete_network' ) ) :
/**
 * Delete a network and all its blogs
 *
 * @param int  $network_id   ID of network to delete
 * @param bool $delete_blogs Flag to permit blog deletion - default setting
 *                           of false will prevent deletion of occupied networks
 */
function delete_network( $network_id, $delete_blogs = false ) {
	global $wpdb;

	// Get network
	$network = get_network( $network_id );

	// Bail if network does not exist
	if ( empty( $network ) ) {
		return new WP_Error( 'network_not_exist', __( 'Network does not exist.', 'wp-multi-network' ) );
	}

	// Ensure there are no blogs attached to this network
	$sites = get_sites( array(
		'network_id' => $network->id
	) );

	// Network has sites
	if ( ! empty( $sites ) ) {

		// Bail if blog deletion is off
		if ( empty( $delete_blogs ) ) {
			return new WP_Error( 'network_not_empty', __( 'Cannot delete network with sites.', 'wp-multi-network' ) );

		// Are we rescuing orphans or deleting them?
		} elseif ( true === $delete_blogs ) {
			foreach ( $sites as $site ) {
				wp_should_rescue_orphaned_sites()
					? move_site( $site->id, 0 )
					: wpmu_delete_blog( $site->id, true );
			}
		}
	}

	// Delete from sites table
	$sql  = "DELETE FROM {$wpdb->site} WHERE id = %d";
	$prep = $wpdb->prepare( $sql, $network->id );
	$wpdb->query( $prep );

	// Delete from site meta table
	$sql  = "DELETE FROM {$wpdb->sitemeta} WHERE site_id = %d";
	$prep = $wpdb->prepare( $sql, $network->id );
	$wpdb->query( $prep );

	// Clean network cache
	clean_network_cache( $network->id );

	// Network deleted
	do_action( 'delete_network', $network );
}
endif;

if ( ! function_exists( 'move_site' ) ) :
/**
 * Move a site to a new network
 *
 * @since 1.3
 *
 * @param  integer  $site_id         ID of site to move
 * @param  integer  $new_network_id  ID of destination network
 */
function move_site( $site_id = 0, $new_network_id = 0 ) {
	global $wpdb;

	// Get the site
	$site = get_site( $site_id );

	// Bail if site does not exist
	if ( empty( $site ) ) {
		return new WP_Error( 'blog_not_exist', __( 'Site does not exist.', 'wp-multi-network' ) );
	}

	// Main sites cannot be moved, to prevent breakage
	if ( is_main_site( $site->id, $site->network_id ) ) {
		return true;
	}

	// Cast new network ID
	$new_network_id = (int) $new_network_id;

	// Return early if site does not need to be moved
	if ( $new_network_id === (int) $site->network_id ) {
		return true;
	}

	// Move the site is the blogs table
	$where  = array( 'blog_id' => $site->id       );
	$update = array( 'site_id' => $new_network_id );
	$result = $wpdb->update( $wpdb->blogs, $update, $where );

	// Bail if site could not be moved
	if ( empty( $result ) ) {
		return new WP_Error( 'blog_not_moved', __( 'Site could not be moved.', 'wp-multi-network' ) );
	}

	// Update old network count
	if ( 0 !== $site->network_id ) {
		_wp_update_network_counts( $site->network_id );
	}

	// Update new network count
	if ( 0 !== $new_network_id ) {
		_wp_update_network_counts( $new_network_id );
	}

	// Refresh blog details
	refresh_blog_details( $site_id );

	// Clean network caches
	clean_network_cache( array_filter( array(
		$site->network_id,
		$new_network_id
	) ) );

	// Site moved
	do_action( 'move_site', $site_id, $site->network_id, $new_network_id );

	// Return the new network ID as confirmation
	return $new_network_id;
}
endif;

if ( ! function_exists( 'network_options_list' ) ) :
/**
 * Return list of URL-dependent options
 *
 * @since 1.3
 * @return array
 */
function network_options_list() {
	return apply_filters( 'network_options_list', array(
		'siteurl',
		'home'
	) );
}
endif;

if ( ! function_exists( 'network_options_to_copy' ) ) :
/**
 * Return list of default options to copy
 *
 * @since 1.3
 * @return array
 */
function network_options_to_copy() {
	return apply_filters( 'network_options_to_copy', array(
		'admin_email'           => __( 'Network admin email'                    , 'wp-multi-network' ),
		'admin_user_id'         => __( 'Admin user ID - deprecated'             , 'wp-multi-network' ),
		'allowed_themes'        => __( 'OLD List of allowed themes - deprecated', 'wp-multi-network' ),
		'allowedthemes'         => __( 'List of allowed themes'                 , 'wp-multi-network' ),
		'banned_email_domains'  => __( 'Banned email domains'                   , 'wp-multi-network' ),
		'first_post'            => __( 'Content of first post on a new blog'    , 'wp-multi-network' ),
		'limited_email_domains' => __( 'Permitted email domains'                , 'wp-multi-network' ),
		'ms_files_rewriting'    => __( 'Uploaded file handling'                 , 'wp-multi-network' ),
		'site_admins'           => __( 'List of network admin usernames'        , 'wp-multi-network' ),
		'upload_filetypes'      => __( 'List of allowed file types for uploads' , 'wp-multi-network' ),
		'welcome_email'         => __( 'Content of welcome email'               , 'wp-multi-network' )
	) );
}
endif;
