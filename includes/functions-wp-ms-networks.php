<?php

/**
 * WP Multi Network Functions
 *
 * @package WPMN
 * @subpackage Functions
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'wp_get_scheme' ) ) :
/**
 * Return the scheme in use based on is_ssl()
 *
 * @since 1.7.0
 *
 * @return string
 */
function wp_get_scheme() {
	return is_ssl()
		? 'https://'
		: 'http://';
}
endif;

/**
 * Check to see if a network exists. Will check the networks object before
 * checking the database.
 *
 * @since 1.3
 *
 * @param integer $network_id ID of network to verify
 */
function network_exists( $network_id ) {
	return wp_get_network( $network_id );
}

/**
 * Get all networks
 *
 * @since 1.0.0
 *
 * @return array Networks available on the installation
 */
function get_networks() {
	global $wpdb;

	return $wpdb->get_results( "SELECT * FROM {$wpdb->site}" );
}

/**
 * Get main site for a network
 *
 * @param int|stdClass $network Network ID or object, null for current network
 * @return int Main site ("blog" in old terminology) ID
 */
function get_main_site_for_network( $network = null ) {
	global $wpdb;

	// Get network
	if ( empty( $network ) ) {
		$network = $GLOBALS['current_site'];
	} elseif ( ! is_object( $network ) ) {
		$network = wp_get_network( $network );
	}

	// Network not found
	if ( empty( $network ) ) {
		return false;
	}

	$primary_id = isset( $network->blog_id ) ? $network->blog_id : wp_cache_get( 'network:' . $network->id . ':main_site', 'site-options' );
	if ( ! $primary_id ) {
		$sql        = "SELECT blog_id FROM {$wpdb->blogs} WHERE domain = %s AND path = %s";
		$query      = $wpdb->prepare( $sql, $network->domain, $network->path );
		$primary_id = $wpdb->get_var( $query );
		wp_cache_add( 'network:' . $network->id . ':main_site', $primary_id, 'site-options' );
	}

	return (int) $primary_id;
}

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
	$site = get_blog_details( $site_id );
	$main = get_main_site_for_network( $site->site_id );

	// Bail if no site or network was found
	if ( empty( $main ) ) {
		return false;
	}

	// Compare & return
	return ( (int) $main === (int) $site_id );
}

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

/**
 * Problem: the various *_site_options() functions operate only on the current network
 * Workaround: change the current network
 *
 * @since 1.3
 * @param integer $new_network ID of network to manipulate
 */
function switch_to_network( $new_network = 0, $validate = false ) {
	global $wpdb, $switched_network, $switched_network_stack, $current_site;

	if ( empty( $new_network ) ) {
		$new_network = $current_site->id;
	}

	if ( ( true == $validate ) && ! wp_get_network( $new_network ) ) {
		return false;
	}

	if ( empty( $switched_network_stack ) ) {
		$switched_network_stack = array();
	}

	array_push( $switched_network_stack, $current_site );

	/**
	 * If we're switching to the same network id that we're on,
	 * set the right vars, do the associated actions, but skip
	 * the extra unnecessary work
	 */
	if ( $current_site->id === $new_network ) {
		do_action( 'switch_network', $current_site->id, $current_site->id );
		$switched_network = true;
		return true;
	}

	// Switch the current site over
	$prev_site_id = $current_site->id;
	$current_site = wp_get_network( $new_network );

	// Figure out the current network's main site.
	if ( ! isset( $current_site->blog_id ) ) {
		$current_site->blog_id = get_main_site_for_network( $current_site );
	}

	if ( ! isset( $current_site->site_name ) ) {
		$current_site->site_name = get_network_name();
	}

	$wpdb->siteid       = $current_site->id;
	$GLOBALS['site_id'] = $current_site->id;

	do_action( 'switch_network', $current_site->id, $prev_site_id );
	$switched_network = true;

	return true;
}

/**
 * Return to the current network
 *
 * @since 1.3
 */
