<?php
/**
 * Multi-network functions.
 *
 * @package WPMN
 * @since 1.0.0
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'wp_should_rescue_orphaned_sites' ) ) :
	/**
	 * Checks whether orphaned sites should be rescued.
	 *
	 * This is checked based on the `RESCUE_ORPHANED_BLOGS` constant and an underlying filter.
	 *
	 * @since 2.0.0
	 *
	 * @return bool True if orphaned sites should be rescued, false otherwise.
	 */
	function wp_should_rescue_orphaned_sites() {
		$should = defined( 'RESCUE_ORPHANED_BLOGS' ) && ( true === RESCUE_ORPHANED_BLOGS );

		/**
		 * Filters whether orphaned sites should be rescued.
		 *
		 * @since 2.0.0
		 *
		 * @param bool $should Whether to rescue orphaned sites.
		 */
		return (bool) apply_filters( 'wp_should_rescue_orphaned_sites', $should );
	}
endif;

if ( ! function_exists( 'network_exists' ) ) :
	/**
	 * Checks to see if a network exists.
	 *
	 * @since 1.3.0
	 *
	 * @param int $network_id ID of network to verify.
	 * @return bool True if the network exists, false otherwise.
	 */
	function network_exists( $network_id ) {
		return ( null !== get_network( $network_id ) );
	}
endif;

if ( ! function_exists( 'user_has_networks' ) ) :
	/**
	 * Gets the array of networks for which user is an administrator.
	 *
	 * @since 1.3.0
	 *
	 * @param int $user_id Optional. User ID. Default is the current user.
	 * @return array|bool Array of network IDs, or false if none.
	 */
	function user_has_networks( $user_id = 0 ) {
		global $wpdb;

		if ( empty( $user_id ) ) {
			$user_info  = wp_get_current_user();
			$user_id    = $user_info->ID;
			$user_login = $user_info->user_login;
		} else {
			$user_id    = (int) $user_id;
			$user_info  = get_userdata( $user_id );
			$user_login = $user_info->user_login;
		}

		/**
		 * Filters the networks a user is the administrator of, to short-circuit the process.
		 *
		 * @since 2.0.0
		 *
		 * @param array|bool|null List of network IDs or false. Anything but null will short-circuit
		 *                        the process.
		 * @param int             User ID for which the networks should be returned.
		 */
		$my_networks = apply_filters( 'networks_pre_user_is_network_admin', null, $user_id );
		if ( null !== $my_networks ) {
			if ( empty( $my_networks ) ) {
				$my_networks = false;
			}

			/**
			 * Filters the networks a user is the administrator of.
			 *
			 * @since 2.0.0
			 *
			 * @param array|bool List of network IDs or false if no networks for the user.
			 * @param int        User ID for which the networks should be returned.
			 */
			return apply_filters( 'networks_user_is_network_admin', $my_networks, $user_id );
		}

		$my_networks = array();

		if ( is_multisite() ) {

			// phpcs:ignore WordPress.VIP.DirectDatabaseQuery.DirectQuery,WordPress.VIP.DirectDatabaseQuery.NoCaching
			$my_networks = array_map( 'intval', $wpdb->get_col( $wpdb->prepare( "SELECT site_id FROM {$wpdb->sitemeta} WHERE meta_key = %s AND meta_value LIKE %s", 'site_admins', '%"' . $user_login . '"%' ) ) );
		}

		// If there are no networks, return false.
		if ( empty( $my_networks ) ) {
			$my_networks = false;
		}

		/** This filter is documented in wp-multi-network/includes/functions.php */
		return apply_filters( 'networks_user_is_network_admin', $my_networks, $user_id );
	}
endif;

