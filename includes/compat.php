<?php

/**
 * WP Multi Network Compatibility
 *
 * @package Plugins/Networks/Compatibility
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