function restore_current_network() {
	global $wpdb, $switched_network, $switched_network_stack, $current_site;

	if ( false == $switched_network ) {
		return false;
	}

	if ( ! is_array( $switched_network_stack ) ) {
		return false;
	}

	$new_network = array_pop( $switched_network_stack );

	if ( $new_network->id == $current_site->id ) {
		do_action( 'switch_site', $current_site->id, $current_site->id );
		$switched_network = ( ! empty( $switched_network_stack ) );
		return true;
	}

	$prev_site_id       = $current_site->id;

	$current_site       = $new_network;
	$wpdb->siteid       = $new_network->id;
	$GLOBALS['site_id'] = $new_network->id;

	do_action( 'switch_network', $new_network->id, $prev_site_id );
	$switched_network = ( ! empty( $switched_network_stack ) );

	return true;
}

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
 *     @type integer $user_id          ID of the user to add as the site owner.
 *                                     Defaults to current user ID.
 *     @type array   $meta             Array of metadata to save to this network.
 *                                     Defaults to array( 'public' => false ).
 *     @type integer $clone_network    ID of network whose networkmeta values are
 *                                     to be copied - default NULL.
 *     @type array   $options_to_clone Override default network meta options to copy
 *                                     when cloning - default NULL.
 * }
 *
 * @return integer ID of newly created network
 */