if ( ! function_exists( 'get_main_site_for_network' ) ) :
	/**
	 * Gets the main site for a network.
	 *
	 * @since 1.3.0
	 *
	 * @param int|WP_Network $network Optional. Network ID or object. Default is the current network.
	 * @return int Main site ID for the network.
	 */
	function get_main_site_for_network( $network = null ) {
		$network = get_network( $network );

		// Bail if network not found.
		if ( empty( $network ) ) {
			return false;
		}

		if ( ! empty( $network->blog_id ) ) {
			$primary_id = $network->blog_id;
		} else {
			$primary_id = wp_cache_get( "network:{$network->id}:main_site", 'site-options' );

			if ( false === $primary_id ) {
				$sites = get_sites( array(
					'network_id' => $network->id,
					'domain'     => $network->domain,
					'path'       => $network->path,
					'fields'     => 'ids',
					'number'     => 1,
				) );

				$primary_id = ! empty( $sites ) ? reset( $sites ) : 0;

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
	 * Checks whether a main site is a given site for a network.
	 *
	 * @since 1.7.0
	 *
	 * @param int $site_id Site ID to check if it's the main site.
	 * @return bool True if it is the main site, false otherwise.
	 */
	function is_main_site_for_network( $site_id ) {
		$site = get_site( $site_id );
		$main = get_main_site_for_network( $site->network_id );

		// Bail if no site or network was found.
		if ( empty( $main ) ) {
			return false;
		}

		return (int) $main === (int) $site_id;
	}
endif;

if ( ! function_exists( 'get_network_name' ) ) :
	/**
	 * Gets the name of the current network.
	 *
	 * @since 1.7.0
	 *
	 * @global WP_Network $current_site Current network object.
	 *
	 * @return string Name of the current network.
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
	 * Switches the current context to the given network.
	 *
	 * @since 1.3.0
	 *
	 * @global wpdb       $wpdb                   WordPress database abstraction object.
	 * @global bool       $switched_network       Whether the network context is switched.
	 * @global array      $switched_network_stack Stack of switched network objects.
	 * @global WP_Network $current_site           Current network object.
	 *
	 * @param int  $new_network Optional. ID of the network to switch to. Default is the current network ID.
	 * @param bool $validate    Optional. Whether to validate that the given network exists. Default false.
	 * @return bool True on successful switch, false on failure.
	 */
	function switch_to_network( $new_network = 0, $validate = false ) {
		global $wpdb, $switched_network, $switched_network_stack, $current_site;

		if ( empty( $new_network ) ) {
			$new_network = $current_site->id;
		}

		// Bail if network does not exist.
		if ( ( true === (bool) $validate ) && ! get_network( $new_network ) ) {
			return false;
		}

		if ( empty( $switched_network_stack ) ) {
			$switched_network_stack = array();
		}

		array_push( $switched_network_stack, $current_site );

		// If the same network, fire the hook and bail.
		if ( $current_site->id === $new_network ) {

			/**
			 * Fires when the current network context is switched.
			 *
			 * @since 1.3.0
			 *
			 * @param int $new_network_id ID of the network that is being switched to.
			 * @param int $old_network_id ID of the previously current network.
			 */
			do_action( 'switch_network', $current_site->id, $current_site->id );
			$switched_network = true;
			return true;
		}

		$prev_site_id = $current_site->id;
		$current_site = get_network( $new_network ); // phpcs:ignore WordPress.Variables.GlobalVariables.OverrideProhibited

		// Populate extra properties if not set already.
		if ( ! isset( $current_site->blog_id ) ) {
			$current_site->blog_id = get_main_site_for_network( $current_site );
		}
		if ( ! isset( $current_site->site_name ) ) {
			$current_site->site_name = get_network_name();
		}

		// Update network globals.
		$wpdb->siteid       = $current_site->id;
		$GLOBALS['site_id'] = $current_site->id; // phpcs:ignore WordPress.Variables.GlobalVariables.OverrideProhibited
		$GLOBALS['domain']  = $current_site->domain; // phpcs:ignore WordPress.Variables.GlobalVariables.OverrideProhibited

		/** This action is documented in wp-multi-network/includes/functions.php */
		do_action( 'switch_network', $current_site->id, $prev_site_id );

		$switched_network = true;

		return true;
	}
endif;

if ( ! function_exists( 'restore_current_network' ) ) :
	/**
	 * Restores the current context to the previous network.
	 *
	 * @since 1.3.0
	 *
	 * @global wpdb       $wpdb                   WordPress database abstraction object.
	 * @global bool       $switched_network       Whether the network context is switched.
	 * @global array      $switched_network_stack Stack of switched network objects.
	 * @global WP_Network $current_site           Current network object.
	 *
	 * @return bool True on successful restore, false on failure.
	 */
	function restore_current_network() {
		global $wpdb, $switched_network, $switched_network_stack, $current_site;

		// Bail if not switched.
		if ( true !== $switched_network ) {
			return false;
		}

		// Bail if no stack.
		if ( ! is_array( $switched_network_stack ) ) {
			return false;
		}

		$new_network = array_pop( $switched_network_stack );

		// If the same network, fire the hook and bail.
		if ( (int) $new_network->id === (int) $current_site->id ) {

			/** This action is documented in wp-multi-network/includes/functions.php */
			do_action( 'switch_network', $current_site->id, $current_site->id );
			$switched_network = ( ! empty( $switched_network_stack ) );
			return true;
		}

		$prev_network_id = $current_site->id;

		// Update network globals.
		$current_site       = $new_network; // phpcs:ignore WordPress.Variables.GlobalVariables.OverrideProhibited
		$wpdb->siteid       = $new_network->id;
		$GLOBALS['site_id'] = $new_network->id; // phpcs:ignore WordPress.Variables.GlobalVariables.OverrideProhibited
		$GLOBALS['domain']  = $new_network->domain; // phpcs:ignore WordPress.Variables.GlobalVariables.OverrideProhibited

		/** This action is documented in wp-multi-network/includes/functions.php */
		do_action( 'switch_network', $new_network->id, $prev_network_id );

		$switched_network = ! empty( $switched_network_stack );

		return true;
	}
endif;

if ( ! function_exists( 'insert_network' ) ) :
	/**
	 * Stores basic network info in the sites table.
	 *
	 * This function creates a row in the wp_site table and returns
	 * the new network ID. It is the first step in creating a new network.
	 *
	 * @since 2.2.0
	 *
	 * @global wpdb $wpdb WordPress database abstraction object.
	 *
	 * @param string $domain The domain of the new network.
	 * @param string $path   The path of the new network.
	 * @return int|bool|WP_Error The ID of the new network, or false on failure.
	 */
	function insert_network( $domain = '', $path = '/' ) {
		global $wpdb;

		// Bail if no domain or path.
		if ( empty( $domain ) ) {
			return new WP_Error(
				'network_empty',
				esc_html__( 'Domain and path cannot be empty.', 'wp-multi-network' )
			);
		}

		// Always end path with a slash.
		$path = trailingslashit( $path );

		// Query for networks.
		$networks = get_networks(
			array(
				'domain' => $domain,
				'path'   => $path,
				'number' => '1',
			)
		);

		// Bail if network already exists.
		if ( ! empty( $networks ) ) {
			return new WP_Error(
				'network_exists',
				esc_html__( 'Network already exists.', 'wp-multi-network' )
			);
		}

		// phpcs:ignore WordPress.VIP.DirectDatabaseQuery.DirectQuery
		$result = $wpdb->insert(
			$wpdb->site,
			array(
				'domain' => $domain,
				'path'   => $path,
			)
		);

		// Bail if database error.
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		// Bail if no result.
		if ( empty( $result ) ) {
			return false;
		}

		// Cast return value as int.
		$network_id = (int) $wpdb->insert_id;

		// Clean the network cache.
		clean_network_cache( $network_id );

		// Return network ID.
		return $network_id;
	}
endif;

if ( ! function_exists( 'add_network' ) ) :
	/**
	 * Adds a new network.
	 *
	 * @since 1.3.0
	 *
	 * @global wpdb $wpdb WordPress database abstraction object.
	 *
	 * @param array $args  {
	 *     Array of network arguments.
	 *
	 *     @type string  $domain           Domain name for new network - for VHOST=no,
	 *                                     this should be FQDN, otherwise domain only.
	 *     @type string  $path             Path to root of network hierarchy - should
	 *                                     be '/' unless WP is cohabiting with another
	 *                                     product on a domain.
	 *     @type string  $site_name        Name of the root blog to be created on
	 *                                     the new network.
	 *     @type string  $network_name     Name of the new network.
	 *     @type integer $user_id          ID of the user to add as the site owner.
	 *                                     Defaults to current user ID.
	 *     @type integer $network_admin_id ID of the user to add as the network administrator.
	 *                                     Defaults to current user ID.
	 *     @type array   $meta             Array of metadata to save to this network.
	 *                                     Defaults to array( 'public' => false ).
	 *     @type integer $clone_network    ID of network whose networkmeta values are
	 *                                     to be copied - default NULL.
	 *     @type array   $options_to_clone Override default network meta options to copy
	 *                                     when cloning - default NULL.
	 * }
	 * @return int|WP_Error ID of newly created network, or WP_Error on failure.
	 */
	function add_network( $args = array() ) {
		global $wpdb, $wp_version, $wp_db_version;

		$func_args = func_get_args();

		// Backward compatibility with old method of passing arguments.
		if ( ! is_array( $args ) || count( $func_args ) > 1 ) {

			// Log the deprecated arguments.
			_deprecated_argument(
				__METHOD__,
				'1.7.0',
				sprintf(
					/* translators: 1: method name, 2: file name */
					esc_html__( 'Arguments passed to %1$s should be in an associative array. See the inline documentation at %2$s for more details.', 'wp-multi-network' ),
					__METHOD__,
					__FILE__
				)
			);

			// Juggle function parameters.
			$old_args_keys = array(
				0 => 'domain',
				1 => 'path',
				2 => 'site_name',
				3 => 'clone_network',
				4 => 'options_to_clone',
			);

			// Reset arguments to empty array.
			$args = array();

			// Loop through deprecated keys and add items to array.
			foreach ( $old_args_keys as $arg_num => $arg_key ) {
				if ( isset( $func_args[ $arg_num ] ) ) {
					$args[ $arg_key ] = $func_args[ $arg_num ];
				}
			}
		}

		// Get the current user ID.
		$current_user_id = get_current_user_id();

		// Default site meta.
		$default_site_meta = array(
			'public' => get_option( 'blog_public', false ),
		);

		// Default network meta.
		$default_network_meta = array(
			'wpmu_upgrade_site'  => $wp_db_version,
			'initial_db_version' => $wp_db_version,
		);

		// Parse all of the arguments.
		$r = wp_parse_args( $args, array(

			// Site & network arguments.
			'domain'           => '',
			'path'             => '/',

			// Site arguments.
			'site_name'        => esc_attr__( 'New Site', 'wp-multi-network' ),
			'user_id'          => $current_user_id,
			'meta'             => $default_site_meta,

			// Network arguments.
			'network_name'     => esc_attr__( 'New Network', 'wp-multi-network' ),
			'network_admin_id' => $current_user_id,
			'network_meta'     => $default_network_meta,
			'clone_network'    => false,
			'options_to_clone' => array_keys( network_options_to_copy() ),
		) );

		// Bail if no user with the given ID for the site exists.
		if ( empty( $r['user_id'] ) || ! get_userdata( $r['user_id'] ) ) {
			return new WP_Error(
				'network_user',
				esc_html__( 'User does not exist.', 'wp-multi-network' ),
				array(
					'status' => 403,
				)
			);
		}

		// Bail if no user with the given ID for the network exists.
		if ( empty( $r['network_admin_id'] ) || ! get_userdata( $r['network_admin_id'] ) ) {
			return new WP_Error(
				'network_super_admin',
				esc_html__( 'User does not exist.', 'wp-multi-network' ),
				array(
					'status' => 403,
				)
			);
		}

		// Strip spaces out of domain & path.
		$r['domain'] = str_replace( ' ', '', strtolower( $r['domain'] ) );
		$r['path']   = str_replace( ' ', '', strtolower( $r['path'] ) );

		// Insert the new network.
		$new_network_id = insert_network( $r['domain'], $r['path'] );

		// Bail if insert returned an error.
		if ( empty( $new_network_id ) || is_wp_error( $new_network_id ) ) {
			return $new_network_id;
		}

		// Set the installation constant to true.
		if ( ! defined( 'WP_INSTALLING' ) ) {
			define( 'WP_INSTALLING', true );
		}

		// Switch to new network.
		switch_to_network( $new_network_id );

		// Make sure upload constants are defined.
		ms_upload_constants();

		// Attempt to create the site.
		$new_blog_id = wpmu_create_blog(
			$r['domain'],
			$r['path'],
			$r['site_name'],
			$r['user_id'],
			$r['meta'],
			$new_network_id
		);

		// Grant super admin priviledges.
		grant_super_admin( $r['network_admin_id'] );

		// Restore current network.
		restore_current_network();

		// Bail if main site could not be created.
		if ( is_wp_error( $new_blog_id ) ) {
			return $new_blog_id;
		}

		if ( empty( $r['network_meta']['site_name'] ) ) {
			$r['network_meta']['site_name'] = ! empty( $r['network_name'] )
				? $r['network_name']
				: $r['site_name'];
		}

		foreach ( $r['network_meta'] as $key => $value ) {
			update_network_option( $new_network_id, $key, $value );
		}

		// Fix upload path and URLs in WP < 3.7.
		$use_files_rewriting = defined( 'SITE_ID_CURRENT_SITE' ) && get_network( SITE_ID_CURRENT_SITE )
			? get_network_option( SITE_ID_CURRENT_SITE, 'ms_files_rewriting' )
			: get_site_option( 'ms_files_rewriting' );

		// Not using rewriting, and using a newer version of WordPress than 3.7.
		if ( empty( $use_files_rewriting ) && version_compare( $wp_version, '3.7', '>' ) ) {

			// WP_CONTENT_URL is locked to the current site and can't be overridden,
			// so we have to replace the hostname the hard way.
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

			update_blog_option( $new_blog_id, 'upload_path', $upload_dir );
			update_blog_option( $new_blog_id, 'upload_url_path', $upload_url );
		}

		// Clone network meta from existing network.
		if ( ! empty( $r['clone_network'] ) && get_network( $r['clone_network'] ) ) {

			// Define empty options cache array.
			$options_cache = array();

			// Get the values of options to clone.
			foreach ( $r['options_to_clone'] as $option ) {
				$options_cache[ $option ] = get_network_option( $r['clone_network'], $option );
			}

			// Clone options.
			foreach ( $r['options_to_clone'] as $option ) {

				// Skip if not set.
				if ( ! isset( $options_cache[ $option ] ) ) {
					continue;
				}

				// Fix for bug that prevents writing the ms_files_rewriting value for new networks.
				if ( 'ms_files_rewriting' === $option ) {
					// phpcs:ignore WordPress.VIP.DirectDatabaseQuery.DirectQuery
					$wpdb->insert( $wpdb->sitemeta, array(
						'site_id'    => $new_network_id,
						// phpcs:ignore WordPress.VIP.SlowDBQuery
						'meta_key'   => $option,
						// phpcs:ignore WordPress.VIP.SlowDBQuery
						'meta_value' => $options_cache[ $option ],
					) );
				} else {
					update_network_option( $new_network_id, $option, $options_cache[ $option ] );
				}
			}
		}

		// Update network counts.
		wp_update_network_counts( $new_network_id );

		// Clean the network cache.
		clean_network_cache( $new_network_id );

		/**
		 * Fires after a new network has been added.
		 *
		 * @since 1.3.0
		 *
		 * @param int   $new_network_id ID of the added network.
		 * @param array $r              Full associative array of network arguments.
		 */
		do_action( 'add_network', $new_network_id, $r );

		// Return new network ID.
		return $new_network_id;
	}
endif;

if ( ! function_exists( 'update_network' ) ) :
	/**
	 * Modifies the domain/path of a network, and updates all of its sites.
	 *
	 * @since 1.3.0
	 *
	 * @global wpdb $wpdb WordPress database abstraction object.
	 *
	 * @param int    $id     ID of network to modify.
	 * @param string $domain New domain for network.
	 * @param string $path   New path for network.
	 * @return bool|WP_Error True on success, or WP_Error on failure.
	 */
	function update_network( $id, $domain, $path = '' ) {
		global $wpdb;

		$network = get_network( $id );

		// Bail if network not found.
		if ( empty( $network ) ) {
			return new WP_Error( 'network_not_exist', __( 'Network does not exist.', 'wp-multi-network' ) );
		}

		$site_id = get_main_site_for_network( $id );
		$path    = wp_sanitize_site_path( $path );

		// Bail if site URL is invalid.
		if ( ! wp_validate_site_url( $domain, $path, $site_id ) ) {
			/* translators: %s: site domain and path */
			return new WP_Error( 'blog_bad', sprintf( __( 'The site "%s" is invalid, not available, or already exists.', 'wp-multi-network' ), $domain . $path ) );
		}

		// phpcs:ignore WordPress.VIP.DirectDatabaseQuery.DirectQuery
		$update_result = $wpdb->update(
			$wpdb->site,
			array(
				'domain' => $domain,
				'path'   => $path,
			),
			array(
				'id' => $network->id,
			)
		);
		if ( is_wp_error( $update_result ) ) {
			return new WP_Error( 'network_not_updated', __( 'Network could not be updated.', 'wp-multi-network' ) );
		}

		$path      = ! empty( $path ) ? $path : $network->path;
		$full_path = untrailingslashit( $domain . $path );
		$old_path  = untrailingslashit( $network->domain . $network->path );

		$sites = get_sites( array(
			'network_id' => $network->id,
		) );

		// Update network site domains and paths as necessary.
		if ( ! empty( $sites ) ) {
			foreach ( $sites as $site ) {
				$update = array();

				if ( $network->domain !== $domain ) {
					$update['domain'] = str_replace( $network->domain, $domain, $site->domain );
				}

				if ( $network->path !== $path ) {
					$search         = sprintf( '|^%s|', preg_quote( $network->path, '|' ) );
					$update['path'] = preg_replace( $search, $path, $site->path, 1 );
				}

				if ( empty( $update ) ) {
					continue;
				}

				// phpcs:ignore WordPress.VIP.DirectDatabaseQuery.DirectQuery
				$wpdb->update( $wpdb->blogs, $update, array(
					'blog_id' => (int) $site->id,
				) );

				$option_table = $wpdb->get_blog_prefix( $site->id ) . 'options';

				// Loop through URL-dependent options and correct them.
				foreach ( network_options_list() as $option_name ) {
					$value = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$option_table} WHERE option_name = %s", $option_name ) );  // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

					if ( ! empty( $value ) && ( false !== strpos( $value->option_value, $old_path ) ) ) {
						$new_value = str_replace( $old_path, $full_path, $value->option_value );
						update_blog_option( $site->id, $option_name, $new_value );
					}
				}

				// Clean the blog cache.
				clean_blog_cache( $site->id );
			}
		}

		// Update network counts.
		wp_update_network_counts( $network->id );

		// Clean the network cache.
		clean_network_cache( $network->id );

		/**
		 * Fires after an existing network has been updated.
		 *
		 * @since 1.3.0
		 *
		 * @param int   $network_id ID of the added network.
		 * @param array $args       Associative array of network arguments.
		 */
		do_action( 'update_network', $network->id, array(
			'domain' => $network->domain,
			'path'   => $network->path,
		) );

		return true;
	}
endif;

if ( ! function_exists( 'delete_network' ) ) :
	/**
	 * Deletes a network and all its sites.
	 *
	 * @since 1.3.0
	 *
	 * @global wpdb $wpdb WordPress database abstraction object.
	 *
	 * @param int  $network_id   ID of network to delete.
	 * @param bool $delete_blogs Flag to permit site deletion - default setting
	 *                           of false will prevent deletion of occupied networks.
	 * @return bool|WP_Error True on success, or WP_Error on failure.
	 */
	function delete_network( $network_id, $delete_blogs = false ) {
		global $wpdb;

		$network = get_network( $network_id );

		// Bail if network does not exist.
		if ( empty( $network ) ) {
			return new WP_Error( 'network_not_exist', __( 'Network does not exist.', 'wp-multi-network' ) );
		}

		$sites = get_sites( array(
			'network_id' => $network->id,
		) );
		if ( ! empty( $sites ) ) {

			// Bail if site deletion is off.
			if ( empty( $delete_blogs ) ) {
				return new WP_Error( 'network_not_empty', __( 'Cannot delete network with sites.', 'wp-multi-network' ) );
			}

			if ( true === $delete_blogs ) {
				foreach ( $sites as $site ) {
					if ( wp_should_rescue_orphaned_sites() ) {
						move_site( $site->id, 0 );
						continue;
					}

					wpmu_delete_blog( $site->id, true );
				}
			}
		}

		// phpcs:ignore WordPress.VIP.DirectDatabaseQuery.DirectQuery
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->site} WHERE id = %d", $network->id ) );

		// phpcs:ignore WordPress.VIP.DirectDatabaseQuery.DirectQuery
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->sitemeta} WHERE site_id = %d", $network->id ) );

		// Clean the network cache.
		clean_network_cache( $network->id );

		/**
		 * Fires after a network has been deleted.
		 *
		 * @since 1.3.0
		 *
		 * @param WP_Network $network The deleted network object.
		 */
		do_action( 'delete_network', $network );

		return true;
	}
