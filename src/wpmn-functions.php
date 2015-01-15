<?php

/**
 * WP Multi Network Functions
 *
 * @package WPMN
 * @subpackage Functions
 */

// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) exit;

/**
 * Check to see if a network exists. Will check the networks object before
 * checking the database.
 *
 * @since 1.3
 *
 * @param integer $site_id ID of network to verify
 * @return boolean true if found, false otherwise
 */
function network_exists( $site_id ) {
	global $wpdb;

	// Cast
	$site_id = (int) $site_id;
	$sites   = get_networks();

	// Loop through sites global and look for a match
	foreach ( $sites as $network ) {
		if ( $site_id == $network->id ) {
			return true;
		}
	}

	// Loop through network list in db and look for a match
	$network_list = $wpdb->get_results( 'SELECT id FROM ' . $wpdb->site );
	if ( !empty( $network_list ) ) {
		foreach ( $network_list as $network ) {
			if ( $site_id == $network->id ) {
				return true;
			}
		}
	}

	// Nothing found
	return false;
}

/**
 * Get all networks
 *
 * @return array Networks available on the installation
 */
function get_networks() {
	global $sites, $wpdb;
	if ( empty( $sites ) ) {
		$sites = $wpdb->get_results( "SELECT * FROM $wpdb->site" );
	}
	return $sites;
}

/**
 * Problem: the various *_site_options() functions operate only on the current network
 * Workaround: change the current network
 *
 * @since 1.3
 * @param integer $new_network ID of network to manipulate
 */
function switch_to_network( $new_network = 0, $validate = false ) {
	global $old_network_details, $wpdb, $site_id, $switched_network, $switched_network_stack, $current_site;

	if ( empty( $new_network ) )
		$new_network = $site_id;

	if ( ( true == $validate ) && !network_exists( $new_network ) )
		return false;

	if ( empty( $switched_network_stack ) )
		$switched_network_stack = array();

	$switched_network_stack[] = $site_id;

	/**
	 * If we're switching to the same network id that we're on,
	 * set the right vars, do the associated actions, but skip
	 * the extra unnecessary work
	 */
	if ( $site_id == $new_network ) {
		do_action( 'switch_network', $site_id, $site_id );
		$switched_network = true;
		return true;
	}

	// backup
	$old_network_details['site_id']   = $site_id;
	$old_network_details['id']        = $current_site->id;
	$old_network_details['domain']    = $current_site->domain;
	$old_network_details['path']      = $current_site->path;
	$old_network_details['site_name'] = $current_site->site_name;
	$old_network_details['blog_id']   = $current_site->blog_id;

	$sites = get_networks();
	foreach ( $sites as $network ) {
		if ( $network->id == $new_network ) {
			$current_site = $network;
			break;
		}
	}

	$wpdb->siteid            = $new_network;
	$current_site->site_name = get_site_option( 'site_name' );
	$current_site->blog_id   = $wpdb->get_var( 
		$wpdb->prepare( 
			'SELECT blog_id FROM ' . $wpdb->blogs . ' WHERE site_id=%d AND domain=%s AND path=%s',
			$new_network,
			$current_site->domain,
			$current_site->path
		)
	);

	$prev_site_id            = $site_id;
	$site_id                 = $new_network;

	do_action( 'switch_network', $site_id, $prev_site_id );
	$switched_network = true;

	return true;
}

/**
 * Return to the current network
 *
 * @since 1.3
 */
function restore_current_network() {
	global $old_network_details, $wpdb, $site_id, $switched_network, $switched_network_stack, $current_site;

	if ( false == $switched_network )
		return false;

	if ( !is_array( $switched_network_stack ) )
		return false;

	$site_id = array_pop( $switched_network_stack );

	if ( $site_id == $current_site->id ) {
		do_action( 'switch_site', $site_id, $site_id );
		$switched_network = ( is_array( $switched_network_stack ) && count( $switched_network_stack ) > 0 );
		return true;
	}

	$prev_site_id            = $wpdb->siteid;
	$wpdb->siteid            = $site_id;
	$current_site->id        = $old_network_details['id'];
	$current_site->domain    = $old_network_details['domain'];
	$current_site->path      = $old_network_details['path'];
	$current_site->site_name = $old_network_details['site_name'];
	$current_site->blog_id   = $old_network_details['blog_id'];

	unset( $old_network_details );

	do_action( 'switch_network', $site_id, $prev_site_id );
	$switched_network = false;
}

/**
 * Add a new network
 *
 * @since 1.3
 *
 * @param string $domain Domain name for new network - for VHOST=no, this
 *                        should be FQDN, otherwise domain only
 * @param string $path Path to root of network hierarchy - should be '/' unless
 *                      WP is cohabiting with another product on a domain
 * @param string $site_name Name of the root blog to be created on the new
 *                           network
 * @param integer $clone_network ID of network whose networkmeta values are
 *                                to be copied - default NULL
 * @param array $options_to_clone Override default network meta options to copy
 *                                 when cloning - default NULL
 * @return integer ID of newly created network
 */