function add_network( $args = array() ) {
	global $wpdb;

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

	// Parse args
	$r = wp_parse_args( $args, array(
		'domain'           => '',
		'path'             => '/',
		'site_name'        => __( 'New Network', 'wp-multi-network' ),
		'user_id'          => get_current_user_id(),
		'meta'             => array( 'public' => get_option( 'blog_public', false ) ),
		'clone_network'    => false,
		'options_to_clone' => array_keys( network_options_to_copy() )
	) );

	// Bail if no user with this ID
	if ( empty( $r['user_id'] ) || ! get_userdata( $r['user_id'] ) ) {
		return new WP_Error( 'network_user', __( 'User does not exist.', 'wp-multi-network' ) );
	}

	// Permissive sanitization for super admin usage
	$r['domain'] = str_replace( ' ', '', strtolower( $r['domain'] ) );
	$r['path']   = str_replace( ' ', '', strtolower( $r['path']   ) );

	// Check for existing network
	$sql     = "SELECT * FROM {$wpdb->site} WHERE domain = %s AND path = %s LIMIT 1";
	$query   = $wpdb->prepare( $sql, $r['domain'], $r['path'] );
	$network = $wpdb->get_row( $query );

	if ( ! empty( $network ) ) {
		return new WP_Error( 'network_exists', __( 'Network already exists.', 'wp-multi-network' ) );
	}

	// Insert new network
	$wpdb->insert( $wpdb->site, array(
		'domain' => $r['domain'],
		'path'   => $r['path']
	) );
	$new_network_id = $wpdb->insert_id;

	// If network was created, create a blog for it too
	if ( ! empty( $new_network_id ) ) {

		if ( ! defined( 'WP_INSTALLING' ) ) {
			define( 'WP_INSTALLING', true );
		}

		// Create the site for the root of this network
		$new_blog_id = wpmu_create_blog(
			$r['domain'],
			$r['path'],
			$r['site_name'],
			$r['user_id'],
			$r['meta'],
			$new_network_id
		);

		// Bail if blog could not be created
		if ( is_a( $new_blog_id, 'WP_Error' ) ) {
			return $new_blog_id;
		}

		/**
		 * Fix upload_path for main sites on secondary networks
		 * This applies only to new installs (WP 3.5+)
		 */

		// Switch to main network (if it exists)
		if ( defined( 'SITE_ID_CURRENT_SITE' ) && wp_get_network( SITE_ID_CURRENT_SITE ) ) {
			switch_to_network( SITE_ID_CURRENT_SITE );
			$use_files_rewriting = get_site_option( 'ms_files_rewriting' );
			restore_current_network();
		} else {
			$use_files_rewriting = get_site_option( 'ms_files_rewriting' );
		}

		global $wp_version;

		// Create the upload_path and upload_url_path values
		if ( ! $use_files_rewriting && version_compare( $wp_version, '3.7', '<' ) ) {

			// WP_CONTENT_URL is locked to the current site and can't be overridden,
			//  so we have to replace the hostname the hard way
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
	}

	// Clone the network meta from an existing network
	if ( ! empty( $r['clone_network'] ) && wp_get_network( $r['clone_network'] ) ) {

		$options_cache = array();

		// Old network
		switch_to_network( $r['clone_network'] );
		foreach ( $r['options_to_clone'] as $option ) {
			$options_cache[ $option ] = get_site_option( $option );
		}
		restore_current_network();

		// New network
		switch_to_network( $new_network_id );

		foreach ( $r['options_to_clone'] as $option ) {
			if ( isset( $options_cache[ $option ] ) ) {

				// Fix for bug that prevents writing the ms_files_rewriting
				// value for new networks.
				if ( 'ms_files_rewriting' === $option ) {
					$wpdb->insert( $wpdb->sitemeta, array(
						'site_id'    => $wpdb->siteid,
						'meta_key'   => $option,
						'meta_value' => $options_cache[ $option ]
					) );
				} else {
					add_site_option( $option, $options_cache[ $option ] );
				}
			}
		}
		unset( $options_cache );

		restore_current_network();
	}

	do_action( 'add_network', $new_network_id );

	return $new_network_id;
}

/**
 * Modify the domain/path of a network, and update all of its blogs
 *
 * @since 1.3
 *
 * @param integer id ID of network to modify
 * @param string $domain New domain for network
 * @param string $path New path for network
 */
function update_network( $id, $domain, $path = '' ) {
	global $wpdb;

	// Get network
	$network = wp_get_network( $id );

	// Bail if network not found
	if ( empty( $network ) ) {
		return new WP_Error( 'network_not_exist', __( 'Network does not exist.', 'wp-multi-network' ) );
	}

	// Set the arrays for updating the db
	$update = array( 'domain' => $domain );
	if ( ! empty( $path ) ) {
		$update['path'] = $path;
	}

	// Attempt to update the network
	$where         = array( 'id' => $network->id );
	$update_result = $wpdb->update( $wpdb->site, $update, $where );

	// Bail if update failed
	if ( empty( $update_result ) ) {
		return new WP_Error( 'network_not_updated', __( 'Network could not be updated.', 'wp-multi-network' ) );
	}

	$path      = ! empty( $path ) ? $path : $network->path;
	$full_path = untrailingslashit( $domain . $path );
	$old_path  = untrailingslashit( $network->domain . $network->path );

	// Also update any associated blogs
	$sql   = "SELECT * FROM {$wpdb->blogs} WHERE site_id = %d";
	$prep  = $wpdb->prepare( $sql, $network->id );
	$sites = $wpdb->get_results( $prep );

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
			$where = array( 'blog_id' => (int) $site->blog_id );
			$wpdb->update( $wpdb->blogs, $update, $where );

			// Fix options table values
			$option_table = $wpdb->get_blog_prefix( $site->blog_id ) . 'options';

			// Loop through options and correct a few of them
			foreach ( network_options_list() as $option_name ) {

				// Query
				$sql   = "SELECT * FROM {$option_table} WHERE option_name = %s";
				$prep  = $wpdb->prepare( $sql, $option_name );
				$value = $wpdb->get_row( $prep );

				// Update if value exists
				if ( ! empty( $value ) ) {
					$new_value = str_replace( $old_path, $full_path, $value->option_value );
					update_blog_option( $site->blog_id, $option_name, $new_value );
				}
			}
		}
	}

	// Network updated
	do_action( 'update_network', $id, array(
		'domain' => $network->domain,
		'path'   => $network->path
	) );
}

