<?php

/**
 * WP Multi Network Compatibility
 *
 * @package Plugins/Networks/Compatibility
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'is_global_admin' ) ) :
	/**
	 * Can the current user edit all networks?
	 *
	 * @since 2.2.0
	 *
	 * @return boolean
	 */
	function is_global_admin() {
		return (bool) apply_filters( 'is_global_admin', is_super_admin() );
	}
endif;

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

if ( ! function_exists( 'wp_sanitize_site_path' ) ) :
/**
 * Sanitize a site path
 *
 * This function exists to prevent slashing issues while updating networks and
 * moving sites between networks.
 *
 * @since 1.8.0
 *
 * @param string $path
 *
 * @return string
 */
function wp_sanitize_site_path( $path = '' ) {
	$parts       = explode( '/', $path );
	$no_empties  = array_filter( $parts );
	$new_path    = implode( '/', $no_empties );
	$end_slash   = trailingslashit( $new_path );
	$left_trim   = ltrim( $end_slash, '/' );
	$front_slash = "/{$left_trim}";
	return $front_slash;
}
endif;

if ( ! function_exists( 'wp_validate_site_url' ) ) :
/**
 * Is a site URL okay to save?
 *
 * @since 1.8.0
 *
 * @global wpdb $wpdb
 *
 * @param string $domain
 * @param string $path
 * @param string $slug
 *
 * @return boolean
 */
function wp_validate_site_url( $domain, $path, $site_id = 0 ) {
	global $wpdb;

	// Does domain exist on this network
	$exists = domain_exists( $domain, $path, get_current_site()->id );

	// Bail if domain is current site ID
	if ( $exists == $site_id ) {
		return true;
	}

	// Bail if domain exists and it's not this site
	if ( true === $exists ) {
		return false;
	}

	// Bail if site is in signups table
	$signup = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->signups} WHERE domain = %s AND path = %s", $domain, $path ) );
	if ( ! empty( $signup ) ) {
		return false;
	}

	// Bail if user is a super admin
	if ( is_super_admin() ) {
		return true;
	}

	// Get pieces of domain & path
	$paths   = explode( '/', $path );
	$domains = substr_count( $domain, '.' ) > 1
		? (array) substr( $domain, 0, strpos( $domain, '.' ) )
		: array();
	$pieces  = array_filter( array_merge( $domains, $paths ) );

	// Loop through pieces
	foreach ( $pieces as $slug ) {

		// Bail if empty
		if ( empty( $slug ) ) {
			return false;
		}

		// Bail if not lowercase or numbers
		if ( preg_match( '/[^a-z0-9]+/', $slug ) ) {
			return false;
		}

		// All numeric?
		if ( preg_match( '/^[0-9]*$/', $slug ) ) {
			return false;
		}

		// Bail if less than 4 chars
		if ( strlen( $slug ) < 3 ) {
			return false;
		}

		// Get illegal names
		$illegal_names = get_site_option( 'illegal_names' );

		// Maybe merge reserved names
		if ( ! is_subdomain_install() ) {
			$illegal_names = array_merge( $illegal_names, get_subdirectory_reserved_names() );
		}

		// Bail if contains illegal names
		if ( in_array( $slug, $illegal_names, true ) ) {
			return false;
		}

		// Bail if username exists
		if ( username_exists( $slug ) ) {
			return false;
		}

		// Bail if subdirectory install and page exists on primary site of network
		if ( ! is_subdomain_install() ) {
			switch_to_blog( get_current_site()->blog_id );
			$page = get_page_by_path( $slug );
			restore_current_blog();
			if ( ! empty( $page ) ) {
				return false;
			}
		}
	}

	// Okay, s'all good
	return true;
}
endif;

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
	return get_network( get_main_network_id() );
}
endif;