function add_network( $domain, $path, $site_name = false, $clone_network = false, $options_to_clone = false ) {
	global $wpdb, $sites;

	// Set a default site name if one isn't set
	if ( false == $site_name )
		$site_name = __( 'New Network Root' );

	// If no options, fallback on defaults
	if ( empty( $options_to_clone ) )
		$options_to_clone = array_keys( network_options_to_copy() );

	// Check for existing network
	$network = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . $wpdb->site . ' WHERE domain = %s AND path = %s LIMIT 1', $domain, $path ) );

	if ( !empty( $network ) )
		return new WP_Error( 'network_exists', __( 'Network already exists.' ) );

	// Insert new network
	$wpdb->insert( $wpdb->site, array(
		'domain' => $domain,
		'path'   => $path
	) );
	$new_network_id = $wpdb->insert_id;

	// Update global network list
	$sites = $wpdb->get_results( "SELECT * FROM {$wpdb->site}" );

	// If network was created, create a blog for it too
	if ( !empty( $new_network_id ) ) {

		if ( !defined( 'WP_INSTALLING' ) ) {
			define( 'WP_INSTALLING', true );
		}

		// there's an ongoing error with wpmu_create_blog that throws a warning if meta is not defined:
		// http://core.trac.wordpress.org/ticket/20793
		// temporary fix -- set from current blog's value
		// Looks like a fix is in for 3.7

		$new_blog_id = wpmu_create_blog( 
			$domain, 
			$path, 
			$site_name, 
			get_current_user_id(), 
			array( 'public' => get_option( 'blog_public', false ) ), 
			(int) $new_network_id )
		;

		// Bail if blog could not be created
		if ( is_a( $new_blog_id, 'WP_Error' ) ) {
			return $new_blog_id;
		}

		/**
		 * Fix upload_path for main sites on secondary networks
		 * This applies only to new installs (WP 3.5+)
		 */

		// Switch to main network (if it exists)
		if ( defined( 'SITE_ID_CURRENT_SITE' ) && network_exists( SITE_ID_CURRENT_SITE ) ) {
			switch_to_network( SITE_ID_CURRENT_SITE );
			$use_files_rewriting = get_site_option( 'ms_files_rewriting' );
			restore_current_network();
		} else {
			$use_files_rewriting = get_site_option( 'ms_files_rewriting' );
		}
		
		global $wp_version;
		
		// Create the upload_path and upload_url_path values
		if( ! $use_files_rewriting && version_compare( $wp_version, '3.7', '<' ) ) {

			// WP_CONTENT_URL is locked to the current site and can't be overridden,
			//  so we have to replace the hostname the hard way
			$current_siteurl = get_option( 'siteurl' );
			$new_siteurl = untrailingslashit( get_blogaddress_by_id( $new_blog_id ) );
			$upload_url = str_replace( $current_siteurl, $new_siteurl, WP_CONTENT_URL );
			$upload_url = $upload_url . '/uploads';
			
			$upload_dir = WP_CONTENT_DIR;
			if( 0 === strpos( $upload_dir, ABSPATH ) ) {
				$upload_dir = substr( $upload_dir, strlen( ABSPATH ) );
			}
			$upload_dir .= '/uploads';
			
			if ( defined( 'MULTISITE' ) )
				$ms_dir = '/sites/' . $new_blog_id;
			else
				$ms_dir = '/' . $new_blog_id;
			
			$upload_dir .= $ms_dir;
			$upload_url .= $ms_dir;
			
			update_blog_option( $new_blog_id, 'upload_path', $upload_dir );
			update_blog_option( $new_blog_id, 'upload_url_path', $upload_url );
			
		}

	}

	// Clone the network meta from an existing network
	if ( !empty( $clone_network ) && network_exists( $clone_network ) ) {

		$options_cache = array();
		$clone_network = (int) $clone_network;

		switch_to_network( $clone_network );

		foreach ( $options_to_clone as $option ) {
			$options_cache[$option] = get_site_option( $option );
		}

		restore_current_network();
		switch_to_network( $new_network_id );

		foreach( $options_to_clone as $option ) {
			if ( isset( $options_cache[$option] ) ) {
				
				// Fix for strange bug that prevents writing the ms_files_rewriting value for new networks
				if ( $option == 'ms_files_rewriting' ) {
					$wpdb->insert( $wpdb->sitemeta, array('site_id' => $wpdb->siteid, 'meta_key' => $option, 'meta_value' => $options_cache[$option] ) );
				} else {
					add_site_option( $option, $options_cache[$option] );
				}
			}
		}
		unset($options_cache);

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

	$id = (int) $id;

	// Bail if network does not exist
	if ( !network_exists( $id ) )
		return new WP_Error( 'network_not_exist', __( 'Network does not exist.' ) );

	// Bail if site for network is missing
	$network = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->site} WHERE id = %d", $id ) );
	if ( empty( $network ) )
		return new WP_Error( 'network_not_exist', __( 'Network does not exist.' ) );

	// Set the arrays for updating the db
	$update = array( 'domain' => $domain );
	if ( !empty( $path ) )
		$update['path'] = $path;

	// Attempt to update the network
	$where         = array( 'id' => $id );
	$update_result = $wpdb->update( $wpdb->site, $update, $where );

	// Bail if update failed
	if ( empty( $update_result ) )
		return new WP_Error( 'network_not_updated', __( 'Network could not be updated.' ) );

	$path      = !empty( $path ) ? $path : $network->path;
	$full_path = untrailingslashit( $domain . $path );
	$old_path  = untrailingslashit( $network->domain . $network->path );

	// Also update any associated blogs
	$sites = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->blogs} WHERE site_id = %d", $id ) );

	if ( ! empty( $sites ) ) {

		// Loop through sites and update domain/path
		foreach ( $sites as $site ) {
			
			$update = array();
			
			if( $network->domain !== $domain ) {
				$update['domain'] = str_replace( $network->domain, $domain, $site->domain );
			}

			if( $network->path !== $path ) {
				$search = sprintf( '|^%s|', preg_quote( $network->path, '|' ) );
				$update['path'] = preg_replace( $search, $path, $site->path, 1 );
			}

			if( empty( $update ) )
				continue;
			
			$where = array( 'blog_id' => (int) $site->blog_id );
			$wpdb->update( $wpdb->blogs, $update, $where );

			// Fix options table values
			$option_table = $wpdb->get_blog_prefix( $site->blog_id ) . 'options';

			// Loop through options and correct a few of them
			foreach ( network_options_list() as $option_name ) {
				$option_value = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$option_table} WHERE option_name = %s", $option_name ) );
				if ( !empty( $option_value ) ) {
					$new_value = str_replace( $old_path, $full_path, $option_value->option_value );
					update_blog_option( $site->blog_id, $option_name, $new_value );
				}
			}
		}
	}

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

	$id = (int) $id;

	// Ensure we have a valid network id
	$network = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->site} WHERE id = %d", $id ) );

	// Bail if network does not exist
	if ( empty( $network ) )
		return new WP_Error( 'network_not_exist', __( 'Network does not exist.' ) );

	// ensure there are no blogs attached to this network */
	$sites = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->blogs} WHERE site_id = %d", $id ) );

	// Bail if network has blogs and blog deletion is off
	if ( ( false == $delete_blogs ) && !empty( $sites ) )
		return new WP_Error( 'network_not_empty', __( 'Cannot delete network with sites.' ) );

	// Are we rescuing orphans or deleting them?
	if ( ( true == $delete_blogs )  && !empty( $sites ) ) {
		foreach ( $sites as $site ) {
			if ( RESCUE_ORPHANED_BLOGS && ENABLE_NETWORK_ZERO ) {
				move_site( $site->blog_id, 0 );
			} else {
				wpmu_delete_blog( $site->blog_id, true );
			}
		}
	}

	// Delete from sites table
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->site} WHERE id = %d", $id ) );

	// Delete from site meta table
	$wpdb->query( $wpdb->prepare( $query = "DELETE FROM {$wpdb->sitemeta} WHERE site_id = %d", $id ) );

	do_action( 'delete_network', $network );
}

