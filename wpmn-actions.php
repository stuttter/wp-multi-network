<?php

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

/**
 * Blank out the value of upload_path when creating a new subsite
 */
if ( ! function_exists( 'wpmn_fix_subsite_upload_path' ) ) {
	/**
	 * Keep uploads for a newly-created subsite from being stored under the
	 * parent site when ms_files_rewriting is off.
	 *
	 * This is only needed for WP 3.5 - 3.6.1, so it can be removed once support 
	 * for those versions is dropped
	 * 
	 * @since 1.4
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