endif;

if ( ! function_exists( 'move_site' ) ) :
	/**
	 * Moves a site to a new network.
	 *
	 * @since 1.3.0
	 *
	 * @global wpdb $wpdb WordPress database abstraction object.
	 *
	 * @param int $site_id        ID of site to move.
	 * @param int $new_network_id ID of destination network.
	 * @return int|bool|WP_Error New network ID on success, true if site cannot be moved,
	 *                           or WP_Error on failure.
	 */
	function move_site( $site_id = 0, $new_network_id = 0 ) {
		global $wpdb;

		$site = get_site( $site_id );

		// Cast network IDs to ints.
		$old_network_id = (int) $site->network_id;
		$new_network_id = (int) $new_network_id;

		// Bail if site does not exist.
		if ( empty( $site ) ) {
			return new WP_Error( 'blog_not_exist', __( 'Site does not exist.', 'wp-multi-network' ) );
		}

		// Bail if site is the main site.
		if ( is_main_site( $site->id, $old_network_id ) ) {
			return true;
		}

		// Bail if no change.
		if ( $new_network_id === $old_network_id ) {
			return true;
		}

		// Update the database entry.
		$result = $wpdb->update( // phpcs:ignore WordPress.VIP.DirectDatabaseQuery.DirectQuery
			$wpdb->blogs,
			array(
				'site_id' => $new_network_id,
			),
			array(
				'blog_id' => $site->id,
			)
		);

		// Bail if error.
		if ( empty( $result ) ) {
			return new WP_Error( 'blog_not_moved', __( 'Site could not be moved.', 'wp-multi-network' ) );
		}

		// Update old network counts.
		if ( 0 !== $old_network_id ) {
			wp_update_network_counts( $old_network_id );
		}

		// Update new network counts.
		if ( 0 !== $new_network_id ) {
			wp_update_network_counts( $new_network_id );
		}

		// Clean the blog cache.
		clean_blog_cache( $site_id );

		// Clean the network caches.
		clean_network_cache(
			array_filter(
				array(
					$site->network_id,
					$new_network_id,
				)
			)
		);

		/**
		 * Fires after a site has been moved to a new network.
		 *
		 * @since 1.3.0
		 *
		 * @param int $site_id        ID of the site that has been moved.
		 * @param int $old_network_id ID of the original network for the site.
		 * @param int $new_network_id ID of the network the site has been moved to.
		 */
		do_action( 'move_site', $site_id, $site->network_id, $new_network_id );

		return $new_network_id;
	}
