<?php
/**
 * Deprecated functions.
 *
 * @package WPMN
 * @since 1.0.0
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'is_global_admin' ) ) :
	/**
	 * Checks whether the current user is a global administrator.
	 *
	 * @since 2.2.0
	 * @deprecated 2.3.0
	 *
	 * @return bool True if the user is a global administrator, false otherwise.
	 */
	function is_global_admin() {
		return (bool) apply_filters( 'is_global_admin', is_super_admin() );
	}
endif;

if ( ! function_exists( 'wpmn_fix_subsite_upload_path' ) ) {
	/**
	 * Keeps uploads for a newly-created subsite from being stored under the
	 * parent site when ms_files_rewriting is off.
	 *
	 * This is only needed for WP 3.5 - 3.6.1, so it can be removed once support
	 * for those versions is dropped.
	 *
	 * @since 1.4.0
	 * @deprecated
	 *
	 * @param string $value   Upload path option value.
	 * @param int    $blog_id Site ID.
	 */
	function wpmn_fix_subsite_upload_path( $value, $blog_id ) {
		global $current_site, $wp_version;

		if ( version_compare( $wp_version, '3.7', '<' ) ) {
			return $value;
		}

		if ( $blog_id === $current_site->blog_id ) {
			if ( ! get_option( 'WPLANG' ) ) {
				return '';
			}
		}

		return $value;
	}
	add_filter( 'blog_option_upload_path', 'wpmn_fix_subsite_upload_path', 10, 2 );
}