/**
 * Delete a network and all its blogs
 *
 * @param integer id ID of network to delete
 * @param boolean $delete_blogs Flag to permit blog deletion - default setting
 *                               of false will prevent deletion of occupied networks
 */
function delete_network( $id, $delete_blogs = false ) {
	global $wpdb;

	// Get network
	$network = wp_get_network( $id );

	// Bail if network does not exist
	if ( empty( $network ) ) {
		return new WP_Error( 'network_not_exist', __( 'Network does not exist.', 'wp-multi-network' ) );
	}

	// ensure there are no blogs attached to this network */
	$sql   = "SELECT * FROM {$wpdb->blogs} WHERE site_id = %d";
	$prep  = $wpdb->prepare( $sql, $network->id );
	$sites = $wpdb->get_results( $prep );

	// Bail if network has blogs and blog deletion is off
	if ( empty( $delete_blogs ) && ! empty( $sites ) ) {
		return new WP_Error( 'network_not_empty', __( 'Cannot delete network with sites.', 'wp-multi-network' ) );
	}

	// Are we rescuing orphans or deleting them?
	if ( ( true == $delete_blogs )  && ! empty( $sites ) ) {
		foreach ( $sites as $site ) {
			if ( RESCUE_ORPHANED_BLOGS ) {
				move_site( $site->blog_id, 0 );
			} else {
				wpmu_delete_blog( $site->blog_id, true );
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

	// Network deleted
	do_action( 'delete_network', $network );
}

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
	$site = get_blog_details( $site_id );

	// Bail if site does not exist
	if ( empty( $site ) ) {
		return new WP_Error( 'blog_not_exist', __( 'Site does not exist.', 'wp-multi-network' ) );
	}

	// Main sites cannot be moved, to prevent breakage
	if ( is_main_site_for_network( $site->blog_id ) ) {
		return true;
	}

	// Cast new network ID
	$new_network_id = (int) $new_network_id;

	// Return early if site does not need to be moved
	if ( $new_network_id === (int) $site->site_id ) {
		return true;
	}

	// Store the old network ID for using later
	$old_network_id = (int) $site->site_id;
	$old_network    = wp_get_network( $old_network_id );

	// No change
	if ( $old_network_id === $new_network_id ) {
		return new WP_Error( 'blog_not_moved', __( 'Site was not moved.', 'wp-multi-network' ) );
	}

	// New network is not zero
	if ( 0 !== $new_network_id ) {

		// Get the destination network
		$new_network = wp_get_network( $new_network_id );

		// Bail if no network
		if ( empty( $new_network ) ) {
			return new WP_Error( 'network_not_exist', __( 'Network does not exist.', 'wp-multi-network' ) );
		}

		// Default to keeping the same domain & path
		$domain = $site->domain;
		$path   = $site->path;

		// Look for nesting
		$site_path    = $site->domain        . $site->path;
		$network_path = $old_network->domain . $old_network->path;

		// Site is made up of parent network domain & path
		if ( false !== strpos( $site_path, $network_path ) ) {

			// Get the diff
			$diff = str_replace( $network_path, '', $site_path );

			// Subdomain
			if ( is_subdomain_install() ) {
				$slug   = str_replace( '/', '.', $diff );
				$domain = $slug . $new_network->domain;
				$path   = $new_network->path;

			// Subdirectory
			} else {
				$slug   = str_replace( '.', '/', $diff );
				$domain = $new_network->domain;
				$path   = $new_network->path . $slug;
			}
		}

	// New network is zero (orphan)
	} else {

		// Mock a zero network object
		$new_network = new WP_Network( (object) array(
			'domain' => 'network.zero',
			'path'   => '/',
			'id'     => '0'
		) );

		// Set domain & path to that of the current site
		$domain = $site->domain;
		$path   = $site->path;
	}

	// The silliest fixes in all of the land
	$domain = str_replace( '..', '.',       rtrim( $domain, '.' ) . '.' );
	$path   = str_replace( '//', '/', '/' . ltrim( $path,   '/' )       );

	// Move the site is the blogs table
	$where  = array( 'blog_id' => $site->blog_id );
	$update = array(
		'site_id' => $new_network->id,
		'domain'  => $domain,
		'path'    => $path
	);
	$update_result = $wpdb->update( $wpdb->blogs, $update, $where );

	// Bail if site could not be moved
	if ( empty( $update_result ) ) {
		return new WP_Error( 'blog_not_moved', __( 'Site could not be moved.', 'wp-multi-network' ) );
	}

	// Update old network count
	if ( 0 !== $old_network_id ) {
		switch_to_network( $old_network_id );
		wp_update_network_site_counts();
		restore_current_network();
	}

	// Update new network count
	if ( 0 !== $new_network_id ) {
		switch_to_network( $new_network_id );
		wp_update_network_site_counts();
		restore_current_network();
	}

	// Change relevant blog options
	$options_table = $wpdb->get_blog_prefix( $site->blog_id ) . 'options';
	$old_domain    = $old_network->domain . $old_network->path;
	$new_domain    = $new_network->domain . $new_network->path;

	// Update all site options
	foreach ( network_options_list() as $option_name ) {
		$sql    = "SELECT * FROM {$options_table} WHERE option_name = %s";
		$prep   = $wpdb->prepare( $sql, $option_name );
		$option = $wpdb->get_row( $prep );

		// No value, so skip it
		if ( empty( $option ) ) {
			continue;
		}

		$new_value = str_replace( $old_domain, $new_domain, $option->option_value );
		update_blog_option( $site->blog_id, $option_name, $new_value );
	}

	// Delete rewrite rules for site at old URL
	delete_blog_option( $site_id, 'rewrite_rules' );

	// Site moved
	do_action( 'move_site', $site_id, $old_network_id, $new_network_id );

	// Return the new network ID as confirmation
	return $new_network_id;
}

/**
 * Return list of URL-dependent options
 *
 * @since 1.3
 * @return array
 */
function network_options_list() {
	return apply_filters( 'network_options_list', array(
		'siteurl',
		'home',
		'fileupload_url'
	) );
}

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

/**
 *
 * Return array of networks for which user is super admin, or FALSE if none
 *
 * @since 1.3
 * @return array | FALSE
 */
function user_has_networks( $user_id = 0 ) {
	global $wpdb;

	// Use current user
	if ( empty( $user_id ) ) {
		global $current_user;

		$user_id    = $current_user->ID;
		$user_login = $current_user->user_login;

	// Use passed user ID
	} else {
		$user_id    = (int) $user_id;
		$user_info  = get_userdata( $user_id );
		$user_login = $user_info->user_login;
	}

	// Setup the networks array
	$my_networks = array();

	// If multisite, get some site meta
	if ( is_multisite() ) {

		// Get the network admins
		$sql        = "SELECT site_id, meta_value FROM {$wpdb->sitemeta} WHERE meta_key = %s";
		$query      = $wpdb->prepare( $sql, 'site_admins' );
		$all_admins = $wpdb->get_results( $query );

		foreach( (array) $all_admins as $network ) {
			$network_admins = maybe_unserialize( $network->meta_value );
			if ( in_array( $user_login, $network_admins, true ) ) {
				$my_networks[] = (int) $network->site_id;
			}
		}
	}

	// If there are no networks, return false
	if ( empty( $my_networks ) ) {
		$my_networks = false;
	}

	return apply_filters( 'networks_user_is_network_admin', $my_networks, $user_id );
}

if ( ! function_exists( 'wp_get_main_network' ) ) :
/**
 * Get the main network
 *
 * Uses the same logic as {@see is_main_network}, but returns the network object
 * instead.
 *
 * @return stdClass|null
 */
function wp_get_main_network() {

	// Bail if not multisite
	if ( ! is_multisite() ) {
		return null;
	}

	// Return main network ID
	return wp_get_network( get_main_network_id() );
}
endif;