endif;

if ( ! function_exists( 'network_options_list' ) ) :
	/**
	 * Lists the URL-dependent options.
	 *
	 * @since 1.3.0
	 *
	 * @return array List of network option names.
	 */
	function network_options_list() {
		$network_options = array(
			'siteurl',
			'home',
		);

		/**
		 * Filters the list of network options that depend on the domain and path of a network.
		 *
		 * @since 1.3.0
		 *
		 * @param array $network_options List of network option names.
		 */
		return apply_filters( 'network_options_list', $network_options );
	}
endif;

if ( ! function_exists( 'network_options_to_copy' ) ) :
	/**
	 * Lists the default network options to copy.
	 *
	 * @since 1.3.0
	 *
	 * @return array List of network $option_name => $option_label pairs.
	 */
	function network_options_to_copy() {
		$network_options = array(
			'admin_email'           => __( 'Network admin email', 'wp-multi-network' ),
			'admin_user_id'         => __( 'Admin user ID - deprecated', 'wp-multi-network' ),
			'allowed_themes'        => __( 'OLD List of allowed themes - deprecated', 'wp-multi-network' ),
			'allowedthemes'         => __( 'List of allowed themes', 'wp-multi-network' ),
			'banned_email_domains'  => __( 'Banned email domains', 'wp-multi-network' ),
			'first_post'            => __( 'Content of first post on a new blog', 'wp-multi-network' ),
			'limited_email_domains' => __( 'Permitted email domains', 'wp-multi-network' ),
			'ms_files_rewriting'    => __( 'Uploaded file handling', 'wp-multi-network' ),
			'site_admins'           => __( 'List of network admin usernames', 'wp-multi-network' ),
			'upload_filetypes'      => __( 'List of allowed file types for uploads', 'wp-multi-network' ),
			'welcome_email'         => __( 'Content of welcome email', 'wp-multi-network' ),
		);

		/**
		 * Filters the default network options to copy.
		 *
		 * @since 1.3.0
		 *
		 * @return array List of network $option_name => $option_label pairs.
		 */
		return apply_filters( 'network_options_to_copy', $network_options );
	}
endif;