/**
 * Move a blog from one network to another
 *
 * @since 1.3
 *
 * @param integer $site_id ID of blog to move
 * @param integer $new_network_id ID of destination network
 */
function move_site( $site_id, $new_network_id ) {
	global $wpdb;

	$site_id = (int) $site_id;

	// Sanity checks
	$site  = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->blogs} WHERE blog_id = %d", $site_id ) );

	// Bail if site does not exist
	if ( empty( $site ) )
		return new WP_Error( 'blog_not_exist', __( 'Site does not exist.' ) );

	// Return early if site does not need to be moved
	if ( (int) $new_network_id == $site->site_id )
		return true;

	// Store the old network ID for using later
	$old_network_id = $site->site_id;

	// Allow 0 network?
	if ( ENABLE_NETWORK_ZERO && ( 0 == $site->site_id ) ) {
		$old_network->domain = 'holding.blogs.local';
		$old_network->path   = '/';
		$old_network->id     = 0;

	// Make sure old network exists
	} else {
		$old_network = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->site} WHERE id = %d", $site->site_id ) );
		if ( empty( $old_network ) ) {
			return new WP_Error( 'network_not_exist', __( 'Network does not exist.' ) );
		}
	}

	// Allow 0 network?
	if ( ENABLE_NETWORK_ZERO && ( 0 == $new_network_id ) ) {
		$new_network->domain = 'holding.blogs.local';
		$new_network->path   = '/';
		$new_network->id     = 0;

	// Make sure destination network exists
	} else {
		$new_network = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->site} WHERE id = %d", $new_network_id ) );
		if ( empty( $new_network ) ) {
			return new WP_Error( 'network_not_exist', __( 'Network does not exist.' ) );
		}
	}

	// Tweak the domain and path as needed
	// If the site domain is the same as the network domain on a subdomain install, don't prepend old "hostname"
	if ( is_subdomain_install() && ( $site->domain != $old_network->domain ) ) {
		$ex_dom = substr( $site->domain, 0, ( strpos( $site->domain, '.' ) + 1 ) );
		$domain = $ex_dom . $new_network->domain;
	} else {
		$domain = $new_network->domain;
	}
	$path = $new_network->path . substr( $site->path, strlen( $old_network->path ) );

	// Move the site
	$update = array(
		'site_id' => $new_network->id,
		'domain'  => $domain,
		'path'    => $path
	);
	$where = array( 'blog_id' => $site->blog_id );
	$update_result = $wpdb->update( $wpdb->blogs, $update, $where );

	// Bail if site could not be moved
	if ( empty( $update_result ) )
		return new WP_Error( 'blog_not_moved', __( 'Site could not be moved.' ) );

	// Change relevant blog options
	$options_table = $wpdb->get_blog_prefix( $site->blog_id ) . 'options';
	$old_domain    = $old_network->domain . $old_network->path;
	$new_domain    = $new_network->domain . $new_network->path;

	// Update all site options
	foreach ( network_options_list() as $option_name ) {
		$option    = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $options_table WHERE option_name = %s", $option_name ) );
		$new_value = str_replace( $old_domain, $new_domain, $option->option_value );
		update_blog_option( $site->blog_id, $option_name, $new_value );
	}

	do_action( 'move_site', $site_id, $old_network_id, $new_network_id );
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
		'admin_email'           => __( 'Network admin email'                     ),
		'admin_user_id'         => __( 'Admin user ID - deprecated'              ),
		'allowed_themes'        => __( 'OLD List of allowed themes - deprecated' ),
		'allowedthemes'         => __( 'List of allowed themes'                  ),
		'banned_email_domains'  => __( 'Banned email domains'                    ),
		'first_post'            => __( 'Content of first post on a new blog'     ),
		'limited_email_domains' => __( 'Permitted email domains'                 ),
		'ms_files_rewriting'    => __( 'Uploaded file handling'                  ),
		'site_admins'           => __( 'List of network admin usernames'         ),
		'upload_filetypes'      => __( 'List of allowed file types for uploads'  ),
		'welcome_email'         => __( 'Content of welcome email'                )
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

	// If multisite, get some site meta
	if ( is_multisite() ) {

		// Get the network admins
		$network_admin_records = $wpdb->get_results( $wpdb->prepare( "SELECT site_id, meta_value FROM {$wpdb->sitemeta} WHERE meta_key = %s", 'site_admins' ) );

		// Setup the networks array
		$my_networks = array();

		foreach( (array) $network_admin_records as $network ) {
			$admins = maybe_unserialize( $network->meta_value );
			if ( in_array( $user_login, $admins ) ) {
				$my_networks[] = (int) $network->site_id;
			}
		}

	// If not multisite, use existing site
	} else {
		$my_networks = array();
	}

	// If there are no networks, return false
	if ( empty( $my_networks ) )
		$my_networks = false;

	return apply_filters( 'networks_user_is_network_admin', $my_networks, $user_id );
}

/**
 * Get the main network
 *
 * Uses the same logic as {@see is_main_network}, but returns the network object
 * instead.
 *
 * @return stdClass|null
 */
function wp_get_main_network() {
	global $wpdb;

	if ( ! is_multisite() ) {
		return null;
	}

	if ( defined( 'PRIMARY_NETWORK_ID' ) ) {
		return wp_get_network( (int) PRIMARY_NETWORK_ID );
	}

	$primary_network_id = (int) wp_cache_get( 'primary_network_id', 'site-options' );

	if ( $primary_network_id ) {
		return wp_get_network( $primary_network_id );
	}

	$primary_network_id = (int) $wpdb->get_var( "SELECT id FROM $wpdb->site ORDER BY id LIMIT 1" );
	wp_cache_add( 'primary_network_id', $primary_network_id, 'site-options' );

	return wp_get_network( $primary_network_id );
}
